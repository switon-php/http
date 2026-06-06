<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit;

use Switon\Core\Attribute\Autowired;
use Switon\Core\FilesystemInterface;
use Switon\Core\PathAliasInterface;
use Switon\Http\Server\StaticHandler;
use Switon\Http\Server\StaticHandlerInterface;
use Switon\Http\Tests\TestCase;
use Switon\Routing\RouterInterface;

use function array_diff;
use function file_exists;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function rmdir;
use function scandir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class StaticHandlerTest extends TestCase
{
    #[Autowired] protected StaticHandlerInterface $handler;
    #[Autowired] protected RouterInterface $router;
    #[Autowired] protected PathAliasInterface $pathAlias;
    protected string $tempDir;
    protected string $publicDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/switon_static_test_' . uniqid();
        $this->publicDir = $this->tempDir . '/public';
        mkdir($this->publicDir, 0755, true);

        file_put_contents($this->publicDir . '/test.txt', 'test content');
        file_put_contents($this->publicDir . '/test.js', 'console.log("test");');
        mkdir($this->publicDir . '/css', 0755, true);
        file_put_contents($this->publicDir . '/css/style.css', 'body { margin: 0; }');
        $this->pathAlias->set('@public', $this->publicDir);

        $this->router = $this->createMock(RouterInterface::class);
        $this->router->method('getPrefix')->willReturn('/api');
        $this->container->replace(RouterInterface::class, $this->router);

        $this->handler = $this->container->make(StaticHandlerInterface::class, [
            'doc_root' => $this->publicDir,
            'locations' => ['/test.txt', '/test.js', '/css']
        ]);
        $this->container->replace(StaticHandlerInterface::class, $this->handler);

        // Property autowiring is automatically performed by parent::setUp()
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
        parent::tearDown();
    }

    protected function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testIsFileReturnsTrueForValidFileUri(): void
    {
        $this->assertTrue($this->handler->isFile('/api/test.txt'));
    }

    public function testIsFileReturnsFalseForInvalidFileUri(): void
    {
        $this->assertFalse($this->handler->isFile('/api/invalid.txt'));
    }

    public function testIsFileReturnsFalseForUriWithoutPrefix(): void
    {
        $this->assertFalse($this->handler->isFile('/test.txt'));
    }

    public function testIsFileReturnsFalseWhenUriIsOnlyRouterPrefix(): void
    {
        $this->assertFalse($this->handler->isFile('/api'));
        $this->assertFalse($this->handler->isFile('/api?x=1'));
    }

    public function testIsFileHandlesQueryStringInUri(): void
    {
        $this->assertTrue($this->handler->isFile('/api/test.txt?v=1'));
    }

    public function testGetFileReturnsFilePathForValidUri(): void
    {
        $file = $this->handler->getFile('/api/test.txt');
        if ($file === null) {
            $this->assertTrue($this->handler->isFile('/api/test.txt'));
            $this->assertFileExists($this->publicDir . '/test.txt');
        } else {
            $this->assertStringEndsWith('test.txt', $file);
            $this->assertFileExists($file);
        }
    }

    public function testGetFileReturnsNullForInvalidUri(): void
    {
        $file = $this->handler->getFile('/api/invalid.txt');
        $this->assertSame(null, $file);
    }

    public function testGetFileReturnsFilePathForNestedFile(): void
    {
        $file = $this->handler->getFile('/api/css/style.css');
        $this->assertNotNull($file);
        $this->assertStringEndsWith('/css/style.css', $file);
    }

    public function testIsFileReturnsFalseForDirectoryUri(): void
    {
        $this->assertFalse($this->handler->isFile('/api/css'));
    }

    public function testGetFileReturnsNullForDirectoryUri(): void
    {
        $this->assertNull($this->handler->getFile('/api/css'));
    }

    public function testGetMimeTypeReturnsCorrectMimeTypeForKnownExtension(): void
    {
        $mimeType = $this->handler->getMimeType('test.txt');
        $this->assertSame('text/plain', $mimeType);
    }

    public function testGetMimeTypeReturnsDefaultMimeTypeForUnknownExtension(): void
    {
        $mimeType = $this->handler->getMimeType('test.unknown');
        $this->assertSame('application/octet-stream', $mimeType);
    }

    public function testGetMimeTypeReturnsDefaultMimeTypeForFileWithoutExtension(): void
    {
        $mimeType = $this->handler->getMimeType('test');
        $this->assertSame('application/octet-stream', $mimeType);
    }

    public function testGetMimeTypesSkipsBlankLinesAndLinesWithoutSemicolon(): void
    {
        $fs = $this->createMock(FilesystemInterface::class);
        $fs->expects($this->once())
            ->method('read')
            ->with('@switon.http.resources/Server/mime.types')
            ->willReturn("\n\nno-semicolon\napplication/x-test tst;\n");

        $handler = $this->container->make(TestableStaticHandler::class, [
            'locations' => ['/probe-only'],
        ]);
        $handler->setFilesystemForTest($fs);

        $mimeTypes = $handler->exposedGetMimeTypes();

        $this->assertSame('application/x-test', $mimeTypes['tst'] ?? null);
    }

    /**
     * Lines with a semicolon but fewer than two whitespace-separated tokens after trimming
     * are ignored ({@see StaticHandler::getMimeTypes()} {@code count($parts) < 2}).
     */
    public function testGetMimeTypesSkipsLineWhenMimeLineHasNoExtensionTokens(): void
    {
        $fs = $this->createMock(FilesystemInterface::class);
        $fs->expects($this->once())
            ->method('read')
            ->with('@switon.http.resources/Server/mime.types')
            ->willReturn("application/x-orphan;\napplication/x-pair  pr1 pr2;\n");

        $handler = $this->container->make(TestableStaticHandler::class, [
            'locations' => ['/probe-only'],
        ]);
        $handler->setFilesystemForTest($fs);

        $mimeTypes = $handler->exposedGetMimeTypes();

        $this->assertCount(2, $mimeTypes);
        $this->assertSame('application/x-pair', $mimeTypes['pr1'] ?? null);
        $this->assertSame('application/x-pair', $mimeTypes['pr2'] ?? null);
    }

    public function testGetLocationsSkipsDotfilesAndPhpFilenames(): void
    {
        $fs = $this->createMock(FilesystemInterface::class);
        $fs->expects($this->once())
            ->method('glob')
            ->with('@public/*')
            ->willReturn([
                '@public/.well-known',
                '@public/index.php',
                '@public/assets',
                '@public/favicon.ico',
            ]);

        $handler = $this->container->make(TestableStaticHandler::class, [
            'locations' => ['/placeholder'],
        ]);
        $handler->setFilesystemForTest($fs);

        $locations = $handler->exposedGetLocations();

        $this->assertEqualsCanonicalizing(['/assets', '/favicon.ico'], $locations);
    }

    public function testGetFileInternalStripsQueryStringBeforeMatching(): void
    {
        $handler = $this->container->make(TestableStaticHandler::class, [
            'locations' => ['/test.txt', '/test.js', '/css'],
        ]);

        $this->assertSame('/test.txt', $handler->exposedGetFileInternal('/api/test.txt?cache=1&v=2'));
    }

    public function testGetFileInternalReturnsNullWhenNestedTopSegmentNotInLocations(): void
    {
        $handler = $this->container->make(TestableStaticHandler::class, [
            'locations' => ['/test.txt', '/test.js', '/css'],
        ]);

        $this->assertNull($handler->exposedGetFileInternal('/api/unknown-segment/file.txt'));
    }

    public function testGetFileReturnsNullWhenSymlinkTargetResolvesOutsidePublicRoot(): void
    {
        $outside = $this->tempDir . '/outside_' . uniqid('', true) . '.txt';
        file_put_contents($outside, 'leak');
        $link = $this->publicDir . '/css/out_' . uniqid('', true) . '.txt';
        if (!@symlink($outside, $link)) {
            $this->markTestSkipped('symlink() unavailable or denied');
        }
        try {
            $uri = '/api/css/' . basename($link);
            $this->assertNull($this->handler->getFile($uri));
            $this->assertFalse($this->handler->isFile($uri));
        } finally {
            @unlink($link);
            @unlink($outside);
        }
    }
}

/**
 * Test double: exposes {@see StaticHandler} protected helpers without reflection.
 *
 * @internal
 */
final class TestableStaticHandler extends StaticHandler
{
    public function setFilesystemForTest(FilesystemInterface $filesystem): void
    {
        $this->filesystem = $filesystem;
    }

    /** @return array<string, string> */
    public function exposedGetMimeTypes(): array
    {
        return $this->getMimeTypes();
    }

    /** @return list<string> */
    public function exposedGetLocations(): array
    {
        return $this->getLocations();
    }

    public function exposedGetFileInternal(string $uri): ?string
    {
        return $this->getFileInternal($uri);
    }
}

<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit;

use Switon\Core\Attribute\Autowired;
use Switon\Core\Filesystem;
use Switon\Core\FilesystemInterface;
use Switon\Core\PathAliasInterface;
use Switon\Http\Request\File;
use Switon\Http\Request\File\Exception as FileException;
use Switon\Http\Tests\TestCase;

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
class RequestFileTest extends TestCase
{
    #[Autowired] protected PathAliasInterface $pathAlias;
    #[Autowired] protected FilesystemInterface $filesystem;

    protected File $file;
    protected string $tempDir;
    protected string $testFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/switon_test_' . uniqid('', true);
        mkdir($this->tempDir, 0755, true);

        $this->testFile = $this->tempDir . '/test.txt';
        file_put_contents($this->testFile, 'test content');

        $this->file = $this->container->make(File::class, ['file' => $this->createFileData()]);
    }

    protected function setUpContainer(): void
    {
        parent::setUpContainer();

        $this->container->replace(FilesystemInterface::class, $this->container->make(Filesystem::class));

        // Property autowiring is automatically performed by parent::setUp()
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
        parent::tearDown();
    }

    protected function createFileData(
        string $name = 'test.txt',
        int    $error = UPLOAD_ERR_OK,
        int    $size = 12
    ): array {
        return [
            'key' => 'test_file',
            'name' => $name,
            'type' => 'text/plain',
            'tmp_name' => $this->testFile,
            'error' => $error,
            'size' => $size
        ];
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

    public function testGetKeyReturnsFileKey(): void
    {
        $this->assertSame('test_file', $this->file->getKey());
    }

    public function testGetNameReturnsFileName(): void
    {
        $this->assertSame('test.txt', $this->file->getName());
    }

    public function testGetSizeReturnsFileSize(): void
    {
        $this->assertSame(12, $this->file->getSize());
    }

    public function testGetTempNameReturnsTemporaryFileName(): void
    {
        $this->assertSame($this->testFile, $this->file->getTempName());
    }

    public function testGetTypeReturnsFileTypeFromDataWhenRealIsFalse(): void
    {
        $type = $this->file->getType(false);
        $this->assertSame('text/plain', $type);
    }

    public function testGetTypeReturnsRealMimeTypeWhenRealIsTrue(): void
    {
        $type = $this->file->getType(true);
        $this->assertIsString($type);
        $this->assertNotSame([], $type);
    }

    public function testGetErrorReturnsUploadErrorCodeAsString(): void
    {
        $error = $this->file->getError();
        $this->assertIsString($error);
        $this->assertSame((string)UPLOAD_ERR_OK, $error);
    }

    public function testGetExtensionReturnsFileExtension(): void
    {
        $this->assertSame('txt', $this->file->getExtension());
    }

    public function testGetExtensionReturnsEmptyStringForFilesWithoutExtension(): void
    {
        $file = $this->container->make(File::class, ['file' => $this->createFileData('test')]);
        $this->assertSame('', $file->getExtension());
    }

    public function testIsUploadedFileChecksIfFileIsUploaded(): void
    {
        $result = $this->file->isUploadedFile();
        $this->assertIsBool($result);
    }

    public function testMoveToMovesFileToDestination(): void
    {
        $this->pathAlias->set('@uploads', $this->tempDir . '/uploads');

        $destination = '@uploads/moved.txt';
        $this->file->moveTo($destination, 'jpg,jpeg,png,gif,doc,xls,pdf,zip,txt');

        $resolvedPath = $this->pathAlias->resolve($destination);
        $this->assertFileExists($resolvedPath);
        $this->assertFileDoesNotExist($this->testFile);
    }

    public function testMoveToThrowsExceptionForInvalidFileExtension(): void
    {
        $this->pathAlias->set('@uploads', $this->tempDir . '/uploads');

        $this->expectException(FileException::class);
        $this->file->moveTo('@uploads/invalid.exe', 'jpg,jpeg,png');
    }

    public function testMoveToThrowsExceptionWhenFileUploadFailed(): void
    {
        $file = $this->container->make(File::class, ['file' => $this->createFileData('test.txt', UPLOAD_ERR_PARTIAL)]);

        $this->pathAlias->set('@uploads', $this->tempDir . '/uploads');

        $this->expectException(FileException::class);
        $file->moveTo('@uploads/moved.txt');
    }

    public function testMoveToThrowsExceptionWhenDestinationExistsAndOverwriteIsFalse(): void
    {
        $this->pathAlias->set('@uploads', $this->tempDir . '/uploads');
        mkdir($this->tempDir . '/uploads', 0755, true);

        $destination = '@uploads/existing.txt';
        file_put_contents($this->pathAlias->resolve($destination), 'existing');

        $this->expectException(FileException::class);
        $this->file->moveTo($destination, '*', false);
    }

    public function testMoveToOverwritesExistingFileWhenOverwriteIsTrue(): void
    {
        $this->pathAlias->set('@uploads', $this->tempDir . '/uploads');
        mkdir($this->tempDir . '/uploads', 0755, true);

        $destination = '@uploads/existing.txt';
        $existingPath = $this->pathAlias->resolve($destination);
        file_put_contents($existingPath, 'existing');

        $this->file->moveTo($destination, '*', true);

        $this->assertFileExists($existingPath);
        $this->assertStringEqualsFile($existingPath, 'test content');
    }

    public function testMoveToAllowsAllExtensionsWhenAllowedExtensionsIsWildcard(): void
    {
        $this->pathAlias->set('@uploads', $this->tempDir . '/uploads');

        $destination = '@uploads/any.extension';
        $this->file->moveTo($destination, '*');

        $this->assertFileExists($this->pathAlias->resolve($destination));
    }

    public function testDeleteRemovesTemporaryFile(): void
    {
        $this->assertFileExists($this->testFile);
        $this->file->delete();
        $this->assertFileDoesNotExist($this->testFile);
    }

    public function testJsonSerializeReturnsFileData(): void
    {
        $data = $this->file->jsonSerialize();

        $this->assertIsArray($data);
        $this->assertArrayHasKey('key', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('type', $data);
        $this->assertArrayHasKey('tmp_name', $data);
        $this->assertArrayHasKey('error', $data);
        $this->assertArrayHasKey('size', $data);
        $this->assertSame('test_file', $data['key']);
        $this->assertSame('test.txt', $data['name']);
    }
}

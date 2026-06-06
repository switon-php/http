<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit;

use Switon\Core\Attribute\Autowired;
use Switon\Http\Event\RequestReceived;
use Switon\Http\Exception\BadRequestException;
use Switon\Http\RequestContext;
use Switon\Http\RequestInterface;
use Switon\Http\Tests\TestCase;
use Switon\Routing\MatcherInterface;

use function json_encode;

class RequestTest extends TestCase
{
    #[Autowired] protected RequestInterface $request;

    // setUp() is not needed - parent::setUp() automatically autowires this test case

    protected function createRequestEvent(
        array  $get = [],
        array  $post = [],
        array  $server = [],
        string $rawBody = '',
        array  $cookie = [],
        array  $files = []
    ): RequestReceived {
        return new RequestReceived(
            GET: $get,
            POST: $post,
            SERVER: $server,
            RAW_BODY: $rawBody,
            COOKIE: $cookie,
            FILES: $files
        );
    }

    public function testGetContextReturnsRequestContext(): void
    {
        $this->assertInstanceOf(RequestContext::class, $this->request->getContext());
    }

    public function testOnRequestReceivedPopulatesGetData(): void
    {
        $event = $this->createRequestEvent(
            get: ['name' => 'John', 'age' => '30'],
            server: ['REQUEST_METHOD' => 'GET']
        );

        $this->request->onRequestReceived($event);

        $this->assertSame('John', $this->request->query('name'));
        $this->assertSame('30', $this->request->query('age'));
        $this->assertSame('John', $this->request->get('name'));
    }

    public function testOnRequestReceivedPopulatesPostData(): void
    {
        $event = $this->createRequestEvent(
            post: ['email' => 'test@example.com', 'password' => 'secret'],
            server: ['REQUEST_METHOD' => 'POST']
        );

        $this->request->onRequestReceived($event);

        $this->assertSame('test@example.com', $this->request->post('email'));
        $this->assertSame('secret', $this->request->post('password'));
        $this->assertSame('test@example.com', $this->request->get('email'));
    }

    public function testOnRequestReceivedParsesJsonBody(): void
    {
        $jsonBody = json_encode(['name' => 'Jane', 'age' => 25]);
        $event = $this->createRequestEvent(
            server: [
                'REQUEST_METHOD' => 'POST',
                'CONTENT_TYPE' => 'application/json'
            ],
            rawBody: $jsonBody
        );

        $this->request->onRequestReceived($event);
        $this->request->parseBody();

        $this->assertSame('Jane', $this->request->post('name'));
        $this->assertSame(25, $this->request->post('age'));
    }

    public function testOnRequestReceivedParsesPlusJsonBody(): void
    {
        $jsonBody = json_encode(['name' => 'Jane', 'age' => 25]);
        $event = $this->createRequestEvent(
            server: [
                'REQUEST_METHOD' => 'POST',
                'CONTENT_TYPE' => 'application/problem+json; charset=utf-8'
            ],
            rawBody: $jsonBody
        );

        $this->request->onRequestReceived($event);
        $this->request->parseBody();

        $this->assertSame('Jane', $this->request->post('name'));
        $this->assertSame(25, $this->request->post('age'));
    }

    public function testOnRequestReceivedParsesFormDataBody(): void
    {
        $event = $this->createRequestEvent(
            server: [
                'REQUEST_METHOD' => 'POST',
                'CONTENT_TYPE' => 'application/x-www-form-urlencoded'
            ],
            rawBody: 'username=admin&password=secret123'
        );

        $this->request->onRequestReceived($event);
        $this->request->parseBody();

        $this->assertSame('admin', $this->request->post('username'));
        $this->assertSame('secret123', $this->request->post('password'));
    }

    public function testRawBodyReturnsRawRequestBody(): void
    {
        $rawBody = '{"key": "value"}';

        $event = $this->createRequestEvent(
            server: ['REQUEST_METHOD' => 'POST'],
            rawBody: $rawBody
        );

        $this->request->onRequestReceived($event);

        $this->assertSame($rawBody, $this->request->rawBody());
    }

    public function testAllReturnsMergedGetAndPostData(): void
    {
        $event = $this->createRequestEvent(
            get: ['id' => '123', 'action' => 'view'],
            post: ['name' => 'Test', 'action' => 'edit'],
            server: ['REQUEST_METHOD' => 'POST']
        );

        $this->request->onRequestReceived($event);

        $all = $this->request->all();

        $this->assertSame('123', $all['id']);
        $this->assertSame('Test', $all['name']);
        $this->assertSame('edit', $all['action']);
    }

    public function testOnlyReturnsOnlySpecifiedFields(): void
    {
        $event = $this->createRequestEvent(
            post: ['name' => 'John', 'email' => 'john@example.com', 'password' => 'secret'],
            server: ['REQUEST_METHOD' => 'POST']
        );

        $this->request->onRequestReceived($event);

        $only = $this->request->only(['name', 'email']);

        $this->assertArrayHasKey('name', $only);
        $this->assertArrayHasKey('email', $only);
        $this->assertArrayNotHasKey('password', $only);
        $this->assertSame('John', $only['name']);
        $this->assertSame('john@example.com', $only['email']);
    }

    public function testExceptReturnsAllFieldsExceptSpecified(): void
    {
        $event = $this->createRequestEvent(
            post: ['name' => 'John', 'email' => 'john@example.com', 'password' => 'secret'],
            server: ['REQUEST_METHOD' => 'POST']
        );

        $this->request->onRequestReceived($event);

        $except = $this->request->except(['password']);

        $this->assertArrayHasKey('name', $except);
        $this->assertArrayHasKey('email', $except);
        $this->assertArrayNotHasKey('password', $except);
    }

    public function testGetReturnsValueFromRequestOrAttributes(): void
    {
        $event = $this->createRequestEvent(
            get: ['param' => 'value1'],
            server: ['REQUEST_METHOD' => 'GET']
        );

        $this->request->onRequestReceived($event);

        $this->assertSame('value1', $this->request->get('param'));
        $this->assertSame(null, $this->request->get('nonexistent'));
        $this->assertSame('default', $this->request->get('nonexistent', 'default'));
    }

    public function testHasChecksIfKeyExists(): void
    {
        $event = $this->createRequestEvent(
            get: ['exists' => 'value'],
            server: ['REQUEST_METHOD' => 'GET']
        );

        $this->request->onRequestReceived($event);

        $this->assertTrue($this->request->has('exists'));
        $this->assertFalse($this->request->has('nonexistent'));

        // Attributes are not included in has() — use attribute() for those
        $this->request->setAttribute('attr', 'value');
        $this->assertFalse($this->request->has('attr'));
        $this->assertSame('value', $this->request->attribute('attr'));
    }

    public function testGetReturnsDefaultForExplicitNullValue(): void
    {
        $event = $this->createRequestEvent(
            get: ['nullable' => null],
            server: ['REQUEST_METHOD' => 'GET']
        );

        $this->request->onRequestReceived($event);

        $this->assertSame('default', $this->request->get('nullable', 'default'));
    }

    public function testHasReturnsFalseForExplicitNullValue(): void
    {
        $event = $this->createRequestEvent(
            get: ['nullable' => null],
            server: ['REQUEST_METHOD' => 'GET']
        );

        $this->request->onRequestReceived($event);

        $this->assertFalse($this->request->has('nullable'));
    }

    public function testQueryReturnsValueFromGet(): void
    {
        $event = $this->createRequestEvent(
            get: ['id' => '123', 'page' => '1'],
            server: ['REQUEST_METHOD' => 'GET']
        );

        $this->request->onRequestReceived($event);

        $this->assertSame('123', $this->request->query('id'));
        $this->assertSame('1', $this->request->query('page'));
        $this->assertSame(null, $this->request->query('nonexistent'));
        $this->assertSame('default', $this->request->query('nonexistent', 'default'));
    }

    public function testPostReturnsValueFromPost(): void
    {
        $event = $this->createRequestEvent(
            post: ['name' => 'John', 'email' => 'john@example.com'],
            server: ['REQUEST_METHOD' => 'POST']
        );

        $this->request->onRequestReceived($event);

        $this->assertSame('John', $this->request->post('name'));
        $this->assertSame('john@example.com', $this->request->post('email'));
        $this->assertSame(null, $this->request->post('nonexistent'));
        $this->assertSame('default', $this->request->post('nonexistent', 'default'));
    }

    public function testHasHeaderChecksIfHeaderExists(): void
    {
        $event = $this->createRequestEvent(
            server: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_USER_AGENT' => 'Mozilla/5.0'
            ]
        );

        $this->request->onRequestReceived($event);

        $this->assertTrue($this->request->hasHeader('user-agent'));
        $this->assertTrue($this->request->hasHeader('User-Agent'));
        $this->assertFalse($this->request->hasHeader('nonexistent'));
    }

    public function testServerReturnsServerVariableOrAll(): void
    {
        $event = $this->createRequestEvent(
            server: [
                'REQUEST_METHOD' => 'POST',
                'SERVER_NAME' => 'example.com',
                'SERVER_PORT' => '80'
            ]
        );

        $this->request->onRequestReceived($event);

        $this->assertSame('POST', $this->request->server('REQUEST_METHOD'));
        $this->assertSame('example.com', $this->request->server('SERVER_NAME'));
        $this->assertSame('80', $this->request->server('SERVER_PORT'));
        $this->assertSame(null, $this->request->server('nonexistent'));
        $this->assertSame('default', $this->request->server('nonexistent', 'default'));

        $all = $this->request->server();
        $this->assertIsArray($all);
        $this->assertArrayHasKey('REQUEST_METHOD', $all);
    }

    public function testElapsedReturnsElapsedTime(): void
    {
        $event = $this->createRequestEvent(
            server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_TIME_FLOAT' => microtime(true) - 0.5
            ]
        );

        $this->request->onRequestReceived($event);

        $elapsed = $this->request->elapsed();
        $this->assertGreaterThan(0, $elapsed);
        $this->assertLessThan(1, $elapsed);

        $elapsed2 = $this->request->elapsed(2);
        $this->assertIsFloat($elapsed2);
    }

    public function testOriginReturnsRefererWhenStrictIsFalse(): void
    {
        $event = $this->createRequestEvent(
            server: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_REFERER' => 'https://example.com/page?param=value'
            ]
        );

        $this->request->onRequestReceived($event);

        $this->assertSame('', $this->request->origin(true));

        $origin = $this->request->origin(false);
        $this->assertStringContainsString('example.com', $origin);
    }

    public function testUrlReturnsUrlWithOrWithoutQueryString(): void
    {
        $event = $this->createRequestEvent(
            get: ['page' => '1'],
            server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/api/users?page=1',
                'REQUEST_SCHEME' => 'https'
            ]
        );

        $this->request->onRequestReceived($event);

        $context = $this->request->getContext();
        $context->headers['host'] = 'example.com';

        $url = $this->request->url();
        $this->assertStringStartsWith('https://example.com', $url);

        $urlWithQuery = $this->request->url(true);
        $this->assertStringContainsString('?', $urlWithQuery);
    }

    public function testHeaderReturnsHeaderValue(): void
    {
        $event = $this->createRequestEvent(
            server: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_USER_AGENT' => 'Mozilla/5.0',
                'HTTP_ACCEPT' => 'application/json',
                'CONTENT_TYPE' => 'text/html'
            ]
        );

        $this->request->onRequestReceived($event);

        $this->assertSame('Mozilla/5.0', $this->request->header('user-agent'));
        $this->assertSame('application/json', $this->request->header('accept'));
        $this->assertSame('text/html', $this->request->header('content-type'));
        $this->assertSame(null, $this->request->header('nonexistent'));
        $this->assertSame('default', $this->request->header('nonexistent', 'default'));
    }

    public function testWantsJsonTrueWhenAcceptContainsApplicationJson(): void
    {
        $event = $this->createRequestEvent(
            server: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_ACCEPT' => 'application/json; charset=utf-8',
            ]
        );
        $this->request->onRequestReceived($event);

        $this->assertTrue($this->request->wantsJson());
    }

    public function testWantsJsonTrueWhenAcceptContainsPlusJson(): void
    {
        $event = $this->createRequestEvent(
            server: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_ACCEPT' => 'application/problem+json; charset=utf-8, text/html',
            ]
        );
        $this->request->onRequestReceived($event);

        $this->assertTrue($this->request->wantsJson());
    }

    public function testWantsJsonFalseWhenAcceptWildcardAndNoAjax(): void
    {
        $event = $this->createRequestEvent(
            server: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_ACCEPT' => '*/*',
            ]
        );
        $this->request->onRequestReceived($event);

        $this->assertFalse($this->request->wantsJson());
    }

    public function testWantsJsonTrueWhenXmlHttpRequestWithoutJsonAccept(): void
    {
        $event = $this->createRequestEvent(
            server: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_ACCEPT' => '*/*',
                'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            ]
        );
        $this->request->onRequestReceived($event);

        $this->assertTrue($this->request->wantsJson());
    }

    public function testHeadersReturnsAllHeaders(): void
    {
        $event = $this->createRequestEvent(
            server: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_USER_AGENT' => 'Mozilla/5.0',
                'HTTP_ACCEPT' => 'application/json'
            ]
        );

        $this->request->onRequestReceived($event);

        $headers = $this->request->headers();

        $this->assertArrayHasKey('user-agent', $headers);
        $this->assertArrayHasKey('accept', $headers);
        $this->assertSame('Mozilla/5.0', $headers['user-agent']);
    }

    public function testMethodReturnsRequestMethod(): void
    {
        $event = $this->createRequestEvent(
            server: ['REQUEST_METHOD' => 'POST']
        );

        $this->request->onRequestReceived($event);

        $this->assertSame('POST', $this->request->verb());
    }

    public function testIsMethodChecksRequestMethod(): void
    {
        $event = $this->createRequestEvent(
            server: ['REQUEST_METHOD' => 'POST']
        );

        $this->request->onRequestReceived($event);

        $this->assertTrue($this->request->isVerb('POST'));
        $this->assertFalse($this->request->isVerb('GET'));
    }

    public function testIsAjaxDetectsAjaxRequests(): void
    {
        $event = $this->createRequestEvent(
            server: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'
            ]
        );

        $this->request->onRequestReceived($event);

        $this->assertTrue($this->request->isAjax());
    }

    public function testSchemeReturnsHttpOrHttps(): void
    {
        $event = $this->createRequestEvent(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_SCHEME' => 'http']
        );

        $this->request->onRequestReceived($event);
        $this->assertSame('http', $this->request->scheme());

        $event2 = $this->createRequestEvent(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_SCHEME' => 'https']
        );

        $this->request->onRequestReceived($event2);
        $this->assertSame('https', $this->request->scheme());

        $event3 = $this->createRequestEvent(
            server: ['REQUEST_METHOD' => 'GET', 'HTTPS' => 'on']
        );

        $this->request->onRequestReceived($event3);
        $this->assertSame('https', $this->request->scheme());

        $event4 = $this->createRequestEvent(
            server: ['REQUEST_METHOD' => 'GET']
        );

        $this->request->onRequestReceived($event4);
        $this->assertSame('http', $this->request->scheme());
    }

    public function testHostReturnsHostFromHeaderOrServer(): void
    {
        $event = $this->createRequestEvent(
            server: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_HOST' => 'example.com'
            ]
        );

        $this->request->onRequestReceived($event);
        $this->assertSame('example.com', $this->request->host());

        $event2 = $this->createRequestEvent(
            server: [
                'REQUEST_METHOD' => 'GET',
                'SERVER_NAME' => 'localhost'
            ]
        );

        $this->request->onRequestReceived($event2);
        $this->assertSame('localhost', $this->request->host());

        $event3 = $this->createRequestEvent(
            server: ['REQUEST_METHOD' => 'GET']
        );

        $this->request->onRequestReceived($event3);
        $this->assertSame('', $this->request->host());
    }

    public function testPortReturnsPortNumber(): void
    {
        $event = $this->createRequestEvent(
            server: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_HOST' => 'example.com:8080'
            ]
        );

        $this->request->onRequestReceived($event);
        $this->assertSame(8080, $this->request->port());

        $event2 = $this->createRequestEvent(
            server: [
                'REQUEST_METHOD' => 'GET',
                'SERVER_PORT' => '443'
            ]
        );

        $this->request->onRequestReceived($event2);
        $this->assertSame(443, $this->request->port());

        $event3 = $this->createRequestEvent(
            server: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_HOST' => 'example.com'
            ]
        );

        $this->request->onRequestReceived($event3);
        $this->assertSame(80, $this->request->port());
    }

    public function testIsSecureChecksIfRequestIsHttps(): void
    {
        $event = $this->createRequestEvent(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_SCHEME' => 'https']
        );

        $this->request->onRequestReceived($event);

        $this->assertTrue($this->request->isSecure());

        $event2 = $this->createRequestEvent(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_SCHEME' => 'http']
        );

        $this->request->onRequestReceived($event2);

        $this->assertFalse($this->request->isSecure());
    }


    public function testIpReturnsClientIpAddress(): void
    {
        $event = $this->createRequestEvent(
            server: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_X_REAL_IP' => '192.168.1.100'
            ]
        );

        $this->request->onRequestReceived($event);
        $this->assertSame('192.168.1.100', $this->request->ip());

        $event2 = $this->createRequestEvent(
            server: [
                'REQUEST_METHOD' => 'GET',
                'REMOTE_ADDR' => '127.0.0.1'
            ]
        );

        $this->request->onRequestReceived($event2);
        $this->assertSame('127.0.0.1', $this->request->ip());

        $event3 = $this->createRequestEvent(
            server: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_X_REAL_IP' => '10.0.0.1',
                'REMOTE_ADDR' => '192.168.1.1'
            ]
        );

        $this->request->onRequestReceived($event3);
        $this->assertSame('10.0.0.1', $this->request->ip());
    }

    public function testSetAttributeSetsAttributeThatCanBeRetrieved(): void
    {
        $event = $this->createRequestEvent(
            server: ['REQUEST_METHOD' => 'GET']
        );

        $this->request->onRequestReceived($event);

        $this->request->setAttribute('test', 'value');

        // Attributes are not exposed via get()/has() — use attribute() directly
        $this->assertNull($this->request->get('test'));
        $this->assertFalse($this->request->has('test'));
        $this->assertSame('value', $this->request->attribute('test'));
        $this->assertTrue($this->request->hasAttribute('test'));
    }


    public function testFiltersReturnsFilteredDataWithEmptyFields(): void
    {
        $event = new RequestReceived(
            GET: [],
            POST: ['name' => '  ', 'email' => 'test@example.com', 'empty' => ''],
            SERVER: ['REQUEST_METHOD' => 'POST'],
            RAW_BODY: '',
            COOKIE: [],
            FILES: []
        );

        $this->request->onRequestReceived($event);

        $filters = $this->request->filters();

        $this->assertArrayHasKey('email', $filters);
        $this->assertArrayNotHasKey('name', $filters); // Empty string filtered out
        $this->assertArrayNotHasKey('empty', $filters); // Empty string filtered out
    }


    public function testFiltersReturnsSpecifiedFieldsOnly(): void
    {
        $event = $this->createRequestEvent(
            post: ['name' => 'John', 'email' => 'john@example.com', 'password' => 'secret'],
            server: ['REQUEST_METHOD' => 'POST']
        );

        $this->request->onRequestReceived($event);

        $filters = $this->request->filters(['name', 'email']);

        $this->assertArrayHasKey('name', $filters);
        $this->assertArrayHasKey('email', $filters);
        $this->assertArrayNotHasKey('password', $filters);
    }

    public function testFiltersMergesCustomValues(): void
    {
        $event = $this->createRequestEvent(
            post: ['name' => 'John'],
            server: ['REQUEST_METHOD' => 'POST']
        );

        $this->request->onRequestReceived($event);

        $filters = $this->request->filters(['name'], ['custom' => 'value']);

        $this->assertArrayHasKey('name', $filters);
        $this->assertArrayHasKey('custom', $filters);
        $this->assertSame('value', $filters['custom']);
    }

    public function testFiltersThrowsExceptionForNonIndexedFieldsArray(): void
    {
        $event = $this->createRequestEvent(
            post: ['name' => 'John'],
            server: ['REQUEST_METHOD' => 'POST']
        );

        $this->request->onRequestReceived($event);

        $this->expectException(\Switon\Http\Exception\InvalidIndexedArrayException::class);

        $this->request->filters(['name' => 'value']);
    }

    public function testFiltersThrowsExceptionForIndexedCustomArray(): void
    {
        $event = $this->createRequestEvent(
            post: ['name' => 'John'],
            server: ['REQUEST_METHOD' => 'POST']
        );

        $this->request->onRequestReceived($event);

        $this->expectException(\Switon\Http\Exception\InvalidAssociativeArrayException::class);

        $this->request->filters([], ['value']);
    }

    public function testFilesReturnsArrayOfFileInterfaceInstances(): void
    {
        $event = $this->createRequestEvent(
            server: ['REQUEST_METHOD' => 'POST'],
            files: [
                'file1' => [
                    'name' => 'test.txt',
                    'type' => 'text/plain',
                    'tmp_name' => '/tmp/php123',
                    'error' => (string)UPLOAD_ERR_OK,
                    'size' => 1024
                ]
            ]
        );

        $this->request->onRequestReceived($event);

        $files = $this->request->files();

        $this->assertIsArray($files);
        $this->assertCount(1, $files);
        $this->assertInstanceOf(\Switon\Http\Request\FileInterface::class, $files[0]);
    }

    public function testFilesWithOnlySuccessfulFalseIncludesFailedUploads(): void
    {
        $event = $this->createRequestEvent(
            server: ['REQUEST_METHOD' => 'POST'],
            files: [
                'file1' => [
                    'name' => 'test.txt',
                    'type' => 'text/plain',
                    'tmp_name' => '/tmp/php123',
                    'error' => (string)UPLOAD_ERR_OK,
                    'size' => 1024
                ],
                'file2' => [
                    'name' => 'failed.txt',
                    'type' => 'text/plain',
                    'tmp_name' => '/tmp/php456',
                    'error' => (string)UPLOAD_ERR_PARTIAL,
                    'size' => 512
                ]
            ]
        );

        $this->request->onRequestReceived($event);

        $files = $this->request->files(false);

        $this->assertIsArray($files);
        $this->assertCount(2, $files);

        $filesOnlySuccessful = $this->request->files(true);
        $this->assertCount(1, $filesOnlySuccessful); // Only successful
    }


    public function testFilesHandlesMultipleFilesWithSameKey(): void
    {
        $event = $this->createRequestEvent(
            server: ['REQUEST_METHOD' => 'POST'],
            files: [
                'files' => [
                    [
                        'name' => 'file1.txt',
                        'type' => 'text/plain',
                        'tmp_name' => '/tmp/php123',
                        'error' => (string)UPLOAD_ERR_OK,
                        'size' => 1024
                    ],
                    [
                        'name' => 'file2.txt',
                        'type' => 'text/plain',
                        'tmp_name' => '/tmp/php456',
                        'error' => (string)UPLOAD_ERR_OK,
                        'size' => 2048
                    ]
                ]
            ]
        );

        $this->request->onRequestReceived($event);

        $files = $this->request->files();

        $this->assertIsArray($files);
        $this->assertCount(2, $files);
        $this->assertInstanceOf(\Switon\Http\Request\FileInterface::class, $files[0]);
        $this->assertInstanceOf(\Switon\Http\Request\FileInterface::class, $files[1]);
    }


    public function testFilesHandlesArrayStyleFileUploads(): void
    {
        $event = $this->createRequestEvent(
            server: ['REQUEST_METHOD' => 'POST'],
            files: [
                'photos' => [
                    'name' => ['photo1.jpg', 'photo2.jpg'],
                    'type' => ['image/jpeg', 'image/jpeg'],
                    'tmp_name' => ['/tmp/php123', '/tmp/php456'],
                    'error' => [(string)UPLOAD_ERR_OK, (string)UPLOAD_ERR_OK],
                    'size' => [1024, 2048]
                ]
            ]
        );

        $this->request->onRequestReceived($event);

        $files = $this->request->files();

        $this->assertIsArray($files);
        $this->assertCount(2, $files);
    }


    public function testFileReturnsFirstFileWhenKeyIsNull(): void
    {
        $event = $this->createRequestEvent(
            server: ['REQUEST_METHOD' => 'POST'],
            files: [
                'file1' => [
                    'name' => 'test.txt',
                    'type' => 'text/plain',
                    'tmp_name' => '/tmp/php123',
                    'error' => (string)UPLOAD_ERR_OK,
                    'size' => 1024
                ]
            ]
        );

        $this->request->onRequestReceived($event);

        $file = $this->request->file();

        $this->assertInstanceOf(\Switon\Http\Request\FileInterface::class, $file);
        $this->assertSame('file1', $file->getKey());
    }


    public function testFileReturnsNullWhenNoFilesExist(): void
    {
        $event = $this->createRequestEvent(
            server: ['REQUEST_METHOD' => 'POST']
        );

        $this->request->onRequestReceived($event);

        $this->assertSame(null, $this->request->file());
        $this->assertSame(null, $this->request->file('nonexistent'));
    }


    public function testHasFileHandlesMultipleFilesWithSameKey(): void
    {
        $event = $this->createRequestEvent(
            server: ['REQUEST_METHOD' => 'POST'],
            files: [
                'photos' => [
                    'name' => ['photo1.jpg', 'photo2.jpg'],
                    'type' => ['image/jpeg', 'image/jpeg'],
                    'tmp_name' => ['/tmp/php123', '/tmp/php456'],
                    'error' => [(string)UPLOAD_ERR_OK, (string)UPLOAD_ERR_PARTIAL],
                    'size' => [1024, 2048]
                ]
            ]
        );

        $this->request->onRequestReceived($event);

        $this->assertTrue($this->request->hasFile('photos'));
    }


    public function testHasFileReturnsFalseForFailedUploads(): void
    {
        $event = $this->createRequestEvent(
            server: ['REQUEST_METHOD' => 'POST'],
            files: [
                'file' => [
                    'name' => 'test.txt',
                    'type' => 'text/plain',
                    'tmp_name' => '/tmp/php123',
                    'error' => (string)UPLOAD_ERR_PARTIAL,
                    'size' => 1024
                ]
            ]
        );

        $this->request->onRequestReceived($event);

        $this->assertFalse($this->request->hasFile('file'));
    }


    public function testHasFileHandlesArrayOfFiles(): void
    {
        $event = $this->createRequestEvent(
            server: ['REQUEST_METHOD' => 'POST'],
            files: [
                'files' => [
                    [
                        'name' => 'file1.txt',
                        'type' => 'text/plain',
                        'tmp_name' => '/tmp/php123',
                        'error' => (string)UPLOAD_ERR_OK,
                        'size' => 1024
                    ]
                ]
            ]
        );

        $this->request->onRequestReceived($event);

        $this->assertTrue($this->request->hasFile('files'));
    }


    public function testFileReturnsFileInterfaceInstanceByKey(): void
    {
        $event = $this->createRequestEvent(
            server: ['REQUEST_METHOD' => 'POST'],
            files: [
                'avatar' => [
                    'name' => 'avatar.jpg',
                    'type' => 'image/jpeg',
                    'tmp_name' => '/tmp/php456',
                    'error' => (string)UPLOAD_ERR_OK,
                    'size' => 2048
                ]
            ]
        );

        $this->request->onRequestReceived($event);

        $file = $this->request->file('avatar');

        $this->assertInstanceOf(\Switon\Http\Request\FileInterface::class, $file);
        $this->assertSame('avatar', $file->getKey());
    }


    public function testFileReturnsNullForNonExistentKey(): void
    {
        $event = $this->createRequestEvent(
            server: ['REQUEST_METHOD' => 'POST']
        );

        $this->request->onRequestReceived($event);

        $this->assertSame(null, $this->request->file('nonexistent'));
    }

    public function testFilesArrayStyleMixedSuccessAndFailureRespectsOnlySuccessfulFlag(): void
    {
        $event = $this->createRequestEvent(
            server: ['REQUEST_METHOD' => 'POST'],
            files: [
                'batch' => [
                    'name' => ['a.txt', 'b.txt'],
                    'type' => ['text/plain', 'text/plain'],
                    'tmp_name' => ['/tmp/a', '/tmp/b'],
                    'error' => [(string)UPLOAD_ERR_OK, (string)UPLOAD_ERR_PARTIAL],
                    'size' => [1, 2],
                ],
            ]
        );

        $this->request->onRequestReceived($event);

        $this->assertCount(1, $this->request->files(true));
        $this->assertCount(2, $this->request->files(false));
    }

    public function testHasFileReturnsFalseWhenArrayStyleUploadHasOnlyFailures(): void
    {
        $event = $this->createRequestEvent(
            server: ['REQUEST_METHOD' => 'POST'],
            files: [
                'batch' => [
                    'name' => ['a.txt', 'b.txt'],
                    'type' => ['text/plain', 'text/plain'],
                    'tmp_name' => ['/tmp/a', '/tmp/b'],
                    'error' => [(string)UPLOAD_ERR_PARTIAL, (string)UPLOAD_ERR_NO_FILE],
                    'size' => [0, 0],
                ],
            ]
        );

        $this->request->onRequestReceived($event);

        $this->assertFalse($this->request->hasFile('batch'));
    }

    public function testHasFileReturnsFalseWhenIndexedFileArrayHasOnlyFailures(): void
    {
        $event = $this->createRequestEvent(
            server: ['REQUEST_METHOD' => 'POST'],
            files: [
                'docs' => [
                    [
                        'name' => 'a.txt',
                        'type' => 'text/plain',
                        'tmp_name' => '/tmp/a',
                        'error' => (string)UPLOAD_ERR_PARTIAL,
                        'size' => 0,
                    ],
                ],
            ]
        );

        $this->request->onRequestReceived($event);

        $this->assertFalse($this->request->hasFile('docs'));
    }

    public function testFileReturnsRequestedKeyAmongMultipleSingleFileUploads(): void
    {
        $event = $this->createRequestEvent(
            server: ['REQUEST_METHOD' => 'POST'],
            files: [
                'alpha' => [
                    'name' => 'a.txt',
                    'type' => 'text/plain',
                    'tmp_name' => '/tmp/a',
                    'error' => (string)UPLOAD_ERR_OK,
                    'size' => 1,
                ],
                'beta' => [
                    'name' => 'b.txt',
                    'type' => 'text/plain',
                    'tmp_name' => '/tmp/b',
                    'error' => (string)UPLOAD_ERR_OK,
                    'size' => 2,
                ],
            ]
        );

        $this->request->onRequestReceived($event);

        $this->assertSame('alpha', $this->request->file()->getKey());
        $this->assertSame('beta', $this->request->file('beta')->getKey());
        $this->assertSame('alpha', $this->request->file('alpha')->getKey());
    }

    public function testPortFallsBackToServerPortForBracketedIpv6HostWithoutExplicitPort(): void
    {
        $event = new RequestReceived(
            GET: [],
            POST: [],
            SERVER: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_HOST' => '[::1]',
                'SERVER_PORT' => '9000',
            ],
            RAW_BODY: '',
            COOKIE: [],
            FILES: []
        );

        $this->request->onRequestReceived($event);

        $this->assertSame(9000, $this->request->port());
    }

    public function testHasFileChecksIfFileExists(): void
    {
        $event = $this->createRequestEvent(
            server: ['REQUEST_METHOD' => 'POST'],
            files: [
                'document' => [
                    'name' => 'doc.pdf',
                    'type' => 'application/pdf',
                    'tmp_name' => '/tmp/php789',
                    'error' => (string)UPLOAD_ERR_OK,
                    'size' => 4096
                ]
            ]
        );

        $this->request->onRequestReceived($event);

        $this->assertTrue($this->request->hasFile('document'));
        $this->assertFalse($this->request->hasFile('nonexistent'));
    }


    public function testIsVerbWorksWithDifferentHttpMethods(): void
    {
        $verbs = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'];

        foreach ($verbs as $verb) {
            $event = $this->createRequestEvent(
                server: ['REQUEST_METHOD' => $verb]
            );

            $this->request->onRequestReceived($event);

            $this->assertTrue($this->request->isVerb($verb), "Failed for verb: $verb");
        }
    }

    public function testJsonSerializeReturnsSerializableRequestData(): void
    {
        $event = $this->createRequestEvent(
            get: ['id' => '123'],
            post: ['name' => 'John'],
            server: ['REQUEST_METHOD' => 'POST']
        );

        $this->request->onRequestReceived($event);

        $json = $this->request->jsonSerialize();

        $this->assertIsArray($json);
        $this->assertSame('123', $this->request->query('id'));
        $this->assertSame('John', $this->request->post('name'));
        $this->assertIsString(json_encode($json));
    }

    public function testPathReturnsRequestPathWithoutQueryString(): void
    {
        $event = $this->createRequestEvent(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/users?page=1']
        );

        $this->request->onRequestReceived($event);

        $this->assertSame('/api/users', $this->request->path());
    }

    public function testPathRemovesTrailingSlash(): void
    {
        $event = $this->createRequestEvent(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/users/']
        );

        $this->request->onRequestReceived($event);

        $this->assertSame('/api/users', $this->request->path());
    }

    public function testPathReturnsRequestPathWithQueryStringWhenRequested(): void
    {
        $event = $this->createRequestEvent(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/users?page=1']
        );

        $this->request->onRequestReceived($event);

        $this->assertSame('/api/users?page=1', $this->request->path(true));
    }

    public function testPathWithQueryRemovesTrailingSlash(): void
    {
        $event = $this->createRequestEvent(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/users/?page=1']
        );

        $this->request->onRequestReceived($event);

        $this->assertSame('/api/users?page=1', $this->request->path(true));
    }

    public function testPathWhenRequestUriMissingUsesEmptyString(): void
    {
        $event = $this->createRequestEvent(
            server: ['REQUEST_METHOD' => 'GET']
        );

        $this->request->onRequestReceived($event);

        $this->assertSame('', $this->request->path());
        $this->assertSame('', $this->request->path(true));
    }

    public function testUrlReturnsFullUrl(): void
    {
        $event = $this->createRequestEvent(
            server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/api/users',
                'REQUEST_SCHEME' => 'https',
                'HTTP_HOST' => 'example.com'
            ]
        );

        $this->request->onRequestReceived($event);

        $url = $this->request->url();

        $this->assertStringStartsWith('https://example.com', $url);
    }

    public function testOriginReturnsOriginHeader(): void
    {
        $event = $this->createRequestEvent(
            server: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_ORIGIN' => 'https://example.com'
            ]
        );

        $this->request->onRequestReceived($event);

        $this->assertSame('https://example.com', $this->request->origin());
    }

    public function testAttributeReturnsAttributeValue(): void
    {
        $event = $this->createRequestEvent(
            server: ['REQUEST_METHOD' => 'GET']
        );

        $this->request->onRequestReceived($event);

        $this->request->setAttribute('test', 'value');

        $this->assertSame('value', $this->request->attribute('test'));
        $this->assertSame(null, $this->request->attribute('nonexistent'));
    }

    public function testHasAttributeChecksIfAttributeExists(): void
    {
        $event = $this->createRequestEvent(
            server: ['REQUEST_METHOD' => 'GET']
        );

        $this->request->onRequestReceived($event);

        $this->request->setAttribute('test', 'value');

        $this->assertTrue($this->request->hasAttribute('test'));
        $this->assertFalse($this->request->hasAttribute('nonexistent'));
    }

    public function testAttributesReturnsAllAttributes(): void
    {
        $event = $this->createRequestEvent(
            server: ['REQUEST_METHOD' => 'GET']
        );

        $this->request->onRequestReceived($event);

        $this->request->setAttribute('attr1', 'value1');
        $this->request->setAttribute('attr2', 'value2');

        $attributes = $this->request->attributes();

        $this->assertArrayHasKey('attr1', $attributes);
        $this->assertArrayHasKey('attr2', $attributes);
        $this->assertSame('value1', $attributes['attr1']);
        $this->assertSame('value2', $attributes['attr2']);
    }

    public function testFiltersHandlesRelationFieldFormat(): void
    {
        // Arrange — request data uses base field names, filters use relation.field format
        $event = $this->createRequestEvent(
            post: ['name' => 'John', 'email' => 'john@example.com', 'status' => 'active'],
            server: ['REQUEST_METHOD' => 'POST']
        );

        $this->request->onRequestReceived($event);

        // Act — relation.field format: base field name is extracted after the dot
        $result = $this->request->filters(['user.name', 'status']);

        // Assert — key preserves original field name, value comes from base field
        $this->assertArrayHasKey('user.name', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertSame('John', $result['user.name']);
        $this->assertSame('active', $result['status']);
    }

    public function testFiltersStripsOperatorSuffixWhenMatchingRequestData(): void
    {
        // Arrange — request data uses plain field names
        $event = $this->createRequestEvent(
            get: ['name' => 'John', 'email' => 'john@example.com', 'age' => '25'],
            server: ['REQUEST_METHOD' => 'GET']
        );

        $this->request->onRequestReceived($event);

        // Act — fields with operator suffixes should match base field name in request data
        $result = $this->request->filters(['name', 'email*=', 'age>=']);

        // Assert — output keys preserve operator suffix, values from base field
        $this->assertArrayHasKey('name', $result, 'Plain field should match');
        $this->assertArrayHasKey('email*=', $result, 'Operator suffix *=  should match base field "email"');
        $this->assertArrayHasKey('age>=', $result, 'Operator suffix >= should match base field "age"');
        $this->assertSame('John', $result['name']);
        $this->assertSame('john@example.com', $result['email*=']);
        $this->assertSame('25', $result['age>=']);
    }

    public function testFiltersStripsOperatorWithRelationField(): void
    {
        // Arrange — request data uses base field names
        $event = $this->createRequestEvent(
            post: ['created_at' => '2026-01-01', 'name' => 'John'],
            server: ['REQUEST_METHOD' => 'POST']
        );

        $this->request->onRequestReceived($event);

        // Act — relation.field with operator: base field extracted from after dot, then operator stripped
        $result = $this->request->filters(['order.created_at@=', 'name']);

        // Assert
        $this->assertArrayHasKey('order.created_at@=', $result, 'Relation field with operator should match base field "created_at"');
        $this->assertSame('2026-01-01', $result['order.created_at@=']);
        $this->assertArrayHasKey('name', $result);
    }

    public function testFiltersOperatorFieldSkipsEmptyValues(): void
    {
        // Arrange
        $event = $this->createRequestEvent(
            get: ['name' => 'John', 'email' => '', 'status' => '   '],
            server: ['REQUEST_METHOD' => 'GET']
        );

        $this->request->onRequestReceived($event);

        // Act
        $result = $this->request->filters(['name', 'email*=', 'status!=']);

        // Assert — empty values should be filtered out even with operator suffix
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayNotHasKey('email*=', $result, 'Empty string should be filtered out');
        $this->assertArrayNotHasKey('status!=', $result, 'Whitespace-only string should be filtered out');
    }

    public function testFiltersNonExistentOperatorFieldReturnsEmpty(): void
    {
        // Arrange
        $event = $this->createRequestEvent(
            get: ['name' => 'John'],
            server: ['REQUEST_METHOD' => 'GET']
        );

        $this->request->onRequestReceived($event);

        // Act — field with operator that doesn't match any request data
        $result = $this->request->filters(['nonexistent*=']);

        // Assert
        $this->assertEmpty($result);
    }

    public function testFiltersHandlesEmptyStringValuesCorrectly(): void
    {
        $event = $this->createRequestEvent(
            post: ['name' => 'John', 'email' => '', 'status' => '   ', 'age' => 25],
            server: ['REQUEST_METHOD' => 'POST']
        );

        $this->request->onRequestReceived($event);

        $result = $this->request->filters();

        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('age', $result);
        $this->assertArrayNotHasKey('email', $result);
        $this->assertArrayNotHasKey('status', $result);
    }

    public function testFiltersWithSpecifiedFieldsFiltersEmptyStrings(): void
    {
        $event = $this->createRequestEvent(
            post: ['name' => 'John', 'email' => '', 'status' => null],
            server: ['REQUEST_METHOD' => 'POST']
        );

        $this->request->onRequestReceived($event);

        $result = $this->request->filters(['name', 'email', 'status']);

        $this->assertArrayHasKey('name', $result);
        $this->assertArrayNotHasKey('email', $result); // Empty string filtered out
        $this->assertArrayNotHasKey('status', $result); // Null value filtered out
    }


    public function testOriginHandlesRefererWithoutQueryString(): void
    {
        $event = new RequestReceived(
            GET: [],
            POST: [],
            SERVER: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_REFERER' => 'https://example.com/page'
            ],
            RAW_BODY: '',
            COOKIE: [],
            FILES: []
        );

        $this->request->onRequestReceived($event);

        $origin = $this->request->origin(false);
        $this->assertStringContainsString('example.com', $origin);
    }


    public function testOriginHandlesRefererWithoutPath(): void
    {
        $event = new RequestReceived(
            GET: [],
            POST: [],
            SERVER: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_REFERER' => 'https://example.com'
            ],
            RAW_BODY: '',
            COOKIE: [],
            FILES: []
        );

        $this->request->onRequestReceived($event);

        $origin = $this->request->origin(false);
        $this->assertSame('https://example.com', $origin);
    }


    public function testOriginReturnsEmptyWhenNoOriginOrReferer(): void
    {
        $event = new RequestReceived(
            GET: [],
            POST: [],
            SERVER: ['REQUEST_METHOD' => 'GET'],
            RAW_BODY: '',
            COOKIE: [],
            FILES: []
        );

        $this->request->onRequestReceived($event);

        $this->assertSame('', $this->request->origin());
        $this->assertSame('', $this->request->origin(false));
    }


    public function testPortExtractsPortFromHostHeader(): void
    {
        $event = new RequestReceived(
            GET: [],
            POST: [],
            SERVER: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_HOST' => 'example.com:8080'
            ],
            RAW_BODY: '',
            COOKIE: [],
            FILES: []
        );

        $this->request->onRequestReceived($event);

        $this->assertSame(8080, $this->request->port());
    }


    public function testPortFallsBackToServerPortWhenHostHeaderHasNoPort(): void
    {
        $event = new RequestReceived(
            GET: [],
            POST: [],
            SERVER: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_HOST' => 'example.com',
                'SERVER_PORT' => '443'
            ],
            RAW_BODY: '',
            COOKIE: [],
            FILES: []
        );

        $this->request->onRequestReceived($event);

        $this->assertSame(443, $this->request->port());
    }


    public function testPortHandlesInvalidPortInHostHeader(): void
    {
        $event = new RequestReceived(
            GET: [],
            POST: [],
            SERVER: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_HOST' => 'example.com:0',
                'SERVER_PORT' => '80'
            ],
            RAW_BODY: '',
            COOKIE: [],
            FILES: []
        );

        $this->request->onRequestReceived($event);

        $this->assertSame(80, $this->request->port());
    }

    public function testPortExtractsPortFromBracketedIpv6HostHeader(): void
    {
        $event = new RequestReceived(
            GET: [],
            POST: [],
            SERVER: [
                'REQUEST_METHOD' => 'GET',
                'HTTP_HOST' => '[::1]:8443',
                'SERVER_PORT' => '80',
            ],
            RAW_BODY: '',
            COOKIE: [],
            FILES: []
        );

        $this->request->onRequestReceived($event);

        $this->assertSame(8443, $this->request->port());
    }


    public function testPathHandlesUriWithoutQueryString(): void
    {
        $event = new RequestReceived(
            GET: [],
            POST: [],
            SERVER: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/api/users'
            ],
            RAW_BODY: '',
            COOKIE: [],
            FILES: []
        );

        $this->request->onRequestReceived($event);

        $this->assertSame('/api/users', $this->request->path());
        $this->assertSame('/api/users', $this->request->path(true));
    }


    public function testUrlHandlesMissingHostHeader(): void
    {
        $event = new RequestReceived(
            GET: [],
            POST: [],
            SERVER: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/api/users',
                'REQUEST_SCHEME' => 'https'
            ],
            RAW_BODY: '',
            COOKIE: [],
            FILES: []
        );

        $this->request->onRequestReceived($event);

        $url = $this->request->url();
        $this->assertIsString($url);
    }


    public function testIpFallsBackToRemoteAddrWhenXRealIpIsNotSet(): void
    {
        $event = new RequestReceived(
            GET: [],
            POST: [],
            SERVER: [
                'REQUEST_METHOD' => 'GET',
                'REMOTE_ADDR' => '192.168.1.100'
            ],
            RAW_BODY: '',
            COOKIE: [],
            FILES: []
        );

        $this->request->onRequestReceived($event);

        $this->assertSame('192.168.1.100', $this->request->ip());
    }


    public function testIpPrefersXRealIpOverRemoteAddr(): void
    {
        $event = new RequestReceived(
            GET: [],
            POST: [],
            SERVER: [
                'REQUEST_METHOD' => 'GET',
                'REMOTE_ADDR' => '192.168.1.100',
                'HTTP_X_REAL_IP' => '10.0.0.1'
            ],
            RAW_BODY: '',
            COOKIE: [],
            FILES: []
        );

        $this->request->onRequestReceived($event);

        $this->assertSame('10.0.0.1', $this->request->ip());
    }


    public function testElapsedHandlesMissingRequestTimeFloat(): void
    {
        $event = new RequestReceived(
            GET: [],
            POST: [],
            SERVER: ['REQUEST_METHOD' => 'GET'],
            RAW_BODY: '',
            COOKIE: [],
            FILES: []
        );

        $this->request->onRequestReceived($event);

        $elapsed = $this->request->elapsed();
        $this->assertIsFloat($elapsed);
    }


    public function testRawBodyReturnsRawBodyContent(): void
    {
        $event = new RequestReceived(
            GET: [],
            POST: [],
            SERVER: ['REQUEST_METHOD' => 'POST'],
            RAW_BODY: 'raw body content',
            COOKIE: [],
            FILES: []
        );

        $this->request->onRequestReceived($event);

        $this->assertSame('raw body content', $this->request->rawBody());
    }


    public function testRawBodyReturnsNullWhenNoRawBody(): void
    {
        $event = new RequestReceived(
            GET: [],
            POST: [],
            SERVER: ['REQUEST_METHOD' => 'GET'],
            RAW_BODY: null,
            COOKIE: [],
            FILES: []
        );

        $this->request->onRequestReceived($event);

        $this->assertSame(null, $this->request->rawBody());
    }


    public function testOnlyReturnsEmptyArrayWhenNoMatchingFields(): void
    {
        $event = new RequestReceived(
            GET: [],
            POST: ['name' => 'John'],
            SERVER: ['REQUEST_METHOD' => 'POST'],
            RAW_BODY: '',
            COOKIE: [],
            FILES: []
        );

        $this->request->onRequestReceived($event);

        $only = $this->request->only(['nonexistent']);

        $this->assertIsArray($only);
        $this->assertCount(0, $only);
    }


    public function testExceptReturnsAllFieldsWhenNoFieldsToExclude(): void
    {
        $event = new RequestReceived(
            GET: [],
            POST: ['name' => 'John', 'email' => 'john@example.com'],
            SERVER: ['REQUEST_METHOD' => 'POST'],
            RAW_BODY: '',
            COOKIE: [],
            FILES: []
        );

        $this->request->onRequestReceived($event);

        $except = $this->request->except([]);

        $this->assertArrayHasKey('name', $except);
        $this->assertArrayHasKey('email', $except);
    }


    public function testGetDoesNotIncludeAttributes(): void
    {
        $event = new RequestReceived(
            GET: [],
            POST: [],
            SERVER: ['REQUEST_METHOD' => 'GET'],
            RAW_BODY: '',
            COOKIE: [],
            FILES: []
        );

        $this->request->onRequestReceived($event);

        $this->request->setAttribute('attr', 'value');

        // get() only returns user input (POST + Query), not attributes
        $this->assertNull($this->request->get('attr'));
        $this->assertSame('value', $this->request->attribute('attr'));
    }


    public function testGetDoesNotMixAttributesWithInput(): void
    {
        $event = new RequestReceived(
            GET: ['key' => 'request_value'],
            POST: [],
            SERVER: ['REQUEST_METHOD' => 'GET'],
            RAW_BODY: '',
            COOKIE: [],
            FILES: []
        );

        $this->request->onRequestReceived($event);

        $this->request->setAttribute('key', 'attribute_value');

        // get() returns user input, attribute() returns attribute
        $this->assertSame('request_value', $this->request->get('key'));
        $this->assertSame('attribute_value', $this->request->attribute('key'));
    }


    public function testParseBodyThrowsOnInvalidJsonBody(): void
    {
        $event = new RequestReceived(
            GET: [],
            POST: [],
            SERVER: [
                'REQUEST_METHOD' => 'POST',
                'CONTENT_TYPE' => 'application/json'
            ],
            RAW_BODY: 'invalid json{',
            COOKIE: [],
            FILES: []
        );

        $this->request->onRequestReceived($event);

        $this->expectException(BadRequestException::class);
        $this->request->parseBody();
    }


    public function testOnRequestReceivedHandlesFormDataBody(): void
    {
        $event = new RequestReceived(
            GET: [],
            POST: [],
            SERVER: [
                'REQUEST_METHOD' => 'POST',
                'CONTENT_TYPE' => 'application/x-www-form-urlencoded'
            ],
            RAW_BODY: 'name=John&email=john@example.com',
            COOKIE: [],
            FILES: []
        );

        $this->request->onRequestReceived($event);
        $this->request->parseBody();

        $this->assertSame('John', $this->request->post('name'));
        $this->assertSame('john@example.com', $this->request->post('email'));
    }


    public function testOnRequestReceivedHandlesNonArrayJsonBody(): void
    {
        $event = new RequestReceived(
            GET: [],
            POST: [],
            SERVER: [
                'REQUEST_METHOD' => 'POST',
                'CONTENT_TYPE' => 'application/json'
            ],
            RAW_BODY: '"string"',
            COOKIE: [],
            FILES: []
        );

        $this->request->onRequestReceived($event);
        $this->request->parseBody();

        $post = $this->request->post();
        $this->assertIsArray($post);
    }


    public function testHandlerReturnsEmptyStringWhenNoMatcher(): void
    {
        // Arrange
        $this->request->onRequestReceived($this->createRequestEvent(server: ['REQUEST_METHOD' => 'GET']));

        // Act & Assert
        $this->assertSame('', $this->request->handler());
    }


    public function testHandlerReturnsHandlerStringFromMatcher(): void
    {
        // Arrange
        $this->request->onRequestReceived($this->createRequestEvent(server: ['REQUEST_METHOD' => 'GET']));

        $matcher = $this->createStub(MatcherInterface::class);
        $matcher->method('getHandler')->willReturn('UserController::show');
        $this->request->getContext()->matcher = $matcher;

        // Act & Assert
        $this->assertSame('UserController::show', $this->request->handler());
    }


    public function testRouteReturnsEmptyArrayWhenNoMatcher(): void
    {
        // Arrange
        $this->request->onRequestReceived($this->createRequestEvent(server: ['REQUEST_METHOD' => 'GET']));

        // Act & Assert
        $this->assertSame([], $this->request->route());
    }


    public function testRouteReturnsDefaultWhenNoMatcherAndNameGiven(): void
    {
        // Arrange
        $this->request->onRequestReceived($this->createRequestEvent(server: ['REQUEST_METHOD' => 'GET']));

        // Act & Assert
        $this->assertNull($this->request->route('id'));
        $this->assertSame(0, $this->request->route('id', 0));
    }


    public function testRouteReturnsAllVariablesFromMatcher(): void
    {
        // Arrange
        $this->request->onRequestReceived($this->createRequestEvent(server: ['REQUEST_METHOD' => 'GET']));

        $matcher = $this->createStub(MatcherInterface::class);
        $matcher->method('getVariables')->willReturn(['id' => '123', 'slug' => 'hello']);
        $this->request->getContext()->matcher = $matcher;

        // Act & Assert
        $this->assertSame(['id' => '123', 'slug' => 'hello'], $this->request->route());
    }


    public function testRouteReturnsSingleVariableByName(): void
    {
        // Arrange
        $this->request->onRequestReceived($this->createRequestEvent(server: ['REQUEST_METHOD' => 'GET']));

        $matcher = $this->createStub(MatcherInterface::class);
        $matcher->method('getVariables')->willReturn(['id' => '123', 'slug' => 'hello']);
        $this->request->getContext()->matcher = $matcher;

        // Act & Assert
        $this->assertSame('123', $this->request->route('id'));
        $this->assertSame('hello', $this->request->route('slug'));
    }


    public function testRouteReturnsDefaultForMissingVariable(): void
    {
        // Arrange
        $this->request->onRequestReceived($this->createRequestEvent(server: ['REQUEST_METHOD' => 'GET']));

        $matcher = $this->createStub(MatcherInterface::class);
        $matcher->method('getVariables')->willReturn(['id' => '123']);
        $this->request->getContext()->matcher = $matcher;

        // Act & Assert
        $this->assertNull($this->request->route('missing'));
        $this->assertSame('fallback', $this->request->route('missing', 'fallback'));
    }


    public function testGetReturnsRouteParamFromMergedRequest(): void
    {
        // Arrange — simulate RequestHandler merging route variables into _REQUEST
        $event = $this->createRequestEvent(
            get: ['page' => '1'],
            server: ['REQUEST_METHOD' => 'GET']
        );
        $this->request->onRequestReceived($event);

        $context = $this->request->getContext();
        $context->_REQUEST = array_merge($context->_REQUEST, ['id' => '42']);

        // Act & Assert — get() returns route param from unified input
        $this->assertSame('42', $this->request->get('id'));
        $this->assertSame('1', $this->request->get('page'));
    }


    public function testRouteParamOverridesQueryParamInGet(): void
    {
        // Arrange — query has id=999, route has id=42 (route wins)
        $event = $this->createRequestEvent(
            get: ['id' => '999', 'page' => '1'],
            server: ['REQUEST_METHOD' => 'GET']
        );
        $this->request->onRequestReceived($event);

        $context = $this->request->getContext();
        $context->_REQUEST = array_merge($context->_REQUEST, ['id' => '42']);

        // Act & Assert — route param has highest priority
        $this->assertSame('42', $this->request->get('id'), 'Route param should override query param');
        $this->assertSame('1', $this->request->get('page'), 'Non-conflicting query param is preserved');
    }


    public function testHasReturnsTrueForRouteParam(): void
    {
        // Arrange
        $event = $this->createRequestEvent(server: ['REQUEST_METHOD' => 'GET']);
        $this->request->onRequestReceived($event);

        $context = $this->request->getContext();
        $context->_REQUEST = array_merge($context->_REQUEST, ['id' => '42']);

        // Act & Assert
        $this->assertTrue($this->request->has('id'));
    }


    public function testAllIncludesRouteParams(): void
    {
        // Arrange
        $event = $this->createRequestEvent(
            get: ['page' => '1'],
            post: ['name' => 'John'],
            server: ['REQUEST_METHOD' => 'POST']
        );
        $this->request->onRequestReceived($event);

        $context = $this->request->getContext();
        $context->_REQUEST = array_merge($context->_REQUEST, ['id' => '42']);

        // Act
        $all = $this->request->all();

        // Assert — all() includes route + POST + query
        $this->assertSame('42', $all['id']);
        $this->assertSame('John', $all['name']);
        $this->assertSame('1', $all['page']);
    }
}

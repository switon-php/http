<?php

declare(strict_types=1);

namespace Switon\Http\Tests\Unit;

use Switon\Core\Attribute\Autowired;
use Switon\Http\Controller;
use Switon\Http\RequestInterface;
use Switon\Http\ResponseInterface;
use Switon\Http\Tests\TestCase;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class ControllerTest extends TestCase
{
    #[Autowired] protected RequestInterface $request;
    #[Autowired] protected ResponseInterface $response;

    public function testControllerCanBeInstantiated(): void
    {
        $controller = new Controller();

        $this->assertInstanceOf(Controller::class, $controller);
    }

    public function testControllerCanBeExtended(): void
    {
        $controller = new class () extends Controller {
        };

        $this->assertInstanceOf(Controller::class, $controller);
    }

    public function testControllerHasAccessToRequest(): void
    {
        $controller = new class () extends Controller {
            #[Autowired] protected RequestInterface $request;

            public function getRequest(): RequestInterface
            {
                return $this->request;
            }
        };

        $this->injector->inject($controller);
        $request = $controller->getRequest();

        $this->assertInstanceOf(RequestInterface::class, $request);
    }

    public function testControllerHasAccessToResponse(): void
    {
        $controller = new class () extends Controller {
            #[Autowired] protected ResponseInterface $response;

            public function getResponse(): ResponseInterface
            {
                return $this->response;
            }
        };

        $this->injector->inject($controller);
        $response = $controller->getResponse();

        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testControllerCanUseAutowiredDependencies(): void
    {
        $controller = new class () extends Controller {
            #[Autowired] protected RequestInterface $request;
            #[Autowired] protected ResponseInterface $response;

            public function hasDependencies(): bool
            {
                return $this->request !== null && $this->response !== null;
            }
        };

        $this->injector->inject($controller);

        $this->assertTrue($controller->hasDependencies());
    }

    public function testControllerCanReturnResponse(): void
    {
        $controller = new class () extends Controller {
            #[Autowired] protected ResponseInterface $response;

            public function action(): ResponseInterface
            {
                return $this->response->json(['status' => 'ok']);
            }
        };

        $this->injector->inject($controller);
        $result = $controller->action();

        $this->assertInstanceOf(ResponseInterface::class, $result);
    }

    public function testControllerCanReturnArray(): void
    {
        $controller = new class () extends Controller {
            public function action(): array
            {
                return ['data' => 'value'];
            }
        };

        $result = $controller->action();

        $this->assertIsArray($result);
        $this->assertSame(['data' => 'value'], $result);
    }

    public function testControllerCanReturnString(): void
    {
        $controller = new class () extends Controller {
            public function action(): string
            {
                return 'Hello World';
            }
        };

        $result = $controller->action();

        $this->assertIsString($result);
        $this->assertSame('Hello World', $result);
    }

    public function testControllerCanAccessRequestData(): void
    {
        $controller = new class () extends Controller {
            #[Autowired] protected RequestInterface $request;

            public function getQuery(string $key, mixed $default = null): mixed
            {
                return $this->request->query($key, $default);
            }
        };

        $this->injector->inject($controller);

        $this->assertNull($controller->getQuery('nonexistent'));
        $this->assertSame('default', $controller->getQuery('nonexistent', 'default'));
    }

    public function testControllerCanModifyResponse(): void
    {
        $controller = new class () extends Controller {
            #[Autowired] protected ResponseInterface $response;

            public function modifyResponse(): ResponseInterface
            {
                return $this->response->json(['created' => true], 201);
            }
        };

        $this->injector->inject($controller);
        $result = $controller->modifyResponse();

        $this->assertSame(201, $result->getStatusCode());
    }

    public function testControllerCanSetHeaders(): void
    {
        $controller = new class () extends Controller {
            #[Autowired] protected ResponseInterface $response;

            public function setCustomHeader(): ResponseInterface
            {
                return $this->response->setHeader('X-Custom', 'test-value');
            }
        };

        $this->injector->inject($controller);
        $result = $controller->setCustomHeader();

        $this->assertSame('test-value', $result->getHeader('X-Custom'));
    }

    public function testControllerCanSetCookies(): void
    {
        $controller = new class () extends Controller {
            #[Autowired] protected ResponseInterface $response;

            public function setCookie(): ResponseInterface
            {
                $this->response->setCookie('test', 'value', 3600);
                return $this->response;
            }
        };

        $this->injector->inject($controller);
        $result = $controller->setCookie();

        $cookies = $result->getCookies();
        $this->assertArrayHasKey('test', $cookies);
        $this->assertSame('value', $cookies['test']['value']);
    }

    public function testControllerSupportsMultipleActions(): void
    {
        $controller = new class () extends Controller {
            public function index(): string
            {
                return 'index';
            }

            public function show(): string
            {
                return 'show';
            }

            public function create(): string
            {
                return 'create';
            }
        };

        $this->assertSame('index', $controller->index());
        $this->assertSame('show', $controller->show());
        $this->assertSame('create', $controller->create());
    }

    public function testControllerCanBeUsedWithInheritance(): void
    {
        // Create a base controller class
        $baseController = new class () extends Controller {
            public function baseMethod(): string
            {
                return 'base';
            }
        };

        // Test that the base controller works
        $this->assertSame('base', $baseController->baseMethod());
        $this->assertInstanceOf(Controller::class, $baseController);
    }
}

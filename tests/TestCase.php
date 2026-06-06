<?php

declare(strict_types=1);

namespace Switon\Http\Tests;

use Switon\Core\Attribute\Autowired;
use Switon\Core\ContextManagerInterface;
use Switon\Core\InputInterface;
use Switon\Http\RequestInterface;
use Switon\Testing\TestCase as BaseTestCase;

/**
 * Base test case for HTTP tests.
 *
 * Provides common functionality for all HTTP tests, including Container and ContextManager initialization.
 */
abstract class TestCase extends BaseTestCase
{
    #[Autowired] protected ContextManagerInterface $contextManager;

    protected function setUpContainer(): void
    {
        parent::setUpContainer();

        // Allow subclasses to replace EventDispatcher before ContextManager is resolved
        $this->beforeSetUpHttpContainer();

        // Set up common HTTP test dependencies
        $this->setUpHttpContainer();

        // ContextManager is already registered in Switon\Testing\Container
        // Property autowiring is automatically performed by parent::setUp()
    }

    /**
     * Hook method called before setUpHttpContainer().
     *
     * Subclasses can override this to replace EventDispatcher or other dependencies
     * before ContextManager is created.
     */
    protected function beforeSetUpHttpContainer(): void
    {
    }

    /**
     * Set up common HTTP test container dependencies.
     *
     * This method configures commonly used interfaces and services for HTTP tests,
     * reducing boilerplate in individual test classes.
     */
    protected function setUpHttpContainer(): void
    {
        $this->container->set(InputInterface::class, RequestInterface::class);

        // PathAliasInterface already provides the singular "@view" alias in the shared test container.
        // Package resource aliases come from provider-declared ResourceAlias attributes.

        // EventDispatcherInterface is already configured by Switon\Testing\Container
        // with real EventDispatcher implementation for event-driven functionality
        // Tests that need a different mock should replace it before property autowiring runs

        // ClockInterface is already configured by Switon\Testing\Container
        // Tests can replace with MockClock or custom mock if needed for time control

        // FilesystemInterface is already configured by Switon\Testing\Container
        // Tests can replace with mock if needed

        // RequestInterface - auto-resolves to Request
        // ResponseInterface - auto-resolves to Response

        // RouterInterface - typically mocked in tests, but can be set if needed
        // ServerInterface - typically mocked in tests, but can be set if needed

        // JsonRendererInterface - auto-resolves to JsonRenderer
        // UrlGeneratorInterface - auto-mapped by convention (UrlGeneratorInterface -> UrlGenerator)
    }
}

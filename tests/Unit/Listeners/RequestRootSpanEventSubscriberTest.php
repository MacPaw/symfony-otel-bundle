<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use Macpaw\SymfonyOtelBundle\Instrumentation\Utils\RouterUtils;
use Macpaw\SymfonyOtelBundle\Listeners\RequestRootSpanEventSubscriber;
use Macpaw\SymfonyOtelBundle\Registry\InstrumentationRegistry;
use Macpaw\SymfonyOtelBundle\Service\HttpMetadataAttacher;
use Macpaw\SymfonyOtelBundle\Service\TraceService;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Tests\Support\Telemetry\InMemoryProviderFactory;

class RequestRootSpanEventSubscriberTest extends TestCase
{
    private InstrumentationRegistry $registry;
    private TextMapPropagatorInterface&MockObject $propagator;
    private TraceService $traceService;
    private HttpMetadataAttacher $httpMetadataAttacher;

    public function testOnKernelRequest(): void
    {
        $subscriber = new RequestRootSpanEventSubscriber(
            $this->registry,
            $this->propagator,
            $this->traceService,
            $this->httpMetadataAttacher,
            false,
            100,
        );

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/test', 'GET');
        $request->attributes->set('_route', 'test_route');
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->propagator->expects($this->once())
            ->method('extract')
            ->willReturn(\OpenTelemetry\Context\Context::getCurrent());

        $subscriber->onKernelRequest($event);

        $this->assertNotNull($this->registry->getSpan(\Macpaw\SymfonyOtelBundle\Registry\SpanNames::REQUEST_START));
    }

    public function testOnKernelTerminate(): void
    {
        $subscriber = new RequestRootSpanEventSubscriber(
            $this->registry,
            $this->propagator,
            $this->traceService,
            $this->httpMetadataAttacher,
            false,
            100,
        );

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/test', 'GET');
        $response = new Response('', 200);
        $event = new TerminateEvent($kernel, $request, $response);

        // First create a span in the registry
        $provider = InMemoryProviderFactory::create();
        $tracer = $provider->getTracer('test');
        $span = $tracer->spanBuilder('test')->startSpan();
        $scope = $span->activate();
        $this->registry->addSpan($span, \Macpaw\SymfonyOtelBundle\Registry\SpanNames::REQUEST_START);
        $this->registry->setScope($scope);

        $subscriber->onKernelTerminate($event);

        $scope->detach();
        $span->end();
    }

    public function testOnKernelTerminateWithForceFlush(): void
    {
        $subscriber = new RequestRootSpanEventSubscriber(
            $this->registry,
            $this->propagator,
            $this->traceService,
            $this->httpMetadataAttacher,
            true, // forceFlushOnTerminate
            200,
        );

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/test', 'GET');
        $response = new Response('', 200);
        $event = new TerminateEvent($kernel, $request, $response);

        // Create a span
        $provider = InMemoryProviderFactory::create();
        $tracer = $provider->getTracer('test');
        $span = $tracer->spanBuilder('test')->startSpan();
        $scope = $span->activate();
        $this->registry->addSpan($span, \Macpaw\SymfonyOtelBundle\Registry\SpanNames::REQUEST_START);
        $this->registry->setScope($scope);

        $subscriber->onKernelTerminate($event);

        $scope->detach();
        $span->end();
    }

    public function testOnKernelTerminateWithoutSpan(): void
    {
        $subscriber = new RequestRootSpanEventSubscriber(
            $this->registry,
            $this->propagator,
            $this->traceService,
            $this->httpMetadataAttacher,
            false,
            100,
        );

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/test', 'GET');
        $response = new Response('', 200);
        $event = new TerminateEvent($kernel, $request, $response);

        // No span in registry
        $subscriber->onKernelTerminate($event);
        $this->assertTrue(true);
    }

    public function testGetSubscribedEvents(): void
    {
        $events = RequestRootSpanEventSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(KernelEvents::REQUEST, $events);
        $this->assertArrayHasKey(KernelEvents::TERMINATE, $events);
        $this->assertEquals(
            [['onKernelRequest', PHP_INT_MAX]],
            $events[KernelEvents::REQUEST],
        );
        $this->assertEquals(
            [['onKernelTerminate', PHP_INT_MAX]],
            $events[KernelEvents::TERMINATE],
        );
    }

    public function testOnKernelRequestWithInvalidContext(): void
    {
        $subscriber = new RequestRootSpanEventSubscriber(
            $this->registry,
            $this->propagator,
            $this->traceService,
            $this->httpMetadataAttacher,
            false,
            100,
        );

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/test', 'GET');
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $invalidContext = \OpenTelemetry\Context\Context::getCurrent();
        $this->propagator->expects($this->once())
            ->method('extract')
            ->willReturn($invalidContext);

        $subscriber->onKernelRequest($event);
    }

    protected function setUp(): void
    {
        $this->registry = new InstrumentationRegistry();
        $this->propagator = $this->createMock(TextMapPropagatorInterface::class);
        $provider = InMemoryProviderFactory::create();
        $this->traceService = new TraceService($provider, 'test-service', 'test-tracer');
        $requestStack = new RequestStack();
        $routerUtils = new RouterUtils($requestStack);
        $this->httpMetadataAttacher = new HttpMetadataAttacher($routerUtils);
    }
}

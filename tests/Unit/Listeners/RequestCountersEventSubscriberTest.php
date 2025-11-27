<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use Exception;
use Macpaw\SymfonyOtelBundle\Instrumentation\Utils\RouterUtils;
use Macpaw\SymfonyOtelBundle\Listeners\RequestCountersEventSubscriber;
use Macpaw\SymfonyOtelBundle\Registry\InstrumentationRegistry;
use Macpaw\SymfonyOtelBundle\Registry\SpanNames;
use OpenTelemetry\API\Metrics\CounterInterface;
use OpenTelemetry\API\Metrics\MeterInterface;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Tests\Support\Telemetry\InMemoryProviderFactory;

class RequestCountersEventSubscriberTest extends TestCase
{
    private MeterProviderInterface&MockObject $meterProvider;

    private RouterUtils $routerUtils;

    private InstrumentationRegistry $registry;

    public function testOnKernelRequestWithOtelBackend(): void
    {
        $meter = $this->createMock(MeterInterface::class);
        $requestCounter = $this->createMock(CounterInterface::class);

        $this->meterProvider->expects($this->once())
            ->method('getMeter')
            ->with('symfony-otel-bundle')
            ->willReturn($meter);

        $meter->expects($this->exactly(2))
            ->method('createCounter')
            ->willReturnOnConsecutiveCalls(
                $requestCounter,
                $this->createMock(CounterInterface::class),
            );

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/test', 'GET');
        $request->attributes->set('_route', 'test_route');

        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn($request);
        $requestStack->method('getMainRequest')->willReturn($request);
        $requestStack->method('getParentRequest')->willReturn(null);
        $routerUtils = new RouterUtils($requestStack);

        $subscriber = new RequestCountersEventSubscriber(
            $this->meterProvider,
            $routerUtils,
            $this->registry,
            'otel',
        );

        $requestCounter->expects($this->once())
            ->method('add')
            ->with(1, [
                'http.route' => 'test_route',
                'http.request.method' => 'GET',
            ]);

        $subscriber->onKernelRequest($event);
    }

    public function testOnKernelRequestWithEventBackend(): void
    {
        $subscriber = new RequestCountersEventSubscriber(
            $this->meterProvider,
            $this->routerUtils,
            $this->registry,
            'event',
        );

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/test', 'GET');
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        // Create a span for the event backend
        $provider = InMemoryProviderFactory::create();
        $tracer = $provider->getTracer('test');
        $span = $tracer->spanBuilder('test')->startSpan();
        $this->registry->addSpan($span, SpanNames::REQUEST_START);

        $subscriber->onKernelRequest($event);

        // Verify span was accessed (event backend adds event to span)
        $this->assertNotNull($this->registry->getSpan(SpanNames::REQUEST_START));
        $span->end();
    }

    public function testOnKernelRequestWithSubRequest(): void
    {
        $subscriber = new RequestCountersEventSubscriber(
            $this->meterProvider,
            $this->routerUtils,
            $this->registry,
            'otel',
        );

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/test', 'GET');
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST);

        // Should return early for sub-requests
        $subscriber->onKernelRequest($event);
        $this->assertTrue(true);
    }

    public function testOnKernelTerminateWithOtelBackend(): void
    {
        $meter = $this->createMock(MeterInterface::class);
        $responseFamilyCounter = $this->createMock(CounterInterface::class);

        $this->meterProvider->expects($this->once())
            ->method('getMeter')
            ->willReturn($meter);

        $meter->expects($this->exactly(2))
            ->method('createCounter')
            ->willReturnOnConsecutiveCalls(
                $this->createMock(CounterInterface::class),
                $responseFamilyCounter,
            );

        $subscriber = new RequestCountersEventSubscriber(
            $this->meterProvider,
            $this->routerUtils,
            $this->registry,
            'otel',
        );

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/test', 'GET');
        $response = new Response('', 200);
        $event = new TerminateEvent($kernel, $request, $response);

        $responseFamilyCounter->expects($this->once())
            ->method('add')
            ->with(1, ['http.status_family' => '2xx']);

        $subscriber->onKernelTerminate($event);
    }

    public function testOnKernelTerminateWithEventBackend(): void
    {
        $subscriber = new RequestCountersEventSubscriber(
            $this->meterProvider,
            $this->routerUtils,
            $this->registry,
            'event',
        );

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/test', 'GET');
        $response = new Response('', 404);
        $event = new TerminateEvent($kernel, $request, $response);

        // Create a span for the event backend
        $provider = InMemoryProviderFactory::create();
        $tracer = $provider->getTracer('test');
        $span = $tracer->spanBuilder('test')->startSpan();
        $this->registry->addSpan($span, SpanNames::REQUEST_START);

        $subscriber->onKernelTerminate($event);

        // Verify span was accessed
        $this->assertNotNull($this->registry->getSpan(SpanNames::REQUEST_START));
        $span->end();
    }

    public function testOnKernelTerminateWithStatus5xx(): void
    {
        $meter = $this->createMock(MeterInterface::class);
        $responseFamilyCounter = $this->createMock(CounterInterface::class);

        $this->meterProvider->method('getMeter')->willReturn($meter);
        $meter->method('createCounter')->willReturnOnConsecutiveCalls(
            $this->createMock(CounterInterface::class),
            $responseFamilyCounter,
        );

        $subscriber = new RequestCountersEventSubscriber(
            $this->meterProvider,
            $this->routerUtils,
            $this->registry,
            'otel',
        );

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/test', 'GET');
        $response = new Response('', 500);
        $event = new TerminateEvent($kernel, $request, $response);

        $responseFamilyCounter->expects($this->once())
            ->method('add')
            ->with(1, ['http.status_family' => '5xx']);

        $subscriber->onKernelTerminate($event);
    }

    public function testOnKernelTerminateWithoutSpan(): void
    {
        $subscriber = new RequestCountersEventSubscriber(
            $this->meterProvider,
            $this->routerUtils,
            $this->registry,
            'event',
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
        $events = RequestCountersEventSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(KernelEvents::REQUEST, $events);
        $this->assertArrayHasKey(KernelEvents::TERMINATE, $events);
    }

    public function testConstructorWithInvalidBackend(): void
    {
        // Should default to 'otel' when invalid backend is provided
        $subscriber = new RequestCountersEventSubscriber(
            $this->meterProvider,
            $this->routerUtils,
            $this->registry,
            'invalid',
        );

        $this->assertInstanceOf(RequestCountersEventSubscriber::class, $subscriber);
    }

    public function testConstructorWithMetricsException(): void
    {
        $this->meterProvider->expects($this->once())
            ->method('getMeter')
            ->willThrowException(new Exception('Metrics not available'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('debug')
            ->with(
                'Metrics not available, falling back to event backend',
                $this->arrayHasKey('error'),
            );

        $subscriber = new RequestCountersEventSubscriber(
            $this->meterProvider,
            $this->routerUtils,
            $this->registry,
            'otel',
            $logger,
        );

        $this->assertInstanceOf(RequestCountersEventSubscriber::class, $subscriber);
    }

    public function testSafeAddWithException(): void
    {
        $meter = $this->createMock(MeterInterface::class);
        $requestCounter = $this->createMock(CounterInterface::class);

        $this->meterProvider->method('getMeter')->willReturn($meter);
        $meter->method('createCounter')->willReturnOnConsecutiveCalls(
            $requestCounter,
            $this->createMock(CounterInterface::class),
        );

        $requestCounter->expects($this->once())
            ->method('add')
            ->willThrowException(new Exception('Counter error'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('debug')
            ->with('Failed to increment counter', $this->arrayHasKey('error'));

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/test', 'GET');
        $request->attributes->set('_route', 'test_route');

        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn($request);
        $requestStack->method('getMainRequest')->willReturn($request);
        $requestStack->method('getParentRequest')->willReturn(null);
        $routerUtils = new RouterUtils($requestStack);

        $subscriber = new RequestCountersEventSubscriber(
            $this->meterProvider,
            $routerUtils,
            $this->registry,
            'otel',
            $logger,
        );

        $subscriber->onKernelRequest($event);
    }

    protected function setUp(): void
    {
        $this->meterProvider = $this->createMock(MeterProviderInterface::class);
        $requestStack = $this->createMock(RequestStack::class);
        $this->routerUtils = new RouterUtils($requestStack);
        $this->registry = new InstrumentationRegistry();
    }
}


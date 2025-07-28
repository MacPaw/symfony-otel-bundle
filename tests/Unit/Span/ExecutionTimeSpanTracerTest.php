<?php

declare(strict_types=1);

namespace Tests\Unit\Span;

use Macpaw\SymfonyOtelBundle\Instrumentation\RequestExecutionTimeInstrumentation;
use Macpaw\SymfonyOtelBundle\Registry\InstrumentationRegistry;
use Macpaw\SymfonyOtelBundle\Listeners\InstrumentationEventSubscriber;
use OpenTelemetry\API\Common\Time\ClockInterface;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class ExecutionTimeSpanTracerTest extends TestCase
{
    public function testOnKernelRequestAndTerminate(): void
    {
        $registry = new InstrumentationRegistry();
        $tracer = $this->createMock(TracerInterface::class);
        $propagator = $this->createMock(TextMapPropagatorInterface::class);
        $clock = $this->createMock(ClockInterface::class);
        $executionTimeInstrumentation = new RequestExecutionTimeInstrumentation(
            $registry,
            $tracer,
            $propagator,
            $clock
        );
        $subscriber = new InstrumentationEventSubscriber($executionTimeInstrumentation);
        $request = new Request();
        $request->headers->add([
            'traceparent' => '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01',
        ]);
        $kernel = $this->createMock(HttpKernelInterface::class);
        $requestEvent = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $terminateEvent = new TerminateEvent($kernel, $request, new Response());
        $subscriber->onKernelRequestExecutionTime($requestEvent);
        $subscriber->onKernelTerminateExecutionTime($terminateEvent);
        $this->assertInstanceOf(InstrumentationEventSubscriber::class, $subscriber);
    }

    public function testOnKernelRequestWithInvalidTraceContext(): void
    {
        $registry = new InstrumentationRegistry();
        $tracer = $this->createMock(TracerInterface::class);
        $propagator = $this->createMock(TextMapPropagatorInterface::class);
        $clock = $this->createMock(ClockInterface::class);
        $executionTimeInstrumentation = new RequestExecutionTimeInstrumentation(
            $registry,
            $tracer,
            $propagator,
            $clock
        );
        $subscriber = new InstrumentationEventSubscriber($executionTimeInstrumentation);
        $request = new Request();
        $kernel = $this->createMock(HttpKernelInterface::class);
        $requestEvent = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onKernelRequestExecutionTime($requestEvent);
        $this->assertInstanceOf(InstrumentationEventSubscriber::class, $subscriber);
    }

    public function testGetSubscribedEvents(): void
    {
        $events = InstrumentationEventSubscriber::getSubscribedEvents();
        $this->assertArrayHasKey('kernel.request', $events);
        $this->assertArrayHasKey('kernel.terminate', $events);
        $this->assertEquals([['onKernelRequestExecutionTime', -PHP_INT_MAX + 2]], $events['kernel.request']);
        $this->assertEquals([['onKernelTerminateExecutionTime', PHP_INT_MAX]], $events['kernel.terminate']);
    }

    public function testOnKernelRequestStoresStartTime(): void
    {
        $registry = new InstrumentationRegistry();
        $tracer = $this->createMock(TracerInterface::class);
        $propagator = $this->createMock(TextMapPropagatorInterface::class);
        $clock = $this->createMock(ClockInterface::class);
        $executionTimeInstrumentation = new RequestExecutionTimeInstrumentation(
            $registry,
            $tracer,
            $propagator,
            $clock
        );
        $subscriber = new InstrumentationEventSubscriber($executionTimeInstrumentation);
        $request = new Request();
        $kernel = $this->createMock(HttpKernelInterface::class);
        $requestEvent = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $terminateEvent = new TerminateEvent($kernel, $request, new Response());
        $startTime = microtime(true);
        $subscriber->onKernelRequestExecutionTime($requestEvent);
        usleep(1000);
        $subscriber->onKernelTerminateExecutionTime($terminateEvent);
        $this->assertInstanceOf(InstrumentationEventSubscriber::class, $subscriber);
    }

    public function testSubRequestIsIgnored(): void
    {
        $registry = new InstrumentationRegistry();
        $tracer = $this->createMock(TracerInterface::class);
        $propagator = $this->createMock(TextMapPropagatorInterface::class);
        $clock = $this->createMock(ClockInterface::class);
        $executionTimeInstrumentation = new RequestExecutionTimeInstrumentation(
            $registry,
            $tracer,
            $propagator,
            $clock
        );
        $subscriber = new InstrumentationEventSubscriber($executionTimeInstrumentation);
        $request = new Request();
        $kernel = $this->createMock(HttpKernelInterface::class);
        $requestEvent = new RequestEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST);
        $subscriber->onKernelRequestExecutionTime($requestEvent);
        $this->assertInstanceOf(InstrumentationEventSubscriber::class, $subscriber);
    }

    public function testExecutionTimeIsPositive(): void
    {
        $registry = new InstrumentationRegistry();
        $tracer = $this->createMock(TracerInterface::class);
        $propagator = $this->createMock(TextMapPropagatorInterface::class);
        $clock = $this->createMock(ClockInterface::class);
        $executionTimeInstrumentation = new RequestExecutionTimeInstrumentation(
            $registry,
            $tracer,
            $propagator,
            $clock
        );
        $subscriber = new InstrumentationEventSubscriber($executionTimeInstrumentation);
        $request = new Request();
        $kernel = $this->createMock(HttpKernelInterface::class);
        $requestEvent = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $terminateEvent = new TerminateEvent($kernel, $request, new Response());
        $subscriber->onKernelRequestExecutionTime($requestEvent);
        $subscriber->onKernelTerminateExecutionTime($terminateEvent);
        $this->assertInstanceOf(InstrumentationEventSubscriber::class, $subscriber);
    }
}

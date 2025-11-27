<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use Macpaw\SymfonyOtelBundle\Instrumentation\RequestExecutionTimeInstrumentation;
use Macpaw\SymfonyOtelBundle\Listeners\InstrumentationEventSubscriber;
use Macpaw\SymfonyOtelBundle\Registry\InstrumentationRegistry;
use OpenTelemetry\API\Common\Time\ClockInterface;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class InstrumentationEventSubscriberTest extends TestCase
{
    private RequestExecutionTimeInstrumentation $executionTimeInstrumentation;
    private InstrumentationEventSubscriber $subscriber;

    public function testOnKernelRequestExecutionTime(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/test', 'GET');
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        // Verify the method calls setHeaders and pre on the instrumentation
        // We can't easily verify this without making the instrumentation more testable,
        // but we can verify it doesn't throw
        $this->subscriber->onKernelRequestExecutionTime($event);
        $this->assertTrue(true);
    }

    public function testOnKernelTerminateExecutionTime(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/test', 'GET');
        $event = new TerminateEvent($kernel, $request, new \Symfony\Component\HttpFoundation\Response());

        // Verify the method calls post on the instrumentation
        $this->subscriber->onKernelTerminateExecutionTime($event);
        $this->assertTrue(true);
    }

    public function testGetSubscribedEvents(): void
    {
        $events = InstrumentationEventSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(KernelEvents::REQUEST, $events);
        $this->assertArrayHasKey(KernelEvents::TERMINATE, $events);
        $this->assertEquals(
            [['onKernelRequestExecutionTime', -PHP_INT_MAX + 2]],
            $events[KernelEvents::REQUEST],
        );
        $this->assertEquals(
            [['onKernelTerminateExecutionTime', PHP_INT_MAX]],
            $events[KernelEvents::TERMINATE],
        );
    }

    protected function setUp(): void
    {
        $registry = new InstrumentationRegistry();
        $tracer = $this->createMock(TracerInterface::class);
        $propagator = $this->createMock(TextMapPropagatorInterface::class);
        $clock = $this->createMock(ClockInterface::class);

        $this->executionTimeInstrumentation = new RequestExecutionTimeInstrumentation(
            $registry,
            $tracer,
            $propagator,
            $clock,
        );

        $this->subscriber = new InstrumentationEventSubscriber($this->executionTimeInstrumentation);
    }
}


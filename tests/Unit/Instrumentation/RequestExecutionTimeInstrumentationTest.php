<?php

declare(strict_types=1);

namespace Tests\Unit\Instrumentation;

use Macpaw\SymfonyOtelBundle\Instrumentation\RequestExecutionTimeInstrumentation;
use Macpaw\SymfonyOtelBundle\Registry\InstrumentationRegistry;
use OpenTelemetry\API\Common\Time\ClockInterface;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RequestExecutionTimeInstrumentationTest extends TestCase
{
    private InstrumentationRegistry $registry;
    private TracerInterface&MockObject $tracer;
    private TextMapPropagatorInterface&MockObject $propagator;
    private ClockInterface&MockObject $clock;
    private RequestExecutionTimeInstrumentation $instrumentation;

    public function testSetHeaders(): void
    {
        $headers = ['traceparent' => '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01'];
        $this->instrumentation->setHeaders($headers);

        // Headers are set, verify by testing retrieveContext uses them
        $this->propagator->expects($this->once())
            ->method('extract')
            ->with($headers)
            ->willReturn(Context::getCurrent());

        $this->tracer->expects($this->once())
            ->method('spanBuilder')
            ->willReturn($this->createMock(SpanBuilderInterface::class));

        $this->clock->expects($this->once())
            ->method('now')
            ->willReturn(1000000);

        $this->instrumentation->pre();
    }

    public function testRetrieveContextWhenRegistryContextIsNull(): void
    {
        $headers = ['traceparent' => '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01'];
        $this->instrumentation->setHeaders($headers);

        $context = Context::getCurrent();
        $this->propagator->expects($this->once())
            ->method('extract')
            ->with($headers)
            ->willReturn($context);

        $spanBuilder = $this->createMock(SpanBuilderInterface::class);
        $span = $this->createMock(SpanInterface::class);

        $spanBuilder->expects($this->once())
            ->method('setSpanKind')
            ->willReturnSelf();
        $spanBuilder->expects($this->once())
            ->method('startSpan')
            ->willReturn($span);
        $spanBuilder->expects($this->once())
            ->method('setParent')
            ->with($this->isInstanceOf(\OpenTelemetry\Context\ContextInterface::class))
            ->willReturnSelf();

        $this->tracer->expects($this->once())
            ->method('spanBuilder')
            ->willReturn($spanBuilder);

        $this->clock->expects($this->once())
            ->method('now')
            ->willReturn(1000000);

        $this->instrumentation->pre();
    }

    public function testRetrieveContextWhenExtractedContextIsInvalid(): void
    {
        $headers = [];
        $this->instrumentation->setHeaders($headers);

        $invalidContext = Context::getCurrent();
        $this->propagator->expects($this->once())
            ->method('extract')
            ->with($headers)
            ->willReturn($invalidContext);

        $spanBuilder = $this->createMock(SpanBuilderInterface::class);
        $span = $this->createMock(SpanInterface::class);

        $spanBuilder->expects($this->once())
            ->method('setSpanKind')
            ->willReturnSelf();
        $spanBuilder->expects($this->once())
            ->method('startSpan')
            ->willReturn($span);
        $spanBuilder->expects($this->once())
            ->method('setParent')
            ->willReturnSelf();

        $this->tracer->expects($this->once())
            ->method('spanBuilder')
            ->willReturn($spanBuilder);

        $this->clock->expects($this->once())
            ->method('now')
            ->willReturn(1000000);

        $this->instrumentation->pre();
    }

    public function testPostWhenSpanIsNotSet(): void
    {
        $span = $this->createMock(SpanInterface::class);
        $span->expects($this->never())
            ->method('setAttribute');
        $span->expects($this->never())
            ->method('end');

        // isSpanSet is false, so post() should not do anything
        $this->instrumentation->post();
    }

    public function testPostWhenSpanIsSet(): void
    {
        $spanBuilder = $this->createMock(SpanBuilderInterface::class);
        $span = $this->createMock(SpanInterface::class);

        $spanBuilder->expects($this->once())
            ->method('setSpanKind')
            ->willReturnSelf();
        $spanBuilder->expects($this->once())
            ->method('startSpan')
            ->willReturn($span);
        $spanBuilder->expects($this->once())
            ->method('setParent')
            ->willReturnSelf();

        $this->tracer->expects($this->once())
            ->method('spanBuilder')
            ->willReturn($spanBuilder);

        $this->clock->expects($this->exactly(2))
            ->method('now')
            ->willReturnOnConsecutiveCalls(1000000, 2000000);

        $this->instrumentation->pre();

        $span->expects($this->once())
            ->method('setAttribute')
            ->with('request.exec_time_ns', 1000000);
        $span->expects($this->once())
            ->method('end');

        $this->instrumentation->post();
    }

    public function testGetName(): void
    {
        $this->assertEquals('request.execution_time', $this->instrumentation->getName());
    }

    public function testRetrieveContextWhenRegistryContextIsNotNull(): void
    {
        // Test the path where registry context is not null (line 65-66)
        $context = Context::getCurrent();
        $this->registry->setContext($context);

        $spanBuilder = $this->createMock(SpanBuilderInterface::class);
        $span = $this->createMock(SpanInterface::class);

        $spanBuilder->expects($this->once())
            ->method('setSpanKind')
            ->willReturnSelf();
        $spanBuilder->expects($this->once())
            ->method('startSpan')
            ->willReturn($span);
        $spanBuilder->expects($this->once())
            ->method('setParent')
            ->with($context)
            ->willReturnSelf();

        $this->tracer->expects($this->once())
            ->method('spanBuilder')
            ->willReturn($spanBuilder);

        $this->clock->expects($this->once())
            ->method('now')
            ->willReturn(1000000);

        // When registry context is not null, it should return it directly
        $this->instrumentation->pre();
        $this->assertTrue(true);
    }

    protected function setUp(): void
    {
        $this->registry = new InstrumentationRegistry();
        $this->tracer = $this->createMock(TracerInterface::class);
        $this->propagator = $this->createMock(TextMapPropagatorInterface::class);
        $this->clock = $this->createMock(ClockInterface::class);

        $this->instrumentation = new RequestExecutionTimeInstrumentation(
            $this->registry,
            $this->tracer,
            $this->propagator,
            $this->clock,
        );
    }
}


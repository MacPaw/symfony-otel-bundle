<?php

declare(strict_types=1);

namespace Tests\Unit\Instrumentation;

use OpenTelemetry\SemConv\Attributes\CodeAttributes;
use Macpaw\SymfonyOtelBundle\Instrumentation\ClassHookInstrumentation;
use Macpaw\SymfonyOtelBundle\Middleware\ClassHookInstrumentationSpanMiddlewareInterface;
use Macpaw\SymfonyOtelBundle\Registry\InstrumentationRegistry;
use OpenTelemetry\API\Common\Time\ClockInterface;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ClassHookInstrumentationTest extends TestCase
{
    private InstrumentationRegistry $instrumentationRegistry;

    private MockObject&TracerInterface $tracer;

    private MockObject&TextMapPropagatorInterface $propagator;

    private MockObject&ClockInterface $clock;

    private string $className;

    private string $methodName;

    protected function setUp(): void
    {
        $this->instrumentationRegistry = new InstrumentationRegistry();
        $this->tracer = $this->createMock(TracerInterface::class);
        $this->propagator = $this->createMock(TextMapPropagatorInterface::class);
        $this->clock = $this->createMock(ClockInterface::class);
        $this->className = 'TestClass';
        $this->methodName = 'testMethod';
    }

    public function testConstructorAndGetters(): void
    {
        $instrumentation = new ClassHookInstrumentation(
            $this->instrumentationRegistry,
            $this->tracer,
            $this->propagator,
            $this->clock,
            $this->className,
            $this->methodName
        );

        $this->assertInstanceOf(ClassHookInstrumentation::class, $instrumentation);
        $this->assertEquals($this->className, $instrumentation->getClass());
        $this->assertEquals($this->methodName, $instrumentation->getMethod());
        $this->assertEquals(ClassHookInstrumentation::NAME, $instrumentation->getName());
        $this->assertEquals('class_method.execution_time', $instrumentation->getName());
    }

    public function testTimingMethods(): void
    {
        $instrumentation = new ClassHookInstrumentation(
            $this->instrumentationRegistry,
            $this->tracer,
            $this->propagator,
            $this->clock,
            $this->className,
            $this->methodName
        );

        $this->assertEquals(0, $instrumentation->getStartTime());
        $this->assertEquals(0, $instrumentation->getEndTime());
        $this->assertEquals(0, $instrumentation->getExecutionTime());
    }

    public function testCompleteExecutionCycle(): void
    {
        $startTime = 1000000;
        $endTime = 2000000;

        $this->clock->expects($this->exactly(2))
            ->method('now')
            ->willReturnOnConsecutiveCalls($startTime, $endTime);

        $spanBuilder = $this->createMock(SpanBuilderInterface::class);
        $span = $this->createMock(SpanInterface::class);

        $this->tracer->expects($this->once())
            ->method('spanBuilder')
            ->with(ClassHookInstrumentation::NAME)
            ->willReturn($spanBuilder);

        $spanBuilder->expects($this->once())
            ->method('setParent')
            ->willReturn($spanBuilder);

        $spanBuilder->expects($this->once())
            ->method('setSpanKind')
            ->with(SpanKind::KIND_SERVER)
            ->willReturn($spanBuilder);

        $spanBuilder->expects($this->once())
            ->method('startSpan')
            ->willReturn($span);

        $span->expects($this->once())
            ->method('end');

        $instrumentation = new ClassHookInstrumentation(
            $this->instrumentationRegistry,
            $this->tracer,
            $this->propagator,
            $this->clock,
            $this->className,
            $this->methodName
        );

        $instrumentation->pre();
        $this->assertEquals($startTime, $instrumentation->getStartTime());
        $this->assertEquals(0, $instrumentation->getEndTime());

        $spans = $this->instrumentationRegistry->getSpans();
        $this->assertArrayHasKey(ClassHookInstrumentation::NAME, $spans);

        $instrumentation->post();
        $this->assertEquals($startTime, $instrumentation->getStartTime());
        $this->assertEquals($endTime, $instrumentation->getEndTime());
        $this->assertEquals($endTime - $startTime, $instrumentation->getExecutionTime());
    }

    public function testWithMiddlewares(): void
    {
        $startTime = 1000000;
        $endTime = 2000000;

        $this->clock->expects($this->exactly(2))
            ->method('now')
            ->willReturnOnConsecutiveCalls($startTime, $endTime);

        $spanBuilder = $this->createMock(SpanBuilderInterface::class);
        $span = $this->createMock(SpanInterface::class);

        $this->tracer->expects($this->once())
            ->method('spanBuilder')
            ->willReturn($spanBuilder);

        $spanBuilder->expects($this->once())
            ->method('setParent')
            ->willReturn($spanBuilder);

        $spanBuilder->expects($this->once())
            ->method('setSpanKind')
            ->willReturn($spanBuilder);

        $spanBuilder->expects($this->once())
            ->method('startSpan')
            ->willReturn($span);

        $span->expects($this->once())
            ->method('end');

        $middleware1 = $this->createMock(ClassHookInstrumentationSpanMiddlewareInterface::class);
        $middleware2 = $this->createMock(ClassHookInstrumentationSpanMiddlewareInterface::class);

        $middleware1->expects($this->once())
            ->method('pre')
            ->with($span, $this->isInstanceOf(ClassHookInstrumentation::class));

        $middleware2->expects($this->once())
            ->method('pre')
            ->with($span, $this->isInstanceOf(ClassHookInstrumentation::class));

        $middleware1->expects($this->once())
            ->method('post')
            ->with($span, $this->isInstanceOf(ClassHookInstrumentation::class));

        $middleware2->expects($this->once())
            ->method('post')
            ->with($span, $this->isInstanceOf(ClassHookInstrumentation::class));

        $instrumentation = new ClassHookInstrumentation(
            $this->instrumentationRegistry,
            $this->tracer,
            $this->propagator,
            $this->clock,
            $this->className,
            $this->methodName,
            $middleware1,
            $middleware2
        );

        $instrumentation->pre();
        $instrumentation->post();

        $this->assertEquals($endTime - $startTime, $instrumentation->getExecutionTime());
    }

    public function testPreSetsCodeFunctionNameAttribute(): void
    {
        $startTime = 1000000;
        $endTime = 2000000;

        $this->clock->expects($this->exactly(2))
            ->method('now')
            ->willReturnOnConsecutiveCalls($startTime, $endTime);

        $spanBuilder = $this->createMock(SpanBuilderInterface::class);
        $span = $this->createMock(SpanInterface::class);

        $this->tracer->expects($this->once())
            ->method('spanBuilder')
            ->with(ClassHookInstrumentation::NAME)
            ->willReturn($spanBuilder);

        $spanBuilder->expects($this->once())
            ->method('setParent')
            ->willReturn($spanBuilder);

        $spanBuilder->expects($this->once())
            ->method('setSpanKind')
            ->with(SpanKind::KIND_SERVER)
            ->willReturn($spanBuilder);

        $spanBuilder->expects($this->once())
            ->method('startSpan')
            ->willReturn($span);

        // Verify CODE_FUNCTION_NAME attribute is set
        $span->expects($this->once())
            ->method('setAttribute')
            ->with(
                CodeAttributes::CODE_FUNCTION_NAME,
                sprintf('%s::%s', $this->className, $this->methodName)
            );

        $span->expects($this->once())
            ->method('end');

        $instrumentation = new ClassHookInstrumentation(
            $this->instrumentationRegistry,
            $this->tracer,
            $this->propagator,
            $this->clock,
            $this->className,
            $this->methodName
        );

        $instrumentation->pre();
        $instrumentation->post();
    }
}

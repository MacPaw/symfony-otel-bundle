<?php

declare(strict_types=1);

namespace Tests\Unit\Instrumentation;

use Macpaw\SymfonyOtelBundle\Registry\InstrumentationRegistry;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class AbstractInstrumentationTest extends TestCase
{
    private TestAbstractInstrumentation $instrumentation;
    private InstrumentationRegistry $registry;
    private TracerInterface&MockObject $tracer;
    private TextMapPropagatorInterface&MockObject $propagator;
    private SpanInterface&MockObject $span;
    private SpanBuilderInterface&MockObject $spanBuilder;

    protected function setUp(): void
    {
        $this->registry = new InstrumentationRegistry();
        $this->tracer = $this->createMock(TracerInterface::class);
        $this->propagator = $this->createMock(TextMapPropagatorInterface::class);
        $this->span = $this->createMock(SpanInterface::class);
        $this->spanBuilder = $this->createMock(SpanBuilderInterface::class);

        $this->instrumentation = new TestAbstractInstrumentation(
            $this->registry,
            $this->tracer,
            $this->propagator
        );
    }

    public function testInitSpanWithNullContext(): void
    {
        $this->spanBuilder->expects($this->once())
            ->method('setParent')
            ->with($this->isInstanceOf(ContextInterface::class))
            ->willReturnSelf();

        $this->tracer->expects($this->once())
            ->method('spanBuilder')
            ->with('test_instrumentation')
            ->willReturn($this->spanBuilder);

        $this->spanBuilder->expects($this->once())
            ->method('startSpan')
            ->willReturn($this->span);

        $this->instrumentation->testInitSpan(null);

        $this->assertTrue($this->instrumentation->isSpanSet());
        $this->assertCount(1, $this->registry->getSpans());
        $this->assertSame($this->span, $this->registry->getSpans()['test_instrumentation']);
    }

    public function testInitSpanWithProvidedContext(): void
    {
        $context = Context::getCurrent();

        $this->spanBuilder->expects($this->once())
            ->method('setParent')
            ->with($context)
            ->willReturnSelf();

        $this->tracer->expects($this->once())
            ->method('spanBuilder')
            ->with('test_instrumentation')
            ->willReturn($this->spanBuilder);

        $this->spanBuilder->expects($this->once())
            ->method('startSpan')
            ->willReturn($this->span);

        $this->instrumentation->testInitSpan($context);

        $this->assertTrue($this->instrumentation->isSpanSet());
        $this->assertCount(1, $this->registry->getSpans());
    }

    public function testInitSpanRemovesExistingSpan(): void
    {
        $this->tracer->expects($this->exactly(2))
            ->method('spanBuilder')
            ->with('test_instrumentation')
            ->willReturn($this->spanBuilder);

        $this->spanBuilder->expects($this->exactly(2))
            ->method('setParent')
            ->willReturnSelf();

        $this->spanBuilder->expects($this->exactly(2))
            ->method('startSpan')
            ->willReturn($this->span);

        $this->instrumentation->testInitSpan(null);
        $this->assertCount(1, $this->registry->getSpans());

        $this->instrumentation->testInitSpan(null);
        $this->assertCount(1, $this->registry->getSpans());
    }

    public function testCloseSpanWhenSpanIsSet(): void
    {
        $this->tracer->expects($this->once())
            ->method('spanBuilder')
            ->willReturn($this->spanBuilder);

        $this->spanBuilder->expects($this->once())
            ->method('setParent')
            ->willReturnSelf();

        $this->spanBuilder->expects($this->once())
            ->method('startSpan')
            ->willReturn($this->span);

        $this->span->expects($this->once())
            ->method('end');

        $this->instrumentation->testInitSpan(null);
        $this->instrumentation->testCloseSpan($this->span);
    }

    public function testCloseSpanWhenSpanIsNotSet(): void
    {
        $this->span->expects($this->never())
            ->method('end');

        $this->instrumentation->testCloseSpan($this->span);
    }

    public function testGetName(): void
    {
        $this->assertEquals('test_instrumentation', $this->instrumentation->getName());
    }

    public function testInitSpanWhenRegistryContextIsNull(): void
    {
        // Set context to null in registry after setting it
        $context = Context::getCurrent();
        $this->registry->setContext($context);

        // Manually clear the context to simulate it being null
        // We need to use reflection to set it to null
        $reflection = new ReflectionClass($this->registry);
        $property = $reflection->getProperty('context');
        $property->setAccessible(true);
        $property->setValue($this->registry, null);

        $this->spanBuilder->expects($this->once())
            ->method('setParent')
            ->with($this->isInstanceOf(ContextInterface::class))
            ->willReturnSelf();

        $this->tracer->expects($this->once())
            ->method('spanBuilder')
            ->with('test_instrumentation')
            ->willReturn($this->spanBuilder);

        $this->spanBuilder->expects($this->once())
            ->method('startSpan')
            ->willReturn($this->span);

        // When registry context is null, it should fall back to Context::getCurrent()
        $this->instrumentation->testInitSpan($context);

        $this->assertTrue($this->instrumentation->isSpanSet());
        $this->assertCount(1, $this->registry->getSpans());
    }

    public function testInitSpanWithNonNullContext(): void
    {
        // Test the null coalescing assignment when context is provided (line 37)
        $context = Context::getCurrent();

        $this->spanBuilder->expects($this->once())
            ->method('setParent')
            ->with($context)
            ->willReturnSelf();

        $this->tracer->expects($this->once())
            ->method('spanBuilder')
            ->with('test_instrumentation')
            ->willReturn($this->spanBuilder);

        $this->spanBuilder->expects($this->once())
            ->method('startSpan')
            ->willReturn($this->span);

        // When context is provided (not null), the null coalescing assignment should use it
        $this->instrumentation->testInitSpan($context);

        $this->assertTrue($this->instrumentation->isSpanSet());
    }
}

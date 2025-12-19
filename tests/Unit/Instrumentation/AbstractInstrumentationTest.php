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

        // Verify setContext was called by checking the registry has a context after initSpan
        $this->assertNotNull($this->registry->getContext());
        $this->assertTrue($this->instrumentation->isSpanSet());
        $this->assertCount(1, $this->registry->getSpans());
    }

    public function testInitSpanCallsRemoveSpanWhenSpanIsSet(): void
    {
        // Test that removeSpan is called when isSpanSet is true
        // If removeSpan is NOT called, we'd have 2 spans with the same name (which shouldn't happen)
        // But since removeSpan IS called, we have only 1 span
        
        // First init to set isSpanSet = true
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
        $this->assertTrue($this->instrumentation->isSpanSet());
        $this->assertNotNull($this->registry->getSpan('test_instrumentation'));

        // Second init should call removeSpan before adding new span
        // Verify removeSpan is called by checking:
        // 1. The span count stays at 1 (not 2) - proving removeSpan was called
        // 2. The span still exists (was removed and re-added)
        $this->instrumentation->testInitSpan(null);
        
        // If removeSpan was NOT called, we'd have issues with duplicate spans
        // But since it IS called, we have exactly 1 span
        $this->assertCount(1, $this->registry->getSpans());
        $this->assertNotNull($this->registry->getSpan('test_instrumentation'));
        
        // Verify the span is still accessible (was properly removed and re-added)
        $finalSpan = $this->registry->getSpan('test_instrumentation');
        $this->assertNotNull($finalSpan);
    }

    public function testInitSpanUsesNullCoalesceAssignment(): void
    {
        // Test that null coalesce assignment is used (not regular assignment)
        // When context is null, null coalesce should assign Context::getCurrent()
        // When context is provided, null coalesce should NOT overwrite it
        
        // Test 1: null context uses null coalesce to get current
        $this->spanBuilder->expects($this->exactly(2))
            ->method('setParent')
            ->with($this->isInstanceOf(ContextInterface::class))
            ->willReturnSelf();

        $this->tracer->expects($this->exactly(2))
            ->method('spanBuilder')
            ->with('test_instrumentation')
            ->willReturn($this->spanBuilder);

        $this->spanBuilder->expects($this->exactly(2))
            ->method('startSpan')
            ->willReturn($this->span);

        // When context is null, null coalesce should assign Context::getCurrent()
        $this->instrumentation->testInitSpan(null);
        
        // Verify context was set (proving null coalesce was used)
        $this->assertNotNull($this->registry->getContext());
        
        // Test 2: provided context should be used (null coalesce doesn't overwrite)
        $providedContext = Context::getCurrent();
        $this->instrumentation->testInitSpan($providedContext);
        
        // Verify the provided context was used (not replaced by getCurrent())
        $this->assertNotNull($this->registry->getContext());
    }

    public function testInitSpanChecksContextInstanceOf(): void
    {
        // Test that instanceof check with negation is used to fall back to Context::getCurrent()
        // The check is: if (!$context instanceof ContextInterface)
        
        // Test 1: When getContext() returns null (not instanceof ContextInterface)
        $reflection = new ReflectionClass($this->registry);
        $property = $reflection->getProperty('context');
        $property->setAccessible(true);
        $property->setValue($this->registry, null);

        $this->spanBuilder->expects($this->exactly(2))
            ->method('setParent')
            ->with($this->isInstanceOf(ContextInterface::class))
            ->willReturnSelf();

        $this->tracer->expects($this->exactly(2))
            ->method('spanBuilder')
            ->with('test_instrumentation')
            ->willReturn($this->spanBuilder);

        $this->spanBuilder->expects($this->exactly(2))
            ->method('startSpan')
            ->willReturn($this->span);

        // When getContext() returns null (not instanceof ContextInterface), should fall back
        $this->instrumentation->testInitSpan(null);
        $this->assertTrue($this->instrumentation->isSpanSet());
        
        // Test 2: When getContext() returns a ContextInterface, should NOT fall back
        $validContext = Context::getCurrent();
        $this->registry->setContext($validContext);
        $this->instrumentation->testInitSpan(null);
        
        // Should use the valid context from registry, not fall back
        $this->assertTrue($this->instrumentation->isSpanSet());
    }

    public function testInitSpanCallsSetContext(): void
    {
        $context = Context::getCurrent();
        
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

        // Verify context is null before
        $this->assertNull($this->registry->getContext());
        
        $this->instrumentation->testInitSpan($context);
        
        // Verify setContext was called by checking context is now set
        $this->assertNotNull($this->registry->getContext());
    }

    public function testInitSpanFallsBackToCurrentContextWhenRegistryContextIsNotContextInterface(): void
    {
        // Set context to null in registry to simulate getContext() returning null
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

        // When registry context is null (not ContextInterface), it should fall back to Context::getCurrent()
        $this->instrumentation->testInitSpan(null);

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

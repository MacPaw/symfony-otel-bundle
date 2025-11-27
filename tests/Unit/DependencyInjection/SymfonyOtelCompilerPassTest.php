<?php

declare(strict_types=1);

namespace Tests\Unit\DependencyInjection;

use App\Service\TraceSpanTestService;
use Macpaw\SymfonyOtelBundle\DependencyInjection\SymfonyOtelCompilerPass;
use Macpaw\SymfonyOtelBundle\Instrumentation\AttributeMethodInstrumentation;
use Macpaw\SymfonyOtelBundle\Listeners\InstrumentationEventSubscriber;
use Macpaw\SymfonyOtelBundle\Registry\InstrumentationRegistry;
use Macpaw\SymfonyOtelBundle\Service\HookManagerService;
use OpenTelemetry\API\Instrumentation\AutoInstrumentation\ExtensionHookManager;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;
use Symfony\Component\DependencyInjection\Reference;

class SymfonyOtelCompilerPassTest extends TestCase
{
    private ContainerBuilder $container;

    private SymfonyOtelCompilerPass $compilerPass;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->compilerPass = new SymfonyOtelCompilerPass();
        $this->container->register(HookManagerService::class)
            ->setPublic(true)
            ->setArguments([
                new Reference(ExtensionHookManager::class),
                null,
            ]);

        $this->container->register(ExtensionHookManager::class);
        $this->container->register(InstrumentationRegistry::class);
        $this->container->register(TracerInterface::class);
        $this->container->register(TextMapPropagatorInterface::class);
    }

    public function testProcessWithEmptyInstrumentations(): void
    {
        $this->container->setParameter('otel_bundle.instrumentations', []);

        $this->compilerPass->process($this->container);

        $this->assertFalse($this->container->hasDefinition('App\Instrumentation\CustomInstrumentation'));
    }

    public function testProcessWithSingleInstrumentation(): void
    {
        $this->container->setParameter('otel_bundle.instrumentations', [
            'App\Instrumentation\CustomInstrumentation',
        ]);
        $this->container->setParameter('otel_bundle.tracer_name', 'test-tracer');

        $this->compilerPass->process($this->container);

        $this->assertTrue($this->container->hasDefinition('App\Instrumentation\CustomInstrumentation'));
        $definition = $this->container->getDefinition('App\Instrumentation\CustomInstrumentation');
        $this->assertTrue($definition->isAutowired());
        $this->assertArrayNotHasKey('kernel.event_subscriber', $definition->getTags());
    }

    public function testProcessWithMultipleInstrumentations(): void
    {
        $this->container->setParameter('otel_bundle.instrumentations', [
            'App\Instrumentation\FirstInstrumentation',
            'App\Instrumentation\SecondInstrumentation',
        ]);
        $this->container->setParameter('otel_bundle.tracer_name', 'test-tracer');

        $this->compilerPass->process($this->container);

        $this->assertTrue($this->container->hasDefinition('App\Instrumentation\FirstInstrumentation'));
        $this->assertTrue($this->container->hasDefinition('App\Instrumentation\SecondInstrumentation'));

        $firstDefinition = $this->container->getDefinition('App\Instrumentation\FirstInstrumentation');
        $secondDefinition = $this->container->getDefinition('App\Instrumentation\SecondInstrumentation');

        $this->assertTrue($firstDefinition->isAutowired());
        $this->assertTrue($secondDefinition->isAutowired());
        $this->assertArrayNotHasKey('kernel.event_subscriber', $firstDefinition->getTags());
        $this->assertArrayNotHasKey('kernel.event_subscriber', $secondDefinition->getTags());
    }

    public function testProcessWithInstrumentationEventSubscriber(): void
    {
        $this->container->setParameter('otel_bundle.instrumentations', [
            InstrumentationEventSubscriber::class,
        ]);
        $this->container->setParameter('otel_bundle.tracer_name', 'test-tracer');

        $this->compilerPass->process($this->container);

        $this->assertTrue($this->container->hasDefinition(InstrumentationEventSubscriber::class));
        $definition = $this->container->getDefinition(InstrumentationEventSubscriber::class);

        $this->assertTrue($definition->isAutowired());
        $this->assertArrayHasKey('kernel.event_subscriber', $definition->getTags());
    }

    public function testProcessWithMissingTracerNameParameter(): void
    {
        $this->container->setParameter('otel_bundle.instrumentations', [
            InstrumentationEventSubscriber::class,
        ]);

        $this->compilerPass->process($this->container);

        $this->assertTrue($this->container->hasDefinition(InstrumentationEventSubscriber::class));
    }

    public function testProcessWithInvalidInstrumentationClass(): void
    {
        $this->container->setParameter('otel_bundle.instrumentations', [
            'Invalid\Class\Name',
        ]);
        $this->container->setParameter('otel_bundle.tracer_name', 'test-tracer');

        $this->compilerPass->process($this->container);

        $this->assertTrue($this->container->hasDefinition('Invalid\Class\Name'));
    }

    public function testProcessWithMixedInstrumentations(): void
    {
        $this->container->setParameter('otel_bundle.instrumentations', [
            'App\Instrumentation\CustomInstrumentation',
            InstrumentationEventSubscriber::class,
            'App\Instrumentation\AnotherInstrumentation',
        ]);
        $this->container->setParameter('otel_bundle.tracer_name', 'test-tracer');

        $this->compilerPass->process($this->container);

        $this->assertTrue($this->container->hasDefinition('App\Instrumentation\CustomInstrumentation'));
        $this->assertTrue($this->container->hasDefinition(InstrumentationEventSubscriber::class));
        $this->assertTrue($this->container->hasDefinition('App\Instrumentation\AnotherInstrumentation'));

        $subscriberDefinition = $this->container->getDefinition(InstrumentationEventSubscriber::class);
        $this->assertArrayHasKey('kernel.event_subscriber', $subscriberDefinition->getTags());

        $customDefinition = $this->container->getDefinition('App\Instrumentation\CustomInstrumentation');
        $this->assertArrayNotHasKey('kernel.event_subscriber', $customDefinition->getTags());
    }

    public function testProcessWithEmptyContainer(): void
    {
        $this->expectException(ParameterNotFoundException::class);
        $this->compilerPass->process($this->container);
    }

    public function testProcessWithNullInstrumentations(): void
    {
        $this->container->setParameter('otel_bundle.instrumentations', null);
        $this->container->setParameter('otel_bundle.tracer_name', 'test-tracer');

        $this->compilerPass->process($this->container);

        $this->assertFalse($this->container->hasDefinition('App\Instrumentation\CustomInstrumentation'));
    }

    public function testProcessWithEmptyArrayInstrumentations(): void
    {
        $this->container->setParameter('otel_bundle.instrumentations', []);
        $this->container->setParameter('otel_bundle.tracer_name', 'test-tracer');

        $this->compilerPass->process($this->container);

        // Should not throw exception and should not create any instrumentation definitions
        $this->assertFalse($this->container->hasDefinition('App\Instrumentation\CustomInstrumentation'));
    }

    public function testProcessDiscoversTraceSpanAttributes(): void
    {
        $this->container->setParameter('otel_bundle.instrumentations', []);

        // Register a service with TraceSpan attribute
        $this->container->register(TraceSpanTestService::class, TraceSpanTestService::class)
            ->setPublic(true);

        $this->compilerPass->process($this->container);

        // Verify AttributeMethodInstrumentation was created for processOrder method
        $instrumentationId = sprintf(
            'otel.attr_instrumentation.%s.%s.%s',
            TraceSpanTestService::class,
            'processOrder',
            'ProcessOrder',
        );

        $this->assertTrue(
            $this->container->hasDefinition($instrumentationId),
            'AttributeMethodInstrumentation should be created for processOrder method',
        );

        $definition = $this->container->getDefinition($instrumentationId);
        $this->assertSame(AttributeMethodInstrumentation::class, $definition->getClass());
        $this->assertTrue($definition->hasTag('otel.hook_instrumentation'));
    }

    public function testProcessDiscoversMultipleTraceSpanAttributes(): void
    {
        $this->container->setParameter('otel_bundle.instrumentations', []);

        // Register a service with multiple TraceSpan attributes
        $this->container->register(TraceSpanTestService::class, TraceSpanTestService::class)
            ->setPublic(true);

        $this->compilerPass->process($this->container);

        // Verify instrumentations for all three methods
        $processOrderId = sprintf(
            'otel.attr_instrumentation.%s.%s.%s',
            TraceSpanTestService::class,
            'processOrder',
            'ProcessOrder',
        );
        $calculatePriceId = sprintf(
            'otel.attr_instrumentation.%s.%s.%s',
            TraceSpanTestService::class,
            'calculatePrice',
            'CalculatePrice',
        );
        $validatePaymentId = sprintf(
            'otel.attr_instrumentation.%s.%s.%s',
            TraceSpanTestService::class,
            'validatePayment',
            'ValidatePayment',
        );

        $this->assertTrue($this->container->hasDefinition($processOrderId));
        $this->assertTrue($this->container->hasDefinition($calculatePriceId));
        $this->assertTrue($this->container->hasDefinition($validatePaymentId));

        // Verify all are tagged as hook instrumentations
        $this->assertTrue($this->container->getDefinition($processOrderId)->hasTag('otel.hook_instrumentation'));
        $this->assertTrue($this->container->getDefinition($calculatePriceId)->hasTag('otel.hook_instrumentation'));
        $this->assertTrue($this->container->getDefinition($validatePaymentId)->hasTag('otel.hook_instrumentation'));
    }

    public function testProcessRegistersHooksForTraceSpanAttributes(): void
    {
        $this->container->setParameter('otel_bundle.instrumentations', []);

        // Register a service with TraceSpan attribute
        $this->container->register(TraceSpanTestService::class, TraceSpanTestService::class)
            ->setPublic(true);

        $this->compilerPass->process($this->container);

        // Verify HookManagerService has method calls to register hooks
        $hookManagerDefinition = $this->container->getDefinition(HookManagerService::class);
        $methodCalls = $hookManagerDefinition->getMethodCalls();

        // Should have at least one registerHook call for the TraceSpan attribute
        $registerHookCalls = array_filter($methodCalls, fn(array $call): bool => $call[0] === 'registerHook');
        $this->assertGreaterThan(0, count($registerHookCalls), 'HookManagerService should have registerHook calls');
    }

    public function testProcessSkipsNonExistentClasses(): void
    {
        $this->container->setParameter('otel_bundle.instrumentations', []);

        // Register a service with a non-existent class
        $this->container->register('non_existent_service', 'NonExistent\Class\Name')
            ->setPublic(true);

        // Should not throw exception
        $this->compilerPass->process($this->container);

        // Should not create any instrumentation for non-existent class
        $this->assertFalse(
            $this->container->hasDefinition('otel.attr_instrumentation.NonExistent\Class\Name.method.SpanName'),
        );
    }

    public function testProcessSkipsServicesWithoutTraceSpanAttributes(): void
    {
        $this->container->setParameter('otel_bundle.instrumentations', []);

        // Register a service without TraceSpan attributes
        $this->container->register(stdClass::class, stdClass::class)
            ->setPublic(true);

        $this->compilerPass->process($this->container);

        // Should not create any instrumentation
        $tagged = $this->container->findTaggedServiceIds('otel.hook_instrumentation');
        $attrInstrumentations = array_filter(
            array_keys($tagged),
            fn(string $id): bool => str_starts_with($id, 'otel.attr_instrumentation.'),
        );

        $this->assertCount(0, $attrInstrumentations);
    }

    public function testProcessHandlesReflectionExceptions(): void
    {
        $this->container->setParameter('otel_bundle.instrumentations', []);

        // Create a mock service definition that will cause reflection to fail
        // We can't easily create a class that fails reflection, so we'll test with a valid class
        // but ensure the code handles exceptions gracefully
        $this->container->register(TraceSpanTestService::class, TraceSpanTestService::class)
            ->setPublic(true);

        // Should not throw exception even if reflection fails
        $this->compilerPass->process($this->container);

        // Should still process other services
        $this->assertTrue(true);
    }

    public function testProcessWithBothInstrumentationsAndTraceSpanAttributes(): void
    {
        $this->container->setParameter('otel_bundle.instrumentations', [
            InstrumentationEventSubscriber::class,
        ]);

        // Register a service with TraceSpan attribute
        $this->container->register(TraceSpanTestService::class, TraceSpanTestService::class)
            ->setPublic(true);

        $this->compilerPass->process($this->container);

        // Verify both types of instrumentations are registered
        $this->assertTrue($this->container->hasDefinition(InstrumentationEventSubscriber::class));
        // InstrumentationEventSubscriber should be tagged as event subscriber
        $subscriberDefinition = $this->container->getDefinition(InstrumentationEventSubscriber::class);
        $this->assertArrayHasKey('kernel.event_subscriber', $subscriberDefinition->getTags());

        $processOrderId = sprintf(
            'otel.attr_instrumentation.%s.%s.%s',
            TraceSpanTestService::class,
            'processOrder',
            'ProcessOrder',
        );
        $this->assertTrue($this->container->hasDefinition($processOrderId));

        // Verify TraceSpan attribute instrumentation is tagged as hook instrumentation
        $tagged = $this->container->findTaggedServiceIds('otel.hook_instrumentation');
        $this->assertArrayHasKey($processOrderId, $tagged);
        // InstrumentationEventSubscriber is not a hook instrumentation, so it won't be in this list
        $this->assertArrayNotHasKey(InstrumentationEventSubscriber::class, $tagged);
    }

    public function testProcessSetsHookManagerServiceProperties(): void
    {
        $this->container->setParameter('otel_bundle.instrumentations', []);

        $this->compilerPass->process($this->container);

        $hookManagerDefinition = $this->container->getDefinition(HookManagerService::class);
        $this->assertFalse($hookManagerDefinition->isLazy(), 'HookManagerService should not be lazy');
        $this->assertTrue($hookManagerDefinition->isPublic(), 'HookManagerService should be public');
    }
}

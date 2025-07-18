<?php

declare(strict_types=1);

namespace Tests\Unit\DependencyInjection;

use Macpaw\SymfonyOtelBundle\DependencyInjection\SymfonyOtelCompilerPass;
use Macpaw\SymfonyOtelBundle\Span\InstrumentationEventSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class SymfonyOtelCompilerPassTest extends TestCase
{
    private ContainerBuilder $container;
    private SymfonyOtelCompilerPass $compilerPass;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->compilerPass = new SymfonyOtelCompilerPass();
    }

    public function testProcessWithEmptyInstrumentations(): void
    {
        $this->container->setParameter('otel_bundle.instrumentations', []);

        $this->compilerPass->process($this->container);

        $this->assertTrue(true);
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
        $this->expectException(\Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException::class);
        $this->compilerPass->process($this->container);
    }

    public function testProcessWithNullInstrumentations(): void
    {
        $this->container->setParameter('otel_bundle.instrumentations', null);
        $this->container->setParameter('otel_bundle.tracer_name', 'test-tracer');

        $this->compilerPass->process($this->container);

        $this->assertTrue(true);
    }
}

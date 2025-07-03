<?php

declare(strict_types=1);

namespace Tests\Unit\DependencyInjection;

use Macpaw\SymfonyOtelBundle\DependencyInjection\SymfonyOtelCompilerPass;
use Macpaw\SymfonyOtelBundle\Span\ExecutionTimeSpanTracer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class SymfonyOtelCompilerPassTest extends TestCase
{
    private SymfonyOtelCompilerPass $compilerPass;
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->compilerPass = new SymfonyOtelCompilerPass();
        $this->container = new ContainerBuilder();
    }

    public function testProcessWithEmptySpanTracers(): void
    {
        $this->container->setParameter('otel_bundle.span_tracers', []);

        $this->compilerPass->process($this->container);

        // Should not create any new definitions
        $this->assertCount(1, $this->container->getDefinitions());
    }

    public function testProcessWithExecutionTimeSpanTracer(): void
    {
        $this->container->setParameter('otel_bundle.span_tracers', [
            [
                'class' => ExecutionTimeSpanTracer::class,
                'tag' => 'kernel.event_subscriber',
            ],
        ]);
        $this->container->setParameter('otel_bundle.tracer_name', 'test-tracer');

        $this->compilerPass->process($this->container);

        $this->assertTrue($this->container->hasDefinition(ExecutionTimeSpanTracer::class));

        $definition = $this->container->getDefinition(ExecutionTimeSpanTracer::class);
        $this->assertTrue($definition->isAutowired());
        $this->assertTrue($definition->hasTag('kernel.event_subscriber'));

        $arguments = $definition->getArguments();
        $this->assertArrayHasKey('$tracerName', $arguments);
        $this->assertEquals('test-tracer', $arguments['$tracerName']);
    }

    public function testProcessWithCustomSpanTracer(): void
    {
        $customTracerClass = 'App\\Span\\CustomTracer';
        $this->container->setParameter('otel_bundle.span_tracers', [
            [
                'class' => $customTracerClass,
                'tag' => 'custom.event_subscriber',
            ],
        ]);

        $this->compilerPass->process($this->container);

        $this->assertTrue($this->container->hasDefinition($customTracerClass));

        $definition = $this->container->getDefinition($customTracerClass);
        $this->assertTrue($definition->isAutowired());
        $this->assertTrue($definition->hasTag('custom.event_subscriber'));

        // Should not have tracer name argument for non-ExecutionTimeSpanTracer
        $arguments = $definition->getArguments();
        $this->assertArrayNotHasKey('$tracerName', $arguments);
    }

    public function testProcessWithExistingDefinition(): void
    {
        $existingDefinition = new Definition(ExecutionTimeSpanTracer::class);
        $existingDefinition->setAutowired(false);
        $this->container->setDefinition(ExecutionTimeSpanTracer::class, $existingDefinition);

        $this->container->setParameter('otel_bundle.span_tracers', [
            [
                'class' => ExecutionTimeSpanTracer::class,
                'tag' => 'kernel.event_subscriber',
            ],
        ]);
        $this->container->setParameter('otel_bundle.tracer_name', 'test-tracer');

        $this->compilerPass->process($this->container);

        $definition = $this->container->getDefinition(ExecutionTimeSpanTracer::class);
        $this->assertSame($existingDefinition, $definition);
        $this->assertTrue($definition->isAutowired()); // Should be updated
        $this->assertTrue($definition->hasTag('kernel.event_subscriber'));
    }

    public function testProcessWithMultipleSpanTracers(): void
    {
        $this->container->setParameter('otel_bundle.span_tracers', [
            [
                'class' => ExecutionTimeSpanTracer::class,
                'tag' => 'kernel.event_subscriber',
            ],
            [
                'class' => 'App\\Span\\CustomTracer',
                'tag' => 'doctrine.event_subscriber',
            ],
            [
                'class' => 'App\\Span\\AnotherTracer',
                'tag' => 'custom.tag',
            ],
        ]);
        $this->container->setParameter('otel_bundle.tracer_name', 'test-tracer');

        $this->compilerPass->process($this->container);

        // Check ExecutionTimeSpanTracer
        $this->assertTrue($this->container->hasDefinition(ExecutionTimeSpanTracer::class));
        $executionTimeDef = $this->container->getDefinition(ExecutionTimeSpanTracer::class);
        $this->assertTrue($executionTimeDef->hasTag('kernel.event_subscriber'));
        $this->assertArrayHasKey('$tracerName', $executionTimeDef->getArguments());

        // Check CustomTracer
        $this->assertTrue($this->container->hasDefinition('App\\Span\\CustomTracer'));
        $customDef = $this->container->getDefinition('App\\Span\\CustomTracer');
        $this->assertTrue($customDef->hasTag('doctrine.event_subscriber'));
        $this->assertArrayNotHasKey('$tracerName', $customDef->getArguments());

        // Check AnotherTracer
        $this->assertTrue($this->container->hasDefinition('App\\Span\\AnotherTracer'));
        $anotherDef = $this->container->getDefinition('App\\Span\\AnotherTracer');
        $this->assertTrue($anotherDef->hasTag('custom.tag'));
    }

    public function testProcessWithMissingTracerNameParameter(): void
    {
        $this->container->setParameter('otel_bundle.span_tracers', [
            [
                'class' => ExecutionTimeSpanTracer::class,
                'tag' => 'kernel.event_subscriber',
            ],
        ]);
        // Not setting otel_bundle.tracer_name parameter

        $this->expectException(\Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException::class);

        $this->compilerPass->process($this->container);
    }

    public function testProcessWithDifferentTags(): void
    {
        $this->container->setParameter('otel_bundle.span_tracers', [
            [
                'class' => 'App\\Span\\TracerOne',
                'tag' => 'kernel.event_subscriber',
            ],
            [
                'class' => 'App\\Span\\TracerTwo',
                'tag' => 'doctrine.event_subscriber',
            ],
            [
                'class' => 'App\\Span\\TracerThree',
                'tag' => 'custom.subscriber',
            ],
        ]);

        $this->compilerPass->process($this->container);

        $tracerOne = $this->container->getDefinition('App\\Span\\TracerOne');
        $this->assertTrue($tracerOne->hasTag('kernel.event_subscriber'));
        $this->assertFalse($tracerOne->hasTag('doctrine.event_subscriber'));

        $tracerTwo = $this->container->getDefinition('App\\Span\\TracerTwo');
        $this->assertTrue($tracerTwo->hasTag('doctrine.event_subscriber'));
        $this->assertFalse($tracerTwo->hasTag('kernel.event_subscriber'));

        $tracerThree = $this->container->getDefinition('App\\Span\\TracerThree');
        $this->assertTrue($tracerThree->hasTag('custom.subscriber'));
    }

    public function testProcessWithSpecialCharacterClassNames(): void
    {
        $complexClassName = 'Very\\Complex\\Namespace\\With_Underscores\\And123Numbers\\TracerClass';
        $this->container->setParameter('otel_bundle.span_tracers', [
            [
                'class' => $complexClassName,
                'tag' => 'complex.tag.with.dots_and_underscores',
            ],
        ]);

        $this->compilerPass->process($this->container);

        $this->assertTrue($this->container->hasDefinition($complexClassName));
        $definition = $this->container->getDefinition($complexClassName);
        $this->assertTrue($definition->hasTag('complex.tag.with.dots_and_underscores'));
    }
} 

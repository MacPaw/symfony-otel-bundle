<?php

declare(strict_types=1);

namespace Tests\Unit\DependencyInjection;

use Macpaw\SymfonyOtelBundle\DependencyInjection\Configuration;
use Macpaw\SymfonyOtelBundle\DependencyInjection\SymfonyOtelExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class SymfonyOtelExtensionTest extends TestCase
{
    private SymfonyOtelExtension $extension;
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->extension = new SymfonyOtelExtension();
        $this->container = new ContainerBuilder();
    }

    public function testLoad(): void
    {
        $configs = [
            [
                'tracer_name' => 'test-tracer',
                'service_name' => 'test-service',
                'span_tracers' => [
                    [
                        'class' => 'App\\Span\\CustomTracer',
                        'tag' => 'kernel.event_subscriber',
                    ],
                ],
            ],
        ];

        $this->extension->load($configs, $this->container);

        $this->assertTrue($this->container->hasParameter('otel_bundle.tracer_name'));
        $this->assertTrue($this->container->hasParameter('otel_bundle.span_tracers'));

        $this->assertEquals('test-tracer', $this->container->getParameter('otel_bundle.tracer_name'));
        $this->assertEquals([
            [
                'class' => 'App\\Span\\CustomTracer',
                'tag' => 'kernel.event_subscriber',
            ],
        ], $this->container->getParameter('otel_bundle.span_tracers'));
    }

    public function testLoadWithDefaultValues(): void
    {
        $configs = [[]];

        $this->extension->load($configs, $this->container);

        $this->assertEquals('test-template', $this->container->getParameter('otel_bundle.tracer_name'));
        $this->assertEquals([], $this->container->getParameter('otel_bundle.span_tracers'));
    }

    public function testLoadWithEmptyConfigs(): void
    {
        $configs = [];

        $this->extension->load($configs, $this->container);

        $this->assertEquals('test-template', $this->container->getParameter('otel_bundle.tracer_name'));
        $this->assertEquals([], $this->container->getParameter('otel_bundle.span_tracers'));
    }

    public function testLoadWithMultipleConfigs(): void
    {
        $configs = [
            [
                'tracer_name' => 'first-tracer',
                'span_tracers' => [
                    [
                        'class' => 'App\\Span\\FirstTracer',
                        'tag' => 'kernel.event_subscriber',
                    ],
                ],
            ],
            [
                'tracer_name' => 'second-tracer',
                'span_tracers' => [
                    [
                        'class' => 'App\\Span\\SecondTracer',
                        'tag' => 'doctrine.event_subscriber',
                    ],
                ],
            ],
        ];

        $this->extension->load($configs, $this->container);

        // Second config should override first
        $this->assertEquals('second-tracer', $this->container->getParameter('otel_bundle.tracer_name'));
        $this->assertEquals([
            [
                'class' => 'App\Span\FirstTracer',
                'tag' => 'kernel.event_subscriber',
            ],
            [
                'class' => 'App\\Span\\SecondTracer',
                'tag' => 'doctrine.event_subscriber',
            ],
        ], $this->container->getParameter('otel_bundle.span_tracers'));
    }

    public function testGetConfiguration(): void
    {
        $config = [];
        $configuration = $this->extension->getConfiguration($config, $this->container);

        $this->assertInstanceOf(Configuration::class, $configuration);
    }

    public function testGetConfigurationWithNonEmptyConfig(): void
    {
        $config = ['tracer_name' => 'test'];
        $configuration = $this->extension->getConfiguration($config, $this->container);

        $this->assertInstanceOf(Configuration::class, $configuration);
    }

    public function testGetAlias(): void
    {
        $alias = $this->extension->getAlias();

        $this->assertEquals('otel_bundle', $alias);
        $this->assertEquals(SymfonyOtelExtension::NAME, $alias);
    }

    public function testNameConstant(): void
    {
        $this->assertEquals('otel_bundle', SymfonyOtelExtension::NAME);
    }

    public function testLoadWithSpecialCharactersInTracerName(): void
    {
        $configs = [
            [
                'tracer_name' => 'test-tracer_with-special.chars123',
            ],
        ];

        $this->extension->load($configs, $this->container);

        $this->assertEquals(
            'test-tracer_with-special.chars123',
            $this->container->getParameter('otel_bundle.tracer_name'),
        );
    }

    public function testLoadWithComplexSpanTracersConfiguration(): void
    {
        $configs = [
            [
                'span_tracers' => [
                    [
                        'class' => 'Namespace\\With\\Backslashes\\TracerClass',
                        'tag' => 'custom.tag.with.dots',
                    ],
                    [
                        'class' => 'Another\\Tracer\\Class',
                        'tag' => 'another_tag_with_underscores',
                    ],
                ],
            ],
        ];

        $this->extension->load($configs, $this->container);

        $spanTracers = $this->container->getParameter('otel_bundle.span_tracers');
        $this->assertCount(2, $spanTracers);
        $this->assertEquals('Namespace\\With\\Backslashes\\TracerClass', $spanTracers[0]['class']);
        $this->assertEquals('custom.tag.with.dots', $spanTracers[0]['tag']);
        $this->assertEquals('Another\\Tracer\\Class', $spanTracers[1]['class']);
        $this->assertEquals('another_tag_with_underscores', $spanTracers[1]['tag']);
    }
}

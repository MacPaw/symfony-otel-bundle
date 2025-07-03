<?php

declare(strict_types=1);

namespace Tests\Unit\DependencyInjection;

use Macpaw\SymfonyOtelBundle\DependencyInjection\Configuration;
use Macpaw\SymfonyOtelBundle\DependencyInjection\SymfonyOtelExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;

class ConfigurationTest extends TestCase
{
    private Configuration $configuration;

    protected function setUp(): void
    {
        $this->configuration = new Configuration();
    }

    public function testDefaultConfiguration(): void
    {
        $processor = new Processor();
        $treeBuilder = $this->configuration->getConfigTreeBuilder();

        $config = $processor->processConfiguration($this->configuration, []);

        $this->assertEquals('test-template', $config['tracer_name']);
        $this->assertEquals('test-service', $config['service_name']);
        $this->assertEquals([], $config['span_tracers']);
    }

    public function testCustomConfiguration(): void
    {
        $processor = new Processor();
        $inputConfig = [
            SymfonyOtelExtension::NAME => [
                'tracer_name' => 'custom-tracer',
                'service_name' => 'custom-service',
                'span_tracers' => [
                    [
                        'class' => 'App\\Span\\CustomTracer',
                        'tag' => 'kernel.event_subscriber',
                    ],
                ],
            ],
        ];

        $config = $processor->processConfiguration($this->configuration, $inputConfig);

        $this->assertEquals('custom-tracer', $config['tracer_name']);
        $this->assertEquals('custom-service', $config['service_name']);
        $this->assertCount(1, $config['span_tracers']);
        $this->assertEquals('App\\Span\\CustomTracer', $config['span_tracers'][0]['class']);
        $this->assertEquals('kernel.event_subscriber', $config['span_tracers'][0]['tag']);
    }

    public function testEmptyTracerNameThrowsException(): void
    {
        $this->expectException(\Symfony\Component\Config\Definition\Exception\InvalidConfigurationException::class);

        $processor = new Processor();
        $inputConfig = [
            SymfonyOtelExtension::NAME => [
                'tracer_name' => '',
            ],
        ];

        $processor->processConfiguration($this->configuration, $inputConfig);
    }

    public function testEmptyServiceNameThrowsException(): void
    {
        $this->expectException(\Symfony\Component\Config\Definition\Exception\InvalidConfigurationException::class);

        $processor = new Processor();
        $inputConfig = [
            SymfonyOtelExtension::NAME => [
                'service_name' => '',
            ],
        ];

        $processor->processConfiguration($this->configuration, $inputConfig);
    }

    public function testSpanTracerWithoutClassThrowsException(): void
    {
        $this->expectException(\Symfony\Component\Config\Definition\Exception\InvalidConfigurationException::class);

        $processor = new Processor();
        $inputConfig = [
            SymfonyOtelExtension::NAME => [
                'span_tracers' => [
                    [
                        'tag' => 'kernel.event_subscriber',
                    ],
                ],
            ],
        ];

        $processor->processConfiguration($this->configuration, $inputConfig);
    }

    public function testSpanTracerWithoutTagThrowsException(): void
    {
        $this->expectException(\Symfony\Component\Config\Definition\Exception\InvalidConfigurationException::class);

        $processor = new Processor();
        $inputConfig = [
            SymfonyOtelExtension::NAME => [
                'span_tracers' => [
                    [
                        'class' => 'App\\Span\\CustomTracer',
                    ],
                ],
            ],
        ];

        $processor->processConfiguration($this->configuration, $inputConfig);
    }

    public function testMultipleSpanTracers(): void
    {
        $processor = new Processor();
        $inputConfig = [
            SymfonyOtelExtension::NAME => [
                'span_tracers' => [
                    [
                        'class' => 'App\\Span\\TracerOne',
                        'tag' => 'kernel.event_subscriber',
                    ],
                    [
                        'class' => 'App\\Span\\TracerTwo',
                        'tag' => 'doctrine.event_subscriber',
                    ],
                ],
            ],
        ];

        $config = $processor->processConfiguration($this->configuration, $inputConfig);

        $this->assertCount(2, $config['span_tracers']);
        $this->assertEquals('App\\Span\\TracerOne', $config['span_tracers'][0]['class']);
        $this->assertEquals('App\\Span\\TracerTwo', $config['span_tracers'][1]['class']);
    }
} 

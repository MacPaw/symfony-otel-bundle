<?php

declare(strict_types=1);

namespace Tests\Unit\DependencyInjection;

use Macpaw\SymfonyOtelBundle\DependencyInjection\Configuration;
use Macpaw\SymfonyOtelBundle\DependencyInjection\SymfonyOtelExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
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

        $config = $processor->processConfiguration($this->configuration, []);

        $this->assertEquals('symfony-tracer', $config['tracer_name']);
        $this->assertEquals('symfony-app', $config['service_name']);
        $this->assertEquals([], $config['instrumentations']);
    }

    public function testCustomConfiguration(): void
    {
        $processor = new Processor();
        $inputConfig = [
            SymfonyOtelExtension::NAME => [
                'tracer_name' => 'custom-tracer',
                'service_name' => 'custom-service',
                'instrumentations' => [
                    'App\Instrumentation\CustomInstrumentation',
                    'App\Instrumentation\HookInstrumentation',
                ],
            ],
        ];

        /** @var array<int|string, mixed> $config */
        $config = $processor->processConfiguration($this->configuration, $inputConfig);
        /** @var array<int, string> $instrumentations */
        $instrumentations = $config['instrumentations'];

        $this->assertEquals('custom-tracer', $config['tracer_name']);
        $this->assertEquals('custom-service', $config['service_name']);
        $this->assertCount(2, $instrumentations);
        $this->assertEquals('App\Instrumentation\CustomInstrumentation', $instrumentations[0]);
        $this->assertEquals('App\Instrumentation\HookInstrumentation', $instrumentations[1]);
    }

    public function testEmptyTracerNameThrowsException(): void
    {
        $this->expectException(InvalidConfigurationException::class);

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
        $this->expectException(InvalidConfigurationException::class);

        $processor = new Processor();
        $inputConfig = [
            SymfonyOtelExtension::NAME => [
                'service_name' => '',
            ],
        ];

        $processor->processConfiguration($this->configuration, $inputConfig);
    }

    public function testEmptyInstrumentationThrowsException(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $processor = new Processor();
        $inputConfig = [
            SymfonyOtelExtension::NAME => [
                'instrumentations' => [
                    '',
                ],
            ],
        ];

        $processor->processConfiguration($this->configuration, $inputConfig);
    }

    public function testMultipleInstrumentations(): void
    {
        $processor = new Processor();
        $inputConfig = [
            SymfonyOtelExtension::NAME => [
                'instrumentations' => [
                    'App\Instrumentation\FirstInstrumentation',
                    'App\Instrumentation\SecondInstrumentation',
                    'App\Instrumentation\ThirdInstrumentation',
                ],
            ],
        ];

        /** @var array<int|string, mixed> $config */
        $config = $processor->processConfiguration($this->configuration, $inputConfig);
        /** @var array<int, string> $instrumentations */
        $instrumentations = $config['instrumentations'];

        $this->assertCount(3, $instrumentations);
        $this->assertEquals('App\Instrumentation\FirstInstrumentation', $instrumentations[0]);
        $this->assertEquals('App\Instrumentation\SecondInstrumentation', $instrumentations[1]);
        $this->assertEquals('App\Instrumentation\ThirdInstrumentation', $instrumentations[2]);
    }

    public function testPartialConfiguration(): void
    {
        $processor = new Processor();
        $inputConfig = [
            SymfonyOtelExtension::NAME => [
                'service_name' => 'partial-service',
            ],
        ];

        /** @var array<int|string, mixed> $config */
        $config = $processor->processConfiguration($this->configuration, $inputConfig);

        $this->assertEquals('partial-service', $config['service_name']);
        $this->assertEquals('symfony-tracer', $config['tracer_name']);
        $this->assertEquals([], $config['instrumentations']);
    }

    public function testConfigurationWithSpecialCharacters(): void
    {
        $processor = new Processor();
        $inputConfig = [
            SymfonyOtelExtension::NAME => [
                'tracer_name' => 'tracer-with-special-chars_123',
                'service_name' => 'service-with-special-chars_123',
                'instrumentations' => [
                    'Namespace\With\Backslashes\InstrumentationClass',
                ],
            ],
        ];

        /** @var array<int|string, mixed> $config */
        $config = $processor->processConfiguration($this->configuration, $inputConfig);

        $this->assertEquals('tracer-with-special-chars_123', $config['tracer_name']);
        $this->assertEquals('service-with-special-chars_123', $config['service_name']);
        $this->assertEquals(['Namespace\With\Backslashes\InstrumentationClass'], $config['instrumentations']);
    }
}

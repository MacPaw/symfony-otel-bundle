<?php

declare(strict_types=1);

namespace Tests\Unit\DependencyInjection;

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

    public function testLoadWithDefaultConfiguration(): void
    {
        $this->extension->load([], $this->container);

        $this->assertTrue($this->container->hasParameter('otel_bundle.tracer_name'));
        $this->assertTrue($this->container->hasParameter('otel_bundle.service_name'));
        $this->assertTrue($this->container->hasParameter('otel_bundle.instrumentations'));

        $this->assertEquals('symfony-tracer', $this->container->getParameter('otel_bundle.tracer_name'));
        $this->assertEquals('symfony-app', $this->container->getParameter('otel_bundle.service_name'));
        $this->assertEquals([], $this->container->getParameter('otel_bundle.instrumentations'));
    }

    public function testLoadWithCustomConfiguration(): void
    {
        /** @var array<string, mixed> $config */
        $config = [
            'tracer_name' => 'custom_tracer',
            'service_name' => 'custom_service',
            'instrumentations' => [
                'App\Instrumentation\CustomInstrumentation',
            ],
        ];

        /** @var array<int, array<string, mixed>> $configs */
        $configs = [$config];
        $this->extension->load($configs, $this->container);

        $this->assertEquals('custom_tracer', $this->container->getParameter('otel_bundle.tracer_name'));
        $this->assertEquals('custom_service', $this->container->getParameter('otel_bundle.service_name'));
        $this->assertEquals(
            ['App\Instrumentation\CustomInstrumentation'],
            $this->container->getParameter('otel_bundle.instrumentations')
        );
    }

    public function testLoadWithMultipleConfigurations(): void
    {
        /** @var array<string, mixed> $config1 */
        $config1 = [
            'tracer_name' => 'first_tracer',
            'service_name' => 'first_service',
        ];

        /** @var array<string, mixed> $config2 */
        $config2 = [
            'tracer_name' => 'second_tracer',
            'service_name' => 'second_service',
            'instrumentations' => [
                'App\Instrumentation\SecondInstrumentation',
            ],
        ];

        /** @var array<int, array<string, mixed>> $configs */
        $configs = [$config1, $config2];
        $this->extension->load($configs, $this->container);

        $this->assertEquals('second_tracer', $this->container->getParameter('otel_bundle.tracer_name'));
        $this->assertEquals('second_service', $this->container->getParameter('otel_bundle.service_name'));
        $this->assertEquals(
            ['App\Instrumentation\SecondInstrumentation'],
            $this->container->getParameter('otel_bundle.instrumentations')
        );
    }

    public function testGetAlias(): void
    {
        $this->assertEquals('otel_bundle', $this->extension->getAlias());
    }

    public function testLoadWithEmptyConfiguration(): void
    {
        $this->extension->load([], $this->container);

        $this->assertTrue($this->container->hasParameter('otel_bundle.tracer_name'));
        $this->assertTrue($this->container->hasParameter('otel_bundle.service_name'));
        $this->assertTrue($this->container->hasParameter('otel_bundle.instrumentations'));
    }

    public function testLoadWithPartialConfiguration(): void
    {
        /** @var array<string, mixed> $config */
        $config = [
            'tracer_name' => 'partial_tracer',
        ];

        /** @var array<int, array<string, mixed>> $configs */
        $configs = [$config];
        $this->extension->load($configs, $this->container);

        $this->assertEquals('partial_tracer', $this->container->getParameter('otel_bundle.tracer_name'));
        $this->assertEquals('symfony-app', $this->container->getParameter('otel_bundle.service_name'));
        $this->assertEquals([], $this->container->getParameter('otel_bundle.instrumentations'));
    }

    public function testLoadWithComplexInstrumentations(): void
    {
        /** @var array<string, mixed> $config */
        $config = [
            'instrumentations' => [
                'App\Instrumentation\FirstInstrumentation',
                'App\Instrumentation\SecondInstrumentation',
                'App\Instrumentation\ThirdInstrumentation',
            ],
        ];

        /** @var array<int, array<string, mixed>> $configs */
        $configs = [$config];
        $this->extension->load($configs, $this->container);

        $expectedInstrumentations = [
            'App\Instrumentation\FirstInstrumentation',
            'App\Instrumentation\SecondInstrumentation',
            'App\Instrumentation\ThirdInstrumentation',
        ];

        $this->assertEquals($expectedInstrumentations, $this->container->getParameter('otel_bundle.instrumentations'));
    }
}

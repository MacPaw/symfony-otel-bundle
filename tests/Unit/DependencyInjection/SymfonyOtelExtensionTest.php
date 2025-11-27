<?php

declare(strict_types=1);

namespace Tests\Unit\DependencyInjection;

use Macpaw\SymfonyOtelBundle\DependencyInjection\SymfonyOtelExtension;
use Macpaw\SymfonyOtelBundle\Instrumentation\Utils\RouterUtils;
use Macpaw\SymfonyOtelBundle\Listeners\RequestCountersEventSubscriber;
use Macpaw\SymfonyOtelBundle\Logging\MonologTraceContextProcessor;
use Macpaw\SymfonyOtelBundle\Registry\InstrumentationRegistry;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
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
        $this->assertTrue($this->container->hasParameter('otel_bundle.force_flush_on_terminate'));
        $this->assertTrue($this->container->hasParameter('otel_bundle.force_flush_timeout_ms'));
        $this->assertTrue($this->container->hasParameter('otel_bundle.header_mappings'));

        $this->assertEquals('symfony-tracer', $this->container->getParameter('otel_bundle.tracer_name'));
        $this->assertEquals('symfony-app', $this->container->getParameter('otel_bundle.service_name'));
        $this->assertEquals([], $this->container->getParameter('otel_bundle.instrumentations'));
        $this->assertFalse($this->container->getParameter('otel_bundle.force_flush_on_terminate'));
        $this->assertEquals(100, $this->container->getParameter('otel_bundle.force_flush_timeout_ms'));
        $this->assertEquals(['http.request_id' => 'X-Request-Id'],
            $this->container->getParameter('otel_bundle.header_mappings'));
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
            'force_flush_on_terminate' => true,
            'force_flush_timeout_ms' => 200,
            'header_mappings' => [
                'user.id' => 'X-User-Id',
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
        $this->assertTrue($this->container->getParameter('otel_bundle.force_flush_on_terminate'));
        $this->assertEquals(200, $this->container->getParameter('otel_bundle.force_flush_timeout_ms'));
        $this->assertEquals(['user.id' => 'X-User-Id'], $this->container->getParameter('otel_bundle.header_mappings'));
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

    public function testLoadWithLoggingConfiguration(): void
    {
        /** @var array<string, mixed> $config */
        $config = [
            'logging' => [
                'enable_trace_processor' => false,
                'log_keys' => [
                    'trace_id' => 'custom_trace_id',
                    'span_id' => 'custom_span_id',
                    'trace_flags' => 'custom_trace_flags',
                ],
            ],
        ];

        /** @var array<int, array<string, mixed>> $configs */
        $configs = [$config];
        $this->extension->load($configs, $this->container);

        $this->assertFalse($this->container->getParameter('otel_bundle.logging.enable_trace_processor'));
        $logKeys = $this->container->getParameter('otel_bundle.logging.log_keys');
        $this->assertEquals('custom_trace_id', $logKeys['trace_id']);
        $this->assertEquals('custom_span_id', $logKeys['span_id']);
        $this->assertEquals('custom_trace_flags', $logKeys['trace_flags']);
    }

    public function testLoadWithMetricsConfiguration(): void
    {
        /** @var array<string, mixed> $config */
        $config = [
            'metrics' => [
                'request_counters' => [
                    'enabled' => true,
                    'backend' => 'event',
                ],
            ],
        ];

        /** @var array<int, array<string, mixed>> $configs */
        $configs = [$config];
        $this->extension->load($configs, $this->container);

        $this->assertTrue($this->container->getParameter('otel_bundle.metrics.request_counters.enabled'));
        $this->assertEquals('event', $this->container->getParameter('otel_bundle.metrics.request_counters.backend'));
    }

    public function testLoadRegistersMonologProcessorWhenEnabled(): void
    {
        /** @var array<string, mixed> $config */
        $config = [
            'logging' => [
                'enable_trace_processor' => true,
            ],
        ];

        /** @var array<int, array<string, mixed>> $configs */
        $configs = [$config];
        $this->extension->load($configs, $this->container);

        $this->assertTrue(
            $this->container->hasDefinition(MonologTraceContextProcessor::class),
        );
    }

    public function testLoadDoesNotRegisterMonologProcessorWhenDisabled(): void
    {
        /** @var array<string, mixed> $config */
        $config = [
            'logging' => [
                'enable_trace_processor' => false,
            ],
        ];

        /** @var array<int, array<string, mixed>> $configs */
        $configs = [$config];
        $this->extension->load($configs, $this->container);

        // The definition might exist but should not be registered as a processor
        // Actually, looking at the code, it only registers if enabled is true
        // So if disabled, the definition should not exist
        $this->assertFalse(
            $this->container->hasDefinition(MonologTraceContextProcessor::class),
        );
    }

    public function testLoadRegistersRequestCountersWhenEnabled(): void
    {
        $this->container->register(MeterProviderInterface::class);
        $this->container->register(RouterUtils::class);
        $this->container->register(InstrumentationRegistry::class);

        /** @var array<string, mixed> $config */
        $config = [
            'metrics' => [
                'request_counters' => [
                    'enabled' => true,
                    'backend' => 'otel',
                ],
            ],
        ];

        /** @var array<int, array<string, mixed>> $configs */
        $configs = [$config];
        $this->extension->load($configs, $this->container);

        $this->assertTrue(
            $this->container->hasDefinition(RequestCountersEventSubscriber::class),
        );
    }

    public function testLoadDoesNotRegisterRequestCountersWhenDisabled(): void
    {
        /** @var array<string, mixed> $config */
        $config = [
            'metrics' => [
                'request_counters' => [
                    'enabled' => false,
                ],
            ],
        ];

        /** @var array<int, array<string, mixed>> $configs */
        $configs = [$config];
        $this->extension->load($configs, $this->container);

        // RequestCountersEventSubscriber should not be registered when disabled
        $this->assertFalse(
            $this->container->hasDefinition(RequestCountersEventSubscriber::class),
        );
    }
}

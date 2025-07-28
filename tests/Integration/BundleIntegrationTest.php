<?php

declare(strict_types=1);

namespace Tests\Integration;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\RequestStack;
use Macpaw\SymfonyOtelBundle\Registry\InstrumentationRegistry;
use Macpaw\SymfonyOtelBundle\Service\HookManagerService;
use Macpaw\SymfonyOtelBundle\Service\TraceService;
use Macpaw\SymfonyOtelBundle\SymfonyOtelBundle;
use OpenTelemetry\API\Trace\TracerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Config\FileLocator;

class BundleIntegrationTest extends TestCase
{
    private ContainerBuilder $container;
    private YamlFileLoader $loader;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->loader = new YamlFileLoader($this->container, new FileLocator(__DIR__ . '/../../Resources/config'));

        $this->container->register('http_client', HttpClientInterface::class)
            ->setClass(HttpClient::class);
        $this->container->register('request_stack', RequestStack::class);
    }

    public function testBundleCanBeLoaded(): void
    {
        $bundle = new SymfonyOtelBundle();
        $bundle->build($this->container);

        $this->assertInstanceOf(SymfonyOtelBundle::class, $bundle);
        $this->assertEquals('otel_bundle', $bundle->getContainerExtension()->getAlias());
    }

    public function testTraceServiceIsAvailable(): void
    {
        $this->container->setParameter('otel_bundle.service_name', 'test-service');
        $this->container->setParameter('otel_bundle.tracer_name', 'test-tracer');

        $this->loader->load('services.yml');
        $this->container->compile();

        $traceService = $this->container->get(TraceService::class);

        $this->assertInstanceOf(TraceService::class, $traceService);
        $this->assertInstanceOf(TracerInterface::class, $traceService->getTracer('test'));
    }

    public function testBundleConfiguration(): void
    {
        $this->container->setParameter('otel_bundle.service_name', 'test-service');
        $this->container->setParameter('otel_bundle.tracer_name', 'test-tracer');

        $this->loader->load('services.yml');
        $this->container->compile();

        $this->assertTrue($this->container->has(HookManagerService::class));
        $this->assertTrue($this->container->has(InstrumentationRegistry::class));
    }
}

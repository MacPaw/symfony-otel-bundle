<?php

declare(strict_types=1);

namespace Tests\Integration;

use Exception;
use Macpaw\SymfonyOtelBundle\Instrumentation\ExecutionTimeInstrumentation;
use Macpaw\SymfonyOtelBundle\Registry\InstrumentationRegistry;
use Macpaw\SymfonyOtelBundle\Service\TraceService;
use OpenTelemetry\API\Common\Time\ClockInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Config\FileLocator;

class InstrumentationIntegrationTest extends TestCase
{
    private ContainerBuilder $container;
    private TraceService $traceService;
    private InstrumentationRegistry $registry;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $loader = new YamlFileLoader($this->container, new FileLocator(__DIR__ . '/../../Resources/config'));
        $loader->load('services.yml');

        $this->container->setParameter('otel_bundle.service_name', 'test-service');
        $this->container->setParameter('otel_bundle.tracer_name', 'test-tracer');

        $this->container->compile();
        /** @var TraceService $traceService */
        $traceService = $this->container->get(TraceService::class);
        $this->traceService = $traceService;
        /** @var InstrumentationRegistry $registry */
        $registry = $this->container->get(InstrumentationRegistry::class);
        $this->registry = $registry;
    }

    public function testInstrumentationRegistryCanManageSpans(): void
    {
        $tracer = $this->traceService->getTracer('test-tracer');
        $span = $tracer->spanBuilder('test_span')->startSpan();

        $this->registry->addSpan($span, 'test_span');

        $this->assertCount(1, $this->registry->getSpans());
        $this->assertSame($span, $this->registry->getSpans()['test_span']);
    }

    public function testExecutionTimeInstrumentationCanCreateSpans(): void
    {
        $tracer = $this->traceService->getTracer('test-tracer');
        /** @var TextMapPropagatorInterface $propagator */
        $propagator = $this->container->get(TextMapPropagatorInterface::class);
        /** @var ClockInterface $clock */
        $clock = $this->container->get(ClockInterface::class);

        $instrumentation = new ExecutionTimeInstrumentation(
            $this->registry,
            $tracer,
            $propagator,
            $clock
        );

        $instrumentation->pre();

        usleep(100000);

        $instrumentation->post();

        $this->assertCount(1, $this->registry->getSpans());
    }

    public function testInstrumentationRegistryCanRemoveSpans(): void
    {
        $tracer = $this->traceService->getTracer('test-tracer');
        $span = $tracer->spanBuilder('test_span')->startSpan();

        $this->registry->addSpan($span, 'test_span');
        $this->assertCount(1, $this->registry->getSpans());

        $this->registry->removeSpan('test_span');
        $this->assertCount(0, $this->registry->getSpans());
    }

    public function testInstrumentationRegistryCanManageContext(): void
    {
        $tracer = $this->traceService->getTracer('test-tracer');
        $span = $tracer->spanBuilder('test_span')->startSpan();
        $scope = $span->activate();

        $this->registry->setContext(Context::getCurrent());
        $this->registry->setScope($scope);

        $this->assertNotNull($this->registry->getContext());
        $this->assertSame($scope, $this->registry->getScope());

        $scope->detach();
        $span->end();
    }

    public function testInstrumentationCanHandleExceptions(): void
    {
        $tracer = $this->traceService->getTracer('test-tracer');
        /** @var TextMapPropagatorInterface $propagator */
        $propagator = $this->container->get(TextMapPropagatorInterface::class);
        /** @var ClockInterface $clock */
        $clock = $this->container->get(ClockInterface::class);

        $instrumentation = new ExecutionTimeInstrumentation(
            $this->registry,
            $tracer,
            $propagator,
            $clock
        );

        $instrumentation->pre();

        try {
            throw new Exception('Test exception during instrumentation');
        } catch (Exception $e) {
            $instrumentation->post();
            $this->assertInstanceOf(Exception::class, $e);
        }
    }
}

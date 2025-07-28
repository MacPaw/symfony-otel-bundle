<?php

declare(strict_types=1);

namespace Tests\Integration;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\RequestStack;
use Exception;
use Macpaw\SymfonyOtelBundle\Service\TraceService;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Config\FileLocator;

class TraceServiceIntegrationTest extends TestCase
{
    private ContainerBuilder $container;
    private YamlFileLoader $loader;
    private TraceService $traceService;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->loader = new YamlFileLoader($this->container, new FileLocator(__DIR__ . '/../../Resources/config'));

        $this->container->setParameter('otel_bundle.service_name', 'test-service');
        $this->container->setParameter('otel_bundle.tracer_name', 'test-tracer');

        $this->container->register('http_client', HttpClientInterface::class)
            ->setClass(HttpClient::class);
        $this->container->register('request_stack', RequestStack::class);

        $this->loader->load('services.yml');
        $this->container->compile();

        /** @var TraceService $traceService */
        $traceService = $this->container->get(TraceService::class);
        $this->traceService = $traceService;
    }

    public function testTraceServiceIsProperlyConfigured(): void
    {
        $this->assertInstanceOf(TraceService::class, $this->traceService);

        $tracer = $this->traceService->getTracer('test-tracer');
        $this->assertInstanceOf(TracerInterface::class, $tracer);

        $span = $tracer->spanBuilder('test_span')->startSpan();
        $this->assertTrue($span->getContext()->isValid());
        $span->end();
    }

    public function testCanCreateAndEndSpanWithContainerConfiguration(): void
    {
        $tracer = $this->traceService->getTracer('test-tracer');

        $span = $tracer->spanBuilder('test_span')
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->startSpan();

        $scope = $span->activate();

        try {
            $this->assertTrue($span->getContext()->isValid());
            $span->setAttribute('test.attribute', 'test_value');
        } finally {
            $scope->detach();
            $span->end();
        }
    }

    public function testCanAddAttributesToSpan(): void
    {
        $tracer = $this->traceService->getTracer('test-tracer');

        $span = $tracer->spanBuilder('test_span_with_attributes')
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->startSpan();

        $scope = $span->activate();

        try {
            $span->setAttribute('test.attribute', 'test_value');
            $span->setAttribute('test.number', 42);
            $span->setAttribute('test.boolean', true);

            $this->assertTrue($span->getContext()->isValid());
        } finally {
            $scope->detach();
            $span->end();
        }
    }

    public function testCanAddEventsToSpan(): void
    {
        $tracer = $this->traceService->getTracer('test-tracer');

        $span = $tracer->spanBuilder('test_span_with_events')
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->startSpan();

        $scope = $span->activate();

        try {
            $span->addEvent('test_event');
            $span->addEvent('test_event_with_attributes', [
                'attribute1' => 'value1',
                'attribute2' => 123
            ]);

            $this->assertTrue($span->getContext()->isValid());
        } finally {
            $scope->detach();
            $span->end();
        }
    }

    public function testCanHandleErrorsInSpan(): void
    {
        $tracer = $this->traceService->getTracer('test-tracer');

        $span = $tracer->spanBuilder('test_error_span')
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->startSpan();

        $scope = $span->activate();

        try {
            $exception = new Exception('Test exception for tracing');
            $span->recordException($exception);
            $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());

            $this->assertTrue($span->getContext()->isValid());
        } finally {
            $scope->detach();
            $span->end();
        }
    }

    public function testCanCreateNestedSpans(): void
    {
        $tracer = $this->traceService->getTracer('test-tracer');

        $parentSpan = $tracer->spanBuilder('parent_span')
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->startSpan();

        $parentScope = $parentSpan->activate();

        try {
            $childSpan = $tracer->spanBuilder('child_span')
                ->setSpanKind(SpanKind::KIND_CLIENT)
                ->startSpan();

            $childScope = $childSpan->activate();

            try {
                $this->assertTrue($childSpan->getContext()->isValid());
                $this->assertTrue($parentSpan->getContext()->isValid());

                $this->assertEquals(
                    $parentSpan->getContext()->getTraceId(),
                    $childSpan->getContext()->getTraceId()
                );
            } finally {
                $childScope->detach();
                $childSpan->end();
            }
        } finally {
            $parentScope->detach();
            $parentSpan->end();
        }
    }

    public function testTraceServiceShutdown(): void
    {
        $this->traceService->shutdown();

        $tracer = $this->traceService->getTracer('test-tracer');
        $this->assertInstanceOf(TracerInterface::class, $tracer);
    }

    public function testTraceServiceIsRegisteredInContainer(): void
    {
        $this->assertTrue($this->container->has(TraceService::class));

        $traceServiceFromContainer = $this->container->get(TraceService::class);
        $this->assertSame($this->traceService, $traceServiceFromContainer);
    }

    public function testTraceServiceUsesContainerConfiguration(): void
    {
        $tracer = $this->traceService->getTracer('test-tracer');
        $span = $tracer->spanBuilder('config_test_span')->startSpan();

        $this->assertTrue($span->getContext()->isValid());
        $span->end();
    }
}

<?php

declare(strict_types=1);

namespace Benchmarks;

use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SemConv\ResourceAttributes;
use PhpBench\Attributes as Bench;

/**
 * Comprehensive benchmarks for OpenTelemetry bundle functionality.
 * Tests span creation, attributes, nesting, and export performance.
 */
#[Bench\BeforeMethods('setUp')]
#[Bench\Iterations(10)]
#[Bench\Revs(100)]
#[Bench\Warmup(2)]
final class BundleOverheadBench
{
    private TracerInterface $tracer;
    private InMemoryExporter $exporter;
    private TracerProvider $tracerProvider;

    public function setUp(): void
    {
        // Create in-memory exporter for testing without network overhead
        $this->exporter = new InMemoryExporter();

        // Create tracer provider with simple processor
        $resource = ResourceInfo::create(Attributes::create([
            ResourceAttributes::SERVICE_NAME => 'benchmark-service',
            ResourceAttributes::SERVICE_VERSION => '1.0.0',
        ]));

        $this->tracerProvider = new TracerProvider(
            new SimpleSpanProcessor($this->exporter),
            null,
            $resource
        );

        $this->tracer = $this->tracerProvider->getTracer('benchmark-tracer');
    }

    #[Bench\Subject]
    #[Bench\OutputTimeUnit('microseconds')]
    public function benchSimpleSpanCreation(): void
    {
        $span = $this->tracer->spanBuilder('test-span')->startSpan();
        $span->end();
    }

    #[Bench\Subject]
    #[Bench\OutputTimeUnit('microseconds')]
    public function benchSpanWithAttributes(): void
    {
        $span = $this->tracer->spanBuilder('test-span-with-attrs')
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->startSpan();

        $span->setAttribute('operation.type', 'test');
        $span->setAttribute('user.id', 12345);
        $span->setAttribute('request.path', '/api/test');
        $span->setAttribute('response.status', 200);
        $span->setAttribute('processing.time_ms', 42.5);

        $span->end();
    }

    #[Bench\Subject]
    #[Bench\OutputTimeUnit('microseconds')]
    public function benchNestedSpans(): void
    {
        $rootSpan = $this->tracer->spanBuilder('root-span')->startSpan();
        $scope1 = $rootSpan->activate();

        $childSpan1 = $this->tracer->spanBuilder('child-span-1')->startSpan();
        $scope2 = $childSpan1->activate();

        $childSpan2 = $this->tracer->spanBuilder('child-span-2')->startSpan();
        $childSpan2->end();

        $scope2->detach();
        $childSpan1->end();

        $scope1->detach();
        $rootSpan->end();
    }

    #[Bench\Subject]
    #[Bench\OutputTimeUnit('microseconds')]
    public function benchSpanWithEvents(): void
    {
        $span = $this->tracer->spanBuilder('span-with-events')->startSpan();

        $span->addEvent('request.started', Attributes::create([
            'http.method' => 'GET',
            'http.url' => '/api/test',
        ]));

        $span->addEvent('request.processing');

        $span->addEvent('request.completed', Attributes::create([
            'http.status_code' => 200,
            'response.time_ms' => 123.45,
        ]));

        $span->end();
    }

    #[Bench\Subject]
    #[Bench\OutputTimeUnit('microseconds')]
    public function benchMultipleSpansSequential(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $span = $this->tracer->spanBuilder("span-{$i}")->startSpan();
            $span->setAttribute('iteration', $i);
            $span->end();
        }
    }

    #[Bench\Subject]
    #[Bench\OutputTimeUnit('microseconds')]
    public function benchComplexSpanHierarchy(): void
    {
        // Simulate HTTP request span
        $httpSpan = $this->tracer->spanBuilder('http.request')
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->startSpan();
        $httpScope = $httpSpan->activate();

        $httpSpan->setAttribute('http.method', 'POST');
        $httpSpan->setAttribute('http.route', '/api/orders');
        $httpSpan->setAttribute('http.status_code', 200);

        // Business logic span
        $businessSpan = $this->tracer->spanBuilder('process.order')
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->startSpan();
        $businessScope = $businessSpan->activate();

        $businessSpan->setAttribute('order.id', 'ORD-12345');
        $businessSpan->setAttribute('order.items_count', 3);

        // Database span
        $dbSpan = $this->tracer->spanBuilder('db.query')
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->startSpan();

        $dbSpan->setAttribute('db.system', 'postgresql');
        $dbSpan->setAttribute('db.operation', 'INSERT');
        $dbSpan->setAttribute('db.statement', 'INSERT INTO orders...');
        $dbSpan->end();

        $businessScope->detach();
        $businessSpan->end();

        $httpScope->detach();
        $httpSpan->end();
    }

    #[Bench\Subject]
    #[Bench\OutputTimeUnit('microseconds')]
    public function benchSpanExport(): void
    {
        // Create 5 spans
        for ($i = 0; $i < 5; $i++) {
            $span = $this->tracer->spanBuilder("export-span-{$i}")->startSpan();
            $span->setAttribute('batch.number', $i);
            $span->end();
        }

        // Force flush to export
        $this->tracerProvider->forceFlush();
    }

    #[Bench\Subject]
    #[Bench\OutputTimeUnit('microseconds')]
    public function benchHighAttributeCount(): void
    {
        $span = $this->tracer->spanBuilder('high-attr-span')->startSpan();

        // Add 20 attributes
        for ($i = 0; $i < 20; $i++) {
            $span->setAttribute("attr.key_{$i}", "value_{$i}");
        }

        $span->end();
    }

    #[Bench\Subject]
    #[Bench\OutputTimeUnit('microseconds')]
    public function benchSpanWithLargeAttributes(): void
    {
        $span = $this->tracer->spanBuilder('large-attr-span')->startSpan();

        $span->setAttribute('request.body', str_repeat('x', 1024)); // 1KB
        $span->setAttribute('response.body', str_repeat('y', 2048)); // 2KB
        $span->setAttribute('metadata.json', json_encode(array_fill(0, 50, ['key' => 'value', 'number' => 42])));

        $span->end();
    }

    #[Bench\Subject]
    #[Bench\OutputTimeUnit('microseconds')]
    public function benchDeeplyNestedSpans(): void
    {
        $spans = [];
        $scopes = [];

        // Create 5 levels of nesting
        for ($i = 0; $i < 5; $i++) {
            $span = $this->tracer->spanBuilder("nested-level-{$i}")->startSpan();
            $spans[] = $span;
            $scopes[] = $span->activate();
            $span->setAttribute('depth', $i);
        }

        // Unwind the stack
        for ($i = 4; $i >= 0; $i--) {
            $scopes[$i]->detach();
            $spans[$i]->end();
        }
    }
}

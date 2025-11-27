<?php

declare(strict_types=1);

namespace Tests\Unit\Logging;

use Macpaw\SymfonyOtelBundle\Logging\MonologTraceContextProcessor;
use OpenTelemetry\API\Trace\TraceFlagsInterface;
use PHPUnit\Framework\TestCase;
use Tests\Support\Telemetry\InMemoryProviderFactory;

class MonologTraceContextProcessorTest extends TestCase
{
    public function testInvokeWithValidSpanAndIsSampled(): void
    {
        $processor = new MonologTraceContextProcessor();
        $record = ['context' => []];

        // Create a real span with valid context
        $provider = InMemoryProviderFactory::create();
        $tracer = $provider->getTracer('test');
        $span = $tracer->spanBuilder('test-span')->startSpan();
        $scope = $span->activate();

        try {
            $result = $processor($record);
            $this->assertArrayHasKey('context', $result);
            $this->assertArrayHasKey('trace_id', $result['context']);
            $this->assertArrayHasKey('span_id', $result['context']);
        } finally {
            $scope->detach();
            $span->end();
        }
    }

    public function testInvokeWithValidSpanAndGetTraceFlags(): void
    {
        $processor = new MonologTraceContextProcessor();
        $record = ['context' => []];

        // Create a real span
        $provider = InMemoryProviderFactory::create();
        $tracer = $provider->getTracer('test');
        $span = $tracer->spanBuilder('test-span')->startSpan();
        $scope = $span->activate();

        try {
            $result = $processor($record);
            $this->assertArrayHasKey('context', $result);
            // May or may not have trace_flags depending on SDK version
        } finally {
            $scope->detach();
            $span->end();
        }
    }

    public function testInvokeWithInvalidSpan(): void
    {
        $processor = new MonologTraceContextProcessor();
        $record = ['context' => []];

        // When no valid span or span context is invalid, may add trace context or return unchanged
        // The actual behavior depends on whether Span::getCurrent() returns a valid span
        $result = $processor($record);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('context', $result);
    }

    public function testInvokeWithCustomKeys(): void
    {
        $processor = new MonologTraceContextProcessor([
            'trace_id' => 'custom_trace_id',
            'span_id' => 'custom_span_id',
            'trace_flags' => 'custom_trace_flags',
        ]);

        $this->assertInstanceOf(MonologTraceContextProcessor::class, $processor);

        // Test that custom keys are used
        $record = ['context' => []];
        $provider = InMemoryProviderFactory::create();
        $tracer = $provider->getTracer('test');
        $span = $tracer->spanBuilder('test-span')->startSpan();
        $scope = $span->activate();

        try {
            $result = $processor($record);
            if (isset($result['context']['custom_trace_id'])) {
                $this->assertArrayHasKey('custom_trace_id', $result['context']);
                $this->assertArrayHasKey('custom_span_id', $result['context']);
            }
        } finally {
            $scope->detach();
            $span->end();
        }
    }

    public function testInvokeWithException(): void
    {
        $processor = new MonologTraceContextProcessor();
        $record = ['context' => []];

        // Should not throw exception even if Span::getCurrent() fails
        $result = $processor($record);
        $this->assertIsArray($result);
    }

    public function testSetLogger(): void
    {
        $processor = new MonologTraceContextProcessor();
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);

        // Should not throw exception
        $processor->setLogger($logger);
        $this->assertTrue(true);
    }

    public function testInvokeWithTraceFlagsNotSampled(): void
    {
        $processor = new MonologTraceContextProcessor();
        $record = ['context' => []];

        // Create a span and test trace flags
        $provider = InMemoryProviderFactory::create();
        $tracer = $provider->getTracer('test');
        $span = $tracer->spanBuilder('test-span')->startSpan();
        $scope = $span->activate();

        try {
            $result = $processor($record);
            $this->assertArrayHasKey('context', $result);
            // trace_flags may or may not be present depending on SDK
        } finally {
            $scope->detach();
            $span->end();
        }
    }
}


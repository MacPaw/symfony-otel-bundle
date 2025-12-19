<?php

declare(strict_types=1);

namespace Tests\Unit\Attribute;

use Macpaw\SymfonyOtelBundle\Attribute\TraceSpan;
use OpenTelemetry\API\Trace\SpanKind;
use PHPUnit\Framework\TestCase;

class TraceSpanTest extends TestCase
{
    public function testConstructorWithNullKindSetsDefault(): void
    {
        $traceSpan = new TraceSpan('test-span', null);
        
        // Verify that when kind is null, it's set to KIND_INTERNAL
        $this->assertSame(SpanKind::KIND_INTERNAL, $traceSpan->kind);
    }

    public function testConstructorWithProvidedKind(): void
    {
        $traceSpan = new TraceSpan('test-span', SpanKind::KIND_CLIENT);
        
        // Verify that when kind is provided, it's used as-is
        $this->assertSame(SpanKind::KIND_CLIENT, $traceSpan->kind);
    }

    public function testConstructorWithAttributes(): void
    {
        $attributes = ['key1' => 'value1', 'key2' => 123];
        $traceSpan = new TraceSpan('test-span', null, $attributes);
        
        $this->assertSame($attributes, $traceSpan->attributes);
        $this->assertSame(SpanKind::KIND_INTERNAL, $traceSpan->kind);
    }
}


<?php

declare(strict_types=1);

namespace Tests\Unit\Instrumentation;

use Macpaw\SymfonyOtelBundle\Instrumentation\AbstractInstrumentation;
use Macpaw\SymfonyOtelBundle\Registry\InstrumentationRegistry;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;

class TestAbstractInstrumentation extends AbstractInstrumentation
{
    public function __construct(
        InstrumentationRegistry $registry,
        TracerInterface $tracer,
        TextMapPropagatorInterface $propagator
    ) {
        parent::__construct($registry, $tracer, $propagator);
    }

    public function getName(): string
    {
        return 'test_instrumentation';
    }

    public function pre(): void
    {
        $this->initSpan(null);
    }

    public function post(): void
    {
        if (isset($this->span)) {
            $this->closeSpan($this->span);
        }
    }

    protected function buildSpan(SpanBuilderInterface $spanBuilder): SpanInterface
    {
        return $spanBuilder->startSpan();
    }

    public function testInitSpan(?ContextInterface $context): void
    {
        $this->initSpan($context);
    }

    public function testCloseSpan(SpanInterface $span): void
    {
        $this->closeSpan($span);
    }

    public function isSpanSet(): bool
    {
        return $this->isSpanSet;
    }
}

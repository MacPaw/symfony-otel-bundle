<?php

namespace Macpaw\SymfonyOtelBundle\Instrumentation;

use App\Infrastructure\MessageBus\QueryBus;
use Macpaw\SymfonyOtelBundle\Registry\InstrumentationRegistry;
use OpenTelemetry\API\Common\Time\ClockInterface;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;

final class AsyncQueryBusHookInstrumentation extends AbstractHookInstrumentation
{
    private int $startTime = 0;

    public function __construct(
        InstrumentationRegistry $instrumentationRegistry,
        TracerInterface $tracer,
        TextMapPropagatorInterface $propagator,
        private ClockInterface $clock,
    ) {
        parent::__construct($instrumentationRegistry, $tracer, $propagator);
    }

    protected function buildSpan(SpanBuilderInterface $spanBuilder): SpanInterface
    {
        return $spanBuilder
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->setAttribute('query_bus.system', 'symfony')
            ->setAttribute('query_bus.operation', 'dispatch')
            ->setAttribute('messaging.system', 'symfony_messenger')
            ->setAttribute('messaging.operation', 'publish')
            ->startSpan();
    }

    public function getClass(): ?string
    {
        return QueryBus::class;
    }

    public function getMethod(): string
    {
        return 'dispatch';
    }

    public function pre(): void
    {
        $this->startTime = $this->clock->now();
        $this->initSpan(null);
        
        $this->span->setAttribute('query_bus.class', $this->getClass());
        $this->span->setAttribute('query_bus.method', $this->getMethod());
        $this->span->setAttribute('query_bus.start_time', $this->startTime);
        $this->span->addEvent('Query bus execution started', [
            'query_bus.operation_type' => 'async_dispatch',
            'timestamp' => $this->startTime,
        ]);
    }

    public function post(): void
    {
        $executionTime = $this->clock->now() - $this->startTime;
        
        $this->span->setAttribute('query_bus.execution_time_ns', $executionTime);
        $this->span->setAttribute('query_bus.execution_time_ms', round($executionTime / 1_000_000, 2));
        $this->span->addEvent('Query bus execution completed', [
            'query_bus.operation_type' => 'async_dispatch',
            'execution_time_ns' => $executionTime,
            'execution_time_ms' => round($executionTime / 1_000_000, 2),
        ]);
        
        $this->closeSpan($this->span);
    }

    public function getName(): string
    {
        return 'query_bus.dispatch';
    }
}

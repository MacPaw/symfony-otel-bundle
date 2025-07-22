<?php

namespace Macpaw\SymfonyOtelBundle\Instrumentation;

use App\Infrastructure\MessageBus\QueryBus;
use Macpaw\SymfonyOtelBundle\Registry\InstrumentationRegistry;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;

final class AsyncQueryBusHookInstrumentation extends AbstractHookInstrumentation
{
    public function __construct(
        InstrumentationRegistry $instrumentationRegistry,
        TracerInterface $tracer,
        TextMapPropagatorInterface $propagator,
    ) {
        parent::__construct(...func_get_args());
    }

    protected function buildSpan(SpanBuilderInterface $spanBuilder): SpanInterface
    {
        return $spanBuilder
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->setAttribute('query_bus.system', 'symfony')
            ->setAttribute('query_bus.operation', 'dispatch')
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
        $this->initSpan(null);
        $this->span->setAttribute('query_bus.class', $this->getClass());
        $this->span->addEvent('Query bus execution started');
    }

    public function post(): void
    {
        $this->span->addEvent('Query bus execution completed');
        $this->closeSpan($this->span);
    }

    public function getName(): string
    {
        return 'query_bus.dispatch';
    }
}

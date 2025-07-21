<?php

namespace Macpaw\SymfonyOtelBundle\Instrumentation;

use App\Handler\DummyHandler;
use Macpaw\SymfonyOtelBundle\Registry\InstrumentationRegistry;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;

final class CQRSHookInstrumentation extends AbstractHookInstrumentation
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
            ->setAttribute('cqrs.system', 'aaa')
            ->setAttribute('cqrs.operation', 'bbb')
            ->startSpan();
    }

    public function getClass(): ?string
    {
        return DummyHandler::class;
    }

    public function getMethod(): string
    {
        return '__invoke';
    }

    public function pre(): void
    {
        $this->initSpan(null);
        $this->span->setAttribute('cqrs.handler', $this->getClass());
        $this->span->addEvent('CQRS handler execution started');
    }

    public function post(): void
    {
        $this->span->addEvent('CQRS handler execution completed');
        $this->closeSpan($this->span);
    }

    public function getName(): string
    {
        return 'EXAMPLE SPAN NAME';
    }
}

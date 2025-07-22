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

final class ClassHookInstrumentation extends AbstractHookInstrumentation
{
    public const NAME = 'class_method.execution_name';

    private int $startTime = 0;

    public function __construct(
        InstrumentationRegistry $instrumentationRegistry,
        TracerInterface $tracer,
        TextMapPropagatorInterface $propagator,
        private ClockInterface $clock,
        public string $className,
        public string $methodName,
        public ?ClassHookInstrumetationSpanDecoratorInterface $decorator,
    ) {
        parent::__construct($instrumentationRegistry, $tracer, $propagator);
    }

    protected function buildSpan(SpanBuilderInterface $spanBuilder): SpanInterface
    {
        $span = $spanBuilder
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->startSpan();

        return $this->decorator->decorateSpanInit($span) ?? $span;
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

        assert($this->span instanceof SpanInterface);
        $this->decorator?->decorateSpanInit($this->span);
    }

    public function post(): void
    {
        $executionTime = $this->clock->now() - $this->startTime;

        $this->span->addEvent('Class method execution completed', [
            'class' => $this->className,
            '' => $executionTime,
            'execution_time_ms' => round($executionTime / 1_000_000, 2),
        ]);
        
        $this->closeSpan($this->span);
    }

    public function getName(): string
    {
        return 'query_bus.dispatch';
    }
}

<?php

declare(strict_types=1);

namespace Macpaw\SymfonyOtelBundle\Instrumentation;

use Macpaw\SymfonyOtelBundle\Registry\InstrumentationRegistry;
use OpenTelemetry\API\Common\Time\ClockInterface;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;

final class ClassHookInstrumentation extends AbstractHookInstrumentation implements TimingInterface
{
    public const NAME = 'class_method.execution_time';

    private int $startTime = 0;

    private int $endTime = 0;

    /**
     * @var ClassHookInstrumetationSpanDecoratorInterface[]
     */
    private array $spanMiddlewares = [];

    public function __construct(
        InstrumentationRegistry $instrumentationRegistry,
        TracerInterface $tracer,
        TextMapPropagatorInterface $propagator,
        private readonly ClockInterface $clock,
        private readonly string $className,
        private readonly string $methodName,
    ) {
        parent::__construct($instrumentationRegistry, $tracer, $propagator);
    }

    public function addMiddleware(ClassHookInstrumetationSpanDecoratorInterface $middleware): self
    {
        $this->spanMiddlewares[] = $middleware;

        return $this;
    }

    public function getClass(): ?string
    {
        return $this->className;
    }
    public function getMethod(): string
    {
        return $this->methodName;
    }

    public function pre(): void
    {
        $this->startTime = $this->clock->now();
        $this->initSpan(null);

        assert($this->span instanceof SpanInterface);

        foreach ($this->spanMiddlewares as $spanMiddleware) {
            $spanMiddleware->pre($this->span, $this);
        }
    }

    public function post(): void
    {
        $this->endTime = $this->clock->now();

        foreach ($this->spanMiddlewares as $spanMiddleware) {
            $spanMiddleware->post($this->span, $this);
        }

        $this->closeSpan($this->span);
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getStartTime(): int
    {
        return $this->startTime;
    }

    public function getEndTime(): int
    {
        return $this->endTime;
    }

    public function getExecutionTime(): int
    {
        return $this->endTime - $this->startTime;
    }

    protected function buildSpan(SpanBuilderInterface $spanBuilder): SpanInterface
    {
        return $spanBuilder
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->startSpan();
    }
}

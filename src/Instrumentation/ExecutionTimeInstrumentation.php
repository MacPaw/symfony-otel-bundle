<?php

declare(strict_types=1);

namespace Macpaw\SymfonyOtelBundle\Instrumentation;

use Macpaw\SymfonyOtelBundle\Registry\InstrumentationRegistry;
use OpenTelemetry\API\Common\Time\ClockInterface;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;

final class ExecutionTimeInstrumentation extends AbstractInstrumentation
{
    public const NAME = 'execution_time';

    /**
     * @var array<string, mixed>
     */
    private array $headers = [];

    private int $startTime = 0;

    public function __construct(
        InstrumentationRegistry $instrumentationRegistry,
        TracerInterface $tracer,
        TextMapPropagatorInterface $propagator,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct($instrumentationRegistry, $tracer, $propagator);
    }

    /**
     * @param array<string, mixed> $headers
     */
    public function setHeaders(array $headers): void
    {
        $this->headers = $headers;
    }

    protected function buildSpan(SpanBuilderInterface $spanBuilder): SpanInterface
    {
        return $spanBuilder->setSpanKind(SpanKind::KIND_SERVER)
            ->startSpan();
    }

    public function pre(): void
    {
        $this->startTime = $this->clock->now();

        $spanContext = null;
        if (count($this->headers) > 0) {
            $context = $this->propagator->extract($this->headers);
            $spanContext = Span::fromContext($context)->getContext();
        }

        $usedContext = $spanContext?->isValid() ? $spanContext : null;

        $this->initSpan($usedContext);
    }

    public function post(): void
    {
        $this->span->addEvent(
            sprintf('Execution time (in nanoseconds): %d', $this->clock->now() - $this->startTime),
        );

        $this->closeScope($this->scope);
        $this->closeSpan($this->span);
    }

    public function getName(): string
    {
        return self::NAME;
    }
}

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
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;

final class RequestExecutionTimeInstrumentation extends AbstractInstrumentation
{
    public const NAME = 'request.execution_time';

    /**
     * @var array<string, mixed>
     */
    private array $headers = [];

    private int $startTime = 0;

    public function __construct(
        InstrumentationRegistry $instrumentationRegistry,
        TracerInterface $tracer,
        TextMapPropagatorInterface $propagator,
        private ClockInterface $clock
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

        $context = $this->retrieveContext();

        $this->initSpan($context);
    }

    protected function retrieveContext(): ContextInterface
    {
        $context = $this->propagator->extract($this->headers);
        $spanInjectedContext = Span::fromContext($context)->getContext();

        return $spanInjectedContext->isValid() ? $context : Context::getCurrent();
    }

    public function post(): void
    {
        $executionTime = $this->clock->now() - $this->startTime;

        if ($this->isSpanSet === true) {
            $this->span->addEvent(
                sprintf('Execution time (in nanoseconds): %d', $executionTime),
            );
            $this->closeSpan($this->span);
        }
    }

    public function getName(): string
    {
        return self::NAME;
    }
}

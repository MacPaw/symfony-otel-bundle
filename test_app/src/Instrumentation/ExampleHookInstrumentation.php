<?php

declare(strict_types=1);

namespace App\Instrumentation;

use Macpaw\SymfonyOtelBundle\Instrumentation\AbstractHookInstrumentation;
use Macpaw\SymfonyOtelBundle\Registry\InstrumentationRegistry;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;
use PDO;

final class ExampleHookInstrumentation extends AbstractHookInstrumentation
{
    public function __construct(
        InstrumentationRegistry $instrumentationRegistry,
        TracerInterface $tracer,
        TextMapPropagatorInterface $propagator,
    ) {
        parent::__construct($instrumentationRegistry, $tracer, $propagator);
    }

    public function getName(): string
    {
        return 'example_hook_instrumentation';
    }

    public function getClass(): ?string //@phpstan-ignore-line
    {
        return PDO::class;
    }

    public function getMethod(): string
    {
        return 'query';
    }

    protected function buildSpan(SpanBuilderInterface $spanBuilder): SpanInterface
    {
        return $spanBuilder
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setAttribute('db.system', 'sql')
            ->setAttribute('db.operation', 'query')
            ->startSpan();
    }

    public function pre(): void
    {
        $this->initSpan(null);
        $this->span->setAttribute('hook.instrumentation', $this->getName());
        $this->span->addEvent('Hook pre-execution started');
    }

    public function post(): void
    {
        $this->span->addEvent('Hook post-execution completed');
        $this->closeSpan($this->span);
    }
}

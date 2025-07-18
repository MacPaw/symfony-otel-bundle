<?php

declare(strict_types=1);

namespace Macpaw\SymfonyOtelBundle\Instrumentation;

use Macpaw\SymfonyOtelBundle\Registry\InstrumentationRegistry;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;
use OpenTelemetry\Context\ScopeInterface;

abstract class AbstractInstrumentation implements InstrumentationInterface
{
    protected SpanInterface $span;
    protected ContextInterface $context;
    protected ScopeInterface $scope;
    protected bool $isSpanSet = false;

    public function __construct(
        protected readonly InstrumentationRegistry $instrumentationRegistry,
        protected readonly TracerInterface $tracer,
        protected readonly TextMapPropagatorInterface $propagator,
    ) {
    }

    abstract protected function buildSpan(SpanBuilderInterface $spanBuilder): SpanInterface;

    protected function initSpan(?ContextInterface $context): void
    {
        if ($this->isSpanSet) {
            $this->instrumentationRegistry->removeSpan($this->getName());
        }
        $context ??= Context::getCurrent();

        $this->instrumentationRegistry->setContext($context);

        $this->context = $this->instrumentationRegistry->getContext();

        $spanBuilder = $this->tracer->spanBuilder($this->getName())->setParent($context);

        $this->span = $this->buildSpan($spanBuilder);

        $this->instrumentationRegistry->addSpan($this->span, $this->getName());
        $this->isSpanSet = true;
    }

    protected function closeScope(ScopeInterface $scope): void
    {
        if ($this->isSpanSet === false) {
            return;
        }

        $scope->detach();
    }

    protected function closeSpan(SpanInterface $span): void
    {
        if ($this->isSpanSet === false) {
            return;
        }

        $span->end();
    }
}

<?php

declare(strict_types=1);

namespace Macpaw\SymfonyOtelBundle\Registry;

use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\Context\ScopeInterface;

final class InstrumentationRegistry
{
    private ?ContextInterface $context = null;

    /**
     * @var SpanInterface[]
     */
    private array $spans = [];

    private ?ScopeInterface $scope;

    public function addSpan(SpanInterface $span, string $name): void
    {
        $this->spans[$name] = $span;
    }

    public function initContext(ContextInterface $context): void
    {
        $this->context = $context;
        $this->scope = $context->activate();
    }

    public function getScope(): ?ScopeInterface
    {
        return $this->scope;
    }

    public function getContext(): ContextInterface
    {
        return $this->context;
    }

    /**
     * @return SpanInterface[]
     */
    public function getSpans(): array
    {
        return $this->spans;
    }

    public function removeSpan(string $spanName): void
    {
        if (array_key_exists($spanName, $this->spans) === false) {
            return;
        }

        $this->spans[$spanName] = null;
        unset($this->spans[$spanName]);
    }
}

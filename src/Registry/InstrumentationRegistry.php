<?php

declare(strict_types=1);

namespace Macpaw\SymfonyOtelBundle\Registry;

use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\Context\ScopeInterface;
use Throwable;

final class InstrumentationRegistry
{
    private ?ContextInterface $context = null;

    private ?ScopeInterface $scope = null;

    /**
     * @var SpanInterface[]
     */
    private array $spans = [];

    public function addSpan(SpanInterface $span, string $name): void
    {
        $this->spans[$name] = $span;
    }

    public function getSpan(string $name): ?SpanInterface
    {
        return $this->spans[$name] ?? null;
    }

    public function setContext(ContextInterface $context): void
    {
        $this->context = $context;
    }

    public function setScope(ScopeInterface $scope): void
    {
        $this->scope = $scope;
    }

    public function getScope(): ?ScopeInterface
    {
        return $this->scope;
    }

    public function getContext(): ?ContextInterface
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

        unset($this->spans[$spanName]);
    }

    public function clearSpans(): void
    {
        $this->spans = [];
    }

    public function detachScope(): void
    {
        if ($this->scope) {
            try {
                $this->scope->detach();
            } catch (Throwable $e) {
                // Scope already detached or invalid
            }
        }
    }

    public function clearScope(): void
    {
        $this->detachScope();
        $this->scope = null;
    }

    public function __destruct()
    {
        foreach ($this->spans as $span) {
            $span->end();
        }

        $this->clearScope();
    }
}

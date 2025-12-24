<?php

declare(strict_types=1);

namespace Macpaw\SymfonyOtelBundle\Instrumentation;

use Macpaw\SymfonyOtelBundle\Registry\InstrumentationRegistry;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;
use OpenTelemetry\SemConv\Attributes as SemConv;

/**
 * Hook-based instrumentation created from #[TraceSpan] attribute on a service method.
 * Starts a span before method execution and ends it afterwards.
 */
final class AttributeMethodInstrumentation extends AbstractHookInstrumentation
{
    /**
     * @param class-string                     $className
     * @param non-empty-string                 $methodName
     * @param non-empty-string                 $spanName
     * @param int                              $spanKind One of OpenTelemetry\API\Trace\SpanKind::KIND_*
     * @param array<string, scalar|array|null> $defaultAttributes
     * @phpstan-ignore-next-line missingType.iterableValue - Type is specified in PHPDoc above
     */
    public function __construct(
        InstrumentationRegistry $instrumentationRegistry,
        TracerInterface $tracer,
        TextMapPropagatorInterface $propagator,
        private readonly string $className,
        private readonly string $methodName,
        private readonly string $spanName,
        private readonly int $spanKind,
        private readonly array $defaultAttributes = [],
    ) {
        parent::__construct($instrumentationRegistry, $tracer, $propagator);
    }

    public function getClass(): string
    {
        return $this->className;
    }

    public function getMethod(): string
    {
        return $this->methodName;
    }

    public function pre(): void
    {
        $this->initSpan($this->instrumentationRegistry->getContext());

        // Standard code.* semantic attributes (namespace + function)
        $this->span->setAttribute(
            SemConv\CodeAttributes::CODE_FUNCTION_NAME,
            sprintf('%s::%s', $this->className, $this->methodName),
        );

        // Set default attributes declared on the attribute
        foreach ($this->defaultAttributes as $key => $value) {
            /** @var non-empty-string $key */
            $this->span->setAttribute($key, $value);
        }
    }

    public function post(): void
    {
        $this->closeSpan($this->span);
    }

    public function getName(): string
    {
        return $this->spanName;
    }

    protected function buildSpan(SpanBuilderInterface $spanBuilder): SpanInterface
    {
        return $spanBuilder
            // spanKind is validated to be one of SpanKind::KIND_* constants at construction
            // @phpstan-ignore-next-line argument.type
            ->setSpanKind($this->spanKind)
            ->startSpan();
    }
}

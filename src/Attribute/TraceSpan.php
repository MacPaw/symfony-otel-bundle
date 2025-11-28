<?php

declare(strict_types=1);

namespace Macpaw\SymfonyOtelBundle\Attribute;

use Attribute;
use OpenTelemetry\API\Trace\SpanKind;

/**
 * Attribute to declare a tracing span around a method.
 *
 * Example:
 * #[TraceSpan('Checkout')]
 * public function __invoke(Command $c): void {}
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class TraceSpan
{
    /**
     * @param non-empty-string                 $name Span name
     * @param int|null                         $kind One of OpenTelemetry\API\Trace\SpanKind::*
     *                         (defaults to KIND_INTERNAL)
     * @param array<string, scalar|array|null> $attributes Default attributes to set
     * on span start
     */
    public function __construct(
        public string $name,
        public ?int $kind = null,
        public array $attributes = [],
    ) {
        if ($this->kind === null) {
            $this->kind = SpanKind::KIND_INTERNAL;
        }
    }
}

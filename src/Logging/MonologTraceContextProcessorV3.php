<?php

declare(strict_types=1);

namespace Macpaw\SymfonyOtelBundle\Logging;

use Monolog\LogRecord;
use OpenTelemetry\API\Trace\Span;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Monolog v3 processor that injects OpenTelemetry trace context into every log record.
 *
 * Adds configurable keys (defaults: trace_id, span_id, trace_flags) into $record->extra when a valid
 * span context is available.
 */
final class MonologTraceContextProcessorV3 implements LoggerAwareInterface
{
    /** @var array{trace_id:string, span_id:string, trace_flags:string} */
    private array $keys;

    /**
     * @param array{trace_id?:string, span_id?:string, trace_flags?:string} $keys
     */
    public function __construct(array $keys = [])
    {
        $this->keys = [
            'trace_id' => $keys['trace_id'] ?? 'trace_id',
            'span_id' => $keys['span_id'] ?? 'span_id',
            'trace_flags' => $keys['trace_flags'] ?? 'trace_flags',
        ];
    }

    public function setLogger(LoggerInterface $logger): void
    {
        // no-op; required by LoggerAwareInterface for some Monolog integrations
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        try {
            $span = Span::getCurrent();
            $ctx = $span->getContext();
            if (!$ctx->isValid()) {
                return $record;
            }

            $traceId = $ctx->getTraceId();
            $spanId = $ctx->getSpanId();
            $sampled = null;
            // Some SDK versions expose isSampled(), others expose getTraceFlags()->isSampled()
            // @phpstan-ignore-next-line function.alreadyNarrowedType
            if (method_exists($ctx, 'isSampled')) {
                $sampled = $ctx->isSampled();
            } elseif (method_exists($ctx, 'getTraceFlags')) {
                $flags = $ctx->getTraceFlags();
                // @phpstan-ignore-next-line function.impossibleType,function.alreadyNarrowedType,booleanAnd.alwaysFalse
                if (is_object($flags) && method_exists($flags, 'isSampled')) {
                    $sampled = (bool) $flags->isSampled();
                }
            }

            $record->extra[$this->keys['trace_id']] = $traceId;
            $record->extra[$this->keys['span_id']] = $spanId;
            if ($sampled !== null) {
                $record->extra[$this->keys['trace_flags']] = $sampled ? '01' : '00';
            }
        } catch (Throwable) {
            // never break logging
            return $record;
        }

        return $record;
    }
}

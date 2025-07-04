<?php

declare(strict_types=1);

namespace Macpaw\SymfonyOtelBundle\Service;

use OpenTelemetry\API\Behavior\LogsMessagesTrait;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\SDK\Common\Future\CancellationInterface;
use OpenTelemetry\SDK\Common\Future\CompletedFuture;
use OpenTelemetry\SDK\Common\Future\FutureInterface;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use Psr\Http\Message\ResponseInterface;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Http\Browser;
use React\Promise\PromiseInterface;

/**
 * ReactPHP-based OTLP exporter for asynchronous trace sending
 */
class ReactPhpOtlpExporter implements SpanExporterInterface
{
    use LogsMessagesTrait;

    private LoopInterface $loop;
    private Browser $browser;
    private string $endpoint;
    private array $headers;
    private array $pendingSpans = [];
    private bool $running = true;

    public function __construct(
        string $endpoint = 'http://localhost:4318/v1/traces',
        array $headers = [],
        private int $batchSize = 100,
        private float $flushInterval = 5.0,
    ) {
        $this->loop = Loop::get();
        $this->browser = new Browser($this->loop);
        $this->endpoint = $endpoint;
        $this->headers = array_merge([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ], $headers);

        // Start periodic flush timer
        $this->startFlushTimer();
    }

    /**
     * Export spans asynchronously
     */
    public function export(iterable $spans, ?CancellationInterface $cancellation = null): FutureInterface
    {
        $spansArray = [];
        foreach ($spans as $span) {
            $spansArray[] = $span;
        }

        // Add spans to pending batch
        $this->pendingSpans = array_merge($this->pendingSpans, $spansArray);

        // Auto-flush if batch size is reached
        if (count($this->pendingSpans) >= $this->batchSize) {
            $this->flushPendingSpans();
        }

        // Return immediately (non-blocking)
        return new CompletedFuture(true);
    }

    /**
     * Force flush all pending spans
     */
    public function forceFlush(?CancellationInterface $cancellation = null): bool
    {
        $this->flushPendingSpans();
        return true;
    }

    /**
     * Shutdown the exporter
     */
    public function shutdown(?CancellationInterface $cancellation = null): bool
    {
        $this->running = false;
        $this->flushPendingSpans();
        return true;
    }

    /**
     * Send spans batch asynchronously
     */
    public function sendSpansBatchAsync(array $spans): PromiseInterface
    {
        if (empty($spans)) {
            return \React\Promise\resolve([]);
        }

        $payload = $this->convertSpansToOtlp($spans);

        return $this->browser
            ->post($this->endpoint, $this->headers, json_encode($payload))
            ->then(
                function (ResponseInterface $response) use ($spans) {
                    $this->handleSuccessfulResponse($response, $spans);
                    return $response;
                },
                function (\Exception $error) use ($spans) {
                    $this->handleErrorResponse($error, $spans);
                    throw $error;
                },
            );
    }

    /**
     * Flush all pending spans
     */
    private function flushPendingSpans(): void
    {
        if (empty($this->pendingSpans)) {
            return;
        }

        $spans = $this->pendingSpans;
        $this->pendingSpans = [];

        // Send asynchronously without blocking
        $this->sendSpansBatchAsync($spans);
    }

    /**
     * Start periodic flush timer
     */
    private function startFlushTimer(): void
    {
        $this->loop->addPeriodicTimer($this->flushInterval, function () {
            if ($this->running) {
                $this->flushPendingSpans();
            }
        });
    }

    /**
     * Convert spans to OTLP format
     */
    private function convertSpansToOtlp(array $spans): array
    {
        $otlpSpans = [];

        foreach ($spans as $span) {
            if (!$span instanceof SpanInterface) {
                continue;
            }

            $context = $span->getContext();
            $otlpSpans[] = [
                'traceId' => $context->getTraceId(),
                'spanId' => $context->getSpanId(),
                'parentSpanId' => $context->getParentSpanId() ?? '',
                'name' => $span->getName(),
                'kind' => $span->getKind(),
                'startTimeUnixNano' => $span->getStartEpochNanos(),
                'endTimeUnixNano' => $span->getEndEpochNanos(),
                'attributes' => $this->convertAttributes($span->getAttributes()),
                'events' => $this->convertEvents($span->getEvents()),
                'status' => [
                    'code' => $span->getStatus()->getCode(),
                    'message' => $span->getStatus()->getDescription(),
                ],
            ];
        }

        return [
            'resourceSpans' => [
                [
                    'resource' => [
                        'attributes' => [
                            [
                                'key' => 'service.name',
                                'value' => ['stringValue' => 'symfony-otel-bundle'],
                            ],
                        ],
                    ],
                    'scopeSpans' => [
                        [
                            'scope' => [
                                'name' => 'symfony-otel-bundle',
                            ],
                            'spans' => $otlpSpans,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Convert span attributes to OTLP format
     */
    private function convertAttributes(array $attributes): array
    {
        $otlpAttributes = [];

        foreach ($attributes as $key => $value) {
            $otlpAttributes[] = [
                'key' => $key,
                'value' => $this->convertAttributeValue($value),
            ];
        }

        return $otlpAttributes;
    }

    /**
     * Convert attribute value to OTLP format
     */
    private function convertAttributeValue($value): array
    {
        return match (gettype($value)) {
            'string' => ['stringValue' => $value],
            'integer' => ['intValue' => $value],
            'double' => ['doubleValue' => $value],
            'boolean' => ['boolValue' => $value],
            default => ['stringValue' => (string)$value]
        };
    }

    /**
     * Convert span events to OTLP format
     */
    private function convertEvents(array $events): array
    {
        $otlpEvents = [];

        foreach ($events as $event) {
            $otlpEvents[] = [
                'timeUnixNano' => $event->getEpochNanos(),
                'name' => $event->getName(),
                'attributes' => $this->convertAttributes($event->getAttributes()),
            ];
        }

        return $otlpEvents;
    }

    /**
     * Handle successful HTTP response
     */
    private function handleSuccessfulResponse(ResponseInterface $response, array $spans): void
    {
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $this->info('Successfully sent batch of ' . count($spans) . ' spans');
        } else {
            $this->error('Failed to send batch of ' . count($spans) . ' spans, HTTP ' . $response->getStatusCode());
        }
    }

    /**
     * Handle HTTP error response
     */
    private function handleErrorResponse(\Exception $error, array $spans): void
    {
        $this->error('Error sending batch of ' . count($spans) . ' spans: ' . $error->getMessage());
    }

    /**
     * Get the event loop (for advanced usage)
     */
    public function getLoop(): LoopInterface
    {
        return $this->loop;
    }
}

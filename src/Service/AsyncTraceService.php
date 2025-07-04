<?php

declare(strict_types=1);

namespace Macpaw\SymfonyOtelBundle\Service;

use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\SDK\Trace\TracerProviderInterface;
use Psr\Http\Message\ResponseInterface;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Http\Browser;
use React\Promise\PromiseInterface;

readonly class AsyncTraceService
{
    private LoopInterface $loop;
    private Browser $browser;
    private string $otlpEndpoint;
    private array $headers;
    private array $pendingSpans;

    public function __construct(
        private TracerProviderInterface $tracerProvider,
        string $otlpEndpoint = 'http://localhost:4318/v1/traces',
        array $headers = [],
    ) {
        $this->loop = Loop::get();
        $this->browser = new Browser($this->loop);
        $this->otlpEndpoint = $otlpEndpoint;
        $this->headers = array_merge([
            'Content-Type' => 'application/x-protobuf',
            'Accept' => 'application/json',
        ], $headers);
        $this->pendingSpans = [];
    }

    public function getTracer(string $name): TracerInterface
    {
        return $this->tracerProvider->getTracer($name);
    }

    /**
     * Send span data asynchronously to OpenTelemetry endpoint
     */
    public function sendSpanAsync(SpanInterface $span): PromiseInterface
    {
        // Convert span to OTLP format
        $spanData = $this->convertSpanToOtlp($span);

        // Send HTTP request asynchronously
        return $this->browser
            ->post($this->otlpEndpoint, $this->headers, json_encode($spanData))
            ->then(
                function (ResponseInterface $response) use ($span) {
                    $this->handleSuccessfulResponse($response, $span);
                    return $response;
                },
                function (\Exception $error) use ($span) {
                    $this->handleErrorResponse($error, $span);
                    throw $error;
                },
            );
    }

    /**
     * Batch send multiple spans asynchronously
     */
    public function sendSpansBatch(array $spans): PromiseInterface
    {
        if (empty($spans)) {
            return \React\Promise\resolve([]);
        }

        // Convert all spans to OTLP format
        $batchData = $this->convertSpansBatchToOtlp($spans);

        // Send batch HTTP request asynchronously
        return $this->browser
            ->post($this->otlpEndpoint, $this->headers, json_encode($batchData))
            ->then(
                function (ResponseInterface $response) use ($spans) {
                    $this->handleSuccessfulBatchResponse($response, $spans);
                    return $response;
                },
                function (\Exception $error) use ($spans) {
                    $this->handleErrorBatchResponse($error, $spans);
                    throw $error;
                },
            );
    }

    /**
     * Queue span for batch processing
     */
    public function queueSpan(SpanInterface $span): void
    {
        $this->pendingSpans[] = $span;

        // Auto-flush when batch size is reached
        if (count($this->pendingSpans) >= 100) {
            $this->flushPendingSpans();
        }
    }

    /**
     * Flush all pending spans
     */
    public function flushPendingSpans(): PromiseInterface
    {
        if (empty($this->pendingSpans)) {
            return \React\Promise\resolve([]);
        }

        $spans = $this->pendingSpans;
        $this->pendingSpans = [];

        return $this->sendSpansBatch($spans);
    }

    /**
     * Shutdown the service and flush any pending spans
     */
    public function shutdown(): PromiseInterface
    {
        return $this->flushPendingSpans()
            ->then(function () {
                $this->tracerProvider->shutdown();
                return true;
            });
    }

    /**
     * Convert a single span to OTLP format
     */
    private function convertSpanToOtlp(SpanInterface $span): array
    {
        $context = $span->getContext();

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
                            'spans' => [
                                [
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
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Convert multiple spans to OTLP batch format
     */
    private function convertSpansBatchToOtlp(array $spans): array
    {
        $otlpSpans = [];

        foreach ($spans as $span) {
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
    private function handleSuccessfulResponse(ResponseInterface $response, SpanInterface $span): void
    {
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            // Success - optionally log or handle successful trace export
            error_log("Successfully sent span: {$span->getName()}");
        } else {
            error_log("Failed to send span: {$span->getName()}, HTTP {$response->getStatusCode()}");
        }
    }

    /**
     * Handle HTTP error response
     */
    private function handleErrorResponse(\Exception $error, SpanInterface $span): void
    {
        error_log("Error sending span: {$span->getName()}, Error: {$error->getMessage()}");
    }

    /**
     * Handle successful batch response
     */
    private function handleSuccessfulBatchResponse(ResponseInterface $response, array $spans): void
    {
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            error_log("Successfully sent batch of " . count($spans) . " spans");
        } else {
            error_log("Failed to send batch of " . count($spans) . " spans, HTTP {$response->getStatusCode()}");
        }
    }

    /**
     * Handle batch error response
     */
    private function handleErrorBatchResponse(\Exception $error, array $spans): void
    {
        error_log("Error sending batch of " . count($spans) . " spans, Error: {$error->getMessage()}");
    }

    /**
     * Get the event loop (for advanced usage)
     */
    public function getLoop(): LoopInterface
    {
        return $this->loop;
    }
}

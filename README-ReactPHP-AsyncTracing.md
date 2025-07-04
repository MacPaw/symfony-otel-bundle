# ReactPHP Async OpenTelemetry Tracing Guide

## Overview

Using ReactPHP for OpenTelemetry trace sending provides significant performance benefits by making HTTP requests
non-blocking. This guide shows you how to integrate ReactPHP with your Symfony OpenTelemetry bundle for asynchronous
trace export.

## Benefits of Async Trace Sending

### 🚀 Performance Advantages

- **Non-blocking I/O**: HTTP requests don't block your application
- **Batching**: Multiple spans sent together reduce HTTP overhead
- **Parallel Processing**: Multiple traces can be processed simultaneously
- **Resource Efficiency**: Better memory and CPU usage

### 📊 Performance Comparison

**Synchronous (Default)**:

```php
Request 1: [====] 50ms (blocking)
Request 2: [====] 50ms (blocking)  
Request 3: [====] 50ms (blocking)
Total: 150ms
```

**Asynchronous (ReactPHP)**:

```php
Request 1: [====] 50ms (non-blocking)
Request 2: [====] 50ms (non-blocking)
Request 3: [====] 50ms (non-blocking)
Total: 50ms (parallel execution)
```

## Installation

### 1. Add ReactPHP Dependencies

```bash
composer require react/socket:^1.15 react/http:^1.10 react/promise:^3.2 react/stream:^1.4
```

### 2. Update composer.json

Add to your `composer.json`:

```json
{
  "require": {
    "react/socket": "^1.15",
    "react/http": "^1.10",
    "react/promise": "^3.2",
    "react/stream": "^1.4"
  }
}
```

## Implementation

### 1. Async Trace Exporter

Create `src/Service/AsyncTraceExporter.php`:

```php
<?php

declare(strict_types=1);

namespace Macpaw\SymfonyOtelBundle\Service;

use React\EventLoop\Loop;
use React\Http\Browser;
use React\Promise\PromiseInterface;
use Psr\Http\Message\ResponseInterface;

class AsyncTraceExporter
{
    private $loop;
    private $browser;
    private $endpoint;
    private $headers;
    private $pendingSpans = [];
    private $batchSize;
    private $flushInterval;

    public function __construct(
        string $endpoint = 'http://localhost:4318/v1/traces',
        array $headers = [],
        int $batchSize = 100,
        float $flushInterval = 5.0
    ) {
        $this->loop = Loop::get();
        $this->browser = new Browser($this->loop);
        $this->endpoint = $endpoint;
        $this->headers = array_merge([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ], $headers);
        $this->batchSize = $batchSize;
        $this->flushInterval = $flushInterval;

        // Start periodic flush timer
        $this->startFlushTimer();
    }

    /**
     * Queue a span for async sending
     */
    public function queueSpan(array $spanData): void
    {
        $this->pendingSpans[] = $spanData;
        
        // Auto-flush when batch size is reached
        if (count($this->pendingSpans) >= $this->batchSize) {
            $this->flushPendingSpans();
        }
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
                }
            );
    }

    /**
     * Flush all pending spans
     */
    public function flushPendingSpans(): void
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
            $this->flushPendingSpans();
        });
    }

    /**
     * Convert spans to OTLP format
     */
    private function convertSpansToOtlp(array $spans): array
    {
        return [
            'resourceSpans' => [
                [
                    'resource' => [
                        'attributes' => [
                            [
                                'key' => 'service.name',
                                'value' => ['stringValue' => 'symfony-otel-bundle']
                            ]
                        ]
                    ],
                    'scopeSpans' => [
                        [
                            'scope' => [
                                'name' => 'symfony-otel-bundle'
                            ],
                            'spans' => $spans
                        ]
                    ]
                ]
            ]
        ];
    }

    private function handleSuccessfulResponse(ResponseInterface $response, array $spans): void
    {
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            error_log("✅ Successfully sent batch of " . count($spans) . " spans");
        } else {
            error_log("❌ Failed to send batch, HTTP " . $response->getStatusCode());
        }
    }

    private function handleErrorResponse(\Exception $error, array $spans): void
    {
        error_log("❌ Error sending batch: " . $error->getMessage());
    }

    public function getLoop()
    {
        return $this->loop;
    }
}
```

### 2. Async Execution Time Tracer

Update `src/Span/ExecutionTimeSpanTracer.php`:

```php
<?php

declare(strict_types=1);

namespace Macpaw\SymfonyOtelBundle\Span;

use Macpaw\SymfonyOtelBundle\Service\AsyncTraceExporter;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class AsyncExecutionTimeSpanTracer implements EventSubscriberInterface
{
    public const NAME = 'execution_time';

    private float $startTime;
    private ?SpanInterface $contextSpan = null;

    public function __construct(
        private readonly AsyncTraceExporter $asyncExporter,
        private readonly TextMapPropagatorInterface $propagator,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $context = $this->checkTraceInjectionValidity($event);
        if ($context === null) {
            return;
        }

        $this->startTime = microtime(true);
        
        // For async implementation, we would create span data instead of actual span
        // This is a conceptual example - actual implementation depends on your setup
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        if ($this->startTime === null) {
            return;
        }

        $executionTime = microtime(true) - $this->startTime;
        
        // Create span data for async sending
        $spanData = [
            'traceId' => bin2hex(random_bytes(16)),
            'spanId' => bin2hex(random_bytes(8)),
            'parentSpanId' => '',
            'name' => self::NAME,
            'kind' => 1, // SPAN_KIND_SERVER
            'startTimeUnixNano' => (int) ($this->startTime * 1_000_000_000),
            'endTimeUnixNano' => (int) (microtime(true) * 1_000_000_000),
            'attributes' => [
                [
                    'key' => 'execution.time',
                    'value' => ['doubleValue' => $executionTime]
                ]
            ],
            'events' => [
                [
                    'timeUnixNano' => (int) (microtime(true) * 1_000_000_000),
                    'name' => sprintf('Execution time: %f seconds', $executionTime),
                    'attributes' => []
                ]
            ],
            'status' => [
                'code' => 1, // STATUS_CODE_OK
                'message' => 'Success'
            ]
        ];

        // Queue span for async sending (non-blocking)
        $this->asyncExporter->queueSpan($spanData);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
            KernelEvents::TERMINATE => 'onKernelTerminate',
        ];
    }

    protected function checkTraceInjectionValidity(RequestEvent $event): ?ContextInterface
    {
        $request = $event->getRequest();
        $headers = $request->headers->all();
        $context = $this->propagator->extract($headers);
        $spanInjectedContext = Span::fromContext($context)->getContext();

        return $spanInjectedContext->isValid() ? $context : null;
    }
}
```

### 3. Service Configuration

Update `Resources/config/services.yml`:

```yaml
services:
  _defaults:
    autowire: true
    autoconfigure: true

  # Async trace exporter
  Macpaw\SymfonyOtelBundle\Service\AsyncTraceExporter:
    arguments:
      $endpoint: '%env(OTEL_EXPORTER_OTLP_ENDPOINT)%/v1/traces'
      $headers: [ ]
      $batchSize: 100
      $flushInterval: 5.0

  # Async execution time tracer
  Macpaw\SymfonyOtelBundle\Span\AsyncExecutionTimeSpanTracer:
    arguments:
      $asyncExporter: '@Macpaw\SymfonyOtelBundle\Service\AsyncTraceExporter'
      $propagator: '@OpenTelemetry\Context\Propagation\TextMapPropagatorInterface'
    tags:
      - { name: kernel.event_subscriber }
```

## Configuration

### Environment Variables

```bash
# OpenTelemetry endpoint
OTEL_EXPORTER_OTLP_ENDPOINT=http://localhost:4318

# Optional: Authentication headers
OTEL_EXPORTER_OTLP_HEADERS="Authorization=Bearer your-token"

# Batch configuration
OTEL_BATCH_SIZE=100
OTEL_FLUSH_INTERVAL=5.0
```

### Bundle Configuration

```yaml
# config/packages/symfony_otel.yaml
symfony_otel:
  tracer_name: 'my-app-tracer'
  service_name: 'my-symfony-app'
  span_tracers:
    - class: 'Macpaw\SymfonyOtelBundle\Span\AsyncExecutionTimeSpanTracer'
      tag: 'kernel.event_subscriber'
```

## Usage Examples

### 1. Basic Async Trace Sending

```php
<?php

use Macpaw\SymfonyOtelBundle\Service\AsyncTraceExporter;

// Create exporter
$exporter = new AsyncTraceExporter(
    'http://localhost:4318/v1/traces',
    ['Authorization' => 'Bearer your-token'],
    100, // batch size
    5.0  // flush interval
);

// Queue spans for async sending
$exporter->queueSpan([
    'traceId' => bin2hex(random_bytes(16)),
    'spanId' => bin2hex(random_bytes(8)),
    'name' => 'my-operation',
    'kind' => 1,
    'startTimeUnixNano' => (int) (microtime(true) * 1_000_000_000),
    'endTimeUnixNano' => (int) (microtime(true) * 1_000_000_000),
    'attributes' => [
        [
            'key' => 'http.method',
            'value' => ['stringValue' => 'GET']
        ]
    ],
    'events' => [],
    'status' => ['code' => 1, 'message' => 'Success']
]);

// Start event loop (in production, this would be managed by your application)
$exporter->getLoop()->run();
```

### 2. Integration with Symfony Events

```php
<?php

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Macpaw\SymfonyOtelBundle\Service\AsyncTraceExporter;

class MyAsyncTracer implements EventSubscriberInterface
{
    public function __construct(private AsyncTraceExporter $exporter) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        // Create span data
        $spanData = [
            'traceId' => bin2hex(random_bytes(16)),
            'spanId' => bin2hex(random_bytes(8)),
            'name' => 'http-request',
            'kind' => 1,
            'startTimeUnixNano' => (int) (microtime(true) * 1_000_000_000),
            'endTimeUnixNano' => (int) (microtime(true) * 1_000_000_000),
            'attributes' => [
                [
                    'key' => 'http.method',
                    'value' => ['stringValue' => $event->getRequest()->getMethod()]
                ],
                [
                    'key' => 'http.url',
                    'value' => ['stringValue' => $event->getRequest()->getUri()]
                ]
            ],
            'events' => [],
            'status' => ['code' => 1, 'message' => 'Success']
        ];

        // Queue for async sending (non-blocking)
        $this->exporter->queueSpan($spanData);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'kernel.request' => 'onKernelRequest',
        ];
    }
}
```

## Testing

### 1. Unit Tests

```bash
# Run existing tests
vendor/bin/phpunit

# Test with async functionality
vendor/bin/phpunit tests/Unit/Service/AsyncTraceExporterTest.php
```

### 2. Integration Testing

```bash
# Start OpenTelemetry collector
docker-compose up -d

# Run async trace demo
php examples/ReactPhpAsyncTracing.php

# Check traces in Grafana
open http://localhost:3000
```

## Performance Optimization

### 1. Batch Size Tuning

```php
// Small batch size (good for low-latency)
$exporter = new AsyncTraceExporter($endpoint, [], 10, 1.0);

// Large batch size (good for high-throughput)
$exporter = new AsyncTraceExporter($endpoint, [], 1000, 10.0);
```

### 2. Memory Management

```php
// Monitor memory usage
$exporter = new AsyncTraceExporter($endpoint, [], 100, 5.0);

// Add memory monitoring
$exporter->getLoop()->addPeriodicTimer(30.0, function () {
    $memory = memory_get_usage(true);
    echo "Memory usage: " . round($memory / 1024 / 1024, 2) . " MB\n";
});
```

### 3. Error Handling

```php
// Custom error handling
$exporter = new AsyncTraceExporter($endpoint, [], 100, 5.0);

// Handle errors gracefully
$exporter->sendSpansBatchAsync($spans)->then(
    function ($response) {
        // Success
    },
    function (\Exception $error) {
        // Log error but don't fail the application
        error_log("Trace sending failed: " . $error->getMessage());
    }
);
```

## Production Considerations

### 1. Resource Management

- Monitor memory usage and implement limits
- Set appropriate batch sizes based on your traffic
- Use connection pooling for high-volume scenarios

### 2. Error Handling

- Implement retry logic for failed requests
- Log errors without affecting application performance
- Consider circuit breaker pattern for failing endpoints

### 3. Monitoring

- Monitor async queue sizes
- Track successful vs failed trace exports
- Set up alerts for export failures

## Troubleshooting

### Common Issues

1. **Memory Leaks**: Ensure proper cleanup of pending spans
2. **Event Loop Blocking**: Avoid blocking operations in callbacks
3. **Connection Timeouts**: Configure appropriate timeout values
4. **Batch Size Too Large**: Reduce batch size if experiencing timeouts

### Debug Commands

```bash
# Check ReactPHP installation
php -m | grep -i react

# Test OpenTelemetry endpoint
curl -X POST http://localhost:4318/v1/traces \
  -H "Content-Type: application/json" \
  -d '{"resourceSpans":[]}'

# Monitor trace export
tail -f /var/log/otel-traces.log
```

This async implementation provides significant performance improvements for high-traffic applications while maintaining
compatibility with OpenTelemetry standards. 

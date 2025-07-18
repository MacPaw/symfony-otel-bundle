# Symfony OpenTelemetry client Bundle
This bundle provides configured official otel bundle for Symfony application under the hood.
In addition, it provides a general way to configure telemetry collection via configuration list of Kernel listeners.

## Setup bundle
Enable bundle in your Symfony application:
```php
return [
    Macpaw\SymfonyOtelBundle\SymfonyOtelBundle::class => ['all' => true],
    // ...
];
```

## Configuration understanding
This bundle is a decoration under [https://github.com/opentelemetry-php/contrib-sdk-bundle](https://github.com/opentelemetry-php/contrib-sdk-bundle) to simplify integration with the official bundle and provide generic kernel listeners for data tracing.
If you want to get more information about configuration, please refer to the official bundle documentation.

## Setup bundle

## Kernel event listeners
Example of kernel event listener implementation can be found in `Macpaw\SymfonyOtelBundle\Span\ExecutionTimeSpanTracer` class.
When specific listener need to be configured, you need to add it to `span_tracers` list in configuration after implementation.

### Example

1. Create a custom span tracer class:

    ```php
    namespace Macpaw\SymfonyOtelBundle\Span;

    use Symfony\Component\EventDispatcher\EventSubscriberInterface;
    use OpenTelemetry\API\Trace\Spane

    class CustomSpanTracer implements EventSubscriberInterface
    {
        public function onKernelRequest(RequestEvent $event): void
        {
            $span = Span::startSpan('custom_span');
            // Add custom tracing logic here
            $span->end();
        }
   
        public static function getSubscribedEvents(): array
        {
            return [
                KernelEvents::REQUEST => 'onKernelRequest',
            ];
        }
    }
    ```
   
2. Register the custom span tracer in the Symfony configuration:

    ```yaml
    # config/packages/symfony_otel.yaml
    symfony_otel:
        span_tracers:
            - App\Span\CustomSpanTracer
    ```

## Environment Variables

This bundle supports the following OpenTelemetry SDK environment variables for configuration:

- `OTEL_RESOURCE_ATTRIBUTES`: Key-value pairs to be used as resource attributes.
- `OTEL_SERVICE_NAME`: The name of the service.
- `OTEL_TRACES_EXPORTER`: The exporter to be used for traces.
- `OTEL_METRICS_EXPORTER`: The exporter to be used for metrics.
- `OTEL_LOGS_EXPORTER`: The exporter to be used for logs.
- `OTEL_EXPORTER_OTLP_ENDPOINT`: The endpoint for the OTLP exporter.
- `OTEL_EXPORTER_OTLP_HEADERS`: Headers to be sent with each OTLP request.
- `OTEL_EXPORTER_OTLP_TIMEOUT`: Timeout for OTLP requests.
- `OTEL_PROPAGATORS`: Propagators to be used for context propagation.
- `OTEL_TRACES_SAMPLER`: The sampler to be used for traces.
- `OTEL_TRACES_SAMPLER_ARG`: Arguments for the trace sampler.

For a complete list and detailed descriptions, please refer to the [OpenTelemetry SDK Environment Variables documentation](https://opentelemetry.io/docs/specs/otel/configuration/sdk-environment-variables/).

## Hooks in OpenTelemetry

A **hook** lets you attach custom logic before (`pre-hook`) or after (`post-hook`) the execution of any method without modifying its source code. Typical use cases:

* **Pre-hook**: start a span.
* **Post-hook**: record attributes, capture exceptions, and end the span.

### Example: Zero-Code Instrumentation on `DemoClass::run`

```php
use OpenTelemetry\Instrumentation;

// Register a hook for DemoClass::run()
Instrumentation::hook(
    class: DemoClass::class,
    function: 'run',
    pre: static function ($context, $args) {
        $tracer = \OpenTelemetry\API\Globals::tracerProvider()
            ->getTracer('demo');
        $span = $tracer->spanBuilder('demo.run')->startSpan();
        return ['context' => $context->withSpan($span)];
    },
    post: static function ($context, $args, $result, $exception) {
        $span = $context->getSpan();
        if ($exception instanceof \Throwable) {
            $span->recordException($exception);
            $span->setStatus(\OpenTelemetry\API\Trace\StatusCode::STATUS_ERROR);
        }
        $span->end();
    }
);
```

### Example: Hook on `PDO::query()`

```php
use OpenTelemetry\Instrumentation;

Instrumentation::hook(
    class: PDO::class,
    function: 'query',
    pre: static function ($context, $args) {
        $tracer = \OpenTelemetry\API\Globals::tracerProvider()
            ->getTracer('db');
        $span = $tracer->spanBuilder('db.query')->startSpan();
        $span->setAttribute('db.statement', $args[0]);
        return ['context' => $context->withSpan($span)];
    },
    post: static function ($context, $args, $result, $exception) {
        $span = $context->getSpan();
        if ($exception) {
            $span->recordException($exception);
            $span->setStatus(\OpenTelemetry\API\Trace\StatusCode::STATUS_ERROR);
        } else {
            $span->setAttribute('db.rows_returned', $result->rowCount());
        }
        $span->end();
    }
);
```

---

## Spans and Hierarchy

A **span** represents a single operation or step (e.g., an HTTP request, method call, or database query). It records:

* **Name** (e.g., `HTTP GET /users`).
* **Context**:

   * `trace_id`: shared by all spans in a trace.
   * `span_id`: unique to this span.
   * `parent_span_id`: refers to its parent span (absent for the root span).
* **Timestamps**: start and end times (to calculate duration).
* **Attributes**: key-value metadata (e.g., status codes, SQL statements).
* **Events**: time-stamped annotations (e.g., `cache.miss`).
* **Links**: non-hierarchical references to other spans (useful in batch/fork scenarios).
* **Status**: success or error code.

### Hierarchy

1. **Trace**: all spans share the same `trace_id`.
2. **Parent–Child**: each child span links to its parent via `parent_span_id`.
3. **Propagation**: across services, context is serialized into headers (`traceparent`/`tracestate`) and deserialized on the receiver.

#### Creating a Root Span

```php
use Macpaw\SymfonyOtelBundle\Service\TraceService;

class MyController
{
    public function __construct(private TraceService $traceService) {}
    
    public function myAction(): Response
    {
        $tracer = $this->traceService->getTracer('my-app');
        $rootSpan = $tracer->spanBuilder('root.operation')->startSpan();
        $scope = $rootSpan->activate();
        
        try {
            // ... your logic ...
        } finally {
            $scope->detach();
            $rootSpan->end();
        }
        
        return new Response('OK');
    }
}
```

#### Creating a Child Span

```php
use Macpaw\SymfonyOtelBundle\Service\TraceService;

class MyController
{
    public function __construct(private TraceService $traceService) {}
    
    public function myAction(): Response
    {
        $tracer = $this->traceService->getTracer('my-app');
        $rootSpan = $tracer->spanBuilder('root.operation')->startSpan();
        $rootScope = $rootSpan->activate();
        
        try {
            $childSpan = $tracer->spanBuilder('child.operation')
                ->setParent($rootSpan->getContext())
                ->startSpan();
            $childScope = $childSpan->activate();
            
            try {
                // ... child logic ...
            } finally {
                $childScope->detach();
                $childSpan->end();
            }
        } finally {
            $rootScope->detach();
            $rootSpan->end();
        }
        
        return new Response('OK');
    }
}
```

---

## SpanKind

**SpanKind** classifies the relationship of a span to remote calls and messaging.

| Kind     | Description                                                    |
| -------- | -------------------------------------------------------------- |
| INTERNAL | Local operations within the application.                       |
| SERVER   | Handling of incoming requests (HTTP, gRPC) on the server side. |
| CLIENT   | Outgoing requests to remote services (HTTP, RPC).              |
| PRODUCER | Initiating messaging operations (e.g., sending to a queue).    |
| CONSUMER | Processing messages consumed from a broker or queue.           |

**Best practices**:

* Use one kind per span.
* Create a `CLIENT` span before injecting context into headers.
* On the client, start a CLIENT span and inject the trace headers (traceparent/tracestate) into the outgoing request. On the backend, the OpenTelemetry SDK automatically extracts this context and creates a SERVER span that continues the same trace. (traceparent/tracestate) into outgoing request headers; on the backend, extract and link that context to a SERVER span so the downstream flow matches the original trace.
* In messaging flows, pair `PRODUCER` and `CONSUMER` spans.

---

## Custom Attributes

### On the SpanBuilder

Attach known attributes before starting the span:

```php
use Macpaw\SymfonyOtelBundle\Service\TraceService;

class MyController
{
    public function __construct(private TraceService $traceService) {}
    
    public function processOrder(): Response
    {
        $tracer = $this->traceService->getTracer('my-app');
        $span = $tracer
            ->spanBuilder('order.process')
            ->setAttribute('order.id', '12345')
            ->setAttribute('user.authenticated', true)
            ->setAttribute('retry.count', 3)
            ->startSpan();
        $scope = $span->activate();
        
        try {
            // ...
        } finally {
            $scope->detach();
            $span->end();
        }
        
        return new Response('OK');
    }
}
```

### On an Active Span

Use when attributes depend on runtime conditions:

```php
$tracer = $this->traceService->getTracer('my-app');
$span = $tracer->spanBuilder('db.query')->startSpan();
$scope = $span->activate();

try {
    if ($span->isRecording()) {
        $span->setAttribute('db.statement', $sql);
        $span->setAttributes([
            'db.system'       => 'mysql',
            'db.user'         => 'readonly',
            'db.rows_returned'=> 42,
        ]);
    }
    // ...
} finally {
    $scope->detach();
    $span->end();
}
```

**Tip**: Always check `isRecording()` before setting attributes to avoid overhead when sampling drops the span.

---

## Events

An **event** is a time-stamped annotation within a span, optionally with attributes.

* Use for recording key moments (`cache.miss`, algorithm steps).
* Log exceptions as events.
* Capture business events (`user.signup`).

```php
$span->addEvent('cache.miss');
$span->addEvent(
    'db.query.executed',
    ['db.statement' => $sql, 'rows' => $count]
);
```

With a custom timestamp:

```php
$timestamp = new \DateTimeImmutable(sprintf('%.6F', microtime(true) - 0.5));
$span->addEvent('message.sent', ['id' => 'abc123'], $timestamp);
```

Exception logging:

```php
try {
    // ...
} catch (\Throwable $e) {
    if ($span->isRecording()) {
        $span->addEvent('exception', [
            'type'    => get_class($e),
            'message' => $e->getMessage(),
            'stack'   => $e->getTraceAsString(),
        ]);
    }
    throw $e;
}
```

---

## Context

A **Context** is an immutable carrier for:

* **SpanContext** (trace identifiers and flags)
* **Active span**
* **Baggage** (custom key-value pairs propagated across calls)

### Storing and Retrieving Context

```php
use OpenTelemetry\Context\Context;

$tracer = $this->traceService->getTracer('my-app');
$rootSpan = $tracer->spanBuilder('root')->startSpan();
$context = $rootSpan->storeInContext(Context::getCurrent());
```

### Propagation via HTTP (W3C)

```php
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;

$propagator = TraceContextPropagator::getInstance();
$headers = [];
$propagator->inject(
    $context,
    $headers,
    fn(&$carrier, $key, $value) => $carrier[$key] = $value
);
// Send $headers with HTTP request

// On the server side:
$extracted = $propagator->extract($_SERVER, fn($carrier, $key) => $carrier[$key] ?? null);
$tracer = $this->traceService->getTracer('my-app');
$serverSpan = $tracer->spanBuilder('http.server')->setParent($extracted)->startSpan();
```

---

## Scope vs. Context

* **Context** holds tracing metadata but does not control its activation.
* **Scope** is a handle returned when you activate a context, making it the current active context in the thread.

```php
$tracer = $this->traceService->getTracer('my-app');
$rootSpan = $tracer->spanBuilder('root')->startSpan();
$scope = $rootSpan->activate();

try {
    // Within this scope, new spans inherit from $rootSpan
    $child = $tracer->spanBuilder('child')->startSpan();
    $childScope = $child->activate();
    
    try {
        // child operations
    } finally {
        $childScope->detach();
        $child->end();
    }
} finally {
    $scope->detach(); // restore previous context
    $rootSpan->end();
}
```

---

## Supported Transports

* **OTLP gRPC** (port 4317)
* **OTLP HTTP** (port 4318, Protobuf or JSON)
* **Jaeger** (Thrift/HTTP 14268 or gRPC 14250)
* **Zipkin** (HTTP/JSON 9411)
* **Prometheus** (`/metrics` endpoint)
* **StatsD** (UDP 8125)
* **Kafka** (publish Protobuf/JSON to topics)
* **File** (write to local file)
* **Custom Exporters** (implement `ExporterInterface`)

---

## Overall Architecture & Custom Instrumentation

1. **TracerProvider**: central factory for tracers, configured with SpanProcessors and Exporters.
2. **SpanProcessor**: `SimpleSpanProcessor` (immediate export) or `BatchSpanProcessor`.
3. **Exporter**: e.g., OTLP exporter sending spans to a collector.
4. **TraceService**: helper service for retrieving tracers in your code.

### Creating and Exporting a Custom Span

```php
use Macpaw\SymfonyOtelBundle\Service\TraceService;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use Symfony\Component\HttpFoundation\JsonResponse;

class OrderController
{
    public function __construct(private TraceService $traceService) {}

    public function process(): JsonResponse
    {
        $tracer = $this->traceService->getTracer('app.orders');
        $span = $tracer->spanBuilder('order.process')
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->setAttribute('order.id', 123)
            ->startSpan();
        $scope = $span->activate();

        try {
            $span->addEvent('payment.started');
            // ... business logic ...
            $span->addEvent('payment.completed');
        } catch (\Throwable $e) {
            if ($span->isRecording()) {
                $span->recordException($e);
                $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            }
            throw $e;
        } finally {
            $scope->detach();
            $span->end(); // BatchSpanProcessor queues it for export
        }

        return new JsonResponse(['status' => 'ok']);
    }
}
```

### Using TraceService in Your Services

The `TraceService` provides a simple way to get tracers in your application:

```php
<?php

namespace App\Service;

use Macpaw\SymfonyOtelBundle\Service\TraceService;

class MyBusinessService
{
    public function __construct(private TraceService $traceService) {}
    
    public function processData(array $data): void
    {
        $tracer = $this->traceService->getTracer('my-business-service');
        $span = $tracer->spanBuilder('process_data')
            ->setAttribute('data.count', count($data))
            ->startSpan();
        $scope = $span->activate();
        
        try {
            // Your business logic here
            foreach ($data as $item) {
                $this->processItem($item);
            }
        } finally {
            $scope->detach();
            $span->end();
        }
    }
    
    private function processItem($item): void
    {
        $tracer = $this->traceService->getTracer('my-business-service');
        $span = $tracer->spanBuilder('process_item')
            ->setAttribute('item.id', $item['id'] ?? 'unknown')
            ->startSpan();
        $scope = $span->activate();
        
        try {
            // Process individual item
        } finally {
            $scope->detach();
            $span->end();
        }
    }
}
```

This setup ensures proper span creation and context propagation throughout your application.

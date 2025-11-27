# Instrumentation Guide

## Overview

This guide explains how to use and extend the instrumentation system in the Symfony OpenTelemetry Bundle, including built-in instrumentations and creating custom ones.

## Built-in Instrumentations

### RequestExecutionTimeInstrumentation

Automatically tracks HTTP request execution time and creates spans for each request.

**Features:**
- Automatic span creation for HTTP requests
- Context propagation from incoming headers
- Request metadata attachment
- Execution time measurement

**Configuration:**
```yaml
otel_bundle:
    instrumentations:
        - 'Macpaw\SymfonyOtelBundle\Instrumentation\RequestExecutionTimeInstrumentation'
```

**Generated Spans:**
- Span name: `request.execution_time`
- Span kind: `SERVER`
- Attributes: HTTP method, route, status code, execution time

### ClassHookInstrumentation

Provides a framework for instrumenting specific class methods using OpenTelemetry hooks.

**Features:**
- Method-level instrumentation
- Middleware support for custom logic
- Timing interface for execution measurement
- Automatic span lifecycle management

**Configuration:**
```yaml
services:
    example.instrumentation:
        class: Macpaw\SymfonyOtelBundle\Instrumentation\ClassHookInstrumentation
        arguments:
            $className: 'App\Service\ExampleService'
            $methodName: 'process'
            $spanMiddlewares:
                - '@App\Middleware\CustomSpanMiddleware'
```

## Custom Instrumentation

### Creating Custom Instrumentations

#### 1. Extend AbstractInstrumentation

```php
<?php

declare(strict_types=1);

namespace App\Instrumentation;

use Macpaw\SymfonyOtelBundle\Instrumentation\AbstractInstrumentation;
use Macpaw\SymfonyOtelBundle\Registry\InstrumentationRegistry;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;

final class CustomDatabaseInstrumentation extends AbstractInstrumentation
{
    public function __construct(
        InstrumentationRegistry $instrumentationRegistry,
        TracerInterface $tracer,
        TextMapPropagatorInterface $propagator,
    ) {
        parent::__construct($instrumentationRegistry, $tracer, $propagator);
    }

    public function getName(): string
    {
        return 'custom.database.operation';
    }

    protected function buildSpan(SpanBuilderInterface $spanBuilder): SpanInterface
    {
        return $spanBuilder
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setAttribute('db.system', 'mysql')
            ->setAttribute('db.operation', 'query')
            ->startSpan();
    }

    public function pre(): void
    {
        $this->initSpan(null);
        $this->span->addEvent('Database operation started');
    }

    public function post(): void
    {
        $this->span->addEvent('Database operation completed');
        $this->closeSpan($this->span);
    }
}
```

#### 2. Register in Configuration

```yaml
otel_bundle:
    instrumentations:
        - 'App\Instrumentation\CustomDatabaseInstrumentation'
```

### Hook Instrumentation

For instrumenting existing classes without modifying their source code:

```php
<?php

declare(strict_types=1);

namespace App\Instrumentation;

use Macpaw\SymfonyOtelBundle\Instrumentation\AbstractHookInstrumentation;
use Macpaw\SymfonyOtelBundle\Registry\InstrumentationRegistry;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;
use PDO;

final class PdoHookInstrumentation extends AbstractHookInstrumentation
{
    public function __construct(
        InstrumentationRegistry $instrumentationRegistry,
        TracerInterface $tracer,
        TextMapPropagatorInterface $propagator,
    ) {
        parent::__construct($instrumentationRegistry, $tracer, $propagator);
    }

    public function getName(): string
    {
        return 'pdo.query';
    }

    public function getClass(): string
    {
        return PDO::class;
    }

    public function getMethod(): string
    {
        return 'query';
    }

    protected function buildSpan(SpanBuilderInterface $spanBuilder): SpanInterface
    {
        return $spanBuilder
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setAttribute('db.system', 'sql')
            ->setAttribute('db.operation', 'query')
            ->startSpan();
    }

    public function pre(): void
    {
        $this->initSpan(null);
        // Access method arguments through context if needed
        $this->span->addEvent('PDO query started');
    }

    public function post(): void
    {
        $this->span->addEvent('PDO query completed');
        $this->closeSpan($this->span);
    }
}
```

## Middleware System

### Creating Custom Middleware

Middleware allows you to add custom logic to instrumentations:

```php
<?php

declare(strict_types=1);

namespace App\Middleware;

use Macpaw\SymfonyOtelBundle\Instrumentation\HookInstrumentationInterface;
use Macpaw\SymfonyOtelBundle\Instrumentation\TimingInterface;
use Macpaw\SymfonyOtelBundle\Middleware\ClassHookInstrumentationSpanMiddlewareInterface;
use OpenTelemetry\API\Common\Time\ClockInterface;
use OpenTelemetry\API\Trace\SpanInterface;

final class CustomSpanMiddleware implements ClassHookInstrumentationSpanMiddlewareInterface
{
    public function __construct(private readonly ClockInterface $clock)
    {
    }

    public function pre(SpanInterface $span, HookInstrumentationInterface&TimingInterface $instrumentation): void
    {
        $span->setAttribute('custom.class', $instrumentation->getClass());
        $span->setAttribute('custom.method', $instrumentation->getMethod());
        $span->setAttribute('custom.start_time', $instrumentation->getStartTime());
    }

    public function post(SpanInterface $span, HookInstrumentationInterface&TimingInterface $instrumentation): void
    {
        $executionTime = $instrumentation->getExecutionTime();
        $span->setAttribute('custom.execution_time_ns', $executionTime);
        $span->setAttribute('custom.end_time', $instrumentation->getEndTime());
    }
}
```

### Using Middleware with Instrumentations

```yaml
services:
    custom.instrumentation:
        class: Macpaw\SymfonyOtelBundle\Instrumentation\ClassHookInstrumentation
        arguments:
            $className: 'App\Service\CustomService'
            $methodName: 'process'
            $spanMiddlewares:
                - '@App\Middleware\CustomSpanMiddleware'
                - '@App\Middleware\LoggingSpanMiddleware'
```

## Best Practices

### Span Naming Conventions

Follow OpenTelemetry semantic conventions for span names:

```php
// Good examples
'http.request'
'database.query'
'cache.get'
'user.authentication'
'payment.processing'

// Bad examples
'HTTP_REQUEST'
'DatabaseQuery'
'cache_get'
'userAuth'
```

### Span Attributes

Use standard OpenTelemetry attributes when possible:

```php
// HTTP attributes
$span->setAttribute('http.method', 'GET');
$span->setAttribute('http.route', '/api/users');
$span->setAttribute('http.status_code', 200);

// Database attributes
$span->setAttribute('db.system', 'mysql');
$span->setAttribute('db.operation', 'SELECT');
$span->setAttribute('db.table', 'users');

// Custom attributes
$span->setAttribute('user.id', $userId);
$span->setAttribute('operation.type', 'background_job');
```

### Error Handling

Always handle exceptions in your instrumentations:

```php
public function post(): void
{
    try {
        // Your post-execution logic
        $this->span->setStatus(StatusCode::STATUS_OK);
    } catch (Exception $e) {
        $this->span->recordException($e);
        $this->span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
    } finally {
        $this->closeSpan($this->span);
    }
}
```

### Performance Considerations

1. **Minimize span creation overhead** - Don't create spans for trivial operations
2. **Use appropriate sampling** - Configure sampling based on your needs
3. **Batch operations** - Group related operations under a single span
4. **Avoid expensive operations** in span creation - Keep it lightweight

## Examples

### Database Instrumentation

```php
final class DatabaseInstrumentation extends AbstractHookInstrumentation
{
    public function getClass(): string
    {
        return EntityManager::class;
    }

    public function getMethod(): string
    {
        return 'persist';
    }

    protected function buildSpan(SpanBuilderInterface $spanBuilder): SpanInterface
    {
        return $spanBuilder
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setAttribute('db.system', 'doctrine')
            ->setAttribute('db.operation', 'persist')
            ->startSpan();
    }

    public function pre(): void
    {
        $this->initSpan(null);
        $this->span->addEvent('Entity persistence started');
    }

    public function post(): void
    {
        $this->span->addEvent('Entity persistence completed');
        $this->closeSpan($this->span);
    }
}
```

### HTTP Client Instrumentation

```php
final class HttpClientInstrumentation extends AbstractHookInstrumentation
{
    public function getClass(): string
    {
        return HttpClientInterface::class;
    }

    public function getMethod(): string
    {
        return 'request';
    }

    protected function buildSpan(SpanBuilderInterface $spanBuilder): SpanInterface
    {
        return $spanBuilder
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setAttribute('http.method', 'GET')
            ->setAttribute('http.url', $this->getUrl())
            ->startSpan();
    }

    public function pre(): void
    {
        $this->initSpan(null);
        $this->span->addEvent('HTTP request started');
    }

    public function post(): void
    {
        $this->span->addEvent('HTTP request completed');
        $this->closeSpan($this->span);
    }
}
```

## Reference Links

- [OpenTelemetry Semantic Conventions](https://opentelemetry.io/docs/specs/semconv/)
- [OpenTelemetry PHP SDK](https://github.com/open-telemetry/opentelemetry-php)
- [Symfony Event System](https://symfony.com/doc/current/event_dispatcher.html) 

## Custom Instrumentations — build business spans fast

Make business tracing delightful with two high‑level DX features designed to reduce boilerplate and enforce consistent
span semantics.

### Attributes / Annotations

Use a PHP attribute to declare a span around a handler/controller method. The bundle auto-discovers and wires a listener
so you don’t have to manage span lifecycle manually.

```php
<?php

use Macpaw\SymfonyOtelBundle\Attribute\TraceSpan;

final class CheckoutHandler
{
    #[TraceSpan('Checkout')]
    public function __invoke(PlaceOrderCommand $command): void
    {
        // ... business logic
        // you may still add attributes/events as needed (see tips below)
    }
}
```

Key points:

- Zero boilerplate: attribute + autoconfigured listener automatically starts/ends spans
- Parent context is inferred from the current request/consumer context
- Add attributes/events inside the method as usual
- Preview note: if your installed version doesn’t yet include the `TraceSpan` attribute, use the manual span or the
  `inSpan()` helper below as an alternative

Tips:

- Name spans after business concepts (e.g., `Checkout`, `CalculatePrice`, `ApplyCoupon`)
- Add compact attributes (e.g., `order.id`, `cart.items_count`), avoid large strings
- Follow OpenTelemetry semantic conventions where applicable

### Business spans helper: inSpan()

Wrap any closure in a span with automatic lifecycle and error handling.

```php
<?php

use Macpaw\SymfonyOtelBundle\Tracing\OtelFacade; // example façade
use OpenTelemetry\API\Trace\SpanInterface; // for context typing if needed

// $otel is a small tracing façade/service provided by the bundle
$result = $otel->inSpan('CalculatePrice', function ($ctx) use ($order) {
    // $ctx can expose helper methods to interact with the active span
    // e.g., $ctx->setAttribute('order.items', count($order->items()));

    // ... business logic
    return $calculator->total($order);
});
```

Semantics:

- Automatic `end()` even if an exception is thrown
- Exceptions set span status to `ERROR` and are rethrown
- The closure’s return value is returned from `inSpan()`
- Inside the closure you can set attributes and add events without manual span lifecycle

Suggested usage patterns:

- Controllers and handlers where you want a single business span per action
- Domain services for key business operations (pricing, allocation, recommendation)
- Background workers / Messenger handlers to wrap message processing

#### Manual alternative (when attributes/helper are unavailable)

```php
<?php

use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\StatusCode;

$tracer = $traceService->getTracer();
$span = $tracer->spanBuilder('CalculatePrice')->startSpan();
$scope = $span->activate();

try {
    $span->setAttribute('order.items', count($order->items()));
    $result = $calculator->total($order);
} catch (\Throwable $e) {
    $span->recordException($e);
    $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
    throw $e;
} finally {
    $scope->detach();
    $span->end();
}
```

### Best practices for naming and attributes

- Prefer business names over technical ones: `Checkout`, not `handle`
- Keep attributes small and typed (ints/bools), avoid large strings or arrays
- Use semantic conventions where they fit (HTTP, DB, messaging)
- Sample wisely in production to reduce overhead

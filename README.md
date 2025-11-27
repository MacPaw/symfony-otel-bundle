# Symfony OpenTelemetry Bundle

A comprehensive OpenTelemetry integration bundle for Symfony applications that provides automatic instrumentation, custom span creation, and distributed tracing capabilities.

## Features

- **Automatic Instrumentation** - Built-in instrumentations for HTTP requests, database operations, and more
- **Custom Instrumentations** - Easy-to-use framework for creating custom instrumentations
- **Middleware System** - Extensible middleware system for span customization
- **Exception Handling** - Automatic span cleanup and error recording
- **Logging & Metrics Bridge** - Monolog trace context processor + optional request counters
- **Docker Support** - Complete development environment with Tempo and Grafana
- **Performance Optimized** - Support for both HTTP and gRPC transport protocols
- **OpenTelemetry Compliant** - Follows OpenTelemetry specifications and semantic conventions

## Quick Overview

This bundle provides a configured OpenTelemetry integration for Symfony applications, offering:
- Automatic telemetry collection via kernel event listeners
- Custom instrumentation framework for business logic
- Built-in support for distributed tracing
- Complete testing and development environment

## Bundle Overview

This bundle is a wrapper around the [official OpenTelemetry PHP SDK bundle](https://github.com/opentelemetry-php/contrib-sdk-bundle) that simplifies integration and provides additional instrumentation capabilities for Symfony applications.

**Key characteristics:**
- **Transport-agnostic** - Uses standard OpenTelemetry SDK environment variables for transport configuration
- **Framework-focused** - Provides Symfony-specific instrumentation and middleware
- **Extensible** - Easy to add custom instrumentations and span processors

## Built-in Features

- **Request Execution Time Tracking** - Automatic HTTP request timing
- **Exception Handling** - Automatic span cleanup and error recording
- **Custom Instrumentations** - Framework for creating custom telemetry collection (Attributes + inSpan helper)

For detailed instrumentation guide, see [Instrumentation Guide](docs/instrumentation.md).

## Custom Instrumentations — build business spans fast

Make business tracing delightful with two high‑level DX features:

#### 1) Attributes / Annotations

```php
use Macpaw\SymfonyOtelBundle\Attribute\TraceSpan;

final class CheckoutHandler
{
    #[TraceSpan('Checkout')]
    public function __invoke(PlaceOrderCommand $command): void
    {
        // ... your business logic
        // inside the method you can still add attributes/events as needed
        // $ctx->setAttribute('order.id', $command->orderId());
    }
}
```

- Zero boilerplate: attribute + autoconfigured listener starts/ends spans for you
- Parent context is inferred from the current request/consumer
- Add attributes/events inside as usual
- Note: If your version doesn’t expose the `TraceSpan` attribute yet, see the Instrumentation Guide for the manual
  approach

#### 2) Simple interface for business spans

```php
// $otel is a small tracing façade (e.g., provided by this bundle)
$result = $otel->inSpan('CalculatePrice', function (SpanContext $ctx) use ($order) {
    $ctx->setAttribute('order.items', count($order->items()));
    // business logic
    return $calculator->total($order);
});
```

- Automatic end() even on exceptions
- Exceptions set span status to ERROR and are rethrown
- Closure’s return value is returned by `inSpan()`
- Access span context (`setAttribute()`, `addEvent()`) without manual lifecycle

See more patterns and best practices in
the [Instrumentation Guide](docs/instrumentation.md#custom-instrumentations-—-build-business-spans-fast).

## Logging & Metrics Bridge

Two easy wins to correlate logs with traces and expose basic HTTP counters.

### 1) Monolog processor for trace context

When enabled (default), the bundle registers a Monolog processor that injects the current `trace_id` and `span_id` into
every log record’s context. This makes log–trace correlation work in most backends instantly.

Example log context (JSON):

```json
{
    "message": "Order created",
    "context": {
        "trace_id": "4bf92f3577b34da6a3ce929d0e0e4736",
        "span_id": "00f067aa0ba902b7",
        "trace_flags": "01"
    }
}
```

Configuration (optional):

```yaml
# config/packages/otel_bundle.yaml
otel_bundle:
  logging:
    enable_trace_processor: true
    log_keys:
      trace_id: trace_id
      span_id: span_id
      trace_flags: trace_flags
```

- Uses OpenTelemetry’s current context (`Span::getCurrent()`)
- No overhead when there is no active span; processor is a no‑op

### 2) Cheap HTTP request counters

Optionally, enable a lightweight middleware that increments counters for:

- Requests per route/method
- Responses grouped by status code family (1xx/2xx/3xx/4xx/5xx)

Backends:

- `otel` (default) — Uses the OpenTelemetry Metrics API counters if available
- `event` — Fallback: adds tiny span events if metrics are not configured

Enable in config:

```yaml
# config/packages/otel_bundle.yaml
otel_bundle:
  metrics:
    request_counters:
      enabled: true
      backend: otel # or 'event'
```

Counters created when using `otel` backend:

- `http.server.request.count{http.route, http.request.method}`
- `http.server.response.family.count{http.status_family}`

If metrics are not available, the subscriber falls back to span events named `request.count` and `response.family.count`
with the same labels.

## Observability & OpenTelemetry Semantics

This bundle aligns with OpenTelemetry Semantic Conventions and uses constants from `open-telemetry/sem-conv` wherever
available. This reduces typos, keeps attribute names consistent with the ecosystem, and eases future upgrades when the
spec changes.

Emitted attributes (selected):

- HTTP request root span (server):
    - `http.request.method`, `http.route`, `http.response.status_code`
    - `url.scheme`, `server.address`
    - Symfony-specific extras: `http.request_id` (custom), `http.route_name` (custom)
    - Controller attribution: `code.namespace`, `code.function`
- Business spans created via attributes/hooks:
    - `code.namespace`, `code.function` (class + method)
    - Any custom attributes declared on `#[TraceSpan(..., attributes: [...])]`
- Request counters (when enabled):
    - Metrics (`otel` backend): `http.server.request.count{http.route,http.request.method}` and
      `http.server.response.family.count{http.status_family}`
    - Fallback `event` backend: span events `request.count` and `response.family.count` carrying the same labels

Non-standard attributes used by this bundle (stable and documented):

- `http.request_id` — request correlation id propagated via `X-Request-Id`
- `http.route_name` — Symfony route name (e.g., `api_test`)
- `request.exec_time_ns` — compact numeric execution time for the request instrumentation

See detailed tables and examples in the Instrumentation Guide.

## Documentation & Adoption

- Troubleshooting: symptom → cause → fix for common issues like no traces in Grafana, missing gRPC/protobuf, wrong
  collector endpoint, CLI traces not appearing. See docs/troubleshooting.md
- Migration: guidance to move from the plain OpenTelemetry Symfony SDK bundle, with config mapping and rollout notes.
  See docs/migration.md
- Symfony Flex recipe: what gets installed automatically (config, health route, .env hints) and how to publish/override.
  See docs/recipe.md
- Ready-made configuration snippets for typical setups (copy-paste): Local dev with docker-compose + Tempo, Kubernetes +
  collector sidecar, monolith with multiple apps. See docs/snippets.md
- Ready-made Grafana dashboard: import docs/grafana/symfony-otel-dashboard.json into Grafana (Dashboards → Import),
  select your Tempo data source. See docs/docker.md#import-the-ready-made-grafana-dashboard

## Environment Variables

The bundle supports all standard OpenTelemetry SDK environment variables. For complete configuration reference, see [Configuration Guide](docs/configuration.md).

**Essential variables:**
- `OTEL_SERVICE_NAME` - Your service name
- `OTEL_TRACER_NAME` - Tracer name
- `OTEL_EXPORTER_OTLP_ENDPOINT` - Collector endpoint
- `OTEL_EXPORTER_OTLP_PROTOCOL` - Transport protocol (grpc/http/protobuf)

### Transport Configuration

**Important:** This bundle is **transport-agnostic** — it relies on standard OpenTelemetry SDK environment variables and
preserves the `BatchSpanProcessor` (BSP) defaults for queued, asynchronous export.

**Recommended for production (gRPC + BSP):**
```bash
# Install gRPC support
composer require open-telemetry/transport-grpc
pecl install grpc # may take time to compile (CI can cache layers)

# Configure gRPC endpoint (4317) and BSP tuning
export OTEL_TRACES_EXPORTER=otlp
export OTEL_EXPORTER_OTLP_PROTOCOL=grpc
export OTEL_EXPORTER_OTLP_ENDPOINT=http://collector:4317
export OTEL_EXPORTER_OTLP_TIMEOUT=1000    # ms
# BatchSpanProcessor (queueing + async export)
export OTEL_BSP_SCHEDULE_DELAY=200        # ms
export OTEL_BSP_MAX_EXPORT_BATCH_SIZE=256
export OTEL_BSP_MAX_QUEUE_SIZE=2048
```

If gRPC is unavailable, switch to HTTP/protobuf + gzip:

```bash
export OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf
export OTEL_EXPORTER_OTLP_ENDPOINT=http://collector:4318
export OTEL_EXPORTER_OTLP_COMPRESSION=gzip
```

Per‑request flush/teardown warning:

- Avoid calling `shutdown()` at request end — it tears down processors/exporters and disables BSP benefits.
- The bundle exposes config switches:
    - `otel_bundle.force_flush_on_terminate` (default: false) — whether to call a non-destructive flush at request end.
    - `otel_bundle.force_flush_timeout_ms` (default: 100) — timeout in milliseconds for `forceFlush()` when enabled.
      Leave flushing OFF in web requests so BSP can export asynchronously; consider enabling only for CLI or short‑lived
      processes.

**Transport protocols supported:**

- `grpc` — High performance, recommended for production
- `http/protobuf` — Standard HTTP with protobuf encoding
- `http/json` — HTTP with JSON encoding (slower)

See the [official OpenTelemetry PHP Exporters docs](https://opentelemetry.io/docs/languages/php/exporters/) for complete
transport options. For Docker setup and env examples, see [Docker Development Guide](docs/docker.md).


## Documentation

- [Installation Guide](docs/installation.md) - Complete installation and setup instructions
- [Configuration Reference](docs/configuration.md) - Bundle configuration and environment variables
- [Instrumentation Guide](docs/instrumentation.md) - Built-in instrumentations and custom development
- [Docker Development](docs/docker.md) - Local development environment setup
- [Testing Guide](docs/testing.md) - Testing, trace visualization, and troubleshooting

- [OpenTelemetry Basics](docs/otel_basics.md) - OpenTelemetry concepts and fundamentals
- [Contributing Guide](CONTRIBUTING.md) - How to contribute to the project

## Quick Start

1. **Install the bundle (with Symfony Flex recipe):**

```bash
composer require macpaw/symfony-otel-bundle
```

When the Flex recipe is enabled (via recipes-contrib), installation will automatically add:

- `config/packages/otel_bundle.yaml` with sane defaults (BSP async export preserved)
- `config/routes/otel_health.yaml` mapping `/_otel/health` to a built-in controller
- Commented `OTEL_*` variables appended to your `.env`

See details in the new guide: docs/recipe.md

2. **Enable in your application:**
   ```php
   // config/bundles.php
   return [
       Macpaw\SymfonyOtelBundle\SymfonyOtelBundle::class => ['all' => true],
   ];
   ```

3. **Configure environment variables:**
   ```bash
   OTEL_SERVICE_NAME=your-service-name
   OTEL_TRACER_NAME=your-tracer-name
   OTEL_EXPORTER_OTLP_ENDPOINT=http://localhost:4318
   ```

4. **Start testing:**
   ```bash
   make up
   open http://localhost:8080
   ```

## Usage

For detailed usage instructions, see [Testing Guide](docs/testing.md).

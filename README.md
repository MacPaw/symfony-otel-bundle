# Symfony OpenTelemetry Bundle

A comprehensive OpenTelemetry integration bundle for Symfony applications that provides automatic instrumentation, custom span creation, and distributed tracing capabilities.

## Features

- **Automatic Instrumentation** - Built-in instrumentations for HTTP requests, database operations, and more
- **Custom Instrumentations** - Easy-to-use framework for creating custom instrumentations
- **Middleware System** - Extensible middleware system for span customization
- **Exception Handling** - Automatic span cleanup and error recording
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

1. **Install the bundle:**
   ```bash
   composer require macpaw/symfony-otel-bundle
   ```

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

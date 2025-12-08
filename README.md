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
- **Custom Instrumentations** - Framework for creating custom telemetry collection

For detailed instrumentation guide, see [Instrumentation Guide](docs/instrumentation.md).



## Environment Variables

The bundle supports all standard OpenTelemetry SDK environment variables. For complete configuration reference, see [Configuration Guide](docs/configuration.md).

**Essential variables:**
- `OTEL_SERVICE_NAME` - Your service name
- `OTEL_TRACER_NAME` - Tracer name
- `OTEL_EXPORTER_OTLP_ENDPOINT` - Collector endpoint
- `OTEL_EXPORTER_OTLP_PROTOCOL` - Transport protocol (grpc/http/protobuf)

### Transport Configuration

**Important:** This bundle is **transport-agnostic** - it doesn't handle transport configuration directly. All transport settings are managed through standard OpenTelemetry SDK environment variables.

**Recommended for production:**
```bash
# Install gRPC support
composer require open-telemetry/transport-grpc
pecl install grpc # may take a time to compile - 30-40 minutes

# Configure gRPC endpoint
OTEL_EXPORTER_OTLP_ENDPOINT=http://collector:4317
OTEL_EXPORTER_OTLP_PROTOCOL=grpc
```

**Default HTTP endpoint:** `http://collector:4318`

**Transport protocols supported:**
- `grpc` - High performance, recommended for production
- `http/protobuf` - Standard HTTP with protobuf encoding
- `http/json` - HTTP with JSON encoding (slower)

**Note:** Our bundle supports all transport protocols supported by the OpenTelemetry PHP SDK since we don't decorate the transport layer. For complete transport configuration options, see the [official OpenTelemetry PHP Exporters documentation](https://opentelemetry.io/docs/languages/php/exporters/).

For detailed Docker setup and development environment configuration, see [Docker Development Guide](docs/docker.md).


## Documentation

- [Installation Guide](docs/installation.md) - Complete installation and setup instructions
- [Configuration Reference](docs/configuration.md) - Bundle configuration and environment variables
- [Instrumentation Guide](docs/instrumentation.md) - Built-in instrumentations and custom development
- [Docker Development](docs/docker.md) - Local development environment setup
- [Testing Guide](docs/testing.md) - Testing, trace visualization, and troubleshooting
- [Load Testing Guide](loadTesting/README.md) - k6 load testing for performance validation

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

## Load Testing

The bundle includes comprehensive load testing capabilities using k6. The k6 runner is built as a Go-based image (no Node.js required) from `docker/k6-go/Dockerfile`:

```bash
# Quick smoke test
make k6-smoke

# Run all load tests
make k6-all

# Stress test (31 minutes)
make k6-stress
```

Notes:
- The `k6` service is gated behind the `loadtest` compose profile. You can run tests with: `docker-compose --profile loadtest run k6 run /scripts/smoke-test.js`.
- Dockerfiles are consolidated under the `docker/` directory, e.g. `docker/php.grpc.Dockerfile` for the PHP app and `docker/k6-go/Dockerfile` for the k6 runner.

See [Load Testing Guide](loadTesting/README.md) for detailed documentation on all available tests and usage options.

## Usage

For detailed usage instructions, see [Testing Guide](docs/testing.md).

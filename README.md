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

### Exception Handling

The bundle includes automatic exception handling via `ExceptionHandlingEventSubscriber` that:

- **Automatically closes spans and scopes** when exceptions occur
- **Records exceptions** in all active spans with error status
- **Adds error attributes** for better debugging
- **Prevents memory leaks** by proper cleanup
- **Ensures trace export** even during exceptions

For detailed documentation, see [Exception Handling Guide](docs/exception-handling.md).

### Example

1. Create a custom span tracer class:

    ```php
    namespace Macpaw\SymfonyOtelBundle\Span;

    use Symfony\Component\EventDispatcher\EventSubscriberInterface;
    use OpenTelemetry\API\Trace\Span;

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
- `OTEL_TRACER_NAME`: The tracer name.
- `OTEL_TRACES_EXPORTER`: The exporter to be used for traces.
- `OTEL_METRICS_EXPORTER`: The exporter to be used for metrics.
- `OTEL_LOGS_EXPORTER`: The exporter to be used for logs.
- `OTEL_EXPORTER_OTLP_ENDPOINT`: The endpoint for the OTLP exporter.
- `OTEL_EXPORTER_OTLP_PROTOCOL`: Specify the OTLP transport protocol (supported values: `grpc`, `http/protobuf`, `http/json`)
- `OTEL_EXPORTER_OTLP_HEADERS`: Headers to be sent with each OTLP request.
- `OTEL_EXPORTER_OTLP_TIMEOUT`: Timeout for OTLP requests.
- `OTEL_PROPAGATORS`: Propagators to be used for context propagation.
- `OTEL_TRACES_SAMPLER`: The sampler to be used for traces.
- `OTEL_TRACES_SAMPLER_ARG`: Arguments for the trace sampler.
- `OTEL_LOG_LEVEL`: Log level

### Recommended Transport Configuration

**For production environments, we strongly recommend using gRPC transport instead of HTTP because HTTP has perfomance issues:**

```bash
# Install required packages
composer require open-telemetry/transport-grpc
# For PHP gRPC extension (recommended for better performance)
pecl install grpc

# Configure gRPC endpoint
OTEL_EXPORTER_OTLP_ENDPOINT=http://collector:4317
OTEL_EXPORTER_OTLP_PROTOCOL=grpc # instead http/protobuf
```
**Default HTTP endpoint (slower):** `http://collector:4318`
**Recommended gRPC endpoint (faster):** `http://collector:4317`

### Docker Examples

This bundle includes ready-to-use Docker examples for different scenarios:

#### PHP with gRPC Support
For applications requiring gRPC transport, use Dockerfile - ```docker/php/Dockerfile_grpc```

This Dockerfile includes:
- PHP 8.2 with gRPC extension
- OpenTelemetry extension
- All necessary build dependencies

#### OpenTelemetry Collector (Sidecar Pattern)
The bundle includes a pre-configured OpenTelemetry Collector that can be deployed as a sidecar:

```yaml
# docker-compose.yml
  otel-collector:
     image: otel/opentelemetry-collector-contrib:latest
     container_name: otel-collector
     command: [ "--config=/etc/otel-collector-config.yaml" ]
     volumes:
        - ./docker/otel-collector/otel-collector-config.yaml:/etc/otel-collector-config.yaml:rw
     depends_on:
        - tempo
     networks:
        - otel-network
     ports:
        - "4317:4317" # OTLP grpc receiver
        - "4318:4318" # OTLP http receiver
```

The collector configuration ```docker/otel-collector/otel-collector-config.yaml``` configured to use grpc and export to Tempo.

> **Note:** This bundle does **not** declare any additional collector-access settings.  
> To configure transports - use the [standard](https://opentelemetry.io/docs/specs/otel/configuration/sdk-environment-variables/) OpenTelemetry SDK environment variables listed above.


## Read next
- For a basic intro in OpenTelementry, please refer to the [OpenTelemetry Basics](./docs/otel_basics.md).
- For a complete list and detailed descriptions, please refer to the [OpenTelemetry SDK Environment Variables documentation](https://opentelemetry.io/docs/specs/otel/configuration/sdk-environment-variables/).

## Usage
see [docs](docs/start-and-test.md)

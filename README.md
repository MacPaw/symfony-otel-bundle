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

## Data Management

The bundle includes convenient commands for managing trace data during development and testing:

### Clear Trace Data

```bash
# Clear all spans data from Tempo and Grafana (quick fresh start)
make clear-data

# Clear only Tempo spans data (keep Grafana dashboards)
make clear-tempo

# Complete reset - rebuild everything with clean state
make reset-all

# Check current data volume status and trace count
make data-status
```

### Example Usage

```bash
# Start testing
make up
make test

# View traces in Grafana
make grafana

# Clear data for fresh testing
make clear-data

# Generate new test data
make test

# Check status
make data-status
```

These commands are essential for development workflows where you need to:

- Test trace collection from a clean state
- Clear accumulated test data
- Verify trace export functionality
- Debug trace storage issues


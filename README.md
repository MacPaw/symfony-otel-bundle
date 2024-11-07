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
First in first, you need to configure otel bundle itself. Example:
```.env
OTEL_TRACER_NAME='io.opentelemetry.contrib.php'
OTEL_PHP_AUTOLOAD_ENABLED=true
OTEL_SERVICE_NAME=your-service-name
OTEL_PHP_TRACES_PROCESSOR=batch
OTEL_TRACES_EXPORTER=otlp
OTEL_EXPORTER_OTLP_TRACES_ENDPOINT=http://local-collector:4318
OTEL_PROPAGATORS=tracecontext
```

Then you can configure your own span tracers. Example:
```yaml
otel_bundle:
  tracer_name: '%otel_bundle.tracer_name%'
  service_name: '%otel_bundle.service_name%'
  span_tracers:
    - { class: 'Macpaw\SymfonyOtelBundle\Span\ExecutionTimeSpanTracer', tag: 'kernel.event_subscriber' }
```

## Kernel event listeners
Example of kernel event listener implementation can be found in `Macpaw\SymfonyOtelBundle\Span\ExecutionTimeSpanTracer` class.
When specific listener need to be configured, you need to add it to `span_tracers` list in configuration after implementation.

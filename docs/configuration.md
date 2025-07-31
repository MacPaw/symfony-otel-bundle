# Configuration Reference

## Overview

This document provides a complete reference for configuring the Symfony OpenTelemetry Bundle, including bundle-specific settings and OpenTelemetry SDK configuration.

## Bundle Configuration

### YAML Configuration

Create or update `config/packages/otel_bundle.yaml`:

```yaml
otel_bundle:
    # Tracer configuration
    tracer_name: '%env(OTEL_TRACER_NAME)%'
    service_name: '%env(OTEL_SERVICE_NAME)%'
    
    # Built-in instrumentations
    instrumentations:
        - 'Macpaw\SymfonyOtelBundle\Instrumentation\RequestExecutionTimeInstrumentation'
    # Custom instrumentations
        - 'App\Instrumentation\CustomInstrumentation'
    
    # Header mappings for request ID propagation
    header_mappings:
        http.request_id: 'X-Request-Id'
        http.user_agent: 'X-User-Agent'
```

### Environment Variables

The bundle supports all standard OpenTelemetry SDK environment variables:

```bash
# Service and Tracer Configuration
OTEL_SERVICE_NAME=your-service-name
OTEL_TRACER_NAME=your-tracer-name

# Resource Attributes
OTEL_RESOURCE_ATTRIBUTES=service.version=1.0.0,deployment.environment=production

# Exporters Configuration
OTEL_TRACES_EXPORTER=otlp
OTEL_METRICS_EXPORTER=otlp
OTEL_LOGS_EXPORTER=otlp

# OTLP Configuration
OTEL_EXPORTER_OTLP_ENDPOINT=http://collector:4318
OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf
OTEL_EXPORTER_OTLP_HEADERS=authorization=Bearer token
OTEL_EXPORTER_OTLP_TIMEOUT=30

# Propagation Configuration
OTEL_PROPAGATORS=tracecontext,baggage

# Sampling Configuration
OTEL_TRACES_SAMPLER=always_on
OTEL_TRACES_SAMPLER_ARG=0.1

# Logging Configuration
OTEL_LOG_LEVEL=info
```

## Service Configuration

### Default Services

The bundle automatically registers the following services:

```yaml
services:
    # Core services
    Macpaw\SymfonyOtelBundle\Service\TraceService:
        public: true
        
    Macpaw\SymfonyOtelBundle\Registry\InstrumentationRegistry:
        public: true
        
    Macpaw\SymfonyOtelBundle\Service\HookManagerService:
        public: true
        lazy: false
        
    # Event subscribers
    Macpaw\SymfonyOtelBundle\Listeners\InstrumentationEventSubscriber:
        tags:
            - { name: 'kernel.event_subscriber' }
            
    Macpaw\SymfonyOtelBundle\Listeners\RequestRootSpanEventSubscriber:
        tags:
            - { name: 'kernel.event_subscriber' }
            
    Macpaw\SymfonyOtelBundle\Listeners\ExceptionHandlingEventSubscriber:
        tags:
            - { name: 'kernel.event_subscriber' }
```

### Custom Service Configuration

You can override default services or add custom configurations:

```yaml
services:
    # Custom tracer configuration
    app.custom_tracer:
        class: Macpaw\SymfonyOtelBundle\Service\TraceService
        arguments:
            $tracerProvider: '@OpenTelemetry\SDK\Trace\TracerProviderInterface'
            $serviceName: 'custom-service'
            $tracerName: 'custom-tracer'
            
    # Custom instrumentation
    app.custom_instrumentation:
        class: App\Instrumentation\CustomInstrumentation
        arguments:
            $instrumentationRegistry: '@Macpaw\SymfonyOtelBundle\Registry\InstrumentationRegistry'
            $tracer: '@OpenTelemetry\API\Trace\TracerInterface'
            $propagator: '@OpenTelemetry\Context\Propagation\TextMapPropagatorInterface'
```

## Environment-Specific Configuration

### Production Configuration

```yaml
# config/packages/prod/otel_bundle.yaml
otel_bundle:
    tracer_name: '%env(OTEL_TRACER_NAME)%'
    service_name: '%env(OTEL_SERVICE_NAME)%'
    
parameters:
    otel_service_name: '%env(OTEL_SERVICE_NAME)%'
    otel_tracer_name: '%env(OTEL_TRACER_NAME)%'
```

### Testing Configuration

```yaml
# config/packages/test/otel_bundle.yaml
otel_bundle:
    tracer_name: 'test-tracer'
    service_name: 'test-service'
    
parameters:
    otel_service_name: 'test-service'
    otel_tracer_name: 'test-tracer'
```

## Header Mappings

Configure custom header mappings for request ID and correlation ID propagation:

```yaml
otel_bundle:
    header_mappings:
        # Standard request ID
        http.request_id: 'X-Request-Id'
        
        # Correlation ID for distributed tracing
        http.correlation_id: 'X-Correlation-Id'
        
        # Custom headers
        custom.user_id: 'X-User-Id'
        custom.session_id: 'X-Session-Id'
```

## Advanced Configuration

### Custom Instrumentations

Configure custom instrumentations for specific operations:

```yaml
otel_bundle:
    instrumentations:
        - 'Macpaw\SymfonyOtelBundle\Instrumentation\RequestExecutionTimeInstrumentation'
        - 'App\Instrumentation\CustomInstrumentation'
        - 'App\Instrumentation\DatabaseInstrumentation'
        - 'App\Instrumentation\CacheInstrumentation'
```

## Validation and Debugging

### Configuration Validation

The bundle validates configuration on startup. Common validation errors:

1. **Invalid tracer name**: Must be a non-empty string
2. **Invalid service name**: Must be a non-empty string
3. **Invalid instrumentation class**: Class must exist and implement required interface
4. **Invalid instrumentation class**: Class must exist and implement InstrumentationInterface

### Debug Configuration

Enable debug logging to troubleshoot configuration issues:

```bash
# Enable debug logging
OTEL_LOG_LEVEL=debug

# Check configuration in Symfony
php bin/console debug:config otel_bundle
```

## Best Practices

### Configuration Organization

1. **Use environment variables** for sensitive data
2. **Separate configurations** by environment
3. **Validate configuration** in CI/CD pipelines
4. **Document custom configurations** in your project
5. **Use secrets for sensitive data**

### Performance Optimization

1. **Use appropriate sampling** for production
2. **Configure batch processing** for high-volume applications
3. **Set reasonable timeouts** for OTLP exporters
4. **Monitor configuration** impact on performance

## Reference Links

- [OpenTelemetry SDK Environment Variables](https://opentelemetry.io/docs/specs/otel/configuration/sdk-environment-variables/)
- [OpenTelemetry Semantic Conventions](https://opentelemetry.io/docs/specs/semconv/)
- [Symfony Configuration Reference](https://symfony.com/doc/current/configuration.html) 

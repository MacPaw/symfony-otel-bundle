# Installation Guide

## Overview

This guide will walk you through installing and setting up the Symfony OpenTelemetry Bundle in your application.

## Requirements

- PHP 8.2 or higher
- Symfony 6.4 or higher
- OpenTelemetry PHP Extension
- Composer

## Quick Installation

### 1. Install via Composer

```bash
composer require macpaw/symfony-otel-bundle
```

### 2. Enable the Bundle

Add the bundle to your `config/bundles.php`:

```php
<?php

return [
    // ... other bundles
    Macpaw\SymfonyOtelBundle\SymfonyOtelBundle::class => ['all' => true],
];
```

### 3. Configure Environment Variables

Create or update your `.env` file:

```bash
# OpenTelemetry Configuration
OTEL_SERVICE_NAME=your-service-name
OTEL_TRACER_NAME=your-tracer-name
OTEL_EXPORTER_OTLP_ENDPOINT=http://localhost:4318
OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf
OTEL_PROPAGATORS=tracecontext,baggage
OTEL_TRACES_SAMPLER=always_on
```

### 4. Choose Transport Protocol

#### HTTP Transport (Default - Slower)

```bash
OTEL_EXPORTER_OTLP_ENDPOINT=http://collector:4318
OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf
```

#### gRPC Transport (Recommended - Faster)

```bash
# Install gRPC dependencies
composer require open-telemetry/transport-grpc

# install PHP gRPC extension by pecl or compile from source
pecl install grpc

# Configure gRPC endpoint (usually 4317 port)
OTEL_EXPORTER_OTLP_ENDPOINT=http://collector:4317
OTEL_EXPORTER_OTLP_PROTOCOL=grpc
```

## Performance Considerations

### CI/CD Build Time Optimization

When using gRPC transport in CI/CD environments, build times can be significantly longer (up to 40 minutes) due to the compilation of gRPC from source. To optimize:

1. **Use pre-built images** when possible
2. **Cache Docker layers** for gRPC compilation
3. **Consider HTTP transport** for development/testing environments
4. **Use multi-stage builds** to separate gRPC compilation

### Production Recommendations

- Use gRPC transport for production environments
- Use environment-specific configurations

## Next Steps

After installation, proceed to:
- [Configuration Reference](configuration.md) - Learn about all configuration options
- [Instrumentation Guide](instrumentation.md) - Set up custom instrumentations
- [Docker Development](docker.md) - Set up local development environment

## Troubleshooting

### Common Issues

1. **OpenTelemetry Extension Not Found**
   ```bash
   pecl install opentelemetry-1.0.0
   ```

2. **gRPC Compilation Issues**
   - Use the provided Dockerfile_grpc for consistent builds
   - Ensure all build dependencies are installed

3. **Connection Issues**
   - Verify collector endpoint is accessible
   - Check firewall and network configuration
   - Validate transport protocol settings

## Support

If you encounter issues during installation:
- Check the [troubleshooting section](docker.md)
- Review [OpenTelemetry Basics](otel_basics.md)
- [Report issues on GitHub](https://github.com/macpaw/symfony-otel-bundle/issues) 

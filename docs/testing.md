# Testing Guide

## Overview

This guide provides comprehensive instructions for testing the Symfony OpenTelemetry Bundle, including quick start commands, test environment setup, trace visualization, and troubleshooting.

## Quick Start

### 1. Start Environment
```bash
make up
make health
```

### 2. Run Tests
```bash
make test
```

### 3. View Traces
```bash
make grafana
```
Navigate to Explore → Tempo → Search for service: `symfony-otel-test`

### 4. Clear Data (Optional)
```bash
make clear-data
```

## Test Environment Setup

The bundle includes a complete Docker-based test environment with all necessary components:

```bash
# Start the complete test environment
make up

# Check service health
make health

# View test application
open http://localhost:8080
```

### Environment Components

The test environment includes:

- **PHP Application** (Port 8080) - Symfony test application with OpenTelemetry bundle
- **Tempo** (Port 3200) - Trace storage and querying
- **Grafana** (Port 3000) - Trace visualization (admin/admin)
- **OpenTelemetry Collector** (Ports 4317/4318) - Trace collection and processing

## Quick Commands

### Environment Management
```bash
make up          # Start all services
make down        # Stop all services
make health      # Check service health
make status      # Show service status
```

### Testing
```bash
make test        # Run all tests
make load-test   # Generate test load
```

### Data Management
```bash
make clear-data  # Clear all trace data
make clear-tempo # Clear only Tempo data
make reset-all   # Complete environment reset
make data-status # Check data status
```

### Access Services
```bash
make grafana     # Open Grafana dashboard
make logs        # View all logs
make logs-php    # View PHP application logs
```

## Running Tests

### Basic Testing

```bash
# Run all tests
make test

# Run specific test suites
make phpunit
make phpcs
make phpstan

# Run with coverage
make coverage
```

### Load Testing

```bash
# Generate test load
make load-test

# Run specific load tests
curl -X GET http://localhost:8080/api/test
curl -X GET http://localhost:8080/api/slow
curl -X GET http://localhost:8080/api/nested
curl -X GET http://localhost:8080/api/error
```

### Test Endpoints

The test application provides several endpoints for testing different scenarios:

| Endpoint | Description | Expected Behavior |
|----------|-------------|-------------------|
| `/` | Homepage | Basic request tracing |
| `/api/test` | Simple API | Basic span creation |
| `/api/slow` | Slow operation | Long-running span (2s) |
| `/api/nested` | Nested spans | Complex trace hierarchy |
| `/api/error` | Error handling | Exception tracing |

For detailed testing guide, see [Docker Guide](docker.md).

## Trace Visualization

### Accessing Grafana

```bash
# Open Grafana in browser
make grafana

# Or manually navigate to
open http://localhost:3000
```

**Credentials:** admin/admin

### Viewing Traces

1. **Navigate to Explore** in the left sidebar
2. **Select Tempo** as the data source
3. **Search for traces** using:
   - Service name: `symfony-otel-test`
   - Operation name: `execution_time`, `api_test_operation`
   - Tags: `http.method`, `http.route`

### TraceQL Queries

Use TraceQL to search for specific traces:

```sql
# Find all traces for the test service
{.service.name="symfony-otel-test"}

# Find slow operations
{.service.name="symfony-otel-test"} | duration > 1s

# Find error traces
{.service.name="symfony-otel-test"} | status = "error"

# Find specific HTTP methods
{.service.name="symfony-otel-test"} | .http.method="GET"
```

## Data Management

### Clearing Test Data

```bash
# Clear all spans data
make clear-data

# Clear only Tempo data
make clear-tempo

# Complete environment reset
make reset-all
```

### Data Status

```bash
# Check current data status
make data-status

# View all data management commands
make data-commands
```

### Data Management Commands

| Command       | Tempo Data | Grafana Data | Containers    | Rebuild |
|---------------|------------|--------------|---------------|---------|
| `clear-data`  | ✅ Cleared  | ✅ Cleared    | ♻️ Restarted  | ❌ No    |
| `clear-tempo` | ✅ Cleared  | ❌ Kept       | ♻️ Tempo only | ❌ No    |
| `reset-all`   | ✅ Cleared  | ✅ Cleared    | ♻️ All        | ✅ Yes   |

### Common Workflows

#### Development
```bash
make up          # Start environment
make test        # Run tests
make clear-data  # Clear for clean testing
make grafana     # View results
```

#### Debugging
```bash
make health      # Check services
make data-status # Check trace data
make clear-data  # Clear and retest
make reset-all   # Complete reset if needed
```

#### Performance Testing
```bash
make clear-data  # Start clean
make load-test   # Generate load
make data-status # Check results
```

## Integration Testing

### Testing Custom Instrumentations

1. **Create test instrumentation** in your application
2. **Register it** in the test configuration
3. **Generate test data** by calling instrumented methods
4. **Verify traces** in Grafana

Example test instrumentation:

```php
<?php

namespace App\Instrumentation;

use Macpaw\SymfonyOtelBundle\Instrumentation\AbstractHookInstrumentation;

final class TestInstrumentation extends AbstractHookInstrumentation
{
    public function getName(): string
    {
        return 'test.operation';
    }

    public function getClass(): string
    {
        return TestService::class;
    }

    public function getMethod(): string
    {
        return 'test';
    }

    protected function buildSpan(SpanBuilderInterface $spanBuilder): SpanInterface
    {
        return $spanBuilder
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->setAttribute('test.operation', 'test')
            ->startSpan();
    }
}
```

### Testing Configuration

```yaml
# test_app/config/packages/test/otel_bundle.yaml
otel_bundle:
    tracer_name: 'test-tracer'
    service_name: 'test-service'
    instrumentations:
        - 'App\Instrumentation\TestInstrumentation'
```

## Performance Testing

### Load Testing

```bash
# Run basic load test
make load-test

# Custom load testing with Apache Bench
ab -n 100 -c 10 http://localhost:8080/api/test

# Custom load testing with curl
for i in {1..50}; do
    curl -X GET http://localhost:8080/api/test &
done
wait
```

### Performance Monitoring

Monitor the following metrics during testing:

- **Trace generation rate** - Number of traces per second
- **Span creation overhead** - Time spent creating spans
- **Export performance** - Time to export traces to collector
- **Memory usage** - Memory consumption of instrumentation

## Quick Tips

- **Fresh Start**: `make clear-data` for quick data clearing
- **Keep Settings**: `make clear-tempo` to preserve Grafana dashboards  
- **Complete Reset**: `make reset-all` for troubleshooting
- **Check Status**: `make data-status` to verify data state

## Debugging Tests

### Common Issues

1. **Traces not appearing in Grafana**
   ```bash
   # Check if services are running
   make status
   
   # Check Tempo logs
   make logs-tempo
   
   # Verify trace export
   curl http://localhost:3200/api/traces
   ```

2. **High memory usage**
   ```bash
   # Check PHP memory usage
   docker exec -it symfony-otel-bundle-php-app-1 ps aux
   
   # Restart PHP application
   make php-restart
   ```

3. **Slow trace export**
   ```bash
   # Check collector configuration
   docker exec -it symfony-otel-bundle-otel-collector-1 cat /etc/otel-collector-config.yaml
   
   # Restart collector
   make collector-restart
   ```

4. **Services won't start**
   ```bash
   make reset-all
   ```

### Debug Logging

Enable debug logging for troubleshooting:

```bash
# Set debug log level
export OTEL_LOG_LEVEL=debug

# Restart services
make restart

# Check logs
make logs
```

## Best Practices

### Test Organization

1. **Separate test environments** for different scenarios
2. **Use meaningful test data** that represents real usage
3. **Test both success and error scenarios**
4. **Verify trace quality** and completeness

### Performance Testing

1. **Test with realistic load** that matches production
2. **Monitor resource usage** during testing
3. **Test different transport protocols** (HTTP vs gRPC)
4. **Verify sampling behavior** under load

## Reference Commands

### Environment Management

```bash
make up          # Start environment
make down        # Stop environment
make restart     # Restart all services
make status      # Check service status
make health      # Check service health
```

### Testing Commands

```bash
make test        # Run all tests
make phpunit     # Run PHPUnit tests
make load-test   # Generate test load
make coverage    # Run tests with coverage
```

### Data Management

```bash
make clear-data  # Clear all test data
make clear-tempo # Clear Tempo data only
make reset-all   # Complete environment reset
make data-status # Check data status
```

### Service Management

```bash
make php-restart     # Restart PHP application
make tempo-restart   # Restart Tempo
make grafana-restart # Restart Grafana
make logs           # View all logs
```

## Support

If you encounter issues during testing:

1. **Check the troubleshooting section** in [Docker Guide](docker.md#troubleshooting)
2. **Review service logs** using `make logs`
3. **Verify configuration** in the test environment
4. **Report issues** on GitHub with detailed information 

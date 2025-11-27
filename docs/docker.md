# 🐳 Docker Development Environment

This guide covers setting up the complete Docker development environment for the Symfony OpenTelemetry Bundle, including performance optimization for gRPC compilation and troubleshooting common issues.

## 🏗️ Architecture

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Symfony App   │───▶│     Tempo       │───▶│    Grafana      │
│   (PHP 8.2+)    │    │   (Traces)      │    │  (Visualization)│
│   Port: 8080    │    │   Port: 3200    │    │   Port: 3000    │
└─────────────────┘    └─────────────────┘    └─────────────────┘
        │                        ▲
        │                        │
        └──────────────────────────┘
            OpenTelemetry OTLP
```

## 🚀 Quick Start

1. **Start the environment:**
   ```bash
   make up
   ```

2. **Check service health:**
   ```bash
   make health
   ```

3. **Access services:**
   - **Test Application:** http://localhost:8080
   - **Grafana Dashboard:** http://localhost:3000 (admin/admin)
   - **Tempo API:** http://localhost:3200

4. **View traces in Grafana:**
   - Navigate to "Explore" → "Tempo"
   - Search for service: `symfony-otel-test`

## 📊 Services

### 🔍 Tempo (Traces Backend)
- **Port:** 3200 (HTTP), 4317 (OTLP gRPC), 4318 (OTLP HTTP)
- **Purpose:** Collects and stores OpenTelemetry traces
- **Config:** `docker/tempo/tempo.yaml`

### 📈 Grafana (Visualization)
- **Port:** 3000
- **Credentials:** admin/admin
- **Purpose:** Visualize traces and create dashboards
- **Config:** `docker/grafana/provisioning/`

### 🐘 PHP Application
- **Port:** 8080
- **Purpose:** Test Symfony application with OpenTelemetry bundle
- **Framework:** Symfony 6.4+ with PHP 8.2
- **OpenTelemetry:** Configured to send traces to Tempo

### 📡 OpenTelemetry Collector (Optional)
- **Ports:** 4317 (gRPC), 4318 (HTTP)
- **Purpose:** Advanced trace processing and routing
- **Config:** `docker/otel-collector/otel-collector-config.yaml`

## 🧪 Testing the Bundle

### Available Test Endpoints

| Endpoint | Description | Expected Behavior |
|----------|-------------|-------------------|
| `/` | Homepage | Basic request tracing |
| `/api/test` | Simple API | Basic span creation |
| `/api/slow` | Slow operation | Long-running span (2s) |
| `/api/nested` | Nested spans | Complex trace hierarchy |
| `/api/error` | Error handling | Exception tracing |

### Generate Test Data

```bash
# Run basic tests
make test

# Generate load for testing
make load-test

# Or use individual cURL commands
curl -X GET http://localhost:8080/api/test
curl -X GET http://localhost:8080/api/slow
curl -X GET http://localhost:8080/api/nested
curl -X GET http://localhost:8080/api/error
```

## 📋 Viewing Traces

1. **Open Grafana:** http://localhost:3000
2. **Navigate to Explore:** Click "Explore" in the left sidebar
3. **Select Tempo:** Choose "Tempo" as the data source
4. **Search traces:** Use TraceQL or search by:
   - Service name: `symfony-otel-test`
   - Operation name: `execution_time`, `api_test_operation`, etc.
   - Tags: `http.method`, `http.route`, etc.

### Import the ready-made Grafana dashboard

1. In Grafana, go to Dashboards → Import
2. Upload the JSON at `docs/grafana/symfony-otel-dashboard.json` (inside this repository)
3. Select your Tempo data source when prompted (or keep the default if named `Tempo`)
4. Open the imported dashboard: "Symfony OpenTelemetry — Starter Dashboard"

Notes:

- The dashboard expects Tempo with spanmetrics enabled in your Grafana/Tempo stack
- Use the service variable at the top of the dashboard to switch between services

### Example TraceQL Queries

```traceql
# Find all traces from the Symfony service
{service.name="symfony-otel-test"}

# Find slow operations (>1 second)
{service.name="symfony-otel-test" && duration>1s}

# Find error traces
{service.name="symfony-otel-test" && status=error}

# Find traces with specific HTTP methods
{service.name="symfony-otel-test" && http.method="GET"}
```

For detailed trace visualization guide, see [Testing Guide](testing.md).

## 🛠️ Configuration

### Environment Variables

The PHP application uses these OpenTelemetry environment variables:

```bash
OTEL_EXPORTER_OTLP_ENDPOINT=http://tempo:4318
OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf
OTEL_SERVICE_NAME=symfony-otel-test
OTEL_TRACER_NAME=symfony-otel-bundle
OTEL_RESOURCE_ATTRIBUTES=service.name=symfony-otel-test,service.version=1.0.0
OTEL_PROPAGATORS=tracecontext,baggage
```

### Bundle Configuration

The test application configures the bundle with:

```yaml
otel_bundle:
  tracer_name: '%env(OTEL_TRACER_NAME)%'
  service_name: '%env(OTEL_SERVICE_NAME)%'
  instrumentations:
    - 'Macpaw\SymfonyOtelBundle\Instrumentation\RequestExecutionTimeInstrumentation'
  header_mappings:
    http.request_id: 'X-Request-Id'
```

## ⚡ Performance Optimization

### gRPC Compilation Optimization

The default `pecl install grpc` can take 30-40 minutes to compile. For faster builds, use the provided `Dockerfile_grpc` which compiles gRPC from source (version 1.63) in just 5-10 minutes.

#### Build Time Comparison

| Method | Time | Notes |
|--------|------|-------|
| `pecl install grpc` | 30-40 minutes | Default method, slow |
| `Dockerfile_grpc` | 5-10 minutes | Optimized from source |
| HTTP transport only | 2-3 minutes | No gRPC compilation needed |

#### Development vs Production

- **Development:** Use HTTP transport for faster builds
- **Production:** Use gRPC transport for better performance
- **CI/CD:** Use `Dockerfile_grpc` for optimized builds

### Transport Protocol Selection

```bash
# HTTP Transport (faster builds)
OTEL_EXPORTER_OTLP_ENDPOINT=http://collector:4318
OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf

# gRPC Transport (better performance)
OTEL_EXPORTER_OTLP_ENDPOINT=http://collector:4317
OTEL_EXPORTER_OTLP_PROTOCOL=grpc
```

## 🐛 Troubleshooting

### Check Service Status
```bash
make status
```

### View Logs
```bash
# All services
make logs

# Specific service
make logs-php
make logs-tempo
make logs-grafana
```

### Debug OpenTelemetry
```bash
# Check if traces are being exported
make logs-php | grep -i otel

# Check Tempo ingestion
make logs-tempo | grep -i trace
```

### Common Issues

1. **No traces in Grafana:**
   - Check if the PHP app is sending traces: `make logs-php`
   - Verify Tempo is receiving traces: `make logs-tempo`
   - Ensure correct OTLP endpoint configuration

2. **Grafana connection issues:**
   - Verify Tempo is running: `make status`
   - Check Grafana datasource configuration
   - Try restarting Grafana: `make grafana-restart`

3. **PHP application errors:**
   - Check PHP logs: `make logs-php`
   - Verify OpenTelemetry extension is loaded
   - Check bundle configuration

4. **Slow gRPC compilation:**
   - Use `Dockerfile_grpc` for faster builds
   - Consider HTTP transport for development
   - Use pre-built images when possible

## 🧹 Cleanup

```bash
# Stop all services
make down

# Clear all data (traces and Grafana data)
make clear-data

# Complete reset (remove volumes and images)
make reset-all
```

## 🔧 Development

### Rebuild PHP Container
```bash
make php-rebuild
```

### Update Bundle Code
The bundle source code is mounted as a volume, so changes are reflected immediately.

### Add Custom Instrumentations
1. Create your instrumentation class in `src/Instrumentation/`
2. Add it to the bundle configuration in `test_app/config/packages/otel_bundle.yaml`
3. Restart the container: `make php-restart`

## 📚 Resources

- [OpenTelemetry PHP Documentation](https://opentelemetry.io/docs/instrumentation/php/)
- [Grafana Tempo Documentation](https://grafana.com/docs/tempo/)
- [TraceQL Documentation](https://grafana.com/docs/tempo/latest/traceql/)
- [Symfony Bundle Best Practices](https://symfony.com/doc/current/bundles/best_practices.html)
- [Testing Guide](testing.md) - Detailed testing and trace visualization
- [Configuration Guide](configuration.md) - Bundle configuration options 

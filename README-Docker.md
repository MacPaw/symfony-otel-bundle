# 🐳 Docker Setup for Symfony OpenTelemetry Bundle Testing

This Docker Compose setup provides a complete environment for testing the Symfony OpenTelemetry bundle with Grafana
Tempo for trace collection and Grafana for visualization.

## 🏗️ Architecture

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Symfony App   │───▶│     Tempo       │───▶│    Grafana      │
│   (PHP 8.2)     │    │   (Traces)      │    │  (Visualization)│
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
   docker-compose up -d
   ```

2. **View the test application:**
    - Open http://localhost:8080 in your browser
    - This shows the test application with available endpoints

3. **Access Grafana:**
    - Open http://localhost:3000 in your browser
    - Login: `admin` / `admin`
    - Navigate to "Explore" → "Tempo" to view traces

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

- **Ports:** 4319 (gRPC), 4320 (HTTP), 8889 (Metrics)
- **Purpose:** Advanced trace processing and routing
- **Config:** `docker/otel-collector/otel-collector-config.yaml`

## 🧪 Testing the Bundle

### Available Test Endpoints

1. **Homepage (`/`)** - Overview and documentation
2. **Simple API (`/api/test`)** - Basic tracing example
3. **Slow Operation (`/api/slow`)** - Long-running operation tracing
4. **Nested Spans (`/api/nested`)** - Complex trace with child spans
5. **Error Handling (`/api/error`)** - Error and exception tracing

### Example cURL Commands

```bash
# Test basic tracing
curl -X GET http://localhost:8080/api/test

# Test slow operation (2 seconds)
curl -X GET http://localhost:8080/api/slow

# Test nested spans
curl -X GET http://localhost:8080/api/nested

# Test error tracing
curl -X GET http://localhost:8080/api/error

# Test with distributed tracing headers
curl -X GET http://localhost:8080/api/test \
  -H "traceparent: 00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01"
```

## 📋 Viewing Traces

1. **Open Grafana:** http://localhost:3000
2. **Navigate to Explore:** Click "Explore" in the left sidebar
3. **Select Tempo:** Choose "Tempo" as the data source
4. **Search traces:** Use TraceQL or search by:
    - Service name: `symfony-otel-test`
    - Operation name: `execution_time`, `api_test_operation`, etc.
    - Tags: `http.method`, `http.route`, etc.

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

The test application configures your bundle with:

```yaml
otel_bundle:
  tracer_name: '%env(OTEL_TRACER_NAME)%'
  service_name: '%env(OTEL_SERVICE_NAME)%'
  span_tracers:
    - { class: 'Macpaw\SymfonyOtelBundle\Span\ExecutionTimeSpanTracer', tag: 'kernel.event_subscriber' }
```

## 🐛 Troubleshooting

### Check Service Status

```bash
docker-compose ps
```

### View Logs

```bash
# All services
docker-compose logs -f

# Specific service
docker-compose logs -f php-app
docker-compose logs -f tempo
docker-compose logs -f grafana
```

### Debug OpenTelemetry

```bash
# Check if traces are being exported
docker-compose logs -f php-app | grep -i otel

# Check Tempo ingestion
docker-compose logs -f tempo | grep -i trace
```

### Common Issues

1. **No traces in Grafana:**
    - Check if the PHP app is sending traces: `docker-compose logs php-app`
    - Verify Tempo is receiving traces: `docker-compose logs tempo`
    - Ensure correct OTLP endpoint configuration

2. **Grafana connection issues:**
    - Verify Tempo is running: `docker-compose ps tempo`
    - Check Grafana datasource configuration
    - Try restarting Grafana: `docker-compose restart grafana`

3. **PHP application errors:**
    - Check PHP logs: `docker-compose logs php-app`
    - Verify OpenTelemetry extension is loaded
    - Check bundle configuration

## 🧹 Cleanup

```bash
# Stop all services
docker-compose down

# Remove volumes (will delete traces and Grafana data)
docker-compose down -v

# Remove images
docker-compose down --rmi all
```

## 🔧 Development

### Rebuild PHP Container

```bash
docker-compose build php-app
docker-compose up -d php-app
```

### Update Bundle Code

The bundle source code is mounted as a volume, so changes are reflected immediately.

### Add Custom Tracers

1. Create your tracer class in `src/Span/`
2. Add it to the bundle configuration in `test_app/src/Kernel.php`
3. Restart the container: `docker-compose restart php-app`

## 📚 Resources

- [OpenTelemetry PHP Documentation](https://opentelemetry.io/docs/instrumentation/php/)
- [Grafana Tempo Documentation](https://grafana.com/docs/tempo/)
- [TraceQL Documentation](https://grafana.com/docs/tempo/latest/traceql/)
- [Symfony Bundle Best Practices](https://symfony.com/doc/current/bundles/best_practices.html) 


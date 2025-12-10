# Load Testing with k6

This directory contains comprehensive k6 load testing scripts for the Symfony OpenTelemetry Bundle test application.

## Overview

k6 is a modern load testing tool. Test scripts are written in JavaScript and executed by the k6 runtime inside a Docker container.

**Key Features:**
- ✅ Containerized runner (docker/k6-go/Dockerfile)
- ✅ Recent k6 version with core features
- ✅ Extensible via xk6 (k6 extensions)
- ✅ Minimal Docker image size
- ✅ Comprehensive test coverage for all endpoints
- ✅ Advanced scenario-based testing

## Prerequisites

- Docker and Docker Compose installed
- The test application must be running (`docker-compose up` or `make up`)
- k6 service is built from `docker/k6-go/Dockerfile`
- PHP app runs in Docker container defined in `docker/php/`

## Test App Endpoints

The test application provides the following endpoints for load testing:

| Endpoint | Description | Expected Response Time |
|----------|-------------|------------------------|
| `/` | Homepage with documentation | < 100ms |
| `/api/test` | Basic API endpoint | < 200ms |
| `/api/slow` | Slow operation (2s sleep) | ~2000ms |
| `/api/nested` | Nested spans (DB + API simulation) | ~800ms |
| `/api/pdo-test` | PDO query test (SQLite in-memory) | < 200ms |
| `/api/cqrs-test` | CQRS pattern (QueryBus + CommandBus) | < 200ms |
| `/api/exception-test` | Exception handling test | N/A (throws exception) |

## Available Tests

### 1. Smoke Test (`smoke-test.js`)
**Purpose:** Minimal load test to verify all endpoints are working correctly.
- **Virtual Users (VUs):** 1
- **Duration:** 1 minute
- **Use Case:** Quick sanity check before running larger tests

**Run:**
```bash
docker-compose run --rm k6 run /scripts/smoke-test.js
```

### 2. Basic Test (`basic-test.js`)
**Purpose:** Test the simple `/api/test` endpoint with ramping load.
- **Stages:**
  - Ramp up to 10 VUs over 30s
  - Maintain 20 VUs for 1m
  - Spike to 50 VUs for 30s
  - Ramp down to 20 VUs for 1m
  - Cool down to 0 over 30s

**Run:**
```bash
docker-compose run --rm k6 run /scripts/basic-test.js
```

### 3. Slow Endpoint Test (`slow-endpoint-test.js`)
**Purpose:** Test the `/api/slow` endpoint which simulates a 2-second operation.
- **Stages:** Lighter load (5-10 VUs) to account for slow responses
- **Thresholds:** p95 < 3s, p99 < 5s

**Run:**
```bash
docker-compose run --rm k6 run /scripts/slow-endpoint-test.js
```

### 4. Nested Spans Test (`nested-spans-test.js`)
**Purpose:** Test the `/api/nested` endpoint which creates nested OpenTelemetry spans.
- **Tests:** Database simulation + External API call simulation
- **Duration:** ~800ms per request

**Run:**
```bash
docker-compose run --rm k6 run /scripts/nested-spans-test.js
```

### 5. PDO Test (`pdo-test.js`)
**Purpose:** Test the `/api/pdo-test` endpoint with PDO instrumentation.
- **Tests:** SQLite in-memory database queries
- **Verifies:** ExampleHookInstrumentation functionality

**Run:**
```bash
docker-compose run --rm k6 run /scripts/pdo-test.js
```

### 6. CQRS Test (`cqrs-test.js`)
**Purpose:** Test the `/api/cqrs-test` endpoint with CQRS pattern.
- **Tests:** QueryBus and CommandBus with middleware tracing

**Run:**
```bash
docker-compose run --rm k6 run /scripts/cqrs-test.js
```

### 7. Comprehensive Test (`comprehensive-test.js`)
**Purpose:** Test all endpoints with weighted distribution.
- **Distribution:**
  - `/api/test`: 40%
  - `/api/nested`: 30%
  - `/api/pdo-test`: 20%
  - `/api/cqrs-test`: 10%
- **Use Case:** Realistic mixed workload

**Run:**
```bash
docker-compose run --rm k6 run /scripts/comprehensive-test.js
```

### 8. Stress Test (`stress-test.js`)
**Purpose:** Push the system beyond normal operating capacity.
- **Stages:**
  - Ramp to 100 VUs (2m)
  - Maintain 100 VUs (5m)
  - Ramp to 200 VUs (2m)
  - Maintain 200 VUs (5m)
  - Ramp to 300 VUs (2m)
  - Maintain 300 VUs (5m)
  - Cool down (10m)

**Run:**
```bash
docker-compose run --rm k6 run /scripts/stress-test.js
```

### 8. All Scenarios Test (`all-scenarios-test.js`) ⭐ RECOMMENDED
**Purpose:** Run all test scenarios in a single comprehensive test with parallel execution.
- **Duration:** ~16 minutes
- **Execution:** Uses k6 scenarios feature for parallel execution with staggered starts
- **Use Case:** Complete system validation and comprehensive trace generation
- **Benefits:**
  - 🎯 Production-realistic load patterns
  - 🚀 All features tested simultaneously
  - 📊 Comprehensive trace data for analysis
  - ⏱️ Time-efficient compared to running tests individually
  - 🔍 Scenario-specific thresholds and tags

**Run:**
```bash
docker-compose run --rm k6 run /scripts/all-scenarios-test.js
# or using Make
make k6-all-scenarios
```

**Execution Schedule:**
1. **0m-1m:** Smoke test (1 VU) - Validates all endpoints
2. **1m-4m30s:** Basic load test - Ramping 0→50 VUs on /api/test
3. **4m30s-6m30s:** Nested spans test - 10 VUs testing complex traces
4. **6m30s-8m30s:** PDO test - 10 VUs testing database instrumentation
5. **8m30s-10m30s:** CQRS test - 10 VUs testing QueryBus/CommandBus
6. **10m30s-12m30s:** Slow endpoint test - Ramping 0→10 VUs on slow operations
7. **12m30s-16m:** Comprehensive test - Mixed workload with weighted distribution

**Scenario-Specific Metrics:**
- Tagged metrics allow analysis per scenario type
- Individual thresholds for each test type
- Comprehensive failure rate monitoring

## Running Tests

### Quick Start

1. **Start the application:**
   ```bash
   docker-compose up -d
   # or using Make
   make up
   ```

2. **Run a test:**
   ```bash
   # Run individual test
   docker-compose run --rm k6 run /scripts/smoke-test.js

   # Run all scenarios at once (recommended)
   docker-compose run --rm k6 run /scripts/all-scenarios-test.js
   # or using Make
   make k6-all-scenarios
   ```

3. **View results in Grafana:**
   ```
   http://localhost:3000
   Navigate to Explore > Tempo
   # or using Make
   make grafana
   ```

### Using Make Commands (Recommended)

The project includes convenient Make commands for running k6 tests:

```bash
# Individual tests
make k6-smoke              # Quick sanity check
make k6-basic              # Basic load test
make k6-slow               # Slow endpoint test
make k6-nested             # Nested spans test
make k6-pdo                # PDO instrumentation test
make k6-cqrs               # CQRS pattern test
make k6-comprehensive      # Mixed workload test
make k6-stress             # Stress test (~31 minutes)

# Run all scenarios in one comprehensive test
make k6-all-scenarios      # All scenarios test (~15 minutes) ⭐ RECOMMENDED

# Run all tests individually in sequence
make k6-all                # Run all tests except stress test

# Custom test
make k6-custom TEST=your-test.js
```

### Using Docker Compose

The k6 service is configured with the `loadtest` profile:

```bash
# Run specific test
docker-compose --profile loadtest run k6 run /scripts/basic-test.js

# Run without profile (if k6 is always available)
docker-compose run --rm k6 run /scripts/basic-test.js

# Run with custom options
docker-compose run --rm k6 run /scripts/basic-test.js --vus 20 --duration 2m

# Run with output to file
docker-compose run --rm k6 run /scripts/basic-test.js --out json=/scripts/results.json

# Run with environment variable override
docker-compose run --rm -e BASE_URL=http://localhost:8080 k6 run /scripts/basic-test.js
```

### Running Without Docker

If you have k6 installed locally:

```bash
cd loadTesting
BASE_URL=http://localhost:8080 k6 run basic-test.js
```

## Test Configuration

All tests share common configuration from `config.js`:

### Default Thresholds
- **http_req_duration:** p95 < 500ms, p99 < 1000ms
- **http_req_failed:** < 1% failure rate
- **http_reqs:** > 10 requests/second

### Available Options
- `options` - Default ramping load test
- `smokingOptions` - Minimal 1 VU test
- `loadOptions` - Standard load test (100 VUs for 5m)
- `stressOptions` - Stress test up to 300 VUs
- `spikeOptions` - Spike test to 1400 VUs

## Viewing Results

### During Test Execution
k6 provides real-time console output showing:
- Current VUs
- Request rate
- Response times (min/avg/max/p90/p95)
- Check pass rates

### In Grafana
1. Open http://localhost:3000
2. Go to Explore > Tempo
3. Search for traces during your test period
4. View detailed span information including:
   - Request duration
   - Nested spans
   - Custom attributes
   - Events and errors

### Export Results
```bash
# JSON output
docker-compose run --rm k6 run /scripts/basic-test.js --out json=/scripts/results.json

# CSV output
docker-compose run --rm k6 run /scripts/basic-test.js --out csv=/scripts/results.csv

# InfluxDB (if configured)
docker-compose run --rm k6 run /scripts/basic-test.js --out influxdb=http://influxdb:8086/k6
```

## Custom Test Configuration

You can override configuration via environment variables:

```bash
# Change base URL
docker-compose run --rm -e BASE_URL=http://custom-host:8080 k6 run /scripts/basic-test.js

# Run with custom VUs and duration
docker-compose run --rm k6 run /scripts/basic-test.js --vus 50 --duration 5m
```

## Interpreting Results

### Success Criteria
- All checks pass (status 200, response times within limits)
- Error rate < 1%
- p95 response times within thresholds
- No crashes or exceptions in the application

### Common Issues
- **High response times:** May indicate performance bottleneck
- **Failed requests:** Check application logs
- **Timeouts:** Increase thresholds or reduce load
- **Memory issues:** Monitor container resources

## Best Practices

1. **Start Small:** Always run smoke test first
2. **Ramp Gradually:** Use staged load increase
3. **Monitor Resources:** Watch CPU, memory, network
4. **Check Traces:** Verify traces are being generated correctly in Grafana
5. **Baseline First:** Establish baseline performance before changes
6. **Clean Environment:** Ensure consistent test conditions

## Troubleshooting

### Tests Fail to Connect
```bash
# Verify php-app is running
docker-compose ps

# Check network connectivity
docker-compose exec k6 wget -O- http://php-app:8080/
```

### No Traces in Grafana
- Verify OTEL configuration in docker-compose.override.yml
- Check Tempo logs: `docker-compose logs tempo`
- Ensure traces are being exported: `docker-compose logs php-app`

### High Error Rates
- Check application logs: `docker-compose logs php-app`
- Reduce concurrent users
- Increase sleep times between requests

## Advanced Usage

### Custom Scenarios
Create your own test by copying an existing script and modifying:
```javascript
import http from 'k6/http';
import { check, sleep } from 'k6';
import { BASE_URL } from './config.js';

export const options = {
    vus: 10,
    duration: '1m',
};

export default function () {
    const res = http.get(`${BASE_URL}/your-endpoint`);
    check(res, { 'status is 200': (r) => r.status === 200 });
    sleep(1);
}
```

### Running Multiple Tests
```bash
#!/bin/bash
for test in smoke-test basic-test nested-spans-test; do
    echo "Running $test..."
    docker-compose run --rm k6 run /scripts/${test}.js
    sleep 10
done
```

## k6 Architecture (Go-based)

Our setup uses a custom Go-based k6 build:

```
┌─────────────────────────────────────┐
│   docker/k6-go/Dockerfile           │
├─────────────────────────────────────┤
│ Stage 1: Builder (golang:1.22)     │
│  - Clone k6 from GitHub             │
│  - Build k6 binary from Go source   │
│  - Optional: Add xk6 extensions     │
├─────────────────────────────────────┤
│ Stage 2: Runtime (alpine:3.20)     │
│  - Copy k6 binary                   │
│  - Minimal image (~50MB)            │
└─────────────────────────────────────┘
           ↓
    k6 JavaScript Tests
    (loadTesting/*.js)
```

**Benefits of Go-based Build:**
- 🔧 Latest k6 features
- 📦 Smaller Docker images
- 🚀 Better performance
- 🔌 Support for xk6 extensions
- 🛠️ Custom build options

## Adding k6 Extensions

To add xk6 extensions, modify `docker/k6-go/Dockerfile`:

```dockerfile
# Install xk6
RUN go install go.k6.io/xk6/cmd/xk6@latest

# Build k6 with extensions
RUN xk6 build latest \
    --with github.com/grafana/xk6-sql@latest \
    --with github.com/grafana/xk6-redis@latest \
    --output /usr/local/bin/k6
```

**Popular Extensions:**
- [xk6-sql](https://github.com/grafana/xk6-sql) - SQL database testing
- [xk6-redis](https://github.com/grafana/xk6-redis) - Redis testing
- [xk6-kafka](https://github.com/mostafa/xk6-kafka) - Kafka testing
- [xk6-prometheus](https://github.com/grafana/xk6-output-prometheus-remote) - Prometheus output
- [More extensions](https://k6.io/docs/extensions/explore/)

## Resources

- [k6 Documentation](https://k6.io/docs/)
- [k6 Scenarios](https://k6.io/docs/using-k6/scenarios/)
- [xk6 Extensions](https://github.com/grafana/xk6)
- [k6 Test Types](https://k6.io/docs/test-types/introduction/)
- [k6 Metrics](https://k6.io/docs/using-k6/metrics/)
- [OpenTelemetry Documentation](https://opentelemetry.io/docs/)
- [Grafana Tempo](https://grafana.com/docs/tempo/latest/)
- [Build k6 Binary Using Go](https://grafana.com/docs/k6/latest/extensions/run/build-k6-binary-using-go/)

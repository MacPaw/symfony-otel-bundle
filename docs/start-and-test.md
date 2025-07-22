# Data Management Commands Demo

This demo shows how to use the data management commands for clearing spans data in Tempo and Grafana.

## Overview

When developing and testing OpenTelemetry integrations, you often need to clear trace data to:

- Start with a clean state for testing
- Remove test/debug traces before production
- Verify trace collection is working correctly
- Debug trace export issues

## Available Commands

### Basic Data Clearing

```bash
# Clear all spans data from both Tempo and Grafana
make clear-data

# Clear only Tempo spans data (keeps Grafana settings)
make clear-tempo

# Alias for clear-data
make clear-spans
```

### Advanced Management

```bash
# Complete reset - rebuild everything with clean state
make reset-all

# Check current data status
make data-status

# Show all data management options
make data-commands
```

## Step-by-Step Demo

### 1. Start the Environment

```bash
# Start all services
make up

# Wait for services to be ready
make health
```

### 2. Generate Test Data

```bash
# Run basic tests to generate traces
make test

# Run some load testing
make load-test

# Check that traces exist
make data-status
```

Expected output:

```
📊 Data Volume Status:
Docker Volumes:
symfony-otel-bundle_grafana-data
symfony-otel-bundle_tempo-data

✅ Tempo is ready
Recent Traces:
4bf92f3577b34da6a3ce929d0e0e4736
7ac84f32b8e42aa9c1e847d3b5f91045
...
✅ Grafana is ready
```

### 3. View Traces in Grafana

```bash
# Open Grafana dashboard
make grafana
```

Navigate to Explore → Select Tempo → Search for traces with:

- Service name: `symfony-otel-test`
- Operation: `execution_time`

### 4. Clear Data

```bash
# Clear all spans data (both Tempo and Grafana)
make clear-data
```

Expected output:

```
🗑️  Clearing all spans data from Tempo and Grafana...
Stopping services...
Removing data volumes...
Restarting services with clean data...
✅ All spans data cleared! Tempo and Grafana restarted with clean state
💡 You can now run tests to generate fresh trace data
```

### 5. Verify Data is Cleared

```bash
# Check status after clearing
make data-status
```

Expected output:

```
📊 Data Volume Status:
Docker Volumes:
symfony-otel-bundle_grafana-data
symfony-otel-bundle_tempo-data

✅ Tempo is ready
Recent Traces:
No traces found
✅ Grafana is ready
```

### 6. Generate Fresh Data

```bash
# Generate new traces
make test

# Verify new traces are collected
make data-status
```

## Use Cases

### Development Workflow

```bash
# Start development
make up

# Test your changes
make test

# Clear data for clean testing
make clear-data

# Test again with clean state
make test

# View results
make grafana
```

### Debugging Trace Issues

```bash
# Check if services are healthy
make health

# Check current trace count
make data-status

# Clear everything and test from scratch
make clear-data
make test

# If still no traces, do complete reset
make reset-all
```

### Performance Testing

```bash
# Start with clean state
make clear-data

# Run performance tests
make load-test

# Check results
make data-status
make grafana

# Clear for next test run
make clear-data
```

### CI/CD Integration

```bash
# In your CI/CD pipeline

# Start services
make up

# Clear any previous data
make clear-data

# Run tests
make test

# Verify traces were generated
make data-status

# Clean up
make down
```

## Command Comparison

| Command       | Tempo Data | Grafana Data | Containers    | Rebuild |
|---------------|------------|--------------|---------------|---------|
| `clear-data`  | ✅ Cleared  | ✅ Cleared    | ♻️ Restarted  | ❌ No    |
| `clear-tempo` | ✅ Cleared  | ❌ Kept       | ♻️ Tempo only | ❌ No    |
| `reset-all`   | ✅ Cleared  | ✅ Cleared    | ♻️ All        | ✅ Yes   |
| `clean`       | ✅ Cleared  | ✅ Cleared    | 🗑️ Removed   | ❌ No    |

## Tips

1. **Quick Fresh Start**: Use `make clear-data` for fast data clearing
2. **Keep Grafana Settings**: Use `make clear-tempo` to keep dashboards
3. **Complete Reset**: Use `make reset-all` for troubleshooting
4. **Status Checking**: Always use `make data-status` to verify changes
5. **Automation**: These commands work great in scripts and CI/CD

## Troubleshooting

### Command Fails

```bash
# If clear-data fails, try force removal
docker volume rm -f symfony-otel-bundle_tempo-data symfony-otel-bundle_grafana-data

# Then restart services
make up
```

### Services Won't Start

```bash
# Complete cleanup and rebuild
make clean
make up
```

### No Traces After Clearing

```bash
# Check service health
make health

# Verify configuration
make debug-otel

# Check if traces are being sent
make debug-traces
```

This data management system makes development and testing much more efficient by providing easy ways to clear and reset
trace data as needed. 

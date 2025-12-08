# k6 Go-Based Build

This directory contains the Dockerfile for building k6 from Go source using xk6.

## Overview

k6 is written in Go and uses JavaScript for test scripts. This Dockerfile builds k6 from source using xk6, which allows for:

- Custom k6 builds
- Integration of k6 extensions
- Latest k6 features
- Minimal Docker image size

## Building

The k6 binary is built using a multi-stage Docker build:

1. **Builder stage**: Uses `golang:1.21-alpine` to compile k6
2. **Runtime stage**: Uses minimal `alpine` image with only the k6 binary

## Adding Extensions

To add k6 extensions, modify the Dockerfile's `xk6 build` command:

```dockerfile
RUN xk6 build latest \
    --with github.com/grafana/xk6-sql@latest \
    --with github.com/grafana/xk6-redis@latest \
    --output /build/k6
```

Popular extensions:
- [xk6-sql](https://github.com/grafana/xk6-sql) - SQL database support
- [xk6-redis](https://github.com/grafana/xk6-redis) - Redis support
- [xk6-kafka](https://github.com/mostafa/xk6-kafka) - Kafka support
- [xk6-prometheus](https://github.com/grafana/xk6-output-prometheus-remote) - Prometheus output

See [k6 extensions](https://k6.io/docs/extensions/explore/) for more.

## Usage

The k6 service is configured in `docker-compose.yml` and uses this Dockerfile.

```bash
# Build the image
docker-compose build k6

# Run tests
docker-compose run --rm k6 run /scripts/all-scenarios-test.js
```

## Resources

- [k6 Documentation](https://k6.io/docs/)
- [xk6 GitHub](https://github.com/grafana/xk6)
- [k6 Extensions](https://k6.io/docs/extensions/)

# Troubleshooting

This page lists common symptoms you may encounter when integrating the Symfony OpenTelemetry Bundle, explains the likely
causes, and provides concrete fixes.

## No traces in Tempo / Grafana

- Symptom:
    - Grafana Explore (Tempo) shows no results for your service; traces are missing or intermittent.
- Common causes:
    - Wrong OTLP endpoint or protocol
    - Exporter disabled (e.g., `OTEL_TRACES_EXPORTER=none`)
    - Collector/Tempo is not reachable from the app container
    - Sampling too low (e.g., `traceidratio` with a very small ratio)
- Fix:
    - Verify environment variables in the running container:
      ```bash
      docker compose exec php-app env | grep OTEL_
      ```
    - Ensure exporter is enabled and points to the correct endpoint:
        - gRPC (recommended):
          ```bash
          OTEL_TRACES_EXPORTER=otlp
          OTEL_EXPORTER_OTLP_PROTOCOL=grpc
          OTEL_EXPORTER_OTLP_ENDPOINT=http://otel-collector:4317
          ```
        - HTTP/protobuf (fallback):
          ```bash
          OTEL_TRACES_EXPORTER=otlp
          OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf
          OTEL_EXPORTER_OTLP_ENDPOINT=http://otel-collector:4318
          OTEL_EXPORTER_OTLP_COMPRESSION=gzip
          ```
    - Confirm the collector/Tempo ports are exposed and healthy (see docs/docker.md):
      ```bash
      curl -sf http://localhost:3200/ready
      ```
    - Increase sampling temporarily to validate end-to-end flow:
      ```bash
      OTEL_TRACES_SAMPLER=always_on
      ```

## gRPC / protobuf extension missing

- Symptom:
    - PHP logs include errors such as `Class "Grpc\Channel" not found` or exporter fails to initialize with protocol
      `grpc`.
- Common causes:
    - `ext-grpc` is not installed/enabled in the runtime
    - `open-telemetry/transport-grpc` composer package missing
- Fix:
    - Install PHP gRPC extension and composer transport:
      ```bash
      pecl install grpc
      echo "extension=grpc.so" > /usr/local/etc/php/conf.d/ext-grpc.ini
      composer require open-telemetry/transport-grpc
      ```
    - If installing gRPC is not feasible (e.g., CI), switch to HTTP/protobuf + gzip:
      ```bash
      OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf
      OTEL_EXPORTER_OTLP_ENDPOINT=http://otel-collector:4318
      OTEL_EXPORTER_OTLP_COMPRESSION=gzip
      ```

## Collector endpoint wrong

- Symptom:
    - Exporter timeouts; logs show connection refused or DNS resolution errors.
- Common causes:
    - Using `localhost` inside a container instead of the service name
    - Wrong port for the chosen protocol
- Fix:
    - In Docker Compose, use service name and correct port:
        - gRPC: `http://otel-collector:4317`
        - HTTP:  `http://otel-collector:4318`
    - Outside Docker, if the collector runs locally on the host, use `http://127.0.0.1:4317` (gRPC) or `:4318` (HTTP)
      and ensure the ports are published.

## Symfony console commands not appearing

- Symptom:
    - Traces from web requests are visible, but `bin/console` commands don’t appear in Tempo.
- Common causes:
    - CLI process exits before the BatchSpanProcessor exports
    - Per-invocation environment lacks OTEL variables
    - Sampling excludes short-lived commands
- Fix:
    - Enable an explicit, bounded flush for CLI (keep disabled for FPM):
      ```yaml
      # config/packages/otel_bundle.yaml
      otel_bundle:
        force_flush_on_terminate: true
        force_flush_timeout_ms: 200
      ```
    - Ensure OTEL_* vars are present in the CLI environment (e.g., export in shell profile or use `env -i` wrappers).
    - For debugging, set `OTEL_TRACES_SAMPLER=always_on` while validating.

## Exporter appears to block request end

- Symptom:
    - Noticeable latency added at Symfony `kernel.terminate`.
- Common causes:
    - Per-request `shutdown()` or flushing with a large timeout
- Fix:
    - This bundle avoids `shutdown()` on request end and uses `forceFlush` only when explicitly enabled. Keep:
      ```yaml
      otel_bundle:
        force_flush_on_terminate: false
      ```
    - Tune BatchSpanProcessor via env vars:
      ```bash
      OTEL_BSP_SCHEDULE_DELAY=200
      OTEL_EXPORTER_OTLP_TIMEOUT=1000
      ```

## Still stuck?

- Check container logs for exporter errors
- Verify that traces reach the collector (`docker logs otel-collector`)
- Try the provided test endpoints in the Docker environment (docs/docker.md)
- Open an issue with logs and your config

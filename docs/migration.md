# Migration Guide: From OpenTelemetry Symfony SDK Bundle

This guide helps you migrate from the official OpenTelemetry Symfony SDK bundle to the Symfony OpenTelemetry Bundle
provided here. The goal is a smooth transition with minimal changes while preserving your existing `OTEL_*` environment
variables.

## Key Principles

- Transport-agnostic: we honor all standard `OTEL_*` variables
- BatchSpanProcessor preserved by default (no per-request shutdown)
- Symfony-focused DX: listeners, attributes, hooks, and helpers to reduce boilerplate

## What stays the same

- Your existing `OTEL_*` env vars continue to work (exporter, endpoint, protocol, sampling, propagators, etc.).
- You can keep your OTLP transport settings (gRPC or HTTP/protobuf).
- Existing tracers and processors defined via the SDK are respected.

## Configuration mapping (Before → After)

Before (plain SDK bundle):

```yaml
# config/packages/opentelemetry.yaml
opentelemetry:
  service_name: '%env(OTEL_SERVICE_NAME)%'
  propagators: 'tracecontext,baggage'
```

After (this bundle):

```yaml
# config/packages/otel_bundle.yaml
otel_bundle:
  service_name: '%env(OTEL_SERVICE_NAME)%'
  tracer_name: '%env(OTEL_TRACER_NAME)%'
  # Keep BSP async; do not flush on each request
  force_flush_on_terminate: false
  force_flush_timeout_ms: 100
  # (Optional) Built-in or custom instrumentations
  instrumentations:
    - 'Macpaw\SymfonyOtelBundle\Instrumentation\RequestExecutionTimeInstrumentation'
  # (Optional) Logging & metrics bridge
  logging:
    enable_trace_processor: true
  metrics:
    request_counters:
      enabled: false
      backend: 'otel'
```

Notes:

- You don’t need to duplicate `OTEL_*` variables in YAML; they are read by the OpenTelemetry SDK.
- Use this bundle’s options only for Symfony-specific behavior (flush policy, instrumentations, logging/metrics bridge).

## Services and tags

- This bundle auto-registers the core OpenTelemetry services and Symfony subscribers.
- Your app services continue to work; for fine-grained instrumentation, you can:
    - Add custom instrumentations (services implementing our instrumentation interfaces)
    - Use attributes: `#[TraceSpan('BusinessOperation')]` on handlers/controllers

## Per-request flush and performance

- If you previously called `shutdown()` at request end, remove it.
- This bundle defaults to not flushing per request to preserve `BatchSpanProcessor` async export. If you need fast
  delivery for CLI jobs, enable a bounded flush:

```yaml
otel_bundle:
  force_flush_on_terminate: true
  force_flush_timeout_ms: 200
```

## Transport (gRPC vs HTTP/protobuf)

- Keep your current transport via `OTEL_EXPORTER_OTLP_PROTOCOL` and `OTEL_EXPORTER_OTLP_ENDPOINT`.
- For gRPC, ensure the runtime has `ext-grpc` and `open-telemetry/transport-grpc` installed.
- Fallback to HTTP/protobuf + gzip if gRPC is unavailable.

## Rollout checklist

1. Enable the bundle and keep your existing `OTEL_*` env vars.
2. Start in a staging environment; verify traces flow to Tempo/Grafana.
3. Check Symfony profiler latency and ensure `kernel.terminate` isn’t doing heavy work.
4. Enable Logging & Metrics Bridge if desired.
5. Add attributes or custom hook instrumentations for critical business paths.

## FAQ

- Do I need to change exporter config? No, the SDK reads `OTEL_*` vars as before.
- What about sampling? Keep `OTEL_TRACES_SAMPLER` and `OTEL_TRACES_SAMPLER_ARG`.
- How to correlate logs? Enable the Monolog trace context processor in `otel_bundle.logging`.

If you encounter issues, see the [Troubleshooting](troubleshooting.md) page.

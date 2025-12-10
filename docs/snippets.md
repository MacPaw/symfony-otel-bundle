# Ready-made configuration snippets

Copy-paste friendly configs for common setups. Adjust service names/endpoints to your environment.

## Local development with docker-compose + Tempo

.env (app):

```bash
# Service identity
OTEL_SERVICE_NAME=symfony-otel-test
OTEL_TRACER_NAME=symfony-tracer

# Transport: gRPC (recommended)
OTEL_TRACES_EXPORTER=otlp
OTEL_EXPORTER_OTLP_PROTOCOL=grpc
OTEL_EXPORTER_OTLP_ENDPOINT=http://otel-collector:4317
OTEL_EXPORTER_OTLP_TIMEOUT=1000

# BatchSpanProcessor (async export)
OTEL_BSP_SCHEDULE_DELAY=200
OTEL_BSP_MAX_EXPORT_BATCH_SIZE=256
OTEL_BSP_MAX_QUEUE_SIZE=2048

# Propagators
OTEL_PROPAGATORS=tracecontext,baggage

# Dev sampler
OTEL_TRACES_SAMPLER=always_on
```

docker-compose (excerpt):

```yaml
services:
  php-app:
    environment:
      - OTEL_TRACES_EXPORTER=otlp
      - OTEL_EXPORTER_OTLP_PROTOCOL=grpc
      - OTEL_EXPORTER_OTLP_ENDPOINT=http://otel-collector:4317
      - OTEL_EXPORTER_OTLP_TIMEOUT=1000
      - OTEL_BSP_SCHEDULE_DELAY=200
      - OTEL_BSP_MAX_EXPORT_BATCH_SIZE=256
      - OTEL_BSP_MAX_QUEUE_SIZE=2048
  otel-collector:
    image: otel/opentelemetry-collector-contrib:latest
    volumes:
      - ./docker/otel-collector/otel-collector-config.yaml:/etc/otel-collector-config.yaml:ro
  tempo:
    image: grafana/tempo:latest
  grafana:
    image: grafana/grafana:latest
```

HTTP/protobuf fallback (if gRPC unavailable):

```bash
OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf
OTEL_EXPORTER_OTLP_ENDPOINT=http://otel-collector:4318
OTEL_EXPORTER_OTLP_COMPRESSION=gzip
```

## Kubernetes + Collector sidecar

Instrumentation via env only; keep bundle config minimal.

Deployment (snippet):

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: symfony-app
spec:
  selector:
    matchLabels:
      app: symfony-app
  template:
    metadata:
      labels:
        app: symfony-app
    spec:
      containers:
        - name: app
          image: your-registry/symfony-app:latest
          env:
            - name: OTEL_SERVICE_NAME
              value: symfony-app
            - name: OTEL_TRACES_EXPORTER
              value: otlp
            - name: OTEL_EXPORTER_OTLP_PROTOCOL
              value: grpc
            - name: OTEL_EXPORTER_OTLP_ENDPOINT
              value: http://localhost:4317
            - name: OTEL_EXPORTER_OTLP_TIMEOUT
              value: "1000"
            - name: OTEL_PROPAGATORS
              value: tracecontext,baggage
        - name: otel-collector
          image: otel/opentelemetry-collector-contrib:latest
          args: [ "--config=/etc/otel/config.yaml" ]
          volumeMounts:
            - name: otel-config
              mountPath: /etc/otel
      volumes:
        - name: otel-config
          configMap:
            name: otel-collector-config
```

Collector ConfigMap (excerpt):

```yaml
apiVersion: v1
kind: ConfigMap
metadata:
  name: otel-collector-config
data:
  config.yaml: |
    receivers:
      otlp:
        protocols:
          grpc:
          http:
    exporters:
      otlp:
        endpoint: tempo.tempo.svc.cluster.local:4317
        tls:
          insecure: true
    service:
      pipelines:
        traces:
          receivers: [otlp]
          exporters: [otlp]
```

## Monolith with multiple Symfony apps sharing a central collector

Each app identifies itself via `OTEL_SERVICE_NAME` and points to the same collector. Sampling can be tuned per app.

App A (.env):

```bash
OTEL_SERVICE_NAME=frontend
OTEL_TRACES_EXPORTER=otlp
OTEL_EXPORTER_OTLP_PROTOCOL=grpc
OTEL_EXPORTER_OTLP_ENDPOINT=http://otel-collector.monitoring.svc:4317
OTEL_TRACES_SAMPLER=traceidratio
OTEL_TRACES_SAMPLER_ARG=0.2
```

App B (.env):

```bash
OTEL_SERVICE_NAME=backend
OTEL_TRACES_EXPORTER=otlp
OTEL_EXPORTER_OTLP_PROTOCOL=grpc
OTEL_EXPORTER_OTLP_ENDPOINT=http://otel-collector.monitoring.svc:4317
OTEL_TRACES_SAMPLER=traceidratio
OTEL_TRACES_SAMPLER_ARG=0.05
```

Bundle YAML (shared baseline):

```yaml
# config/packages/otel_bundle.yaml
otel_bundle:
  service_name: '%env(OTEL_SERVICE_NAME)%'
  tracer_name: '%env(string:default:symfony-tracer:OTEL_TRACER_NAME)%'
  force_flush_on_terminate: false
  force_flush_timeout_ms: 100
  instrumentations:
    - 'Macpaw\\SymfonyOtelBundle\\Instrumentation\\RequestExecutionTimeInstrumentation'
  logging:
    enable_trace_processor: true
  metrics:
    request_counters:
      enabled: false
      backend: 'otel'
```

Notes:

- Keep `force_flush_on_terminate: false` for web apps to preserve BatchSpanProcessor async exporting.
- For CLI/cron jobs requiring fast delivery, temporarily enable force flush with a small timeout.

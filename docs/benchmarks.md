# Benchmarks

This document describes how to measure the overhead of the Symfony OpenTelemetry Bundle and provides a ready-to-run
PhpBench configuration and sample benchmark.

## What we measure

We focus on “overhead per HTTP request” for three scenarios:

- Symfony app baseline (bundle disabled)
- Bundle enabled with HTTP/protobuf exporter
- Bundle enabled with gRPC exporter

Each scenario is measured as wall-time and memory overhead around a simulated request lifecycle (REQUEST → TERMINATE),
without network variance (exporters can be stubbed or use an in-memory processor).

## Results (example placeholder)

|            Scenario | Mean (µs) | StdDev (µs) | Relative |
|--------------------:|----------:|------------:|---------:|
| Baseline (disabled) |       350 |          15 |    1.00x |
|      Enabled (HTTP) |       520 |          22 |    1.49x |
|      Enabled (gRPC) |       480 |          20 |    1.37x |

Notes:

- Replace these numbers with your environment’s measurements. Network/exporter configuration affects results.

## How to run

1) Install PhpBench (dev):

```bash
composer require --dev phpbench/phpbench
```

2) Run benchmarks:

```bash
./vendor/bin/phpbench run benchmarks --report=aggregate
```

3) Toggle scenarios:

- Disable bundle globally:
  ```bash
  export OTEL_ENABLED=0
  ```
- Enable bundle and choose transport via env vars (see README Transport Configuration):
  ```bash
  export OTEL_ENABLED=1
  export OTEL_TRACES_EXPORTER=otlp
  export OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf   # or grpc
  ```

## Bench scaffold

- `benchmarks/phpbench.json` — PhpBench configuration
- `benchmarks/HttpRequestOverheadBench.php` — sample benchmark that bootstraps minimal services and simulates a request
  lifecycle

The example benchmark avoids hitting a real collector by using an in-memory processor when possible.

## Tips

- Pin CPU governor to performance mode for consistent results
- Run multiple iterations and discard outliers
- Use Docker `--cpuset-cpus` and limit background noise
- For gRPC exporter, ensure the extension is prebuilt in your image to avoid installation overhead during runs

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

```
    benchSimpleSpanCreation.................R3 I9 - Mo45.547534μs (±1.39%)
    benchSpanWithAttributes.................R2 I9 - Mo55.846673μs (±1.54%)
    benchNestedSpans........................R2 I9 - Mo152.456967μs (±1.91%)
    benchSpanWithEvents.....................R1 I8 - Mo76.457984μs (±0.90%)
    benchMultipleSpansSequential............R1 I3 - Mo461.512524μs (±2.07%)
    benchComplexSpanHierarchy...............R1 I5 - Mo169.179217μs (±0.76%)
    benchSpanExport.........................R2 I6 - Mo257.052466μs (±1.96%)
    benchHighAttributeCount.................R1 I3 - Mo85.769393μs (±1.79%)
    benchSpanWithLargeAttributes............R1 I2 - Mo56.852877μs (±1.93%)
    benchDeeplyNestedSpans..................R5 I9 - Mo302.831155μs (±1.57%)
```

+---------------------+------------------------------+-----+------+-----+----------+--------------+--------+
| benchmark           | subject                      | set | revs | its | mem_peak | mode         | rstdev |
+---------------------+------------------------------+-----+------+-----+----------+--------------+--------+
| BundleOverheadBench | benchSimpleSpanCreation      |     | 100  | 10  | 6.594mb  | 45.547534μs  | ±1.39% |
| BundleOverheadBench | benchSpanWithAttributes      |     | 100  | 10  | 6.632mb  | 55.846673μs  | ±1.54% |
| BundleOverheadBench | benchNestedSpans             |     | 100  | 10  | 6.842mb  | 152.456967μs | ±1.91% |
| BundleOverheadBench | benchSpanWithEvents          |     | 100  | 10  | 6.761mb  | 76.457984μs  | ±0.90% |
| BundleOverheadBench | benchMultipleSpansSequential |     | 100  | 10  | 8.121mb  | 461.512524μs | ±2.07% |
| BundleOverheadBench | benchComplexSpanHierarchy    |     | 100  | 10  | 6.958mb  | 169.179217μs | ±0.76% |
| BundleOverheadBench | benchSpanExport              |     | 100  | 10  | 7.300mb  | 257.052466μs | ±1.96% |
| BundleOverheadBench | benchHighAttributeCount      |     | 100  | 10  | 6.885mb  | 85.769393μs  | ±1.79% |
| BundleOverheadBench | benchSpanWithLargeAttributes |     | 100  | 10  | 7.181mb  | 56.852877μs  | ±1.93% |
| BundleOverheadBench | benchDeeplyNestedSpans       |     | 100  | 10  | 7.298mb  | 302.831155μs | ±1.57% |
+---------------------+------------------------------+-----+------+-----+----------+--------------+--------+

```

    benchSimpleSpanCreation.................R2 I8 - Mo45.587123μs (±1.57%)
    benchSpanWithAttributes.................R1 I8 - Mo56.050528μs (±1.43%)
    benchNestedSpans........................R1 I1 - Mo154.424168μs (±1.47%)
    benchSpanWithEvents.....................R1 I4 - Mo77.123151μs (±1.34%)
    benchMultipleSpansSequential............R1 I7 - Mo483.122329μs (±1.44%)
    benchComplexSpanHierarchy...............R1 I6 - Mo171.341918μs (±1.60%)
    benchSpanExport.........................R2 I9 - Mo244.932661μs (±1.15%)
    benchHighAttributeCount.................R2 I9 - Mo81.938337μs (±1.49%)
    benchSpanWithLargeAttributes............R1 I8 - Mo54.346027μs (±1.31%)
    benchDeeplyNestedSpans..................R1 I8 - Mo292.023738μs (±1.41%)
```

Subjects: 10, Assertions: 0, Failures: 0, Errors: 0
+---------------------+------------------------------+-----+------+-----+----------+--------------+--------+
| benchmark           | subject                      | set | revs | its | mem_peak | mode         | rstdev |
+---------------------+------------------------------+-----+------+-----+----------+--------------+--------+
| BundleOverheadBench | benchSimpleSpanCreation      |     | 100  | 10  | 6.594mb  | 45.587123μs  | ±1.57% |
| BundleOverheadBench | benchSpanWithAttributes      |     | 100  | 10  | 6.632mb  | 56.050528μs  | ±1.43% |
| BundleOverheadBench | benchNestedSpans             |     | 100  | 10  | 6.842mb  | 154.424168μs | ±1.47% |
| BundleOverheadBench | benchSpanWithEvents          |     | 100  | 10  | 6.761mb  | 77.123151μs  | ±1.34% |
| BundleOverheadBench | benchMultipleSpansSequential |     | 100  | 10  | 8.121mb  | 483.122329μs | ±1.44% |
| BundleOverheadBench | benchComplexSpanHierarchy    |     | 100  | 10  | 6.958mb  | 171.341918μs | ±1.60% |
| BundleOverheadBench | benchSpanExport              |     | 100  | 10  | 7.300mb  | 244.932661μs | ±1.15% |
| BundleOverheadBench | benchHighAttributeCount      |     | 100  | 10  | 6.885mb  | 81.938337μs  | ±1.49% |
| BundleOverheadBench | benchSpanWithLargeAttributes |     | 100  | 10  | 7.181mb  | 54.346027μs  | ±1.31% |
| BundleOverheadBench | benchDeeplyNestedSpans       |     | 100  | 10  | 7.298mb  | 292.023738μs | ±1.41% |
+---------------------+------------------------------+-----+------+-----+----------+--------------+--------+


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

# Symfony Flex Recipe

This bundle ships with a Symfony Flex recipe to provide a frictionless install and a "works out of the box" experience.

## What you get with `composer require macpaw/symfony-otel-bundle`

When the recipe is available via `symfony/recipes-contrib` and your project uses Flex:

- `config/packages/otel_bundle.yaml` — sane defaults that preserve BatchSpanProcessor (async export)
- `config/routes/otel_health.yaml` — a simple health route mapped to a built‑in controller
- `.env` — commented `OTEL_*` environment variables appended with recommended defaults

These files are safe to edit. The recipe writes them once; later updates are managed by you.

## Health endpoint

The recipe maps `/_otel/health` to the bundle's controller `Macpaw\\SymfonyOtelBundle\\Controller\\HealthController`.

- Useful to immediately see a trace in Grafana/Tempo after installation
- Returns a minimal JSON payload:

```json
{
    "status": "ok",
    "service": "<env OTEL_SERVICE_NAME>",
    "time": "2025-01-01T00:00:00+00:00"
}
```

You can change the path or remove the route if you don't need it.

## Environment variables

The recipe appends commented `OTEL_*` variables to your `.env`, including:

- gRPC transport (recommended) and HTTP/protobuf fallback
- BSP (BatchSpanProcessor) tuning to keep exports asynchronous

Uncomment and adjust based on your environment.

## Publishing the recipe

If you maintain a fork or wish to contribute:

1. Ensure this repository contains the recipe directory structure:
    - `recipes/macpaw/symfony-otel-bundle/0.1/manifest.json`
2. Submit the recipe to `symfony/recipes-contrib` following their contribution guide. Point to your package and tag.
3. After merge, projects using Flex will receive the recipe automatically on `composer require`.

## FAQ

- Does the recipe force a transport? No. It only suggests env vars; the OpenTelemetry SDK reads whatever `OTEL_*` vars
  you set.
- Will it flush on each request? No. Defaults keep `force_flush_on_terminate: false` to preserve async export.
- Can I customize the health route? Yes. Change `config/routes/otel_health.yaml` or remove it.

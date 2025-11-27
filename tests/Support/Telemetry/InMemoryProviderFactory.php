<?php

declare(strict_types=1);

namespace Tests\Support\Telemetry;

use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SDK\Trace\TracerProviderInterface;

final class InMemoryProviderFactory
{
    private static ?InMemoryExporter $exporter = null;
    private static ?TracerProviderInterface $provider = null;

    public static function create(): TracerProviderInterface
    {
        if (self::$provider instanceof TracerProviderInterface) {
            return self::$provider;
        }

        self::$exporter = new InMemoryExporter();
        $processor = new SimpleSpanProcessor(self::$exporter);
        self::$provider = TracerProvider::builder()
            ->addSpanProcessor($processor)
            ->build();

        return self::$provider;
    }

    public static function getExporter(): ?InMemoryExporter
    {
        return self::$exporter;
    }

    public static function reset(): void
    {
        // Recreate exporter and provider on next create()
        self::$exporter = null;
        self::$provider = null;
    }
}

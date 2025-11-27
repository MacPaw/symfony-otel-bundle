<?php

declare(strict_types=1);

namespace Macpaw\SymfonyOtelBundle\Service;

use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\SDK\Trace\TracerProviderInterface;

readonly class TraceService
{
    public function __construct(
        private TracerProviderInterface $tracerProvider,
        private string $serviceName,
        private string $tracerName,
    ) {
    }

    public function getTracer(?string $name = null): TracerInterface
    {
        return $this->tracerProvider->getTracer($name ?? $this->tracerName);
    }

    public function getServiceName(): string
    {
        return $this->serviceName;
    }

    public function getTracerName(): string
    {
        return $this->tracerName;
    }

    public function shutdown(): void
    {
        $this->tracerProvider->shutdown();
    }

    public function forceFlush(int $timeoutMs = 200): void
    {
        // Prefer a bounded, non-destructive flush over shutdown per request
        if (method_exists($this->tracerProvider, 'forceFlush')) {
            // @phpstan-ignore-next-line method exists at runtime on SDK provider
            $this->tracerProvider->forceFlush($timeoutMs);
        }
    }
}

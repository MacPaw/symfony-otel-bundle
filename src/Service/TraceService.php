<?php

declare(strict_types=1);

namespace Macpaw\SymfonyOtelBundle\Service;

use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\SDK\Trace\TracerProviderInterface;

readonly class TraceService
{
    public function __construct(private TracerProviderInterface $tracerProvider)
    {
    }

    public function getTracer(string $name): TracerInterface
    {
        return $this->tracerProvider->getTracer($name);
    }

    public function shutdown(): void
    {
        $this->tracerProvider->shutdown();
    }
}

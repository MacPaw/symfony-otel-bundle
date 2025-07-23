<?php

declare(strict_types=1);

namespace App\Middleware;

use Macpaw\SymfonyOtelBundle\Instrumentation\HookInstrumentationInterface;
use Macpaw\SymfonyOtelBundle\Instrumentation\TimingInterface;
use Macpaw\SymfonyOtelBundle\Middleware\ClassHookInstrumentationSpanMiddlewareInterface;
use OpenTelemetry\API\Common\Time\ClockInterface;
use OpenTelemetry\API\Trace\SpanInterface;

final class ExampleQueryBusAttributesSpanMiddleware implements ClassHookInstrumentationSpanMiddlewareInterface
{
    public function __construct(private readonly ClockInterface $clock)
    {
    }

    public function pre(SpanInterface $span, HookInstrumentationInterface&TimingInterface $instrumentation): void
    {
        $span->setAttribute('query_bus.class', $instrumentation->getClass());
        $span->setAttribute('query_bus.method', $instrumentation->getMethod());
    }

    public function post(SpanInterface $span, HookInstrumentationInterface&TimingInterface $instrumentation): void
    {
        $executionTime = $this->clock->now();

        $span->setAttribute('query_bus.execution_time_ns', $executionTime);
    }
}

<?php

declare(strict_types=1);

namespace App\Middleware;

use Macpaw\SymfonyOtelBundle\Instrumentation\HookInstrumentationInterface;
use Macpaw\SymfonyOtelBundle\Instrumentation\TimingInterface;
use Macpaw\SymfonyOtelBundle\Middleware\ClassHookInstrumentationSpanMiddlewareInterface;
use OpenTelemetry\API\Common\Time\ClockInterface;
use OpenTelemetry\API\Trace\SpanInterface;

final class ExampleCommandBusAttributesSpanMiddleware implements ClassHookInstrumentationSpanMiddlewareInterface
{
    public function __construct(private readonly ClockInterface $clock)
    {
    }

    public function pre(SpanInterface $span, HookInstrumentationInterface&TimingInterface $instrumentation): void
    {
        $span->setAttribute('command_bus.class', $instrumentation->getClass());
        $span->setAttribute('command_bus.method', $instrumentation->getMethod());
    }

    public function post(SpanInterface $span, HookInstrumentationInterface&TimingInterface $instrumentation): void
    {
        $executionTime = $this->clock->now();

        $span->setAttribute('command_bus.execution_time_ns', $executionTime);
    }
}

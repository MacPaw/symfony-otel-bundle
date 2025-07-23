<?php

declare(strict_types=1);

namespace App\Middleware;

use Macpaw\SymfonyOtelBundle\Instrumentation\HookInstrumentationInterface;
use Macpaw\SymfonyOtelBundle\Instrumentation\TimingInterface;
use Macpaw\SymfonyOtelBundle\Middleware\ClassHookInstrumentationSpanMiddlewareInterface;
use OpenTelemetry\API\Trace\SpanInterface;

final class ExampleAttributesSpanMiddleware implements ClassHookInstrumentationSpanMiddlewareInterface
{
    public function pre(SpanInterface $span, HookInstrumentationInterface&TimingInterface $instrumentation): void
    {
        $span->setAttribute('query_bus.class', $instrumentation->getClass());
        $span->setAttribute('query_bus.method', $instrumentation->getMethod());
    }

    public function post(SpanInterface $span, HookInstrumentationInterface&TimingInterface $instrumentation): void
    {
        $span->setAttribute('query_bus.execution_time_ns', $instrumentation->getExecutionTime());
    }
}

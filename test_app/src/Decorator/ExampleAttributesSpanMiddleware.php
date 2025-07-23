<?php

declare(strict_types=1);

namespace App\Decorator;

use Macpaw\SymfonyOtelBundle\Instrumentation\ClassHookInstrumetationSpanDecoratorInterface;
use Macpaw\SymfonyOtelBundle\Instrumentation\HookInstrumentationInterface;
use Macpaw\SymfonyOtelBundle\Instrumentation\TimingInterface;
use OpenTelemetry\API\Trace\SpanInterface;

final class ExampleAttributesSpanMiddleware implements ClassHookInstrumetationSpanDecoratorInterface
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

<?php

declare(strict_types=1);

namespace App\Decorator;

use Macpaw\SymfonyOtelBundle\Instrumentation\ClassHookInstrumetationSpanDecoratorInterface;
use Macpaw\SymfonyOtelBundle\Instrumentation\HookInstrumentationInterface;
use Macpaw\SymfonyOtelBundle\Instrumentation\TimingInterface;
use OpenTelemetry\API\Trace\SpanInterface;

final class ExampleEventsSpanMiddleware implements ClassHookInstrumetationSpanDecoratorInterface
{
    public function pre(SpanInterface $span, HookInstrumentationInterface&TimingInterface $instrumentation): void
    {
        $span->addEvent('Query bus execution started', [
            'query_bus.operation_type' => 'query',
            'timestamp' => $instrumentation->getStartTime(),
        ]);
    }

    public function post(SpanInterface $span, HookInstrumentationInterface&TimingInterface $instrumentation): void
    {
        $span->addEvent('Query bus execution completed', [
            'query_bus.operation_type' => 'query',
            'execution_time_ns' => $instrumentation->getExecutionTime(),
        ]);
    }
}

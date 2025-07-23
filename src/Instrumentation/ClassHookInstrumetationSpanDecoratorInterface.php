<?php

declare(strict_types=1);

namespace Macpaw\SymfonyOtelBundle\Instrumentation;

use OpenTelemetry\API\Trace\SpanInterface;

interface ClassHookInstrumetationSpanDecoratorInterface
{
    public function pre(SpanInterface $span, HookInstrumentationInterface&TimingInterface $instrumentation): void;

    public function post(SpanInterface $span, HookInstrumentationInterface&TimingInterface $instrumentation): void;
}

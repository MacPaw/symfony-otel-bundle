<?php

declare(strict_types=1);

namespace Macpaw\SymfonyOtelBundle\Middleware;

use Macpaw\SymfonyOtelBundle\Instrumentation\HookInstrumentationInterface;
use Macpaw\SymfonyOtelBundle\Instrumentation\TimingInterface;
use OpenTelemetry\API\Trace\SpanInterface;

interface ClassHookInstrumentationSpanMiddlewareInterface
{
    public function pre(SpanInterface $span, HookInstrumentationInterface&TimingInterface $instrumentation): void;

    public function post(SpanInterface $span, HookInstrumentationInterface&TimingInterface $instrumentation): void;
}

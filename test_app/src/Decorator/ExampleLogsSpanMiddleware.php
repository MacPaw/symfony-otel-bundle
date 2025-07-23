<?php

declare(strict_types=1);

namespace App\Decorator;

use Macpaw\SymfonyOtelBundle\Instrumentation\ClassHookInstrumetationSpanDecoratorInterface;
use Macpaw\SymfonyOtelBundle\Instrumentation\HookInstrumentationInterface;
use Macpaw\SymfonyOtelBundle\Instrumentation\TimingInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use Psr\Log\LoggerInterface;

final class ExampleLogsSpanMiddleware implements ClassHookInstrumetationSpanDecoratorInterface
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function pre(SpanInterface $span, HookInstrumentationInterface&TimingInterface $instrumentation): void
    {
        $this->logger->info('Span init');
    }

    public function post(SpanInterface $span, HookInstrumentationInterface&TimingInterface $instrumentation): void
    {
        $this->logger->info('Span post');
    }
}

<?php

declare(strict_types=1);

namespace Macpaw\SymfonyOtelBundle\Instrumentation;

use OpenTelemetry\API\Trace\SpanInterface;

interface ClassHookInstrumetationSpanDecoratorInterface
{
    /**
     * Init span.
     */
    public function decorateSpanInit(SpanInterface $spanBuilder): void;

    /**
     * Finish span.
     */
    public function postSpanDecoration(SpanInterface $spanBuilder): void;
}

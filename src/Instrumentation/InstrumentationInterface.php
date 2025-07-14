<?php

declare(strict_types=1);

namespace Macpaw\SymfonyOtelBundle\Instrumentation;

interface InstrumentationInterface
{
    /**
     * Init span.
     */
    public function pre(): void;

    /**
     * Finish span.
     */
    public function post(): void;

    /**
     * Span name.
     */
    public function getName(): string;
}

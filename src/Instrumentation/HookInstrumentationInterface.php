<?php

declare(strict_types=1);

namespace Macpaw\SymfonyOtelBundle\Instrumentation;

interface HookInstrumentationInterface extends InstrumentationInterface
{
    /**
     * Hook class.
     */
    public function getClass(): ?string;

    /**
     * Hook method. If class is null, attached to predefined function.
     */
    public function getMethod(): string;
}

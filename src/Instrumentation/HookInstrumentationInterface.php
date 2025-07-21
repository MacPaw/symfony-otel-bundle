<?php

declare(strict_types=1);

namespace Macpaw\SymfonyOtelBundle\Instrumentation;

interface HookInstrumentationInterface extends InstrumentationInterface
{
    /**
     * Hook class.
     * 
     * @return class-string|null
     */
    public function getClass(): ?string;

    /**
     * Hook method. If class is null, attached to predefined function.
     * 
     * @return non-empty-string
     */
    public function getMethod(): string;
}

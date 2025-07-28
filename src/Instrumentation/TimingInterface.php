<?php

declare(strict_types=1);

namespace Macpaw\SymfonyOtelBundle\Instrumentation;

interface TimingInterface
{
    public function getStartTime(): int;

    public function getEndTime(): int;

    public function getExecutionTime(): int;
}

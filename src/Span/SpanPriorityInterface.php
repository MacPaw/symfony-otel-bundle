<?php

declare(strict_types=1);

namespace Macpaw\SymfonyOtelBundle\Span;

interface SpanPriorityInterface extends SpanInterface
{
    public function getPriority(): int;
}

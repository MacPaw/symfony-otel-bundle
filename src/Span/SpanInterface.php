<?php

declare(strict_types=1);

namespace Macpaw\SymfonyOtelBundle\Span;

interface SpanInterface
{
    public function getName(): string;
}

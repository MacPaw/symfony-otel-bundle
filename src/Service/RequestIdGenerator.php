<?php

declare(strict_types=1);

namespace Macpaw\SymfonyOtelBundle\Service;

use Ramsey\Uuid\Uuid;

class RequestIdGenerator
{
    public static function generate(): string
    {
        return Uuid::uuid4()->toString();
    }
}

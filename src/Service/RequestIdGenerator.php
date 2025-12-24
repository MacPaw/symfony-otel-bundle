<?php

declare(strict_types=1);

namespace Macpaw\SymfonyOtelBundle\Service;

use Symfony\Component\Uid\Uuid;

class RequestIdGenerator
{
    public static function generate(): string
    {
        return Uuid::v4()->toRfc4122();
    }
}

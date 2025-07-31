<?php

declare(strict_types=1);

namespace App\Infrastructure\MessageBus;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
interface QueryHandlerInterface
{
}

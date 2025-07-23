<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class DummyCommandHandler
{
    public function __invoke(DummyCommand $command): void
    {
    }
}
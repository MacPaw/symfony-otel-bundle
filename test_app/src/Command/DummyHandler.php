<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class DummyHandler
{
    public function __invoke(DummyCommand $command): void
    {
    }
}
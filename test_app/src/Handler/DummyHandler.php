<?php

namespace App\Handler;

use App\Command\DummyCommand;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class DummyHandler
{
    public function __invoke(DummyCommand $command)
    {
    }
}
<?php

namespace App\Handler;

use App\Command\DummyCommand;

class DummyHandler
{
    public function __invoke(DummyCommand $command)
    {
    }
}
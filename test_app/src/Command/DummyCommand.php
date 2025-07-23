<?php

declare(strict_types=1);

namespace App\Command;

use App\Infrastructure\MessageBus\CommandMessageInterface;

class DummyCommand implements CommandMessageInterface
{
}
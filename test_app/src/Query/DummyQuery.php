<?php

declare(strict_types=1);

namespace App\Query;

use App\Infrastructure\MessageBus\QueryMessageInterface;

class DummyQuery implements QueryMessageInterface
{
} 
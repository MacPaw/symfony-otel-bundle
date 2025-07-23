<?php

declare(strict_types=1);

namespace App\Query;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class DummyQueryHandler
{
    public function __invoke(DummyQuery $query): ?string
    {
        // Simulate some query processing
        return 'Query result for ' . DummyQuery::class;
    }
} 
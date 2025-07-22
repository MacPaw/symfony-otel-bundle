<?php

declare(strict_types=1);

namespace App\Infrastructure\MessageBus;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @template T
 */
class QueryBus implements MessageBusInterface
{
    use HandleTrait;

    public function __construct(MessageBusInterface $queryBus)
    {
        $this->messageBus = $queryBus;
    }

    /**
     * @return T
     */
    public function query(QueryMessageInterface $queryMessage): mixed
    {
        return $this->handle($queryMessage);
    }

    public function dispatch(object $message, array $stamps = []): Envelope
    {
        return $this->messageBus->dispatch($message, $stamps);
    }
}

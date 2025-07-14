<?php

declare(strict_types=1);

namespace Macpaw\SymfonyOtelBundle\Listeners;

use Macpaw\SymfonyOtelBundle\Registry\InstrumentationRegistry;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class SpansEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private InstrumentationRegistry $spanRegistry,
    ) {
    }

    public function onKernelTerminate(): void
    {
        foreach ($this->spanRegistry->getSpans() as $span) {
            $span->end();
        }
    }

    /**
     * @return array<string, array<string|int>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::TERMINATE => [
                'onKernelTerminate',
                PHP_INT_MAX,
            ],
        ];
    }
}

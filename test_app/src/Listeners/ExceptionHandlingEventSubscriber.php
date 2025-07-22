<?php

declare(strict_types=1);

namespace App\Listeners;

use Macpaw\SymfonyOtelBundle\Registry\InstrumentationRegistry;
use Macpaw\SymfonyOtelBundle\Service\TraceService;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SemConv\TraceAttributes;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Throwable;

final readonly class ExceptionHandlingEventSubscriber implements EventSubscriberInterface
{
    public function __construct(

    ) {
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        dd('!');
        $throwable = $event->getThrowable();

    }

    /**
     * @return array<string, array<int|string>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', PHP_INT_MAX],
        ];
    }
}

<?php

declare(strict_types=1);

namespace Macpaw\SymfonyOtelBundle\Span;

use Macpaw\SymfonyOtelBundle\Instrumentation\ExecutionTimeInstrumentation;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class InstrumentationEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ExecutionTimeInstrumentation $executionTimeInstrumentation,
    ) {
    }

    public function onKernelRequestExecutionTime(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $this->executionTimeInstrumentation->setHeaders($request->headers->all());
        $this->executionTimeInstrumentation->pre();
    }

    public function onKernelTerminateExecutionTime(TerminateEvent $event): void
    {
        $this->executionTimeInstrumentation->post();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [
                ['onKernelRequestExecutionTime', -PHP_INT_MAX + 2],
            ],
            KernelEvents::TERMINATE => [
                ['onKernelTerminateExecutionTime', PHP_INT_MAX],
            ],
        ];
    }
}

<?php

declare(strict_types=1);

namespace Macpaw\SymfonyOtelBundle\Span;

use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Macpaw\SymfonyOtelBundle\Service\TraceService;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;

final class ExecutionTimeSpanTracer implements EventSubscriberInterface
{
    public const NAME = 'execution_time';

    private float $startTime;

    private ?SpanInterface $contextSpan = null;

    public function __construct(
        private readonly TraceService $traceService,
        private readonly TextMapPropagatorInterface $propagator,
        private readonly string $tracerName,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $headers = $request->headers->all();
        $context = $this->propagator->extract($headers);

        $spanInjectedContext = Span::fromContext($context)->getContext();

        if ($spanInjectedContext->isValid() === false) {
            return;
        }

        $this->startTime = microtime(true);

        $this->contextSpan = $this->traceService
            ->getTracer($this->tracerName)
            ->spanBuilder(self::NAME)
            ->setParent($context)
            ->startSpan();
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        if ($this->contextSpan === null) {
            return;
        }

        $executionTime = microtime(true) - $this->startTime;
        $scope = $this->contextSpan->activate();
        try {
            $this->contextSpan->addEvent(
                sprintf('Execution time: %f seconds', $executionTime),
            );
            $this->contextSpan->end();
        } finally {
            $scope->detach();
            $this->traceService->shutdown();
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
            KernelEvents::TERMINATE => 'onKernelTerminate',
        ];
    }
}

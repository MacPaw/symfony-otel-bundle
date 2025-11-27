<?php

declare(strict_types=1);

namespace Macpaw\SymfonyOtelBundle\Listeners;

use Macpaw\SymfonyOtelBundle\Registry\InstrumentationRegistry;
use Macpaw\SymfonyOtelBundle\Registry\SpanNames;
use Macpaw\SymfonyOtelBundle\Service\HttpMetadataAttacher;
use Macpaw\SymfonyOtelBundle\Service\TraceService;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;
use OpenTelemetry\Context\ScopeInterface;
use OpenTelemetry\SemConv\TraceAttributes;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class RequestRootSpanEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private InstrumentationRegistry $instrumentationRegistry,
        private TextMapPropagatorInterface $propagator,
        private TraceService $traceService,
        private HttpMetadataAttacher $httpMetadataAttacher,
        private bool $forceFlushOnTerminate = false,
        private int $forceFlushTimeoutMs = 100,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $context = $this->propagator->extract($event->getRequest()->headers->all());
        $spanInjectedContext = Span::fromContext($context)->getContext();

        $context = $spanInjectedContext->isValid() ? $context : Context::getCurrent();

        $this->instrumentationRegistry->setContext($context);

        $request = $event->getRequest();

        $spanBuilder = $this->traceService
            ->getTracer()
            ->spanBuilder(sprintf('%s %s', $request->getMethod(), $request->getPathInfo()))
            ->setParent($context)
            ->setAttribute(TraceAttributes::HTTP_REQUEST_METHOD, $request->getMethod())
            ->setAttribute(TraceAttributes::HTTP_ROUTE, $request->getPathInfo())
            ->setAttribute(TraceAttributes::URL_SCHEME, $request->getScheme())
            ->setAttribute(TraceAttributes::SERVER_ADDRESS, $request->getHost());

        $this->httpMetadataAttacher->addHttpAttributes($spanBuilder, $request);
        $this->httpMetadataAttacher->addRouteNameAttribute($spanBuilder);
        $this->httpMetadataAttacher->addControllerAttributes($spanBuilder, $request);

        $requestStartSpan = $spanBuilder->startSpan();
        $this->instrumentationRegistry->addSpan($requestStartSpan, SpanNames::REQUEST_START);

        $this->instrumentationRegistry->setScope($requestStartSpan->activate());
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        $requestStartSpan = $this->instrumentationRegistry->getSpan(SpanNames::REQUEST_START);
        if ($requestStartSpan instanceof SpanInterface) {
            $response = $event->getResponse();
            $requestStartSpan->setAttribute(TraceAttributes::HTTP_RESPONSE_STATUS_CODE, $response->getStatusCode());
        }

        $scope = $this->instrumentationRegistry->getScope();
        if ($scope instanceof ScopeInterface) {
            $scope->detach();
        }

        foreach ($this->instrumentationRegistry->getSpans() as $span) {
            $span->end();
        }

        // Preserve BatchSpanProcessor benefits: flush only when explicitly enabled
        if ($this->forceFlushOnTerminate) {
            $this->traceService->forceFlush($this->forceFlushTimeoutMs);
        }
    }

    /**
     * @return array<string, array<int|string>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', PHP_INT_MAX],
            KernelEvents::TERMINATE => ['onKernelTerminate', PHP_INT_MAX],
        ];
    }
}

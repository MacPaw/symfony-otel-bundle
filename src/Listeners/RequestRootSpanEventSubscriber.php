<?php

declare(strict_types=1);

namespace Macpaw\SymfonyOtelBundle\Listeners;

use Macpaw\SymfonyOtelBundle\Instrumentation\Utils\RouterUtils;
use Macpaw\SymfonyOtelBundle\Registry\InstrumentationRegistry;
use Macpaw\SymfonyOtelBundle\Service\TraceService;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;
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
        private RouterUtils $routerUtils
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

        $rootSpan = $spanBuilder->startSpan();
        $this->instrumentationRegistry->addSpan($rootSpan, 'root_span');

        $this->instrumentationRegistry->setScope($rootSpan->activate());
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        $request = $event->getRequest();
        $routeName = $this->routerUtils->getRouteName();
        $rootSpan = $this->instrumentationRegistry->getSpans()['root_span'] ?? null;
        if ($rootSpan !== null) {
            $response = $event->getResponse();
            $rootSpan->updateName(sprintf('%s %s', $request->getMethod(), $routeName))
                ->setAttribute(TraceAttributes::HTTP_RESPONSE_STATUS_CODE, $response->getStatusCode());
        }

        $scope = $this->instrumentationRegistry->getScope();
        if ($scope !== null) {
            $scope->detach();
        }

        foreach ($this->instrumentationRegistry->getSpans() as $span) {
            $span->end();
        }

        $this->traceService->shutdown();
    }

    /**
     * @return array<string, array<string|int>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [
                ['onKernelRequest', PHP_INT_MAX],
            ],
            KernelEvents::TERMINATE => [
                ['onKernelTerminate', PHP_INT_MAX],
            ],
        ];
    }
}

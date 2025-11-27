<?php

declare(strict_types=1);

namespace Macpaw\SymfonyOtelBundle\Listeners;

use Macpaw\SymfonyOtelBundle\Instrumentation\Utils\RouterUtils;
use Macpaw\SymfonyOtelBundle\Registry\InstrumentationRegistry;
use Macpaw\SymfonyOtelBundle\Registry\SpanNames;
use OpenTelemetry\API\Metrics\CounterInterface;
use OpenTelemetry\API\Metrics\MeterInterface;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use OpenTelemetry\SemConv\TraceAttributes;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Throwable;

/**
 * Optional request counters middleware.
 *
 * When enabled, increments cheap counters for:
 *  - request_count per route/method
 *  - response family count per status (1xx,2xx,3xx,4xx,5xx)
 *
 * Backend:
 *  - 'otel' (default): use OpenTelemetry Metrics API when available
 *  - 'event': fallback to adding a tiny event on the root request span
 */
final class RequestCountersEventSubscriber implements EventSubscriberInterface
{
    private readonly LoggerInterface $logger;

    private ?MeterInterface $meter = null;
    private ?CounterInterface $requestCounter = null;
    private ?CounterInterface $responseFamilyCounter = null;

    /** @var 'otel'|'event' */
    private string $backend;

    public function __construct(
        MeterProviderInterface $meterProvider,
        private readonly RouterUtils $routerUtils,
        private readonly InstrumentationRegistry $instrumentationRegistry,
        string $backend = 'otel',
        ?LoggerInterface $logger = null,
    ) {
        $this->backend = in_array($backend, ['otel', 'event'], true) ? $backend : 'otel';
        $this->logger = $logger ?? new NullLogger();

        // Try to initialize metrics instruments
        if ($this->backend === 'otel') {
            try {
                $this->meter = $meterProvider->getMeter('symfony-otel-bundle');
                $this->requestCounter = $this->meter->createCounter(
                    'http.server.request.count',
                    unit: '1',
                    description: 'HTTP server requests',
                );
                $this->responseFamilyCounter = $this->meter->createCounter(
                    'http.server.response.family.count',
                    unit: '1',
                    description: 'HTTP server responses grouped by status code family',
                );
            } catch (Throwable $e) {
                $this->logger->debug(
                    'Metrics not available, falling back to event backend',
                    ['error' => $e->getMessage()],
                );
                $this->backend = 'event';
            }
        }
    }

    /**
     * @return array<string, array<int, array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [
                ['onKernelRequest', -PHP_INT_MAX + 10],
            ],
            KernelEvents::TERMINATE => [
                ['onKernelTerminate', PHP_INT_MAX - 10],
            ],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $routeName = $this->routerUtils->getRouteName() ?? 'unknown';
        $method = $event->getRequest()->getMethod();

        if ($this->backend === 'otel' && $this->requestCounter) {
            $this->safeAdd(function () use ($routeName, $method): void {
                $this->requestCounter?->add(1, [
                    'http.route' => $routeName,
                    'http.request.method' => $method,
                ]);
            });

            return;
        }

        // Fallback: record as a tiny event on the root span
        $span = $this->instrumentationRegistry->getSpan(SpanNames::REQUEST_START);
        if ($span !== null) {
            $span->addEvent('request.count', [
                TraceAttributes::HTTP_ROUTE => $routeName,
                TraceAttributes::HTTP_REQUEST_METHOD => $method,
            ]);
        }
    }

    private function safeAdd(callable $fn): void
    {
        try {
            $fn();
        } catch (Throwable $e) {
            $this->logger->debug('Failed to increment counter', ['error' => $e->getMessage()]);
        }
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        $statusCode = $event->getResponse()->getStatusCode();
        $family = intdiv($statusCode, 100) . 'xx';

        if ($this->backend === 'otel' && $this->responseFamilyCounter) {
            $this->safeAdd(function () use ($family): void {
                $this->responseFamilyCounter?->add(1, [
                    'http.status_family' => $family,
                ]);
            });
            return;
        }

        // Fallback to event
        $span = $this->instrumentationRegistry->getSpan(SpanNames::REQUEST_START);
        if ($span !== null) {
            $span->addEvent('response.family.count', [
                'http.status_family' => $family,
            ]);
        }
    }
}

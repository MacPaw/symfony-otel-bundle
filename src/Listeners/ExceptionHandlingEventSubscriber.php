<?php

declare(strict_types=1);

namespace Macpaw\SymfonyOtelBundle\Listeners;

use Macpaw\SymfonyOtelBundle\Registry\InstrumentationRegistry;
use Macpaw\SymfonyOtelBundle\Service\TraceService;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SemConv\TraceAttributes;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Throwable;

final readonly class ExceptionHandlingEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private InstrumentationRegistry $instrumentationRegistry,
        private TraceService $traceService,
        private ?LoggerInterface $logger = null,
        private bool $forceFlushOnTerminate = false,
        private int $forceFlushTimeoutMs = 100,
    ) {
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();

        $this->logger?->debug('Handling exception in OpenTelemetry tracing', [
            'exception' => $throwable->getMessage(),
            'class' => $throwable::class,
        ]);

        $this->createErrorSpan($event, $throwable);

        $this->cleanupSpansAndScope();

        $this->flushTracesIfConfigured();
    }

    private function createErrorSpan(ExceptionEvent $event, Throwable $throwable): void
    {
        try {
            $tracer = $this->traceService->getTracer();
            $context = $this->instrumentationRegistry->getContext();
            $spanBuilder = $tracer->spanBuilder('exception_handling')
                ->setParent($context)
                ->setSpanKind(SpanKind::KIND_INTERNAL);

            $errorSpan = $spanBuilder->startSpan();
            $errorScope = $errorSpan->activate();

            try {
                $errorSpan->recordException($throwable);
                $errorSpan->setStatus(StatusCode::STATUS_ERROR, $throwable->getMessage());

                $errorSpan->setAttribute(TraceAttributes::EXCEPTION_TYPE, $throwable::class);
                $errorSpan->setAttribute(TraceAttributes::EXCEPTION_MESSAGE, $throwable->getMessage());
                // Gate heavy stacktrace attribute behind env flag to reduce payload in production
                $includeStack = filter_var(getenv('OTEL_INCLUDE_EXCEPTION_STACKTRACE') ?: '0', FILTER_VALIDATE_BOOL);
                if ($includeStack) {
                    $errorSpan->setAttribute(TraceAttributes::EXCEPTION_STACKTRACE, $throwable->getTraceAsString());
                }

                $errorSpan->setAttribute('error.handled_by', 'ExceptionHandlingEventSubscriber');

                if ($event->getRequest() instanceof Request) { // @phpstan-ignore-line
                    $errorSpan->setAttribute(TraceAttributes::HTTP_REQUEST_METHOD, $event->getRequest()->getMethod());
                    $errorSpan->setAttribute(TraceAttributes::URL_FULL, $event->getRequest()->getUri());
                    $errorSpan->setAttribute(
                        TraceAttributes::USER_AGENT_ORIGINAL,
                        $event->getRequest()->headers->get('User-Agent', '')
                    );
                }


                $errorSpan->setAttribute('exception.timestamp', time());

                $this->logger?->debug('Created error span for exception', [
                    'exception' => $throwable->getMessage(),
                    'span_id' => $errorSpan->getContext()->getSpanId(),
                    'trace_id' => $errorSpan->getContext()->getTraceId(),
                ]);
            } finally {
                $errorScope->detach();
                $errorSpan->end();
            }
        } catch (Throwable $e) {
            $this->logger?->error('Failed to create error span', [
                'original_exception' => $throwable->getMessage(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function cleanupSpansAndScope(): void
    {
        foreach ($this->instrumentationRegistry->getSpans() as $spanName => $span) {
            try {
                $span->end();
                $this->logger?->debug('Ended span due to exception', [
                    'span_name' => $spanName,
                ]);
            } catch (Throwable $e) {
                $this->logger?->error('Failed to end span', [
                    'span_name' => $spanName,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->instrumentationRegistry->clearSpans();
        $this->instrumentationRegistry->clearScope();
    }

    private function flushTracesIfConfigured(): void
    {
        if ($this->forceFlushOnTerminate) {
            try {
                $this->traceService->forceFlush($this->forceFlushTimeoutMs);
                $this->logger?->debug('Force-flushed traces due to exception');
            } catch (Throwable $e) {
                $this->logger?->error('Failed to force-flush traces', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
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

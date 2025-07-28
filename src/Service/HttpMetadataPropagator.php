<?php

declare(strict_types=1);

namespace Macpaw\SymfonyOtelBundle\Service;

use Macpaw\SymfonyOtelBundle\Instrumentation\Utils\RouterUtils;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use Symfony\Component\HttpFoundation\Request;

final readonly class HttpMetadataPropagator
{
    public const HEADER_REQUEST_ID = 'X-Request-Id';
    public const HEADER_TRACE_ID = 'X-Trace-Id';

    public function __construct(
        private RouterUtils $routerUtils,
    ) {
    }

    public function addHttpAttributes(SpanBuilderInterface $spanBuilder, Request $request): void
    {
        $this->addRequestIdAttribute($spanBuilder, $request);
        $this->addTraceIdAttribute($spanBuilder, $request);
        $this->addRouteNameAttribute($spanBuilder);
    }

    private function addRequestIdAttribute(SpanBuilderInterface $spanBuilder, Request $request): void
    {
        $requestId = $request->headers->get(self::HEADER_REQUEST_ID);
        if ($requestId === null) {
            $requestId = RequestIdGenerator::generate();
        }

        $spanBuilder->setAttribute('http.request_id', $requestId);
    }

    private function addTraceIdAttribute(SpanBuilderInterface $spanBuilder, Request $request): void
    {
        $traceId = $request->headers->get(self::HEADER_TRACE_ID);
        if ($traceId !== null) {
            $spanBuilder->setAttribute('http.trace_id', $traceId);
        }
    }

    private function addRouteNameAttribute(SpanBuilderInterface $spanBuilder): void
    {
        $routeName = $this->routerUtils->getRouteName();
        if ($routeName !== null) {
            $spanBuilder->setAttribute('http.route_name', $routeName);
        }
    }
}

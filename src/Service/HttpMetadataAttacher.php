<?php

declare(strict_types=1);

namespace Macpaw\SymfonyOtelBundle\Service;

use Macpaw\SymfonyOtelBundle\Instrumentation\Utils\RouterUtils;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use Symfony\Component\HttpFoundation\Request;

final readonly class HttpMetadataAttacher
{
    public const REQUEST_ID_ATTRIBUTE = 'http.request_id';
    public const ROUTE_NAME_ATTRIBUTE = 'http.route_name';

    /**
     * @param array<string, string> $headerMappings
     */
    public function __construct(
        private RouterUtils $routerUtils,
        private array $headerMappings = [],
    ) {
    }

    public function addHttpAttributes(SpanBuilderInterface $spanBuilder, Request $request): void
    {
        foreach ($this->headerMappings as $spanAttributeName => $headerName) {
            if ($request->headers->has($headerName) === false) {
                continue;
            }

            $headerValue = (string)$request->headers->get($headerName);
            $spanBuilder->setAttribute($spanAttributeName, $headerValue);
        }

        // W need to generate a request ID if it is not present in the request and pass it to the span.
        if ($request->headers->has(HttpClientDecorator::REQUEST_ID_HEADER) === false) {
            $requestId = RequestIdGenerator::generate();
            $request->headers->set(HttpClientDecorator::REQUEST_ID_HEADER, $requestId);
            $spanBuilder->setAttribute(self::REQUEST_ID_ATTRIBUTE, $requestId);
        }
    }

    public function addRouteNameAttribute(SpanBuilderInterface $spanBuilder): void
    {
        $routeName = $this->routerUtils->getRouteName();
        if ($routeName !== null) {
            $spanBuilder->setAttribute(self::ROUTE_NAME_ATTRIBUTE, $routeName);
        }
    }
}

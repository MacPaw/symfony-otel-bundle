<?php

declare(strict_types=1);

namespace Macpaw\SymfonyOtelBundle\Service;

use Macpaw\SymfonyOtelBundle\Instrumentation\Utils\RouterUtils;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use Symfony\Component\HttpFoundation\Request;

final readonly class HttpMetadataAttacher
{
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
            $headerValue = $request->headers->get($headerName) ?? RequestIdGenerator::generate();
            $spanBuilder->setAttribute($spanAttributeName, $headerValue);
        }
    }

    public function addRouteNameAttribute(SpanBuilderInterface $spanBuilder): void
    {
        $routeName = $this->routerUtils->getRouteName();
        if ($routeName !== null) {
            $spanBuilder->setAttribute('http.route_name', $routeName);
        }
    }
}

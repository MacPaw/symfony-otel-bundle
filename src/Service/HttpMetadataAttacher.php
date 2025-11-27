<?php

declare(strict_types=1);

namespace Macpaw\SymfonyOtelBundle\Service;

use Macpaw\SymfonyOtelBundle\Instrumentation\Utils\RouterUtils;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\SemConv\Attributes as SemConv;
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

    // Builder-based (pre-start) attachment — keep for internal uses
    public function addHttpAttributes(SpanBuilderInterface $spanBuilder, Request $request): void
    {
        foreach ($this->headerMappings as $spanAttributeName => $headerName) {
            if ($request->headers->has($headerName) === false) {
                continue;
            }

            $headerValue = (string)$request->headers->get($headerName);
            $spanBuilder->setAttribute($spanAttributeName, $headerValue);
        }

        // We need to generate a request ID if it is not present in the request and pass it to the span.
        if ($request->headers->has(HttpClientDecorator::REQUEST_ID_HEADER) === false) {
            $requestId = RequestIdGenerator::generate();
            $request->headers->set(HttpClientDecorator::REQUEST_ID_HEADER, $requestId);
            $spanBuilder->setAttribute(self::REQUEST_ID_ATTRIBUTE, $requestId);
        }

        // Standard HTTP semantic attributes if not set upstream
        $spanBuilder->setAttribute(SemConv\HttpAttributes::HTTP_REQUEST_METHOD, $request->getMethod());
        $spanBuilder->setAttribute(SemConv\HttpAttributes::HTTP_ROUTE, $request->getPathInfo());
    }

    public function addRouteNameAttribute(SpanBuilderInterface $spanBuilder): void
    {
        $routeName = $this->routerUtils->getRouteName();
        if ($routeName !== null) {
            $spanBuilder->setAttribute(self::ROUTE_NAME_ATTRIBUTE, $routeName);
        }
    }

    public function addControllerAttributes(SpanBuilderInterface $spanBuilder, Request $request): void
    {
        $controller = $request->attributes->get('_controller');
        if ($controller === null) {
            return;
        }

        $ns = null;
        $fn = null;

        if (is_string($controller)) {
            // Formats: 'App\\Controller\\HomeController::index' or 'App\\Controller\\InvokableController'
            if (str_contains($controller, '::')) {
                [$ns, $fn] = explode('::', $controller, 2);
            } else {
                $ns = $controller;
                $fn = '__invoke';
            }
        } elseif (is_array($controller) && count($controller) === 2) {
            // [object|string, method]
            $class = is_object($controller[0]) ? $controller[0]::class : (string)$controller[0];
            $ns = $class;
            $fn = (string)$controller[1];
        } elseif (is_object($controller)) {
            // Invokable object
            $ns = $controller::class;
            $fn = '__invoke';
        }

        if ($ns !== null && $fn !== null) {
            $spanBuilder->setAttribute(
                SemConv\CodeAttributes::CODE_FUNCTION_NAME,
                sprintf('%s::%s', $ns, $fn),
            );
        }
    }

    // Span-based (post-start) attachment — used when guarding with isRecording()
    public function addHttpAttributesToSpan(\OpenTelemetry\API\Trace\SpanInterface $span, Request $request): void
    {
        foreach ($this->headerMappings as $spanAttributeName => $headerName) {
            if ($request->headers->has($headerName) === false) {
                continue;
            }
            $headerValue = (string)$request->headers->get($headerName);
            $span->setAttribute($spanAttributeName, $headerValue);
        }

        if ($request->headers->has(HttpClientDecorator::REQUEST_ID_HEADER) === false) {
            $requestId = RequestIdGenerator::generate();
            $request->headers->set(HttpClientDecorator::REQUEST_ID_HEADER, $requestId);
            $span->setAttribute(self::REQUEST_ID_ATTRIBUTE, $requestId);
        }

        $span->setAttribute(SemConv\HttpAttributes::HTTP_REQUEST_METHOD, $request->getMethod());
        $span->setAttribute(SemConv\HttpAttributes::HTTP_ROUTE, $request->getPathInfo());
    }

    public function addRouteNameAttributeToSpan(\OpenTelemetry\API\Trace\SpanInterface $span): void
    {
        $routeName = $this->routerUtils->getRouteName();
        if ($routeName !== null) {
            $span->setAttribute(self::ROUTE_NAME_ATTRIBUTE, $routeName);
        }
    }

    public function addControllerAttributesToSpan(\OpenTelemetry\API\Trace\SpanInterface $span, Request $request): void
    {
        $controller = $request->attributes->get('_controller');
        if ($controller === null) {
            return;
        }

        $ns = null;
        $fn = null;

        if (is_string($controller)) {
            if (str_contains($controller, '::')) {
                [$ns, $fn] = explode('::', $controller, 2);
            } else {
                $ns = $controller;
                $fn = '__invoke';
            }
        } elseif (is_array($controller) && count($controller) === 2) {
            $class = is_object($controller[0]) ? $controller[0]::class : (string)$controller[0];
            $ns = $class;
            $fn = (string)$controller[1];
        } elseif (is_object($controller)) {
            $ns = $controller::class;
            $fn = '__invoke';
        }

        if ($ns !== null && $fn !== null) {
            $span->setAttribute(
                SemConv\CodeAttributes::CODE_FUNCTION_NAME,
                $ns . '::' . $fn,
            );
        }
    }
}

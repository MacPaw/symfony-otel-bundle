<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use Macpaw\SymfonyOtelBundle\Instrumentation\Utils\RouterUtils;
use Macpaw\SymfonyOtelBundle\Service\HttpMetadataAttacher;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class HttpMetadataAttacherTest extends TestCase
{
    private RouterUtils $routerUtils;
    private HttpMetadataAttacher $service;

    protected function setUp(): void
    {
        $requestStack = $this->createMock(RequestStack::class);
        $this->routerUtils = new RouterUtils($requestStack);
        $this->service = new HttpMetadataAttacher($this->routerUtils);
    }

    public function testAddHttpAttributesWithRouteName(): void
    {
        $spanBuilder = $this->createMock(SpanBuilderInterface::class);
        $request = $this->createMock(Request::class);
        $headers = $this->createMock(HeaderBag::class);

        $headers->method('get')->willReturn(null);
        $headers->method('has')->willReturn(false);
        $request->headers = $headers;
        $request->method('getMethod')->willReturn('GET');
        $request->method('getPathInfo')->willReturn('/');

        // Expect 3 calls: 1 for request ID generation + 2 for HTTP_REQUEST_METHOD and HTTP_ROUTE
        $spanBuilder->expects($this->exactly(3))
            ->method('setAttribute')
            ->willReturnSelf();

        $this->service->addHttpAttributes($spanBuilder, $request);
    }

    public function testAddHttpAttributesWithCustomHeaderMappings(): void
    {
        $headerMappings = [
            'user.id' => 'X-User-Id',
            'client.version' => 'X-Client-Version',
            'api.key' => 'X-Api-Key'
        ];

        $service = new HttpMetadataAttacher($this->routerUtils, $headerMappings);
        $spanBuilder = $this->createMock(SpanBuilderInterface::class);
        $request = $this->createMock(Request::class);
        $headers = $this->createMock(HeaderBag::class);

        $headers->method('get')
            ->willReturnMap([
                ['X-User-Id', 'user123'],
                ['X-Client-Version', '1.2.3'],
                ['X-Api-Key', null] // This header is not present, so request ID will be generated
            ]);
        $headers->method('has')
            ->willReturnMap([
                ['X-User-Id', true],
                ['X-Client-Version', true],
                ['X-Api-Key', false],
                ['X-Request-Id', false] // No existing request ID, so one will be generated
            ]);
        $request->headers = $headers;
        $request->method('getMethod')->willReturn('GET');
        $request->method('getPathInfo')->willReturn('/');

        // Expect 5 calls: 2 for existing headers + 1 for request ID generation + 2 for HTTP_REQUEST_METHOD and HTTP_ROUTE
        $spanBuilder->expects($this->exactly(5))
            ->method('setAttribute')
            ->willReturnSelf();

        $service->addHttpAttributes($spanBuilder, $request);
    }

    public function testAddHttpAttributesWithEmptyHeaderMappings(): void
    {
        $service = new HttpMetadataAttacher($this->routerUtils, []);
        $spanBuilder = $this->createMock(SpanBuilderInterface::class);
        $request = $this->createMock(Request::class);
        $headers = $this->createMock(HeaderBag::class);

        $headers->method('get')->willReturn(null);
        $headers->method('has')->willReturn(false);
        $request->headers = $headers;
        $request->method('getMethod')->willReturn('GET');
        $request->method('getPathInfo')->willReturn('/');

        // Expect 3 calls: 1 for request ID generation + 2 for HTTP_REQUEST_METHOD and HTTP_ROUTE
        $spanBuilder->expects($this->exactly(3))
            ->method('setAttribute')
            ->willReturnSelf();

        $service->addHttpAttributes($spanBuilder, $request);
    }

    public function testAddHttpAttributesWithRequestIdMapping(): void
    {
        $headerMappings = [
            'http.request_id' => 'X-Request-Id',
            'http.trace_id' => 'X-Trace-Id'
        ];

        $service = new HttpMetadataAttacher($this->routerUtils, $headerMappings);
        $spanBuilder = $this->createMock(SpanBuilderInterface::class);
        $request = $this->createMock(Request::class);
        $headers = $this->createMock(HeaderBag::class);

        $headers->method('get')
            ->willReturnMap([
                ['X-Request-Id', 'test-request-id'],
                ['X-Trace-Id', 'test-trace-id']
            ]);
        $headers->method('has')
            ->willReturnMap([
                ['X-Request-Id', true],
                ['X-Trace-Id', true],
                ['X-Request-Id', true] // Existing request ID, so no generation needed
            ]);
        $request->headers = $headers;
        $request->method('getMethod')->willReturn('GET');
        $request->method('getPathInfo')->willReturn('/');

        // Expect 4 calls: 2 for existing headers + 2 for HTTP_REQUEST_METHOD and HTTP_ROUTE
        // Note: request ID is not generated because X-Request-Id header exists
        $spanBuilder->expects($this->exactly(4))
            ->method('setAttribute')
            ->willReturnSelf();

        $service->addHttpAttributes($spanBuilder, $request);
    }

    public function testAddRouteNameAttributeWithRouteName(): void
    {
        $spanBuilder = $this->createMock(SpanBuilderInterface::class);

        // Create RouterUtils with mocked RequestStack that returns a request with route name
        $requestStack = $this->createMock(RequestStack::class);
        $request = $this->createMock(Request::class);
        $attributes = $this->createMock(ParameterBag::class);

        $attributes->method('get')->with('_route')->willReturn('test_route');
        $request->attributes = $attributes;

        $requestStack->method('getCurrentRequest')->willReturn($request);
        $requestStack->method('getMainRequest')->willReturn($request);
        $requestStack->method('getParentRequest')->willReturn(null);

        $routerUtils = new RouterUtils($requestStack);
        $service = new HttpMetadataAttacher($routerUtils);

        $spanBuilder->expects($this->once())
            ->method('setAttribute')
            ->with('http.route_name', 'test_route')
            ->willReturnSelf();

        $service->addRouteNameAttribute($spanBuilder);
    }

    public function testAddRouteNameAttributeWithoutRouteName(): void
    {
        $spanBuilder = $this->createMock(SpanBuilderInterface::class);

        // Create RouterUtils with mocked RequestStack that returns a request without route name
        $requestStack = $this->createMock(RequestStack::class);
        $request = $this->createMock(Request::class);
        $attributes = $this->createMock(ParameterBag::class);

        $attributes->method('get')->with('_route')->willReturn(null);
        $request->attributes = $attributes;

        $requestStack->method('getCurrentRequest')->willReturn($request);
        $requestStack->method('getMainRequest')->willReturn($request);
        $requestStack->method('getParentRequest')->willReturn(null);

        $routerUtils = new RouterUtils($requestStack);
        $service = new HttpMetadataAttacher($routerUtils);

        $spanBuilder->expects($this->never())
            ->method('setAttribute');

        $service->addRouteNameAttribute($spanBuilder);
    }
}

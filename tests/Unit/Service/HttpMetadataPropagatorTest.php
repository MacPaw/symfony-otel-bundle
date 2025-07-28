<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use Macpaw\SymfonyOtelBundle\Instrumentation\Utils\RouterUtils;
use Macpaw\SymfonyOtelBundle\Service\HttpMetadataPropagator;
use PHPUnit\Framework\TestCase;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class HttpMetadataPropagatorTest extends TestCase
{
    private const REQUEST_ID_PATTERN = '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/';

    private RouterUtils $routerUtils;
    private HttpMetadataPropagator $service;

    protected function setUp(): void
    {
        $requestStack = $this->createMock(RequestStack::class);
        $this->routerUtils = new RouterUtils($requestStack);
        $this->service = new HttpMetadataPropagator($this->routerUtils);
    }

    public function testAddHttpAttributesWithRequestId(): void
    {
        $spanBuilder = $this->createMock(SpanBuilderInterface::class);
        $request = $this->createMock(Request::class);
        $headers = $this->createMock(HeaderBag::class);

        $headers->method('get')
            ->willReturnMap([
                [HttpMetadataPropagator::HEADER_REQUEST_ID, 'test-request-id'],
                [HttpMetadataPropagator::HEADER_TRACE_ID, null]
            ]);
        $request->headers = $headers;

        $spanBuilder->expects($this->once())
            ->method('setAttribute')
            ->with('http.request_id', 'test-request-id');

        $this->service->addHttpAttributes($spanBuilder, $request);
    }

    public function testAddHttpAttributesWithTraceId(): void
    {
        $spanBuilder = $this->createMock(SpanBuilderInterface::class);
        $request = $this->createMock(Request::class);
        $headers = $this->createMock(HeaderBag::class);

        $headers->method('get')
            ->willReturnMap([
                [HttpMetadataPropagator::HEADER_REQUEST_ID, null],
                [HttpMetadataPropagator::HEADER_TRACE_ID, 'test-trace-id']
            ]);
        $request->headers = $headers;

        $spanBuilder->expects($this->exactly(2))
            ->method('setAttribute')
            ->willReturnSelf();

        $this->service->addHttpAttributes($spanBuilder, $request);
    }

    public function testAddHttpAttributesWithRouteName(): void
    {
        $spanBuilder = $this->createMock(SpanBuilderInterface::class);
        $request = $this->createMock(Request::class);
        $headers = $this->createMock(HeaderBag::class);

        $headers->method('get')
            ->willReturnMap([
                [HttpMetadataPropagator::HEADER_REQUEST_ID, null],
                [HttpMetadataPropagator::HEADER_TRACE_ID, null]
            ]);
        $request->headers = $headers;

        $spanBuilder->expects($this->once())
            ->method('setAttribute')
            ->with('http.request_id', $this->matchesRegularExpression(self::REQUEST_ID_PATTERN));

        $this->service->addHttpAttributes($spanBuilder, $request);
    }

    public function testAddHttpAttributesWithAllAttributes(): void
    {
        $spanBuilder = $this->createMock(SpanBuilderInterface::class);
        $request = $this->createMock(Request::class);
        $headers = $this->createMock(HeaderBag::class);

        $headers->method('get')
            ->willReturnMap([
                [HttpMetadataPropagator::HEADER_REQUEST_ID, 'test-request-id'],
                [HttpMetadataPropagator::HEADER_TRACE_ID, 'test-trace-id']
            ]);
        $request->headers = $headers;

        $spanBuilder->expects($this->exactly(2))
            ->method('setAttribute')
            ->willReturnSelf();

        $this->service->addHttpAttributes($spanBuilder, $request);
    }

    public function testAddHttpAttributesGeneratesRequestIdWhenNotPresent(): void
    {
        $spanBuilder = $this->createMock(SpanBuilderInterface::class);
        $request = $this->createMock(Request::class);
        $headers = $this->createMock(HeaderBag::class);

        $headers->method('get')
            ->willReturnMap([
                [HttpMetadataPropagator::HEADER_REQUEST_ID, null],
                [HttpMetadataPropagator::HEADER_TRACE_ID, null]
            ]);
        $request->headers = $headers;

        $spanBuilder->expects($this->once())
            ->method('setAttribute')
            ->with('http.request_id', $this->matchesRegularExpression(self::REQUEST_ID_PATTERN));

        $this->service->addHttpAttributes($spanBuilder, $request);
    }
}

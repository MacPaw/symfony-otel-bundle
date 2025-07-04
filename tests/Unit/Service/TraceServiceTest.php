<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use Macpaw\SymfonyOtelBundle\Service\TraceService;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\SDK\Trace\TracerProviderInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TraceServiceTest extends TestCase
{
    private TracerProviderInterface&MockObject $tracerProvider;
    private TraceService $traceService;

    protected function setUp(): void
    {
        $this->tracerProvider = $this->createMock(TracerProviderInterface::class);
        $this->traceService = new TraceService($this->tracerProvider);
    }

    public function testGetTracer(): void
    {
        $tracerName = 'test-tracer';
        $expectedTracer = $this->createMock(TracerInterface::class);

        $this->tracerProvider->expects($this->once())
            ->method('getTracer')
            ->with($tracerName)
            ->willReturn($expectedTracer);

        $result = $this->traceService->getTracer($tracerName);

        $this->assertSame($expectedTracer, $result);
    }

    public function testGetTracerWithEmptyName(): void
    {
        $tracerName = '';
        $expectedTracer = $this->createMock(TracerInterface::class);

        $this->tracerProvider
            ->expects($this->once())
            ->method('getTracer')
            ->with($tracerName)
            ->willReturn($expectedTracer);

        $result = $this->traceService->getTracer($tracerName);

        $this->assertSame($expectedTracer, $result);
    }

    public function testGetTracerWithSpecialCharacters(): void
    {
        $tracerName = 'test-tracer-with-special-chars_123';
        $expectedTracer = $this->createMock(TracerInterface::class);

        $this->tracerProvider
            ->expects($this->once())
            ->method('getTracer')
            ->with($tracerName)
            ->willReturn($expectedTracer);

        $result = $this->traceService->getTracer($tracerName);

        $this->assertSame($expectedTracer, $result);
    }

    public function testShutdown(): void
    {
        $this->tracerProvider
            ->expects($this->once())
            ->method('shutdown');

        $this->traceService->shutdown();
    }

    public function testMultipleShutdownCalls(): void
    {
        $this->tracerProvider
            ->expects($this->exactly(2))
            ->method('shutdown');

        $this->traceService->shutdown();
        $this->traceService->shutdown();
    }

    public function testGetTracerMultipleCalls(): void
    {
        $tracerName1 = 'tracer-1';
        $tracerName2 = 'tracer-2';
        $expectedTracer1 = $this->createMock(TracerInterface::class);
        $expectedTracer2 = $this->createMock(TracerInterface::class);

        $this->tracerProvider
            ->expects($this->exactly(2))
            ->method('getTracer')
            ->willReturnOnConsecutiveCalls($expectedTracer1, $expectedTracer2);

        $result1 = $this->traceService->getTracer($tracerName1);
        $result2 = $this->traceService->getTracer($tracerName2);

        $this->assertSame($expectedTracer1, $result1);
        $this->assertSame($expectedTracer2, $result2);
    }
}

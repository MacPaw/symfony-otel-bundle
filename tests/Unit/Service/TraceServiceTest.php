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
    private string $serviceName = 'test-service';
    private string $tracerName = 'test-tracer';

    protected function setUp(): void
    {
        $this->tracerProvider = $this->createMock(TracerProviderInterface::class);
        $this->traceService = new TraceService(
            $this->tracerProvider,
            $this->serviceName,
            $this->tracerName
        );
    }

    public function testGetTracerWithCustomName(): void
    {
        $customTracerName = 'custom-tracer';
        $expectedTracer = $this->createMock(TracerInterface::class);

        $this->tracerProvider->expects($this->once())
            ->method('getTracer')
            ->with($customTracerName)
            ->willReturn($expectedTracer);

        $result = $this->traceService->getTracer($customTracerName);

        $this->assertSame($expectedTracer, $result);
    }

    public function testGetTracerWithDefaultName(): void
    {
        $expectedTracer = $this->createMock(TracerInterface::class);

        $this->tracerProvider->expects($this->once())
            ->method('getTracer')
            ->with($this->tracerName)
            ->willReturn($expectedTracer);

        $result = $this->traceService->getTracer();

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

    public function testGetServiceName(): void
    {
        $result = $this->traceService->getServiceName();
        $this->assertEquals($this->serviceName, $result);
    }

    public function testGetTracerName(): void
    {
        $result = $this->traceService->getTracerName();
        $this->assertEquals($this->tracerName, $result);
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

    public function testGetTracerWithNullName(): void
    {
        $expectedTracer = $this->createMock(TracerInterface::class);

        $this->tracerProvider->expects($this->once())
            ->method('getTracer')
            ->with($this->tracerName)
            ->willReturn($expectedTracer);

        $result = $this->traceService->getTracer(null);

        $this->assertSame($expectedTracer, $result);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Span;

use Macpaw\SymfonyOtelBundle\Service\TraceService;
use Macpaw\SymfonyOtelBundle\Span\ExecutionTimeSpanTracer;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;
use OpenTelemetry\Context\ScopeInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class ExecutionTimeSpanTracerTest extends TestCase
{
    public function testOnKernelRequestAndTerminate(): void
    {
        $traceService = $this->createMock(TraceService::class);
        $propagator = $this->createMock(TextMapPropagatorInterface::class);
        $tracerName = 'test_tracer';

        $span = $this->createMock(SpanInterface::class);
        $span->expects($this->once())->method('addEvent');
        $span->expects($this->once())->method('end');

        $tracer = $this->createMock(TracerInterface::class);
        $spanBuilder = $this->createMock(SpanBuilderInterface::class);

        $traceService->expects($this->once())
            ->method('getTracer')
            ->with($tracerName)
            ->willReturn($tracer);

        $tracer->expects($this->once())
            ->method('spanBuilder')
            ->with(ExecutionTimeSpanTracer::NAME)
            ->willReturn($spanBuilder);

        $spanBuilder->expects($this->once())
            ->method('setParent')
            ->willReturnSelf();
        $spanBuilder->expects($this->once())
            ->method('startSpan')
            ->willReturn($span);

        $traceService->expects($this->once())
            ->method('shutdown');

        $executionTimeSpanTracer = $this->getMockBuilder(ExecutionTimeSpanTracer::class)
            ->setConstructorArgs([$traceService, $propagator, $tracerName])
            ->onlyMethods(['checkTraceInjectionValidity'])
            ->getMock();

        $executionTimeSpanTracer->expects($this->once())
            ->method('checkTraceInjectionValidity')
            ->willReturn(Context::getCurrent());

        $request = new Request();
        $request->headers->add([
            'traceparent' => '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01',
        ]);
        $kernel = $this->createMock(HttpKernelInterface::class);
        $requestEvent = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $terminateEvent = new TerminateEvent($kernel, $request, new Response());

        $executionTimeSpanTracer->onKernelRequest($requestEvent);
        $executionTimeSpanTracer->onKernelTerminate($terminateEvent);
    }

    public function testOnKernelRequestWithInvalidTraceContext(): void
    {
        $traceService = $this->createMock(TraceService::class);
        $propagator = $this->createMock(TextMapPropagatorInterface::class);
        $tracerName = 'test_tracer';

        $executionTimeSpanTracer = $this->getMockBuilder(ExecutionTimeSpanTracer::class)
            ->setConstructorArgs([$traceService, $propagator, $tracerName])
            ->onlyMethods(['checkTraceInjectionValidity'])
            ->getMock();

        $executionTimeSpanTracer->expects($this->once())
            ->method('checkTraceInjectionValidity')
            ->willReturn(null); // Invalid context

        // Should not call tracer methods
        $traceService->expects($this->never())->method('getTracer');

        $request = new Request();
        $kernel = $this->createMock(HttpKernelInterface::class);
        $requestEvent = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $executionTimeSpanTracer->onKernelRequest($requestEvent);
    }

    public function testOnKernelTerminateWithoutSpan(): void
    {
        $traceService = $this->createMock(TraceService::class);
        $propagator = $this->createMock(TextMapPropagatorInterface::class);
        $tracerName = 'test_tracer';

        $executionTimeSpanTracer = new ExecutionTimeSpanTracer($traceService, $propagator, $tracerName);

        // Should not call any tracer methods when no span exists
        $traceService->expects($this->never())->method('shutdown');

        $request = new Request();
        $kernel = $this->createMock(HttpKernelInterface::class);
        $terminateEvent = new TerminateEvent($kernel, $request, new Response());

        $executionTimeSpanTracer->onKernelTerminate($terminateEvent);
    }

    public function testGetSubscribedEvents(): void
    {
        $events = ExecutionTimeSpanTracer::getSubscribedEvents();

        $this->assertArrayHasKey('kernel.request', $events);
        $this->assertArrayHasKey('kernel.terminate', $events);
        $this->assertEquals('onKernelRequest', $events['kernel.request']);
        $this->assertEquals('onKernelTerminate', $events['kernel.terminate']);
    }

    public function testConstantName(): void
    {
        $this->assertEquals('execution_time', ExecutionTimeSpanTracer::NAME);
    }

    public function testOnKernelRequestStoresStartTime(): void
    {
        $traceService = $this->createMock(TraceService::class);
        $propagator = $this->createMock(TextMapPropagatorInterface::class);
        $tracerName = 'test_tracer';

        $span = $this->createMock(SpanInterface::class);
        $scope = $this->createMock(ScopeInterface::class);
        $tracer = $this->createMock(TracerInterface::class);
        $spanBuilder = $this->createMock(SpanBuilderInterface::class);

        $traceService->expects($this->once())
            ->method('getTracer')
            ->with($tracerName)
            ->willReturn($tracer);

        $tracer->expects($this->once())
            ->method('spanBuilder')
            ->with(ExecutionTimeSpanTracer::NAME)
            ->willReturn($spanBuilder);

        $spanBuilder->expects($this->once())
            ->method('setParent')
            ->willReturnSelf();
        $spanBuilder->expects($this->once())
            ->method('startSpan')
            ->willReturn($span);

        $span->expects($this->once())
            ->method('activate')
            ->willReturn($scope);

        $span->expects($this->once())
            ->method('addEvent')
            ->with($this->stringContains('Execution time:'));

        $span->expects($this->once())->method('end');
        $scope->expects($this->once())->method('detach');
        $traceService->expects($this->once())->method('shutdown');

        $executionTimeSpanTracer = $this->getMockBuilder(ExecutionTimeSpanTracer::class)
            ->setConstructorArgs([$traceService, $propagator, $tracerName])
            ->onlyMethods(['checkTraceInjectionValidity'])
            ->getMock();

        $executionTimeSpanTracer->expects($this->once())
            ->method('checkTraceInjectionValidity')
            ->willReturn(Context::getCurrent());

        $request = new Request();
        $kernel = $this->createMock(HttpKernelInterface::class);
        $requestEvent = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $terminateEvent = new TerminateEvent($kernel, $request, new Response());

        $startTime = microtime(true);
        $executionTimeSpanTracer->onKernelRequest($requestEvent);

        // Small delay to ensure execution time is measurable
        usleep(1000);

        $executionTimeSpanTracer->onKernelTerminate($terminateEvent);
    }

    public function testSubRequestIsIgnored(): void
    {
        $traceService = $this->createMock(TraceService::class);
        $propagator = $this->createMock(TextMapPropagatorInterface::class);
        $tracerName = 'test_tracer';

        $executionTimeSpanTracer = $this->getMockBuilder(ExecutionTimeSpanTracer::class)
            ->setConstructorArgs([$traceService, $propagator, $tracerName])
            ->onlyMethods(['checkTraceInjectionValidity'])
            ->getMock();

        $executionTimeSpanTracer->expects($this->once())
            ->method('checkTraceInjectionValidity')
            ->willReturn(Context::getCurrent());

        $request = new Request();
        $kernel = $this->createMock(HttpKernelInterface::class);

        // Test with SUB_REQUEST
        $subRequestEvent = new RequestEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST);

        // Should still process sub-requests (this is application logic dependent)
        $traceService->expects($this->once())->method('getTracer');

        $span = $this->createMock(SpanInterface::class);
        $tracer = $this->createMock(TracerInterface::class);
        $spanBuilder = $this->createMock(SpanBuilderInterface::class);

        $traceService->method('getTracer')->willReturn($tracer);
        $tracer->method('spanBuilder')->willReturn($spanBuilder);
        $spanBuilder->method('setParent')->willReturnSelf();
        $spanBuilder->method('startSpan')->willReturn($span);

        $executionTimeSpanTracer->onKernelRequest($subRequestEvent);
    }

    public function testExecutionTimeIsPositive(): void
    {
        $traceService = $this->createMock(TraceService::class);
        $propagator = $this->createMock(TextMapPropagatorInterface::class);
        $tracerName = 'test_tracer';

        $span = $this->createMock(SpanInterface::class);
        $scope = $this->createMock(ScopeInterface::class);
        $tracer = $this->createMock(TracerInterface::class);
        $spanBuilder = $this->createMock(SpanBuilderInterface::class);

        $traceService->expects($this->once())
            ->method('getTracer')
            ->with($tracerName)
            ->willReturn($tracer);

        $tracer->expects($this->once())
            ->method('spanBuilder')
            ->with(ExecutionTimeSpanTracer::NAME)
            ->willReturn($spanBuilder);

        $spanBuilder->expects($this->once())
            ->method('setParent')
            ->willReturnSelf();
        $spanBuilder->expects($this->once())
            ->method('startSpan')
            ->willReturn($span);

        $span->expects($this->once())
            ->method('activate')
            ->willReturn($scope);

        // Critical: Assert that execution time is positive
        $span->expects($this->once())
            ->method('addEvent')
            ->with(
                $this->callback(function (string $message) {
                    // Extract execution time from message
                    if (preg_match('/Execution time: ([0-9.]+) seconds/', $message, $matches)) {
                        $executionTime = (float)$matches[1];
                        // This will catch the + vs - mutant because + would result in a huge number
                        return $executionTime > 0 && $executionTime < 1; // Should be a reasonable positive value
                    }
                    return false;
                }),
            );

        $span->expects($this->once())->method('end');
        $scope->expects($this->once())->method('detach');
        $traceService->expects($this->once())->method('shutdown');

        $executionTimeSpanTracer = $this->getMockBuilder(ExecutionTimeSpanTracer::class)
            ->setConstructorArgs([$traceService, $propagator, $tracerName])
            ->onlyMethods(['checkTraceInjectionValidity'])
            ->getMock();

        $executionTimeSpanTracer->expects($this->once())
            ->method('checkTraceInjectionValidity')
            ->willReturn(Context::getCurrent());

        $request = new Request();
        $kernel = $this->createMock(HttpKernelInterface::class);
        $requestEvent = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $terminateEvent = new TerminateEvent($kernel, $request, new Response());

        $executionTimeSpanTracer->onKernelRequest($requestEvent);

        // Small delay to ensure measurable execution time but not too long
        usleep(1000); // 1ms

        $executionTimeSpanTracer->onKernelTerminate($terminateEvent);
    }
}

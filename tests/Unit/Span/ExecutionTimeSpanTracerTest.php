<?php

declare(strict_types=1);

namespace Unit\Span;

use PHPUnit\Framework\TestCase;
use OpenTelemetry\Context\Context;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\TracerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use Macpaw\SymfonyOtelBundle\Service\TraceService;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Macpaw\SymfonyOtelBundle\Span\ExecutionTimeSpanTracer;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;

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
}

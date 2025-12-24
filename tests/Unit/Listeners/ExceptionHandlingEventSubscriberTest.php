<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use Exception;
use Macpaw\SymfonyOtelBundle\Listeners\ExceptionHandlingEventSubscriber;
use Macpaw\SymfonyOtelBundle\Registry\InstrumentationRegistry;
use Macpaw\SymfonyOtelBundle\Service\TraceService;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\Context\ScopeInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class ExceptionHandlingEventSubscriberTest extends TestCase
{
    private InstrumentationRegistry $registry;

    private TraceService&MockObject $traceService;

    private LoggerInterface&MockObject $logger;

    private ExceptionHandlingEventSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->registry = new InstrumentationRegistry();
        $this->traceService = $this->createMock(TraceService::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->subscriber = new ExceptionHandlingEventSubscriber(
            $this->registry,
            $this->traceService,
            $this->logger
        );
    }

    public function testOnKernelExceptionWithNoThrowable(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();
        $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, new Exception('Test'));

        $this->logger->expects($this->atLeast(1))->method('debug');
        // shutdown() is not called in the implementation - only forceFlush() if configured

        $this->subscriber->onKernelException($event);
    }

    public function testOnKernelExceptionWithThrowable(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();
        $exception = new Exception('Test exception');
        $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $exception);

        $span = $this->createMock(SpanInterface::class);
        $scope = $this->createMock(ScopeInterface::class);

        $span->expects($this->once())->method('end');
        $scope->expects($this->once())->method('detach');

        $this->logger->expects($this->atLeast(1))->method('debug');
        // shutdown() is not called in the implementation - only forceFlush() if configured

        $this->registry->addSpan($span, 'test_span');
        $this->registry->setScope($scope);

        $this->subscriber->onKernelException($event);
    }

    public function testOnKernelExceptionWithErrorSpanCreationFailure(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();
        $exception = new Exception('Test exception');
        $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $exception);

        $span = $this->createMock(SpanInterface::class);

        $span->expects($this->once())->method('end');

        $this->logger->expects($this->atLeast(1))->method('debug');
        $this->logger->expects($this->atLeast(1))->method('error');
        // shutdown() is not called in the implementation - only forceFlush() if configured

        $this->registry->addSpan($span, 'test_span');

        // Змушуємо TraceService викинути виняток при створенні span
        $this->traceService->expects($this->once())
            ->method('getTracer')
            ->willThrowException(new RuntimeException('Failed to get tracer'));

        $this->subscriber->onKernelException($event);
    }

    public function testOnKernelExceptionWithScopeError(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();
        $exception = new Exception('Test exception');
        $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $exception);

        $scope = $this->createMock(ScopeInterface::class);

        $scope->expects($this->once())->method('detach')->willThrowException(new RuntimeException('Scope error'));

        $this->logger->expects($this->atLeast(1))->method('debug');
        // shutdown() is not called in the implementation - only forceFlush() if configured

        $this->registry->setScope($scope);

        $this->subscriber->onKernelException($event);
    }

    public function testOnKernelExceptionWithShutdownError(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();
        $exception = new Exception('Test exception');
        $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $exception);

        // shutdown() is not called in the implementation - only forceFlush() if configured
        // This test is no longer relevant, but we keep it to verify the implementation doesn't call shutdown
        $this->traceService->expects($this->never())->method('shutdown');

        $this->logger->expects($this->atLeast(1))->method('debug');

        $this->subscriber->onKernelException($event);
    }

    public function testGetSubscribedEvents(): void
    {
        $events = ExceptionHandlingEventSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey('kernel.exception', $events);
        $this->assertEquals(['onKernelException', PHP_INT_MAX], $events['kernel.exception']);
    }
}

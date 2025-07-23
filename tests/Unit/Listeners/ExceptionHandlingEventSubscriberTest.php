<?php

declare(strict_types=1);

namespace Macpaw\SymfonyOtelBundle\Tests\Unit\Listeners;

use Exception;
use Macpaw\SymfonyOtelBundle\Instrumentation\Utils\RouterUtils;
use Macpaw\SymfonyOtelBundle\Listeners\ExceptionHandlingEventSubscriber;
use Macpaw\SymfonyOtelBundle\Registry\InstrumentationRegistry;
use Macpaw\SymfonyOtelBundle\Service\TraceService;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\ScopeInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Throwable;

class ExceptionHandlingEventSubscriberTest extends TestCase
{
    private InstrumentationRegistry $registry;
    private TraceService&MockObject $traceService;
    private RequestStack&MockObject $requestStack;
    private RouterUtils $routerUtils;
    private LoggerInterface&MockObject $logger;
    private ExceptionHandlingEventSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->registry = new InstrumentationRegistry();
        $this->traceService = $this->createMock(TraceService::class);
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->routerUtils = new RouterUtils($this->requestStack);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->subscriber = new ExceptionHandlingEventSubscriber(
            $this->registry,
            $this->traceService,
            $this->routerUtils,
            $this->logger
        );
    }

    public function testOnKernelExceptionWithNoThrowable(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();
        $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, new Exception('Test'));

        $this->logger->expects($this->atLeast(1))->method('debug');
        $this->traceService->expects($this->once())->method('shutdown');

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
        $this->traceService->expects($this->once())->method('shutdown');

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
        $this->traceService->expects($this->once())->method('shutdown');

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
        $this->traceService->expects($this->once())->method('shutdown');

        $this->registry->setScope($scope);

        $this->subscriber->onKernelException($event);
    }

    public function testOnKernelExceptionWithShutdownError(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();
        $exception = new Exception('Test exception');
        $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $exception);

        $this->traceService->expects($this->once())
            ->method('shutdown')
            ->willThrowException(new RuntimeException('Shutdown error'));

        $this->logger->expects($this->atLeast(1))->method('debug');
        $this->logger->expects($this->atLeast(1))->method('error');

        $this->subscriber->onKernelException($event);
    }

    public function testGetSubscribedEvents(): void
    {
        $events = ExceptionHandlingEventSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey('kernel.exception', $events);
        $this->assertEquals(['onKernelException', PHP_INT_MAX], $events['kernel.exception']);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use Closure;
use Exception;
use Macpaw\SymfonyOtelBundle\Instrumentation\HookInstrumentationInterface;
use Macpaw\SymfonyOtelBundle\Service\HookManagerService;
use OpenTelemetry\API\Instrumentation\AutoInstrumentation\HookManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class HookManagerServiceTest extends TestCase
{
    private HookManagerService $hookManagerService;

    private LoggerInterface&MockObject $logger;

    private HookManagerInterface&MockObject $hookManager;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->hookManager = $this->createMock(HookManagerInterface::class);

        $this->hookManagerService = new HookManagerService(
            $this->hookManager,
            $this->logger,
        );
    }

    public function testRegisterHookWithNullClass(): void
    {
        $instrumentation = $this->createMock(HookInstrumentationInterface::class);
        $instrumentation->method('getClass')->willReturn(null);
        $instrumentation->method('getMethod')->willReturn('testFunction');
        $instrumentation->method('getName')->willReturn('test_instrumentation');

        $this->hookManager->expects($this->once())
            ->method('hook')
            ->with(null, 'testFunction', $this->isInstanceOf(Closure::class), $this->isInstanceOf(Closure::class));

        $this->logger->expects($this->once())
            ->method('debug')
            ->with(
                'Successfully registered hook for {class}::{method}',
                [
                    'class' => null,
                    'method' => 'testFunction',
                    'instrumentation' => 'test_instrumentation'
                ]
            );

        $this->hookManagerService->registerHook($instrumentation);
    }

    public function testRegisterHookSuccessfully(): void
    {
        $instrumentation = $this->createMock(HookInstrumentationInterface::class);
        $instrumentation->method('getClass')->willReturn('TestClass');
        $instrumentation->method('getMethod')->willReturn('testMethod');
        $instrumentation->method('getName')->willReturn('test_instrumentation');

        $this->hookManager->expects($this->once())
            ->method('hook')
            ->with(
                'TestClass',
                'testMethod',
                $this->isInstanceOf(Closure::class),
                $this->isInstanceOf(Closure::class)
            );

        $this->logger->expects($this->once())
            ->method('debug')
            ->with(
                'Successfully registered hook for {class}::{method}',
                [
                    'class' => 'TestClass',
                    'method' => 'testMethod',
                    'instrumentation' => 'test_instrumentation'
                ]
            );

        $this->hookManagerService->registerHook($instrumentation);
    }

    public function testRegisterHookWithException(): void
    {
        $instrumentation = $this->createMock(HookInstrumentationInterface::class);
        $instrumentation->method('getClass')->willReturn('TestClass');
        $instrumentation->method('getMethod')->willReturn('testMethod');
        $instrumentation->method('getName')->willReturn('test_instrumentation');

        $this->hookManager->expects($this->once())
            ->method('hook')
            ->willThrowException(new Exception('Hook registration failed'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                'Failed to register hook for {class}::{method}: {error}',
                [
                    'class' => 'TestClass',
                    'method' => 'testMethod',
                    'instrumentation' => 'test_instrumentation',
                    'error' => 'Hook registration failed'
                ]
            );

        $this->hookManagerService->registerHook($instrumentation);
    }

    public function testRegisterHookWithPreHookException(): void
    {
        $instrumentation = $this->createMock(HookInstrumentationInterface::class);
        $instrumentation->method('getClass')->willReturn('TestClass');
        $instrumentation->method('getMethod')->willReturn('testMethod');
        $instrumentation->method('getName')->willReturn('test_instrumentation');
        $instrumentation->method('pre')->willThrowException(new Exception('Pre hook error'));

        $this->hookManager->expects($this->once())
            ->method('hook')
            ->willReturnCallback(function (string $class, string $method, callable $preHook, callable $postHook): void {
                $preHook();
            });

        // Verify error is logged with proper array key 'error' using => operator (not >)
        // The array must have ['error' => $throwable->getMessage()] structure
        $callCount = 0;
        $this->logger->expects($this->exactly(2))
            ->method('error')
            ->willReturnCallback(function (string $message, array $context = []) use (&$callCount): void {
                $callCount++;
                if ($callCount === 1) {
                    $this->assertEquals("Error in hook pre(): {error}", $message);
                    // Verify array has 'error' key with => operator (not >)
                    // If > were used, the array would be malformed or missing the key
                    $this->assertArrayHasKey('error', $context);
                    $this->assertEquals('Pre hook error', $context['error']);
                    // Verify the array structure is correct (=> operator creates proper key-value pair)
                    $this->assertCount(1, $context, 'Array should have exactly 1 element with => operator');
                    $this->assertSame('Pre hook error', $context['error'], 'Array value should match using => operator');
                } elseif ($callCount === 2) {
                    $this->assertEquals('Failed to register hook for {class}::{method}: {error}', $message);
                    $this->assertArrayHasKey('class', $context);
                    $this->assertArrayHasKey('method', $context);
                    $this->assertArrayHasKey('instrumentation', $context);
                    $this->assertArrayHasKey('error', $context);
                }
            });

        $this->logger->expects($this->never())
            ->method('debug');

        $this->hookManagerService->registerHook($instrumentation);
    }

    public function testRegisterHookWithPostHookException(): void
    {
        $instrumentation = $this->createMock(HookInstrumentationInterface::class);
        $instrumentation->method('getClass')->willReturn('TestClass');
        $instrumentation->method('getMethod')->willReturn('testMethod');
        $instrumentation->method('getName')->willReturn('test_instrumentation');
        $instrumentation->method('post')->willThrowException(new Exception('Post hook error'));

        $this->hookManager->expects($this->once())
            ->method('hook')
            ->willReturnCallback(function (string $class, string $method, callable $preHook, callable $postHook): void {
                $postHook();
            });

        // Verify error is logged with proper array key 'error' using => operator (not >)
        // The array must have ['error' => $throwable->getMessage()] structure
        $callCount = 0;
        $this->logger->expects($this->exactly(2))
            ->method('error')
            ->willReturnCallback(function (string $message, array $context = []) use (&$callCount): void {
                $callCount++;
                if ($callCount === 1) {
                    $this->assertEquals("Error in hook post(): {error}", $message);
                    // Verify array has 'error' key with => operator (not >)
                    // If > were used, the array would be malformed or missing the key
                    $this->assertArrayHasKey('error', $context);
                    $this->assertEquals('Post hook error', $context['error']);
                    // Verify the array structure is correct (=> operator creates proper key-value pair)
                    $this->assertCount(1, $context, 'Array should have exactly 1 element with => operator');
                    $this->assertSame('Post hook error', $context['error'], 'Array value should match using => operator');
                } elseif ($callCount === 2) {
                    $this->assertEquals('Failed to register hook for {class}::{method}: {error}', $message);
                    $this->assertArrayHasKey('class', $context);
                    $this->assertArrayHasKey('method', $context);
                    $this->assertArrayHasKey('instrumentation', $context);
                    $this->assertArrayHasKey('error', $context);
                }
            });

        $this->logger->expects($this->never())
            ->method('debug');

        $this->hookManagerService->registerHook($instrumentation);
    }

    public function testRegisterHookWithNullLogger(): void
    {
        $hookManagerService = new HookManagerService($this->hookManager, null);

        $instrumentation = $this->createMock(HookInstrumentationInterface::class);
        $instrumentation->method('getClass')->willReturn('TestClass');
        $instrumentation->method('getMethod')->willReturn('testMethod');
        $instrumentation->method('getName')->willReturn('test_instrumentation');

        $this->hookManager->expects($this->once())
            ->method('hook')
            ->with(
                'TestClass',
                'testMethod',
                $this->isInstanceOf(Closure::class),
                $this->isInstanceOf(Closure::class)
            );

        $hookManagerService->registerHook($instrumentation);
    }

    public function testRegisterHooksWithMultipleInstrumentations(): void
    {
        $instrumentation1 = $this->createMock(HookInstrumentationInterface::class);
        $instrumentation1->method('getClass')->willReturn(null);
        $instrumentation1->method('getMethod')->willReturn('testFunction1');
        $instrumentation1->method('getName')->willReturn('test_instrumentation_1');

        $instrumentation2 = $this->createMock(HookInstrumentationInterface::class);
        $instrumentation2->method('getClass')->willReturn('TestClass');
        $instrumentation2->method('getMethod')->willReturn('testMethod');
        $instrumentation2->method('getName')->willReturn('test_instrumentation_2');

        $this->hookManager->expects($this->exactly(2))
            ->method('hook')
            ->willReturnCallback(
                function (?string $class, string $method, callable $preHook, callable $postHook): void {
                    /** @var int $callCount */
                    static $callCount = 0;
                    $callCount++;

                    if ($callCount === 1) {
                        $this->assertNull($class);
                        $this->assertEquals('testFunction1', $method);
                    } else {
                        $this->assertEquals('TestClass', $class);
                        $this->assertEquals('testMethod', $method);
                    }
                }
            );

        $this->logger->expects($this->exactly(2))
            ->method('debug');

        $this->hookManagerService->registerHooks([$instrumentation1, $instrumentation2]);
    }

    public function testRegisterHooksWithEmptyArray(): void
    {
        $this->hookManager->expects($this->never())
            ->method('hook');

        $this->logger->expects($this->never())
            ->method('debug');

        $this->hookManagerService->registerHooks([]);
    }

    public function testRegisterHooksWithExceptionInOneHook(): void
    {
        $instrumentation1 = $this->createMock(HookInstrumentationInterface::class);
        $instrumentation1->method('getClass')->willReturn('TestClass1');
        $instrumentation1->method('getMethod')->willReturn('testMethod1');
        $instrumentation1->method('getName')->willReturn('test_instrumentation_1');

        $instrumentation2 = $this->createMock(HookInstrumentationInterface::class);
        $instrumentation2->method('getClass')->willReturn('TestClass2');
        $instrumentation2->method('getMethod')->willReturn('testMethod2');
        $instrumentation2->method('getName')->willReturn('test_instrumentation_2');

        $this->hookManager->expects($this->exactly(2))
            ->method('hook')
            ->willReturnCallback(function ($class, $method, $preHook, $postHook): void {
                if ($class === 'TestClass1') {
                    throw new Exception('Hook 1 failed');
                }
            });

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                'Failed to register hook for {class}::{method}: {error}',
                [
                    'class' => 'TestClass1',
                    'method' => 'testMethod1',
                    'instrumentation' => 'test_instrumentation_1',
                    'error' => 'Hook 1 failed'
                ]
            );

        $this->logger->expects($this->once())
            ->method('debug')
            ->with(
                'Successfully registered hook for {class}::{method}',
                [
                    'class' => 'TestClass2',
                    'method' => 'testMethod2',
                    'instrumentation' => 'test_instrumentation_2'
                ]
            );

        $this->hookManagerService->registerHooks([$instrumentation1, $instrumentation2]);
    }

    public function testRegisterHookWithSuccessfulPreHook(): void
    {
        $instrumentation = $this->createMock(HookInstrumentationInterface::class);
        $instrumentation->method('getClass')->willReturn('TestClass');
        $instrumentation->method('getMethod')->willReturn('testMethod');
        $instrumentation->method('getName')->willReturn('test_instrumentation');
        // pre() returns void, so no need to configure return value

        $this->hookManager->expects($this->once())
            ->method('hook')
            ->willReturnCallback(function (string $class, string $method, callable $preHook, callable $postHook): void {
                $preHook(); // Execute pre hook
            });

        $callCount = 0;
        $this->logger->expects($this->exactly(2))
            ->method('debug')
            ->willReturnCallback(function ($message, array $context = []) use (&$callCount): void {
                /** @var array<string, mixed> $context */
                $callCount++;
                if ($callCount === 1) {
                    $this->assertEquals('Successfully executed pre hook for TestClass::testMethod', $message);
                } elseif ($callCount === 2) {
                    $this->assertEquals('Successfully registered hook for {class}::{method}', $message);
                    $this->assertEquals('TestClass', $context['class'] ?? null);
                    $this->assertEquals('testMethod', $context['method'] ?? null);
                    $this->assertEquals('test_instrumentation', $context['instrumentation'] ?? null);
                }
            });

        $this->hookManagerService->registerHook($instrumentation);
    }

    public function testRegisterHookWithSuccessfulPostHook(): void
    {
        $instrumentation = $this->createMock(HookInstrumentationInterface::class);
        $instrumentation->method('getClass')->willReturn('TestClass');
        $instrumentation->method('getMethod')->willReturn('testMethod');
        $instrumentation->method('getName')->willReturn('test_instrumentation');
        // post() returns void, so no need to configure return value

        $this->hookManager->expects($this->once())
            ->method('hook')
            ->willReturnCallback(function (string $class, string $method, callable $preHook, callable $postHook): void {
                $postHook(); // Execute post hook
            });

        $callCount = 0;
        $this->logger->expects($this->exactly(2))
            ->method('debug')
            ->willReturnCallback(function ($message, array $context = []) use (&$callCount): void {
                /** @var array<string, mixed> $context */
                $callCount++;
                if ($callCount === 1) {
                    $this->assertEquals('Successfully executed post hook for TestClass::testMethod', $message);
                } elseif ($callCount === 2) {
                    $this->assertEquals('Successfully registered hook for {class}::{method}', $message);
                    $this->assertEquals('TestClass', $context['class'] ?? null);
                    $this->assertEquals('testMethod', $context['method'] ?? null);
                    $this->assertEquals('test_instrumentation', $context['instrumentation'] ?? null);
                }
            });

        $this->hookManagerService->registerHook($instrumentation);
    }
}

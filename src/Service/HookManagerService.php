<?php

declare(strict_types=1);

namespace Macpaw\SymfonyOtelBundle\Service;

use Macpaw\SymfonyOtelBundle\Instrumentation\HookInstrumentationInterface;
use OpenTelemetry\API\Instrumentation\AutoInstrumentation\HookManagerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

final readonly class HookManagerService
{
    public function __construct(
        private ?LoggerInterface $logger,
        private HookManagerInterface $hookManager,
    ) {
    }

    public function registerHook(HookInstrumentationInterface $instrumentation): void
    {
        $class = $instrumentation->getClass();
        $method = $instrumentation->getMethod();

        try {
            $logger = $this->logger ?? new NullLogger();
            $preHook = static function () use ($instrumentation, $logger, $class, $method) {
                try {
                    $instrumentation->pre();
                    $logger->debug("Successfully executed pre hook for {$class}::{$method}");
                } catch (Throwable $e) {
                    $logger->error("Error in hook pre(): {error}", ['error' => $e->getMessage()]);

                    throw $e;
                }
            };
            $postHook = static function () use ($instrumentation, $logger, $class, $method) {
                try {
                    $instrumentation->post();
                    $logger->debug("Successfully executed post hook for {$class}::{$method}");
                } catch (Throwable $e) {
                    $logger->error("Error in hook post(): {error}", ['error' => $e->getMessage()]);

                    throw $e;
                }
            };

            $this->hookManager->hook($class, $method, $preHook, $postHook);

            $this->logger?->debug('Successfully registered hook for {class}::{method}', [
                'class' => $class,
                'method' => $method,
                'instrumentation' => $instrumentation->getName(),
            ]);
        } catch (Throwable $e) {
            $this->logger?->error('Failed to register hook for {class}::{method}: {error}', [
                'class' => $class,
                'method' => $method,
                'instrumentation' => $instrumentation->getName(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param HookInstrumentationInterface[] $instrumentationCollection
     */
    public function registerHooks(array $instrumentationCollection): void
    {
        foreach ($instrumentationCollection as $instrumentation) {
            $this->registerHook($instrumentation);
        }
    }
}

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
    private LoggerInterface $logger;

    public function __construct(
        private HookManagerInterface $hookManager,
        ?LoggerInterface $logger,
        private bool $enabled = true,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function registerHook(HookInstrumentationInterface $instrumentation): void
    {
        if (!$this->enabled) {
            return;
        }
        $class = $instrumentation->getClass();
        $method = $instrumentation->getMethod();

        try {
            $logger = $this->logger;
            $preHook = static function () use ($instrumentation, $logger, $class, $method): void {
                try {
                    $instrumentation->pre();
                    $logger->debug(sprintf('Successfully executed pre hook for %s::%s', $class, $method));
                } catch (Throwable $throwable) {
                    $logger->error("Error in hook pre(): {error}", ['error' => $throwable->getMessage()]);

                    throw $throwable;
                }
            };
            $postHook = static function () use ($instrumentation, $logger, $class, $method): void {
                try {
                    $instrumentation->post();
                    $logger->debug(sprintf('Successfully executed post hook for %s::%s', $class, $method));
                } catch (Throwable $throwable) {
                    $logger->error("Error in hook post(): {error}", ['error' => $throwable->getMessage()]);

                    throw $throwable;
                }
            };

            $this->hookManager->hook($class, $method, $preHook, $postHook);

            $this->logger->debug('Successfully registered hook for {class}::{method}', [
                'class' => $class,
                'method' => $method,
                'instrumentation' => $instrumentation->getName(),
            ]);
        } catch (Throwable $throwable) {
            $this->logger->error('Failed to register hook for {class}::{method}: {error}', [
                'class' => $class,
                'method' => $method,
                'instrumentation' => $instrumentation->getName(),
                'error' => $throwable->getMessage(),
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

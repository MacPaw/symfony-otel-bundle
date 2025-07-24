<?php

declare(strict_types=1);

namespace Macpaw\SymfonyOtelBundle\DependencyInjection;

use Macpaw\SymfonyOtelBundle\Instrumentation\HookInstrumentationInterface;
use Macpaw\SymfonyOtelBundle\Service\HookManagerService;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SymfonyOtelCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        /** @var array<int, string> $instrumentations */
        $instrumentations = $container->getParameter('otel_bundle.instrumentations') ?? [];

        /** @var array<string, Definition> $hookInstrumentations */
        $hookInstrumentations = [];

        foreach ($instrumentations as $instrumentation) {
            if ($this->isServiceId($container, $instrumentation)) {
                $this->handleAsService($instrumentation, $container, $hookInstrumentations);
                continue;
            }

            $this->handleAsClass($instrumentation, $container, $hookInstrumentations);
        }

        $hookManagerDefinition = $container->getDefinition(HookManagerService::class);
        $hookManagerDefinition->setLazy(false);
        $hookManagerDefinition->setPublic(count($hookInstrumentations) > 0);

        foreach ($hookInstrumentations as $alias => $nextDefinition) {
            $hookManagerDefinition->addMethodCall('registerHook', [
                new Reference($alias),
            ]);
        }
    }

    private function handleAsClass(string $className, ContainerBuilder $container, array &$hookInstrumentations): void
    {
        $definition = $container->hasDefinition($className)
            ? $container->getDefinition($className)
            : new Definition($className);

        $definition->setAutowired(true);
        $definition->setAutoconfigured(true);

        if (is_subclass_of($className, EventSubscriberInterface::class)) {
            $definition->addTag('kernel.event_subscriber');
        }

        if (is_subclass_of($className, HookInstrumentationInterface::class)) {
            $definition->addTag('otel.hook_instrumentation');
            $hookInstrumentations[$definition->getClass()] = $definition;
        }

        $container->setDefinition($className, $definition);;
    }

    private function handleAsService(string $serviceId, ContainerBuilder $container, array &$hookInstrumentations): void
    {
        $definition = $container->getDefinition($serviceId);
        $className = $definition->getClass();

        if ($className && is_subclass_of($className, HookInstrumentationInterface::class)) {
            $definition->addTag('otel.hook_instrumentation');
            $hookInstrumentations[$serviceId] = $definition;
        }

        if ($className && is_subclass_of($className, EventSubscriberInterface::class)) {
            $definition->addTag('kernel.event_subscriber');
        }
    }

    private function isServiceId(ContainerBuilder $container, string $serviceId): bool
    {
        if ($container->hasDefinition($serviceId)) {
            return $container->getDefinition($serviceId)->getClass() !== $serviceId;
        }

        return false;
    }
}

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
        /** @var ?array<string, string> $instrumentations */
        $instrumentations = $container->getParameter('otel_bundle.instrumentations');
        /** @var array<string, Definition> $hookInstrumentations */
        $hookInstrumentations = [];

        if (is_array($instrumentations) && count($instrumentations) > 0) {
            foreach ($instrumentations as $instrumentationClass) {
                $definition = $container->hasDefinition($instrumentationClass) ?
                    $container->getDefinition($instrumentationClass) : new Definition($instrumentationClass);

                $definition->setAutowired(true);
                $definition->setAutoconfigured(true);

                $className = $definition->getClass();

                if ($className && is_subclass_of($className, EventSubscriberInterface::class)) {
                    $definition->addTag('kernel.event_subscriber');
                }

                if ($className && is_subclass_of($className, HookInstrumentationInterface::class)) {
                    $definition->addTag('otel.hook_instrumentation');
                    $hookInstrumentations[$instrumentationClass] = $definition;
                }

                $container->setDefinition($instrumentationClass, $definition);
            }
        }

        $hookManagerDefinition = $container->getDefinition(HookManagerService::class);
        $hookManagerDefinition->setLazy(false);
        $hookManagerDefinition->setPublic(true);

        foreach ($hookInstrumentations as $alias => $nextDefinition) {
            $hookManagerDefinition->addMethodCall('registerHook', [
                new Reference($alias),
            ]);
        }
    }
}

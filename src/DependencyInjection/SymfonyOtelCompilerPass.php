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
        /** @var ?array<int, string> $instrumentations */
        $instrumentations = $container->getParameter('otel_bundle.instrumentations');
        /** @var array<int, Definition> $hookInstrumentations */
        $hookInstrumentations = [];

        if (is_array($instrumentations) && count($instrumentations) > 0) {
            foreach ($instrumentations as $instrumentationClass) {
                $definition = $container->hasDefinition($instrumentationClass) ?
                    $container->getDefinition($instrumentationClass) : new Definition($instrumentationClass);

                $definition->setAutowired(true);
                $definition->setAutoconfigured(true);

                if (is_subclass_of($instrumentationClass, EventSubscriberInterface::class)) {
                    $definition->addTag('kernel.event_subscriber');
                }

                if (is_subclass_of($instrumentationClass, HookInstrumentationInterface::class)) {
                    $definition->addTag('otel.hook_instrumentation');
                    $hookInstrumentations[] = $definition;
                }

                $container->setDefinition($instrumentationClass, $definition);
            }
        }

        $hookManagerDefinition = $container->getDefinition(HookManagerService::class);
        $hookManagerDefinition->setLazy(false);


        if (count($hookInstrumentations) > 0) {
            $hookManagerDefinition->setPublic(true);

            foreach ($hookInstrumentations as $nextDefinition) {
                $hookManagerDefinition->addMethodCall('registerHook', [
                    new Reference((string) $nextDefinition->getClass()),
                ]);
            }
        }
    }
}

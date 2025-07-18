<?php

declare(strict_types=1);

namespace Macpaw\SymfonyOtelBundle\DependencyInjection;

use Macpaw\SymfonyOtelBundle\Instrumentation\HookInstrumentationInterface;
use Macpaw\SymfonyOtelBundle\Service\HookManagerService;
use Macpaw\SymfonyOtelBundle\Span\InstrumentationEventSubscriber;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SymfonyOtelCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        /** @var ?array<int, string> $instrumentations */
        $instrumentations = $container->getParameter('otel_bundle.instrumentations');

        if (is_array($instrumentations) && count($instrumentations) > 0) {
            foreach ($instrumentations as $instrumentationClass) {
                $definition = $container->hasDefinition($instrumentationClass) ?
                    $container->getDefinition($instrumentationClass) : new Definition($instrumentationClass);

                $definition->setAutowired(true);

                if (is_subclass_of($instrumentationClass, EventSubscriberInterface::class)) {
                    $definition->addTag('kernel.event_subscriber');
                }

                if (is_subclass_of($instrumentationClass, HookInstrumentationInterface::class)) {
                    $definition->addTag('otel.hook_instrumentation');
                }

                $container->setDefinition($instrumentationClass, $definition);
            }
        }

        $hookServices = $container->findTaggedServiceIds('otel.hook_instrumentation');

        if (count($hookServices) > 0) {
            if (!$container->hasDefinition(HookManagerService::class)) {
                $hookManagerDefinition = new Definition(HookManagerService::class);
                $hookManagerDefinition->setAutowired(true);
                $hookManagerDefinition->setPublic(true);
                $container->setDefinition(HookManagerService::class, $hookManagerDefinition);
            } else {
                $hookManagerDefinition = $container->getDefinition(HookManagerService::class);
                $hookManagerDefinition->setPublic(true);
            }

            foreach ($hookServices as $serviceId => $tags) {
                $hookManagerDefinition->addMethodCall('registerHook', [
                    new \Symfony\Component\DependencyInjection\Reference($serviceId)
                ]);
            }
        }
    }
}

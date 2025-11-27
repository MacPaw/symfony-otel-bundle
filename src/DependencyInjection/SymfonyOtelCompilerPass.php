<?php

declare(strict_types=1);

namespace Macpaw\SymfonyOtelBundle\DependencyInjection;

use Macpaw\SymfonyOtelBundle\Attribute\TraceSpan;
use Macpaw\SymfonyOtelBundle\Instrumentation\AttributeMethodInstrumentation;
use Macpaw\SymfonyOtelBundle\Instrumentation\HookInstrumentationInterface;
use Macpaw\SymfonyOtelBundle\Registry\InstrumentationRegistry;
use Macpaw\SymfonyOtelBundle\Service\HookManagerService;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Throwable;

class SymfonyOtelCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        /** @var ?array<string, string> $instrumentations */
        $instrumentations = $container->getParameter('otel_bundle.instrumentations');

        if (is_array($instrumentations) && $instrumentations !== []) {
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
                }

                $container->setDefinition($instrumentationClass, $definition);
            }
        }

        // Discover #[TraceSpan] attributes on service methods and register hook instrumentations
        foreach ($container->getDefinitions() as $serviceId => $def) {
            $class = $def->getClass();
            if (!is_string($class)) {
                continue;
            }

            if (!class_exists($class)) {
                continue;
            }

            try {
                $refl = new ReflectionClass($class);
            } catch (Throwable) {
                continue;
            }

            foreach ($refl->getMethods() as $method) {
                $attrs = $method->getAttributes(TraceSpan::class);
                if ($attrs === []) {
                    continue;
                }

                foreach ($attrs as $attr) {
                    /** @var TraceSpan $meta */
                    $meta = $attr->newInstance();
                    $instrDef = new Definition(AttributeMethodInstrumentation::class, [
                        new Reference(InstrumentationRegistry::class),
                        new Reference(TracerInterface::class),
                        new Reference(TextMapPropagatorInterface::class),
                        $class,
                        $method->getName(),
                        $meta->name,
                        $meta->kind,
                        $meta->attributes,
                    ]);
                    $instrDef->setAutowired(true);
                    $instrDef->setAutoconfigured(true);
                    $instrDef->addTag('otel.hook_instrumentation');

                    $serviceAlias = sprintf(
                        'otel.attr_instrumentation.%s.%s.%s',
                        $class,
                        $method->getName(),
                        $meta->name,
                    );
                    $container->setDefinition($serviceAlias, $instrDef);
                }
            }
        }

        // Ensure HookManagerService registers all tagged instrumentations
        $hookManagerDefinition = $container->getDefinition(HookManagerService::class);
        $hookManagerDefinition->setLazy(false);
        $hookManagerDefinition->setPublic(true);

        $tagged = $container->findTaggedServiceIds('otel.hook_instrumentation');
        foreach (array_keys($tagged) as $serviceId) {
            $hookManagerDefinition->addMethodCall('registerHook', [new Reference($serviceId)]);
        }
    }
}

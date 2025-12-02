<?php

declare(strict_types=1);

namespace Macpaw\SymfonyOtelBundle\DependencyInjection;

use ReflectionException;
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

class SymfonyOtelCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        /** @var ?array<string, string> $instrumentations */
        $instrumentations = $container->getParameter('otel_bundle.instrumentations');

        if (is_array($instrumentations) && $instrumentations !== []) {
            foreach ($instrumentations as $instrumentationClass) {
                // Use the class name as service ID to avoid conflicts
                $serviceId = $instrumentationClass;
                
                // Skip if already processed to avoid duplicate registrations
                if ($container->hasDefinition($serviceId)) {
                    $definition = $container->getDefinition($serviceId);
                    // If it already has the tag, skip to avoid re-processing
                    $tags = $definition->getTags();
                    if (isset($tags['otel.hook_instrumentation'])) {
                        continue;
                    }
                } else {
                    $definition = new Definition($instrumentationClass);
                    $definition->setClass($instrumentationClass);
                }

                $definition->setAutowired(true);
                $definition->setAutoconfigured(true);

                $className = $definition->getClass() ?? $instrumentationClass;

                // Use reflection to check class hierarchy without triggering autoloading issues
                try {
                    if (!class_exists($className, false)) {
                        // Only autoload if not already loaded
                        if (!class_exists($className, true)) {
                            continue;
                        }
                    }

                    $reflection = new ReflectionClass($className);

                    if ($reflection->implementsInterface(EventSubscriberInterface::class)) {
                        $tags = $definition->getTags();
                        if (!isset($tags['kernel.event_subscriber'])) {
                            $definition->addTag('kernel.event_subscriber');
                        }
                    }

                    if ($reflection->implementsInterface(HookInstrumentationInterface::class)) {
                        $tags = $definition->getTags();
                        if (!isset($tags['otel.hook_instrumentation'])) {
                            $definition->addTag('otel.hook_instrumentation');
                        }
                    }
                } catch (ReflectionException) {
                    // Skip if class cannot be reflected
                    continue;
                }

                $container->setDefinition($serviceId, $definition);
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

            // ReflectionClass constructor can throw ReflectionException if class doesn't exist,
            // but we already checked with class_exists above, so this should never throw.
            // However, we keep the try-catch for safety in case of edge cases.
            try {
                $refl = new ReflectionClass($class);
                // @phpstan-ignore-next-line catch.neverThrown
            } catch (ReflectionException) {
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

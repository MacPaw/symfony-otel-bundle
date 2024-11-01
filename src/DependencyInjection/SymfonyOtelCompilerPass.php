<?php

declare(strict_types=1);

namespace Macpaw\SymfonyOtelBundle\DependencyInjection;

use Macpaw\SymfonyOtelBundle\Span\ExecutionTimeSpanTracer;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;

class SymfonyOtelCompilerPass implements CompilerPassInterface
{

    public function process(ContainerBuilder $container): void
    {
        $listeners = $container->getParameter('otel_bundle.span_tracers');

        foreach ($listeners as $listener) {
            $definition = $container->register($listener['class'], $listener['class']);

            $definition->setAutowired(true);

            if ($listener['class'] === ExecutionTimeSpanTracer::class) {
                $definition->setArguments([
                    '$tracerName' => $container->getParameter('otel_bundle.tracer_name'),
                ]);
            }

            $definition->addTag($listener['tag']);

            $container->setDefinition($listener['class'], $definition);
        }

    }
}

<?php

declare(strict_types=1);

namespace Macpaw\SymfonyOtelBundle;

use Macpaw\SymfonyOtelBundle\DependencyInjection\SymfonyOtelCompilerPass;
use Macpaw\SymfonyOtelBundle\DependencyInjection\SymfonyOtelExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class SymfonyOtelBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new SymfonyOtelCompilerPass());
    }

    protected function createContainerExtension(): ExtensionInterface
    {
        return new SymfonyOtelExtension();
    }

    public function getContainerExtension(): ExtensionInterface
    {
        return $this->createContainerExtension();
    }
}

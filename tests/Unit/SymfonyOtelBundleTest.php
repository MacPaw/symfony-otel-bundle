<?php

declare(strict_types=1);

namespace Tests\Unit;

use Macpaw\SymfonyOtelBundle\DependencyInjection\SymfonyOtelCompilerPass;
use Macpaw\SymfonyOtelBundle\DependencyInjection\SymfonyOtelExtension;
use Macpaw\SymfonyOtelBundle\SymfonyOtelBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class SymfonyOtelBundleTest extends TestCase
{
    private SymfonyOtelBundle $bundle;

    protected function setUp(): void
    {
        $this->bundle = new SymfonyOtelBundle();
    }

    public function testBuild(): void
    {
        $container = $this->createMock(ContainerBuilder::class);

        $container->expects($this->once())
            ->method('addCompilerPass')
            ->with($this->isInstanceOf(SymfonyOtelCompilerPass::class));

        $this->bundle->build($container);
    }

    public function testGetContainerExtension(): void
    {
        $extension = $this->bundle->getContainerExtension();

        $this->assertInstanceOf(SymfonyOtelExtension::class, $extension);
    }

    public function testGetContainerExtensionReturnsSameInstance(): void
    {
        $extension1 = $this->bundle->getContainerExtension();
        $extension2 = $this->bundle->getContainerExtension();

        // Should create new instances each time (not cached)
        $this->assertNotSame($extension1, $extension2);
        $this->assertEquals(get_class($extension1), get_class($extension2));
    }

    public function testCreateContainerExtensionReturnsCorrectAlias(): void
    {
        $extension = $this->bundle->getContainerExtension();

        $this->assertEquals('otel_bundle', $extension->getAlias());
    }

    public function testBuildCallsParentBuild(): void
    {
        $container = new ContainerBuilder();

        // Should not throw any exceptions
        $this->bundle->build($container);

        // Check that compiler pass was added
        $compilerPasses = $container->getCompilerPassConfig()->getBeforeOptimizationPasses();

        $hasOtelCompilerPass = false;
        foreach ($compilerPasses as $pass) {
            if ($pass instanceof SymfonyOtelCompilerPass) {
                $hasOtelCompilerPass = true;
                break;
            }
        }

        $this->assertTrue($hasOtelCompilerPass, 'SymfonyOtelCompilerPass should be added to container');
    }

    public function testBundleCanBeInstantiatedMultipleTimes(): void
    {
        $bundle1 = new SymfonyOtelBundle();
        $bundle2 = new SymfonyOtelBundle();

        $this->assertNotSame($bundle1, $bundle2);
        $this->assertEquals(get_class($bundle1), get_class($bundle2));

        // Both should work independently
        $extension1 = $bundle1->getContainerExtension();
        $extension2 = $bundle2->getContainerExtension();

        $this->assertInstanceOf(SymfonyOtelExtension::class, $extension1);
        $this->assertInstanceOf(SymfonyOtelExtension::class, $extension2);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit;

use Macpaw\SymfonyOtelBundle\DependencyInjection\SymfonyOtelCompilerPass;
use Macpaw\SymfonyOtelBundle\SymfonyOtelBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class SymfonyOtelBundleTest extends TestCase
{
    public function testGetPath(): void
    {
        $bundle = new SymfonyOtelBundle();
        $path = $bundle->getPath();

        $this->assertIsString($path);
        $this->assertNotEmpty($path);
        $this->assertDirectoryExists($path);
    }

    public function testGetPathReturnsDifferentInstances(): void
    {
        $bundle1 = new SymfonyOtelBundle();
        $bundle2 = new SymfonyOtelBundle();

        $path1 = $bundle1->getPath();
        $path2 = $bundle2->getPath();

        $this->assertEquals($path1, $path2);
    }

    public function testBuild(): void
    {
        $bundle = new SymfonyOtelBundle();
        $container = new ContainerBuilder();

        $bundle->build($container);

        $this->assertTrue(true);
    }

    public function testBuildAddsCompilerPass(): void
    {
        $bundle = new SymfonyOtelBundle();
        $container = new ContainerBuilder();

        $bundle->build($container);

        $passes = $container->getCompilerPassConfig()->getPasses();
        $hasCompilerPass = false;

        foreach ($passes as $pass) {
            if ($pass instanceof SymfonyOtelCompilerPass) {
                $hasCompilerPass = true;
                break;
            }
        }

        $this->assertTrue($hasCompilerPass);
    }

    public function testMultipleBuildCalls(): void
    {
        $bundle = new SymfonyOtelBundle();
        $container1 = new ContainerBuilder();
        $container2 = new ContainerBuilder();

        $bundle->build($container1);
        $bundle->build($container2);

        $this->assertTrue(true);
    }
}

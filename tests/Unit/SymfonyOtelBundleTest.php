<?php

declare(strict_types=1);

namespace Tests\Unit;

use Macpaw\SymfonyOtelBundle\SymfonyOtelBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

class SymfonyOtelBundleTest extends TestCase
{
    private SymfonyOtelBundle $bundle;

    protected function setUp(): void
    {
        $this->bundle = new SymfonyOtelBundle();
    }

    public function testBundleImplementsBundleInterface(): void
    {
        $this->assertInstanceOf(BundleInterface::class, $this->bundle);
    }

    public function testBundleName(): void
    {
        $this->assertEquals('SymfonyOtelBundle', $this->bundle->getName());
    }

    public function testBundleNamespace(): void
    {
        $this->assertEquals('Macpaw\SymfonyOtelBundle', $this->bundle->getNamespace());
    }

    public function testBundlePath(): void
    {
        $expectedPath = dirname(__DIR__, 2) . '/src';
        $this->assertEquals($expectedPath, $this->bundle->getPath());
    }

    public function testBuildMethod(): void
    {
        $container = new ContainerBuilder();
        $this->bundle->build($container);

        $this->assertTrue(true);
    }

    public function testGetContainerExtension(): void
    {
        $extension = $this->bundle->getContainerExtension();

        $this->assertNotNull($extension);
        $this->assertEquals('otel_bundle', $extension->getAlias());
    }
}

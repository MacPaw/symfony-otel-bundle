<?php

declare(strict_types=1);

namespace Tests\Unit\Registry;

use Macpaw\SymfonyOtelBundle\Registry\InstrumentationRegistry;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ScopeInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class InstrumentationRegistryTest extends TestCase
{
    private InstrumentationRegistry $registry;

    public function testAddSpan(): void
    {
        $span = $this->createMock(SpanInterface::class);
        $this->registry->addSpan($span, 'test_span');

        $this->assertSame($span, $this->registry->getSpan('test_span'));
    }

    public function testGetSpanWhenNotExists(): void
    {
        $this->assertNull($this->registry->getSpan('non_existent'));
    }

    public function testSetContext(): void
    {
        $context = Context::getCurrent();
        $this->registry->setContext($context);

        $this->assertSame($context, $this->registry->getContext());
    }

    public function testSetScope(): void
    {
        $scope = $this->createMock(ScopeInterface::class);
        $this->registry->setScope($scope);

        $this->assertSame($scope, $this->registry->getScope());
    }

    public function testGetSpans(): void
    {
        $span1 = $this->createMock(SpanInterface::class);
        $span2 = $this->createMock(SpanInterface::class);

        $this->registry->addSpan($span1, 'span1');
        $this->registry->addSpan($span2, 'span2');

        $spans = $this->registry->getSpans();
        $this->assertCount(2, $spans);
        $this->assertSame($span1, $spans['span1']);
        $this->assertSame($span2, $spans['span2']);
    }

    public function testRemoveSpanWhenExists(): void
    {
        $span = $this->createMock(SpanInterface::class);
        $this->registry->addSpan($span, 'test_span');
        $this->assertNotNull($this->registry->getSpan('test_span'));

        $this->registry->removeSpan('test_span');
        $this->assertNull($this->registry->getSpan('test_span'));
    }

    public function testRemoveSpanWhenNotExists(): void
    {
        // Should not throw exception when removing non-existent span
        $this->registry->removeSpan('non_existent');
        $this->assertNull($this->registry->getSpan('non_existent'));
    }

    public function testClearSpans(): void
    {
        $span1 = $this->createMock(SpanInterface::class);
        $span2 = $this->createMock(SpanInterface::class);

        $this->registry->addSpan($span1, 'span1');
        $this->registry->addSpan($span2, 'span2');
        $this->assertCount(2, $this->registry->getSpans());

        $this->registry->clearSpans();
        $this->assertCount(0, $this->registry->getSpans());
    }

    public function testDetachScope(): void
    {
        $scope = $this->createMock(ScopeInterface::class);
        $scope->expects($this->once())
            ->method('detach');

        $this->registry->setScope($scope);
        $this->registry->detachScope();
    }

    public function testDetachScopeWhenScopeIsNull(): void
    {
        // Should not throw exception when scope is null
        $this->registry->detachScope();
        $this->assertNull($this->registry->getScope());
    }

    public function testDetachScopeWhenExceptionThrown(): void
    {
        $scope = $this->createMock(ScopeInterface::class);
        $scope->expects($this->once())
            ->method('detach')
            ->willThrowException(new RuntimeException('Scope already detached'));

        $this->registry->setScope($scope);
        // Should not throw exception, should catch and ignore
        $this->registry->detachScope();
    }

    public function testClearScope(): void
    {
        $scope = $this->createMock(ScopeInterface::class);
        $scope->expects($this->once())
            ->method('detach');

        $this->registry->setScope($scope);
        $this->registry->clearScope();

        $this->assertNull($this->registry->getScope());
    }

    public function testClearScopeWhenScopeIsNull(): void
    {
        // Should not throw exception when scope is null
        $this->registry->clearScope();
        $this->assertNull($this->registry->getScope());
    }

    public function testGetContextWhenNull(): void
    {
        $this->assertNull($this->registry->getContext());
    }

    public function testGetScopeWhenNull(): void
    {
        $this->assertNull($this->registry->getScope());
    }

    protected function setUp(): void
    {
        $this->registry = new InstrumentationRegistry();
    }
}


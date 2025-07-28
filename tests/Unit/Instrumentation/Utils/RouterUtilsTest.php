<?php

declare(strict_types=1);

namespace Tests\Unit\Instrumentation\Utils;

use Macpaw\SymfonyOtelBundle\Instrumentation\Utils\RouterUtils;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class RouterUtilsTest extends TestCase
{
    public function testGetRouteNameWithCurrentRequest(): void
    {
        $requestStack = $this->createMock(RequestStack::class);
        $request = $this->createMock(Request::class);
        $attributes = $this->createMock(ParameterBag::class);

        $requestStack->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn($request);

        $requestStack->expects($this->once())
            ->method('getMainRequest')
            ->willReturn(null);

        $requestStack->expects($this->once())
            ->method('getParentRequest')
            ->willReturn(null);

        $request->attributes = $attributes;
        $attributes->expects($this->once())
            ->method('get')
            ->with('_route')
            ->willReturn('test_route');

        $routerUtils = new RouterUtils($requestStack);
        $result = $routerUtils->getRouteName();

        $this->assertEquals('test_route', $result);
    }

    public function testGetRouteNameWithMainRequest(): void
    {
        $requestStack = $this->createMock(RequestStack::class);
        $mainRequest = $this->createMock(Request::class);
        $attributes = $this->createMock(ParameterBag::class);

        $requestStack->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn(null);

        $requestStack->expects($this->once())
            ->method('getMainRequest')
            ->willReturn($mainRequest);

        $requestStack->expects($this->once())
            ->method('getParentRequest')
            ->willReturn(null);

        $mainRequest->attributes = $attributes;
        $attributes->expects($this->once())
            ->method('get')
            ->with('_route')
            ->willReturn('main_route');

        $routerUtils = new RouterUtils($requestStack);
        $result = $routerUtils->getRouteName();

        $this->assertEquals('main_route', $result);
    }

    public function testGetRouteNameWithParentRequest(): void
    {
        $requestStack = $this->createMock(RequestStack::class);
        $parentRequest = $this->createMock(Request::class);
        $attributes = $this->createMock(ParameterBag::class);

        $requestStack->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn(null);

        $requestStack->expects($this->once())
            ->method('getMainRequest')
            ->willReturn(null);

        $requestStack->expects($this->once())
            ->method('getParentRequest')
            ->willReturn($parentRequest);

        $parentRequest->attributes = $attributes;
        $attributes->expects($this->once())
            ->method('get')
            ->with('_route')
            ->willReturn('parent_route');

        $routerUtils = new RouterUtils($requestStack);
        $result = $routerUtils->getRouteName();

        $this->assertEquals('parent_route', $result);
    }

    public function testGetRouteNameWithNoRequest(): void
    {
        $requestStack = $this->createMock(RequestStack::class);

        $requestStack->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn(null);

        $requestStack->expects($this->once())
            ->method('getMainRequest')
            ->willReturn(null);

        $requestStack->expects($this->once())
            ->method('getParentRequest')
            ->willReturn(null);

        $routerUtils = new RouterUtils($requestStack);
        $result = $routerUtils->getRouteName();

        $this->assertNull($result);
    }

    public function testGetRouteNameWithNullRoute(): void
    {
        $requestStack = $this->createMock(RequestStack::class);
        $request = $this->createMock(Request::class);
        $attributes = $this->createMock(ParameterBag::class);

        $requestStack->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn($request);

        $requestStack->expects($this->once())
            ->method('getMainRequest')
            ->willReturn(null);

        $requestStack->expects($this->once())
            ->method('getParentRequest')
            ->willReturn(null);

        $request->attributes = $attributes;
        $attributes->expects($this->once())
            ->method('get')
            ->with('_route')
            ->willReturn(null);

        $routerUtils = new RouterUtils($requestStack);
        $result = $routerUtils->getRouteName();

        $this->assertNull($result);
    }

    public function testGetRouteParamsWithCurrentRequest(): void
    {
        $requestStack = $this->createMock(RequestStack::class);
        $request = $this->createMock(Request::class);
        $attributes = $this->createMock(ParameterBag::class);
        $expectedParams = ['id' => 123, 'name' => 'test'];

        $requestStack->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn($request);

        $requestStack->expects($this->once())
            ->method('getMainRequest')
            ->willReturn(null);

        $requestStack->expects($this->once())
            ->method('getParentRequest')
            ->willReturn(null);

        $request->attributes = $attributes;
        $attributes->expects($this->once())
            ->method('get')
            ->with('_route_params')
            ->willReturn($expectedParams);

        $routerUtils = new RouterUtils($requestStack);
        $result = $routerUtils->getRouteParams();

        $this->assertEquals($expectedParams, $result);
    }

    public function testGetRouteParamsWithMainRequest(): void
    {
        $requestStack = $this->createMock(RequestStack::class);
        $mainRequest = $this->createMock(Request::class);
        $attributes = $this->createMock(ParameterBag::class);
        $expectedParams = ['user_id' => 456];

        $requestStack->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn(null);

        $requestStack->expects($this->once())
            ->method('getMainRequest')
            ->willReturn($mainRequest);

        $requestStack->expects($this->once())
            ->method('getParentRequest')
            ->willReturn(null);

        $mainRequest->attributes = $attributes;
        $attributes->expects($this->once())
            ->method('get')
            ->with('_route_params')
            ->willReturn($expectedParams);

        $routerUtils = new RouterUtils($requestStack);
        $result = $routerUtils->getRouteParams();

        $this->assertEquals($expectedParams, $result);
    }

    public function testGetRouteParamsWithParentRequest(): void
    {
        $requestStack = $this->createMock(RequestStack::class);
        $parentRequest = $this->createMock(Request::class);
        $attributes = $this->createMock(ParameterBag::class);
        $expectedParams = ['parent_id' => 789];

        $requestStack->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn(null);

        $requestStack->expects($this->once())
            ->method('getMainRequest')
            ->willReturn(null);

        $requestStack->expects($this->once())
            ->method('getParentRequest')
            ->willReturn($parentRequest);

        $parentRequest->attributes = $attributes;
        $attributes->expects($this->once())
            ->method('get')
            ->with('_route_params')
            ->willReturn($expectedParams);

        $routerUtils = new RouterUtils($requestStack);
        $result = $routerUtils->getRouteParams();

        $this->assertEquals($expectedParams, $result);
    }

    public function testGetRouteParamsWithNoRequest(): void
    {
        $requestStack = $this->createMock(RequestStack::class);

        $requestStack->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn(null);

        $requestStack->expects($this->once())
            ->method('getMainRequest')
            ->willReturn(null);

        $requestStack->expects($this->once())
            ->method('getParentRequest')
            ->willReturn(null);

        $routerUtils = new RouterUtils($requestStack);
        $result = $routerUtils->getRouteParams();

        $this->assertNull($result);
    }

    public function testGetRouteParamsWithNullParams(): void
    {
        $requestStack = $this->createMock(RequestStack::class);
        $request = $this->createMock(Request::class);
        $attributes = $this->createMock(ParameterBag::class);

        $requestStack->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn($request);

        $requestStack->expects($this->once())
            ->method('getMainRequest')
            ->willReturn(null);

        $requestStack->expects($this->once())
            ->method('getParentRequest')
            ->willReturn(null);

        $request->attributes = $attributes;
        $attributes->expects($this->once())
            ->method('get')
            ->with('_route_params')
            ->willReturn(null);

        $routerUtils = new RouterUtils($requestStack);
        $result = $routerUtils->getRouteParams();

        $this->assertNull($result);
    }

    public function testGetRouteParamsWithNonArrayParams(): void
    {
        $requestStack = $this->createMock(RequestStack::class);
        $request = $this->createMock(Request::class);
        $attributes = $this->createMock(ParameterBag::class);

        $requestStack->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn($request);

        $requestStack->expects($this->once())
            ->method('getMainRequest')
            ->willReturn(null);

        $requestStack->expects($this->once())
            ->method('getParentRequest')
            ->willReturn(null);

        $request->attributes = $attributes;
        $attributes->expects($this->once())
            ->method('get')
            ->with('_route_params')
            ->willReturn('not_an_array');

        $routerUtils = new RouterUtils($requestStack);
        $result = $routerUtils->getRouteParams();

        $this->assertNull($result);
    }

    public function testGetRouteParamsWithEmptyArray(): void
    {
        $requestStack = $this->createMock(RequestStack::class);
        $request = $this->createMock(Request::class);
        $attributes = $this->createMock(ParameterBag::class);

        $requestStack->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn($request);

        $requestStack->expects($this->once())
            ->method('getMainRequest')
            ->willReturn(null);

        $requestStack->expects($this->once())
            ->method('getParentRequest')
            ->willReturn(null);

        $request->attributes = $attributes;
        $attributes->expects($this->once())
            ->method('get')
            ->with('_route_params')
            ->willReturn([]);

        $routerUtils = new RouterUtils($requestStack);
        $result = $routerUtils->getRouteParams();

        $this->assertEquals([], $result);
    }
}

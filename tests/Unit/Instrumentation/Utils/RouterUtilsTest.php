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

    public function testGetRequestReturnsCurrentRequestFirst(): void
    {
        $requestStack = $this->createMock(RequestStack::class);
        $currentRequest = $this->createMock(Request::class);

        $requestStack->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn($currentRequest);

        // getMainRequest and getParentRequest may be called as part of the coalesce chain
        // but their results won't be used when currentRequest is not null
        $requestStack->expects($this->once())
            ->method('getMainRequest')
            ->willReturn(null);

        $requestStack->expects($this->once())
            ->method('getParentRequest')
            ->willReturn(null);

        $routerUtils = new RouterUtils($requestStack);
        $result = $routerUtils->getRequest();

        $this->assertSame($currentRequest, $result);
    }

    public function testGetRequestReturnsMainRequestWhenCurrentIsNull(): void
    {
        $requestStack = $this->createMock(RequestStack::class);
        $mainRequest = $this->createMock(Request::class);

        $requestStack->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn(null);

        $requestStack->expects($this->once())
            ->method('getMainRequest')
            ->willReturn($mainRequest);

        // getParentRequest may be called as part of the coalesce chain, but its result won't be used
        $requestStack->expects($this->once())
            ->method('getParentRequest')
            ->willReturn(null);

        $routerUtils = new RouterUtils($requestStack);
        $result = $routerUtils->getRequest();

        $this->assertSame($mainRequest, $result);
    }

    public function testGetRouteParamsCastsKeysToString(): void
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
        
        // Use integer keys to test casting
        $routeParams = [123 => 'value1', 456 => 'value2'];
        $attributes->expects($this->once())
            ->method('get')
            ->with('_route_params')
            ->willReturn($routeParams);

        $routerUtils = new RouterUtils($requestStack);
        $result = $routerUtils->getRouteParams();

        // Verify keys are cast to strings
        $this->assertNotNull($result);
        $this->assertIsArray($result);
        /** @var array<string, mixed> $result */
        $resultArray = $result;
        $this->assertArrayHasKey('123', $resultArray);
        $this->assertArrayHasKey('456', $resultArray);
        $this->assertSame('value1', $resultArray['123']);
        $this->assertSame('value2', $resultArray['456']);
    }

    public function testGetRequestCoalesceOrder(): void
    {
        // Test that coalesce order is: currentRequest ?? mainRequest ?? parentRequest
        $requestStack = $this->createMock(RequestStack::class);
        $currentRequest = $this->createMock(Request::class);
        $mainRequest = $this->createMock(Request::class);
        $parentRequest = $this->createMock(Request::class);

        // Test order: currentRequest is used first
        $requestStack->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn($currentRequest);
        $requestStack->expects($this->once())
            ->method('getMainRequest')
            ->willReturn($mainRequest);
        $requestStack->expects($this->once())
            ->method('getParentRequest')
            ->willReturn($parentRequest);

        $routerUtils = new RouterUtils($requestStack);
        $result = $routerUtils->getRequest();

        // Should return currentRequest (first in coalesce chain)
        $this->assertSame($currentRequest, $result);
    }

    public function testGetRequestCoalesceOrderIsCorrect(): void
    {
        // Test that coalesce order is: currentRequest ?? mainRequest ?? parentRequest
        // NOT: currentRequest ?? parentRequest ?? mainRequest
        // NOT: mainRequest ?? currentRequest ?? parentRequest
        
        $requestStack = $this->createMock(RequestStack::class);
        $mainRequest = $this->createMock(Request::class);
        $parentRequest = $this->createMock(Request::class);

        // When currentRequest is null, should use mainRequest (not parentRequest)
        $requestStack->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn(null);

        $requestStack->expects($this->once())
            ->method('getMainRequest')
            ->willReturn($mainRequest);

        $requestStack->expects($this->once())
            ->method('getParentRequest')
            ->willReturn($parentRequest);

        $routerUtils = new RouterUtils($requestStack);
        $result = $routerUtils->getRequest();

        // Should return mainRequest (second in coalesce chain), not parentRequest
        $this->assertSame($mainRequest, $result);
    }

    public function testGetRouteNameAssertChecksStringOrNull(): void
    {
        // Test that assert checks: is_string($routeName) || is_null($routeName)
        // NOT: !is_string($routeName) || !is_null($routeName)
        
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
        
        // Test with string route name
        $attributes->expects($this->once())
            ->method('get')
            ->with('_route')
            ->willReturn('test_route');

        $routerUtils = new RouterUtils($requestStack);
        $result = $routerUtils->getRouteName();

        // Should return the string route name (assert should pass)
        $this->assertSame('test_route', $result);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use Macpaw\SymfonyOtelBundle\Instrumentation\Utils\RouterUtils;
use Macpaw\SymfonyOtelBundle\Service\HttpClientDecorator;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

class HttpClientDecoratorTest extends TestCase
{
    private HttpClientInterface&MockObject $httpClient;

    private RequestStack&MockObject $requestStack;

    private TextMapPropagatorInterface&MockObject $propagator;

    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->propagator = $this->createMock(TextMapPropagatorInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    private function createDecorator(): HttpClientDecorator
    {
        $routerUtils = new RouterUtils($this->requestStack);

        return new HttpClientDecorator(
            $this->httpClient,
            $this->requestStack,
            $this->propagator,
            $routerUtils,
            $this->logger
        );
    }

    public function testRequestAddsXRequestIdFromRequest(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $request = $this->createMock(Request::class);
        $headers = $this->createMock(HeaderBag::class);

        $headers->method('get')->with('X-Request-Id')->willReturn('test-request-id');
        $request->headers = $headers;

        $this->requestStack->method('getCurrentRequest')->willReturn($request);
        $this->requestStack->method('getMainRequest')->willReturn($request);
        $this->requestStack->method('getParentRequest')->willReturn(null);
        $this->propagator->method('fields')->willReturn(['traceparent', 'tracestate']);
        $this->propagator->expects($this->once())->method('inject');

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                'https://api.example.com/data',
                $this->callback(function (array $options): bool {
                    /** @var array<string, mixed> $options */
                    /** @var array<string, string> $headers */
                    $headers = $options['headers'] ?? [];
                    return isset($headers['X-Request-Id']) &&
                        $headers['X-Request-Id'] === 'test-request-id';
                })
            )
            ->willReturn($response);

        $this->logger
            ->expects($this->once())
            ->method('debug')
            ->with(
                'Added headers to HTTP request',
                [
                    'request_id' => 'test-request-id',
                    'url' => 'https://api.example.com/data',
                ]
            );

        $decorator = $this->createDecorator();
        $result = $decorator->request('GET', 'https://api.example.com/data', [
            'headers' => ['Content-Type' => 'application/json'],
        ]);

        $this->assertSame($response, $result);
    }

    public function testRequestGeneratesXRequestIdWhenNotPresent(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $request = $this->createMock(Request::class);
        $headers = $this->createMock(HeaderBag::class);

        $headers->method('get')->with('X-Request-Id')->willReturn(null);
        $request->headers = $headers;

        $this->requestStack->method('getCurrentRequest')->willReturn($request);
        $this->requestStack->method('getMainRequest')->willReturn($request);
        $this->requestStack->method('getParentRequest')->willReturn(null);
        $this->propagator->method('fields')->willReturn(['traceparent', 'tracestate']);
        $this->propagator->expects($this->once())->method('inject');

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                'https://api.example.com/data',
                $this->callback(function (array $options): bool {
                    /** @var array<string, mixed> $options */
                    /** @var array<string, string> $headers */
                    $headers = $options['headers'] ?? [];
                    return isset($headers['X-Request-Id']) &&
                        preg_match(
                            '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/',
                            $headers['X-Request-Id']
                        );
                })
            )
            ->willReturn($response);

        $decorator = $this->createDecorator();
        $result = $decorator->request('GET', 'https://api.example.com/data');

        $this->assertSame($response, $result);
    }

    public function testStreamDelegatesToHttpClient(): void
    {
        $responses = [$this->createMock(ResponseInterface::class)];
        $stream = $this->createMock(ResponseStreamInterface::class);

        $this->httpClient
            ->expects($this->once())
            ->method('stream')
            ->with($responses, 30.0)
            ->willReturn($stream);

        $decorator = $this->createDecorator();
        $result = $decorator->stream($responses, 30.0);

        $this->assertSame($stream, $result);
    }

    public function testWithOptionsReturnsNewInstance(): void
    {
        $newOptions = ['timeout' => 60];
        $newHttpClient = $this->createMock(HttpClientInterface::class);

        $this->httpClient
            ->expects($this->once())
            ->method('withOptions')
            ->with($newOptions)
            ->willReturn($newHttpClient);

        $decorator = $this->createDecorator();
        $newDecorator = $decorator->withOptions($newOptions);

        $this->assertInstanceOf(HttpClientDecorator::class, $newDecorator);
        $this->assertNotSame($decorator, $newDecorator);
    }

    public function testRequestWithNullRequestUsesNullSafeOperator(): void
    {
        $response = $this->createMock(ResponseInterface::class);

        $this->requestStack->method('getCurrentRequest')->willReturn(null);
        $this->requestStack->method('getMainRequest')->willReturn(null);
        $this->requestStack->method('getParentRequest')->willReturn(null);
        
        $this->propagator->method('fields')->willReturn(['traceparent', 'tracestate']);
        $this->propagator->expects($this->once())->method('inject');

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                'https://api.example.com/data',
                $this->callback(function (array $options): bool {
                    /** @var array<string, mixed> $options */
                    /** @var array<string, string> $headers */
                    $headers = $options['headers'] ?? [];
                    // When request is null, X-Request-Id should be generated
                    return isset($headers['X-Request-Id']) &&
                        preg_match(
                            '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/',
                            $headers['X-Request-Id']
                        );
                })
            )
            ->willReturn($response);

        $decorator = $this->createDecorator();
        $result = $decorator->request('GET', 'https://api.example.com/data');

        $this->assertSame($response, $result);
    }

    public function testRequestUsesNullSafeOperator(): void
    {
        // Test that null safe operator is used for logger access
        // When logger is null, null safe operator (?->) should not throw
        // If regular operator (->) were used, it would throw an error
        $response = $this->createMock(ResponseInterface::class);

        $this->requestStack->method('getCurrentRequest')->willReturn(null);
        $this->requestStack->method('getMainRequest')->willReturn(null);
        $this->requestStack->method('getParentRequest')->willReturn(null);
        
        $this->propagator->method('fields')->willReturn(['traceparent', 'tracestate']);
        $this->propagator->expects($this->once())->method('inject');

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                'https://api.example.com/data',
                $this->callback(function (array $options): bool {
                    /** @var array<string, mixed> $options */
                    /** @var array<string, string> $headers */
                    $headers = $options['headers'] ?? [];
                    // When request is null, null safe operator should not throw, and ID should be generated
                    return isset($headers['X-Request-Id']) &&
                        preg_match(
                            '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/',
                            $headers['X-Request-Id']
                        );
                })
            )
            ->willReturn($response);

        // Create decorator with null logger to test null-safe operator
        $routerUtils = new RouterUtils($this->requestStack);
        $decorator = new HttpClientDecorator(
            $this->httpClient,
            $this->requestStack,
            $this->propagator,
            $routerUtils,
            null // null logger - null safe operator should handle this
        );
        
        // If null-safe operator is NOT used, this would throw an error
        // Since null-safe operator IS used, this should work fine
        $result = $decorator->request('GET', 'https://api.example.com/data');

        $this->assertSame($response, $result);
    }

    public function testRequestUsesCoalesceForHeaders(): void
    {
        // Test that coalesce is used for headers: $options['headers'] ?? []
        $response = $this->createMock(ResponseInterface::class);
        $request = $this->createMock(Request::class);
        $headers = $this->createMock(HeaderBag::class);

        $headers->method('get')->with('X-Request-Id')->willReturn(null);
        $request->headers = $headers;

        $this->requestStack->method('getCurrentRequest')->willReturn($request);
        $this->requestStack->method('getMainRequest')->willReturn($request);
        $this->requestStack->method('getParentRequest')->willReturn(null);
        $this->propagator->method('fields')->willReturn(['traceparent', 'tracestate']);
        $this->propagator->expects($this->once())->method('inject');

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                'https://api.example.com/data',
                $this->callback(function (array $options): bool {
                    /** @var array<string, mixed> $options */
                    // When headers key doesn't exist, coalesce should return []
                    $headers = $options['headers'] ?? [];
                    return is_array($headers) && isset($headers['X-Request-Id']);
                })
            )
            ->willReturn($response);

        $decorator = $this->createDecorator();
        // Call without headers option to test coalesce
        $result = $decorator->request('GET', 'https://api.example.com/data');

        $this->assertSame($response, $result);
    }

    public function testRequestUsesCorrectCoalesceOrderForHeaders(): void
    {
        // Test that coalesce order is: $options['headers'] ?? []
        // NOT: [] ?? $options['headers']
        // When $options['headers'] exists, it should be used (not replaced by [])
        
        $response = $this->createMock(ResponseInterface::class);
        $request = $this->createMock(Request::class);
        $headers = $this->createMock(HeaderBag::class);

        $headers->method('get')->with('X-Request-Id')->willReturn(null);
        $request->headers = $headers;

        $this->requestStack->method('getCurrentRequest')->willReturn($request);
        $this->requestStack->method('getMainRequest')->willReturn($request);
        $this->requestStack->method('getParentRequest')->willReturn(null);
        $this->propagator->method('fields')->willReturn(['traceparent', 'tracestate']);
        $this->propagator->expects($this->once())->method('inject');

        $existingHeaders = ['Authorization' => 'Bearer token123', 'Content-Type' => 'application/json'];
        
        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                'https://api.example.com/data',
                $this->callback(function (array $options): bool {
                    /** @var array<string, mixed> $options */
                    /** @var array<string, string> $headers */
                    $headers = $options['headers'] ?? [];
                    // When headers exist in options, they should be preserved (coalesce uses them)
                    return isset($headers['Authorization']) &&
                        $headers['Authorization'] === 'Bearer token123' &&
                        isset($headers['Content-Type']) &&
                        $headers['Content-Type'] === 'application/json' &&
                        isset($headers['X-Request-Id']); // New header should be added
                })
            )
            ->willReturn($response);

        $decorator = $this->createDecorator();
        // Call with existing headers to test coalesce uses them
        $result = $decorator->request('GET', 'https://api.example.com/data', [
            'headers' => $existingHeaders,
        ]);

        $this->assertSame($response, $result);
    }
}

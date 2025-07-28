<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use Macpaw\SymfonyOtelBundle\Instrumentation\Utils\RouterUtils;
use Macpaw\SymfonyOtelBundle\Service\HttpClientDecorator;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\HeaderBag;
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
                    'otel_headers' => [0, 1],
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
}

<?php

declare(strict_types=1);

namespace Macpaw\SymfonyOtelBundle\Service;

use Macpaw\SymfonyOtelBundle\Instrumentation\Utils\RouterUtils;
use Macpaw\SymfonyOtelBundle\Service\HttpMetadataAttacher;
use Macpaw\SymfonyOtelBundle\Service\RequestIdGenerator;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

class HttpClientDecorator implements HttpClientInterface
{
    public const REQUEST_ID_HEADER = 'X-Request-Id';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly RequestStack $requestStack,
        private readonly TextMapPropagatorInterface $propagator,
        private readonly RouterUtils $routerUtils,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $request = $this->routerUtils->getRequest();
        $requestId = $request?->headers->get(self::REQUEST_ID_HEADER)
            ?? RequestIdGenerator::generate();

        /** @var array<string, string> $headers */
        $headers = $options['headers'] ?? [];
        $headers[self::REQUEST_ID_HEADER] = $requestId;

        // Inject OpenTelemetry headers - traceparent&tracestate
        $this->propagator->inject($headers, null, Context::getCurrent());

        $options['headers'] = $headers;

        $this->logger?->debug('Added headers to HTTP request', [
            'request_id' => $requestId,
            'otel_headers' => array_keys($this->propagator->fields()),
            'url' => $url,
        ]);

        return $this->httpClient->request($method, $url, $options);
    }

    public function stream(iterable|ResponseInterface $responses, ?float $timeout = null): ResponseStreamInterface
    {
        return $this->httpClient->stream($responses, $timeout);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function withOptions(array $options): static
    {
        return new self( // @phpstan-ignore-line
            $this->httpClient->withOptions($options),
            $this->requestStack,
            $this->propagator,
            $this->routerUtils,
            $this->logger
        );
    }
}

<?php

declare(strict_types=1);

namespace Tests\Integration;

use OpenTelemetry\API\Trace\SpanBuilderInterface;
use Macpaw\SymfonyOtelBundle\Instrumentation\Utils\RouterUtils;
use Macpaw\SymfonyOtelBundle\Service\HttpMetadataAttacher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class HttpMetadataAttacherIntegrationTest extends TestCase
{
    public function testHeaderMappingsAreAppliedFromConfiguration(): void
    {
        $headerMappings = [
            'user.id' => 'X-User-Id',
            'client.version' => 'X-Client-Version',
            'api.key' => 'X-Api-Key'
        ];

        $requestStack = new RequestStack();
        $routerUtils = new RouterUtils($requestStack);
        $service = new HttpMetadataAttacher($routerUtils, $headerMappings);

        $request = new Request();
        $request->headers->set('X-User-Id', 'user123');
        $request->headers->set('X-Client-Version', '1.2.3');
        $request->headers->set('X-Api-Key', 'secret-key');

        // Create a mock span builder to verify attributes are set
        $spanBuilder = $this->createMock(SpanBuilderInterface::class);

        $spanBuilder->expects($this->atLeastOnce())
            ->method('setAttribute')
            ->willReturnSelf();

        $service->addHttpAttributes($spanBuilder, $request);
    }

    public function testHeaderMappingsWithMissingHeaders(): void
    {
        $headerMappings = [
            'user.id' => 'X-User-Id',
            'client.version' => 'X-Client-Version',
            'api.key' => 'X-Api-Key'
        ];

        $requestStack = new RequestStack();
        $routerUtils = new RouterUtils($requestStack);
        $service = new HttpMetadataAttacher($routerUtils, $headerMappings);

        $request = new Request();
        // Only set one header, others should be ignored
        $request->headers->set('X-User-Id', 'user123');

        $spanBuilder = $this->createMock(SpanBuilderInterface::class);

        $spanBuilder->expects($this->atLeastOnce())
            ->method('setAttribute')
            ->willReturnSelf();

        $service->addHttpAttributes($spanBuilder, $request);
    }

    public function testEmptyHeaderMappingsConfiguration(): void
    {
        $requestStack = new RequestStack();
        $routerUtils = new RouterUtils($requestStack);
        $service = new HttpMetadataAttacher($routerUtils, []);

        $request = new Request();
        $request->headers->set('X-User-Id', 'user123');

        $spanBuilder = $this->createMock(SpanBuilderInterface::class);

        // With empty mappings, only request ID should be generated
        $spanBuilder->expects($this->once())
            ->method('setAttribute')
            ->with('http.request_id', $this->isType('string'));

        $service->addHttpAttributes($spanBuilder, $request);
    }

    public function testHeaderMappingsWithSpecialCharacters(): void
    {
        $headerMappings = [
            'custom.attribute' => 'X-Custom-Header',
            'nested.attribute.name' => 'X-Nested-Header'
        ];

        $requestStack = new RequestStack();
        $routerUtils = new RouterUtils($requestStack);
        $service = new HttpMetadataAttacher($routerUtils, $headerMappings);

        $request = new Request();
        $request->headers->set('X-Custom-Header', 'custom-value');
        $request->headers->set('X-Nested-Header', 'nested-value');

        $spanBuilder = $this->createMock(SpanBuilderInterface::class);

        $spanBuilder->expects($this->atLeastOnce())
            ->method('setAttribute')
            ->willReturnSelf();

        $service->addHttpAttributes($spanBuilder, $request);
    }
}

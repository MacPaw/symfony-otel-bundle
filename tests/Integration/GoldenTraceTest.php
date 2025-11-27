<?php

declare(strict_types=1);

namespace Tests\Integration;

use Macpaw\SymfonyOtelBundle\Listeners\RequestRootSpanEventSubscriber;
use Macpaw\SymfonyOtelBundle\Registry\InstrumentationRegistry;
use Macpaw\SymfonyOtelBundle\Service\HttpMetadataAttacher;
use Macpaw\SymfonyOtelBundle\Service\TraceService;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;
use OpenTelemetry\SDK\Propagation\PropagatorFactory;
use OpenTelemetry\SDK\Trace\SpanDataInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Tests\Support\Telemetry\InMemoryProviderFactory;

final class GoldenTraceTest extends TestCase
{
    private InstrumentationRegistry $registry;
    private TextMapPropagatorInterface $propagator;
    private TraceService $traceService;
    private HttpMetadataAttacher $httpMetadataAttacher;

    public function test_request_root_span_and_attributes_and_parent_child(): void
    {
        $subscriber = new RequestRootSpanEventSubscriber(
            $this->registry,
            $this->propagator,
            $this->traceService,
            $this->httpMetadataAttacher,
            false,
            50,
        );

        $kernel = $this->createMock(HttpKernelInterface::class);

        // Build a request with route attributes and headers
        $request = Request::create('/api/test', 'GET');
        $request->attributes->set('_route', 'api_test');
        $request->headers->set('X-Request-Id', 'req-123');

        // Simulate Kernel REQUEST
        $requestEvent = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onKernelRequest($requestEvent);

        // Simulate Kernel TERMINATE
        $response = new Response('', 200);
        $terminateEvent = new TerminateEvent($kernel, $request, $response);
        $subscriber->onKernelTerminate($terminateEvent);

        // Fetch exported spans from in-memory exporter
        $exporter = InMemoryProviderFactory::getExporter();
        $this->assertNotNull($exporter, 'InMemory exporter should be available');
        $spans = $exporter->getSpans();

        // Expect at least the request root span and execution time span (from RequestExecutionTimeInstrumentation)
        $this->assertGreaterThanOrEqual(1, count($spans), 'At least one span should be exported');

        // Find request root span by name ("GET /path")
        /** @var SpanDataInterface|null $root */
        $root = null;
        foreach ($spans as $s) {
            if ($s instanceof SpanDataInterface && str_starts_with($s->getName(), 'GET ')) {
                $root = $s;
                break;
            }
        }
        $this->assertNotNull($root, 'Root request span should be exported');

        // Assert key attributes on root span
        $attrs = $root->getAttributes()->toArray();
        $this->assertSame('GET', $attrs['http.request.method'] ?? null);
        $this->assertSame('/api/test', $attrs['http.route'] ?? null);
        $this->assertSame(200, $attrs['http.response.status_code'] ?? null);

        // Request ID may be attached either to builder or via HttpMetadataAttacher
        $this->assertArrayHasKey('http.request_id', $attrs);

        // Verify parent/child by ensuring no parent for the root span (parentSpanId empty/zero)
        $this->assertTrue(
            $root->getParentSpanId() === '' || $root->getParentSpanId() === Span::getInvalidSpan()->getContext(
            )->getSpanId(),
        );
    }

    protected function setUp(): void
    {
        $this->registry = new InstrumentationRegistry();
        $this->propagator = (new PropagatorFactory())->create();
        $provider = InMemoryProviderFactory::create();
        $this->traceService = new TraceService($provider, 'symfony-otel-test', 'test-tracer');
        $this->httpMetadataAttacher = new HttpMetadataAttacher(
            new \Macpaw\SymfonyOtelBundle\Instrumentation\Utils\RouterUtils(
                new \Symfony\Component\HttpFoundation\RequestStack(),
            ), [
            'http.request_id' => 'X-Request-Id',
        ],
        );
    }
}

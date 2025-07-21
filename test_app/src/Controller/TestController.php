<?php

declare(strict_types=1);

namespace App\Controller;

use App\Command\DummyCommand;
use App\Handler\DummyHandler;
use Exception;
use Macpaw\SymfonyOtelBundle\Instrumentation\Utils\RouterUtils;
use Macpaw\SymfonyOtelBundle\Service\TraceService;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use PDO;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TestController
{
    public function __construct(private readonly TraceService $traceService)
    {
    }

    #[Route('/', name: 'homepage')]
    public function homepage(): Response
    {
        $html = '
        <html>
            <head>
                <title>Symfony OpenTelemetry Bundle Test</title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 40px; }
                    h1 { color: #333; }
                    .endpoint { background: #f5f5f5; padding: 10px; margin: 10px 0; border-radius: 5px; }
                    .endpoint a { color: #007bff; text-decoration: none; }
                    .endpoint a:hover { text-decoration: underline; }
                </style>
            </head>
            <body>
                <h1>🚀 Symfony OpenTelemetry Bundle Test</h1>
                <p>This is a test application for the Symfony OpenTelemetry bundle.</p>
                
                <h2>Available Endpoints:</h2>
                <div class="endpoint">
                    <strong>GET <a href="/api/test">/api/test</a></strong> - Simple API endpoint
                </div>
                <div class="endpoint">
                    <strong>GET <a href="/api/slow">/api/slow</a></strong> - Slow endpoint (simulates processing time)
                </div>
                <div class="endpoint">
                    <strong>GET <a href="/api/nested">/api/nested</a></strong> - Nested spans example
                </div>
                <div class="endpoint">
                    <strong>GET <a href="/api/pdo-test">/api/pdo-test</a></strong> - PDO query test (for testing ExampleHookInstrumentation)
                </div>
                <div class="endpoint">
                    <strong>GET <a href="/api/exception-test">/api/exception-test</a></strong> - Exception test (for testing auto-close spans functionality)
                </div>
                <div class="endpoint">
                    <strong>GET <a href="/api/cqrs-test">/api/cqrs-test</a></strong> - AAAAAAA 🤔
                </div>
                
                <h2>Trace Viewing:</h2>
                <div class="endpoint">
                    <strong><a href="http://localhost:3000" target="_blank">Grafana Dashboard</a></strong> - View traces (admin/admin)
                </div>
                
                <h2>Usage:</h2>
                <ol>
                    <li>Make requests to the endpoints above</li>
                    <li>Check Grafana at <a href="http://localhost:3000" target="_blank">http://localhost:3000</a></li>
                    <li>Navigate to Explore > Tempo to view traces</li>
                </ol>
            </body>
        </html>';

        return new Response($html);
    }

    #[Route('/api/test', name: 'api_test')]
    public function apiTest(RouterUtils $routerUtils): JsonResponse
    {
        // Simulate some work
        usleep(100000); // 100ms

        $data = [
            'message' => 'Hello from Symfony OpenTelemetry Bundle!',
            'timestamp' => time(),
        ];

        return new JsonResponse($data);
    }

    #[Route('/api/slow', name: 'api_slow')]
    public function apiSlow(): JsonResponse
    {
        $tracer = $this->traceService->getTracer('test-controller');

        $span = $tracer->spanBuilder('slow_operation')
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->startSpan();

        $scope = $span->activate();

        try {
            $span->addEvent('Starting slow operation');
            $span->setAttribute('operation.type', 'slow');

            sleep(2);

            $span->addEvent('Slow operation completed');

            return new JsonResponse([
                'message' => 'This was a slow operation',
                'duration' => '2 seconds',
                'trace_id' => $span->getContext()->getTraceId(),
            ]);
        } finally {
            $scope->detach();
            $span->end();
        }
    }

    #[Route('/api/nested', name: 'api_nested')]
    public function apiNested(): JsonResponse
    {
        $tracer = $this->traceService->getTracer('test-controller');

        $rootSpan = $tracer->spanBuilder('nested_operation_root')
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->startSpan();

        $rootScope = $rootSpan->activate();

        try {
            $rootSpan->addEvent('Starting nested operations');

            $childSpan1 = $tracer->spanBuilder('database_query_simulation')
                ->setSpanKind(SpanKind::KIND_CLIENT)
                ->startSpan();

            $childScope1 = $childSpan1->activate();
            try {
                $childSpan1->setAttribute('db.operation', 'SELECT');
                $childSpan1->setAttribute('db.table', 'users');
                usleep(300000); // 300ms
                $childSpan1->addEvent('Database query completed');
            } finally {
                $childScope1->detach();
                $childSpan1->end();
            }

            $childSpan2 = $tracer->spanBuilder('external_api_call_simulation')
                ->setSpanKind(SpanKind::KIND_CLIENT)
                ->startSpan();

            $childScope2 = $childSpan2->activate();
            try {
                $childSpan2->setAttribute('http.method', 'GET');
                $childSpan2->setAttribute('http.url', 'https://api.example.com/data');
                usleep(500000); // 500ms
                $childSpan2->addEvent('External API call completed');
            } finally {
                $childScope2->detach();
                $childSpan2->end();
            }

            $rootSpan->addEvent('All nested operations completed');

            return new JsonResponse([
                'message' => 'Nested operations completed',
                'operations' => ['database_query', 'external_api_call'],
                'trace_id' => $rootSpan->getContext()->getTraceId(),
            ]);
        } finally {
            $rootScope->detach();
            $rootSpan->end();
            $this->traceService->shutdown();
        }
    }

    #[Route('/api/pdo-test', name: 'api_pdo_test')]
    public function apiPdoTest(): JsonResponse
    {
        $pdo = new PDO('sqlite::memory:');

        $stmt = $pdo->query('SELECT 1 as test_value, "Hello from PDO" as message');
        if ($stmt === false) {
            throw new Exception('Failed to execute PDO query');
        }
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return new JsonResponse([
            'message' => 'PDO query test completed',
            'pdo_result' => $result,
            'note' => 'Check traces for ExampleHookInstrumentation spans',
        ]);
    }

    #[Route('/api/exception-test', name: 'api_exception_test')]
    public function apiExceptionTest(): JsonResponse
    {
        $tracer = $this->traceService->getTracer('test-controller');

        $span = $tracer->spanBuilder('exception_test_operation')
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->startSpan();

        $span->addEvent('Starting exception test operation');
        $span->setAttribute('operation.type', 'exception_test');
        $span->setAttribute('test.scenario', 'auto_close_spans');

        usleep(100000); // 100ms
        throw new Exception('Test exception for tracing');
    }

    #[Route('/api/cqrs-test', name: 'api_cqrs_exception_test')]
    public function apiCqrsTest(): JsonResponse
    {
        $cmd = new DummyCommand();
        $handler = new DummyHandler();

        $handler($cmd);

        $data = [
            'message' => 'Check CQRS execution',
            'timestamp' => time(),
        ];
        return new JsonResponse($data);
    }
}

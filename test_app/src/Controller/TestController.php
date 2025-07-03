<?php

declare(strict_types=1);

namespace App\Controller;

use Exception;
use Macpaw\SymfonyOtelBundle\Service\TraceService;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use Symfony\Component\HttpFoundation\JsonResponse;
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
                    <strong>GET <a href="/api/error">/api/error</a></strong> - Error endpoint (for testing error traces)
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
    public function apiTest(): JsonResponse
    {
        $tracer = $this->traceService->getTracer('test-controller');

        $span = $tracer->spanBuilder('api_test_operation')
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->startSpan();

        $scope = $span->activate();

        try {
            $span->addEvent('Processing API test request');
            $span->setAttribute('http.method', 'GET');
            $span->setAttribute('http.route', '/api/test');

            // Simulate some work
            usleep(100000); // 100ms

            $data = [
                'message' => 'Hello from Symfony OpenTelemetry Bundle!',
                'timestamp' => time(),
                'trace_id' => $span->getContext()->getTraceId(),
                'span_id' => $span->getContext()->getSpanId(),
            ];

            $span->addEvent('API test completed successfully');

            return new JsonResponse($data);
        } finally {
            $scope->detach();
            $span->end();
        }
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

            // Simulate slow processing
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

            // First nested operation
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

            // Second nested operation
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
        }
    }

    #[Route('/api/error', name: 'api_error')]
    public function apiError(): JsonResponse
    {
        $tracer = $this->traceService->getTracer('test-controller');

        $span = $tracer->spanBuilder('error_operation')
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->startSpan();

        $scope = $span->activate();

        try {
            $span->addEvent('Starting operation that will fail');
            $span->setAttribute('operation.type', 'error_simulation');

            // Simulate some work before error
            usleep(100000); // 100ms

            throw new Exception('This is a test error for tracing');
        } catch (Exception $e) {
            $span->recordException($e);
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());

            return new JsonResponse([
                'error' => true,
                'message' => $e->getMessage(),
                'trace_id' => $span->getContext()->getTraceId(),
            ], 500);
        } finally {
            $scope->detach();
            $span->end();
        }
    }
} 

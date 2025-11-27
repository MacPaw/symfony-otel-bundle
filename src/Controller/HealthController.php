<?php

declare(strict_types=1);

namespace Macpaw\SymfonyOtelBundle\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Simple health endpoint to verify tracing end-to-end.
 * This controller lives in the bundle so a Flex recipe can route to it without extra code.
 */
final class HealthController
{
    #[Route(path: '/_otel/health', name: 'otel_bundle_health', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        return new JsonResponse([
            'status' => 'ok',
            'service' => $_ENV['OTEL_SERVICE_NAME'] ?? 'unknown',
            'time' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ]);
    }
}

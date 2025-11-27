<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

final class OtelHealthController
{
    #[Route(path: '/_otel/health', name: 'otel_health', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        return new JsonResponse([
            'status' => 'ok',
            'time' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ]);
    }
}

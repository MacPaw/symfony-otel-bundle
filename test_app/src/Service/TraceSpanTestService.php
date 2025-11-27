<?php

declare(strict_types=1);

namespace App\Service;

use Macpaw\SymfonyOtelBundle\Attribute\TraceSpan;
use OpenTelemetry\API\Trace\SpanKind;

/**
 * Test service to demonstrate TraceSpan attribute usage.
 */
final class TraceSpanTestService
{
    #[TraceSpan('ProcessOrder', SpanKind::KIND_INTERNAL, [
        'operation.type' => 'order_processing',
        'service.name' => 'order_service',
    ])]
    public function processOrder(string $orderId): string
    {
        // Simulate some work
        usleep(50000); // 50ms
        return "Order {$orderId} processed";
    }

    #[TraceSpan('CalculatePrice', SpanKind::KIND_INTERNAL)]
    public function calculatePrice(float $amount, float $taxRate): float
    {
        // Simulate calculation
        usleep(30000); // 30ms
        return $amount * (1 + $taxRate);
    }

    #[TraceSpan('ValidatePayment', SpanKind::KIND_CLIENT, ['payment.method' => 'credit_card'])]
    public function validatePayment(string $paymentId): bool
    {
        // Simulate validation
        usleep(20000); // 20ms
        return true;
    }
}


<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Deterministic provider double for local service tests; it never performs IO.
 */
final class FakePathaoProvider implements PathaoProviderInterface
{
    private int $calls = 0;

    public function __construct(private readonly array $responses = [])
    {
    }

    public function createShipment(array $payload, string $idempotencyKey): array
    {
        $response = $this->responses[$this->calls] ?? [
            'success' => true,
            'retryable' => false,
            'httpStatus' => 200,
            'externalId' => 'FAKE-' . substr($idempotencyKey, 0, 12),
            'merchantOrderId' => $payload['merchant_order_id'] ?? null,
            'message' => 'Fake shipment created.',
        ];
        $this->calls++;
        return $response;
    }

    public function calls(): int
    {
        return $this->calls;
    }
}

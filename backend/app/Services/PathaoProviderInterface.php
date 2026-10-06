<?php

declare(strict_types=1);

namespace App\Services;

interface PathaoProviderInterface
{
    /**
     * @return array{success: bool, retryable: bool, httpStatus: ?int, externalId: ?string, merchantOrderId: ?string, message: string}
     */
    public function createShipment(array $payload, string $idempotencyKey): array;
}

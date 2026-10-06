<?php

declare(strict_types=1);

namespace App\Services;

use Nemesis\Http\Http;

/**
 * Server-only Pathao Developer API client.
 *
 * Account credentials and store/location identifiers come from the active
 * environment. The client never returns or logs access tokens or raw bodies.
 */
final class PathaoClient implements PathaoProviderInterface
{
    public function createShipment(array $payload, string $idempotencyKey): array
    {
        $baseUrl = rtrim((string) (getenv('PATHAO_BASE_URL') ?: 'https://courier-api.pathao.com'), '/');
        $tokenPath = '/' . ltrim((string) (getenv('PATHAO_TOKEN_PATH') ?: '/aladdin/api/v1/issue-token'), '/');
        $orderPath = '/' . ltrim((string) (getenv('PATHAO_ORDER_PATH') ?: '/aladdin/api/v1/orders'), '/');
        $clientId = trim((string) getenv('PATHAO_CLIENT_ID'));
        $clientSecret = trim((string) getenv('PATHAO_CLIENT_SECRET'));
        $username = trim((string) getenv('PATHAO_USERNAME'));
        $password = (string) getenv('PATHAO_PASSWORD');

        if ($clientId === '' || $clientSecret === '' || $username === '' || $password === '') {
            throw new PathaoProviderException('Pathao credentials are not configured.', false);
        }
        if ((int) ($payload['store_id'] ?? 0) < 1) {
            throw new PathaoProviderException('Pathao store is not configured.', false);
        }

        try {
            $tokenResponse = Http::asJson()
                ->acceptJson()
                ->timeout($this->timeout())
                ->post($baseUrl . $tokenPath, [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'grant_type' => 'password',
                    'username' => $username,
                    'password' => $password,
                ]);
        } catch (\Throwable $error) {
            throw new PathaoProviderException('Pathao authentication request failed.', true);
        }

        if (!$tokenResponse->successful()) {
            throw new PathaoProviderException(
                'Pathao authentication was rejected.',
                $this->isRetryable($tokenResponse->status()),
                $tokenResponse->status()
            );
        }

        $token = (string) ($tokenResponse->json('access_token') ?? '');
        if ($token === '') {
            throw new PathaoProviderException('Pathao authentication returned no access token.', false, $tokenResponse->status());
        }

        try {
            $response = Http::withToken($token)
                ->withHeaders(['Idempotency-Key' => $idempotencyKey])
                ->asJson()
                ->acceptJson()
                ->timeout($this->timeout())
                ->post($baseUrl . $orderPath, $payload);
        } catch (\Throwable $error) {
            throw new PathaoProviderException('Pathao shipment request failed.', true);
        }

        if (!$response->successful()) {
            throw new PathaoProviderException(
                $this->safeProviderMessage($response->status()),
                $this->isRetryable($response->status()),
                $response->status()
            );
        }

        $externalId = $response->json('data.consignment_id');
        if (!is_string($externalId) && !is_int($externalId)) {
            throw new PathaoProviderException('Pathao returned no consignment reference.', false, $response->status());
        }

        $merchantOrderId = $response->json('data.merchant_order_id');
        return [
            'success' => true,
            'retryable' => false,
            'httpStatus' => $response->status(),
            'externalId' => (string) $externalId,
            'merchantOrderId' => is_scalar($merchantOrderId) ? (string) $merchantOrderId : null,
            'message' => 'Pathao shipment created.',
        ];
    }

    private function timeout(): int
    {
        return max(3, min(30, (int) (getenv('PATHAO_TIMEOUT') ?: 10)));
    }

    private function isRetryable(int $status): bool
    {
        return $status === 408 || $status === 429 || $status >= 500;
    }

    private function safeProviderMessage(int $status): string
    {
        return $this->isRetryable($status)
            ? 'Pathao is temporarily unavailable.'
            : 'Pathao rejected the shipment request.';
    }
}

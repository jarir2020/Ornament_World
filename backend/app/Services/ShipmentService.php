<?php

declare(strict_types=1);

namespace App\Services;

use Nemesis\Core\Database;

/**
 * Courier orchestration boundary.
 *
 * The database idempotency row is claimed before provider I/O. A successful
 * shipment can therefore never be created twice by repeated admin clicks,
 * while failed attempts remain recoverable in the manual queue.
 */
final class ShipmentService
{
    public function __construct(private readonly PathaoProviderInterface $provider = new PathaoClient())
    {
    }

    public function createForOrder(string $reference, ?int $actorUserId = null): array
    {
        $prepared = $this->claimAttempt($reference);
        if ($prepared['state'] !== 'claimed') {
            return $this->detail($reference);
        }

        try {
            $result = $this->provider->createShipment($prepared['payload'], $prepared['idempotencyKey']);
        } catch (PathaoProviderException $error) {
            $result = [
                'success' => false,
                'retryable' => $error->retryable,
                'httpStatus' => $error->httpStatus,
                'externalId' => null,
                'merchantOrderId' => null,
                'message' => $error->getMessage(),
            ];
        } catch (\Throwable $error) {
            $result = [
                'success' => false,
                'retryable' => true,
                'httpStatus' => null,
                'externalId' => null,
                'merchantOrderId' => null,
                'message' => 'The shipment provider could not be reached.',
            ];
        }

        return $this->recordAttempt($prepared, $result, $actorUserId);
    }

    public function recordManual(string $reference, string $manualReference, string $note, ?int $actorUserId = null): array
    {
        $manualReference = trim($manualReference);
        $note = trim($note);
        if ($manualReference === '' || mb_strlen($manualReference) > 120) {
            throw new \InvalidArgumentException('A manual shipment reference is required.');
        }
        if ($note === '' || mb_strlen($note) > 500) {
            throw new \InvalidArgumentException('Add a short manual-shipment note.');
        }

        $db = Database::connection();
        $db->beginTransaction();
        try {
            $order = $this->lockedOrder($db, $reference);
            if ($order === null) {
                throw new \RuntimeException('Order not found.');
            }
            if (!in_array($order['status'], ['confirmed', 'processing', 'shipped'], true)) {
                throw new \InvalidArgumentException('Only a confirmed or operational order can receive a manual shipment reference.');
            }
            if ($order['review_status'] === 'blocked') {
                throw new \InvalidArgumentException('Blocked orders cannot receive a shipment reference.');
            }

            $shipment = $this->lockedShipment($db, (int) $order['id']);
            if ($shipment !== null && $shipment['status'] === 'created') {
                throw new \InvalidArgumentException('This order already has a successful Pathao shipment.');
            }
            if ($shipment === null) {
                $insert = $db->prepare(<<<'SQL'
INSERT INTO shipments (order_id, provider, idempotency_key, status, attempt_count)
VALUES (:order_id, 'pathao', :idempotency_key, 'manual_created', 0)
SQL);
                $insert->execute([
                    'order_id' => (int) $order['id'],
                    'idempotency_key' => $this->idempotencyKey($order),
                ]);
                $shipmentId = (int) $db->lastInsertId();
                $attemptNo = 0;
            } else {
                $shipmentId = (int) $shipment['id'];
                $attemptNo = (int) $shipment['attempt_count'];
            }

            $update = $db->prepare(<<<'SQL'
UPDATE shipments
SET status = 'manual_created', manual_reference = :manual_reference,
    manual_note = :manual_note, manual_recorded_at = NOW(), last_retryable = 0, last_error = NULL
WHERE id = :id
SQL);
            $update->execute([
                'manual_reference' => $manualReference,
                'manual_note' => $note,
                'id' => $shipmentId,
            ]);
            $db->prepare('UPDATE orders SET shipment_status = :shipment_status WHERE id = :order_id')->execute([
                'shipment_status' => 'manual_created',
                'order_id' => (int) $order['id'],
            ]);
            $attempt = $db->prepare(<<<'SQL'
INSERT INTO shipment_attempts (shipment_id, attempt_no, outcome, external_id, sanitized_error, actor_user_id)
VALUES (:shipment_id, :attempt_no, 'manual', :external_id, :sanitized_error, :actor_user_id)
SQL);
            $attempt->execute([
                'shipment_id' => $shipmentId,
                'attempt_no' => $attemptNo + 1,
                'external_id' => $manualReference,
                'sanitized_error' => $note,
                'actor_user_id' => $actorUserId,
            ]);
            $db->commit();
        } catch (\Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }

        return $this->detail($reference);
    }

    public function detail(string $reference): array
    {
        $orders = Database::view(<<<'SQL'
SELECT id, reference, status, shipment_status, review_status, customer_name, customer_phone,
       customer_email, delivery_address, district, subdistrict, postoffice, postcode, total,
       is_suspicious
FROM orders
WHERE reference = :reference
LIMIT 1
SQL, ['reference' => $reference]);
        if ($orders === []) {
            throw new \RuntimeException('Order not found.');
        }

        $order = $orders[0];
        $shipmentRows = Database::view(<<<'SQL'
SELECT id, provider, idempotency_key, status, external_id, merchant_order_id,
       attempt_count, last_http_status, last_retryable, last_error, manual_reference, manual_note,
       manual_recorded_at, created_at, updated_at
FROM shipments
WHERE order_id = :order_id
LIMIT 1
SQL, ['order_id' => (int) $order['id']]);
        $shipment = $shipmentRows[0] ?? null;
        if ($shipment !== null) {
            $attemptRows = Database::view(<<<'SQL'
SELECT attempt_no, outcome, http_status, external_id, sanitized_error, created_at
FROM shipment_attempts
WHERE shipment_id = :shipment_id
ORDER BY id DESC
LIMIT 20
SQL, ['shipment_id' => (int) $shipment['id']]);
            $shipment = [
                'id' => (int) $shipment['id'],
                'provider' => $shipment['provider'],
                'status' => $shipment['status'],
                'externalId' => $shipment['external_id'],
                'merchantOrderId' => $shipment['merchant_order_id'],
                'attemptCount' => (int) $shipment['attempt_count'],
                'lastHttpStatus' => $shipment['last_http_status'] !== null ? (int) $shipment['last_http_status'] : null,
                'lastRetryable' => (bool) $shipment['last_retryable'],
                'lastError' => $shipment['last_error'],
                'manualReference' => $shipment['manual_reference'],
                'manualNote' => $shipment['manual_note'],
                'manualRecordedAt' => $shipment['manual_recorded_at'],
                'updatedAt' => $shipment['updated_at'],
                'attempts' => array_map(static fn (array $attempt): array => [
                    'attemptNo' => (int) $attempt['attempt_no'],
                    'outcome' => $attempt['outcome'],
                    'httpStatus' => $attempt['http_status'] !== null ? (int) $attempt['http_status'] : null,
                    'externalId' => $attempt['external_id'],
                    'error' => $attempt['sanitized_error'],
                    'createdAt' => $attempt['created_at'],
                ], $attemptRows),
            ];
        }

        $canSend = $order['status'] === 'confirmed'
            && ((int) $order['is_suspicious'] === 0 || $order['review_status'] === 'reviewed')
            && $order['review_status'] !== 'blocked';
        $canRetry = $canSend
            && $shipment !== null
            && $shipment['status'] === 'manual_required'
            && $shipment['lastRetryable']
            && $shipment['attemptCount'] < $this->maxAttempts();

        return [
            'shipment' => $shipment,
            'eligible' => $canSend && $shipment === null,
            'canRetry' => $canRetry,
            'orderStatus' => $order['status'],
            'orderShipmentStatus' => $order['shipment_status'],
        ];
    }

    /** @return array{state: string, shipmentId: int|null, attemptNo: int, idempotencyKey: string, payload: array} */
    private function claimAttempt(string $reference): array
    {
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $order = $this->lockedOrder($db, $reference);
            if ($order === null) {
                throw new \RuntimeException('Order not found.');
            }
            if ($order['status'] !== 'confirmed') {
                throw new \InvalidArgumentException('Only confirmed orders can be sent to Pathao.');
            }
            if ((int) $order['is_suspicious'] === 1 && $order['review_status'] !== 'reviewed') {
                throw new \InvalidArgumentException('Review the suspicious-order flag before creating a shipment.');
            }
            if ($order['review_status'] === 'blocked') {
                throw new \InvalidArgumentException('Blocked orders cannot be sent to Pathao.');
            }

            $shipment = $this->lockedShipment($db, (int) $order['id']);
            if ($shipment !== null && $shipment['status'] === 'created') {
                $db->commit();
                return ['state' => 'created', 'shipmentId' => (int) $shipment['id'], 'attemptNo' => (int) $shipment['attempt_count'], 'idempotencyKey' => $shipment['idempotency_key'], 'payload' => []];
            }
            if ($shipment !== null && $shipment['status'] === 'manual_created') {
                $db->commit();
                return ['state' => 'manual_created', 'shipmentId' => (int) $shipment['id'], 'attemptNo' => (int) $shipment['attempt_count'], 'idempotencyKey' => $shipment['idempotency_key'], 'payload' => []];
            }
            if ($shipment !== null && $shipment['status'] === 'pending' && !$this->pendingClaimExpired($shipment['updated_at'])) {
                $db->commit();
                return ['state' => 'pending', 'shipmentId' => (int) $shipment['id'], 'attemptNo' => (int) $shipment['attempt_count'], 'idempotencyKey' => $shipment['idempotency_key'], 'payload' => []];
            }

            if ($shipment !== null && $shipment['status'] === 'manual_required' && !(bool) $shipment['last_retryable']) {
                $db->commit();
                return ['state' => 'manual_required', 'shipmentId' => (int) $shipment['id'], 'attemptNo' => (int) $shipment['attempt_count'], 'idempotencyKey' => $shipment['idempotency_key'], 'payload' => []];
            }

            $attemptNo = ($shipment !== null ? (int) $shipment['attempt_count'] : 0) + 1;
            if ($attemptNo > $this->maxAttempts()) {
                if ($shipment !== null) {
                    $db->prepare("UPDATE shipments SET status = 'manual_required', last_error = :last_error WHERE id = :id")->execute([
                        'last_error' => 'Maximum Pathao attempts reached; manual shipment is required.',
                        'id' => (int) $shipment['id'],
                    ]);
                    $db->prepare("UPDATE orders SET shipment_status = 'manual_required' WHERE id = :order_id")->execute(['order_id' => (int) $order['id']]);
                }
                $db->commit();
                return ['state' => 'manual_required', 'shipmentId' => $shipment !== null ? (int) $shipment['id'] : null, 'attemptNo' => $attemptNo - 1, 'idempotencyKey' => $shipment['idempotency_key'] ?? $this->idempotencyKey($order), 'payload' => []];
            }

            $idempotencyKey = $shipment['idempotency_key'] ?? $this->idempotencyKey($order);
            if ($shipment === null) {
                $insert = $db->prepare(<<<'SQL'
INSERT INTO shipments (order_id, provider, idempotency_key, status, attempt_count)
VALUES (:order_id, 'pathao', :idempotency_key, 'pending', :attempt_count)
SQL);
                $insert->execute([
                    'order_id' => (int) $order['id'],
                    'idempotency_key' => $idempotencyKey,
                    'attempt_count' => $attemptNo,
                ]);
                $shipmentId = (int) $db->lastInsertId();
            } else {
                $shipmentId = (int) $shipment['id'];
                $db->prepare("UPDATE shipments SET status = 'pending', attempt_count = :attempt_count, last_error = NULL WHERE id = :id")->execute([
                    'attempt_count' => $attemptNo,
                    'id' => $shipmentId,
                ]);
            }
            $db->prepare("UPDATE orders SET shipment_status = 'pending' WHERE id = :order_id")->execute(['order_id' => (int) $order['id']]);
            $payload = $this->payload($order, $this->items($db, (int) $order['id']));
            $db->commit();
        } catch (\Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }

        return ['state' => 'claimed', 'shipmentId' => $shipmentId, 'attemptNo' => $attemptNo, 'idempotencyKey' => $idempotencyKey, 'payload' => $payload];
    }

    private function recordAttempt(array $prepared, array $result, ?int $actorUserId): array
    {
        $db = Database::connection();
        $db->beginTransaction();
        try {
            $success = (bool) ($result['success'] ?? false);
            $status = $success ? 'created' : 'manual_required';
            $shipmentStatus = $success ? 'created' : 'manual_required';
            $update = $db->prepare(<<<'SQL'
UPDATE shipments
SET status = :status, external_id = :external_id, merchant_order_id = :merchant_order_id,
    last_http_status = :last_http_status, last_retryable = :last_retryable, last_error = :last_error
WHERE id = :id
SQL);
            $update->execute([
                'status' => $status,
                'external_id' => $result['externalId'] ?? null,
                'merchant_order_id' => $result['merchantOrderId'] ?? null,
                'last_http_status' => $result['httpStatus'] ?? null,
                'last_retryable' => $success ? 0 : ((bool) ($result['retryable'] ?? false) ? 1 : 0),
                'last_error' => $success ? null : mb_substr((string) ($result['message'] ?? 'Provider failure.'), 0, 500),
                'id' => (int) $prepared['shipmentId'],
            ]);
            $db->prepare('UPDATE orders SET shipment_status = :shipment_status WHERE id = (SELECT order_id FROM shipments WHERE id = :shipment_id)')->execute([
                'shipment_status' => $shipmentStatus,
                'shipment_id' => (int) $prepared['shipmentId'],
            ]);
            $attempt = $db->prepare(<<<'SQL'
INSERT INTO shipment_attempts (shipment_id, attempt_no, outcome, http_status, external_id, sanitized_error, actor_user_id)
VALUES (:shipment_id, :attempt_no, :outcome, :http_status, :external_id, :sanitized_error, :actor_user_id)
SQL);
            $attempt->execute([
                'shipment_id' => (int) $prepared['shipmentId'],
                'attempt_no' => (int) $prepared['attemptNo'],
                'outcome' => $success ? 'success' : 'failure',
                'http_status' => $result['httpStatus'] ?? null,
                'external_id' => $result['externalId'] ?? null,
                'sanitized_error' => $success ? null : mb_substr((string) ($result['message'] ?? 'Provider failure.'), 0, 500),
                'actor_user_id' => $actorUserId,
            ]);
            $db->commit();
        } catch (\Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }

        return $this->detailByShipment((int) $prepared['shipmentId']);
    }

    private function detailByShipment(int $shipmentId): array
    {
        $rows = Database::view('SELECT o.reference FROM shipments s INNER JOIN orders o ON o.id = s.order_id WHERE s.id = :id LIMIT 1', ['id' => $shipmentId]);
        if ($rows === []) {
            throw new \RuntimeException('Shipment not found.');
        }
        return $this->detail((string) $rows[0]['reference']);
    }

    private function lockedOrder(\PDO $db, string $reference): ?array
    {
        $statement = $db->prepare('SELECT * FROM orders WHERE reference = :reference LIMIT 1 FOR UPDATE');
        $statement->execute(['reference' => $reference]);
        $order = $statement->fetch(\PDO::FETCH_ASSOC);
        return $order ?: null;
    }

    private function lockedShipment(\PDO $db, int $orderId): ?array
    {
        $statement = $db->prepare('SELECT * FROM shipments WHERE order_id = :order_id LIMIT 1 FOR UPDATE');
        $statement->execute(['order_id' => $orderId]);
        $shipment = $statement->fetch(\PDO::FETCH_ASSOC);
        return $shipment ?: null;
    }

    private function items(\PDO $db, int $orderId): array
    {
        $statement = $db->prepare('SELECT product_name, variant_name, sku, quantity, line_total FROM order_items WHERE order_id = :order_id ORDER BY id ASC');
        $statement->execute(['order_id' => $orderId]);
        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function payload(array $order, array $items): array
    {
        $storeId = (int) (getenv('PATHAO_STORE_ID') ?: 0);
        $quantity = array_sum(array_map(static fn (array $item): int => (int) $item['quantity'], $items));
        $description = implode('; ', array_map(static fn (array $item): string => sprintf('%s x%d', $item['product_name'], (int) $item['quantity']), $items));
        $address = implode(', ', array_filter([
            $order['delivery_address'],
            $order['postoffice'],
            $order['subdistrict'],
            $order['district'],
            $order['postcode'],
            'Bangladesh',
        ]));

        return [
            'store_id' => $storeId,
            'merchant_order_id' => $order['reference'],
            'recipient_name' => $order['customer_name'],
            'recipient_phone' => $order['customer_phone'],
            'recipient_address' => $address,
            'delivery_type' => (int) (getenv('PATHAO_DELIVERY_TYPE') ?: 48),
            'item_type' => (int) (getenv('PATHAO_ITEM_TYPE') ?: 2),
            'special_instruction' => 'Please call the customer before delivery.',
            'item_quantity' => max(1, $quantity),
            'item_weight' => (float) (getenv('PATHAO_ITEM_WEIGHT') ?: 0.5),
            'item_description' => mb_substr($description, 0, 500),
            'amount_to_collect' => max(0, (int) round((float) $order['total'])),
        ];
    }

    private function idempotencyKey(array $order): string
    {
        return hash('sha256', 'pathao|order|' . (int) $order['id'] . '|' . $order['reference']);
    }

    private function maxAttempts(): int
    {
        return max(1, min(5, (int) (getenv('PATHAO_MAX_ATTEMPTS') ?: 3)));
    }

    private function pendingClaimExpired(?string $updatedAt): bool
    {
        if ($updatedAt === null || $updatedAt === '') {
            return true;
        }

        $timeout = max(30, min(3600, (int) (getenv('PATHAO_PENDING_TIMEOUT') ?: 300)));
        $timestamp = strtotime($updatedAt);
        return $timestamp === false || $timestamp <= (time() - $timeout);
    }
}

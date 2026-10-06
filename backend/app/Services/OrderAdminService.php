<?php

declare(strict_types=1);

namespace App\Services;

use Nemesis\Core\Database;

/**
 * Protected order operations for staff workflows.
 *
 * Every status change and inventory release is performed in one transaction;
 * the order status and the separate shipment status never get conflated.
 */
final class OrderAdminService
{
    private const STATUSES = [
        'new_order',
        'pending_confirmation',
        'confirmed',
        'processing',
        'shipped',
        'delivered',
        'cancelled',
        'rejected_suspicious',
    ];

    private const TRANSITIONS = [
        'new_order' => ['pending_confirmation', 'confirmed', 'cancelled', 'rejected_suspicious'],
        'pending_confirmation' => ['confirmed', 'cancelled', 'rejected_suspicious'],
        'confirmed' => ['processing', 'cancelled'],
        'processing' => ['shipped', 'cancelled'],
        'shipped' => ['delivered'],
        'delivered' => [],
        'cancelled' => [],
        'rejected_suspicious' => [],
    ];

    public function dashboard(string $search = '', string $status = ''): array
    {
        $counts = array_fill_keys(self::STATUSES, 0);
        foreach (Database::view('SELECT status, COUNT(*) AS total FROM orders GROUP BY status') as $row) {
            if (array_key_exists($row['status'], $counts)) {
                $counts[$row['status']] = (int) $row['total'];
            }
        }

        $where = [];
        $params = [];
        if ($status !== '' && in_array($status, self::STATUSES, true)) {
            $where[] = 'o.status = :status';
            $params['status'] = $status;
        }
        if ($search !== '') {
            $where[] = '(o.reference LIKE :search_reference OR o.customer_name LIKE :search_name OR o.customer_phone LIKE :search_phone OR o.district LIKE :search_district)';
            $params['search_reference'] = '%' . $search . '%';
            $params['search_name'] = '%' . $search . '%';
            $params['search_phone'] = '%' . $search . '%';
            $params['search_district'] = '%' . $search . '%';
        }

        $query = <<<SQL
SELECT
    o.id,
    o.reference,
    o.status,
    o.shipment_status,
    o.review_status,
    o.customer_name,
    o.customer_phone,
    o.district,
    o.subdistrict,
    o.subtotal,
    o.discount_total,
    o.delivery_charge,
    o.total,
    o.risk_score,
    o.is_suspicious,
    o.created_at,
    (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count,
    (SELECT COUNT(*) FROM fraud_flags ff WHERE ff.order_id = o.id AND ff.resolution = 'pending') AS pending_flag_count
FROM orders o
SQL;
        if ($where !== []) {
            $query .= 'WHERE ' . implode(' AND ', $where) . "\n";
        }
        $query .= "\nORDER BY o.created_at DESC, o.id DESC LIMIT 100";

        $orders = array_map(static fn (array $order): array => self::presentOrderRow($order), Database::view($query, $params));
        $shipmentQueue = array_map(static fn (array $order): array => self::presentOrderRow($order), Database::view(<<<'SQL'
SELECT
    o.id,
    o.reference,
    o.status,
    o.shipment_status,
    o.review_status,
    o.customer_name,
    o.customer_phone,
    o.district,
    o.subdistrict,
    o.subtotal,
    o.discount_total,
    o.delivery_charge,
    o.total,
    o.risk_score,
    o.is_suspicious,
    o.created_at,
    (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count,
    (SELECT COUNT(*) FROM fraud_flags ff WHERE ff.order_id = o.id AND ff.resolution = 'pending') AS pending_flag_count
FROM orders o
INNER JOIN shipments s ON s.order_id = o.id
WHERE s.status = 'manual_required'
  AND o.status IN ('confirmed', 'processing', 'shipped')
ORDER BY s.updated_at ASC, o.id ASC
LIMIT 50
SQL));

        return [
            'counts' => $counts,
            'total' => array_sum($counts),
            'pendingFraudFlags' => (int) (Database::view("SELECT COUNT(*) AS total FROM fraud_flags WHERE resolution = 'pending'")[0]['total'] ?? 0),
            'shipmentQueue' => $shipmentQueue,
            'pendingShipmentCount' => count($shipmentQueue),
            'orders' => $orders,
        ];
    }

    public function detail(string $reference): ?array
    {
        $orders = Database::view('SELECT * FROM orders WHERE reference = :reference LIMIT 1', ['reference' => $reference]);
        if ($orders === []) {
            return null;
        }

        $order = self::presentOrderRow($orders[0]);
        $orderId = (int) $orders[0]['id'];
        $order['email'] = $orders[0]['customer_email'];
        $order['address'] = $orders[0]['delivery_address'];
        $order['postoffice'] = $orders[0]['postoffice'];
        $order['postcode'] = $orders[0]['postcode'];
        $order['confirmedAt'] = $orders[0]['confirmed_at'];
        $order['cancelledAt'] = $orders[0]['cancelled_at'];
        $order['cancellationReason'] = $orders[0]['cancellation_reason'];
        $order['items'] = array_map(static fn (array $item): array => [
            'id' => (int) $item['id'],
            'productId' => $item['product_id'] !== null ? (int) $item['product_id'] : null,
            'variantId' => $item['variant_id'] !== null ? (int) $item['variant_id'] : null,
            'productName' => $item['product_name'],
            'variantName' => $item['variant_name'],
            'sku' => $item['sku'],
            'quantity' => (int) $item['quantity'],
            'unitPrice' => (float) $item['unit_price'],
            'lineSubtotal' => (float) $item['line_subtotal'],
            'lineDiscount' => (float) $item['line_discount'],
            'lineTotal' => (float) $item['line_total'],
            'stockReleasedAt' => $item['stock_released_at'],
        ], Database::view('SELECT * FROM order_items WHERE order_id = :order_id ORDER BY id ASC', ['order_id' => $orderId]));
        $order['history'] = array_map(static fn (array $entry): array => [
            'fromStatus' => $entry['from_status'],
            'toStatus' => $entry['to_status'],
            'note' => $entry['note'],
            'actorUserId' => $entry['actor_user_id'] !== null ? (int) $entry['actor_user_id'] : null,
            'createdAt' => $entry['created_at'],
        ], Database::view('SELECT from_status, to_status, note, actor_user_id, created_at FROM order_status_history WHERE order_id = :order_id ORDER BY id DESC', ['order_id' => $orderId]));
        $order['fraudFlags'] = array_map(static fn (array $flag): array => [
            'id' => (int) $flag['id'],
            'code' => $flag['code'],
            'reason' => $flag['reason'],
            'riskScore' => (int) $flag['risk_score'],
            'resolution' => $flag['resolution'],
            'reviewNote' => $flag['review_note'],
            'createdAt' => $flag['created_at'],
        ], Database::view('SELECT id, code, reason, risk_score, resolution, review_note, created_at FROM fraud_flags WHERE order_id = :order_id ORDER BY id ASC', ['order_id' => $orderId]));
        $order['previousOrders'] = array_map(static fn (array $previous): array => [
            'reference' => $previous['reference'],
            'status' => $previous['status'],
            'total' => (float) $previous['total'],
            'createdAt' => $previous['created_at'],
        ], Database::view(<<<'SQL'
SELECT reference, status, total, created_at
FROM orders
WHERE customer_phone = :phone AND id <> :order_id
ORDER BY created_at DESC, id DESC
LIMIT 20
SQL, ['phone' => $orders[0]['customer_phone'], 'order_id' => $orderId]));
        $order['allowedTransitions'] = self::TRANSITIONS[$orders[0]['status']] ?? [];

        return $order;
    }

    public function transition(string $reference, string $toStatus, string $note = '', ?int $actorUserId = null): array
    {
        if (!in_array($toStatus, self::STATUSES, true)) {
            throw new \InvalidArgumentException('That order status is not supported.');
        }

        $db = Database::connection();
        $db->beginTransaction();
        try {
            $statement = $db->prepare('SELECT * FROM orders WHERE reference = :reference LIMIT 1 FOR UPDATE');
            $statement->execute(['reference' => $reference]);
            $order = $statement->fetch(\PDO::FETCH_ASSOC);
            if (!$order) {
                throw new \RuntimeException('Order not found.');
            }

            $fromStatus = (string) $order['status'];
            if (!in_array($toStatus, self::TRANSITIONS[$fromStatus] ?? [], true)) {
                throw new \InvalidArgumentException(sprintf('An order cannot move from %s to %s.', $fromStatus, $toStatus));
            }
            $note = trim($note);
            if (in_array($toStatus, ['confirmed', 'cancelled', 'rejected_suspicious'], true) && $note === '') {
                throw new \InvalidArgumentException('Add a note for confirmation, cancellation, or rejection.');
            }

            $updateSql = 'UPDATE orders SET status = :status';
            $params = ['status' => $toStatus, 'reference' => $reference];
            if ($toStatus === 'confirmed') {
                $updateSql .= ', confirmed_at = NOW()';
            }
            if (in_array($toStatus, ['cancelled', 'rejected_suspicious'], true)) {
                $updateSql .= ', cancelled_at = NOW(), cancellation_reason = :reason';
                $params['reason'] = $note;
            }
            $updateSql .= ' WHERE reference = :reference';
            $db->prepare($updateSql)->execute($params);

            if (in_array($toStatus, ['cancelled', 'rejected_suspicious'], true)) {
                $this->releaseReservedStock($db, (int) $order['id'], $toStatus);
            }

            $history = $db->prepare(<<<'SQL'
INSERT INTO order_status_history (order_id, from_status, to_status, note, actor_user_id)
VALUES (:order_id, :from_status, :to_status, :note, :actor_user_id)
SQL);
            $history->execute([
                'order_id' => (int) $order['id'],
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'note' => $note !== '' ? $note : null,
                'actor_user_id' => $actorUserId,
            ]);
            $db->commit();
        } catch (\Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }

        return $this->detail($reference) ?? throw new \RuntimeException('Order not found.');
    }

    public function addNote(string $reference, string $note, ?int $actorUserId = null): array
    {
        $note = trim($note);
        if ($note === '' || mb_strlen($note) > 500) {
            throw new \InvalidArgumentException('A note between 1 and 500 characters is required.');
        }

        $order = Database::view('SELECT id, status FROM orders WHERE reference = :reference LIMIT 1', ['reference' => $reference]);
        if ($order === []) {
            throw new \RuntimeException('Order not found.');
        }
        $statement = Database::connection()->prepare(<<<'SQL'
INSERT INTO order_status_history (order_id, from_status, to_status, note, actor_user_id)
VALUES (:order_id, :status, :status, :note, :actor_user_id)
SQL);
        $statement->execute([
            'order_id' => (int) $order[0]['id'],
            'status' => $order[0]['status'],
            'note' => $note,
            'actor_user_id' => $actorUserId,
        ]);

        return $this->detail($reference) ?? throw new \RuntimeException('Order not found.');
    }

    public function reviewFlag(int $flagId, string $resolution, string $note = '', ?int $actorUserId = null): array
    {
        if (!in_array($resolution, ['confirmed', 'dismissed', 'blocked'], true)) {
            throw new \InvalidArgumentException('Choose a valid fraud-review outcome.');
        }
        $flag = Database::view('SELECT id, order_id FROM fraud_flags WHERE id = :id LIMIT 1', ['id' => $flagId]);
        if ($flag === []) {
            throw new \RuntimeException('Fraud flag not found.');
        }
        $note = trim($note);
        $statement = Database::connection()->prepare(<<<'SQL'
UPDATE fraud_flags
SET resolution = :resolution, review_note = :review_note, reviewed_by = :reviewed_by, reviewed_at = NOW()
WHERE id = :id
SQL);
        $statement->execute([
            'resolution' => $resolution,
            'review_note' => $note !== '' ? $note : null,
            'reviewed_by' => $actorUserId,
            'id' => $flagId,
        ]);

        $reviewStatus = $resolution === 'blocked' ? 'blocked' : 'reviewed';
        Database::connection()->prepare('UPDATE orders SET review_status = :review_status WHERE id = :order_id')->execute([
            'review_status' => $reviewStatus,
            'order_id' => (int) $flag[0]['order_id'],
        ]);

        $reference = Database::view('SELECT reference FROM orders WHERE id = :id LIMIT 1', ['id' => (int) $flag[0]['order_id']])[0]['reference'] ?? '';
        return $this->detail((string) $reference) ?? throw new \RuntimeException('Order not found.');
    }

    private function releaseReservedStock(\PDO $db, int $orderId, string $reason): void
    {
        $items = $db->prepare('SELECT product_id, variant_id, quantity FROM order_items WHERE order_id = :order_id AND stock_released_at IS NULL FOR UPDATE');
        $items->execute(['order_id' => $orderId]);
        $rows = $items->fetchAll(\PDO::FETCH_ASSOC);
        $restoreVariant = $db->prepare('UPDATE product_variants SET stock_qty = stock_qty + :quantity WHERE id = :id');
        $restoreProduct = $db->prepare('UPDATE products SET stock_qty = stock_qty + :quantity WHERE id = :id');
        $markReleased = $db->prepare('UPDATE order_items SET stock_released_at = NOW(), stock_release_reason = :reason WHERE order_id = :order_id AND variant_id = :variant_id AND stock_released_at IS NULL');

        foreach ($rows as $row) {
            $quantity = (int) $row['quantity'];
            if ($row['variant_id'] !== null) {
                $restoreVariant->execute(['quantity' => $quantity, 'id' => (int) $row['variant_id']]);
            }
            if ($row['product_id'] !== null) {
                $restoreProduct->execute(['quantity' => $quantity, 'id' => (int) $row['product_id']]);
            }
            $markReleased->execute([
                'reason' => $reason,
                'order_id' => $orderId,
                'variant_id' => (int) $row['variant_id'],
            ]);
        }
    }

    private static function presentOrderRow(array $order): array
    {
        return [
            'id' => (int) $order['id'],
            'reference' => $order['reference'],
            'status' => $order['status'],
            'shipmentStatus' => $order['shipment_status'],
            'reviewStatus' => $order['review_status'],
            'customerName' => $order['customer_name'],
            'customerPhone' => $order['customer_phone'],
            'district' => $order['district'],
            'subdistrict' => $order['subdistrict'],
            'subtotal' => (float) $order['subtotal'],
            'discountTotal' => (float) $order['discount_total'],
            'deliveryCharge' => (float) $order['delivery_charge'],
            'total' => (float) $order['total'],
            'riskScore' => (int) $order['risk_score'],
            'isSuspicious' => (bool) $order['is_suspicious'],
            'itemCount' => (int) ($order['item_count'] ?? 0),
            'pendingFlagCount' => (int) ($order['pending_flag_count'] ?? 0),
            'createdAt' => $order['created_at'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use Nemesis\Core\Database;
use Nemesis\Http\Session;

/**
 * Guest checkout domain service.
 *
 * Browser-submitted prices, stock, district labels, and delivery totals are
 * treated as hints only. The order transaction reads the current catalog and
 * delivery records again before it writes anything.
 */
final class CheckoutService
{
    public function options(): array
    {
        $rows = Database::view(<<<'SQL'
SELECT district, subdistrict, postoffice, postcode
FROM bangladesh_locations
ORDER BY district ASC, subdistrict ASC, postoffice ASC
SQL);

        $grouped = [];
        foreach ($rows as $row) {
            $district = (string) $row['district'];
            $subdistrict = (string) $row['subdistrict'];
            $grouped[$district] ??= [
                'name' => $district,
                'subdistricts' => [],
            ];
            $grouped[$district]['subdistricts'][$subdistrict] ??= [
                'name' => $subdistrict,
                'postoffices' => [],
            ];
            $grouped[$district]['subdistricts'][$subdistrict]['postoffices'][] = [
                'name' => (string) $row['postoffice'],
                'postcode' => (string) $row['postcode'],
            ];
        }

        $locations = array_values(array_map(static function (array $district): array {
            $district['subdistricts'] = array_values($district['subdistricts']);
            return $district;
        }, $grouped));

        $deliveryRules = array_map(static fn (array $rule): array => [
            'slug' => $rule['slug'],
            'name' => $rule['name'],
            'charge' => (float) $rule['charge'],
            'districts' => $rule['districts'] !== null ? explode(',', (string) $rule['districts']) : [],
        ], Database::view(<<<'SQL'
SELECT z.slug, z.name, z.charge,
       GROUP_CONCAT(zd.district ORDER BY zd.district SEPARATOR ',') AS districts
FROM delivery_zones z
LEFT JOIN delivery_zone_districts zd ON zd.zone_id = z.id
WHERE z.is_active = 1
GROUP BY z.id, z.slug, z.name, z.charge
ORDER BY z.id ASC
SQL));

        return [
            'locations' => $locations,
            'deliveryRules' => $deliveryRules,
        ];
    }

    public function createOrder(array $input): array
    {
        $idempotencyKey = $this->validatedIdempotencyKey($input['idempotency_key'] ?? $input['idempotencyKey'] ?? null);
        $customerId = $this->sessionCustomerId();
        if ($idempotencyKey !== null) {
            $existing = Database::view(
                'SELECT reference FROM orders WHERE idempotency_key = :idempotency_key LIMIT 1',
                ['idempotency_key' => $idempotencyKey]
            );
            if ($existing !== []) {
                return $this->success((string) $existing[0]['reference']) ?? throw new \RuntimeException('Existing idempotent order could not be loaded.');
            }
        }

        $customer = $this->validatedCustomer($input);
        $items = $this->validatedItems($input['items'] ?? null);
        $location = $this->validatedLocation($customer['district'], $customer['subdistrict'], $customer['postoffice']);
        $db = Database::connection();

        $zone = Database::view(<<<'SQL'
SELECT z.slug, z.charge
FROM delivery_zones z
INNER JOIN delivery_zone_districts zd ON zd.zone_id = z.id
WHERE zd.district = :district AND z.is_active = 1
LIMIT 1
SQL, ['district' => $location['district']]);

        if ($zone === []) {
            throw new \InvalidArgumentException('Delivery is not configured for the selected district.');
        }

        $db->beginTransaction();
        try {
            $lineItems = [];
            $subtotal = 0.0;
            $discountTotal = 0.0;

            foreach ($items as $item) {
                $catalogItem = $this->lockedCatalogItem($item['product_id'], $item['variant_id']);
                if ($catalogItem === null) {
                    throw new \InvalidArgumentException('One of the selected pieces is no longer available.');
                }

                $availableStock = (int) $catalogItem['stock_qty'];
                if ($availableStock < $item['quantity']) {
                    throw new \InvalidArgumentException(sprintf(
                        '%s has only %d available.',
                        $catalogItem['product_name'],
                        $availableStock
                    ));
                }

                $unitPrice = (float) $catalogItem['price'];
                $unitDiscount = $this->discountFor(
                    (int) $catalogItem['product_id'],
                    (int) $catalogItem['variant_id'],
                    $unitPrice
                );
                $lineSubtotal = round($unitPrice * $item['quantity'], 2);
                $lineDiscount = round($unitDiscount * $item['quantity'], 2);
                $lineTotal = round($lineSubtotal - $lineDiscount, 2);

                $lineItems[] = [
                    'product_id' => (int) $catalogItem['product_id'],
                    'variant_id' => (int) $catalogItem['variant_id'],
                    'product_name' => (string) $catalogItem['product_name'],
                    'variant_name' => (string) $catalogItem['variant_name'],
                    'sku' => (string) $catalogItem['sku'],
                    'quantity' => $item['quantity'],
                    'unit_price' => round($unitPrice - $unitDiscount, 2),
                    'line_subtotal' => $lineSubtotal,
                    'line_discount' => $lineDiscount,
                    'line_total' => $lineTotal,
                ];
                $subtotal += $lineSubtotal;
                $discountTotal += $lineDiscount;
            }

            $deliveryCharge = (float) $zone[0]['charge'];
            $total = round($subtotal - $discountTotal + $deliveryCharge, 2);
            $addressHash = hash('sha256', $this->normalizeAddress($customer['address'], $location));
            $risk = $this->riskSignals($customer['phone'], $addressHash);
            $reference = $this->uniqueReference($db);

            $order = $db->prepare(<<<'SQL'
INSERT INTO orders
    (reference, idempotency_key, customer_id, status, shipment_status, review_status, customer_name, customer_phone, customer_email,
     delivery_address, district, subdistrict, postoffice, postcode, address_hash, delivery_zone_slug,
     subtotal, discount_total, delivery_charge, total, risk_score, is_suspicious)
VALUES
    (:reference, :idempotency_key, :customer_id, 'new_order', 'not_ready', 'pending', :customer_name, :customer_phone, :customer_email,
     :delivery_address, :district, :subdistrict, :postoffice, :postcode, :address_hash, :delivery_zone_slug,
     :subtotal, :discount_total, :delivery_charge, :total, :risk_score, :is_suspicious)
SQL);
            $order->execute([
                'reference' => $reference,
                'idempotency_key' => $idempotencyKey,
                'customer_id' => $customerId,
                'customer_name' => $customer['name'],
                'customer_phone' => $customer['phone'],
                'customer_email' => $customer['email'],
                'delivery_address' => $customer['address'],
                'district' => $location['district'],
                'subdistrict' => $location['subdistrict'],
                'postoffice' => $location['postoffice'],
                'postcode' => $location['postcode'],
                'address_hash' => $addressHash,
                'delivery_zone_slug' => $zone[0]['slug'],
                'subtotal' => round($subtotal, 2),
                'discount_total' => round($discountTotal, 2),
                'delivery_charge' => $deliveryCharge,
                'total' => $total,
                'risk_score' => $risk['score'],
                'is_suspicious' => $risk['flags'] === [] ? 0 : 1,
            ]);
            $orderId = (int) $db->lastInsertId();

            $insertItem = $db->prepare(<<<'SQL'
INSERT INTO order_items
    (order_id, product_id, variant_id, product_name, variant_name, sku, quantity,
     unit_price, line_subtotal, line_discount, line_total)
VALUES
    (:order_id, :product_id, :variant_id, :product_name, :variant_name, :sku, :quantity,
     :unit_price, :line_subtotal, :line_discount, :line_total)
SQL);
            $decrementVariant = $db->prepare(
                'UPDATE product_variants SET stock_qty = stock_qty - :quantity WHERE id = :id AND stock_qty >= :required_stock'
            );
            $decrementProduct = $db->prepare(
                'UPDATE products SET stock_qty = GREATEST(stock_qty - :quantity, 0) WHERE id = :id'
            );

            foreach ($lineItems as $line) {
                $insertItem->execute(['order_id' => $orderId] + $line);
                $decrementVariant->execute([
                    'quantity' => $line['quantity'],
                    'required_stock' => $line['quantity'],
                    'id' => $line['variant_id'],
                ]);
                if ($decrementVariant->rowCount() !== 1) {
                    throw new \InvalidArgumentException(sprintf('%s just sold out.', $line['product_name']));
                }
                $decrementProduct->execute([
                    'quantity' => $line['quantity'],
                    'id' => $line['product_id'],
                ]);
            }

            $history = $db->prepare(
                "INSERT INTO order_status_history (order_id, from_status, to_status, note) VALUES (:order_id, NULL, 'new_order', :note)"
            );
            $history->execute([
                'order_id' => $orderId,
                'note' => 'Guest order submitted; phone confirmation is pending.',
            ]);

            $flag = $db->prepare(
                'INSERT INTO fraud_flags (order_id, code, reason, risk_score) VALUES (:order_id, :code, :reason, :risk_score)'
            );
            foreach ($risk['flags'] as $riskFlag) {
                $flag->execute([
                    'order_id' => $orderId,
                    'code' => $riskFlag['code'],
                    'reason' => $riskFlag['reason'],
                    'risk_score' => $riskFlag['score'],
                ]);
            }

            $db->commit();
        } catch (\Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            if ($idempotencyKey !== null && $error instanceof \PDOException && $error->getCode() === '23000') {
                $existing = Database::view(
                    'SELECT reference FROM orders WHERE idempotency_key = :idempotency_key LIMIT 1',
                    ['idempotency_key' => $idempotencyKey]
                );
                if ($existing !== []) {
                    return $this->success((string) $existing[0]['reference']) ?? throw new \RuntimeException('Existing idempotent order could not be loaded.');
                }
            }
            throw $error;
        }

        return [
            'reference' => $reference,
            'status' => 'new_order',
            'items' => array_map(static fn (array $item): array => [
                'name' => $item['product_name'],
                'variant' => $item['variant_name'],
                'quantity' => $item['quantity'],
                'total' => $item['line_total'],
            ], $lineItems),
            'subtotal' => round($subtotal, 2),
            'discountTotal' => round($discountTotal, 2),
            'deliveryCharge' => $deliveryCharge,
            'total' => $total,
        ];
    }

    public function success(string $reference): ?array
    {
        $orders = Database::view(<<<'SQL'
SELECT reference, status, subtotal, discount_total, delivery_charge, total
FROM orders
WHERE reference = :reference
LIMIT 1
SQL, ['reference' => $reference]);

        if ($orders === []) {
            return null;
        }

        $order = $orders[0];
        $items = Database::view(
            'SELECT product_name, variant_name, quantity, line_total FROM order_items WHERE order_id = (SELECT id FROM orders WHERE reference = :reference LIMIT 1) ORDER BY id ASC',
            ['reference' => $reference]
        );

        return [
            'reference' => $order['reference'],
            'status' => $order['status'],
            'items' => array_map(static fn (array $item): array => [
                'name' => $item['product_name'],
                'variant' => $item['variant_name'],
                'quantity' => (int) $item['quantity'],
                'total' => (float) $item['line_total'],
            ], $items),
            'subtotal' => (float) $order['subtotal'],
            'discountTotal' => (float) $order['discount_total'],
            'deliveryCharge' => (float) $order['delivery_charge'],
            'total' => (float) $order['total'],
        ];
    }

    /** @return array{name: string, phone: string, email: ?string, address: string, district: string, subdistrict: string, postoffice: ?string} */
    private function validatedCustomer(array $input): array
    {
        $name = trim((string) ($input['name'] ?? $input['customer_name'] ?? ''));
        $phone = $this->normalizePhone((string) ($input['phone'] ?? $input['customer_phone'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $address = trim((string) ($input['address'] ?? $input['delivery_address'] ?? ''));
        $district = trim((string) ($input['district'] ?? ''));
        $subdistrict = trim((string) ($input['subdistrict'] ?? ''));
        $postoffice = trim((string) ($input['postoffice'] ?? '')) ?: null;

        if ($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 140 || !preg_match("/^[\\p{L} .'-]+$/u", $name)) {
            throw new \InvalidArgumentException('Enter a valid full name.');
        }
        if ($email !== '' && (mb_strlen($email) > 180 || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
            throw new \InvalidArgumentException('Enter a valid email address or leave email blank.');
        }
        if ($address === '' || mb_strlen($address) < 10 || mb_strlen($address) > 1000) {
            throw new \InvalidArgumentException('Enter a complete delivery address.');
        }
        if ($district === '' || $subdistrict === '') {
            throw new \InvalidArgumentException('Select your district and area/thana/upazila.');
        }

        return [
            'name' => $name,
            'phone' => $phone,
            'email' => $email !== '' ? $email : null,
            'address' => $address,
            'district' => $district,
            'subdistrict' => $subdistrict,
            'postoffice' => $postoffice,
        ];
    }

    private function validatedIdempotencyKey(mixed $input): ?string
    {
        if ($input === null || trim((string) $input) === '') {
            return null;
        }

        $key = trim((string) $input);
        if (!preg_match('/^[A-Za-z0-9_-]{16,128}$/', $key)) {
            throw new \InvalidArgumentException('Checkout retry key is invalid. Please refresh and try again.');
        }

        // Store only a digest; the browser keeps the opaque retry key.
        return hash('sha256', $key);
    }

    private function sessionCustomerId(): ?int
    {
        $auth = Session::get('auth');
        if (!is_array($auth) || !isset($auth['sub']) || !is_numeric($auth['sub']) || ($auth['role'] ?? 'user') === 'admin') {
            return null;
        }

        $userId = (int) $auth['sub'];
        $exists = Database::view('SELECT id FROM users WHERE id = :id LIMIT 1', ['id' => $userId]);
        return $exists === [] ? null : $userId;
    }

    /** @return array<int, array{product_id: int, variant_id: ?int, quantity: int}> */
    private function validatedItems(mixed $input): array
    {
        if (!is_array($input) || $input === [] || count($input) > 20) {
            throw new \InvalidArgumentException('Your bag is empty or contains too many items.');
        }

        $items = [];
        foreach ($input as $item) {
            if (!is_array($item)) {
                throw new \InvalidArgumentException('Your bag contains an invalid item.');
            }
            $productId = (int) ($item['product_id'] ?? $item['productId'] ?? 0);
            $variantId = (int) ($item['variant_id'] ?? $item['variantId'] ?? 0);
            $quantity = (int) ($item['quantity'] ?? 0);
            if ($productId < 1 || $quantity < 1 || $quantity > 10) {
                throw new \InvalidArgumentException('Each item quantity must be between 1 and 10.');
            }

            $key = $productId . ':' . ($variantId > 0 ? $variantId : 'default');
            if (isset($items[$key])) {
                $items[$key]['quantity'] += $quantity;
                if ($items[$key]['quantity'] > 10) {
                    throw new \InvalidArgumentException('Each item quantity must be between 1 and 10.');
                }
            } else {
                $items[$key] = [
                    'product_id' => $productId,
                    'variant_id' => $variantId > 0 ? $variantId : null,
                    'quantity' => $quantity,
                ];
            }
        }

        return array_values($items);
    }

    private function validatedLocation(string $district, string $subdistrict, ?string $postoffice): array
    {
        $sql = 'SELECT district, subdistrict, postoffice, postcode FROM bangladesh_locations WHERE district = :district AND subdistrict = :subdistrict';
        $params = ['district' => $district, 'subdistrict' => $subdistrict];
        if ($postoffice !== null) {
            $sql .= ' AND postoffice = :postoffice';
            $params['postoffice'] = $postoffice;
        }
        $sql .= ' ORDER BY postoffice ASC LIMIT 1';
        $rows = Database::view($sql, $params);
        if ($rows === []) {
            throw new \InvalidArgumentException('Select a valid Bangladesh location.');
        }

        return [
            'district' => (string) $rows[0]['district'],
            'subdistrict' => (string) $rows[0]['subdistrict'],
            'postoffice' => $postoffice !== null ? (string) $rows[0]['postoffice'] : null,
            'postcode' => $postoffice !== null ? (string) $rows[0]['postcode'] : null,
        ];
    }

    private function lockedCatalogItem(int $productId, ?int $variantId): ?array
    {
        $sql = <<<SQL
SELECT p.id AS product_id, p.name AS product_name,
       v.id AS variant_id, v.name AS variant_name, v.sku, v.price, v.stock_qty
FROM products p
INNER JOIN product_variants v ON v.product_id = p.id
WHERE p.id = :product_id
  AND p.is_active = 1
  AND v.is_active = 1
SQL;
        $params = ['product_id' => $productId];
        if ($variantId !== null) {
            $sql .= ' AND v.id = :variant_id';
            $params['variant_id'] = $variantId;
        }
        $sql .= ' ORDER BY v.sort_order ASC, v.id ASC LIMIT 1 FOR UPDATE';

        $rows = Database::view($sql, $params);
        return $rows[0] ?? null;
    }

    private function discountFor(int $productId, int $variantId, float $price): float
    {
        $rows = Database::view(<<<'SQL'
SELECT discount_type, value
FROM discounts
WHERE is_active = 1
  AND (starts_at IS NULL OR starts_at <= NOW())
  AND (ends_at IS NULL OR ends_at >= NOW())
  AND ((variant_id = :variant_id) OR (variant_id IS NULL AND product_id = :product_id))
ORDER BY CASE WHEN variant_id IS NULL THEN 1 ELSE 0 END, id DESC
LIMIT 1
SQL, ['variant_id' => $variantId, 'product_id' => $productId]);

        if ($rows === []) {
            return 0.0;
        }

        $value = (float) $rows[0]['value'];
        return min($price, max(0.0, $rows[0]['discount_type'] === 'percent' ? $price * ($value / 100) : $value));
    }

    private function normalizePhone(string $phone): string
    {
        $phone = strtr($phone, [
            '০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4',
            '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9',
        ]);
        $digits = preg_replace('/\\D+/', '', $phone) ?? '';
        if (str_starts_with($digits, '880')) {
            $digits = '0' . substr($digits, 3);
        }
        if (!preg_match('/^01[3-9]\\d{8}$/', $digits)) {
            throw new \InvalidArgumentException('Enter a valid Bangladesh mobile number.');
        }
        return $digits;
    }

    private function normalizeAddress(string $address, array $location): string
    {
        return mb_strtolower(preg_replace('/\\s+/u', ' ', trim($address)) . '|' . $location['district'] . '|' . $location['subdistrict']);
    }

    /** @return array{score: int, flags: array<int, array{code: string, reason: string, score: int}>} */
    private function riskSignals(string $phone, string $addressHash): array
    {
        $phoneCount = (int) Database::connection()->query(
            "SELECT COUNT(*) FROM orders WHERE customer_phone = " . Database::connection()->quote($phone) . " AND created_at >= (NOW() - INTERVAL 24 HOUR)"
        )->fetchColumn();
        $addressCount = (int) Database::connection()->query(
            "SELECT COUNT(*) FROM orders WHERE address_hash = " . Database::connection()->quote($addressHash) . " AND created_at >= (NOW() - INTERVAL 24 HOUR)"
        )->fetchColumn();

        $flags = [];
        if ($phoneCount > 0) {
            $flags[] = ['code' => 'repeat_phone_24h', 'reason' => 'Another order used this phone number in the last 24 hours.', 'score' => 35];
        }
        if ($addressCount > 0) {
            $flags[] = ['code' => 'repeat_address_24h', 'reason' => 'Another order used this delivery address in the last 24 hours.', 'score' => 35];
        }

        return [
            'score' => min(100, array_sum(array_column($flags, 'score'))),
            'flags' => $flags,
        ];
    }

    private function uniqueReference(\PDO $db): string
    {
        do {
            $reference = 'OW-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
            $check = $db->prepare('SELECT id FROM orders WHERE reference = :reference LIMIT 1');
            $check->execute(['reference' => $reference]);
        } while ($check->fetchColumn());

        return $reference;
    }
}

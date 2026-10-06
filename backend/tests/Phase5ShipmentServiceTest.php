<?php

declare(strict_types=1);

/**
 * Run with: php tests/Phase5ShipmentServiceTest.php
 *
 * This is a local MySQL integration check. It uses FakePathaoProvider only,
 * creates disposable orders, and removes them after restoring reserved stock.
 */

require dirname(__DIR__) . '/index.php';

use App\Services\CheckoutService;
use App\Services\FakePathaoProvider;
use App\Services\OrderAdminService;
use App\Services\ShipmentService;
use Nemesis\Core\Database;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$orders = new OrderAdminService();
$createdReferences = [];

$cleanup = static function () use (&$createdReferences, $orders): void {
    foreach ($createdReferences as $reference) {
        try {
            $row = Database::view(
                'SELECT status FROM orders WHERE reference = :reference LIMIT 1',
                ['reference' => $reference]
            )[0] ?? null;
            if ($row !== null && in_array($row['status'], ['new_order', 'pending_confirmation', 'confirmed', 'processing'], true)) {
                $orders->transition($reference, 'cancelled', 'Clean up disposable Phase 5 test order.');
            }
            Database::connection()->prepare('DELETE FROM orders WHERE reference = :reference')->execute([
                'reference' => $reference,
            ]);
        } catch (Throwable $error) {
            fwrite(STDERR, 'Cleanup failed for ' . $reference . ': ' . $error->getMessage() . PHP_EOL);
        }
    }
};

try {
    putenv('PATHAO_MAX_ATTEMPTS=3');
    $catalogItem = Database::view(<<<'SQL'
SELECT p.id AS product_id, v.id AS variant_id
FROM products p
INNER JOIN product_variants v ON v.product_id = p.id
WHERE p.is_active = 1 AND v.is_active = 1 AND p.stock_qty > 0 AND v.stock_qty > 0
ORDER BY p.id
LIMIT 1
SQL)[0] ?? null;
    $assert($catalogItem !== null, 'No local in-stock catalog item is available.');

    $createOrder = static function () use ($catalogItem): array {
        $suffix = (string) random_int(10000000, 99999999);
        return (new CheckoutService())->createOrder([
            'name' => 'Phase Five Test',
            'phone' => '017' . $suffix,
            'address' => 'House ' . substr($suffix, 0, 4) . ', Road ' . substr($suffix, 4, 4) . ', Demra, Dhaka',
            'district' => 'Dhaka',
            'subdistrict' => 'Demra',
            'postoffice' => 'Demra',
            'items' => [[
                'product_id' => (int) $catalogItem['product_id'],
                'variant_id' => (int) $catalogItem['variant_id'],
                'quantity' => 1,
            ]],
        ]);
    };

    $confirmOrder = static function (string $reference) use ($orders): void {
        $orders->transition($reference, 'pending_confirmation', 'Local Phase 5 test review.');
        $orders->transition($reference, 'confirmed', 'Local Phase 5 test phone confirmation.');
        foreach (($orders->detail($reference)['fraudFlags'] ?? []) as $flag) {
            if ($flag['resolution'] === 'pending') {
                $orders->reviewFlag((int) $flag['id'], 'confirmed', 'Reviewed as disposable local test data.');
            }
        }
    };

    $successOrder = $createOrder();
    $createdReferences[] = $successOrder['reference'];
    $confirmOrder($successOrder['reference']);
    $fakeSuccess = new FakePathaoProvider();
    $successService = new ShipmentService($fakeSuccess);
    $first = $successService->createForOrder($successOrder['reference']);
    $second = $successService->createForOrder($successOrder['reference']);
    $assert($first['shipment']['status'] === 'created', 'Fake provider success did not create a shipment.');
    $assert($first['shipment']['externalId'] === $second['shipment']['externalId'], 'Repeated lookup changed the provider reference.');
    $assert($fakeSuccess->calls() === 1, 'Repeated create called the provider more than once.');

    $failureOrder = $createOrder();
    $createdReferences[] = $failureOrder['reference'];
    $confirmOrder($failureOrder['reference']);
    $failure = [
        'success' => false,
        'retryable' => true,
        'httpStatus' => 503,
        'externalId' => null,
        'merchantOrderId' => null,
        'message' => 'Fake temporary provider outage.',
    ];
    $fakeFailure = new FakePathaoProvider([$failure, $failure, $failure, $failure]);
    $failureService = new ShipmentService($fakeFailure);
    $failureService->createForOrder($failureOrder['reference']);
    $failureService->createForOrder($failureOrder['reference']);
    $failureService->createForOrder($failureOrder['reference']);
    $capped = $failureService->createForOrder($failureOrder['reference']);
    $assert($capped['shipment']['status'] === 'manual_required', 'Failed shipment did not remain recoverable.');
    $assert($capped['shipment']['attemptCount'] === 3, 'Retry limit did not cap attempts.');
    $assert($fakeFailure->calls() === 3, 'Retry limit called the provider after the cap.');

    $manual = $failureService->recordManual(
        $failureOrder['reference'],
        'MANUAL-OW-5',
        'Created in courier panel during local test.'
    );
    $assert($manual['shipment']['status'] === 'manual_created', 'Manual fallback was not recorded.');
    $assert($manual['shipment']['manualReference'] === 'MANUAL-OW-5', 'Manual reference was not persisted.');

    $cleanup();
    $createdReferences = [];
    echo 'phase5 shipment test passed: idempotency=1, retry_calls=' . $fakeFailure->calls() . ', manual_fallback=1, cleanup=1' . PHP_EOL;
} catch (Throwable $error) {
    $cleanup();
    fwrite(STDERR, 'phase5 shipment test failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}

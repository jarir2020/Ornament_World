<?php

declare(strict_types=1);

/**
 * Run with: php tests/Phase8CustomerAccountsTest.php
 *
 * Local MySQL integration test for optional customer accounts. It creates one
 * disposable customer and order, then removes both. No customer data leaves
 * the local database and no external provider is contacted.
 */

require dirname(__DIR__) . '/index.php';

use App\Http\Middleware\CustomerAuthenticate;
use App\Services\CheckoutService;
use App\Services\CustomerAccountService;
use App\Services\OrderAdminService;
use Nemesis\Config\SessionConfig;
use Nemesis\Core\Database;
use Nemesis\Http\Request;
use Nemesis\Http\Response;
use Nemesis\Http\Session;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$db = Database::connection();
$accounts = new CustomerAccountService();
$checkout = new CheckoutService();
$orders = new OrderAdminService();
$createdUserId = null;
$createdReference = null;
$sessionStarted = false;

$cleanup = static function () use (&$createdUserId, &$createdReference, &$sessionStarted, $db, $orders): void {
    if ($createdReference !== null) {
        try {
            $row = Database::view('SELECT status FROM orders WHERE reference = :reference LIMIT 1', ['reference' => $createdReference])[0] ?? null;
            if ($row !== null && in_array($row['status'], ['new_order', 'pending_confirmation', 'confirmed', 'processing'], true)) {
                $orders->transition($createdReference, 'cancelled', 'Clean up disposable Phase 8 customer test order.');
            }
            $db->prepare('DELETE FROM orders WHERE reference = :reference')->execute(['reference' => $createdReference]);
        } catch (Throwable $error) {
            fwrite(STDERR, 'Customer order cleanup failed: ' . $error->getMessage() . PHP_EOL);
        }
    }
    if ($sessionStarted) {
        Session::remove('auth');
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }
    if ($createdUserId !== null) {
        $db->prepare('DELETE FROM user_roles WHERE user_id = :id')->execute(['id' => $createdUserId]);
        $db->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $createdUserId]);
    }
};

try {
    $catalogItem = Database::view(<<<'SQL'
SELECT p.id AS product_id, v.id AS variant_id
FROM products p
INNER JOIN product_variants v ON v.product_id = p.id
WHERE p.is_active = 1 AND v.is_active = 1 AND v.stock_qty > 0
ORDER BY p.id
LIMIT 1
SQL)[0] ?? null;
    $assert($catalogItem !== null, 'No local in-stock product is available.');

    $suffix = (string) random_int(10000000, 99999999);
    $email = 'phase8-' . $suffix . '@example.test';
    $auth = $accounts->register([
        'name' => 'Phase Eight Customer',
        'email' => $email,
        'phone' => '+8801712345678',
        'password' => 'phase8-disposable-password',
        'password_confirmation' => 'phase8-disposable-password',
    ]);
    $createdUserId = (int) $auth['sub'];
    $assert($auth['role'] === 'user', 'New customer received an unexpected elevated role.');
    $assert(($accounts->authenticate($email, 'phase8-disposable-password')['sub'] ?? null) === $createdUserId, 'Customer login authentication failed.');

    Session::boot(SessionConfig::fromEnv());
    new Session();
    $sessionStarted = true;
    Session::set('auth', $auth);
    $_SERVER['HTTP_ACCEPT'] = 'text/html';
    $request = new Request();
    $seenAuth = null;
    $response = (new CustomerAuthenticate())->handle($request, static function (Request $request) use (&$seenAuth): Response {
        $seenAuth = $request->getMeta('auth');
        return Response::make('profile-ok');
    });
    $assert($response->getStatus() === 200 && ($seenAuth['sub'] ?? null) === $createdUserId, 'Customer profile middleware did not accept the authenticated session.');

    $profile = $accounts->profile($createdUserId);
    $assert($profile !== null && $profile['user']['email'] === $email, 'Customer profile could not be loaded.');
    $updated = $accounts->updateProfile($createdUserId, [
        'name' => 'Phase Eight Updated',
        'phone' => '01719876543',
    ]);
    $assert($updated['user']['name'] === 'Phase Eight Updated' && $updated['user']['phone'] === '01719876543', 'Customer profile update was not persisted.');

    $order = $checkout->createOrder([
        'name' => 'Phase Eight Updated',
        'phone' => '01719876543',
        'address' => 'House 8, Road 8, Demra, Dhaka',
        'district' => 'Dhaka',
        'subdistrict' => 'Demra',
        'postoffice' => 'Demra',
        'idempotency_key' => 'phase8-account-' . $suffix,
        'items' => [[
            'product_id' => (int) $catalogItem['product_id'],
            'variant_id' => (int) $catalogItem['variant_id'],
            'quantity' => 1,
        ]],
    ]);
    $createdReference = $order['reference'];
    $orderRow = Database::view('SELECT customer_id FROM orders WHERE reference = :reference LIMIT 1', ['reference' => $createdReference])[0] ?? null;
    $assert($orderRow !== null && (int) $orderRow['customer_id'] === $createdUserId, 'Authenticated checkout was not linked to the customer account.');
    $linkedProfile = $accounts->profile($createdUserId);
    $assert(count($linkedProfile['orders'] ?? []) === 1 && $linkedProfile['orders'][0]['reference'] === $createdReference, 'Customer order history did not include the linked order.');

    $cleanup();
    echo 'phase8 customer accounts test passed: registration=1, login=1, profile=1, linked_order=1, cleanup=1' . PHP_EOL;
} catch (Throwable $error) {
    $cleanup();
    fwrite(STDERR, 'phase8 customer accounts test failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}

<?php

declare(strict_types=1);

/**
 * Run with: php tests/Phase7ReleaseGateTest.php
 *
 * Local release-gate integration checks. The test creates disposable orders,
 * an RBAC admin identity, and a role assignment, then removes all of them.
 * It never calls Pathao or sends analytics data.
 */

require dirname(__DIR__) . '/index.php';

use App\Http\Middleware\AdminAuthenticate;
use App\Controllers\UserController;
use App\Services\AdminAuthService;
use App\Services\CheckoutService;
use App\Services\OrderAdminService;
use Nemesis\Core\Database;
use Nemesis\Http\Request;
use Nemesis\Http\Response;
use Nemesis\Http\Session;
use Nemesis\Config\SessionConfig;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$expectInvalid = static function (callable $callback, string $message) use ($assert): void {
    try {
        $callback();
    } catch (InvalidArgumentException) {
        return;
    }

    $assert(false, $message);
};

$db = Database::connection();
$orders = new OrderAdminService();
$checkout = new CheckoutService();
$createdReferences = [];
$createdUserId = null;
$createdRoleId = null;
$sessionStarted = false;

$cleanup = static function () use (&$createdReferences, &$createdUserId, &$createdRoleId, $orders, $db, &$sessionStarted): void {
    foreach ($createdReferences as $reference) {
        try {
            $row = Database::view(
                'SELECT status FROM orders WHERE reference = :reference LIMIT 1',
                ['reference' => $reference]
            )[0] ?? null;
            if ($row !== null && in_array($row['status'], ['new_order', 'pending_confirmation', 'confirmed', 'processing'], true)) {
                $orders->transition($reference, 'cancelled', 'Clean up disposable Phase 7 test order.');
            }
            $db->prepare('DELETE FROM orders WHERE reference = :reference')->execute(['reference' => $reference]);
        } catch (Throwable $error) {
            fwrite(STDERR, 'Order cleanup failed: ' . $error->getMessage() . PHP_EOL);
        }
    }

    if ($sessionStarted) {
        Session::remove('auth');
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    if ($createdUserId !== null) {
        $db->prepare('DELETE FROM user_roles WHERE user_id = :user_id')->execute(['user_id' => $createdUserId]);
        $db->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $createdUserId]);
    }
    if ($createdRoleId !== null) {
        $db->prepare('DELETE FROM roles WHERE id = :id')->execute(['id' => $createdRoleId]);
    }
};

try {
    $catalogItem = Database::view(<<<'SQL'
SELECT p.id AS product_id, v.id AS variant_id, v.stock_qty
FROM products p
INNER JOIN product_variants v ON v.product_id = p.id
WHERE p.is_active = 1 AND v.is_active = 1 AND v.stock_qty > 2
ORDER BY p.id
LIMIT 1
SQL)[0] ?? null;
    $assert($catalogItem !== null, 'No disposable in-stock catalog item is available.');

    $baseInput = static function (string $phone, string $address, ?string $idempotencyKey = null) use ($catalogItem): array {
        return [
            'name' => 'Phase Seven QA',
            'phone' => $phone,
            'email' => '',
            'address' => $address,
            'district' => 'Dhaka',
            'subdistrict' => 'Demra',
            'postoffice' => 'Demra',
            // These values deliberately disagree with the catalog. Checkout
            // must ignore browser prices and client-calculated delivery.
            'price' => 1,
            'delivery_charge' => 1,
            'total' => 1,
            'idempotency_key' => $idempotencyKey,
            'items' => [[
                'product_id' => (int) $catalogItem['product_id'],
                'variant_id' => (int) $catalogItem['variant_id'],
                'quantity' => 1,
                'unit_price' => 1,
            ]],
        ];
    };

    $expectInvalid(
        static fn(): array => $checkout->createOrder($baseInput('01234567890', 'House 7, Road 7, Demra, Dhaka')),
        'Invalid Bangladesh phone input was accepted.'
    );
    $expectInvalid(
        static fn(): array => $checkout->createOrder(array_replace(
            $baseInput('01712345678', 'House 7, Road 7, Demra, Dhaka'),
            ['district' => 'Not a district']
        )),
        'Invalid Bangladesh location input was accepted.'
    );
    $expectInvalid(
        static fn(): array => $checkout->createOrder(array_replace(
            $baseInput('01712345678', 'House 7, Road 7, Demra, Dhaka'),
            ['items' => [['product_id' => (int) $catalogItem['product_id'], 'variant_id' => (int) $catalogItem['variant_id'], 'quantity' => 11]]]
        )),
        'Invalid item quantity was accepted.'
    );

    $qaPhone = '017' . random_int(10000000, 99999999);
    $qaAddress = 'House ' . random_int(100, 999) . ', Road 7, Demra, Dhaka';
    $retryKey = 'phase7-retry-' . random_int(10000000, 99999999);
    $order = $checkout->createOrder($baseInput('+880' . substr($qaPhone, 1), $qaAddress, $retryKey));
    $createdReferences[] = $order['reference'];
    $duplicate = $checkout->createOrder($baseInput($qaPhone, $qaAddress, $retryKey));
    $assert($duplicate['reference'] === $order['reference'], 'Repeated checkout submission did not return the original order.');
    $orderCount = (int) (Database::view('SELECT COUNT(*) AS total FROM orders WHERE reference = :reference', ['reference' => $order['reference']])[0]['total'] ?? 0);
    $assert($orderCount === 1, 'Repeated checkout submission created a duplicate order.');
    $row = Database::view('SELECT * FROM orders WHERE reference = :reference LIMIT 1', ['reference' => $order['reference']])[0] ?? null;
    $assert($row !== null, 'Checkout did not persist the disposable order.');
    $assert($row['customer_phone'] === $qaPhone, 'Bangladesh phone normalization was not persisted.');
    $assert((float) $row['delivery_charge'] === 60.0, 'Inside-Dhaka delivery charge is incorrect.');
    $assert((float) $row['total'] !== 1.0, 'Client-supplied total was trusted.');
    $assert(abs((float) $row['total'] - ((float) $row['subtotal'] - (float) $row['discount_total'] + (float) $row['delivery_charge'])) < 0.001, 'Server-side total formula is inconsistent.');

    $expectInvalid(
        static fn(): array => $orders->transition($order['reference'], 'shipped', 'Invalid direct transition.'),
        'Invalid order status transition was accepted.'
    );
    $orders->transition($order['reference'], 'pending_confirmation', 'Phase 7 QA review.');
    $orders->transition($order['reference'], 'confirmed', 'Phase 7 QA phone confirmation.');
    $orders->transition($order['reference'], 'processing');
    $orders->transition($order['reference'], 'cancelled', 'Phase 7 QA stock-release check.');
    $stockAfterCancel = (int) (Database::view('SELECT stock_qty FROM product_variants WHERE id = :id', ['id' => (int) $catalogItem['variant_id']])[0]['stock_qty'] ?? -1);
    $assert($stockAfterCancel === (int) $catalogItem['stock_qty'], 'Cancelled order did not restore reserved variant stock exactly once.');

    $fraudPhone = '017' . random_int(10000000, 99999999);
    $fraudAddress = 'House ' . random_int(100, 999) . ', Road 9, Demra, Dhaka';
    $firstFraud = $checkout->createOrder($baseInput($fraudPhone, $fraudAddress));
    $secondFraud = $checkout->createOrder($baseInput($fraudPhone, $fraudAddress));
    $createdReferences[] = $firstFraud['reference'];
    $createdReferences[] = $secondFraud['reference'];
    $fraudRow = Database::view('SELECT risk_score, is_suspicious FROM orders WHERE reference = :reference LIMIT 1', ['reference' => $secondFraud['reference']])[0] ?? null;
    $assert($fraudRow !== null && (int) $fraudRow['risk_score'] >= 70 && (int) $fraudRow['is_suspicious'] === 1, 'Repeated phone/address fraud signals were not recorded.');

    $role = Database::view("SELECT id FROM roles WHERE slug = 'admin' LIMIT 1")[0] ?? null;
    if ($role === null) {
        $db->prepare("INSERT INTO roles (name, slug, description) VALUES ('Administrator', 'admin', 'Phase 7 disposable admin role')")->execute();
        $createdRoleId = (int) $db->lastInsertId();
        $role = ['id' => $createdRoleId];
    }
    $suffix = (string) random_int(10000000, 99999999);
    $db->prepare('INSERT INTO users (username, email, password) VALUES (:username, :email, :password)')->execute([
        'username' => 'phase7_' . $suffix,
        'email' => 'phase7-' . $suffix . '@example.test',
        'password' => password_hash('phase7-disposable-password', PASSWORD_BCRYPT),
    ]);
    $createdUserId = (int) $db->lastInsertId();
    $db->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)')->execute([
        'user_id' => $createdUserId,
        'role_id' => (int) $role['id'],
    ]);

    $authService = new AdminAuthService();
    $auth = $authService->authenticate('phase7-' . $suffix . '@example.test', 'phase7-disposable-password');
    $assert($auth !== null && $auth['role'] === 'admin', 'Admin credential/RBAC lookup failed.');

    Session::boot(SessionConfig::fromEnv());
    new Session();
    $sessionStarted = true;
    Session::remove('auth');
    $_GET = [];
    $_POST = [
        'email' => 'phase7-' . $suffix . '@example.test',
        'password' => 'phase7-disposable-password',
    ];
    $_SERVER['HTTP_ACCEPT'] = 'text/html';
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $loginResponse = (new UserController())->login(new Request());
    $assert($loginResponse instanceof Response && $loginResponse->isRedirect() && $loginResponse->getRedirectUrl() === '/admin', 'Browser admin login did not redirect to the protected admin page.');
    $assert((Session::get('auth')['role'] ?? null) === 'admin', 'Browser admin login did not establish the admin session.');

    $request = new Request();
    $seenAuth = null;
    $response = (new AdminAuthenticate())->handle($request, static function (Request $request) use (&$seenAuth): Response {
        $seenAuth = $request->getMeta('auth');
        return Response::make('admin-ok');
    });
    $assert($response->getStatus() === 200 && ($seenAuth['role'] ?? null) === 'admin', 'Browser admin session was not accepted by the protected route.');

    Session::remove('auth');
    $request = new Request();
    $response = (new AdminAuthenticate())->handle($request, static fn(): Response => Response::make('unexpected'));
    $assert($response->isRedirect() && $response->getRedirectUrl() === '/login?next=/admin', 'Unauthenticated admin browser request was not redirected to login.');

    $routeSource = (string) file_get_contents(dirname(__DIR__) . '/routes/route.php');
    $assert(str_contains($routeSource, "['admin']"), 'Admin routes are not protected by the application admin middleware.');
    $assert(!str_contains($routeSource, "['auth:admin']"), 'Legacy bearer-only auth middleware still protects browser admin routes.');

    $cleanup();
    echo 'phase7 release gate passed: validation=1, pricing_delivery=1, status_stock=1, fraud=1, admin_session=1, cleanup=1' . PHP_EOL;
} catch (Throwable $error) {
    $cleanup();
    fwrite(STDERR, 'phase7 release gate failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}

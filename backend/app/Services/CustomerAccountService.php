<?php

declare(strict_types=1);

namespace App\Services;

use Nemesis\Core\Database;

/**
 * Optional customer-account boundary.
 *
 * Accounts are additive: guest checkout remains valid, while an authenticated
 * customer can maintain a profile and see orders linked after sign-in.
 */
final class CustomerAccountService
{
    public function authenticate(string $email, string $password): ?array
    {
        $rows = Database::view(<<<'SQL'
SELECT u.id, u.email, u.password, COALESCE(r.slug, 'user') AS role
FROM users u
LEFT JOIN user_roles ur ON ur.user_id = u.id
LEFT JOIN roles r ON r.id = ur.role_id
WHERE u.email = :email
ORDER BY CASE WHEN r.slug = 'admin' THEN 0 ELSE 1 END, r.id ASC
LIMIT 1
SQL, ['email' => strtolower(trim($email))]);

        if ($rows === [] || !password_verify($password, (string) $rows[0]['password'])) {
            return null;
        }

        return $this->authContext($rows[0]);
    }

    public function register(array $input): array
    {
        $name = $this->validName($input['name'] ?? '');
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $password = (string) ($input['password'] ?? '');
        $confirmation = (string) ($input['password_confirmation'] ?? $input['passwordConfirmation'] ?? '');
        $phone = $this->validPhone($input['phone'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) {
            throw new \InvalidArgumentException('Enter a valid email address.');
        }
        if (mb_strlen($password) < 12 || mb_strlen($password) > 200) {
            throw new \InvalidArgumentException('Password must contain 12 to 200 characters.');
        }
        if ($password !== $confirmation) {
            throw new \InvalidArgumentException('Password confirmation does not match.');
        }

        $db = Database::connection();
        $existing = $db->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $existing->execute(['email' => $email]);
        if ($existing->fetchColumn()) {
            throw new \RuntimeException('An account already exists for that email address.');
        }

        $username = 'customer_' . bin2hex(random_bytes(6));
        $insert = $db->prepare(<<<'SQL'
INSERT INTO users (username, name, email, phone, password)
VALUES (:username, :name, :email, :phone, :password)
SQL);
        $insert->execute([
            'username' => $username,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'password' => password_hash($password, PASSWORD_DEFAULT),
        ]);

        return [
            'sub' => (int) $db->lastInsertId(),
            'email' => $email,
            'role' => 'user',
        ];
    }

    public function sessionUser(int $userId): ?array
    {
        $rows = Database::view(<<<'SQL'
SELECT u.id, u.email, COALESCE(r.slug, 'user') AS role
FROM users u
LEFT JOIN user_roles ur ON ur.user_id = u.id
LEFT JOIN roles r ON r.id = ur.role_id
WHERE u.id = :id
ORDER BY CASE WHEN r.slug = 'admin' THEN 0 ELSE 1 END, r.id ASC
LIMIT 1
SQL, ['id' => $userId]);

        return $rows === [] ? null : $this->authContext($rows[0]);
    }

    public function profile(int $userId): ?array
    {
        $users = Database::view(<<<'SQL'
SELECT id, name, email, phone, created_at
FROM users
WHERE id = :id
LIMIT 1
SQL, ['id' => $userId]);
        if ($users === []) {
            return null;
        }

        return [
            'user' => [
                'id' => (int) $users[0]['id'],
                'name' => (string) ($users[0]['name'] ?? ''),
                'email' => (string) $users[0]['email'],
                'phone' => (string) ($users[0]['phone'] ?? ''),
                'createdAt' => $users[0]['created_at'],
            ],
            'orders' => array_map(static fn (array $order): array => [
                'reference' => (string) $order['reference'],
                'status' => (string) $order['status'],
                'total' => (float) $order['total'],
                'createdAt' => $order['created_at'],
            ], Database::view(<<<'SQL'
SELECT reference, status, total, created_at
FROM orders
WHERE customer_id = :customer_id
ORDER BY created_at DESC, id DESC
LIMIT 20
SQL, ['customer_id' => $userId])),
        ];
    }

    public function updateProfile(int $userId, array $input): array
    {
        $name = $this->validName($input['name'] ?? '');
        $phone = $this->validPhone($input['phone'] ?? '');
        $statement = Database::connection()->prepare(
            'UPDATE users SET name = :name, phone = :phone WHERE id = :id'
        );
        $statement->execute([
            'name' => $name,
            'phone' => $phone,
            'id' => $userId,
        ]);

        return $this->profile($userId) ?? throw new \RuntimeException('Customer account not found.');
    }

    private function authContext(array $row): array
    {
        return [
            'sub' => (int) $row['id'],
            'email' => (string) $row['email'],
            'role' => (string) ($row['role'] ?? 'user'),
        ];
    }

    private function validName(mixed $value): string
    {
        $name = trim((string) $value);
        if ($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 140 || !preg_match("/^[\\p{L} .'-]+$/u", $name)) {
            throw new \InvalidArgumentException('Enter a valid full name.');
        }
        return $name;
    }

    private function validPhone(mixed $value): ?string
    {
        $phone = trim((string) $value);
        if ($phone === '') {
            return null;
        }

        $digits = preg_replace('/\\D+/', '', strtr($phone, [
            '০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4',
            '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9',
        ])) ?? '';
        if (str_starts_with($digits, '880')) {
            $digits = '0' . substr($digits, 3);
        }
        if (!preg_match('/^01[3-9]\\d{8}$/', $digits)) {
            throw new \InvalidArgumentException('Enter a valid Bangladesh mobile number or leave it blank.');
        }
        return $digits;
    }
}

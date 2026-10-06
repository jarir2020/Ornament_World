<?php

declare(strict_types=1);

namespace App\Services;

use Nemesis\Core\Database;

/**
 * Browser/admin authentication boundary.
 *
 * Admin access is resolved from the normalized Nemesis RBAC tables instead of
 * trusting a role value posted by the browser or copied into a long-lived
 * session without revalidation.
 */
final class AdminAuthService
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
SQL, ['email' => trim($email)]);

        if ($rows === [] || !password_verify($password, (string) $rows[0]['password'])) {
            return null;
        }

        return [
            'sub' => (int) $rows[0]['id'],
            'email' => (string) $rows[0]['email'],
            'role' => (string) $rows[0]['role'],
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

        if ($rows === []) {
            return null;
        }

        return [
            'sub' => (int) $rows[0]['id'],
            'email' => (string) $rows[0]['email'],
            'role' => (string) $rows[0]['role'],
        ];
    }
}

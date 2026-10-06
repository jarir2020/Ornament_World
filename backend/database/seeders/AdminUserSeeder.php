<?php

declare(strict_types=1);

use Nemesis\Core\Database;
use Nemesis\Database\Seeder;

/**
 * Provision one protected admin from deployment-only environment variables.
 *
 * Required: ADMIN_EMAIL and ADMIN_PASSWORD. Optional: ADMIN_USERNAME.
 * No default account or password is embedded in the repository.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = trim((string) getenv('ADMIN_EMAIL'));
        $password = (string) getenv('ADMIN_PASSWORD');
        $username = trim((string) (getenv('ADMIN_USERNAME') ?: ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('ADMIN_EMAIL must be a valid email address.');
        }
        if (strlen($password) < 12) {
            throw new RuntimeException('ADMIN_PASSWORD must contain at least 12 characters.');
        }

        $username = $username !== '' ? $username : (string) preg_replace('/[^a-z0-9_]+/i', '_', strstr($email, '@', true) ?: 'admin');
        $username = trim($username, '_');
        if ($username === '' || strlen($username) > 50) {
            throw new RuntimeException('ADMIN_USERNAME must be between 1 and 50 safe characters.');
        }

        $db = Database::connect();
        $role = $db->query("SELECT id FROM roles WHERE slug = 'admin' LIMIT 1")->fetchColumn();
        if (!$role) {
            $insertRole = $db->prepare("INSERT INTO roles (name, slug, description) VALUES ('Administrator', 'admin', 'Ornaments World staff administrator')");
            $insertRole->execute();
            $role = $db->lastInsertId();
        }

        $existing = $db->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $existing->execute(['email' => $email]);
        $userId = $existing->fetchColumn();
        $hash = password_hash($password, PASSWORD_DEFAULT);

        if ($userId) {
            $update = $db->prepare('UPDATE users SET username = :username, password = :password WHERE id = :id');
            $update->execute(['username' => $username, 'password' => $hash, 'id' => $userId]);
        } else {
            $insert = $db->prepare('INSERT INTO users (username, email, password) VALUES (:username, :email, :password)');
            $insert->execute(['username' => $username, 'email' => $email, 'password' => $hash]);
            $userId = $db->lastInsertId();
        }

        $assignment = $db->prepare('INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (:user_id, :role_id)');
        $assignment->execute(['user_id' => $userId, 'role_id' => $role]);
    }
}

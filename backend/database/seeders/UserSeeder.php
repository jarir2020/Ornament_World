<?php

declare(strict_types=1);

use Nemesis\Database\Seeder;

/**
 * Keep the framework's conventional seeder name without shipping plaintext
 * sample credentials. Provision an admin only from deployment environment
 * variables through AdminUserSeeder.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        require_once __DIR__ . '/AdminUserSeeder.php';
        (new AdminUserSeeder())->run();
    }
}

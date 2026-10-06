<?php

declare(strict_types=1);

use Nemesis\Core\Database;
use Nemesis\Database\Migration;

/** Optional customer profile fields and account-owned order references. */
class add_customer_accounts extends Migration
{
    public function up(): void
    {
        $db = Database::connect();
        $userColumns = array_column($db->query('SHOW COLUMNS FROM users')->fetchAll(\PDO::FETCH_ASSOC), 'Field');
        $userAdds = [];
        if (!in_array('name', $userColumns, true)) {
            $userAdds[] = 'ADD COLUMN name VARCHAR(140) NULL AFTER username';
        }
        if (!in_array('phone', $userColumns, true)) {
            $userAdds[] = 'ADD COLUMN phone VARCHAR(20) NULL AFTER email';
        }
        if (!in_array('updated_at', $userColumns, true)) {
            $userAdds[] = 'ADD COLUMN updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at';
        }
        if ($userAdds !== []) {
            $db->exec('ALTER TABLE users ' . implode(', ', $userAdds));
        }

        $orderColumns = array_column($db->query('SHOW COLUMNS FROM orders')->fetchAll(\PDO::FETCH_ASSOC), 'Field');
        if (!in_array('customer_id', $orderColumns, true)) {
            // users.id is a signed INT in the existing Nemesis migration; the
            // referencing column must use the same signed type in MySQL.
            $db->exec('ALTER TABLE orders ADD COLUMN customer_id INT NULL AFTER idempotency_key');
        }

        $indexes = array_column($db->query('SHOW INDEX FROM orders')->fetchAll(\PDO::FETCH_ASSOC), 'Key_name');
        if (!in_array('orders_customer_created_index', $indexes, true)) {
            $db->exec('ALTER TABLE orders ADD INDEX orders_customer_created_index (customer_id, created_at)');
        }

        $foreignKey = $db->query(<<<'SQL'
SELECT CONSTRAINT_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'orders'
  AND CONSTRAINT_NAME = 'orders_customer_id_foreign'
LIMIT 1
SQL)->fetchColumn();
        if (!$foreignKey) {
            $db->exec(<<<'SQL'
ALTER TABLE orders
    ADD CONSTRAINT orders_customer_id_foreign
        FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE SET NULL
SQL);
        }
    }

    public function down(): void
    {
        $db = Database::connect();
        $db->exec('ALTER TABLE orders DROP FOREIGN KEY orders_customer_id_foreign, DROP INDEX orders_customer_created_index, DROP COLUMN customer_id');
        $db->exec('ALTER TABLE users DROP COLUMN updated_at, DROP COLUMN phone, DROP COLUMN name');
    }
}

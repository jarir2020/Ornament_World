<?php

declare(strict_types=1);

use Nemesis\Core\Database;
use Nemesis\Database\Migration;

/**
 * Prevents a browser retry or lost response from creating a second order.
 * The raw client key is never persisted; CheckoutService stores its digest.
 */
class add_checkout_idempotency extends Migration
{
    public function up(): void
    {
        Database::connect()->exec(<<<'SQL'
ALTER TABLE orders
    ADD COLUMN idempotency_key CHAR(64) NULL AFTER reference,
    ADD UNIQUE KEY orders_idempotency_key_unique (idempotency_key)
SQL);
    }

    public function down(): void
    {
        Database::connect()->exec('ALTER TABLE orders DROP INDEX orders_idempotency_key_unique, DROP COLUMN idempotency_key');
    }
}

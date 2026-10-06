<?php

declare(strict_types=1);

use Nemesis\Core\Database;
use Nemesis\Database\Migration;

/**
 * Phase 4 order operations.
 *
 * Checkout reserves stock by decrementing the active variant/product balance.
 * These fields make that reservation reversible exactly once when staff
 * cancels or rejects an order.
 */
class create_order_operations extends Migration
{
    public function up(): void
    {
        $db = Database::connect();
        $db->exec(<<<'SQL'
ALTER TABLE orders
    ADD COLUMN confirmed_at DATETIME NULL AFTER total,
    ADD COLUMN cancelled_at DATETIME NULL AFTER confirmed_at,
    ADD COLUMN cancellation_reason VARCHAR(500) NULL AFTER cancelled_at
SQL);
        $db->exec(<<<'SQL'
ALTER TABLE order_items
    ADD COLUMN stock_released_at DATETIME NULL AFTER line_total,
    ADD COLUMN stock_release_reason VARCHAR(120) NULL AFTER stock_released_at
SQL);
        $db->exec(<<<'SQL'
ALTER TABLE fraud_flags
    ADD COLUMN review_note VARCHAR(500) NULL AFTER resolution,
    ADD COLUMN reviewed_by INT UNSIGNED NULL AFTER review_note
SQL);
    }

    public function down(): void
    {
        $db = Database::connect();
        $db->exec('ALTER TABLE fraud_flags DROP COLUMN reviewed_by, DROP COLUMN review_note');
        $db->exec('ALTER TABLE order_items DROP COLUMN stock_release_reason, DROP COLUMN stock_released_at');
        $db->exec('ALTER TABLE orders DROP COLUMN cancellation_reason, DROP COLUMN cancelled_at, DROP COLUMN confirmed_at');
    }
}

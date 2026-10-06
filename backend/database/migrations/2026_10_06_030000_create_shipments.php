<?php

declare(strict_types=1);

use Nemesis\Core\Database;
use Nemesis\Database\Migration;

/**
 * Phase 5 courier shipment persistence.
 *
 * Request payloads are deliberately not stored because they contain customer
 * data. Attempts keep only the provider outcome needed for safe recovery.
 */
class create_shipments extends Migration
{
    public function up(): void
    {
        $db = Database::connect();
        $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS shipments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL UNIQUE,
    provider VARCHAR(40) NOT NULL DEFAULT 'pathao',
    idempotency_key CHAR(64) NOT NULL UNIQUE,
    status VARCHAR(40) NOT NULL DEFAULT 'pending',
    external_id VARCHAR(120) NULL,
    merchant_order_id VARCHAR(80) NULL,
    attempt_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
    last_http_status SMALLINT UNSIGNED NULL,
    last_retryable TINYINT(1) NOT NULL DEFAULT 0,
    last_error VARCHAR(500) NULL,
    manual_reference VARCHAR(120) NULL,
    manual_note VARCHAR(500) NULL,
    manual_recorded_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT shipments_order_id_foreign
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    INDEX shipments_status_updated_index (status, updated_at),
    INDEX shipments_external_id_index (external_id)
) ENGINE=INNODB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS shipment_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    shipment_id BIGINT UNSIGNED NOT NULL,
    attempt_no TINYINT UNSIGNED NOT NULL,
    outcome VARCHAR(30) NOT NULL,
    http_status SMALLINT UNSIGNED NULL,
    external_id VARCHAR(120) NULL,
    sanitized_error VARCHAR(500) NULL,
    actor_user_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT shipment_attempts_shipment_id_foreign
        FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE CASCADE,
    INDEX shipment_attempts_shipment_created_index (shipment_id, created_at)
) ENGINE=INNODB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        $db = Database::connect();
        $db->exec('SET FOREIGN_KEY_CHECKS=0');
        $db->exec('DROP TABLE IF EXISTS shipment_attempts');
        $db->exec('DROP TABLE IF EXISTS shipments');
        $db->exec('SET FOREIGN_KEY_CHECKS=1');
    }
}

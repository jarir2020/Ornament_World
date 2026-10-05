<?php

declare(strict_types=1);

use Nemesis\Core\Database;
use Nemesis\Database\Migration;

/**
 * Phase 2 catalog foundation.
 *
 * Prices are stored as decimal BDT values at the catalog boundary. Checkout
 * will still re-read the current price and stock inside a transaction.
 */
class create_catalog_tables extends Migration
{
    public function up(): void
    {
        $db = Database::connect();

        $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL UNIQUE,
    description TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=INNODB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NULL,
    name VARCHAR(180) NOT NULL,
    slug VARCHAR(200) NOT NULL UNIQUE,
    short_description VARCHAR(255) NULL,
    description TEXT NULL,
    base_price DECIMAL(10,2) NOT NULL,
    compare_at_price DECIMAL(10,2) NULL,
    stock_qty INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT products_category_id_foreign
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX products_category_active_index (category_id, is_active),
    INDEX products_featured_index (is_featured, sort_order)
) ENGINE=INNODB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS product_variants (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    sku VARCHAR(80) NOT NULL UNIQUE,
    price DECIMAL(10,2) NOT NULL,
    compare_at_price DECIMAL(10,2) NULL,
    stock_qty INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT product_variants_product_id_foreign
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX product_variants_product_active_index (product_id, is_active)
) ENGINE=INNODB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS product_images (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NOT NULL,
    variant_id INT UNSIGNED NULL,
    image_url VARCHAR(2048) NOT NULL,
    alt_text VARCHAR(180) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT product_images_product_id_foreign
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    CONSTRAINT product_images_variant_id_foreign
        FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE SET NULL,
    INDEX product_images_product_order_index (product_id, sort_order)
) ENGINE=INNODB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS discounts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id INT UNSIGNED NULL,
    variant_id INT UNSIGNED NULL,
    code VARCHAR(80) NULL,
    discount_type VARCHAR(20) NOT NULL,
    value DECIMAL(10,2) NOT NULL,
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT discounts_product_id_foreign
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    CONSTRAINT discounts_variant_id_foreign
        FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE CASCADE,
    INDEX discounts_product_active_index (product_id, is_active),
    INDEX discounts_variant_active_index (variant_id, is_active)
) ENGINE=INNODB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function down(): void
    {
        $db = Database::connect();
        $db->exec('SET FOREIGN_KEY_CHECKS=0');
        $db->exec('DROP TABLE IF EXISTS discounts');
        $db->exec('DROP TABLE IF EXISTS product_images');
        $db->exec('DROP TABLE IF EXISTS product_variants');
        $db->exec('DROP TABLE IF EXISTS products');
        $db->exec('DROP TABLE IF EXISTS categories');
        $db->exec('SET FOREIGN_KEY_CHECKS=1');
    }
}

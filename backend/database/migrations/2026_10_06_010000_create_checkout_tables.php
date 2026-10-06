<?php

declare(strict_types=1);

use Nemesis\Core\Database;
use Nemesis\Database\Migration;

/**
 * Phase 3 checkout and order foundation.
 *
 * The location import intentionally reads the committed source file from
 * jarir-in-atl/BangladeshLocations so checkout validates against controlled
 * server data instead of trusting district labels sent by a browser.
 */
class create_checkout_tables extends Migration
{
    private const LOCATION_SOURCE = 'https://github.com/jarir-in-atl/BangladeshLocations/blob/main/bangladesh_locations.json';

    public function up(): void
    {
        $db = Database::connect();

        $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS bangladesh_locations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    district VARCHAR(120) NOT NULL,
    subdistrict VARCHAR(120) NOT NULL,
    postoffice VARCHAR(180) NOT NULL,
    postcode VARCHAR(20) NOT NULL,
    UNIQUE KEY bangladesh_locations_unique (district, subdistrict, postoffice, postcode),
    INDEX bangladesh_locations_district_index (district),
    INDEX bangladesh_locations_subdistrict_index (district, subdistrict)
) ENGINE=INNODB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS delivery_zones (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(80) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    charge DECIMAL(10,2) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=INNODB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS delivery_zone_districts (
    zone_id INT UNSIGNED NOT NULL,
    district VARCHAR(120) NOT NULL,
    PRIMARY KEY (zone_id, district),
    CONSTRAINT delivery_zone_districts_zone_id_foreign
        FOREIGN KEY (zone_id) REFERENCES delivery_zones(id) ON DELETE CASCADE,
    INDEX delivery_zone_districts_district_index (district)
) ENGINE=INNODB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference VARCHAR(40) NOT NULL UNIQUE,
    status VARCHAR(40) NOT NULL DEFAULT 'new_order',
    shipment_status VARCHAR(60) NOT NULL DEFAULT 'not_ready',
    review_status VARCHAR(30) NOT NULL DEFAULT 'pending',
    customer_name VARCHAR(140) NOT NULL,
    customer_phone VARCHAR(20) NOT NULL,
    customer_email VARCHAR(180) NULL,
    delivery_address TEXT NOT NULL,
    district VARCHAR(120) NOT NULL,
    subdistrict VARCHAR(120) NOT NULL,
    postoffice VARCHAR(180) NULL,
    postcode VARCHAR(20) NULL,
    address_hash CHAR(64) NOT NULL,
    delivery_zone_slug VARCHAR(80) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    discount_total DECIMAL(10,2) NOT NULL DEFAULT 0,
    delivery_charge DECIMAL(10,2) NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    risk_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
    is_suspicious TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX orders_phone_created_index (customer_phone, created_at),
    INDEX orders_address_created_index (address_hash, created_at),
    INDEX orders_status_created_index (status, created_at)
) ENGINE=INNODB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS order_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NULL,
    variant_id INT UNSIGNED NULL,
    product_name VARCHAR(180) NOT NULL,
    variant_name VARCHAR(120) NULL,
    sku VARCHAR(80) NULL,
    quantity INT UNSIGNED NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    line_subtotal DECIMAL(10,2) NOT NULL,
    line_discount DECIMAL(10,2) NOT NULL DEFAULT 0,
    line_total DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT order_items_order_id_foreign
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT order_items_product_id_foreign
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    CONSTRAINT order_items_variant_id_foreign
        FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE SET NULL,
    INDEX order_items_order_index (order_id)
) ENGINE=INNODB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS order_status_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    from_status VARCHAR(40) NULL,
    to_status VARCHAR(40) NOT NULL,
    note VARCHAR(500) NULL,
    actor_user_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT order_status_history_order_id_foreign
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    INDEX order_status_history_order_created_index (order_id, created_at)
) ENGINE=INNODB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS fraud_flags (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(80) NOT NULL,
    reason VARCHAR(500) NOT NULL,
    risk_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
    resolution VARCHAR(30) NOT NULL DEFAULT 'pending',
    reviewed_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fraud_flags_order_id_foreign
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    INDEX fraud_flags_order_resolution_index (order_id, resolution)
) ENGINE=INNODB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->importLocations($db);
        $this->seedDeliveryZones($db);
    }

    public function down(): void
    {
        $db = Database::connect();
        $db->exec('SET FOREIGN_KEY_CHECKS=0');
        $db->exec('DROP TABLE IF EXISTS fraud_flags');
        $db->exec('DROP TABLE IF EXISTS order_status_history');
        $db->exec('DROP TABLE IF EXISTS order_items');
        $db->exec('DROP TABLE IF EXISTS orders');
        $db->exec('DROP TABLE IF EXISTS delivery_zone_districts');
        $db->exec('DROP TABLE IF EXISTS delivery_zones');
        $db->exec('DROP TABLE IF EXISTS bangladesh_locations');
        $db->exec('SET FOREIGN_KEY_CHECKS=1');
    }

    private function importLocations(\PDO $db): void
    {
        $sourcePath = dirname(__DIR__) . '/data/bangladesh_locations.json';
        $records = json_decode((string) file_get_contents($sourcePath), true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($records) || $records === []) {
            throw new \RuntimeException('Bangladesh location source is empty or invalid.');
        }

        $insert = $db->prepare(<<<'SQL'
INSERT IGNORE INTO bangladesh_locations (district, subdistrict, postoffice, postcode)
VALUES (:district, :subdistrict, :postoffice, :postcode)
SQL);
        $districts = [];

        foreach ($records as $record) {
            if (!is_array($record)) {
                throw new \RuntimeException('Bangladesh location source contains an invalid record.');
            }

            $district = trim((string) ($record['district'] ?? ''));
            $subdistrict = trim((string) ($record['subdistrict'] ?? ''));
            $postoffice = trim((string) ($record['postoffice'] ?? ''));
            $postcode = trim((string) ($record['postcode'] ?? ''));

            if ($district === '' || $subdistrict === '' || $postoffice === '' || $postcode === '') {
                throw new \RuntimeException('Bangladesh location source contains incomplete address data.');
            }

            $insert->execute([
                'district' => $district,
                'subdistrict' => $subdistrict,
                'postoffice' => $postoffice,
                'postcode' => $postcode,
            ]);
            $districts[$district] = true;
        }

        if (count($districts) !== 64 || !isset($districts['Dhaka'])) {
            throw new \RuntimeException(sprintf(
                'Expected 64 Bangladesh districts from %s; found %d.',
                self::LOCATION_SOURCE,
                count($districts)
            ));
        }
    }

    private function seedDeliveryZones(\PDO $db): void
    {
        $zones = [
            ['slug' => 'inside-dhaka', 'name' => 'Inside Dhaka', 'charge' => 60],
            ['slug' => 'outside-dhaka', 'name' => 'Outside Dhaka', 'charge' => 120],
        ];
        $zoneIds = [];
        $insertZone = $db->prepare('INSERT INTO delivery_zones (slug, name, charge) VALUES (:slug, :name, :charge)');
        foreach ($zones as $zone) {
            $insertZone->execute($zone);
            $zoneIds[$zone['slug']] = (int) $db->lastInsertId();
        }

        $districts = $db->query('SELECT DISTINCT district FROM bangladesh_locations ORDER BY district ASC')->fetchAll(\PDO::FETCH_COLUMN);
        $insertDistrict = $db->prepare('INSERT INTO delivery_zone_districts (zone_id, district) VALUES (:zone_id, :district)');
        foreach ($districts as $district) {
            $insertDistrict->execute([
                'zone_id' => $zoneIds[$district === 'Dhaka' ? 'inside-dhaka' : 'outside-dhaka'],
                'district' => $district,
            ]);
        }
    }
}

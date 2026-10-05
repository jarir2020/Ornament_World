<?php

declare(strict_types=1);

use Nemesis\Core\Database;
use Nemesis\Database\Seeder;

/**
 * Safe, repeatable demo catalog data for local development only.
 */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $db = Database::connect();

        $categories = [
            ['name' => 'Bracelets', 'slug' => 'bracelets', 'sort_order' => 1],
            ['name' => 'Chains', 'slug' => 'chains', 'sort_order' => 2],
            ['name' => 'Rings', 'slug' => 'rings', 'sort_order' => 3],
            ['name' => 'Lockets', 'slug' => 'lockets', 'sort_order' => 4],
        ];

        $categoryIds = [];
        $findCategory = $db->prepare('SELECT id FROM categories WHERE slug = :slug LIMIT 1');
        $insertCategory = $db->prepare(
            'INSERT INTO categories (name, slug, sort_order) VALUES (:name, :slug, :sort_order)'
        );

        foreach ($categories as $category) {
            $findCategory->execute(['slug' => $category['slug']]);
            $id = $findCategory->fetchColumn();

            if (!$id) {
                $insertCategory->execute($category);
                $id = $db->lastInsertId();
            }

            $categoryIds[$category['slug']] = (int) $id;
        }

        $products = [
            [
                'category' => 'rings',
                'name' => 'Onyx Signet Ring',
                'slug' => 'onyx-signet-ring',
                'short_description' => 'A clean signet silhouette with a deep onyx centre.',
                'description' => 'Demo catalog item for developing the Ornaments World storefront.',
                'base_price' => 2850,
                'compare_at_price' => 3200,
                'stock_qty' => 12,
                'is_featured' => 1,
                'sku' => 'OW-RING-ONYX',
                'variant_name' => 'Standard size',
            ],
            [
                'category' => 'bracelets',
                'name' => 'Midnight Link Bracelet',
                'slug' => 'midnight-link-bracelet',
                'short_description' => 'A polished link bracelet with an understated finish.',
                'description' => 'Demo catalog item for developing the Ornaments World storefront.',
                'base_price' => 3200,
                'compare_at_price' => 3600,
                'stock_qty' => 8,
                'is_featured' => 1,
                'sku' => 'OW-BRACELET-MIDNIGHT',
                'variant_name' => '21 cm',
                'discount_percent' => 10,
            ],
            [
                'category' => 'chains',
                'name' => 'Minimal Chain 3mm',
                'slug' => 'minimal-chain-3mm',
                'short_description' => 'A versatile chain designed to layer or wear alone.',
                'description' => 'Demo catalog item for developing the Ornaments World storefront.',
                'base_price' => 4100,
                'compare_at_price' => null,
                'stock_qty' => 7,
                'is_featured' => 1,
                'sku' => 'OW-CHAIN-3MM',
                'variant_name' => '22 inch',
            ],
            [
                'category' => 'lockets',
                'name' => 'Classic Crest Locket',
                'slug' => 'classic-crest-locket',
                'short_description' => 'A compact crest locket for a personal keepsake.',
                'description' => 'Demo catalog item for developing the Ornaments World storefront.',
                'base_price' => 2950,
                'compare_at_price' => null,
                'stock_qty' => 0,
                'is_featured' => 0,
                'sku' => 'OW-LOCKET-CREST',
                'variant_name' => 'Standard',
            ],
        ];

        $findProduct = $db->prepare('SELECT id FROM products WHERE slug = :slug LIMIT 1');
        $insertProduct = $db->prepare(<<<'SQL'
INSERT INTO products
    (category_id, name, slug, short_description, description, base_price, compare_at_price, stock_qty, is_featured)
VALUES
    (:category_id, :name, :slug, :short_description, :description, :base_price, :compare_at_price, :stock_qty, :is_featured)
SQL);
        $findVariant = $db->prepare('SELECT id FROM product_variants WHERE sku = :sku LIMIT 1');
        $insertVariant = $db->prepare(<<<'SQL'
INSERT INTO product_variants
    (product_id, name, sku, price, compare_at_price, stock_qty)
VALUES
    (:product_id, :name, :sku, :price, :compare_at_price, :stock_qty)
SQL);
        $findDiscount = $db->prepare(
            "SELECT id FROM discounts WHERE product_id = :product_id AND discount_type = 'percent' LIMIT 1"
        );
        $insertDiscount = $db->prepare(<<<'SQL'
INSERT INTO discounts (product_id, discount_type, value)
VALUES (:product_id, 'percent', :value)
SQL);

        foreach ($products as $product) {
            $findProduct->execute(['slug' => $product['slug']]);
            $productId = $findProduct->fetchColumn();

            if (!$productId) {
                $insertProduct->execute([
                    'category_id' => $categoryIds[$product['category']],
                    'name' => $product['name'],
                    'slug' => $product['slug'],
                    'short_description' => $product['short_description'],
                    'description' => $product['description'],
                    'base_price' => $product['base_price'],
                    'compare_at_price' => $product['compare_at_price'],
                    'stock_qty' => $product['stock_qty'],
                    'is_featured' => $product['is_featured'],
                ]);
                $productId = $db->lastInsertId();
            }

            $findVariant->execute(['sku' => $product['sku']]);
            if (!$findVariant->fetchColumn()) {
                $insertVariant->execute([
                    'product_id' => $productId,
                    'name' => $product['variant_name'],
                    'sku' => $product['sku'],
                    'price' => $product['base_price'],
                    'compare_at_price' => $product['compare_at_price'],
                    'stock_qty' => $product['stock_qty'],
                ]);
            }

            if (isset($product['discount_percent'])) {
                $findDiscount->execute(['product_id' => $productId]);
                if (!$findDiscount->fetchColumn()) {
                    $insertDiscount->execute([
                        'product_id' => $productId,
                        'value' => $product['discount_percent'],
                    ]);
                }
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use Nemesis\Core\Database;

/**
 * Read-side catalog service for server-rendered storefront props.
 *
 * Product prices, discounts, and stock are read from MySQL here. Checkout
 * must revalidate those values again when order creation is implemented.
 */
final class CatalogService
{
    public function storefront(string $categorySlug = '', string $search = ''): array
    {
        return [
            'categories' => $this->categories(),
            'products' => $this->products($categorySlug, $search),
            'filters' => [
                'category' => $categorySlug,
                'search' => $search,
            ],
        ];
    }

    public function categories(): array
    {
        return Database::view(
            'SELECT id, name, slug FROM categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC'
        );
    }

    public function products(string $categorySlug = '', string $search = '', int $limit = 12): array
    {
        $where = [
            'p.is_active = 1',
            '(c.id IS NULL OR c.is_active = 1)',
        ];
        $params = [];

        if ($categorySlug !== '') {
            $where[] = 'c.slug = :category_slug';
            $params['category_slug'] = $categorySlug;
        }

        if ($search !== '') {
            $where[] = '(p.name LIKE :search_name OR p.short_description LIKE :search_description)';
            $params['search_name'] = '%' . $search . '%';
            $params['search_description'] = '%' . $search . '%';
        }

        $params['limit'] = max(1, min($limit, 48));

        $rows = Database::view(<<<'SQL'
SELECT
    p.id,
    p.name,
    p.slug,
    p.short_description,
    p.base_price,
    p.compare_at_price,
    p.stock_qty,
    c.name AS category_name,
    c.slug AS category_slug,
    (
        SELECT v.price
        FROM product_variants v
        WHERE v.product_id = p.id AND v.is_active = 1
        ORDER BY v.sort_order ASC, v.id ASC
        LIMIT 1
    ) AS variant_price,
    (
        SELECT v.id
        FROM product_variants v
        WHERE v.product_id = p.id AND v.is_active = 1
        ORDER BY v.sort_order ASC, v.id ASC
        LIMIT 1
    ) AS variant_id,
    (
        SELECT v.name
        FROM product_variants v
        WHERE v.product_id = p.id AND v.is_active = 1
        ORDER BY v.sort_order ASC, v.id ASC
        LIMIT 1
    ) AS variant_name,
    (
        SELECT v.sku
        FROM product_variants v
        WHERE v.product_id = p.id AND v.is_active = 1
        ORDER BY v.sort_order ASC, v.id ASC
        LIMIT 1
    ) AS variant_sku,
    (
        SELECT SUM(v.stock_qty)
        FROM product_variants v
        WHERE v.product_id = p.id AND v.is_active = 1
    ) AS variant_stock,
    (
        SELECT i.image_url
        FROM product_images i
        WHERE i.product_id = p.id
        ORDER BY i.is_primary DESC, i.sort_order ASC, i.id ASC
        LIMIT 1
    ) AS image_url
FROM products p
LEFT JOIN categories c ON c.id = p.category_id
WHERE
SQL . ' ' . implode(' AND ', $where) . <<<'SQL'
 ORDER BY p.is_featured DESC, p.sort_order ASC, p.created_at DESC
LIMIT :limit
SQL, $params);

        $discounts = $this->activeProductDiscounts();

        return array_map(
            fn(array $row): array => $this->presentProduct($row, $discounts[(int) $row['id']] ?? null),
            $rows
        );
    }

    public function productBySlug(string $slug): ?array
    {
        $rows = Database::view(<<<'SQL'
SELECT
    p.id,
    p.name,
    p.slug,
    p.short_description,
    p.description,
    p.base_price,
    p.compare_at_price,
    p.stock_qty,
    c.name AS category_name,
    c.slug AS category_slug
FROM products p
LEFT JOIN categories c ON c.id = p.category_id
WHERE p.slug = :slug AND p.is_active = 1
LIMIT 1
SQL, ['slug' => $slug]);

        if ($rows === []) {
            return null;
        }

        $product = $rows[0];
        $productId = (int) $product['id'];
        $product['variants'] = Database::view(
            'SELECT id, name, sku, price, compare_at_price, stock_qty FROM product_variants WHERE product_id = :product_id AND is_active = 1 ORDER BY sort_order ASC, id ASC',
            ['product_id' => $productId]
        );
        $product['images'] = Database::view(
            'SELECT id, image_url, alt_text, sort_order, is_primary FROM product_images WHERE product_id = :product_id ORDER BY is_primary DESC, sort_order ASC, id ASC',
            ['product_id' => $productId]
        );

        $discounts = $this->activeProductDiscounts();
        return $this->presentProduct($product, $discounts[$productId] ?? null, true);
    }

    private function activeProductDiscounts(): array
    {
        $rows = Database::view(<<<'SQL'
SELECT product_id, discount_type, value
FROM discounts
WHERE product_id IS NOT NULL
  AND variant_id IS NULL
  AND is_active = 1
  AND (starts_at IS NULL OR starts_at <= NOW())
  AND (ends_at IS NULL OR ends_at >= NOW())
ORDER BY id DESC
SQL);

        $discounts = [];
        foreach ($rows as $row) {
            $discounts[(int) $row['product_id']] ??= $row;
        }

        return $discounts;
    }

    private function presentProduct(array $row, ?array $discount = null, bool $detail = false): array
    {
        $basePrice = (float) ($row['variant_price'] ?? $row['base_price'] ?? 0);
        $discountedPrice = $basePrice;

        if ($discount !== null) {
            $discountedPrice = $discount['discount_type'] === 'percent'
                ? $basePrice - ($basePrice * ((float) $discount['value'] / 100))
                : $basePrice - (float) $discount['value'];
            $discountedPrice = max(0, round($discountedPrice, 2));
        }

        $stock = array_key_exists('variant_stock', $row) && $row['variant_stock'] !== null
            ? (int) $row['variant_stock']
            : (int) ($row['stock_qty'] ?? 0);

        $defaultVariant = null;
        if (array_key_exists('variant_id', $row) && $row['variant_id'] !== null) {
            $defaultVariant = [
                'id' => (int) $row['variant_id'],
                'name' => $row['variant_name'] ?? 'Standard',
                'sku' => $row['variant_sku'] ?? '',
            ];
        } elseif (!empty($row['variants'][0])) {
            $defaultVariant = [
                'id' => (int) $row['variants'][0]['id'],
                'name' => $row['variants'][0]['name'],
                'sku' => $row['variants'][0]['sku'],
            ];
        }

        $presented = [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'slug' => $row['slug'],
            'shortDescription' => $row['short_description'] ?? '',
            'description' => $row['description'] ?? '',
            'category' => [
                'name' => $row['category_name'] ?? 'Uncategorized',
                'slug' => $row['category_slug'] ?? '',
            ],
            'price' => $discountedPrice,
            'compareAtPrice' => $row['compare_at_price'] !== null ? (float) $row['compare_at_price'] : null,
            'discount' => $discount !== null ? [
                'type' => $discount['discount_type'],
                'value' => (float) $discount['value'],
            ] : null,
            'stockQty' => $stock,
            'inStock' => $stock > 0,
            'imageUrl' => $row['image_url'] ?? ($row['images'][0]['image_url'] ?? null),
            'defaultVariant' => $defaultVariant,
        ];

        if ($detail) {
            $presented['variants'] = array_map(static function (array $variant) use ($discount): array {
                $price = (float) $variant['price'];
                $discountedPrice = $price;
                if ($discount !== null) {
                    $discountedPrice = $discount['discount_type'] === 'percent'
                        ? $price - ($price * ((float) $discount['value'] / 100))
                        : $price - (float) $discount['value'];
                    $discountedPrice = max(0, round($discountedPrice, 2));
                }

                return [
                    'id' => (int) $variant['id'],
                    'name' => $variant['name'],
                    'sku' => $variant['sku'],
                    'price' => $discountedPrice,
                    'originalPrice' => $price,
                    'compareAtPrice' => $variant['compare_at_price'] !== null ? (float) $variant['compare_at_price'] : null,
                    'stockQty' => (int) $variant['stock_qty'],
                    'inStock' => (int) $variant['stock_qty'] > 0,
                ];
            }, $row['variants'] ?? []);
            $presented['images'] = $row['images'] ?? [];
        }

        return $presented;
    }
}

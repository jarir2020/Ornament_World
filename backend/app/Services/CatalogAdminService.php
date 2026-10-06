<?php

declare(strict_types=1);

namespace App\Services;

use Nemesis\Core\Database;

/**
 * Protected catalog write operations for admin workflows.
 *
 * Product deletion is deliberately a soft deactivation so existing order
 * references remain possible once checkout and order tables are introduced.
 */
final class CatalogAdminService
{
    public function snapshot(): array
    {
        $categories = Database::view(
            'SELECT id, name, slug, is_active, sort_order FROM categories ORDER BY sort_order ASC, name ASC'
        );
        $products = Database::view(<<<'SQL'
SELECT
    p.id,
    p.name,
    p.slug,
    p.base_price,
    p.compare_at_price,
    p.stock_qty,
    p.is_active,
    p.is_featured,
    c.name AS category_name,
    (
        SELECT COUNT(*)
        FROM product_variants v
        WHERE v.product_id = p.id AND v.is_active = 1
    ) AS variant_count
FROM products p
LEFT JOIN categories c ON c.id = p.category_id
ORDER BY p.created_at DESC, p.id DESC
SQL);

        return [
            'categories' => array_map(static fn (array $category): array => array_merge($category, [
                'id' => (int) $category['id'],
                'is_active' => (bool) $category['is_active'],
                'sort_order' => (int) $category['sort_order'],
            ]), $categories),
            'products' => array_map(static fn (array $product): array => array_merge($product, [
                'id' => (int) $product['id'],
                'base_price' => (float) $product['base_price'],
                'compare_at_price' => $product['compare_at_price'] === null ? null : (float) $product['compare_at_price'],
                'stock_qty' => (int) $product['stock_qty'],
                'is_active' => (bool) $product['is_active'],
                'is_featured' => (bool) $product['is_featured'],
                'variant_count' => (int) $product['variant_count'],
            ]), $products),
        ];
    }

    public function createCategory(array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $slug = $this->slug((string) ($input['slug'] ?? $name));

        if ($name === '' || mb_strlen($name) > 120) {
            throw new \InvalidArgumentException('Category name is required and must be 120 characters or fewer.');
        }

        $this->assertSlugAvailable('categories', $slug);
        $statement = Database::connection()->prepare(
            'INSERT INTO categories (name, slug, description, sort_order) VALUES (:name, :slug, :description, :sort_order)'
        );
        $statement->execute([
            'name' => $name,
            'slug' => $slug,
            'description' => trim((string) ($input['description'] ?? '')) ?: null,
            'sort_order' => max(0, (int) ($input['sort_order'] ?? 0)),
        ]);

        return ['id' => (int) Database::connection()->lastInsertId(), 'name' => $name, 'slug' => $slug];
    }

    public function createProduct(array $input): array
    {
        $data = $this->validatedProduct($input);
        $db = Database::connection();
        $this->assertCategory($data['category_id']);
        $this->assertSlugAvailable('products', $data['slug']);

        $db->beginTransaction();
        try {
            $product = $db->prepare(<<<'SQL'
INSERT INTO products
    (category_id, name, slug, short_description, description, base_price, compare_at_price, stock_qty, is_active, is_featured, sort_order)
VALUES
    (:category_id, :name, :slug, :short_description, :description, :base_price, :compare_at_price, :stock_qty, :is_active, :is_featured, :sort_order)
SQL);
            $product->execute($data);
            $productId = (int) $db->lastInsertId();

            $variant = $db->prepare(<<<'SQL'
INSERT INTO product_variants
    (product_id, name, sku, price, compare_at_price, stock_qty)
VALUES
    (:product_id, :name, :sku, :price, :compare_at_price, :stock_qty)
SQL);
            $variant->execute([
                'product_id' => $productId,
                'name' => trim((string) ($input['variant_name'] ?? 'Standard')) ?: 'Standard',
                'sku' => $this->uniqueSku((string) ($input['sku'] ?? ''), $productId),
                'price' => $data['base_price'],
                'compare_at_price' => $data['compare_at_price'],
                'stock_qty' => $data['stock_qty'],
            ]);

            $db->commit();
        } catch (\Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }

        return $this->product($productId);
    }

    public function updateProduct(int $id, array $input): array
    {
        $data = $this->validatedProduct($input);
        $db = Database::connection();
        $existing = $this->product($id);

        if ($existing === null) {
            throw new \RuntimeException('Product not found.');
        }

        $this->assertCategory($data['category_id']);
        $this->assertSlugAvailable('products', $data['slug'], $id);

        $db->beginTransaction();
        try {
            $update = $db->prepare(<<<'SQL'
UPDATE products
SET category_id = :category_id,
    name = :name,
    slug = :slug,
    short_description = :short_description,
    description = :description,
    base_price = :base_price,
    compare_at_price = :compare_at_price,
    stock_qty = :stock_qty,
    is_active = :is_active,
    is_featured = :is_featured,
    sort_order = :sort_order
WHERE id = :id
SQL);
            $update->execute($data + ['id' => $id]);

            $variantId = $this->defaultVariantId($id);
            if ($variantId !== null) {
                $variant = $db->prepare(<<<'SQL'
UPDATE product_variants
SET price = :price, compare_at_price = :compare_at_price, stock_qty = :stock_qty
WHERE id = :id
SQL);
                $variant->execute([
                    'price' => $data['base_price'],
                    'compare_at_price' => $data['compare_at_price'],
                    'stock_qty' => $data['stock_qty'],
                    'id' => $variantId,
                ]);
            }

            $db->commit();
        } catch (\Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }

        return $this->product($id);
    }

    public function setActive(int $id, bool $active): array
    {
        $statement = Database::connection()->prepare('UPDATE products SET is_active = :is_active WHERE id = :id');
        $statement->execute(['is_active' => $active ? 1 : 0, 'id' => $id]);

        if ($statement->rowCount() === 0 && $this->product($id) === null) {
            throw new \RuntimeException('Product not found.');
        }

        return $this->product($id);
    }

    public function product(int $id): ?array
    {
        $rows = Database::view(
            'SELECT id, category_id, name, slug, short_description, description, base_price, compare_at_price, stock_qty, is_active, is_featured, sort_order FROM products WHERE id = :id LIMIT 1',
            ['id' => $id]
        );

        return $rows[0] ?? null;
    }

    /** @return array<string, mixed> */
    private function validatedProduct(array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $slug = $this->slug((string) ($input['slug'] ?? $name));
        $price = (float) ($input['base_price'] ?? $input['price'] ?? -1);
        $compareAtPrice = ($input['compare_at_price'] ?? '') === '' || ($input['compare_at_price'] ?? null) === null
            ? null
            : (float) $input['compare_at_price'];

        if ($name === '' || mb_strlen($name) > 180) {
            throw new \InvalidArgumentException('Product name is required and must be 180 characters or fewer.');
        }
        if ($price < 0 || ($compareAtPrice !== null && $compareAtPrice < 0)) {
            throw new \InvalidArgumentException('Prices cannot be negative.');
        }

        return [
            'category_id' => max(1, (int) ($input['category_id'] ?? 0)),
            'name' => $name,
            'slug' => $slug,
            'short_description' => trim((string) ($input['short_description'] ?? '')) ?: null,
            'description' => trim((string) ($input['description'] ?? '')) ?: null,
            'base_price' => $price,
            'compare_at_price' => $compareAtPrice,
            'stock_qty' => max(0, (int) ($input['stock_qty'] ?? 0)),
            'is_active' => array_key_exists('is_active', $input) ? (!empty($input['is_active']) ? 1 : 0) : 1,
            'is_featured' => !empty($input['is_featured']) ? 1 : 0,
            'sort_order' => max(0, (int) ($input['sort_order'] ?? 0)),
        ];
    }

    private function assertCategory(int $id): void
    {
        $statement = Database::connection()->prepare('SELECT id FROM categories WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        if (!$statement->fetchColumn()) {
            throw new \InvalidArgumentException('A valid category is required.');
        }
    }

    private function assertSlugAvailable(string $table, string $slug, ?int $ignoreId = null): void
    {
        $sql = "SELECT id FROM {$table} WHERE slug = :slug";
        $params = ['slug' => $slug];
        if ($ignoreId !== null) {
            $sql .= ' AND id <> :ignore_id';
            $params['ignore_id'] = $ignoreId;
        }
        $sql .= ' LIMIT 1';
        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);
        if ($statement->fetchColumn()) {
            throw new \InvalidArgumentException('The slug is already in use.');
        }
    }

    private function defaultVariantId(int $productId): ?int
    {
        $rows = Database::view(
            'SELECT id FROM product_variants WHERE product_id = :product_id ORDER BY sort_order ASC, id ASC LIMIT 1',
            ['product_id' => $productId]
        );
        return isset($rows[0]['id']) ? (int) $rows[0]['id'] : null;
    }

    private function uniqueSku(string $requested, int $productId): string
    {
        $base = strtoupper(trim($requested));
        if ($base === '') {
            $base = 'OW-PRODUCT-' . $productId;
        }

        $sku = $base;
        $suffix = 1;
        $statement = Database::connection()->prepare('SELECT id FROM product_variants WHERE sku = :sku LIMIT 1');
        while (true) {
            $statement->execute(['sku' => $sku]);
            if (!$statement->fetchColumn()) {
                return $sku;
            }
            $sku = $base . '-' . $suffix++;
        }
    }

    private function slug(string $value): string
    {
        $slug = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $value), '-'));
        if ($slug === '') {
            throw new \InvalidArgumentException('A non-empty slug or name is required.');
        }
        return $slug;
    }
}

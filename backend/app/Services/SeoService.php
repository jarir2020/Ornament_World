<?php

declare(strict_types=1);

namespace App\Services;

use Nemesis\Core\Database;

/**
 * Public discovery metadata and crawl-file boundary.
 *
 * Only active public catalog records enter the sitemap. Customer, checkout,
 * order-success, and admin URLs are intentionally excluded.
 */
final class SeoService
{
    public function siteUrl(): string
    {
        $configured = trim((string) (getenv('APP_URL') ?: 'http://localhost'));
        if (!preg_match('#^https?://#i', $configured)) {
            return 'http://localhost';
        }

        return rtrim($configured, '/');
    }

    public function url(string $path): string
    {
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        return $this->siteUrl() . '/' . ltrim($path, '/');
    }

    public function robots(): string
    {
        return implode("\n", [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin',
            'Disallow: /checkout',
            'Disallow: /checkout/success/',
            'Sitemap: ' . $this->url('/sitemap.xml'),
            '',
        ]);
    }

    public function sitemap(): string
    {
        $urls = [
            ['path' => '/storefront', 'lastmod' => null],
            ['path' => '/help/delivery', 'lastmod' => null],
            ['path' => '/help/contact', 'lastmod' => null],
            ['path' => '/help/privacy', 'lastmod' => null],
        ];

        foreach (Database::view('SELECT slug, updated_at FROM categories WHERE is_active = 1 ORDER BY id ASC') as $category) {
            $urls[] = [
                'path' => '/storefront?category=' . rawurlencode((string) $category['slug']),
                'lastmod' => $category['updated_at'] ?? null,
            ];
        }

        foreach (Database::view('SELECT slug, updated_at FROM products WHERE is_active = 1 ORDER BY id ASC') as $product) {
            $urls[] = [
                'path' => '/storefront/product/' . rawurlencode((string) $product['slug']),
                'lastmod' => $product['updated_at'] ?? null,
            ];
        }

        $xml = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
        ];
        foreach ($urls as $entry) {
            $xml[] = '  <url>';
            $xml[] = '    <loc>' . htmlspecialchars($this->url($entry['path']), ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc>';
            if (!empty($entry['lastmod'])) {
                $xml[] = '    <lastmod>' . htmlspecialchars((string) $entry['lastmod'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</lastmod>';
            }
            $xml[] = '  </url>';
        }
        $xml[] = '</urlset>';

        return implode("\n", $xml) . "\n";
    }

    public function productStructuredData(array $product, string $canonical): array
    {
        $emptyImage = $this->url('/');
        $images = array_values(array_filter(array_map(
            fn (array $image): string => $this->url((string) ($image['image_url'] ?? '')),
            $product['images'] ?? []
        ), fn (string $image): bool => $image !== $emptyImage));
        if ($images === [] && !empty($product['imageUrl'])) {
            $images[] = $this->url((string) $product['imageUrl']);
        }

        $price = (float) ($product['price'] ?? 0);
        $sku = (string) ($product['defaultVariant']['sku'] ?? '');

        return [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => (string) ($product['name'] ?? ''),
            'description' => trim((string) ($product['description'] ?? $product['shortDescription'] ?? '')),
            'image' => $images,
            'sku' => $sku !== '' ? $sku : null,
            'category' => (string) ($product['category']['name'] ?? ''),
            'brand' => [
                '@type' => 'Brand',
                'name' => 'Ornaments World',
            ],
            'offers' => [
                '@type' => 'Offer',
                'url' => $canonical,
                'priceCurrency' => 'BDT',
                'price' => number_format($price, 2, '.', ''),
                'availability' => !empty($product['inStock'])
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
                'itemCondition' => 'https://schema.org/NewCondition',
            ],
        ];
    }
}

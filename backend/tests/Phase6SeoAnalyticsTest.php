<?php

declare(strict_types=1);

/**
 * Run with: php tests/Phase6SeoAnalyticsTest.php
 *
 * Verifies public crawl metadata and the provider-neutral event contract
 * without sending analytics or customer data to an external provider.
 */

require dirname(__DIR__) . '/index.php';

use App\Services\CatalogService;
use App\Services\SeoService;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$seo = new SeoService();
$robots = $seo->robots();
$sitemap = $seo->sitemap();
$assert(str_contains($robots, 'Sitemap: '), 'robots.txt does not advertise the sitemap.');
$assert(str_contains($robots, 'Disallow: /admin'), 'robots.txt does not protect admin paths.');
$assert(str_contains($robots, 'Disallow: /checkout'), 'robots.txt does not protect checkout paths.');
$assert(str_contains($sitemap, '<urlset '), 'Sitemap is missing its urlset root.');
$assert(str_contains($sitemap, '/storefront'), 'Sitemap is missing the storefront URL.');
$assert(!str_contains($sitemap, '/admin'), 'Sitemap must not expose admin URLs.');
$assert(!str_contains($sitemap, '/checkout'), 'Sitemap must not expose checkout URLs.');

$productRow = \Nemesis\Core\Database::view('SELECT slug FROM products WHERE is_active = 1 ORDER BY id ASC LIMIT 1')[0] ?? null;
$assert($productRow !== null, 'No active product is available for structured-data verification.');
$product = (new CatalogService())->productBySlug((string) $productRow['slug']);
$assert($product !== null, 'Active product could not be loaded for structured-data verification.');
$structured = $seo->productStructuredData($product, $seo->url('/storefront/product/' . $product['slug']));
$assert($structured['@type'] === 'Product', 'Product structured data has the wrong type.');
$assert(($structured['offers']['priceCurrency'] ?? null) === 'BDT', 'Product structured data is missing BDT currency.');
$assert(!array_key_exists('customer_phone', $structured), 'Product structured data contains customer data.');

$analyticsSource = (string) file_get_contents(dirname(__DIR__) . '/resources/js/svelte/analytics.js');
$assert(str_contains($analyticsSource, "ornaments:analytics"), 'Analytics event dispatch contract is missing.');
$assert(str_contains($analyticsSource, 'trackOnce'), 'Analytics purchase deduplication helper is missing.');
$assert(!str_contains($analyticsSource, 'customer_phone'), 'Analytics source contains a private customer field.');

echo 'phase6 seo/analytics test passed: robots=1, sitemap=1, product_schema=1, pii_boundary=1' . PHP_EOL;

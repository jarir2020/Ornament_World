<?php

declare(strict_types=1);

/**
 * PHP's development server serves files with extensions directly and skips
 * the front controller. Return existing assets as files; send every other
 * request through Nemesis so routes such as robots.txt and sitemap.xml work
 * locally just as they do behind Apache/Nginx.
 */
$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$file = __DIR__ . $path;

if ($path !== '/' && is_file($file)) {
    return false;
}

require __DIR__ . '/index.php';

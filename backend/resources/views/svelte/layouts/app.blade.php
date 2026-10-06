<?php
$seo = is_array($pageProps['seo'] ?? null) ? $pageProps['seo'] : [];
$seoTitle = (string) ($seo['title'] ?? 'Ornaments World');
$seoDescription = (string) ($seo['description'] ?? 'Refined men\'s jewellery from Ornaments World.');
$seoCanonical = (string) ($seo['canonical'] ?? '');
$seoRobots = (string) ($seo['robots'] ?? 'index,follow');
$seoType = (string) ($seo['type'] ?? 'website');
$seoImage = (string) ($seo['image'] ?? '');
$seoStructuredData = $seo['structuredData'] ?? null;
$escapeSeo = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $escapeSeo($seoTitle) ?></title>
    <meta name="description" content="<?= $escapeSeo($seoDescription) ?>">
    <meta name="robots" content="<?= $escapeSeo($seoRobots) ?>">
    <?php if ($seoCanonical !== ''): ?><link rel="canonical" href="<?= $escapeSeo($seoCanonical) ?>"><?php endif; ?>
    <meta property="og:site_name" content="Ornaments World">
    <meta property="og:title" content="<?= $escapeSeo($seoTitle) ?>">
    <meta property="og:description" content="<?= $escapeSeo($seoDescription) ?>">
    <meta property="og:type" content="<?= $escapeSeo($seoType) ?>">
    <?php if ($seoCanonical !== ''): ?><meta property="og:url" content="<?= $escapeSeo($seoCanonical) ?>"><?php endif; ?>
    <?php if ($seoImage !== ''): ?><meta property="og:image" content="<?= $escapeSeo($seoImage) ?>"><?php endif; ?>
    <meta name="twitter:card" content="<?= $seoImage !== '' ? 'summary_large_image' : 'summary' ?>">
    <meta name="twitter:title" content="<?= $escapeSeo($seoTitle) ?>">
    <meta name="twitter:description" content="<?= $escapeSeo($seoDescription) ?>">
    <?php if ($seoImage !== ''): ?><meta name="twitter:image" content="<?= $escapeSeo($seoImage) ?>"><?php endif; ?>
    <?php if (is_array($seoStructuredData)): ?>
        <script type="application/ld+json"><?= json_encode($seoStructuredData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
    <?php endif; ?>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
</head>
<body class="layout-svelte">
    @yield('content')
</body>
</html>

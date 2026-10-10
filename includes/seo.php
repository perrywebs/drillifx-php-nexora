<?php
declare(strict_types=1);
// Shared SEO / social-sharing metadata (Open Graph + Twitter/X + favicons).
// Public pages call seo_head() once inside <head>; private pages are kept out
// of indexes via X-Robots-Tag headers in require_login()/require_admin().
// PHP 8.1 compatible. All URLs emitted here are absolute (never localhost in
// production: set APP_URL=https://yourdomain.com — see DEPLOY.md).

// Absolute public URL for a root-relative asset/page path.
function abs_url(string $path): string {
    if (preg_match('#^(?:[a-z][a-z0-9+.-]*:|//)#i', $path)) return $path;
    $base = function_exists('base_path') ? base_path() : '';
    if ($base !== '' && ($path === $base || strpos($path, $base . '/') === 0)) {
        return rtrim(app_base_url(), '/') . substr($path, strlen($base));
    }
    if ($path === '' || $path[0] !== '/') $path = '/' . $path;
    return rtrim(app_base_url(), '/') . $path;
}

function canonical_url(string $path): string {
    return abs_url($path);
}

function default_share_image(): string {
    return '/images/og-cover.png';
}

function default_favicon(): string {
    return '/images/favicon.png';
}

function default_touch_icon(): string {
    return '/images/apple-touch-icon.png';
}

// $o: title (required), description, path (canonical page path, default '/'),
//     canonical (false to omit, e.g. error pages), image (default OG cover),
//     type ('website'), index (bool, default true).
function seo_head(array $o): void {
    $title = trim((string)($o['title'] ?? ''));
    if ($title === '') $title = site_name();
    $desc = trim((string)($o['description'] ?? ''));
    if ($desc === '') $desc = site_name() . ' — ' . site_tagline();
    $desc = mb_substr($desc, 0, 300);
    $type = (string)($o['type'] ?? 'website');
    if ($type !== 'website' && $type !== 'article') $type = 'website';
    $indexed = !array_key_exists('index', $o) || (bool)$o['index'];
    $canonical = array_key_exists('canonical', $o) ? $o['canonical'] : ($o['path'] ?? '/');
    if ($canonical === true) $canonical = $o['path'] ?? '/';
    $canonicalUrl = ($canonical === false || $canonical === null) ? '' : canonical_url((string)$canonical);
    $image = abs_url((string)($o['image'] ?? default_share_image()));
    $imageAlt = $title . ' — ' . site_name();
    $brand = site_name();

    $out = [];
    $out[] = '<title>' . e($title) . '</title>';
    $out[] = '<meta name="description" content="' . e($desc) . '">';
    if ($canonicalUrl !== '') $out[] = '<link rel="canonical" href="' . e($canonicalUrl) . '">';
    $out[] = '<meta name="robots" content="' . ($indexed ? 'index, follow, max-image-preview:large' : 'noindex, nofollow') . '">';
    // Open Graph (Facebook, WhatsApp, Telegram, …)
    $out[] = '<meta property="og:title" content="' . e($title) . '">';
    $out[] = '<meta property="og:description" content="' . e($desc) . '">';
    $out[] = '<meta property="og:type" content="' . e($type) . '">';
    if ($canonicalUrl !== '') $out[] = '<meta property="og:url" content="' . e($canonicalUrl) . '">';
    $out[] = '<meta property="og:site_name" content="' . e($brand) . '">';
    $out[] = '<meta property="og:image" content="' . e($image) . '">';
    $out[] = '<meta property="og:image:alt" content="' . e($imageAlt) . '">';
    $out[] = '<meta property="og:image:width" content="1200">';
    $out[] = '<meta property="og:image:height" content="630">';
    $out[] = '<meta property="og:locale" content="en_US">';
    // Twitter / X
    $out[] = '<meta name="twitter:card" content="summary_large_image">';
    $out[] = '<meta name="twitter:title" content="' . e($title) . '">';
    $out[] = '<meta name="twitter:description" content="' . e($desc) . '">';
    $out[] = '<meta name="twitter:image" content="' . e($image) . '">';
    $out[] = '<meta name="twitter:image:alt" content="' . e($imageAlt) . '">';
    if ($canonicalUrl !== '') $out[] = '<meta name="twitter:url" content="' . e($canonicalUrl) . '">';
    // Browser chrome + favicons (real files; admin branding override wins)
    $out[] = '<meta name="theme-color" content="#1d4ed8">';
    $icon = site_favicon() !== '' ? abs_url(site_favicon()) : abs_url(default_favicon());
    $ext = strtolower((string)pathinfo(parse_url($icon, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
    $mime = $ext === 'svg' ? 'image/svg+xml' : ($ext === 'ico' ? 'image/x-icon' : 'image/png');
    $out[] = '<link rel="icon" type="' . $mime . '" href="' . e($icon) . '">';
    $out[] = '<link rel="apple-touch-icon" href="' . e(abs_url(default_touch_icon())) . '">';
    echo implode("\n", $out) . "\n";
}

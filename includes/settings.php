<?php
declare(strict_types=1);
// DB-backed application settings with per-request cache.
function setting(string $key, $default = null) {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (db()->query('SELECT `k`, `v` FROM settings')->fetchAll() as $r) $cache[$r['k']] = $r['v'];
        } catch (Throwable $e) {}
    }
    return array_key_exists($key, $cache) ? $cache[$key] : $default;
}

function set_setting(string $key, $value): void {
    $v = $value === null ? null : (string)$value;
    db()->prepare('INSERT INTO settings (`k`,`v`) VALUES (?,?) ON DUPLICATE KEY UPDATE `v`=VALUES(`v`)')->execute([$key, $v]);
}

function site_name(): string { return (string)(setting('site_name', 'Drillifyx') ?: 'Drillifyx'); }
function site_tagline(): string { return (string)(setting('site_title', 'Hash & Earn USDC') ?: 'Hash & Earn USDC'); }
function site_logo(): string {
    $l = (string)(setting('site_logo', '/images/usd-coin-usdc-logo.png') ?: '/images/usd-coin-usdc-logo.png');
    // Stored paths are root-relative; prefix the subdirectory base when needed.
    if (strpos($l, '/') === 0 && strpos($l, '//') !== 0 && function_exists('url')) return url($l);
    return $l;
}
function site_favicon(): string {
    // Default brand icon (real file); admins can override via Branding page.
    $f = (string)(setting('site_favicon', '/images/favicon.png') ?: '/images/favicon.png');
    if ($f !== '' && strpos($f, '/') === 0 && strpos($f, '//') !== 0 && function_exists('url')) return url($f);
    return $f;
}

// Settings-aware business values (admin changes take effect immediately; config/app.php is fallback)
function cfg_min_withdraw(): float { return (float)(setting('min_withdraw', app_config('min_withdraw') ?? 100) ?: 100); }
function cfg_min_deposit(): float { return (float)(setting('min_deposit', 25) ?: 25); }
function cfg_referral_reward(): float { return (float)(setting('referral_reward', app_config('referral_reward') ?? 10) ?: 10); }
function cfg_fx_rates(): array {
    $out = [];
    foreach (['USD','NGN','GHS'] as $c) {
        $v = setting('fx_' . $c, null);
        $out[$c] = ($v === null || $v === '') ? (float)(app_config('fx_rates')[$c] ?? 1) : (float)$v;
        if ($out[$c] <= 0) $out[$c] = 1.0;
    }
    return $out;
}
function cfg_flag(string $key, bool $default = true): bool {
    $v = setting($key, null);
    if ($v === null) return $default;
    return $v === '1' || $v === 'true';
}

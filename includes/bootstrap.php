<?php
declare(strict_types=1);
// Secure session bootstrap — include FIRST on every page (before any output).
//
// Defines the central project-root constants used across the app:
//   APP_ROOT  - absolute filesystem path to the project root (for includes/uploads)
//   BASE_PATH - URL base path of the install ('' at domain root, '/subdir' in a subfolder)
//   APP_URL   - public base URL (scheme + host + BASE_PATH), resolved from env/config/auto-detect
//
// PHP 8.1 compatible. No extensions required beyond core + session.

// ---- Project root (filesystem) ----
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

// ---- Base path (URL) detection ----
// Works when installed at the domain document root (public_html) or in a
// subdirectory (public_html/subdir). Detection compares the entry script's
// directory against the document root.
if (!defined('BASE_PATH')) {
    $base = '';
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? (string)$_SERVER['DOCUMENT_ROOT'] : '';
    // Primary method: locate APP_ROOT relative to DOCUMENT_ROOT on disk.
    // Robust for every entry script (/, /users/*, /admin/*, /ajax/*).
    $realDoc = ($docRoot !== '') ? realpath($docRoot) : false;
    $realApp = realpath(APP_ROOT);
    if ($realDoc !== false && $realApp !== false) {
        $normDoc = rtrim(str_replace('\\', '/', $realDoc), '/');
        $normApp = rtrim(str_replace('\\', '/', $realApp), '/');
        $starts = (PHP_OS_FAMILY === 'Windows')
            ? stripos($normApp, $normDoc) === 0
            : strpos($normApp, $normDoc) === 0;
        if ($starts) {
            $rest = substr($normApp, strlen($normDoc));
            // Directory-boundary check: '' (docroot install) or '/subdir'.
            if ($rest === '' || strpos($rest, '/') === 0) {
                $base = $rest;
            }
        }
    }
    // Fallback: derive from the entry script path (strip known page dirs).
    if ($base === '' && isset($_SERVER['SCRIPT_NAME'])) {
        $scriptDir = str_replace('\\', '/', dirname((string)$_SERVER['SCRIPT_NAME']));
        if ($scriptDir === '/' || $scriptDir === '.' || $scriptDir === '\\') {
            $scriptDir = '';
        }
        foreach (['/users', '/admin', '/ajax'] as $pageDir) {
            if ($scriptDir === $pageDir || substr($scriptDir, -strlen($pageDir)) === $pageDir) {
                // Only strip one trailing page dir, then re-check: e.g.
                // /sub/users -> /sub ; /users -> '' ; / -> ''
                $scriptDir = substr($scriptDir, 0, -strlen($pageDir));
                break;
            }
        }
        $base = rtrim($scriptDir, '/');
    }
    // Never let detection produce a full URL or traversal.
    if (!is_string($base) || strpos($base, '://') !== false || strpos($base, '..') !== false) {
        $base = '';
    }
    // Explicit override wins (cPanel subdirectory installs): set env APP_BASE_PATH=/subdir
    $envBase = getenv('APP_BASE_PATH');
    if (is_string($envBase) && $envBase !== '') {
        $base = '/' . trim($envBase, '/');
        if ($base === '/') {
            $base = '';
        }
    }
    define('BASE_PATH', $base);
}

// ---- Timezone (UTC keeps daily hash/spin limits + token expiry consistent) ----
if (!ini_get('date.timezone')) {
    date_default_timezone_set('UTC');
}

// ---- Production-safe error handling ----
// Log everything, never display paths/SQL to visitors. Local dev can opt into
// display via the APP_DEBUG environment variable.
$isDebug = getenv('APP_DEBUG') === '1' || getenv('APP_DEBUG') === 'true';
if (!$isDebug) {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
}
error_reporting(E_ALL);
if (!ini_get('log_errors')) {
    ini_set('log_errors', '1');
}

// ---- HTTPS detection (incl. cPanel proxies: Cloudflare / load balancers) ----
if (!function_exists('app_is_https')) {
    function app_is_https(): bool {
        if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        if (isset($_SERVER['SERVER_PORT']) && (string)$_SERVER['SERVER_PORT'] === '443') {
            return true;
        }
        $proto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        if ($proto === 'https') {
            return true;
        }
        return false;
    }
}

// ---- Secure session ----
if (session_status() === PHP_SESSION_NONE) {
    // Use cookies only (no URL session IDs leaking in links/logs).
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => (BASE_PATH === '' ? '/' : BASE_PATH . '/'),
        'secure' => app_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ---- Subdirectory safety net for long-tail static URLs ----
// Layout helpers and redirect() already emit base-aware URLs. This output
// filter additionally rewrites any remaining root-relative app URLs in HTML/JS
// (href="/users/…", url: '/ajax/…', …) so pages keep working when the project
// lives in a subdirectory. No-op at the domain root. JSON/AJAX responses are
// skipped (content-type check) so API payloads are never altered.
if (BASE_PATH !== '' && !headers_sent()) {
    $isAjaxEndpoint = isset($_SERVER['REQUEST_URI'])
        && strpos((string)$_SERVER['REQUEST_URI'], BASE_PATH . '/ajax/') !== false;
    if (!$isAjaxEndpoint) {
        ob_start(function ($html) {
            if (!is_string($html) || $html === '') {
                return $html;
            }
            foreach (headers_list() as $h) {
                if (stripos($h, 'content-type:') === 0 && stripos($h, 'application/json') !== false) {
                    return $html;
                }
            }
            $base = BASE_PATH;
            // HTML attributes: href="/users/…" src="/images/…" action="/login.php" …
            $html = preg_replace(
                '#\b(href|src|action)=("|\')/(users|admin|ajax|images|uploads|login\.php|logout\.php|register\.php|index\.php|top-earners\.php|verify\.php|forgot-password\.php|reset-password\.php|maintenance\.php|404\.php)#',
                '$1=$2' . $base . '/$3',
                $html
            );
            // JS strings: url: '/ajax/…', fetch('/users/…'), location = '/admin/…' …
            $html = preg_replace(
                '#((?:url\s*:\s*|fetch\s*\(\s*|location(?:\.href)?\s*=\s*|window\.location(?:\.href)?\s*=\s*)[\'"])/(users|admin|ajax)/#',
                '$1' . $base . '/$2/',
                $html
            );
            return $html;
        });
    }
}

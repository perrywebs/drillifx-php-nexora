<?php
declare(strict_types=1);

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $cfg = require __DIR__ . '/../config/database.php';
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $cfg['host'], $cfg['port'], $cfg['db'], $cfg['charset']);
    try {
        $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        // Never leak host/db/user to visitors; the real message goes to the error log.
        error_log('DB connection failed: ' . $e->getMessage());
        http_response_code(500);
        exit('Service temporarily unavailable. Please try again later.');
    }
    return $pdo;
}

function app_config(?string $key = null) {
    static $cfg = null;
    if ($cfg === null) $cfg = require __DIR__ . '/../config/app.php';
    return $key === null ? $cfg : ($cfg[$key] ?? null);
}

function e(?string $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

// ---- Base path / URLs (cPanel: domain root '' or subdirectory '/subdir') ----
function base_path(): string {
    return defined('BASE_PATH') ? (string)BASE_PATH : '';
}

// Absolute public base URL (for emails + referral links). Priority:
// APP_URL env var -> config/app.php -> auto-detect from the request.
function app_base_url(): string {
    static $url = null;
    if ($url !== null) return $url;
    $env = getenv('APP_URL');
    if (is_string($env) && trim($env) !== '') {
        $url = rtrim(trim($env), '/');
        return $url;
    }
    $cfg = app_config('app_url');
    if (is_string($cfg) && trim($cfg) !== '' && strpos($cfg, 'drillifx-php.test') === false) {
        $url = rtrim(trim($cfg), '/');
        return $url;
    }
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $host = trim(strtok($host, " \t\n\r\0\x0B/"));
    if ($host === '') $host = 'localhost';
    $scheme = (function_exists('app_is_https') && app_is_https()) ? 'https' : 'http';
    $url = $scheme . '://' . $host . base_path();
    return $url;
}

// Browser-facing URL for an app path. url('/users/dashboard.php') works at
// both the domain root and in a subdirectory install.
function url(string $path): string {
    if ($path === '') return base_path() === '' ? '/' : base_path() . '/';
    // Leave absolute URLs and protocol-relative URLs untouched.
    if (preg_match('#^(?:[a-z][a-z0-9+.-]*:|//)#i', $path)) return $path;
    $base = base_path();
    if ($path[0] !== '/') $path = '/' . $path;
    if ($base !== '' && ($path === $base || strpos($path, $base . '/') === 0)) return $path;
    return $base . $path;
}

function asset(string $path): string {
    return url($path);
}

function redirect(string $url): void {
    // Only allow local redirects (prevents open-redirect via ?next=).
    if (preg_match('#^(?:[a-z][a-z0-9+.-]*:|//)#i', $url)) {
        $url = url('/index.php');
    } else {
        $url = url($url);
    }
    header('Location: ' . $url);
    exit;
}

// Validate a ?next= target: must be a local app path (optionally base-prefixed).
function safe_next(?string $next, string $fallback): string {
    if (!is_string($next) || $next === '') return $fallback;
    if (preg_match('#^(?:[a-z][a-z0-9+.-]*:|//|\\\\)#i', $next)) return $fallback;
    $base = base_path();
    $check = $next;
    if ($base !== '' && strpos($check, $base . '/') === 0) {
        $check = substr($check, strlen($base));
        if ($check === '') $check = '/';
    }
    if ($check[0] !== '/') return $fallback;
    return url($check);
}

// ---- CSRF ----
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}
function csrf_check(?string $token): bool {
    return is_string($token) && hash_equals($_SESSION['csrf'] ?? '', $token);
}

// ---- Flash toasts (rendered by pages into #toastContainer via Toast.show) ----
function flash(string $type, string $title, string $message): void {
    $_SESSION['toasts'][] = ['type' => $type, 'title' => $title, 'message' => $message];
}
function pull_flashes(): array {
    $t = $_SESSION['toasts'] ?? [];
    unset($_SESSION['toasts']);
    return is_array($t) ? $t : [];
}
function render_toast_queue(): string {
    $toasts = pull_flashes();
    if (!$toasts) return '';
    $json = json_encode($toasts, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
    return "<script>document.addEventListener('DOMContentLoaded',function(){try{var q=$json;if(window.Toast){q.forEach(function(t){Toast.show(t.message,t.type||'info',5000);});}else if(window.showToast){q.forEach(function(t){showToast(t.message,t.type);});}}catch(e){}});</script>";
}

// ---- Misc helpers ----
function gen_reference(string $prefix): string {
    return $prefix . strtoupper(bin2hex(random_bytes(6)));
}
function gen_referral_code(PDO $pdo): string {
    do {
        $code = strtoupper(substr(bin2hex(random_bytes(5)), 0, 8));
        $st = $pdo->prepare('SELECT 1 FROM users WHERE referral_code = ?');
        $st->execute([$code]);
    } while ($st->fetch());
    return $code;
}
function mask_username(string $u): string {
    $len = strlen($u);
    if ($len <= 4) return substr($u, 0, 1) . '***' . substr($u, -1);
    return substr($u, 0, 2) . '***' . substr($u, -2);
}
function referral_link(string $code): string {
    return rtrim(app_base_url(), '/') . '/register.php?ref=' . urlencode($code);
}
function money(float|string $v): string {
    return number_format((float)$v, 2, '.', ',');
}
function money4(float|string $v): string {
    return number_format((float)$v, 4, '.', ',');
}
function client_ip(): string {
    // Prefer direct connection; only trust proxy headers for private-network
    // proxies (avoids trivial IP spoofing from the open internet).
    $remote = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $fwd = trim((string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
    if ($fwd !== '' && filter_var($remote, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        $first = trim(strtok($fwd, ','));
        if (filter_var($first, FILTER_VALIDATE_IP)) return $first;
    }
    return is_string($remote) && $remote !== '' ? $remote : '127.0.0.1';
}
function log_activity(int $userId, string $type, string $message): void {
    try {
        $st = db()->prepare('INSERT INTO activity_log (user_id, type, message, ip) VALUES (?,?,?,?)');
        $st->execute([$userId, $type, $message, client_ip()]);
    } catch (Throwable $e) {}
}
function notify(int $userId, string $title, string $message): void {
    try {
        $st = db()->prepare('INSERT INTO notifications (user_id, title, message) VALUES (?,?,?)');
        $st->execute([$userId, $title, $message]);
    } catch (Throwable $e) {}
}
function json_out(bool $success, string $message, array $data = [], int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit;
}
function today_count(string $table, int $userId): int {
    $allowed = ['hashes' => true, 'spins' => true];
    if (!isset($allowed[$table])) return 0;
    $st = db()->prepare("SELECT COUNT(*) c FROM `$table` WHERE user_id = ? AND DATE(created_at) = CURDATE()");
    $st->execute([$userId]);
    return (int)($st->fetch()['c'] ?? 0);
}
function is_valid_email(string $email): bool {
    return (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
}

require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/seo.php';

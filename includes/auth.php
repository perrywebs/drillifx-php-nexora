<?php
declare(strict_types=1);
// Session + auth helpers. Include AFTER session_start().
require_once __DIR__ . '/functions.php';

function current_user(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    static $cache = null;
    if ($cache !== null) return $cache;
    try {
        $st = db()->prepare('SELECT u.*, t.name AS tier_name FROM users u LEFT JOIN tiers t ON t.id = u.tier_id WHERE u.id = ? LIMIT 1');
        $st->execute([(int)$_SESSION['user_id']]);
        $u = $st->fetch();
        if (!$u || ($u['status'] ?? '') !== 'active') return null;
        // Expire paid tiers automatically (downgrade to Free, balance intact)
        if (!empty($u['tier_expires_at']) && (int)$u['tier_id'] > 0 && strtotime($u['tier_expires_at']) < time()) {
            $up = db()->prepare('UPDATE users SET tier_id = 0, tier_expires_at = NULL WHERE id = ?');
            $up->execute([$u['id']]);
            $u['tier_id'] = 0;
            $u['tier_name'] = 'Free Tier';
            $u['tier_expires_at'] = null;
        }
        // Tier info comes from the tiers table (single source of truth)
        $cache = $u;
        return $u;
    } catch (Throwable $e) {
        return null;
    }
}

function require_login(): array {
    // Private area: keep user pages out of search/social indexes.
    if (!headers_sent()) header('X-Robots-Tag: noindex, nofollow', false);
    $u = current_user();
    if (!$u) {
        $next = urlencode($_SERVER['REQUEST_URI'] ?? url('/users/dashboard.php'));
        redirect(url('/login.php') . '?next=' . $next);
    }
    // Maintenance mode: admins still pass, users are held on a notice page
    try {
        require_once __DIR__ . '/settings.php';
        if (setting('maintenance_mode', '0') === '1' && empty($_SESSION['admin_authenticated'])) {
            $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
            $base = base_path();
            $path = $base !== '' && strpos($uri, $base) === 0 ? substr($uri, strlen($base)) : $uri;
            $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strpos($path, '/ajax/') === 0;
            if ($isAjax) json_out(false, 'Platform is under maintenance. Try again shortly.', [], 503);
            if (strpos($path, '/maintenance.php') !== 0) redirect('/maintenance.php');
        }
    } catch (Throwable $e) {}
    return $u;
}

function login_user(int $userId): void {
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
}

function logout_user(): void {
    unset($_SESSION['user_id']);
    session_regenerate_id(true);
}

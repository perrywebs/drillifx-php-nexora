<?php
declare(strict_types=1);
// Admin authentication: separate table + separate session namespace from users.
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/settings.php';

function current_admin(): ?array {
    if (empty($_SESSION['admin_id']) || empty($_SESSION['admin_authenticated'])) return null;
    static $cache = null;
    if ($cache !== null) return $cache;
    try {
        $st = db()->prepare('SELECT * FROM admins WHERE id = ? LIMIT 1');
        $st->execute([(int)$_SESSION['admin_id']]);
        $a = $st->fetch();
        if (!$a || ($a['status'] ?? '') !== 'active') return null;
        if ((string)($_SESSION['admin_role'] ?? '') !== (string)$a['role']) $_SESSION['admin_role'] = $a['role'];
        $cache = $a;
        return $a;
    } catch (Throwable $e) { return null; }
}

// $roles: e.g. ['super_admin'] or ['super_admin','admin','finance']
function require_admin(array $roles = []): array {
    // Private area: keep admin pages out of search/social indexes.
    if (!headers_sent()) header('X-Robots-Tag: noindex, nofollow', false);
    $a = current_admin();
    if (!$a) redirect(url('/admin/login.php') . '?next=' . urlencode($_SERVER['REQUEST_URI'] ?? url('/admin/')));
    if ($roles && !in_array($a['role'], $roles, true)) {
        http_response_code(403);
        exit('Forbidden: insufficient permissions.');
    }
    return $a;
}

function admin_login(int $adminId, string $role): void {
    session_regenerate_id(true);
    $_SESSION['admin_id'] = $adminId;
    $_SESSION['admin_authenticated'] = true;
    $_SESSION['admin_role'] = $role;
}

function admin_logout(): void {
    unset($_SESSION['admin_id'], $_SESSION['admin_authenticated'], $_SESSION['admin_role']);
    session_regenerate_id(true);
}

function admin_log(?int $adminId, string $action, string $description, ?string $targetType = null, $targetId = null): void {
    try {
        $st = db()->prepare('INSERT INTO admin_audit_logs (admin_id, action, target_type, target_id, description, ip) VALUES (?,?,?,?,?,?)');
        $st->execute([$adminId, $action, $targetType, $targetId === null ? null : (string)$targetId, mb_substr($description, 0, 500), client_ip()]);
    } catch (Throwable $e) {}
}

function admin_can(array $a, string ...$roles): bool {
    if (($a['role'] ?? '') === 'super_admin') return true;
    return in_array($a['role'] ?? '', $roles, true);
}

function is_last_super_admin(int $excludeId): bool {
    try {
        $st = db()->prepare("SELECT COUNT(*) c FROM admins WHERE role='super_admin' AND status='active' AND id <> ?");
        $st->execute([$excludeId]);
        return ((int)$st->fetch()['c']) === 0;
    } catch (Throwable $e) { return true; }
}

<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/includes/layout.php';
$admin = require_admin();
$pdo = db();
$action = $_GET['action'] ?? '';
$aid = isset($_GET['admin_id']) && $_GET['admin_id'] !== '' ? (int)$_GET['admin_id'] : null;
$page = max(1, (int)($_GET['page'] ?? 1));
$per = 30;
$where = ['1=1']; $params = [];
if ($action !== '') { $where[] = 'l.action=?'; $params[] = $action; }
if ($aid !== null) { $where[] = 'l.admin_id=?'; $params[] = $aid; }
$w = implode(' AND ', $where);
$st = $pdo->prepare("SELECT COUNT(*) c FROM admin_audit_logs l WHERE $w");
$st->execute($params);
$total = (int)$st->fetch()['c'];
$pages = max(1, (int)ceil($total / $per));
$page = min($page, $pages);
$off = ($page - 1) * $per;
$st = $pdo->prepare("SELECT l.*, a.username FROM admin_audit_logs l LEFT JOIN admins a ON a.id=l.admin_id WHERE $w ORDER BY l.id DESC LIMIT $per OFFSET $off");
$st->execute($params);
$rows = $st->fetchAll();
$actions = $pdo->query('SELECT DISTINCT action FROM admin_audit_logs ORDER BY action')->fetchAll();
$admins = $pdo->query('SELECT id, username FROM admins ORDER BY username')->fetchAll();
admin_head('Activity Log');
admin_sidebar($admin, 'audit');
echo admin_flashes();
echo '<h1 class="text-xl font-bold text-slate-800 mb-1">Activity Log</h1>';
echo '<p class="text-sm text-slate-500 mb-4">Everything administrators have done, newest first. ' . $total . ' entries.</p>';
echo '<form method="GET" class="bg-white rounded-2xl p-3 shadow-sm mb-4 flex flex-wrap gap-2">';
echo '<select name="action" class="border rounded-xl px-3 py-2 text-sm"><option value="">All actions</option>';
foreach ($actions as $a) echo '<option value="' . e($a['action']) . '"' . ($action === $a['action'] ? ' selected' : '') . '>' . e(admin_action_label($a['action'])) . '</option>';
echo '</select><select name="admin_id" class="border rounded-xl px-3 py-2 text-sm"><option value="">Everyone</option>';
foreach ($admins as $a) echo '<option value="' . (int)$a['id'] . '"' . ($aid === (int)$a['id'] ? ' selected' : '') . '>' . e($a['username']) . '</option>';
echo '</select><button class="bg-slate-900 text-white px-4 py-2 rounded-xl text-sm font-semibold">Search</button>';
if ($action !== '' || $aid !== null) echo '<a href="/admin/audit.php" class="px-4 py-2 text-sm">Clear</a>';
echo '</form>';
echo '<div class="bg-white rounded-2xl shadow-sm tablescroll"><table class="w-full text-sm min-w-[620px]">';
echo '<tr class="text-left text-xs text-slate-400 border-b"><th class="p-3">Date</th><th class="p-3">Done by</th><th class="p-3">What happened</th></tr>';
foreach ($rows as $r) {
    echo '<tr class="border-b border-slate-100 last:border-0"><td class="p-3 text-xs whitespace-nowrap">' . e(date('M d, Y g:i A', strtotime($r['created_at']))) . '</td><td class="p-3 font-semibold">' . e($r['username'] ?? 'System') . '</td><td class="p-3 text-xs"><span class="font-bold">' . e(admin_action_label($r['action'])) . '</span> — ' . e($r['description']) . '</td></tr>';
}
if (!$rows) echo '<tr><td colspan="3" class="p-6 text-center text-slate-400">Nothing found.</td></tr>';
echo '</table></div>';
admin_pager($page, $pages, '/admin/audit.php', ['action' => $action, 'admin_id' => $aid === null ? '' : $aid]);
admin_footer();

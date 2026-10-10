<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/includes/layout.php';
$admin = require_admin();
$pdo = db();
$q = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per = 25;
$where = ['1=1']; $params = [];
if ($q !== '') { $where[] = '(u.username LIKE ? OR u.email LIKE ? OR n.title LIKE ? OR n.message LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; }
$w = implode(' AND ', $where);
$st = $pdo->prepare("SELECT COUNT(*) c FROM notifications n JOIN users u ON u.id=n.user_id WHERE $w");
$st->execute($params);
$total = (int)$st->fetch()['c'];
$pages = max(1, (int)ceil($total / $per));
$page = min($page, $pages);
$off = ($page - 1) * $per;
$st = $pdo->prepare("SELECT n.*, u.username, u.email FROM notifications n JOIN users u ON u.id=n.user_id WHERE $w ORDER BY n.id DESC LIMIT $per OFFSET $off");
$st->execute($params);
$rows = $st->fetchAll();
admin_head('User Messages');
admin_sidebar($admin, 'notifications');
echo admin_flashes();
echo '<h1 class="text-xl font-bold text-slate-800 mb-1">User Messages</h1>';
echo '<p class="text-sm text-slate-500 mb-4">Messages the app has sent to users (welcome notes, payment updates, security alerts).</p>';
echo '<form method="GET" class="bg-white rounded-2xl p-3 shadow-sm mb-4 flex gap-2">';
echo '<input name="q" value="' . e($q) . '" placeholder="Search by user, email or message" class="flex-1 border rounded-xl px-3 py-2 text-sm">';
echo '<button class="bg-slate-900 text-white px-4 py-2 rounded-xl text-sm font-semibold">Search</button>';
if ($q !== '') echo '<a href="/admin/notifications.php" class="px-4 py-2 text-sm">Clear</a>';
echo '</form>';
foreach ($rows as $n) {
    echo '<div class="bg-white rounded-2xl p-4 shadow-sm mb-3"><div class="flex flex-wrap justify-between gap-1 mb-1">';
    echo '<p class="font-bold text-sm">' . e($n['title']) . '</p><span class="text-xs text-slate-400">To ' . e($n['username']) . ' · ' . e(date('M d, Y g:i A', strtotime($n['created_at']))) . '</span></div>';
    echo '<p class="text-sm text-slate-600">' . e($n['message']) . '</p></div>';
}
if (!$rows) echo '<p class="text-sm text-slate-400">No messages found.</p>';
admin_pager($page, $pages, '/admin/notifications.php', ['q' => $q]);
admin_footer();

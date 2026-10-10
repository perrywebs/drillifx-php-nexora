<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/includes/layout.php';
$admin = require_admin();
$pdo = db();
$q = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
if (!in_array($status, ['', 'active', 'suspended'], true)) $status = '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per = 20;
$where = ['1=1']; $params = [];
if ($q !== '') { $where[] = '(u.username LIKE ? OR u.email LIKE ? OR u.referral_code LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($status !== '') { $where[] = 'u.status=?'; $params[] = $status; }
$w = implode(' AND ', $where);
$st = $pdo->prepare("SELECT COUNT(*) c FROM users u WHERE $w");
$st->execute($params);
$total = (int)$st->fetch()['c'];
$pages = max(1, (int)ceil($total / $per));
$page = min($page, $pages);
$off = ($page - 1) * $per;
$st = $pdo->prepare("SELECT u.* FROM users u WHERE $w ORDER BY u.id DESC LIMIT $per OFFSET $off");
$st->execute($params);
$rows = $st->fetchAll();
admin_head('Users');
admin_sidebar($admin, 'users');
echo admin_flashes();
echo '<h1 class="text-xl font-bold text-slate-800 mb-1">Users</h1>';
echo '<p class="text-sm text-slate-500 mb-4">' . $total . ' people ' . ($q !== '' ? 'matching "' . e($q) . '"' : 'in total') . '.</p>';
echo '<form method="GET" class="bg-white rounded-2xl p-3 shadow-sm mb-4 flex flex-wrap gap-2">';
echo '<input name="q" value="' . e($q) . '" placeholder="Search name, email or referral code" class="flex-1 min-w-[200px] border rounded-xl px-3 py-2 text-sm">';
echo '<select name="status" class="border rounded-xl px-3 py-2 text-sm"><option value="">Everyone</option><option value="active"' . ($status === 'active' ? ' selected' : '') . '>Active accounts</option><option value="suspended"' . ($status === 'suspended' ? ' selected' : '') . '>Disabled accounts</option></select>';
echo '<button class="bg-slate-900 text-white px-4 py-2 rounded-xl text-sm font-semibold">Search</button>';
if ($q !== '' || $status !== '') echo '<a href="/admin/users.php" class="px-4 py-2 text-sm">Clear</a>';
echo '</form>';
echo '<div class="bg-white rounded-2xl shadow-sm tablescroll"><table class="w-full text-sm min-w-[640px]">';
echo '<tr class="text-left text-xs text-slate-400 border-b"><th class="p-3">User</th><th class="p-3">Balance</th><th class="p-3">Account</th><th class="p-3">Joined</th><th class="p-3"></th></tr>';
foreach ($rows as $r) {
    echo '<tr class="border-b border-slate-100 last:border-0 hover:bg-slate-50">';
    echo '<td class="p-3"><p class="font-semibold">' . e($r['username']) . '</p><p class="text-xs text-slate-400">' . e($r['email']) . '</p></td>';
    echo '<td class="p-3 font-bold">$' . e(number_format((float)$r['balance'], 2)) . '</td>';
    echo '<td class="p-3"><span class="text-xs font-bold px-2 py-1 rounded-full ' . ($r['status'] === 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700') . '">' . ($r['status'] === 'active' ? 'Active' : 'Disabled') . '</span></td>';
    echo '<td class="p-3 text-xs text-slate-500">' . e(date('M d, Y', strtotime($r['created_at']))) . '</td>';
    echo '<td class="p-3"><a class="text-blue-600 font-semibold text-xs" href="/admin/user.php?id=' . (int)$r['id'] . '">View</a></td></tr>';
}
if (!$rows) echo '<tr><td colspan="5" class="p-6 text-center text-slate-400">No users found. Try a different search.</td></tr>';
echo '</table></div>';
admin_pager($page, $pages, '/admin/users.php', ['q' => $q, 'status' => $status]);
admin_footer();

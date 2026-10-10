<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/includes/layout.php';
$admin = require_admin();
$pdo = db();
$status = $_GET['status'] ?? '';
if (!in_array($status, ['', 'pending', 'earned'], true)) $status = '';
$q = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per = 25;
$where = ['1=1']; $params = [];
if ($status !== '') { $where[] = 'r.status=?'; $params[] = $status; }
if ($q !== '') { $where[] = '(a.username LIKE ? OR b.username LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
$w = implode(' AND ', $where);
$st = $pdo->prepare("SELECT COUNT(*) c FROM referrals r JOIN users a ON a.id=r.referrer_id JOIN users b ON b.id=r.referred_id WHERE $w");
$st->execute($params);
$total = (int)$st->fetch()['c'];
$pages = max(1, (int)ceil($total / $per));
$page = min($page, $pages);
$off = ($page - 1) * $per;
$st = $pdo->prepare("SELECT r.*, a.username AS referrer, b.username AS referred FROM referrals r JOIN users a ON a.id=r.referrer_id JOIN users b ON b.id=r.referred_id WHERE $w ORDER BY r.id DESC LIMIT $per OFFSET $off");
$st->execute($params);
$rows = $st->fetchAll();
admin_head('Referrals');
admin_sidebar($admin, 'referrals');
echo admin_flashes();
echo '<h1 class="text-xl font-bold text-slate-800 mb-1">Invited Friends</h1>';
echo '<p class="text-sm text-slate-500 mb-4">Who invited whom, and whether the reward has been paid. ' . $total . ' in total.</p>';
echo '<form method="GET" class="bg-white rounded-2xl p-3 shadow-sm mb-4 flex flex-wrap gap-2">';
echo '<select name="status" class="border rounded-xl px-3 py-2 text-sm"><option value="">All</option><option value="pending"' . ($status === 'pending' ? ' selected' : '') . '>Waiting for reward</option><option value="earned"' . ($status === 'earned' ? ' selected' : '') . '>Reward paid</option></select>';
echo '<input name="q" value="' . e($q) . '" placeholder="Search a name" class="flex-1 min-w-[200px] border rounded-xl px-3 py-2 text-sm">';
echo '<button class="bg-slate-900 text-white px-4 py-2 rounded-xl text-sm font-semibold">Search</button>';
if ($status !== '' || $q !== '') echo '<a href="/admin/referrals.php" class="px-4 py-2 text-sm">Clear</a>';
echo '</form>';
echo '<div class="bg-white rounded-2xl shadow-sm tablescroll"><table class="w-full text-sm min-w-[560px]">';
echo '<tr class="text-left text-xs text-slate-400 border-b"><th class="p-3">User</th><th class="p-3">Invited friend</th><th class="p-3">Reward</th><th class="p-3">Status</th><th class="p-3">Date</th></tr>';
foreach ($rows as $r) {
    echo '<tr class="border-b border-slate-100 last:border-0"><td class="p-3 font-semibold">' . e($r['referrer']) . '</td><td class="p-3">' . e($r['referred']) . '</td><td class="p-3">$' . e(number_format((float)$r['reward'], 2)) . '</td><td class="p-3"><span class="text-xs font-bold px-2 py-1 rounded-full ' . ($r['status'] === 'earned' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700') . '">' . ($r['status'] === 'earned' ? 'Reward paid' : 'Waiting') . '</span></td><td class="p-3 text-xs text-slate-500">' . e(date('M d, Y', strtotime($r['created_at']))) . '</td></tr>';
}
if (!$rows) echo '<tr><td colspan="5" class="p-6 text-center text-slate-400">Nothing found.</td></tr>';
echo '</table></div>';
admin_pager($page, $pages, '/admin/referrals.php', ['status' => $status, 'q' => $q]);
admin_footer();

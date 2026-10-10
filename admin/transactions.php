<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/includes/layout.php';
$admin = require_admin();
$pdo = db();
$type = $_GET['type'] ?? '';
$q = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per = 25;
$where = ['1=1']; $params = [];
if ($type !== '') { $where[] = 't.type=?'; $params[] = $type; }
if ($q !== '') { $where[] = '(u.username LIKE ? OR t.reference LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
$w = implode(' AND ', $where);
$st = $pdo->prepare("SELECT COUNT(*) c FROM transactions t JOIN users u ON u.id=t.user_id WHERE $w");
$st->execute($params);
$total = (int)$st->fetch()['c'];
$pages = max(1, (int)ceil($total / $per));
$page = min($page, $pages);
$off = ($page - 1) * $per;
$st = $pdo->prepare("SELECT t.*, u.username FROM transactions t JOIN users u ON u.id=t.user_id WHERE $w ORDER BY t.id DESC LIMIT $per OFFSET $off");
$st->execute($params);
$rows = $st->fetchAll();
$types = $pdo->query('SELECT DISTINCT type FROM transactions ORDER BY type')->fetchAll();
admin_head('Transactions');
admin_sidebar($admin, 'transactions');
echo admin_flashes();
echo '<h1 class="text-xl font-bold text-slate-800 mb-1">Money Movements</h1>';
echo '<p class="text-sm text-slate-500 mb-4">Every addition, removal and reward on the platform. ' . $total . ' in total.</p>';
echo '<form method="GET" class="bg-white rounded-2xl p-3 shadow-sm mb-4 flex flex-wrap gap-2">';
echo '<select name="type" class="border rounded-xl px-3 py-2 text-sm"><option value="">Everything</option>';
foreach ($types as $t) echo '<option value="' . e($t['type']) . '"' . ($type === $t['type'] ? ' selected' : '') . '>' . e($t['type']) . '</option>';
echo '</select><input name="q" value="' . e($q) . '" placeholder="Person or reference" class="flex-1 min-w-[200px] border rounded-xl px-3 py-2 text-sm">';
echo '<button class="bg-slate-900 text-white px-4 py-2 rounded-xl text-sm font-semibold">Search</button>';
if ($type !== '' || $q !== '') echo '<a href="/admin/transactions.php" class="px-4 py-2 text-sm">Clear</a>';
echo '</form>';
echo '<div class="bg-white rounded-2xl shadow-sm overflow-x-auto"><table class="w-full text-sm min-w-[720px]">';
echo '<tr class="text-left text-xs text-slate-400 border-b"><th class="p-3">User</th><th class="p-3">What happened</th><th class="p-3">Amount</th><th class="p-3">Payment status</th><th class="p-3">Date</th></tr>';
foreach ($rows as $r) {
    $neg = (float)$r['amount'] < 0;
    echo '<tr class="border-b border-slate-100 last:border-0"><td class="p-3 font-semibold">' . e($r['username']) . '</td><td class="p-3">' . e($r['type']) . '</td>';
    echo '<td class="p-3 font-bold ' . ($neg ? 'text-red-600' : 'text-green-600') . '">' . ($neg ? '−' : '+') . '$' . e(number_format(abs((float)$r['amount']), 2)) . '</td>';
    echo '<td class="p-3 text-xs">' . e(ucfirst($r['status'])) . '</td><td class="p-3 text-xs text-slate-500">' . e(date('M d, Y g:i A', strtotime($r['created_at']))) . '</td></tr>';
}
if (!$rows) echo '<tr><td colspan="5" class="p-6 text-center text-slate-400">Nothing found.</td></tr>';
echo '</table></div>';
admin_pager($page, $pages, '/admin/transactions.php', ['type' => $type, 'q' => $q]);
admin_footer();

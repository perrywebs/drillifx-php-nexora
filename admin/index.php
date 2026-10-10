<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/includes/layout.php';
$admin = require_admin();
$pdo = db();
$users = (int)$pdo->query('SELECT COUNT(*) c FROM users')->fetch()['c'];
$moneyIn = (float)($pdo->query("SELECT COALESCE(SUM(amount),0) s FROM deposits WHERE status='approved'")->fetch()['s'] ?? 0);
$moneyOut = (float)($pdo->query("SELECT COALESCE(SUM(amount),0) s FROM withdrawals WHERE status='paid'")->fetch()['s'] ?? 0);
$waitWd = (int)$pdo->query("SELECT COUNT(*) c FROM withdrawals WHERE status='pending'")->fetch()['c'];
$waitDep = (int)$pdo->query("SELECT COUNT(*) c FROM deposits WHERE status='pending'")->fetch()['c'];
$pendWd = $pdo->query('SELECT w.id, w.amount, w.method, w.created_at, u.username FROM withdrawals w JOIN users u ON u.id=w.user_id WHERE w.status="pending" ORDER BY w.id DESC LIMIT 5')->fetchAll();
$pendDep = $pdo->query('SELECT d.id, d.amount, d.created_at, u.username, t.name AS tier_name FROM deposits d JOIN users u ON u.id=d.user_id JOIN tiers t ON t.id=d.tier_id WHERE d.status="pending" ORDER BY d.id DESC LIMIT 5')->fetchAll();
$newUsers = $pdo->query('SELECT username, email, created_at FROM users ORDER BY id DESC LIMIT 5')->fetchAll();
admin_head('Dashboard');
admin_sidebar($admin, 'dashboard');
echo admin_flashes();
echo '<h1 class="text-xl font-bold text-slate-800 mb-4">How is the platform doing?</h1>';
echo '<div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-6">';
echo '<a href="/admin/users.php" class="bg-white rounded-2xl p-4 shadow-sm"><p class="text-2xl font-bold text-slate-800">' . number_format($users) . '</p><p class="text-xs text-slate-500">Users</p></a>';
echo '<a href="/admin/deposits.php?status=approved" class="bg-white rounded-2xl p-4 shadow-sm"><p class="text-2xl font-bold text-slate-800">$' . number_format($moneyIn, 2) . '</p><p class="text-xs text-slate-500">Money added</p></a>';
echo '<a href="/admin/withdrawals.php?status=paid" class="bg-white rounded-2xl p-4 shadow-sm"><p class="text-2xl font-bold text-slate-800">$' . number_format($moneyOut, 2) . '</p><p class="text-xs text-slate-500">Money paid out</p></a>';
echo '<a href="/admin/withdrawals.php?status=pending" class="bg-white rounded-2xl p-4 shadow-sm ' . ($waitWd ? 'ring-2 ring-red-400' : '') . '"><p class="text-2xl font-bold ' . ($waitWd ? 'text-red-600' : 'text-slate-800') . '">' . $waitWd . '</p><p class="text-xs text-slate-500">Waiting withdrawals</p></a>';
echo '<a href="/admin/deposits.php?status=pending" class="bg-white rounded-2xl p-4 shadow-sm ' . ($waitDep ? 'ring-2 ring-amber-400' : '') . '"><p class="text-2xl font-bold ' . ($waitDep ? 'text-amber-600' : 'text-slate-800') . '">' . $waitDep . '</p><p class="text-xs text-slate-500">Waiting deposits</p></a>';
echo '</div>';
echo '<h2 class="font-bold text-slate-700 text-sm mb-2">Needs your attention</h2>';
echo '<div class="grid lg:grid-cols-2 gap-4 mb-6">';
echo '<div class="bg-white rounded-2xl p-4 shadow-sm"><div class="flex justify-between items-center mb-2"><h3 class="font-bold text-sm">Waiting withdrawals</h3><a class="text-xs text-blue-600 font-semibold" href="/admin/withdrawals.php?status=pending">View all</a></div>';
if ($pendWd) foreach ($pendWd as $w) {
    echo '<div class="flex justify-between items-center py-2 border-b border-slate-100 last:border-0 text-sm"><span>' . e($w['username']) . ' wants <strong>$' . e(number_format((float)$w['amount'], 2)) . '</strong> via ' . e($w['method']) . '</span><a class="text-blue-600 font-semibold text-xs" href="/admin/withdrawals.php?status=pending&focus=' . (int)$w['id'] . '">Review</a></div>';
} else echo '<p class="text-sm text-slate-400">Nothing waiting. 🎉</p>';
echo '</div>';
echo '<div class="bg-white rounded-2xl p-4 shadow-sm"><div class="flex justify-between items-center mb-2"><h3 class="font-bold text-sm">Waiting deposits</h3><a class="text-xs text-blue-600 font-semibold" href="/admin/deposits.php?status=pending">View all</a></div>';
if ($pendDep) foreach ($pendDep as $d) {
    echo '<div class="flex justify-between items-center py-2 border-b border-slate-100 last:border-0 text-sm"><span>' . e($d['username']) . ' paid <strong>$' . e(number_format((float)$d['amount'], 2)) . '</strong> for ' . e($d['tier_name']) . '</span><a class="text-blue-600 font-semibold text-xs" href="/admin/deposits.php?status=pending">Review</a></div>';
} else echo '<p class="text-sm text-slate-400">Nothing waiting. 🎉</p>';
echo '</div></div>';
echo '<div class="bg-white rounded-2xl p-4 shadow-sm"><div class="flex justify-between items-center mb-2"><h3 class="font-bold text-sm">Newest users</h3><a class="text-xs text-blue-600 font-semibold" href="/admin/users.php">View all</a></div>';
foreach ($newUsers as $ru) {
    echo '<div class="flex justify-between py-2 border-b border-slate-100 last:border-0 text-sm"><span>' . e($ru['username']) . '<span class="text-slate-400"> · ' . e($ru['email']) . '</span></span><span class="text-xs text-slate-400">' . e(date('M d', strtotime($ru['created_at']))) . '</span></div>';
}
if (!$newUsers) echo '<p class="text-sm text-slate-400">No users yet.</p>';
echo '</div>';
admin_footer();

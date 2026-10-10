<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/actions.php';
$admin = require_admin(['super_admin', 'admin', 'finance']);
$pdo = db();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) admin_flash('error', 'Invalid session.');
    else {
        $id = (int)($_POST['id'] ?? 0);
        $to = $_POST['to'] ?? '';
        $r = admin_withdrawal($admin, $id, $to, $_POST['note'] ?? '');
        admin_flash($r['ok'] ? 'success' : 'error', $r['msg']);
    }
    redirect('/admin/withdrawals.php?status=' . urlencode($_POST['back_status'] ?? 'pending'));
}
$status = $_GET['status'] ?? 'pending';
if (!in_array($status, ['pending', 'approved', 'paid', 'rejected', 'all'], true)) $status = 'pending';
$search = trim($_GET['q'] ?? '');
$focus = (int)($_GET['focus'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$per = 20;
$where = ['1=1']; $params = [];
if ($status !== 'all') { $where[] = 'w.status=?'; $params[] = $status; }
if ($search !== '') { $where[] = '(u.username LIKE ? OR u.email LIKE ? OR w.reference LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }
$w = implode(' AND ', $where);
$st = $pdo->prepare("SELECT COUNT(*) c FROM withdrawals w JOIN users u ON u.id=w.user_id WHERE $w");
$st->execute($params);
$total = (int)$st->fetch()['c'];
$pages = max(1, (int)ceil($total / $per));
$page = min($page, $pages);
$off = ($page - 1) * $per;
$st = $pdo->prepare("SELECT w.*, u.username, u.email, u.balance AS user_balance FROM withdrawals w JOIN users u ON u.id=w.user_id WHERE $w ORDER BY w.id DESC LIMIT $per OFFSET $off");
$st->execute($params);
$rows = $st->fetchAll();
if ($focus) {
    $rows = array_merge(
        array_filter($rows, fn($r) => (int)$r['id'] === $focus),
        array_filter($rows, fn($r) => (int)$r['id'] !== $focus)
    );
}
admin_head('Withdrawals');
admin_sidebar($admin, 'withdrawals');
echo admin_flashes();
echo '<h1 class="text-xl font-bold text-slate-800 mb-1">Withdrawals</h1>';
echo '<p class="text-sm text-slate-500 mb-4">' . ($status === 'pending' ? 'These people are waiting for their money. Check the details, then approve or reject.' : 'Showing ' . e($status) . ' withdrawals.') . ' ' . $total . ' in total.</p>';
echo '<form method="GET" class="bg-white rounded-2xl p-3 shadow-sm mb-4 flex flex-wrap gap-2">';
echo '<input type="hidden" name="status" value="' . e($status) . '">';
echo '<input name="q" value="' . e($search) . '" placeholder="Search person or email" class="flex-1 min-w-[200px] border rounded-xl px-3 py-2 text-sm">';
echo '<button class="bg-slate-900 text-white px-4 py-2 rounded-xl text-sm font-semibold">Search</button>';
if ($search !== '') echo '<a href="/admin/withdrawals.php?status=' . e($status) . '" class="px-4 py-2 text-sm">Clear</a>';
echo '</form>';
echo '<div class="flex gap-2 mb-4 flex-wrap">';
foreach (['pending' => 'Pending', 'approved' => 'Approved', 'paid' => 'Paid', 'rejected' => 'Rejected', 'all' => 'All'] as $k => $label) {
    echo '<a href="/admin/withdrawals.php?status=' . $k . '" class="px-3 py-1.5 rounded-xl text-xs font-semibold ' . ($status === $k ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 shadow-sm') . '">' . $label . '</a>';
}
echo '</div>';
$next = ['pending' => ['approved', 'rejected'], 'approved' => ['paid', 'rejected']];
foreach ($rows as $r) {
    $hl = $focus === (int)$r['id'] ? ' ring-2 ring-blue-500' : '';
    echo '<div class="bg-white rounded-2xl p-4 shadow-sm mb-3' . $hl . '">';
    echo '<div class="flex flex-wrap justify-between gap-2 mb-2"><div><p class="font-bold text-sm">' . e($r['username']) . ' <span class="font-normal text-slate-400">' . e($r['email']) . '</span></p>';
    echo '<p class="text-sm"><strong>$' . e(number_format((float)$r['amount'], 2)) . '</strong> ' . e($r['currency_code']) . ' (≈' . e(number_format((float)$r['local_amount'], 2)) . ') via ' . e($r['method']) . '</p>';
    echo '<p class="text-xs text-slate-500">They currently have $' . e(number_format((float)$r['user_balance'], 2)) . ' · asked on ' . e(date('M d, Y g:i A', strtotime($r['created_at']))) . '</p>';
    $det = json_decode($r['details'] ?? '{}', true) ?: [];
    $detLabels = ['bank_name' => 'Bank', 'account_name' => 'Account name', 'account_number' => 'Account number', 'momo_provider' => 'Network', 'momo_name' => 'Registered name', 'momo_number' => 'Phone number', 'wallet_network' => 'Network', 'wallet_address' => 'Wallet address'];
    if ($det) { echo '<div class="text-xs bg-slate-50 rounded-xl p-2 mt-2"><p class="font-bold text-xs mb-1">Where to send it:</p>'; foreach ($det as $k => $v) echo '<p><span class="text-slate-400">' . e($detLabels[$k] ?? ucwords(str_replace('_', ' ', (string)$k))) . ':</span> ' . e((string)$v) . '</p>'; echo '</div>'; }
    if ($r['admin_note']) echo '<p class="text-xs text-slate-500 mt-1">Your note: ' . e($r['admin_note']) . '</p>';
    echo '</div><span class="text-xs font-bold px-2 py-1 rounded-full h-fit ' . ($r['status'] === 'pending' ? 'bg-yellow-100 text-yellow-700' : (($r['status'] === 'paid' || $r['status'] === 'approved') ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700')) . '">' . ($r['status'] === 'pending' ? 'Waiting' : e(ucfirst($r['status']))) . '</span></div>';
    if (isset($next[$r['status']])) {
        echo '<form method="POST" class="flex flex-wrap gap-2 items-center">' . csrf_field() . '<input type="hidden" name="id" value="' . (int)$r['id'] . '"><input type="hidden" name="back_status" value="' . e($status) . '">';
        echo '<input name="note" maxlength="255" placeholder="Note for the user (you must explain a rejection)" class="flex-1 min-w-[200px] border rounded-xl px-3 py-2 text-sm">';
        foreach ($next[$r['status']] as $to) {
            $btn = $to === 'rejected' ? 'bg-red-600' : ($to === 'paid' ? 'bg-emerald-600' : 'bg-green-600');
            $label = ['approved' => 'Approve', 'paid' => 'Mark Paid', 'rejected' => 'Reject'][$to];
            $q = $to === 'rejected' ? 'Are you sure you want to reject this withdrawal? The money goes back to the user. Choose Reject Withdrawal to confirm, or Cancel to go back.' : ($to === 'paid' ? 'Have you actually sent $' . number_format((float)$r['amount'], 2) . ' to ' . $r['username'] . '? Only confirm if the money has left.' : 'Approve $' . number_format((float)$r['amount'], 2) . ' for ' . $r['username'] . '?');
            echo '<button name="to" value="' . $to . '" ' . admin_confirm($q) . ' class="' . $btn . ' text-white px-4 py-2 rounded-xl text-sm font-semibold">' . $label . '</button>';
        }
        echo '</form>';
    }
    echo '<p class="mt-2"><a class="text-xs text-blue-600 font-semibold" href="/admin/user.php?id=' . (int)$r['user_id'] . '">View user</a></p>';
    echo '</div>';
}
if (!$rows) echo '<p class="text-sm text-slate-400">No withdrawals.</p>';
admin_pager($page, $pages, '/admin/withdrawals.php', ['status' => $status, 'q' => $search]);
admin_footer();

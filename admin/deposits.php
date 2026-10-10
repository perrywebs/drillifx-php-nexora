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
        if (isset($_POST['approve'])) { $r = admin_approve_deposit($admin, $id); admin_flash($r['ok'] ? 'success' : 'error', $r['msg']); }
        elseif (isset($_POST['reject'])) { $r = admin_reject_deposit($admin, $id, $_POST['reason'] ?? ''); admin_flash($r['ok'] ? 'success' : 'error', $r['msg']); }
    }
    redirect('/admin/deposits.php?status=' . urlencode($_GET['status'] ?? $_POST['back_status'] ?? 'pending'));
}
$status = $_GET['status'] ?? 'pending';
if (!in_array($status, ['pending', 'approved', 'rejected', 'all'], true)) $status = 'pending';
$search = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per = 20;
$where = ['1=1']; $params = [];
if ($status !== 'all') { $where[] = 'd.status=?'; $params[] = $status; }
if ($search !== '') { $where[] = '(u.username LIKE ? OR u.email LIKE ? OR d.reference LIKE ?)'; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }
$w = implode(' AND ', $where);
$st = $pdo->prepare("SELECT COUNT(*) c FROM deposits d JOIN users u ON u.id=d.user_id WHERE $w");
$st->execute($params);
$total = (int)$st->fetch()['c'];
$pages = max(1, (int)ceil($total / $per));
$page = min($page, $pages);
$off = ($page - 1) * $per;
$st = $pdo->prepare("SELECT d.*, u.username, u.email, t.name AS tier_name FROM deposits d JOIN users u ON u.id=d.user_id JOIN tiers t ON t.id=d.tier_id WHERE $w ORDER BY d.id DESC LIMIT $per OFFSET $off");
$st->execute($params);
$rows = $st->fetchAll();
admin_head('Deposits');
admin_sidebar($admin, 'deposits');
echo admin_flashes();
echo '<h1 class="text-xl font-bold text-slate-800 mb-1">Deposits</h1>';
echo '<p class="text-sm text-slate-500 mb-4">' . ($status === 'pending' ? 'These need you to check the payment and approve or reject.' : 'Showing ' . e($status) . ' deposits.') . ' ' . $total . ' in total.</p>';
echo '<form method="GET" class="bg-white rounded-2xl p-3 shadow-sm mb-4 flex flex-wrap gap-2">';
echo '<input type="hidden" name="status" value="' . e($status) . '">';
echo '<input name="q" value="' . e($search) . '" placeholder="Search person or email" class="flex-1 min-w-[200px] border rounded-xl px-3 py-2 text-sm">';
echo '<button class="bg-slate-900 text-white px-4 py-2 rounded-xl text-sm font-semibold">Search</button>';
if ($search !== '') echo '<a href="/admin/deposits.php?status=' . e($status) . '" class="px-4 py-2 text-sm">Clear</a>';
echo '</form>';
echo '<div class="flex gap-2 mb-4">';
foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'] as $k => $label) {
    echo '<a href="/admin/deposits.php?status=' . $k . '" class="px-3 py-1.5 rounded-xl text-xs font-semibold ' . ($status === $k ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 shadow-sm') . '">' . $label . '</a>';
}
echo '</div>';
foreach ($rows as $d) {
    echo '<div class="bg-white rounded-2xl p-4 shadow-sm mb-3">';
    echo '<div class="flex flex-wrap justify-between gap-2 mb-2"><div><p class="font-bold text-sm">' . e($d['username']) . ' <span class="font-normal text-slate-400">' . e($d['email']) . '</span></p>';
    echo '<p class="text-sm">' . e($d['tier_name']) . ' · $' . e(number_format((float)$d['amount'], 2)) . ' · <span class="text-slate-400">' . e(date('M d, Y g:i A', strtotime($d['created_at']))) . '</span></p>';
    if ($d['proof_path']) {
        $isPdf = strtolower(substr($d['proof_path'], -4)) === '.pdf';
        echo '<p class="text-xs mt-2"><button onclick="openProof(\'' . e(url('/admin/proof.php?id=' . (int)$d['id'])) . '\',' . ($isPdf ? 'true' : 'false') . ')" class="text-blue-600 font-semibold bg-blue-50 px-3 py-1.5 rounded-lg">View proof</button></p>';
    } else echo '<p class="text-xs mt-2 text-slate-400">No proof uploaded.</p>';
    if ($d['admin_note']) echo '<p class="text-xs text-slate-500 mt-1">Note: ' . e($d['admin_note']) . '</p>';
    echo '</div><span class="text-xs font-bold px-2 py-1 rounded-full h-fit ' . ($d['status'] === 'pending' ? 'bg-yellow-100 text-yellow-700' : ($d['status'] === 'approved' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700')) . '">' . e($d['status']) . '</span></div>';
    if ($d['status'] === 'pending') {
        echo '<div class="flex flex-wrap gap-2">';
        echo '<form method="POST" ' . admin_confirm('Approve this deposit? This will switch on ' . $d['tier_name'] . ' for ' . $d['username'] . '.') . '>' . csrf_field() . '<input type="hidden" name="id" value="' . (int)$d['id'] . '"><input type="hidden" name="back_status" value="' . e($status) . '"><button name="approve" class="bg-green-600 text-white px-4 py-2 rounded-xl text-sm font-semibold">Approve</button></form>';
        echo '<form method="POST" ' . admin_confirm('Reject this deposit? The user will be told why.') . ' class="flex flex-1 min-w-[240px] gap-2">' . csrf_field() . '<input type="hidden" name="id" value="' . (int)$d['id'] . '"><input type="hidden" name="back_status" value="' . e($status) . '"><input name="reason" required placeholder="Why are you rejecting it? (the user sees this)" class="flex-1 border rounded-xl px-3 py-2 text-sm"><button name="reject" class="bg-red-600 text-white px-4 py-2 rounded-xl text-sm font-semibold">Reject</button></form>';
        echo '</div>';
    }
    echo '<p class="mt-2"><a class="text-xs text-blue-600 font-semibold" href="/admin/user.php?id=' . (int)$d['user_id'] . '">View user</a></p>';
    echo '</div>';
}
if (!$rows) echo '<p class="text-sm text-slate-400">Nothing here.</p>';
admin_pager($page, $pages, '/admin/deposits.php', ['status' => $status, 'q' => $search]);
admin_proof_modal();
admin_footer();

<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/actions.php';
$admin = require_admin();
$canMoney = admin_can($admin, 'admin', 'finance');
$canStatus = admin_can($admin, 'admin');
$pdo = db();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) { admin_flash('error', 'That user could not be found.'); redirect('/admin/users.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) admin_flash('error', 'Something went wrong. Please try again.');
    elseif (isset($_POST['do_status']) && $canStatus) {
        $r = admin_set_user_status($admin, $id, $_POST['do_status'] === 'suspended' ? 'suspended' : 'active');
        admin_flash($r['ok'] ? 'success' : 'error', $r['msg']);
    } elseif (isset($_POST['do_tier']) && $canStatus) {
        $newTierId = (int)($_POST['new_tier'] ?? 0);
        if ($newTierId <= 0) { admin_flash('error', 'Please select a tier.'); }
        else {
            $tierCheck = $pdo->prepare('SELECT * FROM tiers WHERE id = ? LIMIT 1')->execute([$newTierId])->fetch();
            if (!$tierCheck) { admin_flash('error', 'Tier not found.'); }
            else {
                $pdo->prepare('UPDATE users SET tier_id = ?, display_name = ?, display_rating = ? WHERE id = ?')->execute([$newTierId, $tierCheck['display_name'] ?? '', $tierCheck['display_rating'] ?? '', $id]);
                admin_flash('success', 'User tier updated to ' . e($tierCheck['display_name'] ?? 'Tier') . '.');
            }
        }
        redirect('/admin/user.php?id=' . $id);
    } elseif (isset($_POST['direction']) && $canMoney) {
        $amt = abs((float)($_POST['amount'] ?? 0));
        if ($_POST['direction'] === 'debit') $amt = -$amt;
        $r = admin_adjust_balance($admin, $id, $amt, $_POST['reason'] ?? '');
        admin_flash($r['ok'] ? 'success' : 'error', $r['msg']);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        admin_flash('error', 'You do not have permission to do that.');
    }
    redirect('/admin/user.php?id=' . $id);
}

$st = $pdo->prepare('SELECT u.*, COALESCE(t.name,"Free Tier") tier_name FROM users u LEFT JOIN tiers t ON t.id=u.tier_id WHERE u.id=? LIMIT 1');
$st->execute([$id]);
$u = $st->fetch();
if (!$u) { admin_flash('error', 'That user could not be found.'); redirect('/admin/users.php'); }
$st = $pdo->prepare('SELECT COUNT(*) c FROM referrals WHERE referrer_id=?'); $st->execute([$id]); $refCount = (int)$st->fetch()['c'];
$st = $pdo->prepare('SELECT * FROM transactions WHERE user_id=? ORDER BY id DESC LIMIT 15'); $st->execute([$id]); $tx = $st->fetchAll();
$st = $pdo->prepare('SELECT r.*, u2.username AS referred FROM referrals r JOIN users u2 ON u2.id=r.referred_id WHERE r.referrer_id=? ORDER BY r.id DESC LIMIT 15'); $st->execute([$id]); $refs = $st->fetchAll();
$st = $pdo->prepare('SELECT * FROM spins WHERE user_id=? ORDER BY id DESC LIMIT 10'); $st->execute([$id]); $spins = $st->fetchAll();
$st = $pdo->prepare('SELECT * FROM hashes WHERE user_id=? ORDER BY id DESC LIMIT 10'); $st->execute([$id]); $hashes = $st->fetchAll();
$st = $pdo->prepare('SELECT * FROM withdrawals WHERE user_id=? ORDER BY id DESC LIMIT 10'); $st->execute([$id]); $wds = $st->fetchAll();
$st = $pdo->prepare('SELECT d.*, t.name AS tier_name FROM deposits d JOIN tiers t ON t.id=d.tier_id WHERE d.user_id=? ORDER BY d.id DESC LIMIT 10'); $st->execute([$id]); $deps = $st->fetchAll();
$st = $pdo->prepare('SELECT * FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 10'); $st->execute([$id]); $notifs = $st->fetchAll();
$st = $pdo->prepare('SELECT * FROM activity_log WHERE user_id=? ORDER BY id DESC LIMIT 10'); $st->execute([$id]); $acts = $st->fetchAll();
$st = $pdo->prepare('SELECT * FROM user_spin_outcomes WHERE user_id=? AND status="pending" ORDER BY id DESC'); $st->execute([$id]); $pendSpin = $st->fetchAll();
$st = $pdo->prepare('SELECT * FROM user_hash_outcomes WHERE user_id=? AND status="pending" ORDER BY id DESC'); $st->execute([$id]); $pendHash = $st->fetchAll();
$isOff = $u['status'] !== 'active';

admin_head($u['username']);
admin_sidebar($admin, 'users');
echo admin_flashes();
echo '<a href="/admin/users.php" class="text-sm text-blue-600 font-semibold">&larr; Back to all users</a>';
echo '<div class="bg-white rounded-2xl p-4 shadow-sm mt-2 mb-4">';
echo '<div class="flex flex-wrap items-center gap-4">';
echo '<div class="w-14 h-14 bg-slate-900 text-white rounded-full flex items-center justify-center text-xl font-bold">' . e(strtoupper(substr($u['username'], 0, 1))) . '</div>';
echo '<div class="flex-1 min-w-[200px]"><h1 class="text-lg font-bold">' . e($u['username']) . '</h1><p class="text-sm text-slate-500">' . e($u['email']) . '</p>';
echo '<p class="text-sm mt-1">Account: <strong class="' . ($isOff ? 'text-red-600' : 'text-green-600') . '">' . ($isOff ? 'Disabled' : 'Active') . '</strong> &nbsp;·&nbsp; Balance: <strong class="text-lg">$' . e(number_format((float)$u['balance'], 2)) . '</strong> &nbsp;·&nbsp; Plan: ' . e($u['tier_name']) . ' &nbsp;·&nbsp; Friends invited: ' . $refCount . '</p></div>';
if ($canStatus) {
    echo '<form method="POST" ' . admin_confirm($isOff ? 'Enable this account? They will be able to sign in again.' : 'Disable this account? They will be signed out and blocked immediately.') . '>' . csrf_field() . '<input type="hidden" name="id" value="' . $id . '">';
    echo '<input type="hidden" name="do_status" value="' . ($isOff ? 'active' : 'suspended') . '">';
    echo '<button class="px-4 py-2 rounded-xl text-sm font-semibold ' . ($isOff ? 'bg-green-600 text-white' : 'bg-red-600 text-white') . '">' . ($isOff ? 'Enable Account' : 'Disable Account') . '</button></form>';
}
echo '</div>';
if ($canMoney) {
    echo '<div class="grid md:grid-cols-2 gap-3 mt-4">';
    echo '<form method="POST" ' . admin_confirm('Add this money to ' . $u['username'] . '\'s balance?') . ' class="bg-green-50 border border-green-100 rounded-xl p-3 flex flex-wrap gap-2 items-end">' . csrf_field() . '<input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="direction" value="credit">';
    echo '<label class="text-xs text-slate-500 flex-1 min-w-[100px]">Amount ($)<br><input name="amount" type="number" step="0.01" min="0.01" required class="w-full border rounded-xl px-3 py-2 text-sm bg-white"></label>';
    echo '<label class="text-xs text-slate-500 flex-[2] min-w-[160px]">Why? (required)<br><input name="reason" required maxlength="200" placeholder="e.g. Bonus for ..." class="w-full border rounded-xl px-3 py-2 text-sm bg-white"></label>';
    echo '<button class="bg-green-600 text-white px-4 py-2 rounded-xl text-sm font-semibold">Add Money</button></form>';
    echo '<form method="POST" ' . admin_confirm('Remove this money from ' . $u['username'] . '\'s balance?') . ' class="bg-red-50 border border-red-100 rounded-xl p-3 flex flex-wrap gap-2 items-end">' . csrf_field() . '<input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="direction" value="debit">';
    echo '<label class="text-xs text-slate-500 flex-1 min-w-[100px]">Amount ($)<br><input name="amount" type="number" step="0.01" min="0.01" required class="w-full border rounded-xl px-3 py-2 text-sm bg-white"></label>';
    echo '<label class="text-xs text-slate-500 flex-[2] min-w-[160px]">Why? (required)<br><input name="reason" required maxlength="200" placeholder="e.g. Correction for ..." class="w-full border rounded-xl px-3 py-2 text-sm bg-white"></label>';
    echo '<button class="bg-red-600 text-white px-4 py-2 rounded-xl text-sm font-semibold">Remove Money</button></form>';
echo '</div>';
echo '<div class="bg-white rounded-2xl p-4 shadow-sm mt-4">';
echo '<h2 class="font-bold text-sm mb-3">Tier Assignment</h2>';
echo '<p class="text-sm text-slate-500 mb-3">Assign or change the user\'s current tier.</p>';
echo '<form method="POST" class="space-y-3">';
echo '<input type="hidden" name="id" value="' . $id . '">';
echo '<input type="hidden" name="do_tier" value="1">';
echo '<div class="grid md:grid-cols-2 gap-3">';
echo '<label class="text-sm text-slate-600">Current Tier</label>';
echo '<div class="flex items-center gap-2">';
echo '<span class="px-3 py-1 bg-gray-100 text-sm rounded font-mono" id="curTier">' . e($u['tier_name'] ?? 'Free Tier') . '</span>';
echo '</div>';
echo '</div>';
echo '<div class="grid md:grid-cols-3 gap-3">';
echo '<label class="text-sm text-slate-600">New Tier</label>';
echo '<select name="new_tier" class="mt-1 w-full border rounded-xl px-3 py-2 text-sm">';
echo '<option value="">-- Select tier --</option>';
$allTiers = $pdo->query("SELECT * FROM tiers ORDER BY active DESC, id")->fetchAll(PDO::FETCH_ASSOC);
foreach ($allTiers as $t): $act = $t['active'] ? 'bg-green-100' : 'bg-red-100'; $actCls = $t['active'] ? 'text-green-800' : 'text-red-800';
echo '<option value="' . $t['id'] . '" ' . ($u['tier_id'] == $t['id'] ? 'selected' : '') . ' class="' . $act . '">' . e($t['display_name'] ?? $t['name']) . ' ' . e($t['display_rating'] ?? '') . '</option>';
endforeach;
echo '</select>';
echo '</div>';
echo '</div>';
echo '<div class="flex gap-3">';
echo '<button type="submit" name="do_tier" value="assign" class="bg-indigo-600 text-white px-4 py-2 rounded text-sm font-semibold">Assign Tier</button>';
echo '<button type="button" onclick="if(confirm(\'Are you sure you want to change this user\'s tier?\')) window.location=\'/admin/user.php?id=' . $id . '\'" class="bg-slate-200 px-4 py-2 rounded text-sm">Cancel</button>';
echo '</div>';
echo '</form>';
echo '</div>';
}
echo '</div>';

admin_tabs('payments', [['payments', 'Money'], ['withdrawals', 'Withdrawals'], ['deposits', 'Deposits'], ['referrals', 'Friends'], ['games', 'Spin & Hash'], ['messages', 'Messages']]);
echo '<div data-tabpane="payments" class="bg-white rounded-2xl p-4 shadow-sm"><h2 class="font-bold text-sm mb-2">Money movements</h2>';
if ($tx) { echo '<div class="tablescroll"><table class="w-full text-sm min-w-[520px]">'; foreach ($tx as $t) {
    $neg = (float)$t['amount'] < 0;
    echo '<tr class="border-b border-slate-100 last:border-0"><td class="py-2">' . e($t['type']) . '<span class="block text-xs text-slate-400">' . e(date('M d, Y g:i A', strtotime($t['created_at']))) . '</span></td><td class="py-2 text-right font-bold ' . ($neg ? 'text-red-600' : 'text-green-600') . '">' . ($neg ? '−' : '+') . '$' . e(number_format(abs((float)$t['amount']), 2)) . '</td></tr>';
} echo '</table></div>'; } else echo '<p class="text-sm text-slate-400">No money movements yet.</p>';
echo '</div>';
echo '<div data-tabpane="withdrawals" style="display:none" class="bg-white rounded-2xl p-4 shadow-sm"><h2 class="font-bold text-sm mb-2">Withdrawal requests</h2>';
if ($wds) foreach ($wds as $w) echo '<div class="flex flex-wrap justify-between gap-1 py-2 border-b border-slate-100 last:border-0 text-sm"><span>$' . e(number_format((float)$w['amount'], 2)) . ' via ' . e($w['method']) . ' <span class="text-slate-400 text-xs">' . e(date('M d, Y', strtotime($w['created_at']))) . '</span></span><span class="text-xs font-bold">' . e(ucfirst($w['status'])) . '</span></div>';
else echo '<p class="text-sm text-slate-400">No withdrawals.</p>';
echo '<p class="mt-2"><a class="text-xs text-blue-600 font-semibold" href="/admin/withdrawals.php">Manage all withdrawals</a></p></div>';
echo '<div data-tabpane="deposits" style="display:none" class="bg-white rounded-2xl p-4 shadow-sm"><h2 class="font-bold text-sm mb-2">Deposits</h2>';
if ($deps) foreach ($deps as $d) echo '<div class="flex flex-wrap justify-between gap-1 py-2 border-b border-slate-100 last:border-0 text-sm"><span>' . e($d['tier_name']) . ' — $' . e(number_format((float)$d['amount'], 2)) . ' <span class="text-slate-400 text-xs">' . e(date('M d, Y', strtotime($d['created_at']))) . '</span></span><span class="text-xs font-bold">' . e(ucfirst($d['status'])) . '</span></div>';
else echo '<p class="text-sm text-slate-400">No deposits.</p>';
echo '<p class="mt-2"><a class="text-xs text-blue-600 font-semibold" href="/admin/deposits.php">Manage all deposits</a></p></div>';
echo '<div data-tabpane="referrals" style="display:none" class="bg-white rounded-2xl p-4 shadow-sm"><h2 class="font-bold text-sm mb-2">Friends they invited (' . $refCount . ')</h2>';
if ($refs) foreach ($refs as $r) echo '<div class="flex justify-between py-2 border-b border-slate-100 last:border-0 text-sm"><span>' . e($r['referred']) . '</span><span class="text-xs font-bold ' . ($r['status'] === 'earned' ? 'text-green-600' : 'text-amber-600') . '">' . ($r['status'] === 'earned' ? 'Reward paid' : 'Waiting') . '</span></div>';
else echo '<p class="text-sm text-slate-400">No invited friends yet.</p>';
echo '</div>';
echo '<div data-tabpane="games" style="display:none" class="grid lg:grid-cols-2 gap-4">';
echo '<div class="bg-white rounded-2xl p-4 shadow-sm"><h2 class="font-bold text-sm mb-2">Recent spins</h2>';
if ($spins) foreach ($spins as $s) echo '<div class="flex justify-between text-sm py-1 border-b border-slate-100 last:border-0"><span class="text-slate-400 text-xs">' . e(date('M d', strtotime($s['created_at']))) . '</span><span class="font-bold text-green-600">+$' . e(number_format((float)$s['reward'], 2)) . '</span></div>';
else echo '<p class="text-sm text-slate-400">No spins yet.</p>';
echo '<h2 class="font-bold text-sm mt-3 mb-2">Recent hashes</h2>';
if ($hashes) foreach ($hashes as $h) echo '<div class="flex justify-between text-sm py-1 border-b border-slate-100 last:border-0"><span class="text-slate-400 text-xs">' . e(date('M d', strtotime($h['created_at']))) . '</span><span class="font-bold text-green-600">+$' . e(number_format((float)$h['reward'], 4)) . '</span></div>';
else echo '<p class="text-sm text-slate-400">No hashes yet.</p>';
echo '</div>';
echo '<div class="bg-white rounded-2xl p-4 shadow-sm"><h2 class="font-bold text-sm mb-2">Promised results (used on their next try)</h2>';
if ($pendSpin) foreach ($pendSpin as $p) echo '<p class="text-sm py-1">Spin: <strong>$' . e(number_format((float)$p['amount'], 2)) . '</strong> <span class="text-xs text-amber-600 font-bold">Waiting</span> <a class="text-blue-600 text-xs font-semibold" href="/admin/spins.php">Manage</a></p>';
if ($pendHash) foreach ($pendHash as $p) echo '<p class="text-sm py-1">Hash: <strong>$' . e(number_format((float)$p['amount'], 4)) . '</strong> <span class="text-xs text-amber-600 font-bold">Waiting</span> <a class="text-blue-600 text-xs font-semibold" href="/admin/hashes.php">Manage</a></p>';
if (!$pendSpin && !$pendHash) echo '<p class="text-sm text-slate-400">None promised.</p>';
echo '</div></div>';
echo '<div data-tabpane="messages" style="display:none" class="bg-white rounded-2xl p-4 shadow-sm"><h2 class="font-bold text-sm mb-2">Messages sent to them</h2>';
if ($notifs) foreach ($notifs as $n) echo '<div class="py-2 border-b border-slate-100 last:border-0"><p class="text-sm font-semibold">' . e($n['title']) . '</p><p class="text-sm text-slate-600">' . e($n['message']) . '</p></div>';
else echo '<p class="text-sm text-slate-400">No messages yet.</p>';
echo '<h2 class="font-bold text-sm mt-3 mb-2">What they have done lately</h2>';
if ($acts) foreach ($acts as $a) echo '<p class="text-sm py-1 border-b border-slate-100 last:border-0">' . e($a['message']) . ' <span class="text-xs text-slate-400">' . e(date('M d, g:i A', strtotime($a['created_at']))) . '</span></p>';
else echo '<p class="text-sm text-slate-400">Nothing recorded yet.</p>';
echo '</div>';
admin_footer();

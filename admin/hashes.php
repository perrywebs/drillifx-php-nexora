<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/includes/layout.php';
$admin = require_admin(['super_admin', 'admin']);
$pdo = db();
function hash_amount_label($amount): string {
    return (float)$amount > 0 ? '$' . number_format((float)$amount, 4) : 'Normal reward';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) admin_flash('error', 'Something went wrong. Please try again.');
    elseif (isset($_POST['save_outcome'])) {
        $id = (int)($_POST['id'] ?? 0);
        $label = trim(mb_substr($_POST['label'] ?? '', 0, 60));
        $amount = round((float)($_POST['amount'] ?? 0), 4);
        $chance = max(0, (int)($_POST['chance'] ?? 0));
        $on = isset($_POST['on']) ? 1 : 0;
        if ($label === '' || $amount < 0) admin_flash('error', 'Please give the result a name and an amount of $0 or more. Use $0 for the normal reward.');
        else {
            if ($id > 0) {
                $pdo->prepare('UPDATE hash_outcomes SET label=?, amount=?, weight=?, active=? WHERE id=?')->execute([$label, $amount, $chance, $on, $id]);
                admin_log((int)$admin['id'], 'hash_config', "Changed hash result #$id ($label)", 'hash_outcome', $id);
            } else {
                $pdo->prepare('INSERT INTO hash_outcomes (label, amount, weight, active, sort) VALUES (?,?,?,?,(SELECT COALESCE(MAX(sort),0)+1 FROM (SELECT sort FROM hash_outcomes) s))')->execute([$label, $amount, $chance, $on]);
                admin_log((int)$admin['id'], 'hash_config', "Added hash result ($label)", 'hash_outcome', (int)$pdo->lastInsertId());
            }
            admin_flash('success', 'Saved. The new chances apply to hashes right away.');
        }
    } elseif (isset($_POST['remove_outcome'])) {
        $id = (int)($_POST['id'] ?? 0);
        $st = $pdo->prepare('SELECT COUNT(*) c FROM user_hash_outcomes WHERE outcome_id=? AND status="pending"');
        $st->execute([$id]);
        if ((int)$st->fetch()['c'] > 0) admin_flash('error', 'You cannot remove this result because someone is still waiting for it.');
        else {
            $pdo->prepare('DELETE FROM hash_outcomes WHERE id=?')->execute([$id]);
            admin_log((int)$admin['id'], 'hash_config', "Removed hash result #$id", 'hash_outcome', $id);
            admin_flash('success', 'Result removed.');
        }
    } elseif (isset($_POST['give'])) {
        $who = trim($_POST['username'] ?? '');
        $amount = round((float)($_POST['amount'] ?? 0), 4);
        $st = $pdo->prepare('SELECT id, username FROM users WHERE username=? LIMIT 1');
        $st->execute([$who]);
        $t = $st->fetch();
        if (!$t) admin_flash('error', 'Please search for and select a user first.');
        elseif ($amount < 0) admin_flash('error', 'Please enter an amount of $0 or more.');
        else {
            $pdo->prepare('INSERT INTO user_hash_outcomes (user_id, outcome_id, amount, assigned_by) VALUES (?,?,?,?)')->execute([(int)$t['id'], null, $amount, (int)$admin['id']]);
            admin_log((int)$admin['id'], 'hash_assign', 'Promised ' . $t['username'] . ' a hash result of $' . number_format($amount, 4), 'user', (int)$t['id']);
            admin_flash('success', 'Specific hash result saved for ' . $t['username'] . '. They will get it on their next hash.');
        }
    } elseif (isset($_POST['cancel_assign'])) {
        $aid = (int)($_POST['aid'] ?? 0);
        $st = $pdo->prepare('SELECT uho.*, u.username FROM user_hash_outcomes uho JOIN users u ON u.id=uho.user_id WHERE uho.id=? AND uho.status="pending" LIMIT 1');
        $st->execute([$aid]);
        $a = $st->fetch();
        if (!$a) admin_flash('error', 'That result is no longer waiting.');
        else {
            $pdo->prepare('UPDATE user_hash_outcomes SET status="cancelled" WHERE id=?')->execute([$aid]);
            admin_log((int)$admin['id'], 'hash_assign_cancel', 'Took back the promised hash result for ' . $a['username'], 'user', (int)$a['user_id']);
            admin_flash('success', 'Removed. ' . $a['username'] . ' will now get a normal result.');
        }
    }
    redirect('/admin/hashes.php');
}

$outcomes = $pdo->query('SELECT * FROM hash_outcomes ORDER BY sort, id')->fetchAll();
$totalW = array_sum(array_map(fn($o) => $o['active'] ? (int)$o['weight'] : 0, $outcomes));
$waiting = $pdo->query('SELECT uho.*, u.username, u.email FROM user_hash_outcomes uho JOIN users u ON u.id=uho.user_id WHERE uho.status="pending" ORDER BY uho.id DESC LIMIT 20')->fetchAll();
$used = $pdo->query('SELECT uho.*, u.username FROM user_hash_outcomes uho JOIN users u ON u.id=uho.user_id WHERE uho.status="used" ORDER BY uho.used_at DESC LIMIT 10')->fetchAll();
$recent = $pdo->query('SELECT h.*, u.username FROM hashes h JOIN users u ON u.id=h.user_id ORDER BY h.id DESC LIMIT 10')->fetchAll();
$enabled = cfg_flag('hash_enabled', true);

admin_head('Hash');
admin_sidebar($admin, 'hashes');
echo admin_flashes();
echo '<h1 class="text-xl font-bold text-slate-800 mb-1">Hash</h1>';
echo '<p class="text-sm text-slate-500 mb-4">Hash is currently <strong>' . ($enabled ? 'on' : 'off') . '</strong>. You can switch it on or off in Settings → General.</p>';
if ($totalW <= 0) echo '<div class="border bg-red-50 border-red-200 text-red-700 rounded-xl p-3 mb-4 text-sm">No results are switched on, so hashing cannot work right now. Switch at least one result on below.</div>';

echo '<div class="bg-white rounded-2xl p-4 shadow-sm mb-4"><h2 class="font-bold text-sm mb-1">Hash chances</h2><p class="text-xs text-slate-400 mb-3">Use $0 for the normal reward (the amount depends on the user\'s plan). Bigger chance number = more likely.</p>';
echo '<div class="tablescroll"><table class="w-full text-sm min-w-[620px]">';
echo '<tr class="text-left text-xs text-slate-400 border-b"><th class="p-2">Result</th><th class="p-2">Amount</th><th class="p-2">Chance</th><th class="p-2">Switched on</th><th class="p-2"></th></tr>';
foreach ($outcomes as $o) {
    $pct = ($totalW > 0 && $o['active']) ? round((int)$o['weight'] / $totalW * 100, 1) . '%' : '—';
    echo '<form method="POST"><tr class="border-b border-slate-100 last:border-0">';
    echo '<td class="p-2"><input name="label" value="' . e($o['label']) . '" class="border rounded-lg px-2 py-1 text-sm w-32">' . csrf_field() . '<input type="hidden" name="id" value="' . (int)$o['id'] . '"><input type="hidden" name="save_outcome" value="1"></td>';
    echo '<td class="p-2">$<input name="amount" type="number" step="0.0001" min="0" value="' . e((string)$o['amount']) . '" class="border rounded-lg px-2 py-1 text-sm w-24"></td>';
    echo '<td class="p-2"><input name="chance" type="number" min="0" value="' . (int)$o['weight'] . '" class="border rounded-lg px-2 py-1 text-sm w-20"> <span class="font-bold text-blue-600 text-xs chancepct">' . $pct . '</span></td>';
    echo '<td class="p-2"><input type="checkbox" name="on" value="1"' . ($o['active'] ? ' checked' : '') . ' class="w-4 h-4"></td>';
    echo '<td class="p-2 whitespace-nowrap"><button class="bg-slate-900 text-white px-3 py-1.5 rounded-lg text-xs font-semibold">Save</button> ';
    echo '<button name="remove_outcome" value="1" ' . admin_confirm('Remove "' . $o['label'] . '"?') . ' class="text-red-600 text-xs font-semibold px-2 py-1.5">Remove</button></td></tr></form>';
}
echo '</table></div>';
echo '<form method="POST" class="flex flex-wrap items-end gap-2 mt-3">' . csrf_field() . '<input type="hidden" name="save_outcome" value="1">';
echo '<label class="text-xs text-slate-500">Result name<br><input name="label" required placeholder="e.g. Big hash" class="border rounded-xl px-3 py-2 text-sm"></label>';
echo '<label class="text-xs text-slate-500">Amount ($, 0 = normal)<br><input name="amount" type="number" step="0.0001" min="0" value="0" class="border rounded-xl px-3 py-2 text-sm w-32"></label>';
echo '<label class="text-xs text-slate-500">Chance number<br><input name="chance" type="number" min="1" value="100" class="border rounded-xl px-3 py-2 text-sm w-24"></label>';
echo '<label class="text-xs text-slate-500 flex items-center gap-1 pb-2"><input type="checkbox" name="on" value="1" checked class="w-4 h-4"> On</label>';
echo '<button class="bg-blue-600 text-white px-4 py-2 rounded-xl text-sm font-semibold">Add result</button></form>';
echo '</div>';

echo '<div class="bg-white rounded-2xl p-4 shadow-sm mb-4"><h2 class="font-bold text-sm mb-1">Give a user a specific hash result</h2><p class="text-xs text-slate-400 mb-3">They will get exactly this on their next hash, instead of the normal one.</p>';
echo '<form method="POST" ' . admin_confirm('Save this specific result for the selected user?') . ' class="space-y-3">' . csrf_field() . '<input type="hidden" name="give" value="1">';
echo '<div><p class="text-sm font-semibold mb-1">1. Find the user</p>';
admin_user_search('username', 'Type at least 2 letters of a name or email...');
echo '</div><div><p class="text-sm font-semibold mb-1">2. Enter the amount ($)</p><input name="amount" type="number" step="0.0001" min="0" required placeholder="e.g. 5.00" class="w-full border rounded-xl px-3 py-2 text-sm"></div><button class="bg-slate-900 text-white px-6 py-2.5 rounded-xl text-sm font-semibold">Save</button></form></div>';

echo '<div class="grid lg:grid-cols-2 gap-4">';
echo '<div class="bg-white rounded-2xl p-4 shadow-sm"><h2 class="font-bold text-sm mb-2">People waiting for a specific result</h2>';
if ($waiting) {
    echo '<div class="tablescroll"><table class="w-full text-sm min-w-[420px]"><tr class="text-left text-xs text-slate-400 border-b"><th class="p-2">User</th><th class="p-2">Result</th><th class="p-2">Status</th><th class="p-2"></th></tr>';
    foreach ($waiting as $p) {
        echo '<tr class="border-b border-slate-100 last:border-0"><td class="p-2 font-semibold">' . e($p['username']) . '<span class="block text-xs font-normal text-slate-400">' . e($p['email']) . '</span></td><td class="p-2 font-bold text-green-600">' . e(hash_amount_label($p['amount'])) . '</td><td class="p-2"><span class="text-xs font-bold px-2 py-1 rounded-full bg-yellow-100 text-yellow-700">Waiting</span></td>';
        echo '<td class="p-2"><form method="POST" class="inline" ' . admin_confirm('Take back the promised result for ' . $p['username'] . '?') . '>' . csrf_field() . '<input type="hidden" name="aid" value="' . (int)$p['id'] . '"><button name="cancel_assign" class="text-red-600 text-xs font-semibold">Remove</button></form></td></tr>';
    }
    echo '</table></div>';
} else echo '<p class="text-sm text-slate-400">Nobody is waiting for a specific result.</p>';
echo '<h3 class="font-bold text-xs mt-4 mb-1 text-slate-500">ALREADY GIVEN OUT</h3>';
if ($used) foreach ($used as $p) echo '<div class="flex justify-between text-sm py-1 border-b border-slate-100 last:border-0"><span>' . e($p['username']) . ': <strong>' . e(hash_amount_label($p['amount'])) . '</strong></span><span class="text-xs font-bold px-2 py-1 rounded-full bg-slate-100 text-slate-500">Used</span></div>';
else echo '<p class="text-sm text-slate-400">None yet.</p>';
echo '</div>';
echo '<div class="bg-white rounded-2xl p-4 shadow-sm"><h2 class="font-bold text-sm mb-2">Recent hashes</h2>';
foreach ($recent as $s) echo '<div class="flex justify-between text-sm py-1 border-b border-slate-100 last:border-0"><span>' . e($s['username']) . '</span><span class="font-bold text-green-600">+$' . e(number_format((float)$s['reward'], 4)) . '</span></div>';
if (!$recent) echo '<p class="text-sm text-slate-400">No hashes yet.</p>';
echo '</div></div>';
echo <<<'JS'
<script>
document.querySelectorAll('input[name=chance]').forEach(function(inp){
  inp.addEventListener('input', function(){
    var total = 0;
    document.querySelectorAll('input[name=chance]').forEach(function(o){ total += Math.max(0, parseInt(o.value || '0', 10)); });
    document.querySelectorAll('.chancepct').forEach(function(el, i){
      var w = Math.max(0, parseInt(document.querySelectorAll('input[name=chance]')[i].value || '0', 10));
      el.textContent = total > 0 ? (Math.round(w / total * 1000) / 10) + '%' : '—';
    });
  });
});
</script>
JS;
admin_footer();

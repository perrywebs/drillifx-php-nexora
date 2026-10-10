<?php
declare(strict_types=1);
// General application settings page - consolidated tier system
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/includes/layout.php';
$admin = require_admin(['super_admin', 'admin']);
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) admin_flash('error', 'Invalid session.');
    else {
        $prevVerify = setting('email_verification', '0');
        $fields = [
            'site_name','site_title','site_description','support_email','contact_email','timezone','currency',
            'registration_enabled','referral_enabled','email_verification','mail_enabled',
            'min_deposit','min_withdraw','fx_USD','fx_NGN','fx_GHS','referral_reward',
            'spin_enabled','hash_enabled','maintenance_mode',
        ];
        $changed = [];
        foreach ($fields as $f) {
            $v = $_POST[$f] ?? null;
            if (in_array($f, ['registration_enabled','referral_enabled','email_verification','mail_enabled','spin_enabled','hash_enabled','maintenance_mode'], true)) $v = isset($_POST[$f]) ? '1' : '0';
            $v = is_string($v) ? trim($v) : $v;
            if ($f === 'support_email' || $f === 'contact_email') { if ($v !== '' && !is_valid_email($v)) { admin_flash('error', "Invalid email for $f."); redirect('/admin/settings.php'); } }
            if (in_array($f, ['min_deposit','min_withdraw','fx_USD','fx_NGN','fx_GHS','referral_reward'], true)) { if (!is_numeric($v) || (float)$v < 0) { admin_flash('error', "Invalid number for $f."); redirect('/admin/settings.php'); } }
            if ((string)setting($f, '') !== (string)$v) { set_setting($f, $v); $changed[] = $f; }
        }
        // Enabling email verification must not lock out existing users: backfill them as verified
        if ($prevVerify !== '1' && setting('email_verification', '0') === '1') {
            $pdo->exec("UPDATE users SET email_verified_at = COALESCE(email_verified_at, NOW()) WHERE email_verified_at IS NULL");
        }
        admin_log((int)$admin['id'], 'settings_change', 'Settings updated: ' . ($changed ? implode(', ', $changed) : 'no changes'));
        admin_flash('success', 'Settings saved' . ($changed ? ' (' . count($changed) . ' changed).' : '.'));
    }
    redirect('/admin/settings.php');
}

$v = fn($k, $d = '') => e((string)setting($k, $d));
$c = fn($k, $d = true) => cfg_flag($k, $d) ? ' checked' : '';
admin_head('Settings');
admin_sidebar($admin, 'settings');
echo admin_flashes();
echo '<h1 class="text-xl font-bold text-slate-800 mb-1">General Settings</h1>';
echo '<p class="text-sm text-slate-500 mb-4">Changes here take effect immediately across the site.</p>';
echo '<form method="POST" class="space-y-4">' . csrf_field();
echo '<div class="bg-white rounded-2xl p-4 shadow-sm"><h2 class="font-bold text-sm mb-3">General</h2><div class="grid md:grid-cols-2 gap-3">';
echo '<label class="text-sm text-slate-600">Site name<input name="site_name" value="' . e(site_name()) . '" class="mt-1 w-full border rounded-xl px-3 py-2 text-sm"></label>';
echo '<label class="text-sm text-slate-600">Site title<input name="site_title" value="' . e(site_title()) . '" class="mt-1 w-full border rounded-xl px-3 py-2 text-sm"></label>';
echo '<label class="text-sm text-slate-600 md:col-span-2">Site description<textarea name="site_description" class="mt-1 w-full border rounded-xl px-3 py-2 text-sm">' . e(setting('site_description', '')) . '</textarea></label>';
echo '<label class="text-sm flex items-center gap-2"><input type="checkbox" name="maintenance_mode" value="1"' . $c('maintenance_mode', false) . '> Maintenance mode (users see a notice page)</label>';
echo '</div></div>';
echo '<div class="bg-white rounded-2xl p-4 shadow-sm"><h2 class="font-bold text-sm mb-3">User settings</h2><div class="grid md:grid-cols-2 gap-3 text-sm">';
echo '<label class="flex items-center gap-2"><input type="checkbox" name="registration_enabled" value="1"' . $c('registration_enabled') . '> Registration open</label>';
echo '<label class="flex items-center gap-2"><input type="checkbox" name="referral_enabled" value="1"' . $c('referral_enabled') . '> Referral system enabled</label>';
echo '<label class="flex items-center gap-2"><input type="checkbox" name="email_verification" value="1"' . $c('email_verification', false) . '> Require email verification (existing users auto-verified on enable)</label>';
echo '<label class="flex items-center gap-2"><input type="checkbox" name="mail_enabled" value="1"' . $c('mail_enabled') . '> Email notifications enabled</label>';
echo '</div></div>';
echo '<div class="bg-white rounded-2xl p-4 shadow-sm"><h2 class="font-bold text-sm mb-3">Financial</h2><div class="grid md:grid-cols-3 gap-3">';
echo '<label class="text-sm text-slate-600">Min deposit (USD)<input name="min_deposit" type="number" step="any" min="0" value="' . e(cfg_min_deposit()) . '" class="mt-1 w-full border rounded-xl px-3 py-2 text-sm"></label>';
echo '<label class="text-sm text-slate-600">Min withdrawal (USD)<input name="min_withdraw" type="number" step="any" min="0" value="' . e(cfg_min_withdraw()) . '" class="mt-1 w-full border rounded-xl px-3 py-2 text-sm"></label>';
echo '<label class="text-sm text-slate-600">Referral reward (USD)<input name="referral_reward" type="number" step="any" min="0" value="' . e(cfg_referral_reward()) . '" class="mt-1 w-full border rounded-xl px-3 py-2 text-sm"></label>';
echo '<label class="text-sm text-slate-600">USD rate<input name="fx_USD" type="number" step="any" min="0" value="' . e($v('fx_USD', '1')) . '" class="mt-1 w-full border rounded-xl px-3 py-2 text-sm"></label>';
echo '<label class="text-sm text-slate-600">NGN rate<input name="fx_NGN" type="number" step="any" min="0" value="' . e($v('fx_NGN', '1600')) . '" class="mt-1 w-full border rounded-xl px-3 py-2 text-sm"></label>';
echo '<label class="text-sm text-slate-600">GHS rate<input name="fx_GHS" type="number" step="any" min="0" value="' . e($v('fx_GHS', '21.6')) . '" class="mt-1 w-full border rounded-xl px-3 py-2 text-sm"></label>';
echo '</div></div>';
echo '<div class="bg-white rounded-2xl p-4 shadow-sm"><h2 class="font-bold text-sm mb-3">Games</h2><div class="grid md:grid-cols-2 gap-3 text-sm">';
echo '<label class="flex items-center gap-2"><input type="checkbox" name="spin_enabled" value="1"' . $c('spin_enabled') . '> Spin enabled</label>';
echo '<label class="flex items-center gap-2"><input type="checkbox" name="hash_enabled" value="1"' . $c('hash_enabled') . '> Hash enabled</label>';
echo '</div></div>';
echo '<div class="mt-4">';
echo '<button class="bg-indigo-600 text-white px-4 py-2 rounded text-sm font-semibold mb-3">Update Settings</button>';
echo '</div></div>';

$u = require_login();
$pdo = db();
$options = $pdo->query("SELECT * FROM tiers WHERE active = 1 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
echo '<h2 class="text-xl font-bold text-slate-800 mb-4">Current Tiers</h2>';
echo '<p class="text-sm text-slate-500 mb-4">Unified tier system - manage tiers from the dedicated admin tier page.</p>';
echo '<div class="bg-white rounded-2xl p-4 shadow-sm">';
echo '<h3 class="font-bold text-sm mb-3">Active Tiers</h3>';
echo '<table class="w-full text-sm min-w-[640px]">';
echo '<thead><tr class="text-left text-xs text-slate-400 border-b"><th class="p-3">Display Name</th><th class="p-3">Rating</th><th class="p-3">Hashes/Spins</th><th class="p-3">Price</th><th class="p-3">Duration</th><th class="p-3">Referral</th><th class="p-3">Status</th><th class="p-3 text-right">Actions</th></tr></thead>';
echo '<tbody>';
foreach ($options as $t) {
    $uc = $userCountByTier[$t['id']] ?? 0; 
    // We need to get user count - let's add it inline
    $ucStmt = $pdo->query("SELECT COUNT(*) c FROM users WHERE tier_id = " . (int)$t['id']);
    $uc = $ucStmt->fetchColumn();
    $isAct = $t['active'] ? 'Active' : 'Inactive';
    $bCls = $t['active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
    echo '<tr class="border-b border-slate-100 last:border-0' . ($t['active'] ? ' bg-green-50' : ' bg-red-50') . '>';
    echo '<td class="p-3 font-medium">' . e($t['display_name'] ?? $t['name']) . '</td>';
    echo '<td class="p-3 text-center">' . e($t['display_rating'] ?? '') . '</td>';
    echo '<td class="p-3 text-center">' . (int)$t['daily_hashes'] . 'H / ' . (int)$t['daily_spins'] . 'S</td>';
    echo '<td class="p-3 text-right">$' . number_format((float)$t['price'], 2) . '</td>';
    echo '<td class="p-3 text-center">' . (int)$t['duration_days'] . 'd</td>';
    echo '<td class="p-3 text-center">' . (int)$t['referral_requirement'] . ($t['requirement_type'] === 'threshold' ? ' (more than)' : '') . '</td>';
    echo '<td class="p-3"><span class="px-2 py-1 rounded text-xs font-semibold ' . $bCls . '">' . $isAct . '</span></td>';
    echo '<td class="p-3 text-right">';
    echo '<a href="/admin/tiers.php?action=edit&tier_id=' . $t['id'] . '" class="text-blue-600 text-xs hover:underline">Edit</a> ';
    echo ($uc == 0 ? '<a href="/admin/tiers.php?action=delete&tier_id=' . $t['id'] . '" class="text-red-600 text-xs hover underline" onclick="return confirm(\'Delete this tier permanently? This will not affect user records.\');">Delete</a>' : '<span class="text-gray-400 text-xs">Delete (' . $uc . ' users)</span>');
    echo '</td>';
    echo '</tr>';
}
echo '</tbody>';
echo '</table>';
echo '</div>';

echo '<file_sep><!-- General Settings -->';
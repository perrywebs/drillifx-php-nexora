<?php
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
foreach ([['site_name','Site name'],['site_title','Site title'],['contact_email','Contact email'],['support_email','Support email'],['timezone','Timezone'],['currency','Currency (code)']] as [$k,$label]) {
    echo '<label class="text-sm text-slate-600">' . $label . '<input name="' . $k . '" value="' . $v($k) . '" class="mt-1 w-full border rounded-xl px-3 py-2 text-sm"></label>';
}
echo '<label class="text-sm text-slate-600 md:col-span-2">Site description<textarea name="site_description" class="mt-1 w-full border rounded-xl px-3 py-2 text-sm">' . $v('site_description') . '</textarea></label>';
echo '<label class="text-sm flex items-center gap-2"><input type="checkbox" name="maintenance_mode" value="1"' . $c('maintenance_mode', false) . '> Maintenance mode (users see a notice page)</label>';
echo '</div></div>';
echo '<div class="bg-white rounded-2xl p-4 shadow-sm"><h2 class="font-bold text-sm mb-3">User settings</h2><div class="grid md:grid-cols-2 gap-3 text-sm">';
echo '<label class="flex items-center gap-2"><input type="checkbox" name="registration_enabled" value="1"' . $c('registration_enabled') . '> Registration open</label>';
echo '<label class="flex items-center gap-2"><input type="checkbox" name="referral_enabled" value="1"' . $c('referral_enabled') . '> Referral system enabled</label>';
echo '<label class="flex items-center gap-2"><input type="checkbox" name="email_verification" value="1"' . $c('email_verification', false) . '> Require email verification (existing users auto-verified on enable)</label>';
echo '<label class="flex items-center gap-2"><input type="checkbox" name="mail_enabled" value="1"' . $c('mail_enabled') . '> Email notifications enabled</label>';
echo '</div></div>';
echo '<div class="bg-white rounded-2xl p-4 shadow-sm"><h2 class="font-bold text-sm mb-3">Financial</h2><div class="grid md:grid-cols-3 gap-3">';
foreach ([['min_deposit','Min deposit (USD)'],['min_withdraw','Min withdrawal (USD)'],['referral_reward','Referral reward (USD)'],['fx_USD','USD rate'],['fx_NGN','NGN rate'],['fx_GHS','GHS rate']] as [$k,$label]) {
    echo '<label class="text-sm text-slate-600">' . $label . '<input name="' . $k . '" type="number" step="any" min="0" value="' . $v($k, '0') . '" class="mt-1 w-full border rounded-xl px-3 py-2 text-sm"></label>';
}
echo '</div></div>';
echo '<div class="bg-white rounded-2xl p-4 shadow-sm"><h2 class="font-bold text-sm mb-3">Games</h2><div class="grid md:grid-cols-2 gap-3 text-sm">';
echo '<label class="flex items-center gap-2"><input type="checkbox" name="spin_enabled" value="1"' . $c('spin_enabled') . '> Spin enabled</label>';
echo '<label class="flex items-center gap-2"><input type="checkbox" name="hash_enabled" value="1"' . $c('hash_enabled') . '> Hash enabled</label>';
echo '</div></div>';
echo '<button class="bg-slate-900 text-white px-6 py-2.5 rounded-xl text-sm font-semibold">Save</button></form>';
admin_footer();

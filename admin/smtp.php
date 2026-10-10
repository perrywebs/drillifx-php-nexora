<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/../includes/mail.php';
$admin = require_admin(['super_admin', 'admin']);
$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) admin_flash('error', 'Invalid session.');
    elseif (isset($_POST['save_smtp'])) {
        $fields = ['smtp_host','smtp_port','smtp_user','smtp_enc','mail_from_email','mail_from_name'];
        $changed = [];
        foreach ($fields as $f) {
            $val = trim($_POST[$f] ?? '');
            if ($f === 'smtp_port' && ($val === '' || !ctype_digit($val))) { admin_flash('error', 'SMTP port must be a number.'); redirect('/admin/smtp.php'); }
            if ($f === 'mail_from_email' && $val !== '' && !is_valid_email($val)) { admin_flash('error', 'Invalid sender email.'); redirect('/admin/smtp.php'); }
            if (!in_array($f, ['smtp_enc'], true) || in_array($val, ['tls', 'ssl', 'none'], true)) {
                if ((string)setting($f, '') !== $val) { set_setting($f, $val); $changed[] = $f; }
            }
        }
        if (array_key_exists('smtp_pass', $_POST) && $_POST['smtp_pass'] !== '') {
            set_setting('smtp_pass', $_POST['smtp_pass']);
            $changed[] = 'smtp_pass';
        }
        admin_log((int)$admin['id'], 'smtp_change', 'SMTP settings updated: ' . ($changed ? implode(', ', array_map(fn($f) => $f === 'smtp_pass' ? 'smtp_pass=***' : $f, $changed)) : 'no changes'));
        admin_flash('success', 'SMTP settings saved.');
        redirect('/admin/smtp.php');
    } elseif (isset($_POST['send_test'])) {
        $to = trim($_POST['test_email'] ?? '');
        if (!is_valid_email($to)) admin_flash('error', 'Enter a valid test email address.');
        else {
            $ok = send_template($to, 'test_email', [], null);
            $result = $ok ? 'Test email sent successfully.' : 'Test email failed. Check the email log below for the reason.';
            admin_log((int)$admin['id'], 'smtp_test', 'Test email to ' . $to . ': ' . ($ok ? 'sent' : 'failed'));
            admin_flash($ok ? 'success' : 'error', $result);
        }
        redirect('/admin/smtp.php');
    }
}
$v = fn($k, $d = '') => e((string)setting($k, $d));
$passSet = trim((string)setting('smtp_pass', '')) !== '';
$logs = db()->query('SELECT * FROM email_logs ORDER BY id DESC LIMIT 15')->fetchAll();
admin_head('SMTP & Email');
admin_sidebar($admin, 'smtp');
echo admin_flashes();
echo '<h1 class="text-xl font-bold text-slate-800 mb-4">SMTP & Email</h1>';
echo '<div class="grid lg:grid-cols-2 gap-4">';
echo '<form method="POST" class="bg-white rounded-2xl p-4 shadow-sm space-y-3">' . csrf_field() . '<input type="hidden" name="save_smtp" value="1">';
echo '<h2 class="font-bold text-sm">SMTP configuration</h2>';
echo '<label class="block text-sm text-slate-600">SMTP host<input name="smtp_host" value="' . $v('smtp_host') . '" placeholder="mail.example.com" class="mt-1 w-full border rounded-xl px-3 py-2 text-sm"></label>';
echo '<div class="grid grid-cols-2 gap-3"><label class="block text-sm text-slate-600">Port<input name="smtp_port" value="' . $v('smtp_port', '587') . '" class="mt-1 w-full border rounded-xl px-3 py-2 text-sm"></label>';
echo '<label class="block text-sm text-slate-600">Encryption<select name="smtp_enc" class="mt-1 w-full border rounded-xl px-3 py-2 text-sm">';
foreach (['tls' => 'TLS (STARTTLS)', 'ssl' => 'SSL (implicit)', 'none' => 'None'] as $k => $label) echo '<option value="' . $k . '"' . (setting('smtp_enc', 'tls') === $k ? ' selected' : '') . '>' . $label . '</option>';
echo '</select></label></div>';
echo '<label class="block text-sm text-slate-600">SMTP username<input name="smtp_user" value="' . $v('smtp_user') . '" autocomplete="off" class="mt-1 w-full border rounded-xl px-3 py-2 text-sm"></label>';
echo '<label class="block text-sm text-slate-600">SMTP password ' . ($passSet ? '<span class="text-xs text-green-600 font-semibold">(saved •••••••• — leave blank to keep)</span>' : '<span class="text-xs text-slate-400">(not set)</span>') . '<input type="password" name="smtp_pass" value="" autocomplete="new-password" placeholder="' . ($passSet ? '••••••••' : 'Enter password') . '" class="mt-1 w-full border rounded-xl px-3 py-2 text-sm"></label>';
echo '<label class="block text-sm text-slate-600">From email<input name="mail_from_email" value="' . $v('mail_from_email') . '" class="mt-1 w-full border rounded-xl px-3 py-2 text-sm"></label>';
echo '<label class="block text-sm text-slate-600">From name<input name="mail_from_name" value="' . $v('mail_from_name') . '" class="mt-1 w-full border rounded-xl px-3 py-2 text-sm"></label>';
echo '<button class="bg-slate-900 text-white px-6 py-2.5 rounded-xl text-sm font-semibold">Save SMTP</button></form>';
echo '<div class="space-y-4"><form method="POST" class="bg-white rounded-2xl p-4 shadow-sm space-y-3">' . csrf_field() . '<input type="hidden" name="send_test" value="1">';
echo '<h2 class="font-bold text-sm">Send test email</h2><p class="text-xs text-slate-500">Uses the saved configuration above.</p>';
echo '<input name="test_email" type="email" required placeholder="you@example.com" class="w-full border rounded-xl px-3 py-2 text-sm">';
echo '<button class="bg-blue-600 text-white px-6 py-2.5 rounded-xl text-sm font-semibold">Send Test Email</button></form>';
echo '<div class="bg-white rounded-2xl p-4 shadow-sm"><div class="flex justify-between items-center mb-2"><h2 class="font-bold text-sm">Email log</h2><a class="text-xs text-blue-600 font-semibold" href="/admin/audit.php">Audit logs</a></div>';
foreach ($logs as $l) {
    echo '<div class="text-xs py-1.5 border-b border-slate-100 last:border-0"><span class="font-semibold">' . e($l['recipient']) . '</span> <span class="text-slate-400">' . e($l['template_slug']) . '</span> <span class="font-bold ' . ($l['status'] === 'sent' ? 'text-green-600' : ($l['status'] === 'failed' ? 'text-red-600' : 'text-slate-400')) . '">' . e($l['status']) . '</span><span class="text-slate-400"> ' . e($l['created_at']) . '</span>' . ($l['error'] ? '<br><span class="text-red-500">' . e(mb_substr($l['error'], 0, 160)) . '</span>' : '') . '</div>';
}
if (!$logs) echo '<p class="text-sm text-slate-400">No emails logged yet.</p>';
echo '</div></div></div>';
admin_footer();

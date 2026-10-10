<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/includes/layout.php';
$admin = require_admin(['super_admin', 'admin']);
$pdo = db();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) admin_flash('error', 'Invalid session.');
    else {
        $slug = $_POST['slug'] ?? '';
        $subject = trim(mb_substr($_POST['subject'] ?? '', 0, 190));
        $body = (string)($_POST['body'] ?? '');
        $enabled = isset($_POST['enabled']) ? 1 : 0;
        if ($subject === '' || $body === '') admin_flash('error', 'Subject and body are required.');
        else {
            // Templates are content: strip_tags to prevent stored HTML/JS, keep placeholders intact
            $body = strip_tags($body);
            $st = $pdo->prepare('UPDATE email_templates SET subject=?, body=?, enabled=? WHERE slug=?');
            $st->execute([$subject, $body, $enabled, $slug]);
            admin_log((int)$admin['id'], 'template_change', "Edited email template '$slug' (" . ($enabled ? 'enabled' : 'disabled') . ')', 'email_template', $slug);
            admin_flash('success', 'Template saved.');
        }
    }
    redirect('/admin/templates.php?edit=' . urlencode($_POST['slug'] ?? ''));
}
$edit = $_GET['edit'] ?? '';
$tpls = $pdo->query('SELECT * FROM email_templates ORDER BY name')->fetchAll();
admin_head('Email Templates');
admin_sidebar($admin, 'templates');
echo admin_flashes();
echo '<h1 class="text-xl font-bold text-slate-800 mb-1">Email Templates</h1>';
echo '<p class="text-sm text-slate-500 mb-4">Placeholders: {{name}} {{email}} {{amount}} {{reference}} {{status}} {{date}} {{balance}} {{site_name}} {{referral_code}} {{reset_link}} {{verify_link}} {{tier_name}} {{expiry}} {{method}} {{reason}} {{referred_user}} {{event}} {{support_email}}</p>';
if ($edit) {
    $st = $pdo->prepare('SELECT * FROM email_templates WHERE slug=? LIMIT 1');
    $st->execute([$edit]);
    $t = $st->fetch();
    if ($t) {
        echo '<form method="POST" class="bg-white rounded-2xl p-4 shadow-sm mb-4 space-y-3">' . csrf_field() . '<input type="hidden" name="slug" value="' . e($t['slug']) . '">';
        echo '<h2 class="font-bold text-sm">Editing: ' . e($t['name']) . ' <span class="text-slate-400 font-normal">(' . e($t['slug']) . ')</span></h2>';
        echo '<label class="block text-sm text-slate-600">Subject<input name="subject" value="' . e($t['subject']) . '" class="mt-1 w-full border rounded-xl px-3 py-2 text-sm"></label>';
        echo '<label class="block text-sm text-slate-600">Body (plain text)<textarea name="body" rows="8" class="mt-1 w-full border rounded-xl px-3 py-2 text-sm font-mono">' . e($t['body']) . '</textarea></label>';
        echo '<label class="text-sm flex items-center gap-2"><input type="checkbox" name="enabled" value="1"' . ((int)$t['enabled'] ? ' checked' : '') . '> Enabled</label>';
        echo '<div class="flex gap-2"><button class="bg-slate-900 text-white px-6 py-2.5 rounded-xl text-sm font-semibold">Save template</button><a href="/admin/templates.php" class="px-4 py-2.5 text-sm">Cancel</a></div></form>';
    }
}
echo '<div class="bg-white rounded-2xl shadow-sm overflow-x-auto"><table class="w-full text-sm min-w-[560px]">';
echo '<tr class="text-left text-xs text-slate-400 border-b"><th class="p-3">Template</th><th class="p-3">Subject</th><th class="p-3">Status</th><th class="p-3"></th></tr>';
foreach ($tpls as $t) {
    echo '<tr class="border-b border-slate-100 last:border-0"><td class="p-3 font-semibold">' . e($t['name']) . '<span class="block text-xs font-normal text-slate-400">' . e($t['slug']) . '</span></td><td class="p-3 text-xs">' . e(mb_substr($t['subject'], 0, 70)) . '</td>';
    echo '<td class="p-3"><span class="text-xs font-bold px-2 py-1 rounded-full ' . ((int)$t['enabled'] ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500') . '">' . ((int)$t['enabled'] ? 'on' : 'off') . '</span></td>';
    echo '<td class="p-3"><a class="text-blue-600 text-xs font-semibold" href="/admin/templates.php?edit=' . e($t['slug']) . '">Edit</a></td></tr>';
}
echo '</table></div>';
admin_footer();

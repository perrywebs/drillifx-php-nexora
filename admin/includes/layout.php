<?php
declare(strict_types=1);
// Shared admin chrome: grouped everyday-language navigation.
function admin_flash(string $type, string $msg): void {
    $_SESSION['admin_flash'][] = ['type' => $type, 'msg' => $msg];
}
function admin_flashes(): string {
    $f = $_SESSION['admin_flash'] ?? [];
    unset($_SESSION['admin_flash']);
    $out = '';
    foreach ((array)$f as $m) {
        $cls = ($m['type'] ?? '') === 'success' ? 'bg-green-50 border-green-200 text-green-700' : 'bg-red-50 border-red-200 text-red-700';
        $out .= '<div class="border rounded-xl p-3 mb-4 text-sm ' . $cls . '">' . e($m['msg']) . '</div>';
    }
    return $out;
}
// Technical audit action codes -> everyday language
function admin_action_label(string $action): string {
    $map = [
        'admin_login' => 'Signed in', 'admin_logout' => 'Signed out', 'admin_login_failed' => 'Failed sign-in attempt',
        'credit_user' => 'Added money', 'debit_user' => 'Removed money',
        'suspend_user' => 'Disabled account', 'activate_user' => 'Enabled account',
        'deposit_approve' => 'Approved deposit', 'deposit_reject' => 'Rejected deposit',
        'withdrawal_approved' => 'Approved withdrawal', 'withdrawal_rejected' => 'Rejected withdrawal', 'withdrawal_paid' => 'Marked withdrawal paid',
        'spin_config' => 'Changed spin settings', 'spin_assign' => 'Gave a specific spin result', 'spin_assign_cancel' => 'Removed a specific spin result',
        'hash_config' => 'Changed hash settings', 'hash_assign' => 'Gave a specific hash result', 'hash_assign_cancel' => 'Removed a specific hash result',
        'settings_change' => 'Changed settings', 'smtp_change' => 'Changed email settings', 'smtp_test' => 'Sent a test email',
        'template_change' => 'Edited an email message', 'branding_change' => 'Changed logo or design',
        'admin_create' => 'Created an administrator', 'admin_suspend' => 'Disabled an administrator', 'admin_activate' => 'Enabled an administrator',
        'admin_role' => 'Changed an administrator role', 'admin_pw_reset' => 'Reset an administrator password',
    ];
    return $map[$action] ?? ucfirst(str_replace('_', ' ', $action));
}
function admin_sections(array $admin): array {
    $role = $admin['role'] ?? '';
    $isSuper = $role === 'super_admin';
    $canFinance = in_array($role, ['super_admin', 'admin', 'finance'], true);
    $canConfig = in_array($role, ['super_admin', 'admin'], true);
    $canUsers = true;
    return [
        ['label' => null, 'items' => [
            ['dashboard', 'Dashboard', 'fa-gauge-high', '/admin/index.php', true],
        ]],
        ['label' => 'Users', 'items' => [
            ['users', 'All Users', 'fa-users', '/admin/users.php', $canUsers],
        ]],
        ['label' => 'Payments', 'items' => [
            ['deposits', 'Deposits', 'fa-arrow-down', '/admin/deposits.php', $canFinance],
            ['withdrawals', 'Withdrawals', 'fa-wallet', '/admin/withdrawals.php', $canFinance],
            ['transactions', 'Transactions', 'fa-receipt', '/admin/transactions.php', $canFinance],
            ['referrals', 'Referrals', 'fa-user-plus', '/admin/referrals.php', $canFinance],
        ]],
        ['label' => 'Rewards', 'items' => [
            ['spins', 'Spin', 'fa-arrows-rotate', '/admin/spins.php', $canConfig],
            ['hashes', 'Hash', 'fa-hashtag', '/admin/hashes.php', $canConfig],
        ]],
        ['label' => 'Messages', 'items' => [
            ['notifications', 'User Messages', 'fa-bell', '/admin/notifications.php', true],
            ['templates', 'Email Messages', 'fa-file-lines', '/admin/templates.php', $canConfig],
        ]],
        ['label' => 'Settings', 'items' => [
            ['settings', 'General', 'fa-gear', '/admin/settings.php', $canConfig],
            ['branding', 'Branding', 'fa-image', '/admin/branding.php', $canConfig],
            ['smtp', 'Email / SMTP', 'fa-envelope', '/admin/smtp.php', $canConfig],
        ]],
        ['label' => 'Tiers', 'items' => [
            ['tiers', 'Manage Tiers', 'fa-layer-group', '/admin/tiers.php', $canConfig],
        ]],
        ['label' => 'System', 'items' => [
            ['audit', 'Activity Log', 'fa-clock-rotate-left', '/admin/audit.php', true],
            ['admins', 'Administrators', 'fa-user-shield', '/admin/admins.php', $isSuper],
        ]],
    ];
}
function admin_head(string $title): void {
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>' . e($title) . ' - ' . e(site_name()) . ' Admin</title>';
    echo '<script>window.APP_BASE=' . json_encode(base_path()) . ';</script>';
    echo '<script src="https://cdn.tailwindcss.com"></script>';
    echo '<link href="https://fonts.googleapis.com/css2?family=Signika+Negative:wght@300;400;500;600;700&display=swap" rel="stylesheet">';
    echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">';
    echo '<style>*{font-family:"Signika Negative",sans-serif}body{background:#f1f5f9}.navlink.active{background:#0f172a;color:#fff}.tablescroll{overflow-x:auto}.usuggest{position:absolute;z-index:40;background:#fff;border:1px solid #e2e8f0;border-radius:.75rem;box-shadow:0 10px 25px rgba(0,0,0,.12);max-height:240px;overflow-y:auto;width:100%}.usuggest button{display:block;width:100%;text-align:left;padding:.5rem .75rem}.usuggest button:hover{background:#f1f5f9}</style>';
    echo '</head><body class="min-h-screen">';
}
function admin_sidebar(array $admin, string $active): void {
    $role = $admin['role'] ?? '';
    echo '<div class="flex min-h-screen">';
    echo '<aside class="w-60 bg-white border-r border-slate-200 fixed inset-y-0 hidden md:flex md:flex-col z-30">';
    echo '<div class="px-4 py-4 border-b border-slate-100"><p class="font-bold text-slate-800">' . e(site_name()) . ' Admin</p><p class="text-xs text-slate-400">' . e($admin['username']) . ' · ' . e(str_replace('_', ' ', $role)) . '</p></div>';
    echo '<nav class="flex-1 overflow-y-auto p-3 space-y-3">';
    foreach (admin_sections($admin) as $sec) {
        if ($sec['label']) echo '<p class="px-3 pt-1 text-[11px] font-bold uppercase tracking-wider text-slate-400">' . e($sec['label']) . '</p>';
        echo '<div class="space-y-1">';
        foreach ($sec['items'] as [$key, $label, $icon, $href, $show]) {
            if (!$show) continue;
            $cls = 'navlink flex items-center gap-3 px-3 py-2 rounded-xl text-sm text-slate-600 hover:bg-slate-100' . ($key === $active ? ' active' : '');
            echo '<a href="' . e(url($href)) . '" class="' . $cls . '"><i class="fas ' . $icon . ' w-5 text-center"></i>' . e($label) . '</a>';
        }
        echo '</div>';
    }
    echo '</nav>';
    echo '<div class="p-3 border-t border-slate-100"><a href="' . e(url('/admin/logout.php')) . '" class="flex items-center gap-3 px-3 py-2 rounded-xl text-sm text-red-600 hover:bg-red-50"><i class="fas fa-right-from-bracket w-5 text-center"></i>Logout</a></div>';
    echo '</aside>';
    echo '<div class="md:hidden fixed top-0 inset-x-0 bg-white border-b border-slate-200 z-30 px-4 py-2 flex items-center justify-between">';
    echo '<span class="font-bold text-slate-800 text-sm">' . e(site_name()) . ' Admin</span>';
    echo '<details class="relative"><summary class="text-sm bg-slate-900 text-white px-3 py-1.5 rounded-lg cursor-pointer">Menu</summary><div class="absolute right-0 mt-2 w-56 bg-white border rounded-xl shadow-xl p-2 max-h-96 overflow-y-auto">';
    foreach (admin_sections($admin) as $sec) {
        if ($sec['label']) echo '<p class="px-3 pt-2 text-[11px] font-bold uppercase tracking-wider text-slate-400">' . e($sec['label']) . '</p>';
        foreach ($sec['items'] as [$key, $label, $icon, $href, $show]) {
            if (!$show) continue;
            echo '<a href="' . e(url($href)) . '" class="block px-3 py-2 rounded-lg text-sm text-slate-700 hover:bg-slate-100">' . e($label) . '</a>';
        }
    }
    echo '<a href="' . e(url('/admin/logout.php')) . '" class="block px-3 py-2 rounded-lg text-sm text-red-600">Logout</a></div></details></div>';
    echo '<main class="flex-1 md:ml-60 p-4 md:p-6 pt-16 md:pt-6 max-w-6xl w-full">';
}
function admin_footer(): void {
    echo '</main></div></body></html>';
}
// Proof preview modal (image or PDF, no page navigation)
function admin_proof_modal(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    echo <<<'HTML'
<div id="proofModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(0,0,0,.6)">
  <div class="bg-white rounded-2xl max-w-2xl w-full p-4">
    <div class="flex justify-between items-center mb-2"><h3 class="font-bold text-sm">Payment proof</h3><button onclick="closeProof()" class="text-slate-400 hover:text-slate-700 text-xl leading-none">&times;</button></div>
    <div id="proofBody" class="bg-slate-100 rounded-xl overflow-hidden flex items-center justify-center" style="min-height:200px"></div>
    <div class="flex justify-end gap-2 mt-3"><a id="proofOpen" href="#" target="_blank" class="text-xs text-blue-600 font-semibold px-3 py-2">Open in new tab</a><button onclick="closeProof()" class="bg-slate-900 text-white px-4 py-2 rounded-xl text-sm font-semibold">Close</button></div>
  </div>
</div>
<script>
function openProof(url, isPdf) {
  var m = document.getElementById('proofModal');
  var body = document.getElementById('proofBody');
  document.getElementById('proofOpen').href = url;
  body.innerHTML = '';
  if (isPdf) {
    var em = document.createElement('embed');
    em.src = url; em.type = 'application/pdf';
    em.style.cssText = 'width:100%;height:60vh';
    body.appendChild(em);
  } else {
    var img = document.createElement('img');
    img.src = url; img.alt = 'Payment proof';
    img.style.cssText = 'max-width:100%;max-height:60vh;object-fit:contain';
    img.onerror = function(){ body.innerHTML = '<p class="text-sm text-slate-500 p-8">This proof file could not be displayed. <a class="text-blue-600 font-semibold" target="_blank" href="'+url+'">Open it in a new tab instead.</a></p>'; };
    body.appendChild(img);
  }
  m.classList.remove('hidden');
}
function closeProof() { document.getElementById('proofModal').classList.add('hidden'); document.getElementById('proofBody').innerHTML=''; }
document.getElementById('proofModal').addEventListener('click', function(e){ if (e.target === this) closeProof(); });
document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeProof(); });
</script>
HTML;
}
// Shared searchable-user dropdown (AJAX). Emits input + hidden field + suggestion box.
function admin_user_search(string $fieldName = 'username', string $placeholder = 'Type a name or email to search...'): void {
    static $done = false;
    echo '<div class="relative"><input type="text" id="usearch_' . $fieldName . '" autocomplete="off" placeholder="' . e($placeholder) . '" class="w-full border rounded-xl px-3 py-2 text-sm">';
    echo '<input type="hidden" name="' . e($fieldName) . '" id="uvalue_' . $fieldName . '"><div id="ulist_' . $fieldName . '" class="usuggest hidden"></div>';
    echo '<p class="text-xs text-slate-400 mt-1" id="uselected_' . $fieldName . '">No user selected.</p></div>';
    if ($done) return;
    $done = true;
    echo <<<'JS'
<script>
(function(){
document.addEventListener('input', function(ev){
  if (!ev.target || !ev.target.id || ev.target.id.indexOf('usearch_') !== 0) return;
  var key = ev.target.id.replace('usearch_','');
  var box = document.getElementById('ulist_'+key);
  var hidden = document.getElementById('uvalue_'+key);
  var label = document.getElementById('uselected_'+key);
  var q = ev.target.value.trim();
  hidden.value = ''; if (label) label.textContent = 'No user selected.';
  if (q.length < 2) { box.classList.add('hidden'); box.innerHTML=''; return; }
  fetch((window.APP_BASE||'')+'/admin/ajax_users.php?q='+encodeURIComponent(q), {headers:{'X-Requested-With':'XMLHttpRequest'}})
    .then(function(r){ return r.json(); })
    .then(function(res){
      box.innerHTML='';
      if (!res.success || !res.data.length) { box.classList.add('hidden'); return; }
      res.data.forEach(function(u){
        var b = document.createElement('button');
        b.type = 'button';
        b.innerHTML = '<span class="font-semibold text-sm"></span><br><span class="text-xs text-slate-400"></span>';
        b.querySelector('span').textContent = u.username;
        b.querySelectorAll('span')[1].textContent = u.email;
        b.addEventListener('click', function(){
          ev.target.value = u.username + ' (' + u.email + ')';
          hidden.value = u.username;
          if (label) label.textContent = 'Selected: ' + u.username + ' (' + u.email + ')';
          box.classList.add('hidden'); box.innerHTML='';
        });
        box.appendChild(b);
      });
      box.classList.remove('hidden');
    })
    .catch(function(){ box.classList.add('hidden'); });
});
document.addEventListener('click', function(ev){
  document.querySelectorAll('.usuggest').forEach(function(box){
    if (!box.contains(ev.target)) { box.classList.add('hidden'); }
  });
});
})();
</script>
JS;
}
function admin_pager(int $page, int $pages, string $base, array $q = []): void {
    $base = url($base);
    echo '<div class="flex items-center justify-between mt-4">';
    if ($page > 1) {
        $q['page'] = $page - 1;
        echo '<a href="' . e($base . '?' . http_build_query($q)) . '" class="px-4 py-2 bg-white border rounded-xl text-sm font-semibold">Previous</a>';
    } else echo '<span class="px-4 py-2 bg-slate-100 text-slate-400 rounded-xl text-sm">Previous</span>';
    echo '<span class="text-xs text-slate-500">Page ' . $page . ' of ' . $pages . '</span>';
    if ($page < $pages) {
        $q['page'] = $page + 1;
        echo '<a href="' . e($base . '?' . http_build_query($q)) . '" class="px-4 py-2 bg-slate-900 text-white rounded-xl text-sm font-semibold">Next</a>';
    } else echo '<span class="px-4 py-2 bg-slate-100 text-slate-400 rounded-xl text-sm">Next</span>';
    echo '</div>';
}
function admin_confirm(string $message): string {
    return 'onsubmit="return confirm(' . htmlspecialchars(json_encode($message), ENT_QUOTES) . ')"';
}
// Simple tab bar for detail pages
function admin_tabs(string $active, array $tabs): void {
    echo '<div class="flex gap-2 mb-4 overflow-x-auto pb-1">';
    foreach ($tabs as [$key, $label]) {
        echo '<a href="#tab-' . $key . '" data-tabbtn="' . $key . '" class="tabbtn flex-shrink-0 px-3 py-1.5 rounded-xl text-sm font-semibold ' . ($key === $active ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 shadow-sm') . '">' . e($label) . '</a>';
    }
    echo '</div>';
    echo <<<'JS'
<script>
document.querySelectorAll('[data-tabbtn]').forEach(function(btn){
  btn.addEventListener('click', function(e){
    e.preventDefault();
    var key = btn.getAttribute('data-tabbtn');
    document.querySelectorAll('[data-tabbtn]').forEach(function(b){
      var on = b.getAttribute('data-tabbtn') === key;
      b.classList.toggle('bg-slate-900', on); b.classList.toggle('text-white', on);
      b.classList.toggle('bg-white', !on); b.classList.toggle('text-slate-600', !on);
    });
    document.querySelectorAll('[data-tabpane]').forEach(function(p){
      p.style.display = p.getAttribute('data-tabpane') === key ? '' : 'none';
    });
    location.hash = 'tab-' + key;
  });
});
(function(){
  var h = (location.hash || '').replace('#tab-','');
  if (h) { var b = document.querySelector('[data-tabbtn="'+h+'"]'); if (b) b.click(); }
})();
</script>
JS;
}

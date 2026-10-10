<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/includes/layout.php';
$admin = require_admin(['super_admin']);
$pdo = db();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) admin_flash('error', 'Invalid session.');
    elseif (isset($_POST['create'])) {
        $name = trim(mb_substr($_POST['name'] ?? '', 0, 100));
        $email = trim(strtolower($_POST['email'] ?? ''));
        $username = trim($_POST['username'] ?? '');
        $role = $_POST['role'] ?? 'admin';
        $pw = $_POST['password'] ?? '';
        if ($name === '' || !is_valid_email($email) || !preg_match('/^[A-Za-z0-9_]{3,50}$/', $username) || strlen($pw) < 8) admin_flash('error', 'Name, valid email, username (3+, a-z0-9_) and password (8+) required.');
        elseif (!in_array($role, ['super_admin', 'admin', 'support', 'finance'], true)) admin_flash('error', 'Invalid role.');
        else {
            try {
                $pdo->prepare('INSERT INTO admins (name,email,username,password_hash,role) VALUES (?,?,?,?,?)')->execute([$name, $email, $username, password_hash($pw, PASSWORD_DEFAULT), $role]);
                $nid = (int)$pdo->lastInsertId();
                admin_log((int)$admin['id'], 'admin_create', "Created admin $username ($role)", 'admin', $nid);
                admin_flash('success', 'Administrator created.');
            } catch (Throwable $e) { admin_flash('error', 'Email or username already taken.'); }
        }
    } elseif (isset($_POST['set_status'])) {
        $id = (int)($_POST['id'] ?? 0);
        $to = $_POST['set_status'] === 'suspended' ? 'suspended' : 'active';
        if ($id === (int)$admin['id']) admin_flash('error', 'You cannot change your own status.');
        elseif ($to === 'suspended' && is_last_super_admin($id)) admin_flash('error', 'Cannot suspend the last active super admin.');
        else {
            $st = $pdo->prepare('SELECT username FROM admins WHERE id=?');
            $st->execute([$id]);
            $t = $st->fetch();
            if (!$t) admin_flash('error', 'Admin not found.');
            else {
                $pdo->prepare('UPDATE admins SET status=? WHERE id=?')->execute([$to, $id]);
                admin_log((int)$admin['id'], $to === 'suspended' ? 'admin_suspend' : 'admin_activate', ($to === 'suspended' ? 'Suspended' : 'Activated') . ' admin ' . $t['username'], 'admin', $id);
                admin_flash('success', 'Admin ' . $t['username'] . ' is now ' . $to . '.');
            }
        }
    } elseif (isset($_POST['set_role'])) {
        $id = (int)($_POST['id'] ?? 0);
        $role = $_POST['role'] ?? '';
        if (!in_array($role, ['super_admin', 'admin', 'support', 'finance'], true)) admin_flash('error', 'Invalid role.');
        elseif ($id === (int)$admin['id']) admin_flash('error', 'You cannot change your own role.');
        else {
            $st = $pdo->prepare('SELECT username, role FROM admins WHERE id=?');
            $st->execute([$id]);
            $t = $st->fetch();
            if (!$t) admin_flash('error', 'Admin not found.');
            elseif ($t['role'] === 'super_admin' && $role !== 'super_admin' && is_last_super_admin($id)) admin_flash('error', 'Cannot demote the last active super admin.');
            else {
                $pdo->prepare('UPDATE admins SET role=? WHERE id=?')->execute([$role, $id]);
                admin_log((int)$admin['id'], 'admin_role', 'Set ' . $t['username'] . ' role to ' . $role, 'admin', $id);
                admin_flash('success', 'Role updated.');
            }
        }
    } elseif (isset($_POST['reset_pw'])) {
        $id = (int)($_POST['id'] ?? 0);
        $pw = $_POST['password'] ?? '';
        if (strlen($pw) < 8) admin_flash('error', 'Password must be 8+ characters.');
        else {
            $st = $pdo->prepare('SELECT username FROM admins WHERE id=?');
            $st->execute([$id]);
            $t = $st->fetch();
            if (!$t) admin_flash('error', 'Admin not found.');
            else {
                $pdo->prepare('UPDATE admins SET password_hash=? WHERE id=?')->execute([password_hash($pw, PASSWORD_DEFAULT), $id]);
                admin_log((int)$admin['id'], 'admin_pw_reset', 'Reset password for admin ' . $t['username'], 'admin', $id);
                admin_flash('success', 'Password reset for ' . $t['username'] . '.');
            }
        }
    }
    redirect('/admin/admins.php');
}
$rows = $pdo->query('SELECT * FROM admins ORDER BY id')->fetchAll();
admin_head('Administrators');
admin_sidebar($admin, 'admins');
echo admin_flashes();
echo '<h1 class="text-xl font-bold text-slate-800 mb-4">Administrators</h1>';
echo '<div class="bg-white rounded-2xl shadow-sm overflow-x-auto mb-4"><table class="w-full text-sm min-w-[720px]">';
echo '<tr class="text-left text-xs text-slate-400 border-b"><th class="p-3">Admin</th><th class="p-3">Role</th><th class="p-3">Status</th><th class="p-3">Last login</th><th class="p-3">Actions</th></tr>';
foreach ($rows as $r) {
    $self = (int)$r['id'] === (int)$admin['id'];
    echo '<tr class="border-b border-slate-100 last:border-0"><td class="p-3 font-semibold">' . e($r['name']) . '<span class="block text-xs font-normal text-slate-400">' . e($r['username']) . ' · ' . e($r['email']) . '</span></td>';
    echo '<td class="p-3"><form method="POST" class="inline">' . csrf_field() . '<input type="hidden" name="id" value="' . (int)$r['id'] . '"><select name="role" ' . ($self ? 'disabled' : '') . ' class="border rounded-lg px-2 py-1 text-xs">';
    foreach (['super_admin' => 'Super Admin', 'admin' => 'Admin', 'support' => 'Support', 'finance' => 'Finance'] as $k => $label) echo '<option value="' . $k . '"' . ($r['role'] === $k ? ' selected' : '') . '>' . $label . '</option>';
    echo '</select> ' . ($self ? '' : '<button name="set_role" class="text-blue-600 text-xs font-semibold">Set</button>') . '</form></td>';
    echo '<td class="p-3"><span class="text-xs font-bold px-2 py-1 rounded-full ' . ($r['status'] === 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700') . '">' . e($r['status']) . '</span></td>';
    echo '<td class="p-3 text-xs text-slate-500">' . e($r['last_login_at'] ?? 'never') . '</td><td class="p-3">';
    if (!$self) {
        echo '<form method="POST" class="inline">' . csrf_field() . '<input type="hidden" name="id" value="' . (int)$r['id'] . '"><input type="hidden" name="set_status" value="' . ($r['status'] === 'active' ? 'suspended' : 'active') . '"><button ' . admin_confirm(($r['status'] === 'active' ? 'Suspend ' : 'Activate ') . $r['username'] . '?') . ' class="text-xs font-semibold ' . ($r['status'] === 'active' ? 'text-red-600' : 'text-green-600') . '">' . ($r['status'] === 'active' ? 'Suspend' : 'Activate') . '</button></form> ';
        echo '<form method="POST" class="inline" ' . admin_confirm('Reset password for ' . $r['username'] . '?') . '>' . csrf_field() . '<input type="hidden" name="id" value="' . (int)$r['id'] . '"><input type="password" name="password" required minlength="8" placeholder="New pw" class="border rounded-lg px-2 py-1 text-xs w-24"><button name="reset_pw" class="text-blue-600 text-xs font-semibold ml-1">Reset pw</button></form>';
    } else echo '<span class="text-xs text-slate-400">You</span>';
    echo '</td></tr>';
}
echo '</table></div>';
echo '<form method="POST" class="bg-white rounded-2xl p-4 shadow-sm" ' . admin_confirm('Create this administrator?') . '>' . csrf_field() . '<input type="hidden" name="create" value="1">';
echo '<h2 class="font-bold text-sm mb-3">Create administrator</h2><div class="grid md:grid-cols-3 gap-3">';
echo '<input name="name" required placeholder="Full name" class="border rounded-xl px-3 py-2 text-sm"><input name="email" type="email" required placeholder="Email" class="border rounded-xl px-3 py-2 text-sm"><input name="username" required placeholder="Username" class="border rounded-xl px-3 py-2 text-sm">';
echo '<select name="role" class="border rounded-xl px-3 py-2 text-sm"><option value="admin">Admin</option><option value="support">Support</option><option value="finance">Finance</option><option value="super_admin">Super Admin</option></select><input name="password" type="password" required minlength="8" placeholder="Password (8+)" class="border rounded-xl px-3 py-2 text-sm"><button class="bg-slate-900 text-white px-4 py-2 rounded-xl text-sm font-semibold">Create</button>';
echo '</div></form>';
admin_footer();

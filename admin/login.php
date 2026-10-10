<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_auth.php';
// Private entry point: keep out of search/social indexes.
if (!headers_sent()) header('X-Robots-Tag: noindex, nofollow', false);
if (current_admin()) redirect('/admin/index.php');
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) $err = 'Invalid session. Try again.';
    else {
        $login = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $ip = client_ip();
        $rlDir = sys_get_temp_dir();
        $rl = (is_string($rlDir) && is_dir($rlDir) && is_writable($rlDir))
            ? rtrim($rlDir, '/\\') . '/drill_admin_' . md5($ip) : null;
        $att = ($rl !== null && is_file($rl)) ? (int)@file_get_contents($rl) : 0;
        if ($rl !== null && $att >= 10) $err = 'Too many attempts. Try again later.';
        elseif ($login === '' || $password === '') $err = 'Enter your email/username and password.';
        else {
            $st = db()->prepare('SELECT * FROM admins WHERE email=? OR username=? LIMIT 1');
            $st->execute([$login, $login]);
            $a = $st->fetch();
            if (!$a || $a['status'] !== 'active' || !password_verify($password, $a['password_hash'])) {
                if ($rl !== null) @file_put_contents($rl, (string)($att + 1));
                $err = 'Incorrect credentials.';
                admin_log($a ? (int)$a['id'] : null, 'admin_login_failed', 'Failed admin login for "' . mb_substr($login, 0, 80) . '"');
            } else {
                if ($rl !== null) @unlink($rl);
                admin_login((int)$a['id'], $a['role']);
                db()->prepare('UPDATE admins SET last_login_at=NOW() WHERE id=?')->execute([(int)$a['id']]);
                admin_log((int)$a['id'], 'admin_login', $a['username'] . ' signed in');
                $next = $_GET['next'] ?? '';
                $base = base_path();
                $check = is_string($next) ? $next : '';
                if ($base !== '' && strpos($check, $base . '/') === 0) $check = substr($check, strlen($base));
                if (str_starts_with($check, '/admin/')) redirect($next);
                redirect('/admin/index.php');
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - <?php echo e(site_name()); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Signika+Negative:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Signika Negative', sans-serif
        }
    </style>
</head>

<body class="bg-slate-900 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-sm bg-white rounded-2xl shadow-2xl p-6">
        <div class="text-center mb-4">
            <div class="inline-flex items-center justify-center w-12 h-12 bg-slate-900 rounded-xl mb-2">
                <svg xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    class="w-6 h-6 text-white">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M9.594 3.94a1.5 1.5 0 0 1 2.812 0l.18.51a1.5 1.5 0 0 0 2.15.77l.48-.27a1.5 1.5 0 0 1 2.05.55l.72 1.25a1.5 1.5 0 0 1-.55 2.05l-.48.27a1.5 1.5 0 0 0 0 2.66l.48.27a1.5 1.5 0 0 1 .55 2.05l-.72 1.25a1.5 1.5 0 0 1-2.05.55l-.48-.27a1.5 1.5 0 0 0-2.15.77l-.18.51a1.5 1.5 0 0 1-2.812 0l-.18-.51a1.5 1.5 0 0 0-2.15-.77l-.48.27a1.5 1.5 0 0 1-2.05-.55l-.72-1.25a1.5 1.5 0 0 1 .55-2.05l.48-.27a1.5 1.5 0 0 0 0-2.66l-.48-.27a1.5 1.5 0 0 1-.55-2.05l.72-1.25a1.5 1.5 0 0 1 2.05-.55l.48.27a1.5 1.5 0 0 0 2.15-.77l.18-.51Z" />
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M12 15.25a3.25 3.25 0 1 0 0-6.5 3.25 3.25 0 0 0 0 6.5Z" />
                </svg>
            </div>
            <h1 class="text-xl font-bold text-gray-800"><?php echo e(site_name()); ?> Admin</h1>
            <p class="text-gray-500 text-sm">Restricted area. Authorized administrators only.</p>
        </div>
        <?php if ($err): ?><div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl p-3 mb-4"><?php echo e($err); ?></div><?php endif; ?>
        <form method="POST" class="space-y-4"><?php echo csrf_field(); ?>
            <input type="text" name="email" placeholder="Email or username" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm">
            <input type="password" name="password" placeholder="Password" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm">
            <button class="w-full bg-slate-900 text-white py-3 rounded-xl font-semibold">Sign In</button>
        </form>
    </div>
</body>

</html>
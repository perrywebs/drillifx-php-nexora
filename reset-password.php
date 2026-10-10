<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
if (current_user()) redirect('/users/dashboard.php');
$token = $_GET['token'] ?? $_POST['token'] ?? '';
$err = ''; $done = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) $err = 'Invalid session. Try again.';
    else {
        $pw = $_POST['password'] ?? ''; $cf = $_POST['confirm_password'] ?? '';
        if (strlen($pw) < 6) $err = 'Password must be at least 6 characters.';
        elseif ($pw !== $cf) $err = 'Passwords do not match.';
        else {
            $h = hash('sha256', $_POST['token'] ?? '');
            $st = db()->prepare('SELECT * FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1');
            $st->execute([$h]);
            $r = $st->fetch();
            if (!$r) $err = 'This reset link is invalid or expired.';
            else {
                $pdo = db();
                $pdo->beginTransaction();
                try {
                    $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($pw, PASSWORD_DEFAULT), (int)$r['user_id']]);
                    $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?')->execute([(int)$r['id']]);
                    $pdo->commit();
                    log_activity((int)$r['user_id'], 'password_reset', 'Password reset via email link');
                    $done = true;
                } catch (Throwable $e) { $pdo->rollBack(); $err = 'Unable to reset password. Try again.'; }
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
<?php seo_head(['title' => 'Reset Password - ' . site_name(), 'description' => 'Choose a new password for your ' . site_name() . ' account.', 'path' => '/reset-password.php']); ?>
<script src="https://cdn.tailwindcss.com/3.4.17"></script>
<link href="https://fonts.googleapis.com/css2?family=Signika+Negative:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>*{font-family:'Signika Negative',sans-serif}</style>
</head>
<body class="bg-gradient-to-br from-blue-50 via-white to-blue-100 min-h-screen flex items-center justify-center p-4">
<div class="w-full max-w-md bg-white rounded-2xl shadow-xl p-6">
<h1 class="text-xl font-bold text-gray-800 mb-1">Reset Password</h1>
<p class="text-gray-500 text-sm mb-4">Choose a new password for your account.</p>
<?php if ($done): ?><div class="bg-green-50 border border-green-200 text-green-700 text-sm rounded-xl p-3 mb-4">Password reset successfully. <a class="font-semibold underline" href="/login.php">Sign in</a></div>
<?php else: ?>
<?php if ($err): ?><div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl p-3 mb-4"><?php echo e($err); ?></div><?php endif; ?>
<form method="POST" class="space-y-4"><?php echo csrf_field(); ?>
<input type="hidden" name="token" value="<?php echo e($token); ?>">
<input type="password" name="password" placeholder="New password (min 6 chars)" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm">
<input type="password" name="confirm_password" placeholder="Confirm new password" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm">
<button class="w-full bg-blue-600 text-white py-3 rounded-xl font-semibold">Reset Password</button>
</form>
<?php endif; ?>
<p class="text-center text-sm mt-4"><a class="text-blue-600 font-semibold" href="/login.php">Back to Sign In</a></p>
</div>
</body>
</html>

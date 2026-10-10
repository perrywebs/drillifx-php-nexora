<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mail.php';
if (current_user()) redirect('/users/dashboard.php');
$msg = ''; $ok = false;
// Verify via link
$token = $_GET['token'] ?? '';
if ($token !== '') {
    $st = db()->prepare('SELECT * FROM email_verify_tokens WHERE token_hash=? AND used_at IS NULL AND expires_at > NOW() LIMIT 1');
    $st->execute([hash('sha256', $token)]);
    $r = $st->fetch();
    if (!$r) $msg = 'This verification link is invalid or expired.';
    else {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE users SET email_verified_at=NOW() WHERE id=?')->execute([(int)$r['user_id']]);
            $pdo->prepare('UPDATE email_verify_tokens SET used_at=NOW() WHERE id=?')->execute([(int)$r['id']]);
            $pdo->commit();
            log_activity((int)$r['user_id'], 'verify', 'Email verified');
            $ok = true; $msg = 'Email verified successfully. You can now sign in.';
        } catch (Throwable $e) { $pdo->rollBack(); $msg = 'Verification failed. Try again.'; }
    }
}
// Resend
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) $msg = 'Invalid session. Try again.';
    else {
        $email = trim(strtolower($_POST['email'] ?? ''));
        $st = db()->prepare('SELECT id, username, email, email_verified_at FROM users WHERE email=? LIMIT 1');
        $st->execute([$email]);
        $u = $st->fetch();
        if ($u && !$u['email_verified_at']) {
            $t = bin2hex(random_bytes(32));
            db()->prepare('INSERT INTO email_verify_tokens (user_id, token_hash, expires_at) VALUES (?,?,DATE_ADD(NOW(), INTERVAL 24 HOUR))')->execute([(int)$u['id'], hash('sha256', $t)]);
            $link = rtrim(app_base_url(), '/') . '/verify.php?token=' . $t;
            send_template($u['email'], 'email_verify', user_email_vars($u, ['verify_link' => $link]), (int)$u['id']);
        }
        $msg = 'If that email needs verification, a new link has been sent.';
    }
}
$prefill = $_GET['email'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php seo_head(['title' => 'Verify Email - ' . site_name(), 'description' => 'Verify your email address to activate your ' . site_name() . ' account.', 'path' => '/verify.php']); ?>
<script src="https://cdn.tailwindcss.com/3.4.17"></script>
<link href="https://fonts.googleapis.com/css2?family=Signika+Negative:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>*{font-family:'Signika Negative',sans-serif}</style>
</head>
<body class="bg-gradient-to-br from-blue-50 via-white to-blue-100 min-h-screen flex items-center justify-center p-4">
<div class="w-full max-w-md bg-white rounded-2xl shadow-xl p-6">
<h1 class="text-xl font-bold text-gray-800 mb-1">Email Verification</h1>
<?php if ($msg): ?><div class="rounded-xl p-3 mb-4 text-sm <?php echo $ok ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-blue-50 border border-blue-200 text-blue-700'; ?>"><?php echo e($msg); ?> <?php if ($ok): ?><a class="font-semibold underline" href="/login.php">Sign in</a><?php endif; ?></div><?php endif; ?>
<p class="text-gray-500 text-sm mb-4">Didn't get the link? Enter your email to resend it.</p>
<form method="POST" class="space-y-4"><?php echo csrf_field(); ?>
<input type="email" name="email" value="<?php echo e($prefill); ?>" placeholder="Your email" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl text-sm">
<button class="w-full bg-blue-600 text-white py-3 rounded-xl font-semibold">Resend Link</button>
</form>
<p class="text-center text-sm mt-4"><a class="text-blue-600 font-semibold" href="/login.php">Back to Sign In</a></p>
</div>
</body>
</html>

<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mail.php';
$u = require_login();
$pdo = db();
$tierId = (int)($_GET['tier'] ?? $_POST['tier'] ?? 0);
$tierNameFromUrl = $_GET['tier_name'] ?? '';
// Look up tier from tiers table (by ID, since upgrade_options is consolidated into tiers)
$tier = null;
$tierSource = '';
if (!empty($tierId)) {
    $st = $pdo->prepare('SELECT * FROM tiers WHERE id=? LIMIT 1');
    $st->execute([$tierId]);
    $tier = $st->fetch();
    if ($tier) { $tierSource = 'tiers'; }
}
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) $err = 'Invalid session. Try again.';
    elseif ((int)$u['tier_id'] === $tierId) $err = 'You are already on this tier.';
    else {
        // prevent duplicate pending deposit for same tier
        $st = $pdo->prepare('SELECT id FROM deposits WHERE user_id=? AND tier_id=? AND status="pending" LIMIT 1');
        $st->execute([(int)$u['id'], $tierId]);
        if ($st->fetch()) $err = 'You already have a pending deposit for this tier.';
        elseif (empty($_FILES['proof']) || $_FILES['proof']['error'] !== UPLOAD_ERR_OK) $err = 'Upload your payment proof (image or PDF).';
        else {
            $f = $_FILES['proof'];
            if ($f['size'] > 5 * 1024 * 1024) $err = 'Proof file must be under 5MB.';
            else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($f['tmp_name']);
                $ok = ['image/jpeg'=>'.jpg','image/png'=>'.png','image/webp'=>'.webp','application/pdf'=>'.pdf'];
                if (!isset($ok[$mime])) $err = 'Proof must be JPG, PNG, WEBP or PDF.';
                else {
                    $dir = __DIR__ . '/../uploads/proofs';
                    if (!is_dir($dir)) mkdir($dir, 0755, true);
                    $name = 'proof_' . (int)$u['id'] . '_' . bin2hex(random_bytes(8)) . $ok[$mime];
                    if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) $err = 'Upload failed. Try again.';
                    else {
                        $ref = gen_reference('DEP');
                        $doid = $tierId;
                        $st = $pdo->prepare('INSERT INTO deposits (user_id,tier_id,amount,status,proof_path,reference) VALUES (?,?,?,?,?)');
                        $st->execute([(int)$u['id'], $tierId, $doid, $tier['price'], 'pending', 'uploads/proofs/' . $name, $ref]);
                        log_activity((int)$u['id'], 'deposit', 'Upgrade deposit ' . $ref . ' for ' . $tier['name']);
                        notify((int)$u['id'], 'Deposit received', 'Your ' . $tier['name'] . ' payment proof is under review.');
                        send_template($u['email'], 'deposit_submitted', user_email_vars($u, ['tier_name' => $tier['name'], 'amount' => number_format((float)$tier['price'], 2), 'reference' => $ref]), (int)$u['id']);
                        flash('success','Deposit submitted','Your payment proof is under review. Your tier activates after approval.');
                        redirect('/users/upgrade.php');
                    }
                }
            }
        }
    }
}
$st = $pdo->prepare('SELECT * FROM deposits WHERE user_id=? ORDER BY id DESC LIMIT 5');
$st->execute([(int)$u['id']]);
$myDeps = $st->fetchAll();
$isCurrent = ((int)$u['tier_id'] === $tierId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<?php if (site_favicon()): ?><link rel="icon" href="<?php echo e(site_favicon()); ?>"><?php endif; ?>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Deposit - <?php echo e($tier['name']); ?> - <?php echo e(site_name()); ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Signika+Negative:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>body{font-family:'Signika Negative',sans-serif;padding-bottom:70px;background:linear-gradient(180deg,#f8fafc 0%,#eff6ff 100%);min-height:100vh}</style>
</head>
<body class="min-h-screen">
<header class="bg-white sticky top-0 z-40 shadow-sm"><div class="flex items-center justify-between px-4 py-3">
<a href="/users/upgrade.php" class="flex items-center gap-2 text-blue-700 font-semibold text-sm"><i class="fas fa-arrow-left"></i> Back</a>
<span class="font-bold text-gray-800">Upgrade Deposit</span><span class="w-10"></span>
</div></header>
<main class="px-4 py-4">
<div class="bg-gradient-to-br from-blue-600 via-blue-700 to-blue-900 rounded-2xl p-4 mb-4 text-white relative overflow-hidden">
<div class="absolute top-0 right-0 w-24 h-24 bg-white/10 rounded-full -mr-8 -mt-8"></div>
<div class="relative flex items-center justify-between">
<div><p class="text-blue-200 text-xs"><?php echo e($tier['name']); ?></p><p class="text-2xl font-bold">$<?php echo e(money($tier['price'])); ?></p>
<p class="text-blue-200 text-xs"><?php echo (int)$tier['duration_days']; ?> days · <?php echo (int)$tier['daily_hashes']; ?> hashes/day · $<?php echo e(money($tier['per_hash'])); ?>/hash</p></div>
<?php if ($isCurrent): ?><span class="bg-green-400/30 border border-green-300/40 text-xs font-bold px-3 py-1 rounded-full">CURRENT</span><?php endif; ?>
</div></div>
<?php if ($err): ?><div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl p-3 mb-4"><?php echo e($err); ?></div><?php endif; ?>
<?php if ($isCurrent): ?>
<div class="bg-green-50 border border-green-200 text-green-700 text-sm rounded-xl p-4 mb-4">You are already on this tier<?php echo $u['tier_expires_at'] ? ' (expires ' . e(date('M d, Y', strtotime($u['tier_expires_at']))) . ')' : ''; ?>.</div>
<?php else: ?>
<div class="bg-white rounded-2xl shadow-sm p-4 mb-4">
<h2 class="text-gray-700 font-semibold text-sm mb-2">1. Make payment</h2>
<p class="text-sm text-gray-600 mb-1">Send <strong>$<?php echo e(money($tier['price'])); ?></strong> to the platform wallet shown by support, then upload your proof below.</p>
<p class="text-xs text-gray-400">Payment details are confirmed via <a class="text-blue-600 font-semibold" href="/users/support.php">Support</a>. Admin approval activates your tier.</p>
</div>
<div class="bg-white rounded-2xl shadow-sm p-4 mb-4">
<h2 class="text-gray-700 font-semibold text-sm mb-3">2. Upload payment proof</h2>
<form method="POST" enctype="multipart/form-data" class="space-y-3" id="depForm">
<?php echo csrf_field(); ?>
<input type="hidden" name="tier" value="<?php echo (int)$tierId; ?>">
<input type="hidden" name="tier_name" value="<?php echo e($tier['display_name'] ?? $tierNameFromUrl); ?>">
<input type="file" name="proof" accept=".jpg,.jpeg,.png,.webp,.pdf" class="w-full text-sm border border-gray-200 rounded-xl p-2">
<button id="depBtn" class="w-full bg-blue-600 text-white py-3 rounded-xl font-semibold">Submit for Approval</button>
</form>
</div>
<?php endif; ?>
<?php if ($myDeps): ?>
<div class="bg-white rounded-2xl shadow-sm p-4 mb-4">
<h3 class="text-gray-700 font-semibold text-sm mb-2">My Deposits</h3>
<?php foreach ($myDeps as $d): ?>
<div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
<div><p class="text-sm font-semibold text-gray-800"><?php echo e($d['reference']); ?> · $<?php echo e(money($d['amount'])); ?></p><p class="text-xs text-gray-400"><?php echo e(date('M j, Y', strtotime($d['created_at']))); ?></p></div>
<span class="text-xs font-bold px-2 py-1 rounded-full <?php echo $d['status']==='pending'?'bg-yellow-100 text-yellow-700':($d['status']==='approved'?'bg-green-100 text-green-700':'bg-red-100 text-red-700'); ?>"><?php echo e(ucfirst($d['status'])); ?></span>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</main>
<script>
document.getElementById('depForm')?.addEventListener('submit', function(){
    var b = document.getElementById('depBtn');
    if (b) { b.disabled = true; b.textContent = 'Uploading...'; }
});
</script>
<?php echo render_toast_queue(); ?>
</body>
</html>

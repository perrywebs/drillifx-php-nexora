<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
$u = require_login();
$pdo = db();
$type = $_GET['type'] ?? 'all';
$allowed = ['all','Hash Reward','Spin Reward','Referral Reward','Withdrawal','Deposit','signup','adjustment'];
if (!in_array($type, $allowed, true)) $type = 'all';
$page = max(1, (int)($_GET['page'] ?? 1));
$per = 20;
$where = 'user_id = ?';
$params = [(int)$u['id']];
if ($type !== 'all') { $where .= ' AND type = ?'; $params[] = $type; }
$st = $pdo->prepare("SELECT COUNT(*) c FROM transactions WHERE $where");
$st->execute($params);
$total = (int)$st->fetch()['c'];
$pages = max(1, (int)ceil($total / $per));
$page = min($page, $pages);
$off = ($page - 1) * $per;
$st = $pdo->prepare("SELECT * FROM transactions WHERE $where ORDER BY id DESC LIMIT $per OFFSET $off");
$st->execute($params);
$rows = $st->fetchAll();
$st = $pdo->prepare('SELECT COALESCE(SUM(amount),0) s FROM transactions WHERE user_id=? AND amount > 0 AND status="completed"');
$st->execute([(int)$u['id']]);
$earned = (float)$st->fetch()['s'];
function tx_icon(string $t): string {
    if (stripos($t, 'hash') !== false) return 'fa-hashtag';
    if (stripos($t, 'spin') !== false) return 'fa-sync';
    if (stripos($t, 'referral') !== false) return 'fa-users';
    if (stripos($t, 'withdraw') !== false) return 'fa-wallet';
    if (stripos($t, 'deposit') !== false || stripos($t, 'upgrade') !== false) return 'fa-arrow-up';
    return 'fa-receipt';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<?php if (site_favicon()): ?><link rel="icon" href="<?php echo e(site_favicon()); ?>"><?php endif; ?>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Transactions - <?php echo e(site_name()); ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Signika+Negative:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script>tailwind.config={theme:{extend:{fontFamily:{sans:['Signika Negative','sans-serif']}}}}</script>
<style>body{font-family:'Signika Negative',sans-serif;padding-bottom:70px;background:linear-gradient(180deg,#f8fafc 0%,#eff6ff 100%);min-height:100vh}.bottom-nav-item.active{color:#2563eb;background:linear-gradient(180deg,rgba(37,99,235,.1) 0%,transparent 100%)}</style>
</head>
<body class="min-h-screen">
<header class="bg-white sticky top-0 z-40 shadow-sm"><div class="flex items-center justify-between px-4 py-3">
<a href="/users/dashboard.php" class="flex items-center gap-2"><img src="<?php echo e(site_logo()); ?>" class="w-8 h-8" alt="logo"><span class="font-bold text-lg text-blue-800"><?php echo e(site_name()); ?></span></a>
<div class="flex items-center gap-3"><a href="/users/support.php" class="text-blue-600"><i class="fas fa-life-ring text-xl"></i></a><a href="/users/notifications.php" class="text-gray-600"><i class="fas fa-bell text-xl"></i></a></div>
</div></header>
<main class="px-4 py-4">
<div class="bg-gradient-to-br from-blue-600 via-blue-700 to-blue-900 rounded-2xl p-4 mb-4 text-white relative overflow-hidden">
<div class="absolute top-0 right-0 w-24 h-24 bg-white/10 rounded-full -mr-8 -mt-8"></div>
<div class="relative">
<h1 class="text-xl font-bold">Transactions</h1>
<p class="text-blue-200 text-xs">Total earned: $<?php echo e(money($earned)); ?> · <?php echo (int)$total; ?> records</p>
</div></div>
<div class="flex gap-2 mb-3 overflow-x-auto pb-1">
<?php foreach (['all'=>'All','Hash Reward'=>'Hash','Spin Reward'=>'Spin','Referral Reward'=>'Referral','Withdrawal'=>'Withdrawals','Deposit'=>'Deposits'] as $k=>$label): ?>
<a href="/users/transactions.php?type=<?php echo urlencode($k); ?>" class="flex-shrink-0 px-3 py-1.5 rounded-xl text-xs font-semibold <?php echo $type===$k?'bg-blue-600 text-white':'bg-white text-gray-600 shadow-sm'; ?>"><?php echo $label; ?></a>
<?php endforeach; ?>
</div>
<div class="bg-white rounded-xl shadow-sm overflow-hidden mb-4">
<?php if ($rows): foreach ($rows as $t): $neg = (float)$t['amount'] < 0; ?>
<div class="flex items-center gap-3 p-3 border-b border-gray-100 last:border-0">
<div class="w-9 h-9 bg-blue-50 rounded-lg flex items-center justify-center flex-shrink-0"><i class="fas <?php echo tx_icon($t['type']); ?> text-blue-600 text-sm"></i></div>
<div class="flex-1 min-w-0">
<p class="font-semibold text-gray-800 text-sm truncate"><?php echo e($t['type']); ?></p>
<p class="text-xs text-gray-400"><?php echo e(date('M j, Y g:i A', strtotime($t['created_at']))); ?> · <?php echo e($t['reference']); ?></p>
</div>
<div class="text-right flex-shrink-0">
<p class="font-bold text-sm <?php echo $neg?'text-red-600':'text-green-600'; ?>"><?php echo $neg?'-':'+'; ?>$<?php echo e(money(abs((float)$t['amount']))); ?></p>
<p class="text-[10px] text-gray-400"><?php echo e(ucfirst($t['status'])); ?></p>
</div>
</div>
<?php endforeach; else: ?>
<p class="p-8 text-center text-sm text-gray-500">No transactions yet.</p>
<?php endif; ?>
</div>
<div class="flex items-center justify-between">
<?php if ($page>1): ?><a href="/users/transactions.php?type=<?php echo urlencode($type); ?>&page=<?php echo $page-1; ?>" class="px-4 py-2 bg-white rounded-xl text-sm font-semibold text-blue-600 shadow-sm">Previous</a><?php else: ?><div class="px-4 py-2 bg-gray-100 rounded-xl text-sm text-gray-400">Previous</div><?php endif; ?>
<span class="text-xs text-gray-500">Page <?php echo $page; ?> of <?php echo $pages; ?></span>
<?php if ($page<$pages): ?><a href="/users/transactions.php?type=<?php echo urlencode($type); ?>&page=<?php echo $page+1; ?>" class="px-4 py-2 bg-blue-600 rounded-xl text-sm font-semibold text-white shadow-sm">Next</a><?php else: ?><div class="px-4 py-2 bg-gray-100 rounded-xl text-sm text-gray-400">Next</div><?php endif; ?>
</div>
</main>
<nav class="fixed bottom-0 left-0 right-0 bg-white/95 backdrop-blur z-50 border-t flex">
<a href="/users/dashboard.php" class="flex-1 flex flex-col items-center py-2 text-xs text-gray-500"><i class="fas fa-home text-lg"></i>Home</a>
<a href="/users/hash.php" class="flex-1 flex flex-col items-center py-2 text-xs text-gray-500"><i class="fas fa-hashtag text-lg"></i>Hash</a>
<a href="/users/spin.php" class="flex-1 flex flex-col items-center py-2 text-xs text-gray-500"><i class="fas fa-sync text-lg"></i>Spin</a>
<a href="/users/withdraw.php" class="flex-1 flex flex-col items-center py-2 text-xs text-gray-500"><i class="fas fa-wallet text-lg"></i>Withdraw</a>
<a href="/users/profile.php" class="flex-1 flex flex-col items-center py-2 text-xs text-gray-500"><i class="fas fa-user text-lg"></i>Profile</a>
</nav>
</body>
</html>

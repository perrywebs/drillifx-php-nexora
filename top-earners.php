<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
maintenance_gate_public();
$pdo = db();
$page = max(1, min(100, (int)($_GET['page'] ?? 1)));
$per = 20;
try {
    $total = (int)($pdo->query('SELECT COUNT(*) c FROM users')->fetch()['c'] ?? 0);
    $sum = (float)($pdo->query('SELECT COALESCE(SUM(total_earned),0) s FROM users')->fetch()['s'] ?? 0);
    $pages = max(1, (int)ceil($total / $per));
    $page = min($page, $pages);
    $off = ($page - 1) * $per;
$st = $pdo->prepare('SELECT u.username, u.total_earned, COALESCE(t.display_name, ?) tier FROM users u LEFT JOIN tiers t ON u.tier_id=t.id ORDER BY u.total_earned DESC LIMIT ? OFFSET ?');
$st->bindValue(1, 'Free Tier'); $st->bindValue(2, $per, PDO::PARAM_INT); $st->bindValue(3, $off, PDO::PARAM_INT);
$st->execute();
$rows = $st->fetchAll();
$st3 = $pdo->query('SELECT u.username, u.total_earned, COALESCE(t.display_name, ?) tier FROM users u LEFT JOIN tiers t ON u.tier_id=t.id ORDER BY u.total_earned DESC LIMIT 3');
$podium = $st3 ? $st3->fetchAll() : [];
} catch (Throwable $e) { $total = 0; $sum = 0; $pages = 1; $rows = []; $podium = []; }
$from = $total ? $off + 1 : 0; $to = min($total, $off + $per);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<?php seo_head(['title' => 'Top Earners - ' . site_name(), 'description' => 'See the top ' . site_name() . ' earners ranked by total USDC earned.', 'path' => '/top-earners.php']); ?>
<script src="https://cdn.tailwindcss.com/3.4.17"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Signika+Negative:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script>tailwind.config={theme:{extend:{fontFamily:{sans:['Signika Negative','sans-serif']}}}}</script>
<style>body{font-family:'Signika Negative',sans-serif;padding-bottom:70px;background:linear-gradient(180deg,#f8fafc 0%,#eff6ff 100%);min-height:100vh}</style>
</head>
<body class="min-h-screen">
<header class="bg-white border-b border-gray-100 shadow-sm sticky top-0 z-40">
<div class="px-4 py-3"><div class="flex items-center justify-between">
<a href="/index.php" class="flex items-center gap-2"><img src="<?php echo e(site_logo()); ?>" alt="USDC Core" class="w-8 h-8"><span class="text-lg font-bold text-gray-800"><?php echo e(site_name()); ?></span></a>
<div class="flex items-center gap-2"><a href="/login.php" class="text-sm font-medium text-gray-600 px-3 py-1.5">Login</a><a href="/register.php" class="bg-blue-600 text-white text-sm font-medium px-4 py-1.5 rounded-xl">Register</a></div>
</div></div></header>
<main class="px-4 py-4">
<div class="bg-gradient-to-br from-blue-600 via-blue-700 to-blue-900 rounded-2xl p-4 mb-4 text-white relative overflow-hidden">
<div class="relative">
<div class="flex items-center gap-3 mb-4">
<div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center"><i class="fas fa-trophy"></i></div>
<div><h1 class="text-lg font-bold">Top 100 Earners</h1><p class="text-blue-200 text-xs">Leaderboard Rankings</p></div>
</div>
<div class="grid grid-cols-3 gap-2 text-center">
<div class="bg-white/10 rounded-xl p-2"><p class="text-lg font-bold"><?php echo e((string)$total); ?></p><p class="text-blue-200 text-[10px]">Total Earners</p></div>
<div class="bg-white/10 rounded-xl p-2"><p class="text-lg font-bold">$<?php echo e(number_format($sum, 0)); ?></p><p class="text-blue-200 text-[10px]">Total Earned</p></div>
<div class="bg-white/10 rounded-xl p-2"><p class="text-lg font-bold"><?php echo e((string)$pages); ?></p><p class="text-blue-200 text-[10px]">Pages</p></div>
</div></div></div>
<?php if (count($podium) >= 3): ?>
<div class="mb-4"><h2 class="text-gray-700 font-semibold mb-2 text-sm">Top 3 Champions</h2>
<div class="grid grid-cols-3 gap-2">
<?php $order=[1,0,2]; foreach ($order as $k): $p=$podium[$k]; ?>
<div class="rounded-xl p-3 shadow-sm text-center <?php echo $k===0?'bg-gradient-to-br from-yellow-50 to-yellow-100 border border-yellow-200':'bg-white'; ?>">
<div class="w-<?php echo $k===0?'12':'10'; ?> h-<?php echo $k===0?'12':'10'; ?> rounded-full flex items-center justify-center mx-auto mb-2 shadow-lg <?php echo $k===0?'bg-gradient-to-br from-yellow-400 to-yellow-600':($k===1?'bg-gradient-to-br from-gray-300 to-gray-500':'bg-gradient-to-br from-orange-400 to-orange-600'); ?>">
<?php if($k===0): ?><i class="fas fa-star text-white"></i><?php else: ?><span class="font-bold text-white text-sm"><?php echo $k+1; ?></span><?php endif; ?>
</div>
<p class="font-semibold text-gray-800 text-xs truncate"><?php echo e(mask_username($p['username'])); ?></p>
<p class="text-green-600 font-bold text-sm">$<?php echo e(number_format((float)$p['total_earned'],2)); ?></p>
<p class="text-[10px] text-gray-400"><?php echo e($p['tier']); ?></p>
</div>
<?php endforeach; ?></div></div>
<?php endif; ?>
<div class="flex items-center justify-between mb-2"><h2 class="text-gray-700 font-semibold text-sm">All Rankings</h2><p class="text-xs text-gray-500"><?php echo $from; ?>-<?php echo $to; ?> of <?php echo $total; ?></p></div>
<div class="bg-white rounded-xl shadow-sm overflow-hidden mb-4">
<?php $rank=$off; foreach ($rows as $r): $rank++; ?>
<div class="flex items-center gap-3 p-3 border-b border-gray-100">
<div class="w-9 h-9 rounded-full flex items-center justify-center shadow flex-shrink-0 <?php echo $rank===1?'bg-gradient-to-br from-yellow-400 to-yellow-600':($rank===2?'bg-gradient-to-br from-gray-300 to-gray-500':($rank===3?'bg-gradient-to-br from-orange-400 to-orange-600':'bg-gray-100')); ?>">
<?php if($rank===1): ?><i class="fas fa-star text-white text-xs"></i><?php else: ?><span class="font-bold <?php echo $rank<=3?'text-white':'text-gray-500'; ?> text-sm"><?php echo $rank; ?></span><?php endif; ?>
</div>
<div class="flex-1 min-w-0"><p class="font-semibold text-gray-800 text-sm truncate"><?php echo e(mask_username($r['username'])); ?></p><p class="text-xs text-gray-500"><?php echo e($r['tier']); ?></p></div>
<div class="text-right flex-shrink-0"><p class="font-bold text-green-600 text-sm">$<?php echo e(number_format((float)$r['total_earned'],2)); ?></p><p class="text-[10px] text-gray-400">Earned</p></div>
</div>
<?php endforeach; ?>
<?php if (!$rows): ?><p class="p-6 text-center text-sm text-gray-500">No earners yet. Be the first — <a class="text-blue-600 font-semibold" href="/register.php">create a free account</a>.</p><?php endif; ?>
</div>
<div class="flex items-center justify-between">
<?php if ($page>1): ?><a href="/top-earners.php?page=<?php echo $page-1; ?>" class="px-4 py-2 bg-white rounded-xl text-sm font-semibold text-blue-600 shadow-sm">Previous</a><?php else: ?><div class="px-4 py-2 bg-gray-100 rounded-xl text-sm text-gray-400 cursor-not-allowed">Previous</div><?php endif; ?>
<span class="text-xs text-gray-500">Page <?php echo $page; ?> of <?php echo $pages; ?></span>
<?php if ($page<$pages): ?><a href="/top-earners.php?page=<?php echo $page+1; ?>" class="px-4 py-2 bg-blue-600 rounded-xl text-sm font-semibold text-white shadow-sm">Next</a><?php else: ?><div class="px-4 py-2 bg-gray-100 rounded-xl text-sm text-gray-400 cursor-not-allowed">Next</div><?php endif; ?>
</div>
<div class="text-center mt-6"><a href="/register.php" class="inline-block bg-blue-600 text-white px-8 py-3 rounded-2xl font-semibold">Get Started Free</a></div>
</main>
<p class="text-center text-gray-400 text-xs py-8 bg-white">&copy; 2026 <?php echo e(site_name()); ?>. All rights reserved.</p>
</body>
</html>

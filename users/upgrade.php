<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
$pdo = db();
$u = require_login();
// Determine user's current tier from the unified tiers table
$st = $pdo->prepare('SELECT * FROM tiers WHERE id = ? LIMIT 1');
$st->execute([(int)$u['tier_id']]);
$currentTier = $st->fetch() ?: ['name'=>'Free Tier','daily_hashes'=>1,'daily_spins'=>1,'display_name'=>'Free Tier','display_rating'=>'','daily_hash_allowance'=>1,'daily_spin_allowance'=>1,'referral_requirement'=>3,'requirement_type'=>'fixed'];
$tierName = $u['tier_name'] ?? $currentTier['display_name'] ?? 'Free Tier';
$rating = $currentTier['display_rating'] ?? '';
$hashLimit = (int)($currentTier['daily_hash_allowance'] ?? 1);
$spinLimit = (int)($currentTier['daily_spin_allowance'] ?? 1);
// Get user's referral count
$st = $pdo->prepare('SELECT COUNT(*) c FROM referrals WHERE referrer_id=?'); $st->execute([(int)$u['id']]); $refCount = (int)$st->fetch()['c'];
// Get user's current tier daily progress
$hashDone = today_count('hashes', (int)$u['id']); $spinDone = today_count('spins', (int)$u['id']);
// Get pending deposits
$st = db()->prepare('SELECT tier_id FROM deposits WHERE user_id=? AND status="pending"');
$st->execute([(int)$u['id']]);
$pendingTiers = array_map('intval', array_column($st->fetchAll(), 'tier_id'));
// Get upgrade tiers from the tiers table (those with star ratings - dynamic, not hardcoded)
$allOpts = $pdo->query('SELECT * FROM tiers WHERE display_rating != "" ORDER BY id')->fetchAll();
?>
<!DOCTYPE html>

<html lang="en">

<head>
    <meta charset="UTF-8">
<?php if (site_favicon()): ?><link rel="icon" href="<?php echo e(site_favicon()); ?>"><?php endif; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Upgrade Tier - <?php echo e(site_name()); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Signika+Negative:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Signika Negative', 'sans-serif'],
                    },
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            200: '#bfdbfe',
                            300: '#93c5fd',
                            400: '#60a5fa',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Signika Negative', sans-serif;
            padding-bottom: 70px;
            background: linear-gradient(180deg, #f8fafc 0%, #eff6ff 100%);
            min-height: 100vh;
        }

        /* Removed gradient-header, now using clean white header */
        .bottom-nav-item.active {
            color: #2563eb;
            background: linear-gradient(180deg, rgba(37, 99, 235, 0.1) 0%, transparent 100%);
        }

        .bottom-nav-item.active::before {
            content: '';
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 40px;
            height: 3px;
            background: #2563eb;
            border-radius: 0 0 4px 4px;
        }

        /* Badge animation */
        @keyframes pulse-badge {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.1);
            }
        }

        .badge-pulse {
            animation: pulse-badge 2s ease-in-out infinite;
        }

        /* Support icon spinning animation */
        @keyframes spin-slow {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .support-icon-spin {
            animation: spin-slow 4s linear infinite;
        }
    </style>
</head>

<body class="min-h-screen">

    <!-- Redesigned header - clean white with subtle shadow -->
    <header class="bg-white border-b border-gray-100 shadow-sm sticky top-0 z-40">
        <div class="px-4 py-3">
            <div class="flex items-center justify-between">
                <a href="/users/dashboard.php" class="flex items-center gap-2">
                    <img src="<?php echo e(site_logo()); ?>" alt="USDC Core" class="w-8 h-8">
                    <span class="text-lg font-bold text-gray-800"><?php echo e(site_name()); ?></span>
                </a>
                <div class="flex items-center gap-2">
                    <!-- Added support icon -->
                    <!-- Added animation class to support icon -->
                    <a href="/users/support.php"
                        class="w-9 h-9 bg-gray-50 hover:bg-blue-50 rounded-xl flex items-center justify-center transition group">
                        <svg class="w-5 h-5 text-gray-500 group-hover:text-blue-600 support-icon-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </a>
                    <!-- Notifications with unread badge -->
                    <a href="/users/notifications.php"
                        class="w-9 h-9 bg-gray-50 hover:bg-blue-50 rounded-xl flex items-center justify-center transition relative group">
                        <svg class="w-5 h-5 text-gray-500 group-hover:text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </header>


    <main class="px-4 py-4">

        <!-- Redesigned header card to match dashboard with stats row -->
        <div
            class="bg-gradient-to-br from-blue-600 via-blue-700 to-blue-900 rounded-2xl p-4 mb-4 text-white relative overflow-hidden">
            <div class="absolute top-0 right-0 w-24 h-24 bg-white/10 rounded-full -mr-8 -mt-8"></div>
            <div class="absolute bottom-0 left-0 w-16 h-16 bg-white/5 rounded-full -ml-6 -mb-6"></div>

            <div class="relative">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-lg font-bold">Upgrade Your Account</h1>
                        <p class="text-blue-200 text-xs">Maximize your daily earnings</p>
                    </div>
                </div>

                <!-- Current tier info -->
                <div class="bg-white/10 backdrop-blur rounded-xl p-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-200 text-xs">Current Tier</p>
                            <p class="text-lg font-bold"><?php echo e($tierName); ?></p>
                        </div>
                        <div class="text-right">
<?php if (!empty($currentTier)): ?>
                            <span
                                class="inline-flex items-center gap-1 bg-yellow-500/20 px-2 py-1 rounded-full text-xs">
                                <svg class="w-3 h-3 text-yellow-300" fill="currentColor" viewBox="0 0 20 20">
                                    <path
                                        d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                </svg>
                                <?php echo $currentTier['display_name']; ?> <?php echo $currentTier['display_rating']; ?>
<?php else: ?>
                                <span class="text-gray-500">Tier 0</span>
<?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Added section title matching dashboard style -->
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-gray-700 font-semibold text-sm">Available Tiers</h2>
<?php if (!empty($currentTier)): ?>
            <span class="text-xs text-gray-500">Your level: <?php echo $currentTier['display_name']; ?> (<?php echo $currentTier['display_rating']; ?>)</span>
<?php else: ?>
            <span class="text-xs text-gray-500">Tier 0</span>
<?php endif; ?>
        </div>

        <!-- Redesigned tier cards with modern styling and SVG icons -->
        <div class="space-y-3 mb-4">
<?php if (!empty($currentTier)): ?>
<!-- Show upgrade tiers from database -->
<?php // Filter to only the 3 upgrade tiers (those with star ratings) upgrade tiers
$upgradeTiers = array_values(array_filter($allOpts, function($o) { return $o['display_rating'] !== '' && $o['id'] > 4; })); ?>
<?php foreach ($upgradeTiers as $opt): $isCurrent = false; ?>
<div class="bg-white rounded-2xl shadow-sm overflow-hidden <?php echo $isCurrent ? 'ring-2 ring-green-500' : ''; ?>">
    <div class="bg-gradient-to-r from-gray-50 to-gray-100 p-3">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="flex-shrink-0 w-10 h-10 bg-gradient-to-br from-blue-500 to-blue-700 rounded-xl flex items-center justify-center text-white font-bold text-sm">
                    <?php echo $opt['display_rating']; ?>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="font-bold text-sm text-gray-800 truncate"><?php echo $opt['display_name']; ?></h3>
                    <p class="text-xs text-gray-500"><?php echo (int)$opt['daily_hash_allowance']; ?> hashes | <?php echo (int)$opt['daily_spin_allowance']; ?> spins</p>
                </div>
            </div>
            <div class="text-right">
                <p class="text-xl font-bold text-blue-600">$<?php echo e(money($opt['price'])); ?></p>
<?php if ($refCount >= $opt['referral_requirement']): ?><span class="text-[10px] font-bold text-green-600 bg-green-100 px-2 py-0.5 rounded-full">CURRENT</span><?php endif; ?>
            </div>
        </div>
    </div>
    <div class="p-3">
        <div class="grid grid-cols-3 gap-2 mb-3">
            <div class="text-center p-2 bg-blue-50 rounded-xl">
                <p class="text-sm font-bold text-gray-800"><?php echo (int)$opt['daily_hash_allowance']; ?></p>
                <p class="text-xs text-gray-500">Hashes</p>
            </div>
            <div class="text-center p-2 bg-green-50 rounded-xl">
                <p class="text-sm font-bold text-gray-800">$<?php echo e(money($opt['price'])); ?></p>
                <p class="text-xs text-gray-500">Price</p>
            </div>
            <div class="text-center p-2 bg-purple-50 rounded-xl">
                <p class="text-sm font-bold text-gray-800"><?php echo (int)$opt['daily_spin_allowance']; ?></p>
                <p class="text-xs text-gray-500">Spins</p>
            </div>
        </div>
        <div class="bg-gray-50 rounded-xl p-2 mb-3">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Referral Requirement</p>
            <div class="grid grid-cols-2 gap-1 text-center">
                <div><p class="font-bold text-gray-800 text-sm"><?php echo (int)$opt['referral_requirement']; ?></p><p class="text-xs text-gray-500">referrals</p></div>
                <div><p class="font-bold text-gray-800 text-sm"><?php echo $opt['requirement_type'] === 'threshold' ? 'more than' : 'exact'; ?></p><p class="text-xs text-gray-500"><?php echo $opt['requirement_type'] === 'threshold' ? '10' : ''; ?>+</p></div>
            </div>
        </div>
<?php if ($refCount >= $opt['referral_requirement']): ?>
        <div class="block w-full bg-green-100 text-green-700 py-2.5 rounded-xl font-semibold text-sm text-center">Eligible</div>
<?php else: ?>
        <a href="/users/deposit.php?tier=<?php echo $opt['id']; ?>&tier_name=<?php echo urlencode($opt['display_name']); ?>" class="block w-full bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white py-2.5 rounded-xl font-semibold text-sm text-center transition">Upgrade Now</a>
<?php endif; ?>
</div>
</div>
<?php endforeach; ?>
<?php else: ?>
<!-- Fallback: no current tier -->
<div class="bg-white rounded-2xl shadow-sm overflow-hidden">
    <div class="bg-gradient-to-r from-gray-50 to-gray-100 p-3">
        <p class="text-gray-700 text-sm">Please log in to see upgrade options.</p>
    </div>
</div>
<?php endif; ?>
</div>

    </main>

    <!-- Redesigned bottom navigation with modern SVG icons -->
    <nav
        class="fixed bottom-0 left-0 right-0 bg-white/95 backdrop-blur-lg border-t border-gray-100 shadow-2xl z-50 safe-area-bottom">
        <div class="flex justify-around items-center h-16 max-w-lg mx-auto">
            <!-- Home -->
            <!-- Added pathPrefix to all navigation links -->
            <a href="/users/dashboard.php"
                class="bottom-nav-item flex flex-col items-center justify-center flex-1 h-full relative text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                <span class="text-xs font-medium">Home</span>
            </a>
            <!-- Hash -->
            <a href="/users/hash.php"
                class="bottom-nav-item flex flex-col items-center justify-center flex-1 h-full relative text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                </svg>
                <span class="text-xs font-medium">Hash</span>
            </a>
            <!-- Spin -->
            <a href="/users/spin.php"
                class="bottom-nav-item flex flex-col items-center justify-center flex-1 h-full relative text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                <span class="text-xs font-medium">Spin</span>
            </a>
            <!-- Withdraw -->
            <a href="/users/withdraw.php"
                class="bottom-nav-item flex flex-col items-center justify-center flex-1 h-full relative text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                <span class="text-xs font-medium">Withdraw</span>
            </a>
            <!-- Profile -->
            <a href="/users/profile.php"
                class="bottom-nav-item flex flex-col items-center justify-center flex-1 h-full relative text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                <span class="text-xs font-medium">Profile</span>
            </a>
        </div>
    </nav>

    <script>
        // Modal functionality
        function openModal(id) {
            document.getElementById(id).classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
        function closeModal(id) {
            document.getElementById(id).classList.add('hidden');
            document.body.style.overflow = '';
        }

        // Toast notification
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `fixed top-16 left-1/2 -translate-x-1/2 px-4 py-2 rounded-xl text-sm z-50 shadow-lg ${type === 'success' ? 'bg-green-600 text-white' : 'bg-red-600 text-white'}`;
            toast.textContent = message;
            document.body.appendChild(toast);
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transition = 'opacity 0.3s';
                setTimeout(() => toast.remove(), 300);
            }, 2000);
        }
    </script>
</body>

</html>
<?php echo render_toast_queue(); ?>
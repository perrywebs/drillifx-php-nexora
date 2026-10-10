<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
$u = require_login();
$pdo = db();
// Determine user's effective tier from the unified tiers table
$st = $pdo->prepare('SELECT * FROM tiers WHERE id = ? LIMIT 1');
$st->execute([(int)$u['tier_id']]);
$tier = $st->fetch() ?: ['display_name'=>'Free Tier','display_rating'=>'','daily_hashes'=>1,'daily_spins'=>1,'per_hash'=>0];
$tierName = $u['tier_name'] ?? $tier['display_name'] ?? 'Free Tier';
$perHash = (float)($tier['per_hash'] ?? 0);
$limit = (int)($tier['daily_hashes'] ?? 1);
$rating = $tier['display_rating'] ?? '⭐⭐';
$done = today_count('hashes', (int)$u['id']);
$left = max(0, $limit - $done);
$pct = $limit > 0 ? (int)round($done / $limit * 100) : 0;
$maxDaily = $perHash * $limit;
// Non-JS fallback: normal POST credits the hash then PRG-redirects
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hash'])) {
    if (!csrf_check($_POST['csrf'] ?? null)) { flash('error','Security check','Invalid session.'); redirect('/users/hash.php'); }
    require_once __DIR__ . '/../includes/hash_logic.php';
    $r = do_hash((int)$u['id']);
    flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Hash successful' : 'Hash failed', $r['msg']);
    redirect('/users/hash.php');
}
?><!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
<?php if (site_favicon()): ?><link rel="icon" href="<?php echo e(site_favicon()); ?>"><?php endif; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Hash & Earn - <?php echo e(site_name()); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
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
                        <svg class="w-5 h-5 text-gray-500 group-hover:text-blue-600 support-icon-spin" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </a>
                    <!-- Notifications with unread badge -->
                    <a href="/users/notifications.php"
                        class="w-9 h-9 bg-gray-50 hover:bg-blue-50 rounded-xl flex items-center justify-center transition relative group">
                        <svg class="w-5 h-5 text-gray-500 group-hover:text-blue-600" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </header>


    <main class="px-4 py-4">

        <!-- Toast Notification Container -->
        <div id="toastContainer" class="fixed z-50 w-full max-w-sm px-4 sm:px-6 right-[1px]">
            <!-- Toast will be inserted here -->
        </div>

        <!-- Optional reward sound (only rendered when the file exists) -->
        <?php if (is_file(__DIR__ . '/../images/cashier-quotka-chingquot-sound-effect.mp3')): ?><audio id="hashSound" preload="auto" src="<?php echo e(url('/images/cashier-quotka-chingquot-sound-effect.mp3')); ?>"></audio><?php endif; ?>

        <!-- Tier Expired Notification - using toast style -->

        <!-- Header card matching dashboard gradient style -->
        <div
            class="bg-gradient-to-br from-blue-600 via-blue-700 to-blue-900 rounded-2xl p-4 mb-4 text-white relative overflow-hidden">
            <div class="absolute top-0 right-0 w-24 h-24 bg-white/10 rounded-full -mr-8 -mt-8"></div>
            <div class="absolute bottom-0 left-0 w-16 h-16 bg-white/5 rounded-full -ml-6 -mb-6"></div>

            <div class="relative">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <h1 class="text-xl font-bold">Hash & Earn</h1>
                        <p class="text-blue-200 text-xs">Click to mine USDC rewards</p>
                    </div>
                    <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                        </svg>
                    </div>
                </div>

                <!-- Current Tier Badge -->
                <div class="bg-white/10 backdrop-blur rounded-xl p-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center gap-1 bg-white/20 px-2 py-1 rounded-full text-xs">
                                <svg class="w-3 h-3 text-yellow-300" fill="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                                </svg>
                                <?php echo e($tierName); ?> </span>
                        </div>
                        <div class="text-right">
                            <p class="text-blue-200 text-xs">Per Hash</p>
                            <p class="text-lg font-bold">$<?php echo e(money4($perHash)); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Progress Card -->
        <div class="bg-white rounded-2xl p-4 mb-4 shadow-sm">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-gray-700 font-semibold text-sm">Today's Progress</h2>
                <span class="text-blue-600 font-bold text-sm"><?php echo (int)$done; ?>/<?php echo (int)$limit; ?></span>
            </div>
            <div class="w-full bg-gray-100 rounded-full h-3 mb-2">
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 h-3 rounded-full transition-all duration-500"
                    style="width: <?php echo (int)$pct; ?>%"></div>
            </div>
            <p class="text-xs text-gray-500 text-center" id="hashRemaining"><?php echo (int)$left; ?> hashes remaining today</p>
        </div>

        <!-- Main Hash Button Area -->
        <div class="bg-white rounded-2xl p-6 mb-4 shadow-sm text-center">
            <form method="POST" id="hashForm" action="/users/hash.php"><?php echo csrf_field(); ?>
                <!-- Added id and onclick handler for sound effect -->
                <button type="submit" name="hash" id="hashBtn"<?php echo $left <= 0 ? " disabled" : ""; ?>
                    class="w-32 h-32 rounded-full bg-gradient-to-br from-blue-500 to-blue-700 hover:from-blue-600 hover:to-blue-800 text-white font-bold shadow-lg hover:shadow-xl transition-all transform hover:scale-105 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100 mx-auto flex items-center justify-center relative overflow-hidden group">
                    <!-- Animated ring -->
                    <div
                        class="absolute inset-0 rounded-full border-4 border-white/20 group-hover:border-white/40 transition">
                    </div>
                    <div
                        class="absolute inset-2 rounded-full border-2 border-white/10 group-hover:border-white/20 transition">
                    </div>

                    <div class="relative z-10">
                        <svg class="w-10 h-10 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                        </svg>
                        <p class="text-sm font-bold">HASH</p>
                    </div>
                </button>
            </form>

            <p class="text-gray-500 text-xs mt-4">Tap the button to earn USDC</p>
        </div>

        <!-- Stats Grid matching dashboard style -->
        <div class="mb-4">
            <h2 class="text-gray-700 font-semibold mb-2 text-sm">Hash Stats</h2>
            <div class="grid grid-cols-2 gap-2">
                <div class="bg-white rounded-xl p-3 shadow-sm">
                    <div class="flex items-center gap-2">
                        <div
                            class="w-8 h-8 bg-gradient-to-br from-blue-100 to-blue-200 rounded-lg flex items-center justify-center">
                            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Hashes Left</p>
                            <p class="text-base font-bold text-gray-800"><?php echo (int)$left; ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl p-3 shadow-sm">
                    <div class="flex items-center gap-2">
                        <div
                            class="w-8 h-8 bg-gradient-to-br from-green-100 to-green-200 rounded-lg flex items-center justify-center">
                            <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Per Hash</p>
                            <p class="text-base font-bold text-gray-800">$<?php echo e(money4($perHash)); ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl p-3 shadow-sm">
                    <div class="flex items-center gap-2">
                        <div
                            class="w-8 h-8 bg-gradient-to-br from-purple-100 to-purple-200 rounded-lg flex items-center justify-center">
                            <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v8m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Daily Limit</p>
                            <p class="text-base font-bold text-gray-800"><?php echo (int)$limit; ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl p-3 shadow-sm">
                    <div class="flex items-center gap-2">
                        <div
                            class="w-8 h-8 bg-gradient-to-br from-orange-100 to-orange-200 rounded-lg flex items-center justify-center">
                            <svg class="w-4 h-4 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Max Daily</p>
                            <p class="text-base font-bold text-gray-800">$<?php echo e(money($maxDaily)); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upgrade CTA if not max tier -->
        <div
            class="bg-gradient-to-br from-blue-600 to-blue-800 rounded-xl p-4 mb-4 text-white relative overflow-hidden">
            <div class="absolute top-0 right-0 w-16 h-16 bg-white/10 rounded-full -mr-4 -mt-4"></div>
            <div class="relative flex items-center gap-3">
                <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-sm">Want more hashes?</p>
                    <p class="text-blue-200 text-xs">Upgrade your tier for higher limits & rewards</p>
                </div>
                <a href="/users/upgrade.php"
                    class="bg-white text-blue-600 px-3 py-1.5 rounded-lg text-xs font-bold hover:bg-blue-50 transition flex-shrink-0">
                    Upgrade
                </a>
            </div>
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
                class="bottom-nav-item flex flex-col items-center justify-center flex-1 h-full relative active">
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

<script>
    // Toast notification system
    class Toast {
        static show(message, type = 'info', duration = 5000) {
            const container = document.getElementById('toastContainer');
            if (!container) return;

            const toastId = 'toast-' + Date.now();
            const icon = this.getIcon(type);
            const bgColor = this.getBgColor(type);
            const borderColor = this.getBorderColor(type);

            const toast = document.createElement('div');
            toast.id = toastId;
            toast.className = `w-full mb-3 transform transition-all duration-300 ease-out opacity-0 translate-y-2`;
            toast.innerHTML = `
            <div class="bg-white ${borderColor} border-l-4 rounded-xl shadow-lg overflow-hidden">
                <div class="p-4 flex items-start gap-3">
                    <div class="${bgColor} rounded-full p-2 flex-shrink-0">
                        ${icon}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-800">${this.escapeHtml(message)}</p>
                    </div>
                    <button type="button" onclick="Toast.dismiss('${toastId}')" class="text-gray-400 hover:text-gray-600 flex-shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="h-1 w-full bg-gray-200 overflow-hidden">
                    <div id="${toastId}-progress" class="h-full ${this.getProgressColor(type)} transition-all duration-${duration} ease-linear" style="width: 100%"></div>
                </div>
            </div>
        `;

            container.appendChild(toast);

            // Animate in
            setTimeout(() => {
                toast.classList.remove('opacity-0', 'translate-y-2');
                toast.classList.add('opacity-100', 'translate-y-0');

                // Start progress bar
                setTimeout(() => {
                    const progressBar = document.getElementById(`${toastId}-progress`);
                    if (progressBar) {
                        progressBar.style.width = '0%';
                    }
                }, 10);
            }, 10);

            // Auto dismiss
            if (duration > 0) {
                setTimeout(() => {
                    this.dismiss(toastId);
                }, duration);
            }
        }

        static dismiss(toastId) {
            const toast = document.getElementById(toastId);
            if (!toast) return;

            toast.classList.remove('opacity-100', 'translate-y-0');
            toast.classList.add('opacity-0', 'translate-y-2');

            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 300);
        }

        static getIcon(type) {
            switch (type) {
                case 'success':
                    return `<svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>`;
                case 'error':
                    return `<svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>`;
                case 'warning':
                    return `<svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.34 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                </svg>`;
                default:
                    return `<svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>`;
            }
        }

        static getBgColor(type) {
            switch (type) {
                case 'success': return 'bg-green-500';
                case 'error': return 'bg-red-500';
                case 'warning': return 'bg-yellow-500';
                default: return 'bg-blue-500';
            }
        }

        static getBorderColor(type) {
            switch (type) {
                case 'success': return 'border-green-500';
                case 'error': return 'border-red-500';
                case 'warning': return 'border-yellow-500';
                default: return 'border-blue-500';
            }
        }

        static getProgressColor(type) {
            switch (type) {
                case 'success': return 'bg-green-500';
                case 'error': return 'bg-red-500';
                case 'warning': return 'bg-yellow-500';
                default: return 'bg-blue-500';
            }
        }

        static escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    }

    // Show PHP messages as toasts on page load
    document.addEventListener('DOMContentLoaded', function () {

        (function(){
var hashForm = document.getElementById('hashForm');
var hashBtn = document.getElementById('hashBtn');
        const hashSound = document.getElementById('hashSound');

        if (hashForm && hashBtn && !hashBtn.disabled) {
            hashForm.addEventListener('submit', function (e) {
                e.preventDefault();
                if (hashBtn.disabled) return;
                var csrf = (hashForm.querySelector('input[name=csrf]')||{}).value || '';
                hashBtn.disabled = true;
                hashBtn.style.opacity = '.6';
                $.ajax({
                    url: (window.APP_BASE||'')+'/ajax/hash.php', method: 'POST', dataType: 'json',
                    data: { csrf: csrf }, timeout: 15000
                }).done(function (res) {
                    if (res && res.success) {
                        try { if (hashSound) { hashSound.currentTime = 0; hashSound.play().catch(function(){}); } } catch (err) {}
                        Toast.show(res.message || 'Hash successful!', 'success', 5000);
                        if (res.data && typeof res.data.hashes_left !== 'undefined') {
                            var lr = document.getElementById('hashRemaining');
                            if (lr) lr.textContent = res.data.hashes_left + ' hashes remaining today';
                        }
                        setTimeout(function(){ window.location.reload(); }, 1200);
                    } else {
                        Toast.show((res && res.message) || 'Hash failed. Try again.', 'error', 5000);
                        hashBtn.disabled = false; hashBtn.style.opacity = '';
                    }
                }).fail(function () {
                    Toast.show('Network error. Please try again.', 'error', 5000);
                    hashBtn.disabled = false; hashBtn.style.opacity = '';
                });
            });
        }

        // Auto-play sound on successful hash (after page reload)
    });
</script>
<?php echo render_toast_queue(); ?>

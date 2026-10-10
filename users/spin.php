<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
$u = require_login();
$pdo = db();
// Determine user's effective tier from the unified tiers table
$st = $pdo->prepare('SELECT * FROM tiers WHERE id = ? LIMIT 1');
$st->execute([(int)$u['tier_id']]);
$tier = $st->fetch() ?: ['display_name'=>'Free Tier','display_rating'=>'','daily_hashes'=>1,'daily_spins'=>1];
$tierName = $u['tier_name'] ?? $tier['display_name'] ?? 'Free Tier';
$limit = (int)($tier['daily_spins'] ?? 1);
$rating = $tier['display_rating'] ?? '⭐⭐';
$done = today_count('spins', (int)$u['id']);
$left = max(0, $limit - $done);
$pct = $limit > 0 ? (int)round($done / $limit * 100) : 0;
// Non-JS fallback
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['spin'])) {
    if (!csrf_check($_POST['csrf'] ?? null)) { flash('error','Security check','Invalid session.'); redirect('/users/spin.php'); }
    require_once __DIR__ . '/../includes/spin_logic.php';
    $r = do_spin((int)$u['id']);
    flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Spin result' : 'Spin failed', $r['msg']);
    redirect('/users/spin.php');
}
?><!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
<?php if (site_favicon()): ?><link rel="icon" href="<?php echo e(site_favicon()); ?>"><?php endif; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Spin & Earn - <?php echo e(site_name()); ?></title>
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

        <!-- Changed purple gradient to blue to match dashboard -->
        <div
            class="bg-gradient-to-br from-blue-600 via-blue-700 to-blue-900 rounded-2xl p-4 mb-4 text-white relative overflow-hidden">
            <!-- Decorative circles -->
            <div class="absolute top-0 right-0 w-24 h-24 bg-white/10 rounded-full -mr-8 -mt-8"></div>
            <div class="absolute bottom-0 left-0 w-16 h-16 bg-white/5 rounded-full -ml-6 -mb-6"></div>

            <div class="relative">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <p class="text-blue-200 text-xs">Spin & Earn</p>
                        <h1 class="text-xl font-bold">Lucky Wheel</h1>
                    </div>
                    <div class="text-right">
                        <span class="inline-flex items-center gap-1 bg-white/20 px-2 py-1 rounded-full text-xs">
                            <svg class="w-3 h-3 text-yellow-300" fill="currentColor" viewBox="0 0 24 24">
                                <path
                                    d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" />
                            </svg>
                            <?php echo e($tierName); ?> </span>
                    </div>
                </div>

                <!-- Spin Progress -->
                <div class="bg-white/10 backdrop-blur rounded-xl p-3">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-blue-200 text-xs">Today's Spins</span>
                        <span class="text-white text-sm font-bold" id="spinCount"><?php echo (int)$done; ?> / <?php echo (int)$limit; ?></span>
                    </div>
                    <div class="w-full bg-white/20 rounded-full h-2">
                        <div class="bg-gradient-to-r from-yellow-400 to-yellow-500 h-2 rounded-full transition-all duration-500"
                            style="width: <?php echo (int)$pct; ?>%"></div>
                    </div>
                    <p class="text-blue-200 text-xs mt-1" id="spinRemaining"><?php echo (int)$left; ?> spins remaining</p>
                </div>
            </div>
        </div>

        <!-- Modern spin wheel design -->
        <div class="bg-white rounded-2xl shadow-sm p-4 mb-4">
            <div class="relative mb-4">
                <!-- Wheel Container -->
                <div class="relative w-56 h-56 mx-auto">
                    <!-- Changed outer glow to blue theme -->
                    <div
                        class="absolute inset-0 rounded-full bg-gradient-to-r from-blue-400 via-cyan-500 to-blue-500 animate-pulse opacity-30 blur-xl">
                    </div>

                    <!-- Wheel segments -->
                    <div class="relative w-full h-full rounded-full bg-gradient-conic from-blue-500 via-cyan-500 via-green-500 via-yellow-500 via-orange-500 via-red-500 via-pink-500 to-blue-500 p-1 shadow-2xl"
                        id="wheel">
                        <div
                            class="w-full h-full rounded-full bg-gradient-to-br from-gray-900 to-gray-800 flex items-center justify-center relative overflow-hidden">
                            <!-- Inner decorative rings -->
                            <div class="absolute inset-4 rounded-full border-2 border-white/10"></div>
                            <div class="absolute inset-8 rounded-full border border-white/5"></div>

                            <!-- Prize amounts arranged in circle -->
                            <div class="absolute inset-0 flex items-center justify-center">
                                <div class="text-center relative z-10">

                                    <div
                                        class="w-16 h-16 bg-gradient-to-br from-blue-600 to-blue-800 rounded-full flex items-center justify-center shadow-lg border-4 border-yellow-400">
                                        <svg class="w-7 h-7 text-yellow-400" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                </div>
                            </div>

                            <!-- Moved segment labels inward to avoid touching the border -->
                            <div class="absolute top-8 left-1/2 -translate-x-1/2 text-yellow-400 text-xs font-bold">
                                $0.50</div>
                            <div class="absolute top-12 right-10 text-green-400 text-xs font-bold">$0.25</div>
                            <div class="absolute right-8 top-1/2 -translate-y-1/2 text-blue-400 text-xs font-bold">$0.20
                            </div>
                            <div class="absolute bottom-12 right-10 text-pink-400 text-xs font-bold">$0.15</div>
                            <div class="absolute bottom-8 left-1/2 -translate-x-1/2 text-orange-400 text-xs font-bold">
                                $0.10</div>
                            <div class="absolute bottom-12 left-10 text-red-400 text-xs font-bold">$0.05</div>
                            <div class="absolute left-8 top-1/2 -translate-y-1/2 text-cyan-400 text-xs font-bold">$0.02
                            </div>
                            <div class="absolute top-12 left-10 text-purple-400 text-xs font-bold">$0.01</div>
                        </div>
                    </div>

                    <!-- Pointer -->
                    <div class="absolute -top-1 left-1/2 -translate-x-1/2 z-10">
                        <div
                            class="w-0 h-0 border-l-[12px] border-r-[12px] border-t-[20px] border-l-transparent border-r-transparent border-t-yellow-400 drop-shadow-lg">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Spin Button - Changed to blue theme -->
            <form method="POST" class="text-center" id="spinForm" action="/users/spin.php"><?php echo csrf_field(); ?>
                <button type="submit" name="spin" id="spinBtn"<?php echo $left <= 0 ? "" : ""; ?>
                    class="relative group bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white px-8 py-3 rounded-xl font-bold text-base shadow-lg hover:shadow-xl transition-all disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:shadow-lg"
                    <?php echo $left <= 0 ? "disabled" : ""; ?>>
                    <span class="relative z-10 flex items-center justify-center gap-2">
                        <svg class="w-5 h-5 group-hover:animate-spin" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        <?php echo $left > 0 ? "SPIN NOW" : "NO SPINS LEFT"; ?> </span>
                    <!-- Button glow effect -->
                    <div
                        class="absolute inset-0 rounded-xl bg-blue-400 opacity-0 group-hover:opacity-20 transition-opacity">
                    </div>
                </button>
            </form>
        </div>

        <!-- Added Spin Rules / Info section -->
        <div class="bg-white rounded-2xl shadow-sm p-4 mb-4">
            <h2 class="text-gray-700 font-semibold mb-3 text-sm flex items-center gap-2">
                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Spin Rules & Info
            </h2>
            <div class="space-y-2">
                <div class="flex items-start gap-3 p-2 bg-blue-50 rounded-lg">
                    <div class="w-6 h-6 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                        <span class="text-blue-600 text-xs font-bold">1</span>
                    </div>
                    <p class="text-xs text-gray-600">Each spin gives you a chance to win between <span
                            class="font-semibold text-blue-600">$0.01</span> and <span
                            class="font-semibold text-blue-600">$0.50 USDC</span>.</p>
                </div>
                <div class="flex items-start gap-3 p-2 bg-green-50 rounded-lg">
                    <div
                        class="w-6 h-6 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                        <span class="text-green-600 text-xs font-bold">2</span>
                    </div>
                    <p class="text-xs text-gray-600">Your daily spin limit is determined by your <span
                            class="font-semibold text-green-600">account tier</span>. Upgrade for more spins!</p>
                </div>
                <div class="flex items-start gap-3 p-2 bg-yellow-50 rounded-lg">
                    <div
                        class="w-6 h-6 bg-yellow-100 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                        <span class="text-yellow-600 text-xs font-bold">3</span>
                    </div>
                    <p class="text-xs text-gray-600">Spins reset daily at <span
                            class="font-semibold text-yellow-600">midnight (00:00 UTC)</span>. Unused spins do not carry
                        over.</p>
                </div>
                <div class="flex items-start gap-3 p-2 bg-purple-50 rounded-lg">
                    <div
                        class="w-6 h-6 bg-purple-100 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                        <span class="text-purple-600 text-xs font-bold">4</span>
                    </div>
                    <p class="text-xs text-gray-600">Winnings are <span class="font-semibold text-purple-600">instantly
                            credited</span> to your available balance.</p>
                </div>
            </div>
        </div>

        <!-- Stats grid matching dashboard style -->
        <div class="mb-4">
            <h2 class="text-gray-700 font-semibold mb-2 text-sm">Spin Stats</h2>
            <div class="grid grid-cols-2 gap-2">
                <div class="bg-white rounded-xl p-3 shadow-sm">
                    <div class="flex items-center gap-2">
                        <div
                            class="w-8 h-8 bg-gradient-to-br from-blue-100 to-blue-200 rounded-lg flex items-center justify-center">
                            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Spins Left</p>
                            <p class="text-base font-bold text-gray-800"><?php echo (int)$left; ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl p-3 shadow-sm">
                    <div class="flex items-center gap-2">
                        <div
                            class="w-8 h-8 bg-gradient-to-br from-yellow-100 to-yellow-200 rounded-lg flex items-center justify-center">
                            <svg class="w-4 h-4 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Max Win</p>
                            <p class="text-base font-bold text-gray-800">$0.50</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl p-3 shadow-sm">
                    <div class="flex items-center gap-2">
                        <div
                            class="w-8 h-8 bg-gradient-to-br from-green-100 to-green-200 rounded-lg flex items-center justify-center">
                            <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
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
                            class="w-8 h-8 bg-gradient-to-br from-cyan-100 to-cyan-200 rounded-lg flex items-center justify-center">
                            <svg class="w-4 h-4 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Max Daily</p>
                            <p class="text-base font-bold text-gray-800">$0.50</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upgrade CTA - Changed to blue theme -->
        <div
            class="bg-gradient-to-br from-blue-600 to-blue-800 rounded-xl p-4 mb-4 text-white relative overflow-hidden">
            <div class="absolute top-0 right-0 w-16 h-16 bg-white/10 rounded-full -mr-4 -mt-4"></div>
            <div class="relative">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-bold text-sm">Want More Spins?</p>
                        <p class="text-blue-200 text-xs">Upgrade your tier for more daily spins!</p>
                    </div>
                    <a href="/users/upgrade.php"
                        class="bg-white text-blue-700 px-3 py-1.5 rounded-lg text-xs font-bold hover:bg-blue-50 transition flex-shrink-0">
                        Upgrade
                    </a>
                </div>
            </div>
        </div>

        <!-- Increased spin duration from 3s to 4.5s -->
        <style>
            @keyframes spin {
                from {
                    transform: rotate(0deg);
                }

                to {
                    transform: rotate(1800deg);
                }
            }

            .spinning {
                animation: spin 4.5s cubic-bezier(0.17, 0.67, 0.12, 0.99);
            }
        </style>

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
            });

            const spinBtn = document.getElementById('spinBtn');
            const wheel = document.getElementById('wheel');
            let audioContext;
            let isSpinning = false;

            function playTickerSound() {
                if (!audioContext) {
                    audioContext = new (window.AudioContext || window.webkitAudioContext)();
                }

                let tickCount = 0;
                const maxTicks = 50;
                let interval = 40;

                function tick() {
                    if (tickCount >= maxTicks) return;

                    const oscillator = audioContext.createOscillator();
                    const gainNode = audioContext.createGain();

                    oscillator.connect(gainNode);
                    gainNode.connect(audioContext.destination);

                    oscillator.type = 'sine';
                    oscillator.frequency.value = 600 + (tickCount * 8);

                    gainNode.gain.setValueAtTime(0.12, audioContext.currentTime);
                    gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.04);

                    oscillator.start(audioContext.currentTime);
                    oscillator.stop(audioContext.currentTime + 0.04);

                    tickCount++;
                    interval += tickCount * 3;

                    setTimeout(tick, interval);
                }

                tick();
            }

            (function(){
var spinForm = document.getElementById('spinForm');
var csrf = (spinForm && spinForm.querySelector('input[name=csrf]')||{}).value || '';
var pendingReward = null, spinFailed = false;
if (spinForm) spinForm.addEventListener('submit', function (e) {
    e.preventDefault();
    if (!spinBtn || spinBtn.disabled || isSpinning) return;
    isSpinning = true;
    spinBtn.disabled = true;
    spinBtn.style.opacity = '.6';
    wheel.classList.add('spinning');
    playTickerSound();
    pendingReward = null; spinFailed = false;
    $.ajax({ url: (window.APP_BASE||'')+'/ajax/spin.php', method: 'POST', dataType: 'json', data: { csrf: csrf }, timeout: 15000 })
      .done(function (res) {
        if (res && res.success) { pendingReward = (res.data && res.data.reward) || 0; }
        else { spinFailed = true; pendingReward = null; window.__spinErr = (res && res.message) || 'Spin failed. Try again.'; }
      })
      .fail(function () { spinFailed = true; window.__spinErr = 'Network error. Please try again.'; });
    // Always resolve visually when the 4.5s animation ends (matches .spinning duration)
    setTimeout(function () {
        wheel.classList.remove('spinning');
        isSpinning = false;
        if (spinFailed) {
            Toast.show(window.__spinErr || 'Spin failed. Try again.', 'error', 5000);
            spinBtn.disabled = false; spinBtn.style.opacity = '';
        } else if (pendingReward !== null) {
            Toast.show('You won $' + Number(pendingReward).toFixed(2) + '!', 'success', 5000);
            setTimeout(function(){ window.location.reload(); }, 1200);
        } else {
            // Response slower than animation: wait for it (max 10s) then resolve
            var waited = 0;
            var iv = setInterval(function () {
                waited += 250;
                if (pendingReward !== null || spinFailed || waited > 10000) {
                    clearInterval(iv);
                    if (spinFailed || pendingReward === null) { Toast.show(window.__spinErr || 'Spin failed. Try again.', 'error', 5000); spinBtn.disabled = false; spinBtn.style.opacity = ''; }
                    else { Toast.show('You won $' + Number(pendingReward).toFixed(2) + '!', 'success', 5000); setTimeout(function(){ window.location.reload(); }, 1200); }
                }
            }, 250);
        }
    }, 4500);
});
})();
        </script>

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
                class="bottom-nav-item flex flex-col items-center justify-center flex-1 h-full relative active">
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

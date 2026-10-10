<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
$u = require_login();
$page = max(1, min(100, (int)($_GET['page'] ?? 1)));
$per = 20;
$pdo = db();
$total = (int)($pdo->query('SELECT COUNT(*) c FROM users')->fetch()['c'] ?? 0);
$sum = (float)($pdo->query('SELECT COALESCE(SUM(total_earned),0) s FROM users')->fetch()['s'] ?? 0);
$pages = max(1, (int)ceil($total / $per));
$page = min($page, $pages);
$off = ($page - 1) * $per;
$st = $pdo->prepare('SELECT u.username, u.total_earned, COALESCE(t.name, ?) tier FROM users u LEFT JOIN tiers t ON t.id=u.tier_id ORDER BY u.total_earned DESC LIMIT ? OFFSET ?');
$st->bindValue(1, 'Free Tier'); $st->bindValue(2, $per, PDO::PARAM_INT); $st->bindValue(3, $off, PDO::PARAM_INT);
$st->execute();
$rows = $st->fetchAll();
$from = $total ? $off + 1 : 0; $to = min($total, $off + $per);
?><!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
<?php if (site_favicon()): ?><link rel="icon" href="<?php echo e(site_favicon()); ?>"><?php endif; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Top Earners - <?php echo e(site_name()); ?></title>
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

        <!-- Redesigned header card matching dashboard style -->
        <div
            class="bg-gradient-to-br from-blue-600 via-blue-700 to-blue-900 rounded-2xl p-4 mb-4 text-white relative overflow-hidden">
            <!-- Decorative circles -->
            <div class="absolute top-0 right-0 w-24 h-24 bg-white/10 rounded-full -mr-8 -mt-8"></div>
            <div class="absolute bottom-0 left-0 w-16 h-16 bg-white/5 rounded-full -ml-6 -mb-6"></div>

            <div class="relative">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path
                                d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-lg font-bold">Top 100 Earners</h1>
                        <p class="text-blue-200 text-xs">Leaderboard Rankings</p>
                    </div>
                </div>

                <!-- Quick Stats Row -->
                <div class="grid grid-cols-3 gap-2 text-center">
                    <div class="bg-white/10 rounded-xl p-2">
                        <p class="text-lg font-bold"><?php echo (int)$total; ?></p>
                        <p class="text-blue-200 text-[10px]">Total Earners</p>
                    </div>
                    <div class="bg-white/10 rounded-xl p-2">
                        <p class="text-lg font-bold">$<?php echo e(number_format($sum, 0)); ?></p>
                        <p class="text-blue-200 text-[10px]">Total Earned</p>
                    </div>
                    <div class="bg-white/10 rounded-xl p-2">
                        <p class="text-lg font-bold"><?php echo (int)$pages; ?></p>
                        <p class="text-blue-200 text-[10px]">Pages</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top 3 Podium Section -->

        <!-- Page Info -->
        <div class="flex items-center justify-between mb-2">
            <h2 class="text-gray-700 font-semibold text-sm">All Rankings</h2>
            <p class="text-xs text-gray-500">
                <?php echo (int)$from; ?>-<?php echo (int)$to; ?> of <?php echo (int)$total; ?> </p>
        </div>

        <!-- Earners List matching dashboard card style -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden mb-4">
<?php $rank = $off; foreach ($rows as $r): $rank++; ?>
            <div class="flex items-center gap-3 p-3 border-b border-gray-100">
                <div class="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0 <?php echo $rank <= 3 ? 'bg-gradient-to-br from-yellow-400 to-yellow-600' : 'bg-gray-100'; ?>">
                    <span class="font-semibold <?php echo $rank <= 3 ? 'text-white' : 'text-gray-500'; ?> text-sm"><?php echo (int)$rank; ?></span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-gray-800 text-sm truncate"><?php echo e(mask_username($r['username'])); ?><?php echo $r['username'] === $u['username'] ? ' (You)' : ''; ?></p>
                    <p class="text-xs text-gray-500"><?php echo e($r['tier']); ?></p>
                </div>
                <div class="text-right flex-shrink-0">
                    <p class="font-bold text-green-600 text-sm">$<?php echo e(number_format((float)$r['total_earned'], 2)); ?></p>
                    <p class="text-[10px] text-gray-400">Earned</p>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (!$rows): ?><p class="p-6 text-center text-sm text-gray-500">No earners yet.</p><?php endif; ?>
        <!-- Pagination matching dashboard style -->
        <div class="flex items-center justify-between gap-2 mb-4">
<?php if ($page > 1): ?>
            <a href="/users/top-earners.php?page=<?php echo $page - 1; ?>" class="flex-1 flex items-center justify-center gap-1 bg-white border border-gray-200 text-gray-700 py-2.5 px-3 rounded-xl text-sm font-semibold hover:bg-gray-50 transition">Previous</a>
            <?php else: ?>
            <div class="flex-1 flex items-center justify-center gap-1 bg-gray-100 text-gray-400 py-2.5 px-3 rounded-xl text-sm font-semibold cursor-not-allowed">Previous</div>
            <?php endif; ?>
            <div class="bg-blue-600 text-white px-3 py-2 rounded-xl font-bold text-sm"><?php echo (int)$page; ?></div>
            <?php if ($page < $pages): ?>
            <a href="/users/top-earners.php?page=<?php echo $page + 1; ?>" class="flex-1 flex items-center justify-center gap-1 bg-white border border-gray-200 text-gray-700 py-2.5 px-3 rounded-xl text-sm font-semibold hover:bg-gray-50 transition">Next</a>
            <?php else: ?>
            <div class="flex-1 flex items-center justify-center gap-1 bg-gray-100 text-gray-400 py-2.5 px-3 rounded-xl text-sm font-semibold cursor-not-allowed">Next</div>
            <?php endif; ?>
        </div>

            <a href="?page=4"
                class="flex-1 flex items-center justify-center gap-1 bg-white border border-gray-200 text-gray-700 py-2.5 px-3 rounded-xl text-sm font-semibold hover:bg-gray-50 transition">
                Next
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </a>
        </div>

        <!-- CTA for non-logged in users -->

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
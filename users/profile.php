<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
$u = require_login();
$pdo = db();
// Determine effective tier from the unified tiers table
$st = $pdo->prepare('SELECT * FROM tiers WHERE id = ? LIMIT 1');
$st->execute([(int)$u['tier_id']]);
$tier = $st->fetch() ?: ['display_name'=>'Free Tier','display_rating'=>''];
$tierName = $u['tier_name'] ?? $tier['display_name'] ?? 'Free Tier';
$displayRating = $tier['display_rating'] ?? '';
$initial = strtoupper(substr($u['username'] ?? 'U', 0, 1));
$st = db()->prepare('SELECT COUNT(*) c FROM referrals WHERE referrer_id=?'); $st->execute([(int)$u['id']]); $refCount = (int)$st->fetch()['c'];
$hasPin = !empty($u['pin_hash']);
?><!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
<?php if (site_favicon()): ?><link rel="icon" href="<?php echo e(site_favicon()); ?>"><?php endif; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Profile - <?php echo e(site_name()); ?></title>
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

        <!-- Profile Header Card -->
        <div
            class="bg-gradient-to-br from-blue-600 via-blue-700 to-blue-900 rounded-2xl p-4 mb-4 text-white relative overflow-hidden">
            <!-- Decorative circles -->
            <div class="absolute top-0 right-0 w-24 h-24 bg-white/10 rounded-full -mr-8 -mt-8"></div>
            <div class="absolute bottom-0 left-0 w-16 h-16 bg-white/5 rounded-full -ml-6 -mb-6"></div>

            <div class="relative">
                <div class="flex items-center gap-4 mb-4">
                    <!-- Avatar -->
                    <div
                        class="w-16 h-16 bg-white/20 rounded-full flex items-center justify-center ring-4 ring-white/30">
                        <span class="text-2xl font-bold"><?php echo e($initial); ?></span>
                    </div>
                    <div class="flex-1">
                        <h1 class="text-xl font-bold"><?php echo e($u["username"]); ?></h1>
                        <p class="text-blue-200 text-sm"><?php echo e($u["email"]); ?></p>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="inline-flex items-center gap-1 bg-white/20 px-2 py-0.5 rounded-full text-xs">
                                <svg class="w-3 h-3 text-yellow-300" fill="currentColor" viewBox="0 0 20 20">
                                    <path
                                        d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                </svg>
                                <?php echo e($tierName); ?> </span>
                        </div>
                    </div>
                </div>

                <!-- Stats Row -->
                <div class="grid grid-cols-3 gap-2 text-center bg-white/10 backdrop-blur rounded-xl p-3">
                    <div>
                        <p class="text-lg font-bold">$<?php echo e(money($u["balance"])); ?></p>
                        <p class="text-blue-200 text-xs">Balance</p>
                    </div>
                    <div class="border-x border-white/20">
                        <p class="text-lg font-bold"><?php echo (int)$refCount; ?></p>
                        <p class="text-blue-200 text-xs">Referrals</p>
                    </div>
                    <div>
                        <p class="text-lg font-bold">$<?php echo e(money($u["total_earned"])); ?></p>
                        <p class="text-blue-200 text-xs">Earned</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Account Info -->
        <div class="mb-4">
            <h2 class="text-gray-700 font-semibold mb-2 text-sm">Account Information</h2>
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="flex items-center justify-between p-3 border-b border-gray-100">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14" />
                            </svg>
                        </div>
                        <span class="text-gray-600 text-sm">Referral Code</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="font-bold text-blue-600 text-sm"><?php echo e($u["referral_code"]); ?></span>
                        <button onclick="copyCode('<?php echo e($u["referral_code"]); ?>')" class="text-gray-400 hover:text-blue-600 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="flex items-center justify-between p-3 border-b border-gray-100">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                            <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <span class="text-gray-600 text-sm">Member Since</span>
                    </div>
                    <span class="font-semibold text-gray-800 text-sm"><?php echo e(date("M d, Y", strtotime($u["created_at"]))); ?></span>
                </div>
                <div class="flex items-center justify-between p-3">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center">
                            <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                        </div>
                        <span class="text-gray-600 text-sm">Account Status</span>
                    </div>
                    <span
                        class="bg-green-100 text-green-700 px-2 py-0.5 rounded-full text-xs font-semibold">Active</span>
                </div>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="mb-4">
            <h2 class="text-gray-700 font-semibold mb-2 text-sm">Quick Links</h2>
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <!-- Change Password -->
                <a href="/users/change-password.php"
                    class="flex items-center justify-between p-3 border-b border-gray-100 hover:bg-gray-50 transition">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                            </svg>
                        </div>
                        <span class="font-medium text-gray-700 text-sm">Change Password</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </div>
                </a>

                <!-- Withdrawal PIN -->
                <a href="/users/set-pin.php"
                    class="flex items-center justify-between p-3 border-b border-gray-100 hover:bg-gray-50 transition">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                        </div>
                        <span class="font-medium text-gray-700 text-sm">Withdrawal PIN</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full"><?php echo $hasPin ? "Active" : "Not Set"; ?></span>
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </div>
                </a>

                <!-- My Referrals -->
                <a href="/users/referral.php"
                    class="flex items-center justify-between p-3 border-b border-gray-100 hover:bg-gray-50 transition">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                            <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                        </div>
                        <span class="font-medium text-gray-700 text-sm">My Referrals</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">0</span>
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </div>
                </a>

                <!-- Notifications -->
                <a href="/users/notifications.php"
                    class="flex items-center justify-between p-3 border-b border-gray-100 hover:bg-gray-50 transition">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 bg-orange-100 rounded-lg flex items-center justify-center">
                            <svg class="w-4 h-4 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                        </div>
                        <span class="font-medium text-gray-700 text-sm">Notifications</span>
                    </div>
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </a>

                <!-- Transaction History -->
                <a href="/users/transactions.php"
                    class="flex items-center justify-between p-3 border-b border-gray-100 hover:bg-gray-50 transition">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center">
                            <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                        </div>
                        <span class="font-medium text-gray-700 text-sm">Transaction History</span>
                    </div>
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </a>

                <!-- Support -->
                <a href="/users/support.php"
                    class="flex items-center justify-between p-3 border-b border-gray-100 hover:bg-gray-50 transition">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 bg-cyan-100 rounded-lg flex items-center justify-center">
                            <svg class="w-4 h-4 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                        </div>
                        <span class="font-medium text-gray-700 text-sm">Help & Support</span>
                    </div>
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </a>

                <!-- Top Earners -->
                <a href="/users/top-earners.php" class="flex items-center justify-between p-3  hover:bg-gray-50 transition">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 bg-yellow-100 rounded-lg flex items-center justify-center">
                            <svg class="w-4 h-4 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4
         M7.835 4.697a3.42 3.42 0 001.946-.806
         3.42 3.42 0 014.438 0
         3.42 3.42 0 001.946.806
         3.42 3.42 0 013.138 3.138
         3.42 3.42 0 00.806 1.946
         3.42 3.42 0 010 4.438
         3.42 3.42 0 00-.806 1.946
         3.42 3.42 0 01-3.138 3.138
         3.42 3.42 0 00-1.946.806
         3.42 3.42 0 01-4.438 0
         3.42 3.42 0 00-1.946-.806
         3.42 3.42 0 01-3.138-3.138
         3.42 3.42 0 00-.806-1.946
         3.42 3.42 0 010-4.438
         3.42 3.42 0 00.806-1.946
         3.42 3.42 0 013.138-3.138z" />

                            </svg>
                        </div>
                        <span class="font-medium text-gray-700 text-sm">Top Earners</span>
                    </div>
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </a>

            </div>
        </div>

        <!-- Logout Button -->
        <button onclick="openLogoutModal()"
            class="flex items-center justify-center gap-2 w-full bg-red-500 hover:bg-red-600 text-white py-3 rounded-xl font-semibold transition mb-4">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
            </svg>
            Logout
        </button>

        <!-- Logout Confirmation Modal -->
        <div id="logoutModal"
            class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
            <div class="bg-white rounded-2xl p-6 max-w-sm w-full shadow-2xl transform transition-all scale-95 opacity-0"
                id="logoutModalContent">
                <div class="text-center">
                    <!-- Warning Icon -->
                    <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-2">Confirm Logout</h3>
                    <p class="text-gray-600 mb-6">Are you sure you want to logout from your account?</p>

                    <div class="flex gap-3">
                        <button onclick="closeLogoutModal()"
                            class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 py-3 rounded-xl font-semibold transition">
                            Cancel
                        </button>
                        <a href="/logout.php"
                            class="flex-1 bg-red-500 hover:bg-red-600 text-white py-3 rounded-xl font-semibold transition text-center">
                            Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Copy Code Toast -->
        <div id="copyToast"
            class="fixed bottom-24 left-1/2 transform -translate-x-1/2 bg-gray-800 text-white px-4 py-2 rounded-lg text-sm opacity-0 transition-opacity pointer-events-none">
            Referral code copied!
        </div>

        <script>
            function copyCode(code) {
                navigator.clipboard.writeText(code).then(() => {
                    const toast = document.getElementById('copyToast');
                    toast.classList.remove('opacity-0');
                    toast.classList.add('opacity-100');
                    setTimeout(() => {
                        toast.classList.remove('opacity-100');
                        toast.classList.add('opacity-0');
                    }, 2000);
                });
            }

            // Logout modal functions
            function openLogoutModal() {
                const modal = document.getElementById('logoutModal');
                const content = document.getElementById('logoutModalContent');
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                setTimeout(() => {
                    content.classList.remove('scale-95', 'opacity-0');
                    content.classList.add('scale-100', 'opacity-100');
                }, 10);
            }

            function closeLogoutModal() {
                const modal = document.getElementById('logoutModal');
                const content = document.getElementById('logoutModalContent');
                content.classList.remove('scale-100', 'opacity-100');
                content.classList.add('scale-95', 'opacity-0');
                setTimeout(() => {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                }, 200);
            }

            // Close modal when clicking outside
            document.getElementById('logoutModal').addEventListener('click', function (e) {
                if (e.target === this) {
                    closeLogoutModal();
                }
            });
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
                class="bottom-nav-item flex flex-col items-center justify-center flex-1 h-full relative active">
                <svg class="w-5 h-5 mb-0.5" fill="currentColor" stroke="currentColor" viewBox="0 0 24 24">
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

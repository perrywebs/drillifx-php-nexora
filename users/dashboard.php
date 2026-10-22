<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
$u = require_login();
$pdo = db();
// Determine user's effective tier from the unified tiers table
$st = $pdo->prepare('SELECT * FROM tiers WHERE id = ? LIMIT 1');
$st->execute([(int)$u['tier_id']]);
$tier = $st->fetch() ?: ['daily_hashes' => 1, 'daily_spins' => 1, 'display_name' => 'Free Tier', 'display_rating' => '', 'daily_hash_allowance' => 1, 'daily_spin_allowance' => 1, 'referral_requirement' => 3, 'requirement_type' => 'fixed'];
$tierName = $u['tier_name'] ?? $tier['display_name'] ?? 'Free Tier';
$rating = $tier['display_rating'] ?? '';
$hashLimit = (int)($tier['daily_hash_allowance'] ?? 1);
$spinLimit = (int)($tier['daily_spin_allowance'] ?? 1);
$referralReq = (float)($tier['referral_requirement'] ?? 3);
$reqType = $tier['requirement_type'] ?? 'fixed';
$hashDone = today_count('hashes', (int)$u['id']);
$spinDone = today_count('spins', (int)$u['id']);
$st = $pdo->prepare('SELECT COUNT(*) c FROM referrals WHERE referrer_id=?');
$st->execute([(int)$u['id']]);
$refCount = (int)$st->fetch()['c'];
$st = $pdo->prepare('SELECT type, amount, created_at FROM transactions WHERE user_id=? ORDER BY id DESC LIMIT 3');
$st->execute([(int)$u['id']]);
$recent = $st->fetchAll();
// Upsell: show next upgrade options based on referral progress
$nextUpsell = [];
$currentTierId = (int)$u['tier_id'];
$st = $pdo->prepare('SELECT * FROM tiers WHERE id > ? AND active = 1 ORDER BY id ASC LIMIT 2');
$st->execute([$currentTierId]);
$nextUpsell = $st->fetchAll();
if (!$nextUpsell) {
    $st = $pdo->query('SELECT * FROM tiers WHERE id IN (5,6,7) ORDER BY id');
    $nextUpsell = $st->fetchAll();
}
$st = $pdo->prepare('SELECT type, amount, created_at FROM transactions WHERE user_id=? ORDER BY id DESC LIMIT 3');
$st->execute([(int)$u['id']]);
$recent = $st->fetchAll();
$refLink = referral_link($u['referral_code']);
?>﻿
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <?php if (site_favicon()): ?>
        <link rel="icon" href="<?php echo e(site_favicon()); ?>"><?php endif; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Dashboard - <?php echo e(site_name()); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Signika+Negative:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
                    <a href="/users/support.php" class="w-9 h-9 bg-gray-50 hover:bg-blue-50 rounded-xl flex items-center justify-center transition group">
                        <svg class="w-5 h-5 text-gray-500 group-hover:text-blue-600 support-icon-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </a>
                    <!-- Notifications with unread badge -->
                    <a href="/users/notifications.php" class="w-9 h-9 bg-gray-50 hover:bg-blue-50 rounded-xl flex items-center justify-center transition relative group">
                        <svg class="w-5 h-5 text-gray-500 group-hover:text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </header>


    <main class="px-4 py-4">

        <!-- Tier Expired Notification -->

        <!-- Improved header card structure with better layout and organization -->
        <div class="bg-gradient-to-br from-blue-600 via-blue-700 to-blue-900 rounded-2xl p-4 mb-4 text-white relative overflow-hidden">
            <!-- Decorative elements -->
            <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -mr-12 -mt-12"></div>
            <div class="absolute bottom-0 left-0 w-20 h-20 bg-white/5 rounded-full -ml-8 -mb-8"></div>
            <div class="absolute top-1/2 right-1/4 w-8 h-8 bg-white/5 rounded-full"></div>

            <div class="relative">
                <!-- Top Row: Welcome & Profile -->
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <p class="text-blue-200 text-xs font-medium">Welcome back,</p>
                        <h1 class="text-xl font-bold tracking-tight"><?php echo e($u["username"]); ?></h1>
                    </div>
                    <a href="/users/profile.php" class="w-10 h-10 bg-white/20 backdrop-blur rounded-full flex items-center justify-center hover:bg-white/30 transition-all hover:scale-105 ring-2 ring-white/10">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </a>
                </div>

                <!-- Balance Card -->
                <div class="bg-white/10 backdrop-blur-sm rounded-xl p-3 mb-3 border border-white/10">
                    <div class="flex items-center justify-between">
                        <!-- Balance Section -->
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-1">
                                <p class="text-blue-200 text-[10px] uppercase tracking-widest font-semibold">Available Balance</p>
                                <button onclick="toggleBalance()" class="text-blue-200 hover:text-white transition p-0.5 rounded hover:bg-white/10" id="toggleBalanceBtn" aria-label="Toggle balance visibility">
                                    <svg id="eyeIcon" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                    <svg id="eyeOffIcon" class="w-3.5 h-3.5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                    </svg>
                                </button>
                            </div>
                            <p class="text-2xl font-bold tracking-tight" id="balanceDisplay">$<?php echo e(money($u["balance"])); ?></p>
                            <p class="text-2xl font-bold tracking-tight hidden" id="balanceHidden">$****</p>
                        </div>

                        <!-- Tier Badge Section -->
                        <div class="text-right flex flex-col items-end gap-1">
                            <span class="inline-flex items-center gap-1.5 bg-gradient-to-r from-yellow-400/20 to-yellow-500/20 border border-yellow-400/30 px-2.5 py-1 rounded-full text-xs font-semibold">
                                <svg class="w-3 h-3 text-yellow-300" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                </svg>
                                <?php echo e($tierName); ?> </span>
                        </div>
                    </div>
                </div>

                <!-- Quick Stats Row -->
                <div class="grid grid-cols-3 gap-1">
                    <div class="bg-white/5 rounded-lg py-2 px-1 text-center">
                        <p class="text-base font-bold"><?php echo (int)$hashDone; ?><span class="text-blue-300 font-normal">/<?php echo (int)$hashLimit; ?></span></p>
                        <p class="text-blue-300 text-[10px] uppercase tracking-wide">Hashes</p>
                    </div>
                    <div class="bg-white/5 rounded-lg py-2 px-1 text-center border-x border-white/10">
                        <p class="text-base font-bold"><?php echo (int)$refCount; ?></p>
                        <p class="text-blue-300 text-[10px] uppercase tracking-wide">Referrals</p>
                    </div>
                    <div class="bg-white/5 rounded-lg py-2 px-1 text-center">
                        <p class="text-base font-bold"><?php echo (int)$spinDone; ?><span class="text-blue-300 font-normal">/<?php echo (int)$spinLimit; ?></span></p>
                        <p class="text-blue-300 text-[10px] uppercase tracking-wide">Spins</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Balance toggle script with localStorage -->
        <script>
            function toggleBalance() {
                const balanceDisplay = document.getElementById('balanceDisplay');
                const balanceHidden = document.getElementById('balanceHidden');
                const eyeIcon = document.getElementById('eyeIcon');
                const eyeOffIcon = document.getElementById('eyeOffIcon');

                const isHidden = localStorage.getItem('hideBalance') === 'true';

                if (isHidden) {
                    // Show balance
                    balanceDisplay.classList.remove('hidden');
                    balanceHidden.classList.add('hidden');
                    eyeIcon.classList.remove('hidden');
                    eyeOffIcon.classList.add('hidden');
                    localStorage.setItem('hideBalance', 'false');
                } else {
                    // Hide balance
                    balanceDisplay.classList.add('hidden');
                    balanceHidden.classList.remove('hidden');
                    eyeIcon.classList.add('hidden');
                    eyeOffIcon.classList.remove('hidden');
                    localStorage.setItem('hideBalance', 'true');
                }
            }

            // Check localStorage on page load
            document.addEventListener('DOMContentLoaded', function() {
                const isHidden = localStorage.getItem('hideBalance') === 'true';
                if (isHidden) {
                    document.getElementById('balanceDisplay').classList.add('hidden');
                    document.getElementById('balanceHidden').classList.remove('hidden');
                    document.getElementById('eyeIcon').classList.add('hidden');
                    document.getElementById('eyeOffIcon').classList.remove('hidden');
                }
            });
        </script>

        <!-- Quick Actions Grid - More compact -->
        <div class="mb-4">
            <h2 class="text-gray-700 font-semibold mb-2 text-sm">Quick Actions</h2>
            <div class="grid grid-cols-4 gap-2">
                <!-- Updated Hash icon to blockchain/mining style -->
                <a href="/users/hash.php" class="bg-white rounded-xl p-3 text-center shadow-sm hover:shadow-md transition group">
                    <div class="w-10 h-10 bg-gradient-to-br from-blue-100 to-blue-200 rounded-xl flex items-center justify-center mx-auto mb-1 group-hover:from-blue-500 group-hover:to-blue-700 transition-all">
                        <svg class="w-5 h-5 text-blue-600 group-hover:text-white transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                        </svg>
                    </div>
                    <p class="text-xs font-medium text-gray-700">Hash</p>
                </a>
                <!-- Updated Spin icon to wheel/fortune style -->
                <a href="/users/spin.php" class="bg-white rounded-xl p-3 text-center shadow-sm hover:shadow-md transition group">
                    <div class="w-10 h-10 bg-gradient-to-br from-purple-100 to-purple-200 rounded-xl flex items-center justify-center mx-auto mb-1 group-hover:from-purple-500 group-hover:to-purple-700 transition-all">
                        <svg class="w-5 h-5 text-purple-600 group-hover:text-white transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                    </div>
                    <p class="text-xs font-medium text-gray-700">Spin</p>
                </a>
                <!-- Updated Upgrade icon to rocket/level-up style -->
                <a href="/users/upgrade.php" class="bg-white rounded-xl p-3 text-center shadow-sm hover:shadow-md transition group">
                    <div class="w-10 h-10 bg-gradient-to-br from-green-100 to-green-200 rounded-xl flex items-center justify-center mx-auto mb-1 group-hover:from-green-500 group-hover:to-green-700 transition-all">
                        <svg class="w-5 h-5 text-green-600 group-hover:text-white transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                        </svg>
                    </div>
                    <p class="text-xs font-medium text-gray-700">Upgrade</p>
                </a>
                <!-- Updated Withdraw icon to wallet/cash-out style -->
                <a href="/users/withdraw.php" class="bg-white rounded-xl p-3 text-center shadow-sm hover:shadow-md transition group">
                    <div class="w-10 h-10 bg-gradient-to-br from-orange-100 to-orange-200 rounded-xl flex items-center justify-center mx-auto mb-1 group-hover:from-orange-500 group-hover:to-orange-700 transition-all">
                        <svg class="w-5 h-5 text-orange-600 group-hover:text-white transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                    <p class="text-xs font-medium text-gray-700">Withdraw</p>
                </a>
            </div>
        </div>

        <!-- Earnings Overview - Compact -->
        <div class="mb-4">
            <h2 class="text-gray-700 font-semibold mb-2 text-sm">Earnings Overview</h2>
            <div class="grid grid-cols-2 gap-2">
                <div class="bg-white rounded-xl p-3 shadow-sm">
                    <div class="flex items-center gap-2">
                        <!-- Updated Hash Earnings icon -->
                        <div class="w-8 h-8 bg-gradient-to-br from-blue-100 to-blue-200 rounded-lg flex items-center justify-center">
                            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Hash Earnings</p>
                            <p class="text-base font-bold text-gray-800">$<?php echo e(money($u["hash_earnings"])); ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl p-3 shadow-sm">
                    <div class="flex items-center gap-2">
                        <!-- Updated Referral Earnings icon -->
                        <div class="w-8 h-8 bg-gradient-to-br from-green-100 to-green-200 rounded-lg flex items-center justify-center">
                            <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Referral Earnings</p>
                            <p class="text-base font-bold text-gray-800">$<?php echo e(money($u["referral_earnings"])); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Transactions - Limited to 3 -->
        <div class="mb-4">
            <div class="flex items-center justify-between mb-2">
                <h2 class="text-gray-700 font-semibold text-sm">Recent Activity</h2>
                <a href="/users/transactions.php" class="text-blue-600 text-xs font-medium">View All</a>
            </div>
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <?php if ($recent): foreach ($recent as $t): ?>
                        <div class="flex items-center gap-3 p-3 border-b border-gray-100">
                            <div class="w-8 h-8 bg-gradient-to-br from-blue-100 to-blue-200 rounded-lg flex items-center justify-center">
                                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-gray-800 text-sm"><?php echo e($t["type"]); ?></p>
                                <p class="text-xs text-gray-500"><?php echo e(date("M j, g:i A", strtotime($t["created_at"]))); ?></p>
                            </div>
                            <p class="font-semibold text-sm <?php echo ((float)$t["amount"] < 0) ? "text-red-600" : "text-green-600"; ?>"><?php echo ((float)$t["amount"] < 0 ? "-" : "+") . "$" . e(money(abs((float)$t["amount"]))); ?></p>
                        </div>
                    <?php endforeach;
                else: ?>
                    <p class="p-4 text-center text-xs text-gray-500">No activity yet. Try Hash or Spin to earn.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Upgrade Tier Card -->
        <div class="mb-4">
            <div class="flex items-center justify-between mb-2">
                <h2 class="text-gray-700 font-semibold text-sm">Upgrade Your Tier</h2>
                <!-- Changed link from deposit.php to upgrade.php -->
                <a href="/users/upgrade.php" class="text-blue-600 text-xs font-medium">View All</a>
            </div>
            <div class="space-y-2">
                <?php if (!empty($uo)): ?>
                    <!-- Show progress toward next upgrade option -->
                    <div class="bg-white rounded-xl p-4 shadow-sm">
                        <h3 class="font-semibold text-gray-800 text-sm">Current Level</h3>
                        <div class="flex items-center gap-2 mb-2">
                            <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-blue-700 rounded-lg flex items-center justify-center text-white font-bold text-xs">
                                <?php echo $uo['display_rating']; ?>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-800 text-sm"><?php echo $uo['display_name']; ?></p>
                                <p class="text-xs text-gray-500"><?php echo (int)$hashDone; ?>/<?php echo $hashLimit; ?> hashes today</p>
                            </div>
                        </div>
                        <div class="progress progress-sm mb-3">
                            <div class="progress-bar" role="progressbar" aria-valuenow="<?php echo (int)($refCount / $referralReq * 100); ?>" aria-valuemin="0" aria-valuemax="100" style="width: <?php echo min(100, max(0, $refCount > 0 ? $refCount / $referralReq * 100 : 0)); ?>%"></div>
                        </div>
                        <p class="text-xs text-gray-500">Referrals: <strong><?php echo (int)$refCount; ?></strong> of <?php echo (int)$referralReq; ?> required<?php echo $refCount >= $referralReq ? ' - Eligible for upgrade!' : ''; ?></p>
                        <?php if ($refCount < $referralReq): ?>
                            <p class="text-xs text-gray-500"><?php echo $referralReq - $refCount; ?> more referral<?php echo $referralReq - $refCount > 1 ? 's' : ''; ?> needed</p>
                        <?php endif; ?>
                        <a href="/users/upgrade.php" class="bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white px-3 py-1.5 rounded-lg text-xs font-semibold transition">Upgrade Now</a>
                    </div>
                <?php endif; ?>
                <?php if (!empty($nextUpsell)): ?>
                    <div class="grid grid-cols-2 gap-2">
                        <?php foreach ($nextUpsell as $opt): ?>
                            <div class="bg-white rounded-xl p-3 shadow-sm">
                                <div class="flex items-center gap-2 mb-2">
                                    <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-blue-700 rounded-lg flex items-center justify-center text-white font-bold text-xs">
                                        <?php echo $opt['display_rating']; ?>
                                    </div>
                                    <div>
                                        <p class="font-semibold text-gray-800 text-sm"><?php echo $opt['display_name']; ?></p>
                                        <p class="text-xs text-gray-500"><?php echo (int)$opt['daily_hash_allowance']; ?> hashes | <?php echo (int)$opt['daily_spin_allowance']; ?> spins</p>
                                    </div>
                                </div>
                                <p class="text-xs text-gray-500">Refer: <?php echo (int)$opt['referral_requirement']; ?> referrals<?php echo $opt['requirement_type'] === 'threshold' ? ' (more than)' : ''; ?></p>
                                <?php if ($refCount < $opt['referral_requirement']): ?>
                                    <a href="/users/upgrade.php?tier=<?php echo $opt['name']; ?>" class="bg-blue-600 hover:bg-blue-700 text-white px-2 py-1 rounded-xl text-xs font-semibold transition">Upgrade</a>
                                <?php else: ?>
                                    <span class="text-green-600 text-xs font-semibold">Eligible</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($upsell as $t): ?>
                        <div class="bg-white rounded-xl p-3 shadow-sm">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-blue-700 rounded-lg flex items-center justify-center text-white font-bold text-sm">
                                    T<?php echo (int)$t["id"]; ?> </div>
                                <div class="flex-1 min-w-0">
                                    <p class="font-semibold text-gray-800 text-sm"><?php echo e($t["name"]); ?></p>
                                    <p class="text-xs text-gray-500 truncate"><?php echo (int)$t["daily_hashes"]; ?> hashes | $<?php echo e(money4($t["per_hash"])); ?>/hash | <?php echo (int)$t["duration_days"]; ?>d</p>
                                </div>
                                <a href="/users/deposit.php?tier=<?php echo (int)$t["id"]; ?>" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-lg text-xs font-semibold transition">
                                    $<?php echo e(money($t["price"])); ?> </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Referral Card - Compact -->
        <div class="bg-gradient-to-br from-blue-600 to-blue-800 rounded-xl p-4 mb-4 text-white relative overflow-hidden">
            <div class="absolute top-0 right-0 w-16 h-16 bg-white/10 rounded-full -mr-4 -mt-4"></div>
            <div class="relative">
                <div class="flex items-center gap-2 mb-2">
                    <div class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center">
                        <i class="fas fa-gift text-sm"></i>
                    </div>
                    <div>
                        <p class="font-semibold text-sm">Refer & Earn</p>
                        <p class="text-xs text-blue-200">$<?php echo e(money(cfg_referral_reward())); ?> per upgrade</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <input type="text" readonly value="<?php echo e($refLink); ?>"
                        class="flex-1 bg-white/20 border border-white/30 rounded-lg px-2 py-1.5 text-xs placeholder-white/50 text-white" id="refLink">
                    <button onclick="copyRefLink()" class="bg-white text-blue-600 hover:bg-blue-50 px-3 py-1.5 rounded-lg font-semibold transition text-sm">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
            </div>
        </div>

        <script>
            function copyRefLink() {
                const input = document.getElementById('refLink');
                input.select();
                document.execCommand('copy');

                const toast = document.createElement('div');
                toast.className = 'fixed top-4 left-1/2 -translate-x-1/2 bg-gray-800 text-white px-4 py-2 rounded-xl text-sm z-50';
                toast.textContent = 'Referral link copied!';
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 2000);
            }
        </script>

    </main>

    <!-- Redesigned bottom navigation with modern SVG icons -->
    <nav class="fixed bottom-0 left-0 right-0 bg-white/95 backdrop-blur-lg border-t border-gray-100 shadow-2xl z-50 safe-area-bottom">
        <div class="flex justify-around items-center h-16 max-w-lg mx-auto">
            <!-- Home -->
            <!-- Added pathPrefix to all navigation links -->
            <a href="/users/dashboard.php" class="bottom-nav-item flex flex-col items-center justify-center flex-1 h-full relative active">
                <svg class="w-5 h-5 mb-0.5" fill="currentColor" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                <span class="text-xs font-medium">Home</span>
            </a>
            <!-- Hash -->
            <a href="/users/hash.php" class="bottom-nav-item flex flex-col items-center justify-center flex-1 h-full relative text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                </svg>
                <span class="text-xs font-medium">Hash</span>
            </a>
            <!-- Spin -->
            <a href="/users/spin.php" class="bottom-nav-item flex flex-col items-center justify-center flex-1 h-full relative text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                <span class="text-xs font-medium">Spin</span>
            </a>
            <!-- Withdraw -->
            <a href="/users/withdraw.php" class="bottom-nav-item flex flex-col items-center justify-center flex-1 h-full relative text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                <span class="text-xs font-medium">Withdraw</span>
            </a>
            <!-- Profile -->
            <a href="/users/profile.php" class="bottom-nav-item flex flex-col items-center justify-center flex-1 h-full relative text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
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
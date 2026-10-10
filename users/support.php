<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
$u = require_login();
$support = app_config('support');
?><!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
<?php if (site_favicon()): ?><link rel="icon" href="<?php echo e(site_favicon()); ?>"><?php endif; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Support - <?php echo e(site_name()); ?></title>
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

        <!-- Support Header -->
        <div class="text-center mb-6">
            <div
                class="w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-700 rounded-2xl flex items-center justify-center mx-auto mb-3">
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
            </div>
            <h1 class="text-xl font-bold text-gray-800">How can we help?</h1>
            <p class="text-gray-500 text-sm">Get support through any of these channels</p>
        </div>

        <!-- Quick Support Options -->
        <div class="space-y-3 mb-6">
            <!-- Telegram Channel -->
            <a href="<?php echo e($support["telegram_channel"]); ?>" target="_blank"
                class="bg-white rounded-2xl p-4 shadow-sm hover:shadow-md transition flex items-center gap-4 group">
                <div
                    class="w-12 h-12 bg-gradient-to-br from-sky-400 to-sky-600 rounded-xl flex items-center justify-center group-hover:scale-105 transition flex-shrink-0">
                    <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 24 24">
                        <path
                            d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <h3 class="font-semibold text-gray-800">Join Our Channel</h3>
                    <p class="text-gray-500 text-sm">Get latest updates, news & announcements</p>
                </div>
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </a>

            <!-- Telegram Support -->
            <a href="<?php echo e($support["telegram_support"]); ?>" target="_blank"
                class="bg-white rounded-2xl p-4 shadow-sm hover:shadow-md transition flex items-center gap-4 group">
                <div
                    class="w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-700 rounded-xl flex items-center justify-center group-hover:scale-105 transition flex-shrink-0">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <h3 class="font-semibold text-gray-800">Live Chat Support</h3>
                    <p class="text-gray-500 text-sm">Chat directly with our support team</p>
                </div>
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </a>

            <!-- Email Support -->
            <a href="mailto:<?php echo e($support["email"]); ?>"
                class="bg-white rounded-2xl p-4 shadow-sm hover:shadow-md transition flex items-center gap-4 group">
                <div
                    class="w-12 h-12 bg-gradient-to-br from-green-500 to-green-700 rounded-xl flex items-center justify-center group-hover:scale-105 transition flex-shrink-0">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                </div>
                <div class="flex-1">
                    <h3 class="font-semibold text-gray-800">Email Us</h3>
                    <p class="text-gray-500 text-sm">support@nexoraspin.live</p>
                </div>
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </a>
        </div>

        <!-- FAQs Section -->
        <div class="mb-4">
            <h2 class="text-gray-700 font-semibold mb-3 flex items-center gap-2">
                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Frequently Asked Questions
            </h2>

            <div class="space-y-2">
                <!-- FAQ Item 1 -->
                <div class="bg-white rounded-xl overflow-hidden shadow-sm">
                    <button onclick="toggleFaq(1)" class="w-full flex items-center justify-between p-4 text-left">
                        <span class="font-medium text-gray-800 text-sm">How do I start earning USDC?</span>
                        <svg id="faqIcon1" class="w-5 h-5 text-gray-400 transition-transform" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div id="faqContent1" class="hidden px-4 pb-4">
                        <p class="text-gray-600 text-sm">Once you create an account, you can start hashing immediately
                            on the Free Tier and earn 1 USDC per day. To earn more, upgrade to a higher tier which
                            allows more daily hashes and higher rewards per hash.</p>
                    </div>
                </div>

                <!-- FAQ Item 2 -->
                <div class="bg-white rounded-xl overflow-hidden shadow-sm">
                    <button onclick="toggleFaq(2)" class="w-full flex items-center justify-between p-4 text-left">
                        <span class="font-medium text-gray-800 text-sm">How do I upgrade my account tier?</span>
                        <svg id="faqIcon2" class="w-5 h-5 text-gray-400 transition-transform" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div id="faqContent2" class="hidden px-4 pb-4">
                        <p class="text-gray-600 text-sm">Go to the Upgrade page, select your desired tier, choose a
                            payment method, and upload proof of payment. Once admin approves your payment, your tier
                            will be upgraded automatically.</p>
                    </div>
                </div>

                <!-- FAQ Item 3 -->
                <div class="bg-white rounded-xl overflow-hidden shadow-sm">
                    <button onclick="toggleFaq(3)" class="w-full flex items-center justify-between p-4 text-left">
                        <span class="font-medium text-gray-800 text-sm">What is the minimum withdrawal amount?</span>
                        <svg id="faqIcon3" class="w-5 h-5 text-gray-400 transition-transform" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div id="faqContent3" class="hidden px-4 pb-4">
                        <p class="text-gray-600 text-sm">The minimum withdrawal amount is $100. Once you reach this
                            amount, you can request a withdrawal through Bank Transfer, Mobile Money, or Crypto.</p>
                    </div>
                </div>

                <!-- FAQ Item 4 -->
                <div class="bg-white rounded-xl overflow-hidden shadow-sm">
                    <button onclick="toggleFaq(4)" class="w-full flex items-center justify-between p-4 text-left">
                        <span class="font-medium text-gray-800 text-sm">How does the referral program work?</span>
                        <svg id="faqIcon4" class="w-5 h-5 text-gray-400 transition-transform" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div id="faqContent4" class="hidden px-4 pb-4">
                        <p class="text-gray-600 text-sm">Share your referral link with friends. When they sign up and
                            upgrade to a paid tier, you'll receive $10 bonus credited to your account.</p>
                    </div>
                </div>

                <!-- FAQ Item 5 -->
                <div class="bg-white rounded-xl overflow-hidden shadow-sm">
                    <button onclick="toggleFaq(5)" class="w-full flex items-center justify-between p-4 text-left">
                        <span class="font-medium text-gray-800 text-sm">How long does withdrawal take?</span>
                        <svg id="faqIcon5" class="w-5 h-5 text-gray-400 transition-transform" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div id="faqContent5" class="hidden px-4 pb-4">
                        <p class="text-gray-600 text-sm">Withdrawals are typically processed within minutes. Bank
                            transfers may take additional time depending on your bank. Crypto withdrawals are usually
                            faster.</p>
                    </div>
                </div>

                <!-- FAQ Item 6 -->
                <div class="bg-white rounded-xl overflow-hidden shadow-sm">
                    <button onclick="toggleFaq(6)" class="w-full flex items-center justify-between p-4 text-left">
                        <span class="font-medium text-gray-800 text-sm">What happens when my tier expires?</span>
                        <svg id="faqIcon6" class="w-5 h-5 text-gray-400 transition-transform" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div id="faqContent6" class="hidden px-4 pb-4">
                        <p class="text-gray-600 text-sm">When your tier expires, you'll be automatically downgraded to
                            the Free Tier. You can still hash and earn, but at the free tier rate. Your balance remains
                            intact, and you can upgrade again anytime.</p>
                    </div>
                </div>
            </div>
        </div>

        <script>
            function toggleFaq(id) {
                const content = document.getElementById('faqContent' + id);
                const icon = document.getElementById('faqIcon' + id);

                // Close all other FAQs
                for (let i = 1; i <= 6; i++) {
                    if (i !== id) {
                        document.getElementById('faqContent' + i).classList.add('hidden');
                        document.getElementById('faqIcon' + i).classList.remove('rotate-180');
                    }
                }

                // Toggle current FAQ
                content.classList.toggle('hidden');
                icon.classList.toggle('rotate-180');
            }
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

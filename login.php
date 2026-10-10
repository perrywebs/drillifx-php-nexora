<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
if (current_user()) redirect('/users/dashboard.php');
$err = $_SESSION['form_error'] ?? '';
unset($_SESSION['form_error']);
$old = $_SESSION['old_login'] ?? '';
unset($_SESSION['old_login']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) {
        $_SESSION['form_error'] = 'Invalid session. Try again.';
        redirect('/login.php');
    }
    $login = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($login === '' || $password === '') {
        $_SESSION['form_error'] = 'Enter your email/username and password.';
        $_SESSION['old_login'] = $login;
        redirect('/login.php');
    }
    $st = db()->prepare('SELECT * FROM users WHERE email = ? OR username = ? LIMIT 1');
    $st->execute([strtolower($login), $login]);
    $u = $st->fetch();
    // rate limit: max 8 attempts / 10 min per IP (file-based simple).
    // Skipped gracefully when the temp dir is not writable (some shared hosts).
    $ip = client_ip();
    $rlDir = sys_get_temp_dir();
    $rl = (is_string($rlDir) && is_dir($rlDir) && is_writable($rlDir))
        ? rtrim($rlDir, '/\\') . '/drill_login_' . md5($ip) : null;
    $att = ($rl !== null && is_file($rl)) ? (int)@file_get_contents($rl) : 0;
    if ($rl !== null && $att >= 8) {
        $_SESSION['form_error'] = 'Too many attempts. Try again later.';
        redirect('/login.php');
    }
    if (!$u || $u['status'] !== 'active' || !password_verify($password, $u['password_hash'])) {
        if ($rl !== null) @file_put_contents($rl, (string)($att + 1));
        $_SESSION['form_error'] = 'Incorrect email/username or password.';
        $_SESSION['old_login'] = $login;
        redirect('/login.php');
    }
    if ($rl !== null) @unlink($rl);
    require_once __DIR__ . '/includes/mail.php';
    if (cfg_flag('email_verification', false) && empty($u['email_verified_at'])) {
        redirect('/verify.php?email=' . urlencode($u['email']));
    }
    login_user((int)$u['id']);
    log_activity((int)$u['id'], 'login', 'User logged in');
    $next = $_GET['next'] ?? $_POST['next'] ?? '';
    $base = base_path();
    $check = is_string($next) ? $next : '';
    if ($base !== '' && strpos($check, $base . '/') === 0) $check = substr($check, strlen($base));
    if (str_starts_with($check, '/users/')) redirect($next);
    redirect('/users/dashboard.php');
}
?>﻿
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php seo_head(['title' => 'Login - ' . site_name(), 'description' => 'Sign in to your ' . site_name() . ' account to hash, spin and manage your USDC earnings.', 'path' => '/login.php']); ?>
    <script src="https://cdn.tailwindcss.com/3.4.17"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Signika+Negative:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Signika Negative', sans-serif;
        }

        @keyframes bounce-slow {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(8px);
            }
        }

        .scroll-indicator {
            animation: bounce-slow 2s ease-in-out infinite;
        }

        @keyframes fade-in-up {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fade-in-up {
            animation: fade-in-up 0.6s ease-out forwards;
        }

        .feature-card {
            animation: fade-in-up 0.6s ease-out forwards;
            opacity: 0;
        }

        .feature-card:nth-child(1) {
            animation-delay: 0.1s;
        }

        .feature-card:nth-child(2) {
            animation-delay: 0.2s;
        }

        .feature-card:nth-child(3) {
            animation-delay: 0.3s;
        }

        .feature-card:nth-child(4) {
            animation-delay: 0.4s;
        }

        .feature-card:nth-child(5) {
            animation-delay: 0.5s;
        }

        .feature-card:nth-child(6) {
            animation-delay: 0.6s;
        }

        /* Added shake animation for validation errors */
        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            25% {
                transform: translateX(-5px);
            }

            75% {
                transform: translateX(5px);
            }
        }

        .shake {
            animation: shake 0.3s ease-in-out;
        }
    </style>
</head>

<body class="bg-gradient-to-br from-blue-50 via-white to-blue-100 min-h-screen">

    <!-- Hero Section with Login -->
    <section class="min-h-screen flex flex-col items-center justify-center p-4 relative">
        <div class="w-full max-w-md">
            <!-- Logo and Brand -->
            <div class="text-center mb-6">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-blue-600 to-blue-800 rounded-2xl shadow-lg mb-3">
                    <img src="<?php echo e(site_logo()); ?>" alt="USDC" class="w-10 h-10">
                </div>
                <h1 class="text-2xl font-bold text-gray-800"><?php echo e(site_name()); ?></h1>
                <p class="text-gray-500 text-sm">Welcome back! Sign in to continue</p>
            </div>

            <!-- Login Card -->
            <div class="bg-white rounded-2xl shadow-xl p-6 relative overflow-hidden">
                <!-- Decorative elements -->
                <div class="absolute top-0 right-0 w-24 h-24 bg-blue-50 rounded-full -mr-12 -mt-12"></div>
                <div class="absolute bottom-0 left-0 w-16 h-16 bg-blue-50 rounded-full -ml-8 -mb-8"></div>

                <div class="relative">


                    <!-- Added novalidate to disable browser validation -->
                    <form method="POST" class="space-y-4" id="loginForm" novalidate=""><?php echo csrf_field(); ?><input type="hidden" name="login" value="1"><?php if (!empty($err)): ?><div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl p-3"><?php echo e($err); ?></div><?php endif; ?>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Email or Username</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <!-- Icon color changes on error -->
                                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
                                </div>
                                <!-- Custom styled error state -->
                                <input type="text" name="email" id="email" class="w-full pl-10 pr-4 py-3 bg-gray-50 border rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:bg-white transition text-sm border-gray-200" placeholder="Enter your email or username" value="<?php echo e($old ?? ''); ?>">
                            </div>
                            <!-- Custom error message display -->
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Password</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                    </svg>
                                </div>
                                <input type="password" name="password" id="password" class="w-full pl-10 pr-12 py-3 bg-gray-50 border rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:bg-white transition text-sm border-gray-200" placeholder="Enter your password">
                                <button type="button" onclick="togglePassword()" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                    <svg id="eyeIcon" class="w-5 h-5 text-gray-400 hover:text-gray-600" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                    <svg id="eyeOffIcon" class="w-5 h-5 text-gray-400 hover:text-gray-600 hidden" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="flex items-center justify-end">
                            <a href="forgot-password.php" class="text-sm text-blue-600 hover:text-blue-700 font-medium">Forgot password?</a>
                        </div>

                        <button type="submit" class="w-full bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white py-3 px-6 rounded-xl font-semibold transition shadow-lg shadow-blue-500/30 flex items-center justify-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                            </svg>
                            Sign In
                        </button>
                    </form>

                    <div class="mt-6 text-center">
                        <p class="text-gray-600 text-sm">
                            Don't have an account?
                            <a href="register.php" class="text-blue-600 font-semibold hover:text-blue-700">Create Account</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>

    </section>

    <!-- Added features section -->
    <section id="features" class="py-16 px-4 bg-white">
        <div class="max-w-6xl mx-auto">
            <div class="absolute bottom-8 left-0 right-0 flex justify-center scroll-indicator cursor-pointer" onclick="document.getElementById('features').scrollIntoView({behavior: 'smooth'})">
                <div class="flex flex-col items-center gap-1 text-gray-400">
                    <span class="text-xs font-medium">Explore Features</span>
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
                    </svg>
                </div>
            </div>
            <!-- Section Header -->
            <div class="text-center mb-12">
                <span class="inline-block px-4 py-1.5 bg-blue-100 text-blue-700 text-xs font-semibold rounded-full mb-3">WHY CHOOSE US</span>
                <h2 class="text-3xl font-bold text-gray-800 mb-3">Platform Features</h2>
                <p class="text-gray-500 max-w-lg mx-auto">Discover why thousands of users trust Drillifyx for their crypto earnings journey</p>
            </div>

            <!-- Features Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Feature 1: Hash & Earn -->
                <div class="feature-card bg-gradient-to-br from-blue-50 to-white p-6 rounded-2xl border border-blue-100 hover:shadow-lg hover:shadow-blue-100/50 transition-all duration-300 group">
                    <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-blue-700 rounded-2xl flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                        <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-gray-800 mb-2">Hash & Earn</h3>
                    <p class="text-gray-500 text-sm leading-relaxed">Simple one-click hashing to earn USDC. The more you hash, the more you earn daily.</p>
                </div>

                <!-- Feature 2: Spin to Win -->
                <div class="feature-card bg-gradient-to-br from-purple-50 to-white p-6 rounded-2xl border border-purple-100 hover:shadow-lg hover:shadow-purple-100/50 transition-all duration-300 group">
                    <div class="w-14 h-14 bg-gradient-to-br from-purple-500 to-purple-700 rounded-2xl flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                        <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-gray-800 mb-2">Spin to Win</h3>
                    <p class="text-gray-500 text-sm leading-relaxed">Try your luck with our daily spin wheel. Win bonus USDC rewards with every spin!</p>
                </div>

                <!-- Feature 3: Instant Withdrawals -->
                <div class="feature-card bg-gradient-to-br from-green-50 to-white p-6 rounded-2xl border border-green-100 hover:shadow-lg hover:shadow-green-100/50 transition-all duration-300 group">
                    <div class="w-14 h-14 bg-gradient-to-br from-green-500 to-green-700 rounded-2xl flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                        <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-gray-800 mb-2">Fast Withdrawals</h3>
                    <p class="text-gray-500 text-sm leading-relaxed">Withdraw your earnings via bank transfer, mobile money, or crypto. Quick processing guaranteed.</p>
                </div>

                <!-- Feature 4: Multiple Tiers -->
                <div class="feature-card bg-gradient-to-br from-amber-50 to-white p-6 rounded-2xl border border-amber-100 hover:shadow-lg hover:shadow-amber-100/50 transition-all duration-300 group">
                    <div class="w-14 h-14 bg-gradient-to-br from-amber-500 to-amber-700 rounded-2xl flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                        <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-gray-800 mb-2">Upgrade & Earn More</h3>
                    <p class="text-gray-500 text-sm leading-relaxed">Unlock higher earning tiers with more daily hashes, bigger rewards, and bonus spins.</p>
                </div>

                <!-- Feature 5: Referral Program -->
                <div class="feature-card bg-gradient-to-br from-pink-50 to-white p-6 rounded-2xl border border-pink-100 hover:shadow-lg hover:shadow-pink-100/50 transition-all duration-300 group">
                    <div class="w-14 h-14 bg-gradient-to-br from-pink-500 to-pink-700 rounded-2xl flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                        <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-gray-800 mb-2">Referral Rewards</h3>
                    <p class="text-gray-500 text-sm leading-relaxed">Invite friends and earn bonus USDC when they upgrade their account tier.</p>
                </div>

                <!-- Feature 6: Secure Platform -->
                <div class="feature-card bg-gradient-to-br from-cyan-50 to-white p-6 rounded-2xl border border-cyan-100 hover:shadow-lg hover:shadow-cyan-100/50 transition-all duration-300 group">
                    <div class="w-14 h-14 bg-gradient-to-br from-cyan-500 to-cyan-700 rounded-2xl flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                        <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-gray-800 mb-2">Secure & Protected</h3>
                    <p class="text-gray-500 text-sm leading-relaxed">Your account is protected with withdrawal PIN, activity logs, and secure authentication.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer text -->
    <p class="text-center text-gray-400 text-xs py-6 bg-white">&copy; 2026 Drillifyx. All rights reserved.</p>

    <script>
        function togglePassword() {
            const password = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');
            const eyeOffIcon = document.getElementById('eyeOffIcon');

            if (password.type === 'password') {
                password.type = 'text';
                eyeIcon.classList.add('hidden');
                eyeOffIcon.classList.remove('hidden');
            } else {
                password.type = 'password';
                eyeIcon.classList.remove('hidden');
                eyeOffIcon.classList.add('hidden');
            }
        }

        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const email = document.getElementById('email');
            const password = document.getElementById('password');
            let hasError = false;

            // Clear previous errors
            document.querySelectorAll('.client-error').forEach(el => el.remove());
            document.querySelectorAll('.shake').forEach(el => el.classList.remove('shake'));

            // Validate email
            if (!email.value.trim()) {
                showError(email, 'Email or username is required');
                hasError = true;
            }

            // Validate password
            if (!password.value) {
                showError(password, 'Password is required');
                hasError = true;
            }

            if (hasError) {
                e.preventDefault();
            }
        });

        function showError(input, message) {
            input.classList.add('border-red-300', 'bg-red-50', 'shake');
            input.classList.remove('border-gray-200', 'bg-gray-50');

            // Update icon color
            const icon = input.parentElement.querySelector('svg');
            if (icon) {
                icon.classList.remove('text-gray-400');
                icon.classList.add('text-red-400');
            }

            // Add error message
            const errorEl = document.createElement('p');
            errorEl.className = 'mt-1.5 text-xs text-red-600 flex items-center gap-1 client-error';
            errorEl.innerHTML = `<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>${message}`;
            input.parentElement.parentElement.appendChild(errorEl);
        }

        // Clear error on input
        document.querySelectorAll('#loginForm input').forEach(input => {
            input.addEventListener('input', function() {
                this.classList.remove('border-red-300', 'bg-red-50', 'shake');
                this.classList.add('border-gray-200', 'bg-gray-50');

                const icon = this.parentElement.querySelector('svg');
                if (icon) {
                    icon.classList.remove('text-red-400');
                    icon.classList.add('text-gray-400');
                }

                const errorEl = this.parentElement.parentElement.querySelector('.client-error');
                if (errorEl) errorEl.remove();
            });
        });
    </script>
</body>

</html>
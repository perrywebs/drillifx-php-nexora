<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
try {
    $pdo = db();
    $n = $pdo->query('SELECT COUNT(*) c FROM users')->fetch()['c'] ?? 0;
    $sum = $pdo->query('SELECT COALESCE(SUM(total_earned),0) s FROM users')->fetch()['s'] ?? 0;
    $statUsers = $n > 0 ? ($n >= 1000 ? round($n / 1000, 1) . 'K+' : $n . '+') : '10K+';
    $statEarned = $sum > 0 ? '$' . number_format($sum / 1000, 1) . 'K+' : '$99K+';
    $rows = $pdo->query('SELECT username, total_earned FROM users ORDER BY total_earned DESC LIMIT 5')->fetchAll();
    $top5 = [];
    foreach ($rows as $r) $top5[] = ['masked' => mask_username($r['username']), 'total_earned' => $r['total_earned']];
    if (!$top5) $top5 = [
        ['masked' => 'Ki***22', 'total_earned' => 7410.75],
        ['masked' => 'fi***st', 'total_earned' => 7000.00],
        ['masked' => 'lu***56', 'total_earned' => 6710.00],
        ['masked' => 'Ka***ni', 'total_earned' => 6563.24],
        ['masked' => 'pa***et', 'total_earned' => 6000.00],
    ];
} catch (Throwable $e) {
    $statUsers = '10K+';
    $statEarned = '$99K+';
    $top5 = [['masked' => 'Ki***22', 'total_earned' => 7410.75], ['masked' => 'fi***st', 'total_earned' => 7000.00], ['masked' => 'lu***56', 'total_earned' => 6710.00], ['masked' => 'Ka***ni', 'total_earned' => 6563.24], ['masked' => 'pa***et', 'total_earned' => 6000.00]];
}
?>﻿
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php seo_head(['title' => site_name() . ' - ' . site_tagline(), 'description' => 'Create a free ' . site_name() . ' account to hash, spin and earn USDC rewards daily, and manage everything from your dashboard.', 'path' => '/']); ?>
    <script src="https://cdn.tailwindcss.com/3.4.17"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="">
    <link href="https://fonts.googleapis.com/css2?family=Signika+Negative:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Signika Negative', sans-serif;
        }

        /* Enhanced animations */
        @keyframes float {

            0%,
            100% {
                transform: translateY(0) rotate(0deg);
            }

            50% {
                transform: translateY(-15px) rotate(2deg);
            }
        }

        @keyframes pulse-glow {

            0%,
            100% {
                box-shadow: 0 0 30px rgba(59, 130, 246, 0.4), 0 0 60px rgba(59, 130, 246, 0.2);
            }

            50% {
                box-shadow: 0 0 50px rgba(59, 130, 246, 0.6), 0 0 100px rgba(59, 130, 246, 0.3);
            }
        }

        @keyframes fade-in-up {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes bounce-slow {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-8px);
            }
        }

        @keyframes shimmer {
            0% {
                background-position: -200% 0;
            }

            100% {
                background-position: 200% 0;
            }
        }

        @keyframes rotate-slow {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }

        @keyframes scale-pulse {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.05);
            }
        }

        @keyframes gradient-shift {

            0%,
            100% {
                background-position: 0% 50%;
            }

            50% {
                background-position: 100% 50%;
            }
        }

        .float {
            animation: float 4s ease-in-out infinite;
        }

        .pulse-glow {
            animation: pulse-glow 3s ease-in-out infinite;
        }

        .fade-in-up {
            animation: fade-in-up 0.8s ease-out forwards;
            opacity: 0;
        }

        .bounce-slow {
            animation: bounce-slow 2s ease-in-out infinite;
        }

        .shimmer {
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            background-size: 200% 100%;
            animation: shimmer 2s infinite;
        }

        .rotate-slow {
            animation: rotate-slow 20s linear infinite;
        }

        .scale-pulse {
            animation: scale-pulse 2s ease-in-out infinite;
        }

        .gradient-animate {
            background-size: 200% 200%;
            animation: gradient-shift 4s ease infinite;
        }

        .stagger-1 {
            animation-delay: 0.1s;
        }

        .stagger-2 {
            animation-delay: 0.2s;
        }

        .stagger-3 {
            animation-delay: 0.3s;
        }

        .stagger-4 {
            animation-delay: 0.4s;
        }

        .stagger-5 {
            animation-delay: 0.5s;
        }

        .stagger-6 {
            animation-delay: 0.6s;
        }

        html {
            scroll-behavior: smooth;
        }

        /* Glassmorphism card style */
        .glass-card {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.8);
        }

        /* Gradient text */
        .gradient-text {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 50%, #1e40af 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Button shine effect */
        .btn-shine {
            position: relative;
            overflow: hidden;
        }

        .btn-shine::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(to right, transparent, rgba(255, 255, 255, 0.3), transparent);
            transform: rotate(45deg);
            animation: shimmer 3s infinite;
        }

        /* Feature card hover effect */
        .feature-card {
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .feature-card:hover {
            transform: translateY(-8px) scale(1.02);
        }

        /* Testimonial card styling */
        .testimonial-card {
            transition: all 0.3s ease;
        }

        .testimonial-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>

<body class="bg-gradient-to-br from-blue-50 via-white to-blue-100 min-h-screen overflow-x-hidden">

    <!-- Hero Section -->
    <div class="min-h-screen flex flex-col relative overflow-hidden">
        <!-- Enhanced decorative elements with more visual interest -->
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            <div class="absolute top-10 left-5 w-32 h-32 bg-gradient-to-br from-blue-400/20 to-purple-400/20 rounded-full blur-2xl"></div>
            <div class="absolute top-40 right-0 w-48 h-48 bg-gradient-to-br from-blue-300/30 to-cyan-300/20 rounded-full blur-3xl"></div>
            <div class="absolute bottom-32 left-0 w-40 h-40 bg-gradient-to-br from-purple-300/20 to-blue-300/20 rounded-full blur-2xl"></div>
            <div class="absolute bottom-20 right-10 w-24 h-24 bg-gradient-to-br from-cyan-300/30 to-blue-400/20 rounded-full blur-xl"></div>

            <!-- Rotating decorative ring -->
            <div class="absolute top-1/4 right-1/4 w-64 h-64 border border-blue-200/30 rounded-full rotate-slow"></div>
            <div class="absolute bottom-1/4 left-1/4 w-48 h-48 border border-blue-300/20 rounded-full rotate-slow" style="animation-direction: reverse;"></div>

            <!-- Floating dots pattern -->
            <div class="absolute top-20 left-1/4 w-2 h-2 bg-blue-400/40 rounded-full bounce-slow"></div>
            <div class="absolute top-32 right-1/3 w-3 h-3 bg-purple-400/30 rounded-full bounce-slow stagger-2"></div>
            <div class="absolute bottom-40 left-1/3 w-2 h-2 bg-cyan-400/40 rounded-full bounce-slow stagger-3"></div>
        </div>

        <!-- Header -->
        <div class="p-4 flex items-center justify-between relative z-10">
            <div class="flex items-center gap-2">
                <img src="<?php echo e(site_logo()); ?>" alt="USDC" class="w-8 h-8 rounded-full">
                <span class="font-bold text-gray-800"><?php echo e(site_name()); ?></span>
            </div>
            <a href="login.php" class="flex items-center gap-1 text-blue-600 font-semibold text-sm hover:text-blue-700 transition bg-blue-50 px-4 py-2 rounded-xl hover:bg-blue-100">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                </svg>
                Sign In
            </a>
        </div>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col items-center justify-center px-4 py-8 relative z-10">
            <!-- Enhanced logo animation with outer ring -->
            <div class="float mb-8 relative">
                <div class="absolute inset-0 w-28 h-28 -m-2 bg-gradient-to-br from-blue-400/20 to-purple-400/20 rounded-3xl blur-xl scale-pulse"></div>
                <div class="w-24 h-24 bg-gradient-to-br from-blue-500 via-blue-600 to-blue-700 rounded-3xl flex items-center justify-center pulse-glow shadow-2xl relative gradient-animate">
                    <img src="<?php echo e(site_logo()); ?>" alt="USDC" class="w-16 h-16 rounded-full relative z-10">
                    <div class="absolute inset-0 rounded-3xl shimmer"></div>
                </div>
            </div>

            <!-- Enhanced title with gradient text -->
            <h1 class="text-3xl font-bold text-center mb-3 fade-in-up">
                Welcome to <span class="gradient-text"><?php echo e(site_name()); ?></span>
            </h1>
            <p class="text-gray-500 text-center mb-8 fade-in-up stagger-1 max-w-sm leading-relaxed">
                Hash, Spin & Earn USDC daily. Join <span class="text-blue-600 font-semibold">thousands</span> earning cryptocurrency!
            </p>

            <!-- Enhanced stats cards with glassmorphism -->
            <div class="grid grid-cols-2 gap-4 w-full max-w-xs mb-8 fade-in-up stagger-2">
                <div class="glass-card rounded-2xl p-4 text-center shadow-xl hover:shadow-2xl transition-all duration-300 hover:-translate-y-1">
                    <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-blue-700 rounded-xl flex items-center justify-center mx-auto mb-2 shadow-lg shadow-blue-500/30">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                    <p class="text-2xl font-bold gradient-text"><?php echo e($statUsers); ?></p>
                    <p class="text-xs text-gray-500 font-medium">Active Users</p>
                </div>
                <div class="glass-card rounded-2xl p-4 text-center shadow-xl hover:shadow-2xl transition-all duration-300 hover:-translate-y-1">
                    <div class="w-10 h-10 bg-gradient-to-br from-green-500 to-green-700 rounded-xl flex items-center justify-center mx-auto mb-2 shadow-lg shadow-green-500/30">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <p class="text-2xl font-bold text-green-600"><?php echo e($statEarned); ?></p>
                    <p class="text-xs text-gray-500 font-medium">Total Earned</p>
                </div>
            </div>

            <!-- Enhanced action buttons with shine effect -->
            <div class="w-full max-w-xs space-y-3 fade-in-up stagger-3">
                <a href="register.php" class="btn-shine flex items-center justify-center gap-2 w-full bg-gradient-to-r from-blue-600 via-blue-700 to-blue-800 hover:from-blue-700 hover:via-blue-800 hover:to-blue-900 text-white py-4 px-6 rounded-2xl font-semibold transition-all duration-300 shadow-xl shadow-blue-500/30 hover:shadow-2xl hover:shadow-blue-500/40 hover:-translate-y-0.5 gradient-animate">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                    </svg>
                    Get Started Free
                </a>
                <a href="login.php" class="flex items-center justify-center gap-2 w-full glass-card hover:bg-white text-gray-700 py-4 px-6 rounded-2xl font-semibold transition-all duration-300 shadow-lg hover:shadow-xl hover:-translate-y-0.5">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                    </svg>
                    Sign In
                </a>
            </div>
        </div>

        <!-- Scroll Indicator -->
        <br>
        <div class="absolute bottom-6 left-0 right-0 flex justify-center fade-in-up stagger-4">
            <a href="#features" class="flex flex-col items-center text-gray-400 hover:text-blue-600 transition">
                <span class="text-xs mb-1">Explore Features</span>
                <svg class="w-5 h-5 bounce-slow" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
                </svg>
            </a>
        </div>
    </div>

    <!-- Features Section -->
    <div id="features" class="px-4 py-16 bg-white relative overflow-hidden">
        <!-- Background pattern -->
        <div class="absolute inset-0 opacity-50">
            <div class="absolute top-10 right-10 w-32 h-32 bg-blue-100 rounded-full blur-3xl"></div>
            <div class="absolute bottom-10 left-10 w-40 h-40 bg-purple-100 rounded-full blur-3xl"></div>
        </div>

        <div class="relative z-10">
            <div class="text-center mb-10">
                <div class="inline-flex items-center gap-2 bg-gradient-to-r from-blue-100 to-purple-100 text-blue-700 px-4 py-2 rounded-full text-xs font-semibold mb-4 shadow-sm">
                    <svg class="w-4 h-4" fill="currentColor" viewbox="0 0 24 24">
                        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path>
                    </svg>
                    Premium Features
                </div>
                <h2 class="text-3xl font-bold text-gray-800 mb-3">Why Choose <span class="gradient-text">Us?</span></h2>
                <p class="text-gray-500 text-sm max-w-md mx-auto">Powerful features designed to maximize your earnings potential</p>
            </div>

            <div class="grid grid-cols-2 gap-4 max-w-lg mx-auto">
                <!-- Enhanced feature cards with better hover effects -->
                <!-- Feature 1 -->
                <div class="feature-card bg-gradient-to-br from-blue-50 via-white to-blue-100 rounded-2xl p-5 border border-blue-100 shadow-lg hover:shadow-xl">
                    <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-blue-700 rounded-xl flex items-center justify-center mb-4 shadow-lg shadow-blue-500/30">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path>
                        </svg>
                    </div>
                    <h3 class="font-bold text-gray-800 mb-1">Hash & Earn</h3>
                    <p class="text-xs text-gray-500 leading-relaxed">Click to hash and earn USDC instantly every day</p>
                </div>

                <!-- Feature 2 -->
                <div class="feature-card bg-gradient-to-br from-purple-50 via-white to-purple-100 rounded-2xl p-5 border border-purple-100 shadow-lg hover:shadow-xl">
                    <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-purple-700 rounded-xl flex items-center justify-center mb-4 shadow-lg shadow-purple-500/30">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                    </div>
                    <h3 class="font-bold text-gray-800 mb-1">Spin to Win</h3>
                    <p class="text-xs text-gray-500 leading-relaxed">Daily spins for exciting bonus rewards</p>
                </div>

                <!-- Feature 3 -->
                <div class="feature-card bg-gradient-to-br from-green-50 via-white to-green-100 rounded-2xl p-5 border border-green-100 shadow-lg hover:shadow-xl">
                    <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-green-700 rounded-xl flex items-center justify-center mb-4 shadow-lg shadow-green-500/30">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                        </svg>
                    </div>
                    <h3 class="font-bold text-gray-800 mb-1">Upgrade Tiers</h3>
                    <p class="text-xs text-gray-500 leading-relaxed">Unlock higher earnings with premium tiers</p>
                </div>

                <!-- Feature 4 -->
                <div class="feature-card bg-gradient-to-br from-orange-50 via-white to-orange-100 rounded-2xl p-5 border border-orange-100 shadow-lg hover:shadow-xl">
                    <div class="w-12 h-12 bg-gradient-to-br from-orange-500 to-orange-700 rounded-xl flex items-center justify-center mb-4 shadow-lg shadow-orange-500/30">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                    <h3 class="font-bold text-gray-800 mb-1">Fast Withdrawals</h3>
                    <p class="text-xs text-gray-500 leading-relaxed">Quick payouts to your wallet anytime</p>
                </div>

                <!-- Feature 5 -->
                <div class="feature-card bg-gradient-to-br from-pink-50 via-white to-pink-100 rounded-2xl p-5 border border-pink-100 shadow-lg hover:shadow-xl">
                    <div class="w-12 h-12 bg-gradient-to-br from-pink-500 to-pink-700 rounded-xl flex items-center justify-center mb-4 shadow-lg shadow-pink-500/30">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                    </div>
                    <h3 class="font-bold text-gray-800 mb-1">Refer & Earn</h3>
                    <p class="text-xs text-gray-500 leading-relaxed">Get bonus when friends upgrade</p>
                </div>

                <!-- Feature 6 -->
                <div class="feature-card bg-gradient-to-br from-cyan-50 via-white to-cyan-100 rounded-2xl p-5 border border-cyan-100 shadow-lg hover:shadow-xl">
                    <div class="w-12 h-12 bg-gradient-to-br from-cyan-500 to-cyan-700 rounded-xl flex items-center justify-center mb-4 shadow-lg shadow-cyan-500/30">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                    </div>
                    <h3 class="font-bold text-gray-800 mb-1">Secure & Safe</h3>
                    <p class="text-xs text-gray-500 leading-relaxed">Your funds are always protected</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Testimonials Section -->
    <div class="px-4 py-16 bg-gradient-to-br from-gray-50 via-white to-blue-50 relative overflow-hidden">
        <!-- Background decorations -->
        <div class="absolute top-0 left-0 w-full h-full overflow-hidden pointer-events-none">
            <div class="absolute -top-10 -right-10 w-40 h-40 bg-blue-100 rounded-full opacity-50 blur-3xl"></div>
            <div class="absolute -bottom-10 -left-10 w-40 h-40 bg-purple-100 rounded-full opacity-50 blur-3xl"></div>
        </div>

        <div class="relative z-10">
            <div class="text-center mb-10">
                <div class="inline-flex items-center gap-2 bg-gradient-to-r from-green-100 to-emerald-100 text-green-700 px-4 py-2 rounded-full text-xs font-semibold mb-4 shadow-sm">
                    <svg class="w-4 h-4" fill="currentColor" viewbox="0 0 24 24">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"></path>
                    </svg>
                    Testimonials
                </div>
                <h2 class="text-3xl font-bold text-gray-800 mb-3">What Our <span class="gradient-text">Users Say</span></h2>
                <p class="text-gray-500 text-sm">Real experiences from real earners</p>
            </div>

            <div class="space-y-4 max-w-lg mx-auto">
                <!-- Enhanced testimonial cards -->
                <!-- Testimonial 1 -->
                <div class="testimonial-card glass-card rounded-2xl p-6 shadow-xl">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-blue-700 rounded-2xl flex items-center justify-center text-white font-bold text-lg shadow-lg shadow-blue-500/30">
                            JD
                        </div>
                        <div>
                            <p class="font-bold text-gray-800">Jo***oe</p>
                            <div class="flex items-center gap-1">
                                <svg class="w-4 h-4 text-yellow-400" fill="currentColor" viewbox="0 0 24 24">
                                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path>
                                </svg>
                                <svg class="w-4 h-4 text-yellow-400" fill="currentColor" viewbox="0 0 24 24">
                                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path>
                                </svg>
                                <svg class="w-4 h-4 text-yellow-400" fill="currentColor" viewbox="0 0 24 24">
                                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path>
                                </svg>
                                <svg class="w-4 h-4 text-yellow-400" fill="currentColor" viewbox="0 0 24 24">
                                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path>
                                </svg>
                                <svg class="w-4 h-4 text-yellow-400" fill="currentColor" viewbox="0 0 24 24">
                                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-auto">
                            <svg class="w-8 h-8 text-blue-200" fill="currentColor" viewbox="0 0 24 24">
                                <path d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10h-9.983zm-14.017 0v-7.391c0-5.704 3.748-9.57 9-10.609l.996 2.151c-2.433.917-3.996 3.638-3.996 5.849h3.983v10h-9.983z"></path>
                            </svg>
                        </div>
                    </div>
                    <p class="text-gray-600 leading-relaxed">"I was skeptical at first, but after my first withdrawal went through smoothly, I knew this was legit. Now I earn daily just by hashing!"</p>
                </div>

                <!-- Testimonial 2 -->
                <div class="testimonial-card glass-card rounded-2xl p-6 shadow-xl">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-14 h-14 bg-gradient-to-br from-green-500 to-green-700 rounded-2xl flex items-center justify-center text-white font-bold text-lg shadow-lg shadow-green-500/30">
                            SK
                        </div>
                        <div>
                            <p class="font-bold text-gray-800">Sa***ng</p>
                            <div class="flex items-center gap-1">
                                <svg class="w-4 h-4 text-yellow-400" fill="currentColor" viewbox="0 0 24 24">
                                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path>
                                </svg>
                                <svg class="w-4 h-4 text-yellow-400" fill="currentColor" viewbox="0 0 24 24">
                                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path>
                                </svg>
                                <svg class="w-4 h-4 text-yellow-400" fill="currentColor" viewbox="0 0 24 24">
                                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path>
                                </svg>
                                <svg class="w-4 h-4 text-yellow-400" fill="currentColor" viewbox="0 0 24 24">
                                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path>
                                </svg>
                                <svg class="w-4 h-4 text-yellow-400" fill="currentColor" viewbox="0 0 24 24">
                                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-auto">
                            <svg class="w-8 h-8 text-green-200" fill="currentColor" viewbox="0 0 24 24">
                                <path d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10h-9.983zm-14.017 0v-7.391c0-5.704 3.748-9.57 9-10.609l.996 2.151c-2.433.917-3.996 3.638-3.996 5.849h3.983v10h-9.983z"></path>
                            </svg>
                        </div>
                    </div>
                    <p class="text-gray-600 leading-relaxed">"The referral program is amazing! I upgraded to Tier 3 and my earnings tripled. Plus, I got bonus from my referrals upgrading too."</p>
                </div>

                <!-- Testimonial 3 -->
                <div class="testimonial-card glass-card rounded-2xl p-6 shadow-xl">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="w-14 h-14 bg-gradient-to-br from-purple-500 to-purple-700 rounded-2xl flex items-center justify-center text-white font-bold text-lg shadow-lg shadow-purple-500/30">
                            MR
                        </div>
                        <div>
                            <p class="font-bold text-gray-800">Mi***el</p>
                            <div class="flex items-center gap-1">
                                <svg class="w-4 h-4 text-yellow-400" fill="currentColor" viewbox="0 0 24 24">
                                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path>
                                </svg>
                                <svg class="w-4 h-4 text-yellow-400" fill="currentColor" viewbox="0 0 24 24">
                                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path>
                                </svg>
                                <svg class="w-4 h-4 text-yellow-400" fill="currentColor" viewbox="0 0 24 24">
                                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path>
                                </svg>
                                <svg class="w-4 h-4 text-yellow-400" fill="currentColor" viewbox="0 0 24 24">
                                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path>
                                </svg>
                                <svg class="w-4 h-4 text-yellow-400" fill="currentColor" viewbox="0 0 24 24">
                                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-auto">
                            <svg class="w-8 h-8 text-purple-200" fill="currentColor" viewbox="0 0 24 24">
                                <path d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10h-9.983zm-14.017 0v-7.391c0-5.704 3.748-9.57 9-10.609l.996 2.151c-2.433.917-3.996 3.638-3.996 5.849h3.983v10h-9.983z"></path>
                            </svg>
                        </div>
                    </div>
                    <p class="text-gray-600 leading-relaxed">"Simple and effective. I love the spin feature - won $0.50 yesterday! The interface is clean and withdrawals are processed fast."</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Earners Section -->
    <div class="px-4 py-16 bg-gradient-to-br from-blue-50 via-white to-purple-50 relative overflow-hidden">
        <div class="absolute inset-0 pointer-events-none">
            <div class="absolute top-20 right-0 w-32 h-32 bg-yellow-100 rounded-full opacity-50 blur-3xl"></div>
            <div class="absolute bottom-20 left-0 w-32 h-32 bg-blue-100 rounded-full opacity-50 blur-3xl"></div>
        </div>

        <div class="relative z-10">
            <div class="text-center mb-8">
                <div class="inline-flex items-center gap-2 bg-gradient-to-r from-yellow-100 to-orange-100 text-yellow-700 px-4 py-2 rounded-full text-xs font-semibold mb-4 shadow-sm">
                    <svg class="w-4 h-4" fill="currentColor" viewbox="0 0 24 24">
                        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path>
                    </svg>
                    Leaderboard
                </div>
                <h2 class="text-3xl font-bold text-gray-800 mb-3">Top <span class="gradient-text">Earners</span></h2>
                <p class="text-gray-500 text-sm">See who's earning the most on our platform</p>
            </div>

            <div class="glass-card rounded-3xl shadow-2xl overflow-hidden max-w-lg mx-auto border border-white">
                <?php foreach ($top5 as $i => $t): ?>
                    <div class="flex items-center gap-4 p-5 border-b border-gray-100 hover:bg-white/50 transition-colors">
                        <div class="w-12 h-12 rounded-xl flex items-center justify-center shadow-lg <?php echo $i === 0 ? 'bg-gradient-to-br from-yellow-400 to-yellow-600 shadow-yellow-500/30 scale-pulse' : ($i === 1 ? 'bg-gradient-to-br from-gray-300 to-gray-500' : ($i === 2 ? 'bg-gradient-to-br from-orange-400 to-orange-600 shadow-orange-500/30' : 'bg-gradient-to-br from-gray-100 to-gray-200')); ?>">
                            <?php if ($i === 0): ?><svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                                </svg><?php else: ?><span class="font-bold <?php echo $i < 3 ? 'text-white text-lg' : 'text-gray-500'; ?>"><?php echo $i + 1; ?></span><?php endif; ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-bold text-gray-800 truncate"><?php echo e($t['masked']); ?></p>
                            <p class="text-xs text-gray-500">Top Earner</p>
                        </div>
                        <div class="text-right">
                            <p class="font-bold text-green-600 text-lg">$<?php echo e(number_format((float)$t['total_earned'], 2)); ?></p>
                            <p class="text-xs text-gray-400">Earned</p>
                        </div>
                    </div>
                <?php endforeach; ?>


                <!-- View All link -->
                <a href="top-earners.php" class="flex items-center justify-center gap-2 p-4 bg-gradient-to-r from-gray-50 to-white text-blue-600 font-semibold text-sm hover:from-blue-50 hover:to-white transition-all border-t border-gray-100 group">
                    View All Top Earners
                    <svg class="w-4 h-4 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
                    </svg>
                </a>
            </div>
        </div>
    </div>

    <!-- CTA Section -->
    <div class="px-4 py-16 bg-gradient-to-br from-blue-600 via-blue-700 to-blue-900 relative overflow-hidden">
        <!-- Decorative elements -->
        <div class="absolute inset-0 pointer-events-none overflow-hidden">
            <div class="absolute top-0 left-0 w-64 h-64 bg-white/5 rounded-full -translate-x-1/2 -translate-y-1/2"></div>
            <div class="absolute bottom-0 right-0 w-80 h-80 bg-white/5 rounded-full translate-x-1/3 translate-y-1/3"></div>
            <div class="absolute top-1/2 left-1/2 w-40 h-40 bg-white/5 rounded-full -translate-x-1/2 -translate-y-1/2"></div>
        </div>

        <div class="text-center max-w-lg mx-auto relative z-10">
            <div class="w-16 h-16 bg-white/10 rounded-2xl flex items-center justify-center mx-auto mb-6 backdrop-blur">
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                </svg>
            </div>
            <h2 class="text-3xl font-bold text-white mb-4">Ready to Start Earning?</h2>
            <p class="text-blue-200 mb-8 leading-relaxed">Join thousands of users earning USDC daily. It's completely free to get started!</p>
            <a href="register.php" class="btn-shine inline-flex items-center gap-2 bg-white text-blue-600 px-10 py-4 rounded-2xl font-bold hover:bg-blue-50 transition-all duration-300 shadow-2xl hover:shadow-white/20 hover:-translate-y-1">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                </svg>
                Create Free Account
            </a>
        </div>
    </div>

    <!-- Simple Footer -->
    <p class="text-center text-gray-400 text-xs py-8 bg-white">&copy; 2026 Drillifyx. All rights reserved.</p>

    <!-- Enhanced Back to Top Button -->
    <button id="backToTop" onclick="window.scrollTo({top: 0, behavior: 'smooth'})" class="fixed bottom-6 right-4 w-12 h-12 bg-gradient-to-br from-blue-600 to-blue-700 text-white rounded-2xl shadow-lg shadow-blue-500/30 flex items-center justify-center opacity-0 invisible transition-all duration-300 hover:shadow-xl hover:shadow-blue-500/40 hover:-translate-y-1 z-50 group">
        <svg class="w-5 h-5 group-hover:-translate-y-0.5 transition-transform" fill="none" stroke="currentColor" viewbox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path>
        </svg>
    </button>

    <script>
        // Back to top button visibility
        const backToTop = document.getElementById('backToTop');
        window.addEventListener('scroll', () => {
            if (window.scrollY > 300) {
                backToTop.classList.remove('opacity-0', 'invisible');
                backToTop.classList.add('opacity-100', 'visible');
            } else {
                backToTop.classList.add('opacity-0', 'invisible');
                backToTop.classList.remove('opacity-100', 'visible');
            }
        });

        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('fade-in-up');
                    entry.target.style.opacity = '1';
                }
            });
        }, observerOptions);

        document.querySelectorAll('.feature-card, .testimonial-card').forEach(el => {
            el.style.opacity = '0';
            observer.observe(el);
        });
    </script>

</body>

</html>
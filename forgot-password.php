<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mail.php';
if (current_user()) redirect('/users/dashboard.php');
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) { $msg = 'Invalid session. Try again.'; }
    else {
        $email = trim(strtolower($_POST['email'] ?? ''));
        if (!is_valid_email($email)) { $msg = 'Enter a valid email address.'; }
        else {
            $pdo = db();
            $st = $pdo->prepare('SELECT id, username, email FROM users WHERE email = ? LIMIT 1');
            $st->execute([$email]);
            $u = $st->fetch();
            // Always show same message (no enumeration). Create token only if user exists.
            if ($u) {
                $token = bin2hex(random_bytes(32));
                $st = $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?,?,DATE_ADD(NOW(), INTERVAL 1 HOUR))');
                $st->execute([(int)$u['id'], hash('sha256',$token)]);
                $link = rtrim(app_base_url(),'/') . '/reset-password.php?token=' . $token;
                send_template($email, 'password_reset', user_email_vars($u, ['reset_link' => $link]), (int)$u['id']);
                if (!mail_configured() && (getenv('APP_DEBUG') === '1' || getenv('APP_DEBUG') === 'true')) {
                    // Local-dev fallback only: never write reset links on production.
                    // (*.log is also blocked from browser access via .htaccess.)
                    @file_put_contents(APP_ROOT . '/storage_reset_links.log', date('c').' '.$email.' '.$link.PHP_EOL, FILE_APPEND);
                }
            }
            $msg = 'If that email is registered, a reset link has been sent (valid 1 hour).';
        }
    }
}
?>﻿<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php seo_head(['title' => 'Forgot Password - ' . site_name(), 'description' => 'Reset your ' . site_name() . ' account password securely with an email link.', 'path' => '/forgot-password.php']); ?>
    <script src="https://cdn.tailwindcss.com/3.4.17"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Signika+Negative:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Signika Negative', sans-serif; }
        /* Added shake animation for validation errors */
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-5px); }
            40%, 80% { transform: translateX(5px); }
        }
        .shake { animation: shake 0.4s ease-in-out; }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 via-white to-blue-100 min-h-screen flex items-center justify-center p-4">
    
    <div class="w-full max-w-md">
        <!-- Logo and Brand -->
        <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-blue-600 to-blue-800 rounded-2xl shadow-lg mb-3">
                <img src="<?php echo e(site_logo()); ?>" alt="USDC" class="w-10 h-10">
            </div>
            <h1 class="text-2xl font-bold text-gray-800"><?php echo e(site_name()); ?></h1>
            <p class="text-gray-500 text-sm">Reset your password</p>
        </div>
        
        <!-- Forgot Password Card -->
        <div class="bg-white rounded-2xl shadow-xl p-6 relative overflow-hidden">
            <!-- Decorative elements -->
            <div class="absolute top-0 right-0 w-24 h-24 bg-blue-50 rounded-full -mr-12 -mt-12"></div>
            <div class="absolute bottom-0 left-0 w-16 h-16 bg-blue-50 rounded-full -ml-8 -mb-8"></div>
            
            <div class="relative">
                <!-- Icon -->
                <div class="flex justify-center mb-4">
                    <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center">
                        <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                        </svg>
                    </div>
                </div>
                
                <h2 class="text-xl font-bold text-gray-800 text-center mb-2">Forgot your password?</h2>
                <p class="text-gray-500 text-sm text-center mb-6">No worries! Enter your email and we'll send you reset instructions.</p>
                
                                
                <!-- Added novalidate for custom validation -->
                <form method="POST" id="forgotForm" novalidate="" class="space-y-4"><?php echo csrf_field(); ?><?php if(!empty($msg)): ?><div class="bg-green-50 border border-green-200 text-green-700 text-sm rounded-xl p-3"><?php echo e($msg); ?></div><?php endif; ?>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Email Address</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                            <input type="email" name="email" id="email" class="w-full pl-10 pr-4 py-3 bg-gray-50 border rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:bg-white transition text-sm border-gray-200" placeholder="Enter your registered email" value="">
                        </div>
                        <!-- Inline error message -->
                                                <p class="hidden mt-1.5 text-xs text-red-600 items-center gap-1" id="emailError">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span></span>
                        </p>
                    </div>
                    
                    <button type="submit" class="w-full bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white py-3 px-6 rounded-xl font-semibold transition shadow-lg shadow-blue-500/30 flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                        Send Reset Link
                    </button>
                </form>
                                
                <div class="mt-6 text-center">
                    <a href="login.php" class="text-blue-600 text-sm font-semibold hover:text-blue-700 inline-flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Back to Sign In
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Footer text -->
        <p class="text-center text-gray-400 text-xs mt-6">&copy; 2026 Drillifyx. All rights reserved.</p>
    </div>
    
    <!-- Client-side validation script -->
    <script>
    const form = document.getElementById('forgotForm');
    const emailInput = document.getElementById('email');
    const emailError = document.getElementById('emailError');
    
    function showError(input, errorEl, message) {
        input.classList.add('border-red-500', 'bg-red-50', 'shake');
        input.classList.remove('border-gray-200');
        errorEl.classList.remove('hidden');
        errorEl.classList.add('flex');
        errorEl.querySelector('span').textContent = message;
    }
    
    function clearError(input, errorEl) {
        input.classList.remove('border-red-500', 'bg-red-50', 'shake');
        input.classList.add('border-gray-200');
        errorEl.classList.add('hidden');
        errorEl.classList.remove('flex');
    }
    
    emailInput.addEventListener('input', () => clearError(emailInput, emailError));
    
    form?.addEventListener('submit', function(e) {
        let valid = true;
        const emailVal = emailInput.value.trim();
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        
        if (!emailVal) {
            showError(emailInput, emailError, 'Please enter your email address');
            valid = false;
        } else if (!emailRegex.test(emailVal)) {
            showError(emailInput, emailError, 'Please enter a valid email address');
            valid = false;
        }
        
        if (!valid) e.preventDefault();
    });
    </script>
</body>
</html>

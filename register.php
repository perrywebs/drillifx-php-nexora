<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/mail.php';
if (current_user()) redirect('/users/dashboard.php');
if (!cfg_flag('registration_enabled', true)) { http_response_code(403); exit('Registration is currently disabled.'); }
$old = $_SESSION['old'] ?? []; unset($_SESSION['old']);
$err = $_SESSION['form_error'] ?? ''; unset($_SESSION['form_error']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) { $_SESSION['old'] = $_POST; flash('error','Security check','Invalid session. Please try again.'); redirect('/register.php' . (!empty($_GET['ref']) ? '?ref='.urlencode($_GET['ref']) : '')); }
    $username = trim($_POST['username'] ?? '');
    $email = trim(strtolower($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $ref = strtoupper(trim($_POST['referral_code'] ?? ''));
    $errors = [];
    if (strlen($username) < 3 || !preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)) $errors[] = 'Username must be 3+ chars (letters, numbers, underscore).';
    if (!is_valid_email($email)) $errors[] = 'Enter a valid email address.';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';
    $pdo = db();
    if (!$errors) {
        $st = $pdo->prepare('SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1');
        $st->execute([$email, $username]);
        if ($st->fetch()) $errors[] = 'Email or username already registered.';
    }
    $referrerId = null;
    if (!$errors && $ref !== '') {
        $st = $pdo->prepare('SELECT id FROM users WHERE referral_code = ? LIMIT 1');
        $st->execute([$ref]);
        $r = $st->fetch();
        if (!$r) $errors[] = 'Invalid referral code.';
        else $referrerId = (int)$r['id'];
    }
    if ($errors) {
        $_SESSION['old'] = ['username'=>$username,'email'=>$email,'referral_code'=>$_POST['referral_code'] ?? ''];
        foreach ($errors as $m) flash('error','Registration failed',$m);
        redirect('/register.php' . (!empty($_GET['ref']) ? '?ref='.urlencode($_GET['ref']) : ''));
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $code = gen_referral_code($pdo);
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('INSERT INTO users (username,email,password_hash,referral_code,referred_by_id) VALUES (?,?,?,?,?)');
        $st->execute([$username,$email,$hash,$code,$referrerId]);
        $uid = (int)$pdo->lastInsertId();
        if ($referrerId) {
            $st = $pdo->prepare('INSERT INTO referrals (referrer_id,referred_id,status) VALUES (?,?,\'pending\')');
            $st->execute([$referrerId,$uid]);
        }
        $st = $pdo->prepare('INSERT INTO transactions (user_id,type,amount,status,reference,description) VALUES (?,?,?,?,?,?)');
        $st->execute([$uid,'signup',0,'completed',gen_reference('TXN'),'Account created']);
        notify($uid,'Welcome to Drillifyx','Your account was created. Start with Hash & Spin from your dashboard.');
        log_activity($uid,'register','New account registered');
        $pdo->commit();
    } catch (Throwable $e) { $pdo->rollBack(); flash('error','Registration failed','Unable to create account. Try again.'); redirect('/register.php'); }
    $newUser = ['username' => $username, 'email' => $email, 'referral_code' => $code];
    send_template($email, 'welcome', user_email_vars($newUser), $uid);
    if (cfg_flag('email_verification', false)) {
        $vToken = bin2hex(random_bytes(32));
        $st = $pdo->prepare('INSERT INTO email_verify_tokens (user_id, token_hash, expires_at) VALUES (?,?,DATE_ADD(NOW(), INTERVAL 24 HOUR))');
        $st->execute([$uid, hash('sha256', $vToken)]);
        $verifyLink = rtrim(app_base_url(), '/') . '/verify.php?token=' . $vToken;
        send_template($email, 'email_verify', user_email_vars($newUser, ['verify_link' => $verifyLink]), $uid);
    }
    login_user($uid);
    log_activity($uid,'login','Logged in after registration');
    flash('success','Welcome','Account created successfully.');
    redirect('/users/dashboard.php');
}
?>﻿<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php seo_head(['title' => 'Register - ' . site_name(), 'description' => 'Create your free ' . site_name() . ' account and start earning USDC rewards with daily hash and spin.', 'path' => '/register.php']); ?>
    <script src="https://cdn.tailwindcss.com/3.4.17"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Signika+Negative:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Signika Negative', sans-serif; }
        /* Added shake animation for validation errors */
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        .shake {
            animation: shake 0.3s ease-in-out;
        }
        /* Toast animations */
        @keyframes slideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        @keyframes slideOut {
            from {
                transform: translateY(0);
                opacity: 1;
            }
            to {
                transform: translateY(-20px);
                opacity: 0;
            }
        }
        .animate-slideIn {
            animation: slideIn 0.3s ease-out;
        }
        .animate-slideOut {
            animation: slideOut 0.3s ease-in;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 via-white to-blue-100 min-h-screen flex items-center justify-center p-4">
    
    <!-- Toast Notification Container -->
    <div id="toastContainer" class="fixed z-50 w-full max-w-sm px-4 sm:px-6 right-[1px]">
        <!-- Toast will be inserted here -->
    </div>
    
    <div class="w-full max-w-md">
        <!-- Logo and Brand -->
        <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-blue-600 to-blue-800 rounded-2xl shadow-lg mb-3">
                <img src="<?php echo e(site_logo()); ?>" alt="USDC" class="w-10 h-10">
            </div>
            <h1 class="text-2xl font-bold text-gray-800"><?php echo e(site_name()); ?></h1>
            <p class="text-gray-500 text-sm">Create your account to get started</p>
        </div>
        
        <!-- Register Card -->
        <div class="bg-white rounded-2xl shadow-xl p-6 relative overflow-hidden">
            <!-- Decorative elements -->
            <div class="absolute top-0 right-0 w-24 h-24 bg-blue-50 rounded-full -mr-12 -mt-12"></div>
            <div class="absolute bottom-0 left-0 w-16 h-16 bg-blue-50 rounded-full -ml-8 -mb-8"></div>
            
            <div class="relative">
                <!-- Added novalidate to disable browser validation -->
                <form method="POST" class="space-y-4" id="registerForm" novalidate=""><?php echo csrf_field(); ?><input type="hidden" name="register" value="1">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Username</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                            </div>
                            <input type="text" name="username" id="username" value="<?php echo e($old['username'] ?? ($_GET['ref'] ?? '') === '' ? ($old['username'] ?? '') : ($old['username'] ?? '')); ?>" class="w-full pl-10 pr-4 py-3 bg-gray-50 border rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:bg-white transition text-sm border-gray-200" placeholder="Choose a username" value="<?php echo e($old['username'] ?? ''); ?>">
                        </div>
                                            </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Email Address</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                            <input type="email" name="email" id="email" class="w-full pl-10 pr-4 py-3 bg-gray-50 border rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:bg-white transition text-sm border-gray-200" placeholder="Enter your email" value="<?php echo e($old['email'] ?? ''); ?>">
                        </div>
                                            </div>
                    
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Password</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                    </svg>
                                </div>
                                <input type="password" name="password" id="password" class="w-full pl-10 pr-10 py-3 bg-gray-50 border rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:bg-white transition text-sm border-gray-200" placeholder="Min 6 chars">
                                <button type="button" onclick="togglePassword('password', 'eyeIcon1')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                                    <svg id="eyeIcon1" class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                </button>
                            </div>
                                                    </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Confirm</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                    </svg>
                                </div>
                                <input type="password" name="confirm_password" id="confirmPassword" class="w-full pl-10 pr-10 py-3 bg-gray-50 border rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:bg-white transition text-sm border-gray-200" placeholder="Re-enter">
                                <button type="button" onclick="togglePassword('confirmPassword', 'eyeIcon2')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                                    <svg id="eyeIcon2" class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                </button>
                            </div>
                                                    </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Referral Code <span class="text-gray-400 font-normal">(Optional)</span></label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                </svg>
                            </div>
                            <input type="text" name="referral_code" id="referralCode" class="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 focus:bg-white transition text-sm" placeholder="Enter referral code if you have one" value="<?php echo e($old['referral_code'] ?? ($_GET['ref'] ?? '')); ?>">
                        </div>
                    </div>
                    
                    <button type="submit" class="w-full bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white py-3 px-6 rounded-xl font-semibold transition shadow-lg shadow-blue-500/30 flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewbox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                        </svg>
                        Create Account
                    </button>
                </form>
                
                <div class="mt-6 text-center">
                    <p class="text-gray-600 text-sm">
                        Already have an account? 
                        <a href="login.php" class="text-blue-600 font-semibold hover:text-blue-700">Sign In</a>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Footer text -->
        <p class="text-center text-gray-400 text-xs mt-6">&copy; 2026 Drillifyx. All rights reserved.</p>
    </div>
    
    <!-- Toast Notification Script -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const toastContainer = document.getElementById('toastContainer');
        
        function showToast(toast) {
            const toastElement = document.createElement('div');
            toastElement.className = `animate-slideIn transform transition-all duration-300 ${toast.type === 'success' ? 'bg-gradient-to-r from-green-50 to-green-50/80 border border-green-200' : 'bg-gradient-to-r from-red-50 to-red-50/80 border border-red-200'} backdrop-blur-sm rounded-xl shadow-lg p-4`;
            
            const icon = toast.type === 'success' ? 
                '<svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>' :
                '<svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
            
            toastElement.innerHTML = `
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 mt-0.5">
                        <div class="w-8 h-8 ${toast.type === 'success' ? 'bg-green-100' : 'bg-red-100'} rounded-full flex items-center justify-center">
                            ${icon}
                        </div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold ${toast.type === 'success' ? 'text-green-800' : 'text-red-800'} text-sm">${toast.title}</p>
                        <p class="${toast.type === 'success' ? 'text-green-600' : 'text-red-600'} text-xs mt-1">${toast.message}</p>
                    </div>
                    <button onclick="removeToast(this)" class="flex-shrink-0 text-gray-400 hover:text-gray-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            `;
            
            toastContainer.appendChild(toastElement);
            
            // Auto remove after duration
            setTimeout(() => {
                if (toastElement.parentNode) {
                    removeToastElement(toastElement);
                }
            }, toast.duration || 5000);
        }
        
        function removeToast(button) {
            const toastElement = button.closest('.animate-slideIn');
            removeToastElement(toastElement);
        }
        
        function removeToastElement(toastElement) {
            toastElement.classList.remove('animate-slideIn');
            toastElement.classList.add('animate-slideOut');
            setTimeout(() => {
                if (toastElement.parentNode) {
                    toastContainer.removeChild(toastElement);
                }
            }, 300);
        }
        
        var __q = <?php echo json_encode(pull_flashes()); ?>; __q.forEach(function(t){ showToast({type: t.type, title: t.title, message: t.message, duration: 5000}); });
                
        // Expose to global scope
        window.removeToast = removeToast;
        window.showToast = showToast;
    });
    
    // Password toggle function
    function togglePassword(inputId, iconId) {
        const input = document.getElementById(inputId);
        const icon = document.getElementById(iconId);
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>';
        } else {
            input.type = 'password';
            icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>';
        }
    }
    
    // Client-side validation script
    document.getElementById('registerForm').addEventListener('submit', function(e) {
        var __btn = this.querySelector('button[type=submit]');
        const username = document.getElementById('username');
        const email = document.getElementById('email');
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirmPassword');
        let hasError = false;
        
        // Clear previous errors
        document.querySelectorAll('.client-error').forEach(el => el.remove());
        document.querySelectorAll('.shake').forEach(el => el.classList.remove('shake'));
        
        // Validate username
        if (!username.value.trim()) {
            showError(username, 'Username is required');
            hasError = true;
        } else if (username.value.trim().length < 3) {
            showError(username, 'Username must be at least 3 characters');
            hasError = true;
        }
        
        // Validate email
        if (!email.value.trim()) {
            showError(email, 'Email is required');
            hasError = true;
        } else if (!isValidEmail(email.value)) {
            showError(email, 'Please enter a valid email address');
            hasError = true;
        }
        
        // Validate password
        if (!password.value) {
            showError(password, 'Password is required');
            hasError = true;
        } else if (password.value.length < 6) {
            showError(password, 'Password must be at least 6 characters');
            hasError = true;
        }
        
        // Validate confirm password
        if (!confirmPassword.value) {
            showError(confirmPassword, 'Please confirm your password');
            hasError = true;
        } else if (password.value !== confirmPassword.value) {
            showError(confirmPassword, 'Passwords do not match');
            hasError = true;
        }
        
        if (hasError) {
            e.preventDefault();
        }
    });
    
    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }
    
    function showError(input, message) {
        input.classList.add('border-red-300', 'bg-red-50', 'shake');
        input.classList.remove('border-gray-200', 'bg-gray-50');
        
        // Update icon color
        const icon = input.parentElement.querySelector('.absolute.left-0 svg');
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
    document.querySelectorAll('#registerForm input').forEach(input => {
        input.addEventListener('input', function() {
            this.classList.remove('border-red-300', 'bg-red-50', 'shake');
            this.classList.add('border-gray-200', 'bg-gray-50');
            
            const icon = this.parentElement.querySelector('.absolute.left-0 svg');
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
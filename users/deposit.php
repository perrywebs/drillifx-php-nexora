<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mail.php';
require_once __DIR__ . '/../includes/payment_methods.php';
$u = require_login();
$pdo = db();

// Upgrade tiers: same source as the Upgrade page (tiers with a star rating).
$tierOpts = $pdo->query('SELECT * FROM tiers WHERE display_rating != "" AND active = 1 ORDER BY id')->fetchAll();
if (!$tierOpts) $tierOpts = $pdo->query('SELECT * FROM tiers WHERE active = 1 ORDER BY id')->fetchAll();
$tierIds = array_map(fn($t) => (int)$t['id'], $tierOpts);

$reqTierId = (int)($_GET['tier'] ?? $_GET['tier_id'] ?? 0);
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? null)) $err = 'Invalid session. Try again.';
    else {
        // Accept reference form names, fall back to previous ones.
        $tierId = (int)($_POST['tier_id'] ?? $_POST['tier'] ?? 0);
        $methodId = (int)($_POST['payment_method_id'] ?? $_POST['method'] ?? 0);
        $st = $pdo->prepare('SELECT * FROM tiers WHERE id=? LIMIT 1');
        $st->execute([$tierId]);
        $tier = $st->fetch() ?: null;
        // Server-side validation: never trust the browser's IDs.
        $method = $methodId > 0 ? pm_get_active_method($methodId) : null;
        if (!$tier) $err = 'Please select a valid tier.';
        elseif ((int)($tier['active'] ?? 1) !== 1) $err = 'This tier is not available right now.';
        elseif (!$method) $err = 'Please select an active payment method.';
        elseif ((int)$u['tier_id'] === $tierId) $err = 'You are already on this tier.';
        else {
            // prevent duplicate pending deposit for same tier
            $st = $pdo->prepare('SELECT id FROM deposits WHERE user_id=? AND tier_id=? AND status="pending" LIMIT 1');
            $st->execute([(int)$u['id'], $tierId]);
            if ($st->fetch()) $err = 'You already have a pending deposit for this tier.';
            elseif (empty($_FILES['proof']) || $_FILES['proof']['error'] !== UPLOAD_ERR_OK) $err = 'Upload your payment proof (image or PDF).';
            else {
                $f = $_FILES['proof'];
                if ($f['size'] > 5 * 1024 * 1024) $err = 'Proof file must be under 5MB.';
                else {
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime = $finfo->file($f['tmp_name']);
                    $ok = ['image/jpeg'=>'.jpg','image/png'=>'.png','image/webp'=>'.webp','application/pdf'=>'.pdf'];
                    if (!isset($ok[$mime])) $err = 'Proof must be JPG, PNG, WEBP or PDF.';
                    else {
                        $dir = __DIR__ . '/../uploads/proofs';
                        if (!is_dir($dir)) mkdir($dir, 0755, true);
                        $name = 'proof_' . (int)$u['id'] . '_' . bin2hex(random_bytes(8)) . $ok[$mime];
                        if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) $err = 'Upload failed. Try again.';
                        else {
                            $methodFields = pm_method_fields((int)$method['id']);
                            $ref = gen_reference('DEP');
                            $snap = json_encode(pm_build_snapshot($method, $methodFields), JSON_UNESCAPED_UNICODE);
                            $st = $pdo->prepare('INSERT INTO deposits (user_id,tier_id,payment_method_id,payment_method_name,payment_snapshot,amount,status,proof_path,reference) VALUES (?,?,?,?,?,?,?,?,?)');
                            $st->execute([(int)$u['id'], $tierId, (int)$method['id'], $method['name'], $snap, $tier['price'], 'pending', 'uploads/proofs/' . $name, $ref]);
                            log_activity((int)$u['id'], 'deposit', 'Upgrade deposit ' . $ref . ' for ' . $tier['name']);
                            notify((int)$u['id'], 'Deposit received', 'Your ' . $tier['name'] . ' payment proof is under review.');
                            send_template($u['email'], 'deposit_submitted', user_email_vars($u, ['tier_name' => $tier['name'], 'amount' => number_format((float)$tier['price'], 2), 'reference' => $ref]), (int)$u['id']);
                            flash('success','Deposit submitted','Your payment proof is under review. Your tier activates after approval.');
                            redirect('/users/upgrade.php');
                        }
                    }
                }
            }
        }
    }
}

// Current tier display name for the header card.
$curTierName = $u['tier_name'] ?? 'Free Tier';
// Preselect: requested tier if valid, else first tier that is not the user's current one.
$selTierId = in_array($reqTierId, $tierIds, true) ? $reqTierId : 0;
if (!$selTierId) {
    foreach ($tierOpts as $o) { if ((int)$o['id'] !== (int)$u['tier_id']) { $selTierId = (int)$o['id']; break; } }
    if (!$selTierId && $tierOpts) $selTierId = (int)$tierOpts[0]['id'];
}
$methods = pm_active_methods();
$methodsWithFields = [];
foreach ($methods as $m) {
    $methodsWithFields[] = [
        'id' => (int)$m['id'],
        'name' => $m['name'],
        'description' => (string)($m['description'] ?? ''),
        'fields' => array_map(fn($f) => [
            'label' => (string)$f['label'],
            'value' => (string)$f['value'],
            'display_type' => (string)$f['display_type'],
            'copy_enabled' => (int)$f['copy_enabled'],
        ], pm_method_fields((int)$m['id'])),
    ];
}
$fx = cfg_fx_rates();
$st = $pdo->prepare('SELECT * FROM deposits WHERE user_id=? ORDER BY id DESC LIMIT 5');
$st->execute([(int)$u['id']]);
$myDeps = $st->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <?php if (site_favicon()): ?><link rel="icon" href="<?php echo e(site_favicon()); ?>"><?php endif; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Deposit - <?php echo e(site_name()); ?></title>
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
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
        .badge-pulse {
            animation: pulse-badge 2s ease-in-out infinite;
        }
        /* Support icon spinning animation */
        @keyframes spin-slow {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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
                <a href="<?php echo e(url('/users/dashboard.php')); ?>" class="flex items-center gap-2">
                    <img src="<?php echo e(site_logo()); ?>" alt="<?php echo e(site_name()); ?>" class="w-8 h-8">
                    <span class="text-lg font-bold text-gray-800"><?php echo e(site_name()); ?></span>
                </a>
                <div class="flex items-center gap-2">
                    <!-- Added support icon -->
                    <!-- Added animation class to support icon -->
                    <a href="<?php echo e(url('/users/support.php')); ?>" class="w-9 h-9 bg-gray-50 hover:bg-blue-50 rounded-xl flex items-center justify-center transition group">
                        <svg class="w-5 h-5 text-gray-500 group-hover:text-blue-600 support-icon-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                    </a>
                    <!-- Notifications with unread badge -->
                    <a href="<?php echo e(url('/users/notifications.php')); ?>" class="w-9 h-9 bg-gray-50 hover:bg-blue-50 rounded-xl flex items-center justify-center transition relative group">
                        <svg class="w-5 h-5 text-gray-500 group-hover:text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
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

<!-- Header card matching dashboard style -->
<div class="bg-gradient-to-br from-blue-600 via-blue-700 to-blue-900 rounded-2xl p-4 mb-4 text-white relative overflow-hidden">
    <div class="absolute top-0 right-0 w-24 h-24 bg-white/10 rounded-full -mr-8 -mt-8"></div>
    <div class="absolute bottom-0 left-0 w-16 h-16 bg-white/5 rounded-full -ml-6 -mb-6"></div>

    <div class="relative">
        <div class="flex items-center gap-3 mb-3">
            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
            </div>
            <div>
                <h1 class="text-xl font-bold">Deposit & Upgrade</h1>
                <p class="text-blue-200 text-xs">Purchase a tier to increase earnings</p>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div class="bg-white/10 backdrop-blur rounded-xl p-3 text-center">
                <p class="text-blue-200 text-xs">Current Tier</p>
                <p class="font-bold"><?php echo e($curTierName); ?></p>
            </div>
            <div class="bg-white/10 backdrop-blur rounded-xl p-3 text-center">
                <p class="text-blue-200 text-xs">Balance</p>
                <p class="font-bold">$<?php echo e(money($u['balance'])); ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Deposit form with modern styling -->
<form method="POST" enctype="multipart/form-data" id="depositForm" novalidate onsubmit="return validateForm()">
    <?php echo csrf_field(); ?>
    <!-- Select Tier -->
    <div class="mb-4">
        <h2 class="text-gray-700 font-semibold mb-2 text-sm">Select Tier *</h2>
        <div class="bg-white rounded-xl shadow-sm p-4">
            <select name="tier_id" id="tierSelect" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition text-gray-700">
                <option value="">-- Select Tier --</option>
                <?php foreach ($tierOpts as $o): ?>
                <option value="<?php echo (int)$o['id']; ?>"
                    data-price="<?php echo e((string)$o['price']); ?>"
                    data-daily-hashes="<?php echo (int)$o['daily_hashes']; ?>"
                    data-hash-reward="<?php echo e((string)$o['per_hash']); ?>"
                    data-daily-spins="<?php echo (int)$o['daily_spins']; ?>"
                    data-duration="<?php echo (int)$o['duration_days']; ?>"
                    data-name="<?php echo e($o['display_name'] !== '' ? $o['display_name'] : $o['name']); ?>"
                    <?php echo (int)$o['id'] === $selTierId ? 'selected' : ''; ?>>
                    <?php echo e($o['display_name'] !== '' ? $o['display_name'] : $o['name']); ?> - $<?php echo e(money($o['price'])); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <p class="text-xs text-red-500 mt-1 hidden" id="tierError">Please select a tier</p>
    </div>

    <!-- Tier Details with modern card style -->
    <div id="tierDetails" class="hidden mb-4">
        <div class="bg-white rounded-xl shadow-sm p-3">
            <!-- Made tier header more compact -->
            <div class="flex items-center gap-2 mb-2">
                <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-blue-700 rounded-lg flex items-center justify-center">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 id="tierName" class="font-bold text-gray-800 text-sm truncate"></h3>
                </div>
                <span class="bg-blue-100 text-blue-700 text-xs font-semibold px-2 py-0.5 rounded-full flex-shrink-0" id="tierDuration">30d</span>
            </div>

            <!-- Made stats grid more compact with smaller text -->
            <div class="grid grid-cols-4 gap-1.5 mb-2">
                <div class="bg-gray-50 rounded-lg p-1.5 text-center">
                    <div class="text-sm font-bold text-blue-600" id="dailyHashes">0</div>
                    <div class="text-[10px] text-gray-500">Hashes</div>
                </div>
                <div class="bg-gray-50 rounded-lg p-1.5 text-center">
                    <div class="text-sm font-bold text-green-600">$<span id="rewardPerHash">0</span></div>
                    <div class="text-[10px] text-gray-500">Per Hash</div>
                </div>
                <div class="bg-gray-50 rounded-lg p-1.5 text-center">
                    <div class="text-sm font-bold text-purple-600" id="dailySpins">0</div>
                    <div class="text-[10px] text-gray-500">Spins</div>
                </div>
                <div class="bg-gray-50 rounded-lg p-1.5 text-center">
                    <div class="text-sm font-bold text-orange-600">$<span id="dailyEarnings">0</span></div>
                    <div class="text-[10px] text-gray-500">Daily</div>
                </div>
            </div>

            <!-- Made earnings row more compact -->
            <div class="grid grid-cols-3 gap-1.5 text-center">
                <div class="bg-green-50 rounded-lg p-1.5">
                    <p class="text-[10px] text-gray-500">Weekly</p>
                    <p class="font-bold text-green-600 text-xs">$<span id="weeklyEarnings">0</span></p>
                </div>
                <div class="bg-green-50 rounded-lg p-1.5">
                    <p class="text-[10px] text-gray-500">Monthly</p>
                    <p class="font-bold text-green-600 text-xs">$<span id="monthlyEarnings">0</span></p>
                </div>
                <div class="bg-blue-50 rounded-lg p-1.5">
                    <p class="text-[10px] text-gray-500">ROI</p>
                    <p class="font-bold text-blue-600 text-xs" id="roiPeriod">0d</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Currency Converter -->
    <div class="mb-4">
        <h2 class="text-gray-700 font-semibold mb-2 text-sm">Amount in Local Currency</h2>
        <div class="bg-white rounded-xl shadow-sm p-4">
            <!-- Fixed overflow by using flex-shrink-0 on select and min-w-0 + truncate on input -->
            <div class="flex items-center gap-2">
                <select id="currencySelect" class="flex-shrink-0 w-20 px-2 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none transition text-sm">
                    <?php foreach ($fx as $code => $rate): ?>
                    <option value="<?php echo e((string)$rate); ?>" data-code="<?php echo e((string)$code); ?>">
                        <?php echo e((string)$code); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <input type="text" id="localAmount" readonly class="flex-1 min-w-0 bg-blue-50 px-3 py-3 border border-blue-200 rounded-xl font-bold text-blue-700 text-sm truncate">
            </div>
        </div>
    </div>

    <!-- Payment Method with card selection style -->
    <div class="mb-4">
        <h2 class="text-gray-700 font-semibold mb-2 text-sm">Payment Method *</h2>
        <div class="bg-white rounded-xl shadow-sm p-4">
            <?php if (!$methodsWithFields): ?>
            <p class="text-sm text-gray-500 text-center py-2">No payment methods are available right now. Please check back later or contact support.</p>
            <?php else: ?>
            <div class="grid grid-cols-2 gap-2" id="paymentMethodsContainer">
                <?php foreach ($methodsWithFields as $i => $m): ?>
                <label class="relative cursor-pointer">
                    <input type="radio" name="payment_method_id" value="<?php echo (int)$m['id']; ?>" class="peer sr-only paymentMethod" required>
                    <div class="p-3 border-2 border-gray-200 rounded-xl text-center transition-all peer-checked:border-blue-500 peer-checked:bg-blue-50 hover:border-blue-300">
                        <div class="w-8 h-8 mx-auto mb-1 bg-gray-100 rounded-lg flex items-center justify-center peer-checked:bg-blue-100">
                            <?php if ($i % 3 === 0): ?>
                            <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                            <?php elseif ($i % 3 === 1): ?>
                            <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                            <?php else: ?>
                            <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                            </svg>
                            <?php endif; ?>
                        </div>
                        <p class="font-medium text-gray-700 text-xs"><?php echo e($m['name']); ?></p>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <p class="text-xs text-red-500 mt-1 hidden" id="paymentMethodError">Please select a payment method</p>
    </div>

    <!-- Payment Details -->
    <div id="paymentDetails" class="hidden mb-4">
        <h2 class="text-gray-700 font-semibold mb-2 text-sm">Payment Details</h2>
        <div class="bg-white rounded-xl shadow-sm p-4">
            <div id="methodIntro" class="hidden text-xs text-blue-700 bg-blue-50 rounded-lg p-3 mb-2"></div>
            <div id="detailsContent" class="text-sm text-gray-700 bg-gray-50 rounded-lg p-3"></div>
        </div>
    </div>

    <!-- Proof Upload -->
    <div class="mb-4">
        <h2 class="text-gray-700 font-semibold mb-2 text-sm">Proof of Payment *</h2>
        <div class="bg-white rounded-xl shadow-sm p-4">
            <label class="block">
                <div class="border-2 border-dashed border-gray-300 rounded-xl p-4 text-center hover:border-blue-400 transition cursor-pointer" id="uploadArea">
                    <svg class="w-8 h-8 mx-auto mb-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <p class="text-sm text-gray-600" id="uploadText">Click to upload screenshot</p>
                    <p class="text-xs text-gray-400 mt-1">JPG, PNG, WEBP or PDF up to 5MB</p>
                </div>
                <input type="file" name="proof" accept=".jpg,.jpeg,.png,.webp,.pdf" required class="hidden" id="proofInput">
            </label>
        </div>
        <p class="text-xs text-red-500 mt-1 hidden" id="proofError">Please upload proof of payment</p>
    </div>

    <!-- Submit Button -->
    <button type="submit" class="w-full bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white py-3 px-6 rounded-xl font-semibold transition shadow-lg shadow-blue-500/30 flex items-center justify-center gap-2 mb-4">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
        </svg>
        Submit Payment
    </button>
</form>

<!-- Recent Deposits with View All link -->
<?php if ($myDeps): ?>
<div class="mb-4">
    <div class="flex items-center justify-between mb-2">
        <h2 class="text-gray-700 font-semibold text-sm">Recent Deposits</h2>
        <a href="<?php echo e(url('/users/transactions.php')); ?>" class="text-blue-600 text-xs font-medium">View All</a>
    </div>
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <?php foreach ($myDeps as $d): ?>
        <div class="flex items-center justify-between gap-2 p-3 border-b border-gray-100 last:border-0">
            <div class="min-w-0">
                <p class="font-medium text-gray-800 text-sm truncate"><?php echo e($d['reference']); ?> · $<?php echo e(money($d['amount'])); ?></p>
                <p class="text-xs text-gray-500"><?php echo e(date('M j, Y', strtotime($d['created_at']))); ?><?php if (!empty($d['payment_method_name'])): ?> · <?php echo e($d['payment_method_name']); ?><?php endif; ?></p>
            </div>
            <span class="flex-shrink-0 text-xs font-bold px-2 py-1 rounded-full <?php echo $d['status']==='pending'?'bg-yellow-100 text-yellow-700':($d['status']==='approved'?'bg-green-100 text-green-700':'bg-red-100 text-red-700'); ?>"><?php echo e(ucfirst($d['status'])); ?></span>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Deposit Info Tips -->
<div class="bg-blue-50 rounded-xl p-4 mb-4">
    <h3 class="font-semibold text-blue-800 text-sm mb-2 flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        Deposit Info
    </h3>
    <ul class="text-xs text-blue-700 space-y-1">
        <li class="flex items-start gap-2">
            <span class="w-1 h-1 bg-blue-400 rounded-full mt-1.5 flex-shrink-0"></span>
            Deposits are typically processed within minutes
        </li>
        <li class="flex items-start gap-2">
            <span class="w-1 h-1 bg-blue-400 rounded-full mt-1.5 flex-shrink-0"></span>
            Ensure payment screenshot is clear and readable
        </li>
        <li class="flex items-start gap-2">
            <span class="w-1 h-1 bg-blue-400 rounded-full mt-1.5 flex-shrink-0"></span>
            Your tier activates immediately after approval
        </li>
    </ul>
</div>

<script>
// Payment methods + custom fields come from the database (admin-managed).
// Rendered with textContent only, so admin-configured values cannot inject HTML.
const paymentMethods = <?php echo json_encode($methodsWithFields, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
const currentTierId = <?php echo (int)$u['tier_id']; ?>;

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
        switch(type) {
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
        switch(type) {
            case 'success': return 'bg-green-500';
            case 'error': return 'bg-red-500';
            case 'warning': return 'bg-yellow-500';
            default: return 'bg-blue-500';
        }
    }

    static getBorderColor(type) {
        switch(type) {
            case 'success': return 'border-green-500';
            case 'error': return 'border-red-500';
            case 'warning': return 'border-yellow-500';
            default: return 'border-blue-500';
        }
    }

    static getProgressColor(type) {
        switch(type) {
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
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($err): ?>Toast.show(<?php echo json_encode($err, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>, 'error', 6000);<?php endif; ?>
});

// Form validation function (mirrors server-side rules; server always re-checks)
function validateForm() {
    let isValid = true;
    let errorMessages = [];

    // Validate tier selection
    const tierSelect = document.getElementById('tierSelect');
    if (!tierSelect.value) {
        tierSelect.classList.add('border-red-500');
        tierSelect.classList.remove('border-gray-200');
        errorMessages.push('Please select a tier');
        isValid = false;
    } else {
        tierSelect.classList.remove('border-red-500');
        tierSelect.classList.add('border-gray-200');
    }
    if (tierSelect.value && parseInt(tierSelect.value, 10) === currentTierId) {
        errorMessages.push('You are already on this tier');
        isValid = false;
    }

    // Validate payment method selection
    const paymentMethodSelected = document.querySelector('input[name="payment_method_id"]:checked');
    const pmContainer = document.getElementById('paymentMethodsContainer');
    if (!paymentMethodSelected) {
        if (pmContainer) pmContainer.classList.add('border-2', 'border-red-500', 'rounded-xl');
        errorMessages.push('Please select a payment method');
        isValid = false;
    } else if (pmContainer) {
        pmContainer.classList.remove('border-2', 'border-red-500', 'rounded-xl');
    }

    // Validate file upload (must agree with server: 5MB, JPG/PNG/WEBP/PDF)
    const proofInput = document.getElementById('proofInput');
    if (!proofInput.files || proofInput.files.length === 0) {
        document.getElementById('uploadArea').classList.add('border-red-500');
        document.getElementById('uploadArea').classList.remove('border-gray-300');
        errorMessages.push('Please upload proof of payment');
        isValid = false;
    } else {
        document.getElementById('uploadArea').classList.remove('border-red-500');
        document.getElementById('uploadArea').classList.add('border-gray-300');

        // Check file size (5MB limit)
        const file = proofInput.files[0];
        if (file.size > 5 * 1024 * 1024) {
            errorMessages.push('File is too large. Maximum size is 5MB.');
            isValid = false;
        }

        // Check file type (same list the server accepts)
        const allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
        if (!allowedTypes.includes(file.type)) {
            errorMessages.push('Invalid file type. Only JPG, PNG, WEBP or PDF are allowed.');
            isValid = false;
        }
    }

    // Show error toast if validation fails
    if (!isValid && errorMessages.length > 0) {
        Toast.show(errorMessages[0], 'error', 6000);
    }

    return isValid;
}

// Replace copy success function with toast
function showCopySuccess() {
    Toast.show('Copied to clipboard!', 'success', 2000);
}

// Modify copy function to use toast
function copyToClipboard(text, event) {
    // Prevent form submission
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }

    const done = () => Toast.show('Copied to clipboard!', 'success', 2000);
    const fallback = () => {
        try {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.opacity = '0';
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            Toast.show('Copied to clipboard!', 'success', 2000);
        } catch (err) {
            Toast.show('Copy failed. Long-press to copy manually.', 'error', 3000);
        }
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(done, fallback);
    } else {
        fallback();
    }
}

function copyButton(label) {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'flex-shrink-0 flex items-center gap-1 bg-blue-50 hover:bg-blue-100 text-blue-700 py-1.5 px-2.5 rounded-lg transition-colors text-xs font-medium';
    btn.innerHTML = '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg><span>Copy</span>';
    btn.setAttribute('aria-label', 'Copy ' + label);
    return btn;
}

function showPaymentDetails(method) {
    const box = document.getElementById('paymentDetails');
    const content = document.getElementById('detailsContent');
    const intro = document.getElementById('methodIntro');
    box.classList.remove('hidden');
    content.innerHTML = '';
    if (method.description) {
        intro.textContent = method.description;
        intro.classList.remove('hidden');
    } else {
        intro.textContent = '';
        intro.classList.add('hidden');
    }
    if (!method.fields || !method.fields.length) {
        const p = document.createElement('p');
        p.className = 'text-sm text-gray-500';
        p.textContent = 'Payment details for this method are not available yet. Please choose a different method or contact support.';
        content.appendChild(p);
        return;
    }
    const list = document.createElement('div');
    list.className = 'space-y-2';
    method.fields.forEach((f) => {
        if (f.display_type === 'multiline') {
            const wrap = document.createElement('div');
            wrap.className = 'py-2 border-b border-gray-100 last:border-0';
            const head = document.createElement('div');
            head.className = 'flex items-center justify-between gap-2 mb-1';
            const lab = document.createElement('span');
            lab.className = 'font-medium text-gray-800 text-sm';
            lab.textContent = f.label;
            head.appendChild(lab);
            if (f.copy_enabled) {
                const b = copyButton(f.label);
                b.addEventListener('click', (ev) => copyToClipboard(f.value, ev));
                head.appendChild(b);
            }
            const val = document.createElement('p');
            val.className = 'text-gray-600 break-all text-sm whitespace-pre-line';
            val.textContent = f.value;
            wrap.appendChild(head);
            wrap.appendChild(val);
            list.appendChild(wrap);
        } else {
            const row = document.createElement('div');
            row.className = 'flex items-center justify-between gap-2 py-2 border-b border-gray-100 last:border-0';
            const lab = document.createElement('span');
            lab.className = 'font-medium text-gray-800 flex-shrink-0';
            lab.textContent = f.label + ':';
            const right = document.createElement('div');
            right.className = 'flex items-center gap-2 min-w-0';
            const val = document.createElement('span');
            val.className = 'text-gray-600 break-all text-right font-mono text-xs bg-white border border-gray-100 p-1.5 rounded-lg';
            val.textContent = f.value;
            right.appendChild(val);
            if (f.copy_enabled) {
                const b = copyButton(f.label);
                b.addEventListener('click', (ev) => copyToClipboard(f.value, ev));
                right.appendChild(b);
            }
            row.appendChild(lab);
            row.appendChild(right);
            list.appendChild(row);
        }
    });
    content.appendChild(list);
    // "Copy All Details" button at the bottom
    const foot = document.createElement('div');
    foot.className = 'mt-3 pt-3 border-t border-gray-200';
    const all = document.createElement('button');
    all.type = 'button';
    all.className = 'w-full flex items-center justify-center gap-2 bg-blue-50 hover:bg-blue-100 text-blue-700 py-2.5 px-4 rounded-lg transition-colors group';
    all.innerHTML = '<svg class="w-4 h-4 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg><span class="font-medium text-sm">Copy All Details</span>';
    all.addEventListener('click', (ev) => {
        const text = method.fields.map((f) => f.label + ': ' + f.value).join('\n');
        copyToClipboard(text, ev);
    });
    foot.appendChild(all);
    content.appendChild(foot);
}

// Rest of the JavaScript code...
document.getElementById('tierSelect').addEventListener('change', function() {
    updateLocalAmount();
    updateTierDetails();
});
document.getElementById('currencySelect').addEventListener('change', updateLocalAmount);

// File upload preview
document.getElementById('proofInput').addEventListener('change', function() {
    const file = this.files[0];
    if (file) {
        document.getElementById('uploadText').textContent = file.name;
        document.getElementById('uploadArea').classList.add('border-blue-500', 'bg-blue-50');
        document.getElementById('uploadArea').classList.remove('border-red-500');
    }
});

function updateTierDetails() {
    const tier = document.getElementById('tierSelect');
    const selected = tier.options[tier.selectedIndex];
    const detailsDiv = document.getElementById('tierDetails');

    if (!selected || !selected.value) {
        detailsDiv.classList.add('hidden');
        return;
    }

    const price = parseFloat(selected.dataset.price) || 0;
    const dailyHashes = parseInt(selected.dataset.dailyHashes) || 0;
    const hashReward = parseFloat(selected.dataset.hashReward) || 0;
    const dailySpins = parseInt(selected.dataset.dailySpins) || 0;
    const duration = parseInt(selected.dataset.duration) || 0;
    const name = selected.dataset.name || '';

    const dailyEarnings = dailyHashes * hashReward;
    const weeklyEarnings = dailyEarnings * 7;
    const monthlyEarnings = dailyEarnings * 30;
    const roiDays = dailyEarnings > 0 ? Math.ceil(price / dailyEarnings) : 0;

    document.getElementById('tierName').textContent = name;
    document.getElementById('tierDuration').textContent = duration > 0 ? duration + ' days' : 'Lifetime';
    document.getElementById('dailyHashes').textContent = dailyHashes;
    document.getElementById('rewardPerHash').textContent = hashReward.toFixed(2);
    document.getElementById('dailySpins').textContent = dailySpins;
    document.getElementById('dailyEarnings').textContent = dailyEarnings.toFixed(2);
    document.getElementById('weeklyEarnings').textContent = weeklyEarnings.toFixed(2);
    document.getElementById('monthlyEarnings').textContent = monthlyEarnings.toFixed(2);
    document.getElementById('roiPeriod').textContent = roiDays > 0 ? roiDays + 'd' : '-';

    detailsDiv.classList.remove('hidden');
}

function updateLocalAmount() {
    const tier = document.getElementById('tierSelect');
    const currency = document.getElementById('currencySelect');
    const localAmount = document.getElementById('localAmount');

    const price = parseFloat(tier.options[tier.selectedIndex]?.dataset?.price || 0);
    const rate = parseFloat(currency.value);
    const code = currency.options[currency.selectedIndex]?.dataset?.code || 'USD';

    localAmount.value = code + ' ' + (price * rate).toLocaleString();
}

// Payment method selection (options come from the database, not hardcoded)
document.querySelectorAll('.paymentMethod').forEach(radio => {
    radio.addEventListener('change', function() {
        const method = paymentMethods.find(m => m.id == this.value);
        if (!method) return;

        // Clear payment method error when selected
        const container = document.getElementById('paymentMethodsContainer');
        if (container) container.classList.remove('border-2', 'border-red-500', 'rounded-xl');

        showPaymentDetails(method);
    });
});

// Initialize on page load
updateLocalAmount();
updateTierDetails();
</script>

</main>

    <!-- Redesigned bottom navigation with modern SVG icons -->
    <nav class="fixed bottom-0 left-0 right-0 bg-white/95 backdrop-blur-lg border-t border-gray-100 shadow-2xl z-50 safe-area-bottom">
        <div class="flex justify-around items-center h-16 max-w-lg mx-auto">
            <!-- Home -->
            <!-- Added pathPrefix to all navigation links -->
            <a href="<?php echo e(url('/users/dashboard.php')); ?>" class="bottom-nav-item flex flex-col items-center justify-center flex-1 h-full relative text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                <span class="text-xs font-medium">Home</span>
            </a>
            <!-- Hash -->
            <a href="<?php echo e(url('/users/hash.php')); ?>" class="bottom-nav-item flex flex-col items-center justify-center flex-1 h-full relative text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                </svg>
                <span class="text-xs font-medium">Hash</span>
            </a>
            <!-- Spin -->
            <a href="<?php echo e(url('/users/spin.php')); ?>" class="bottom-nav-item flex flex-col items-center justify-center flex-1 h-full relative text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                <span class="text-xs font-medium">Spin</span>
            </a>
            <!-- Withdraw -->
            <a href="<?php echo e(url('/users/withdraw.php')); ?>" class="bottom-nav-item flex flex-col items-center justify-center flex-1 h-full relative text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
                <span class="text-xs font-medium">Withdraw</span>
            </a>
            <!-- Profile -->
            <a href="<?php echo e(url('/users/profile.php')); ?>" class="bottom-nav-item flex flex-col items-center justify-center flex-1 h-full relative text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
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
<?php echo render_toast_queue(); ?>
</body>
</html>

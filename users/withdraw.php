<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/withdraw_logic.php';
$u = require_login();
$pdo = db();
$st = $pdo->prepare('SELECT pin_hash FROM users WHERE id=?'); $st->execute([(int)$u['id']]);
$hasPin = !empty($st->fetch()['pin_hash']);
$st = $pdo->prepare('SELECT amount, method, status, reference, created_at FROM withdrawals WHERE user_id=? ORDER BY id DESC LIMIT 5'); $st->execute([(int)$u['id']]);
$recentWd = $st->fetchAll();
// Non-JS fallback
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    if (!csrf_check($_POST['csrf'] ?? null)) { flash('error','Security check','Invalid session.'); redirect('/users/withdraw.php'); }
    $r = do_withdraw((int)$u['id'], $_POST);
    if (!$r['ok'] && !empty($r['need_pin'])) redirect('/users/set-pin.php');
    flash($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Withdrawal submitted' : 'Withdrawal failed', $r['msg']);
    redirect('/users/withdraw.php');
}
?><!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
<?php if (site_favicon()): ?><link rel="icon" href="<?php echo e(site_favicon()); ?>"><?php endif; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Withdraw - <?php echo e(site_name()); ?></title>
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

        <div
            class="bg-gradient-to-br from-blue-500 via-blue-600 to-blue-800 rounded-2xl p-4 mb-4 text-white relative overflow-hidden">
            <div class="absolute top-0 right-0 w-24 h-24 bg-white/10 rounded-full -mr-8 -mt-8"></div>
            <div class="absolute bottom-0 left-0 w-16 h-16 bg-white/5 rounded-full -ml-6 -mb-6"></div>

            <div class="relative">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-lg font-bold">Withdraw Funds</h1>
                        <p class="text-blue-200 text-xs">Cash out your earnings</p>
                    </div>
                </div>

                <div class="bg-white/10 backdrop-blur rounded-xl p-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-200 text-xs uppercase tracking-wider">Available Balance</p>
                            <p class="text-2xl font-bold">$<?php echo e(money($u["balance"])); ?></p>
                        </div>
                        <div class="text-right">
                            <p class="text-blue-200 text-xs">Min. Withdrawal</p>
                            <p class="text-lg font-bold">$100.00</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm p-4 mb-4">
            <h2 class="text-gray-700 font-semibold text-sm mb-3">Withdrawal Details</h2>

            <form method="POST" class="space-y-4" id="withdrawForm" novalidate action="/users/withdraw.php"><?php echo csrf_field(); ?><?php if (!$hasPin): ?><div class="bg-yellow-50 border border-yellow-200 text-yellow-800 text-sm rounded-xl p-3">Set your <a class="font-semibold underline" href="/users/set-pin.php">withdrawal PIN</a> before requesting a withdrawal.</div><?php endif; ?>
                <!-- Amount Input -->
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Amount (USDC)</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 font-bold">$</span>
                        <input type="number" name="amount" step="0.01" min="100" max="<?php echo e((string)(float)$u["balance"]); ?>"
                            class="w-full pl-8 pr-4 py-3 border rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent text-lg font-semibold border-gray-200"
                            id="withdrawAmount" placeholder="0.00" value="">
                    </div>
                </div>

                <div class="bg-gradient-to-r from-blue-50 to-sky-50 rounded-xl p-3 border border-blue-100">
                    <label class="block text-xs font-medium text-gray-500 mb-2">You Will Receive</label>
                    <div class="flex items-center gap-2">
                        <select id="currencySelect"
                            class="px-3 py-2 border border-gray-200 rounded-lg bg-white text-sm font-medium focus:ring-2 focus:ring-blue-500">
                            <option value="1.0000" data-code="USD">
                                USD </option>
                            <option value="1600.0000" data-code="NGN">
                                NGN </option>
                            <option value="21.6000" data-code="GHS">
                                GHS </option>
                        </select>
                        <div class="flex-1 bg-white px-3 py-2 border border-gray-200 rounded-lg">
                            <span id="localAmount" class="font-bold text-blue-600">0.00</span>
                        </div>
                    </div>
                </div>

                <!-- Payment Method Selection -->
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-2">Payment Method</label>
                    <div class="grid grid-cols-2 gap-2" id="paymentMethodGrid">
                        <label class="payment-method-option cursor-pointer">
                            <input type="radio" name="payment_method" value="Bank Transfer" class="hidden peer">
                            <div
                                class="border-2 border-gray-200 rounded-xl p-3 text-center peer-checked:border-blue-500 peer-checked:bg-blue-50 transition hover:border-gray-300">
                                <div
                                    class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center mx-auto mb-1">
                                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                    </svg>
                                </div>
                                <p class="text-xs font-medium text-gray-700">Bank</p>
                            </div>
                        </label>
                        <label class="payment-method-option cursor-pointer">
                            <input type="radio" name="payment_method" value="MOMO" class="hidden peer">
                            <div
                                class="border-2 border-gray-200 rounded-xl p-3 text-center peer-checked:border-blue-500 peer-checked:bg-blue-50 transition hover:border-gray-300">
                                <div
                                    class="w-8 h-8 bg-yellow-100 rounded-lg flex items-center justify-center mx-auto mb-1">
                                    <svg class="w-4 h-4 text-yellow-600" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <p class="text-xs font-medium text-gray-700">MOMO</p>
                            </div>
                        </label>
                        <label class="payment-method-option cursor-pointer">
                            <input type="radio" name="payment_method" value="USDT" class="hidden peer">
                            <div
                                class="border-2 border-gray-200 rounded-xl p-3 text-center peer-checked:border-blue-500 peer-checked:bg-blue-50 transition hover:border-gray-300">
                                <div
                                    class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center mx-auto mb-1">
                                    <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <p class="text-xs font-medium text-gray-700">USDT</p>
                            </div>
                        </label>
                        <label class="payment-method-option cursor-pointer">
                            <input type="radio" name="payment_method" value="USDC" class="hidden peer">
                            <div
                                class="border-2 border-gray-200 rounded-xl p-3 text-center peer-checked:border-blue-500 peer-checked:bg-blue-50 transition hover:border-gray-300">
                                <div
                                    class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center mx-auto mb-1">
                                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <p class="text-xs font-medium text-gray-700">USDC</p>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Bank Transfer Details -->
                <div id="bankTransferFields" class="space-y-3 hidden">
                    <div class="bg-gray-50 rounded-xl p-3 space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Select Bank</label>
                            <select name="bank_name" id="bankSelect"
                                class="w-full px-3 py-2.5 border rounded-xl focus:ring-2 focus:ring-blue-500 text-sm border-gray-200">
                                <option value="">-- Select Bank --</option>
                                <optgroup label="International Banks">
                                    <option value="Wise (TransferWise)">Wise (TransferWise)</option>
                                    <option value="PayPal">PayPal</option>
                                    <option value="Payoneer">Payoneer</option>
                                    <option value="Revolut">Revolut</option>
                                    <option value="Skrill">Skrill</option>
                                    <option value="Western Union">Western Union</option>
                                    <option value="MoneyGram">MoneyGram</option>
                                </optgroup>
                                <optgroup label="Nigerian Banks">
                                    <option value="Access Bank">Access Bank</option>
                                    <option value="First Bank of Nigeria">First Bank of Nigeria</option>
                                    <option value="Guaranty Trust Bank (GTBank)">Guaranty Trust Bank (GTBank)</option>
                                    <option value="United Bank for Africa (UBA)">United Bank for Africa (UBA)</option>
                                    <option value="Zenith Bank">Zenith Bank</option>
                                    <option value="Ecobank Nigeria">Ecobank Nigeria</option>
                                    <option value="Fidelity Bank">Fidelity Bank</option>
                                    <option value="Union Bank of Nigeria">Union Bank of Nigeria</option>
                                    <option value="Stanbic IBTC Bank">Stanbic IBTC Bank</option>
                                    <option value="Sterling Bank">Sterling Bank</option>
                                    <option value="Wema Bank">Wema Bank</option>
                                    <option value="Polaris Bank">Polaris Bank</option>
                                    <option value="Keystone Bank">Keystone Bank</option>
                                    <option value="FCMB">FCMB</option>
                                    <option value="Jaiz Bank">Jaiz Bank</option>
                                    <option value="Opay">Opay</option>
                                    <option value="Kuda Bank">Kuda Bank</option>
                                    <option value="PalmPay">PalmPay</option>
                                    <option value="Moniepoint">Moniepoint</option>
                                </optgroup>
                                <optgroup label="Ghana Banks">
                                    <option value="Ghana Commercial Bank (GCB)">Ghana Commercial Bank (GCB)</option>
                                    <option value="Ecobank Ghana">Ecobank Ghana</option>
                                    <option value="Stanbic Bank Ghana">Stanbic Bank Ghana</option>
                                    <option value="Standard Chartered Bank Ghana">Standard Chartered Bank Ghana</option>
                                    <option value="Absa Bank Ghana">Absa Bank Ghana</option>
                                    <option value="Fidelity Bank Ghana">Fidelity Bank Ghana</option>
                                    <option value="Zenith Bank Ghana">Zenith Bank Ghana</option>
                                    <option value="Access Bank Ghana">Access Bank Ghana</option>
                                    <option value="CalBank">CalBank</option>
                                    <option value="Agricultural Development Bank (ADB)">Agricultural Development Bank
                                        (ADB)</option>
                                    <option value="Republic Bank Ghana">Republic Bank Ghana</option>
                                    <option value="Prudential Bank">Prudential Bank</option>
                                    <option value="MTN Mobile Money">MTN Mobile Money</option>
                                    <option value="Vodafone Cash">Vodafone Cash</option>
                                    <option value="AirtelTigo Money">AirtelTigo Money</option>
                                </optgroup>
                                <optgroup label="Kenya Banks">
                                    <option value="Kenya Commercial Bank (KCB)">Kenya Commercial Bank (KCB)</option>
                                    <option value="Equity Bank">Equity Bank</option>
                                    <option value="Co-operative Bank of Kenya">Co-operative Bank of Kenya</option>
                                    <option value="ABSA Bank Kenya">ABSA Bank Kenya</option>
                                    <option value="Standard Chartered Kenya">Standard Chartered Kenya</option>
                                    <option value="Diamond Trust Bank (DTB)">Diamond Trust Bank (DTB)</option>
                                    <option value="Stanbic Bank Kenya">Stanbic Bank Kenya</option>
                                    <option value="NCBA Bank">NCBA Bank</option>
                                    <option value="I&amp;M Bank">I&amp;M Bank</option>
                                    <option value="Family Bank">Family Bank</option>
                                    <option value="M-Pesa">M-Pesa</option>
                                    <option value="Airtel Money Kenya">Airtel Money Kenya</option>
                                </optgroup>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Account Name</label>
                            <input type="text" name="account_name" placeholder="Enter account holder name"
                                class="w-full px-3 py-2.5 border rounded-xl focus:ring-2 focus:ring-blue-500 text-sm border-gray-200"
                                value="">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Account Number</label>
                            <input type="text" name="account_number" placeholder="Enter account number"
                                class="w-full px-3 py-2.5 border rounded-xl focus:ring-2 focus:ring-blue-500 text-sm border-gray-200"
                                value="">
                        </div>
                    </div>
                </div>

                <!-- MOMO Details -->
                <div id="momoFields" class="space-y-3 hidden">
                    <div class="bg-gray-50 rounded-xl p-3 space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Mobile Money Provider</label>
                            <select name="momo_provider"
                                class="w-full px-3 py-2.5 border rounded-xl focus:ring-2 focus:ring-blue-500 text-sm border-gray-200">
                                <option value="">-- Select Provider --</option>
                                <option value="MTN Mobile Money">MTN Mobile Money</option>
                                <option value="Vodafone Cash">Vodafone Cash</option>
                                <option value="AirtelTigo Money">AirtelTigo Money</option>
                                <option value="M-Pesa">M-Pesa</option>
                                <option value="Airtel Money">Airtel Money</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Account Name</label>
                            <input type="text" name="momo_name" placeholder="Enter registered name"
                                class="w-full px-3 py-2.5 border rounded-xl focus:ring-2 focus:ring-blue-500 text-sm border-gray-200"
                                value="">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Mobile Number</label>
                            <input type="tel" name="momo_number" placeholder="Enter mobile number"
                                class="w-full px-3 py-2.5 border rounded-xl focus:ring-2 focus:ring-blue-500 text-sm border-gray-200"
                                value="">
                        </div>
                    </div>
                </div>

                <!-- Crypto Details -->
                <div id="cryptoFields" class="space-y-3 hidden">
                    <div class="bg-gray-50 rounded-xl p-3 space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Network</label>
                            <select name="wallet_network"
                                class="w-full px-3 py-2.5 border rounded-xl focus:ring-2 focus:ring-blue-500 text-sm border-gray-200">
                                <option value="">-- Select Network --</option>
                                <option value="TRC20 (Tron)">TRC20 (Tron)</option>
                                <option value="ERC20 (Ethereum)">ERC20 (Ethereum)</option>
                                <option value="Solana">Solana</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Wallet Address</label>
                            <input type="text" name="wallet_address" placeholder="Enter wallet address"
                                class="w-full px-3 py-2.5 border rounded-xl focus:ring-2 focus:ring-blue-500 text-sm font-mono border-gray-200"
                                value="">
                        </div>
                    </div>
                </div>

                <!-- Added Withdrawal PIN Input Field -->
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Withdrawal PIN</label>
                    <div class="relative">
                        <div class="absolute left-3 top-1/2 -translate-y-1/2">
                            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                        </div>
                        <input type="password" name="withdrawal_pin" maxlength="4" pattern="[0-9]{4}"
                            inputmode="numeric"
                            class="w-full pl-10 pr-4 py-3 border rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent text-center text-xl font-bold tracking-widest border-gray-200"
                            placeholder="****">
                    </div>
                    <a href="/users/set-pin.php" class="text-blue-500 text-xs hover:underline mt-1 inline-block">Change PIN</a>
                </div>

                <button type="submit"
                    class="w-full bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white py-3.5 rounded-xl font-semibold transition shadow-lg shadow-blue-500/30 flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    Request Withdrawal
                </button>
            </form>
        </div>

        <?php if ($recentWd): ?>
        <div class="bg-white rounded-2xl shadow-sm p-4 mb-4">
            <h3 class="text-gray-700 font-semibold text-sm mb-2">Recent Withdrawals</h3>
            <?php foreach ($recentWd as $w): ?>
            <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
                <div><p class="font-semibold text-sm text-gray-800">$<?php echo e(money($w["amount"])); ?> <span class="text-xs font-normal text-gray-400"><?php echo e($w["method"]); ?></span></p><p class="text-xs text-gray-400"><?php echo e(date("M j, g:i A", strtotime($w["created_at"]))); ?> · <?php echo e($w["reference"]); ?></p></div>
                <span class="text-xs font-bold px-2 py-1 rounded-full <?php echo $w["status"] === "pending" ? "bg-yellow-100 text-yellow-700" : ($w["status"] === "paid" || $w["status"] === "approved" ? "bg-green-100 text-green-700" : "bg-red-100 text-red-700"); ?>"><?php echo e(ucfirst($w["status"])); ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <!-- Recent Withdrawals -->

        <!-- Withdrawal Info -->
        <div class="bg-blue-50 rounded-2xl p-4 mb-4 border border-blue-100">
            <h3 class="text-blue-800 font-semibold text-sm mb-3 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Withdrawal Info
            </h3>
            <ul class="space-y-2 text-xs text-blue-700">
                <li class="flex items-start gap-2">
                    <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Minimum withdrawal amount is $100.00</span>
                </li>
                <li class="flex items-start gap-2">
                    <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Withdrawals are processed within minutes</span>
                </li>
                <li class="flex items-start gap-2">
                    <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Ensure your payment details are accurate</span>
                </li>
            </ul>
        </div>

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

<script>
document.getElementById('withdrawForm').addEventListener('submit', function (e) {
    e.preventDefault();
    var form = this;
    var btn = form.querySelector('button[type=submit]');
    var orig = btn.innerHTML;
    btn.disabled = true; btn.style.opacity = '.7'; btn.innerHTML = 'Processing...';
    $.ajax({
        url: (window.APP_BASE||'')+'/ajax/withdraw.php', method: 'POST', dataType: 'json',
        data: $(form).serialize(), timeout: 20000
    }).done(function (res) {
        if (res && res.success) {
            Toast.show(res.message || 'Withdrawal submitted.', 'success', 5000);
            setTimeout(function(){ window.location.reload(); }, 1500);
        } else {
            if (res && res.data && res.data.need_pin) { window.location.href = (window.APP_BASE||'')+'/users/set-pin.php'; return; }
            Toast.show((res && res.message) || 'Withdrawal failed.', 'error', 6000);
            btn.disabled = false; btn.style.opacity = ''; btn.innerHTML = orig;
        }
    }).fail(function () {
        Toast.show('Network error. Please try again.', 'error', 5000);
        btn.disabled = false; btn.style.opacity = ''; btn.innerHTML = orig;
    });
});
</script>
            // Show PHP messages as toasts on page load
            document.addEventListener('DOMContentLoaded', function () {

            });

            // Payment method toggle
            document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
                radio.addEventListener('change', function () {
                    document.getElementById('bankTransferFields').classList.add('hidden');
                    document.getElementById('momoFields').classList.add('hidden');
                    document.getElementById('cryptoFields').classList.add('hidden');

                    if (this.value === 'Bank Transfer') {
                        document.getElementById('bankTransferFields').classList.remove('hidden');
                    } else if (this.value === 'MOMO') {
                        document.getElementById('momoFields').classList.remove('hidden');
                    } else if (this.value === 'USDT' || this.value === 'USDC') {
                        document.getElementById('cryptoFields').classList.remove('hidden');
                    }
                });
            });

            // Show fields on page load if method was selected
            const selectedMethod = document.querySelector('input[name="payment_method"]:checked');
            if (selectedMethod) {
                selectedMethod.dispatchEvent(new Event('change'));
            }

            // Currency conversion
            const amountInput = document.getElementById('withdrawAmount');
            const currencySelect = document.getElementById('currencySelect');
            const localAmountDisplay = document.getElementById('localAmount');

            function updateLocalAmount() {
                const amount = parseFloat(amountInput.value) || 0;
                const rate = parseFloat(currencySelect.value) || 1;
                const code = currencySelect.options[currencySelect.selectedIndex].dataset.code;
                localAmountDisplay.textContent = (amount * rate).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + code;
            }

            amountInput.addEventListener('input', updateLocalAmount);
            currencySelect.addEventListener('change', updateLocalAmount);
            updateLocalAmount();
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
                class="bottom-nav-item flex flex-col items-center justify-center flex-1 h-full relative active">
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

<?php
declare(strict_types=1);
// Shared withdrawal processing (users/withdraw.php fallback + ajax/withdraw.php).
function do_withdraw(int $userId, array $in): array {
    require_once __DIR__ . '/settings.php';
    $min = cfg_min_withdraw();
    $rates = cfg_fx_rates();
    $amount = round((float)($in['amount'] ?? 0), 2);
    $method = trim($in['payment_method'] ?? '');
    $currency = strtoupper(trim($in['currency'] ?? 'USD'));
    $pin = $in['withdrawal_pin'] ?? '';
    if ($amount <= 0) return ['ok'=>false,'msg'=>'Enter a valid withdrawal amount.'];
    if ($amount < $min) return ['ok'=>false,'msg'=>'Minimum withdrawal is $' . number_format($min,2) . '.'];
    if (!in_array($method, ['Bank Transfer','MOMO','USDT','USDC'], true)) return ['ok'=>false,'msg'=>'Select a payment method.'];
    if (!isset($rates[$currency])) $currency = 'USD';
    if (!preg_match('/^\d{4}$/', (string)$pin)) return ['ok'=>false,'msg'=>'Enter your 4-digit withdrawal PIN.'];
    $details = [];
    if ($method === 'Bank Transfer') {
        foreach (['bank_name','account_name','account_number'] as $f) {
            if (trim($in[$f] ?? '') === '') return ['ok'=>false,'msg'=>'Complete all bank transfer details.'];
            $details[$f] = trim($in[$f]);
        }
    } elseif ($method === 'MOMO') {
        foreach (['momo_provider','momo_name','momo_number'] as $f) {
            if (trim($in[$f] ?? '') === '') return ['ok'=>false,'msg'=>'Complete all mobile money details.'];
            $details[$f] = trim($in[$f]);
        }
    } else {
        if (trim($in['wallet_network'] ?? '') === '') return ['ok'=>false,'msg'=>'Select a wallet network.'];
        if (strlen(trim($in['wallet_address'] ?? '')) < 10) return ['ok'=>false,'msg'=>'Enter a valid wallet address.'];
        $details = ['wallet_network'=>trim($in['wallet_network']), 'wallet_address'=>trim($in['wallet_address'])];
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT * FROM users WHERE id=? FOR UPDATE');
        $st->execute([$userId]);
        $u = $st->fetch();
        if (!$u || $u['status'] !== 'active') { $pdo->rollBack(); return ['ok'=>false,'msg'=>'Account not available.']; }
        if (empty($u['pin_hash'])) { $pdo->rollBack(); return ['ok'=>false,'msg'=>'Set your withdrawal PIN first.','need_pin'=>true]; }
        if (!password_verify((string)$pin, $u['pin_hash'])) { $pdo->rollBack(); return ['ok'=>false,'msg'=>'Incorrect withdrawal PIN.']; }
        $bal = (float)$u['balance'];
        if ($amount > $bal) { $pdo->rollBack(); return ['ok'=>false,'msg'=>'Insufficient balance. Available: $' . number_format($bal,2) . '.']; }
        $rate = (float)$rates[$currency];
        $local = round($amount * $rate, 2);
        $ref = gen_reference('WD');
        $st = $pdo->prepare('INSERT INTO withdrawals (user_id,amount,currency_code,fx_rate,local_amount,method,details,status,reference) VALUES (?,?,?,?,?,? ,?,"pending",?)');
        $st->execute([$userId, $amount, $currency, $rate, $local, $method, json_encode($details), $ref]);
        $newBal = $bal - $amount;
        $pdo->prepare('UPDATE users SET balance=? WHERE id=?')->execute([$newBal, $userId]);
        $st = $pdo->prepare('INSERT INTO transactions (user_id,type,amount,balance_after,status,reference,description) VALUES (?,?,?,?,?,?,?)');
        $st->execute([$userId, 'Withdrawal', -$amount, $newBal, 'pending', $ref, $method . ' to ' . ($details['account_number'] ?? $details['momo_number'] ?? substr($details['wallet_address'] ?? '',0,12)) . ' (' . $currency . ')']);
        log_activity($userId, 'withdraw', 'Withdrawal request $' . number_format($amount,2) . ' via ' . $method);
        notify($userId, 'Withdrawal requested', 'Your $' . number_format($amount,2) . ' withdrawal via ' . $method . ' is pending.');
        $pdo->commit();
        require_once __DIR__ . '/mail.php';
        try { send_template($u['email'], 'withdrawal_submitted', user_email_vars($u, ['amount' => number_format($amount, 2), 'method' => $method, 'reference' => $ref]), $userId); } catch (Throwable $e) {}
        return ['ok'=>true,'msg'=>'Withdrawal request submitted. Processing within minutes.','reference'=>$ref,'balance'=>$newBal];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok'=>false,'msg'=>'Unable to process withdrawal. Try again.'];
    }
}

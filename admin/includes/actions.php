<?php
declare(strict_types=1);
// Defined admin operations: every balance/status change goes through here
// (validation + row locking + audit + user notification + email). No blind editing.
require_once __DIR__ . '/../../includes/mail.php';

function admin_adjust_balance(array $admin, int $userId, float $amount, string $reason): array {
    $amount = round($amount, 2);
    if ($amount == 0) return ['ok' => false, 'msg' => 'Amount must not be zero.'];
    if (abs($amount) > 1000000) return ['ok' => false, 'msg' => 'Amount exceeds the allowed limit.'];
    if (mb_strlen(trim($reason)) < 3) return ['ok' => false, 'msg' => 'A reason is required.'];
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT * FROM users WHERE id=? FOR UPDATE');
        $st->execute([$userId]);
        $u = $st->fetch();
        if (!$u) { $pdo->rollBack(); return ['ok' => false, 'msg' => 'User not found.']; }
        if ($amount < 0 && (float)$u['balance'] + $amount < 0) { $pdo->rollBack(); return ['ok' => false, 'msg' => 'Debit exceeds available balance ($' . number_format((float)$u['balance'], 2) . ').']; }
        $ref = gen_reference('ADJ');
        $newBal = (float)$u['balance'] + $amount;
        $pdo->prepare('UPDATE users SET balance=?, total_earned=total_earned+? WHERE id=?')->execute([$newBal, max(0, $amount), $userId]);
        $pdo->prepare('INSERT INTO transactions (user_id,type,amount,balance_after,status,reference,description) VALUES (?,?,?,?,?,?)')
            ->execute([$userId, $amount > 0 ? 'Admin Credit' : 'Admin Debit', $amount, $newBal, 'completed', $ref, 'Admin ' . $admin['username'] . ': ' . mb_substr(trim($reason), 0, 200)]);
        notify($userId, $amount > 0 ? 'Account credited' : 'Account debited', 'Admin adjustment of $' . number_format(abs($amount), 2) . ' (' . $ref . '). Reason: ' . mb_substr(trim($reason), 0, 200));
        log_activity($userId, 'admin_adjust', ($amount > 0 ? 'Credited $' : 'Debited $') . number_format(abs($amount), 2) . ' by ' . $admin['username']);
        $pdo->commit();
        admin_log((int)$admin['id'], $amount > 0 ? 'credit_user' : 'debit_user', 'Admin ' . $admin['username'] . ($amount > 0 ? ' credited ' : ' debited ') . $u['username'] . ' $' . number_format(abs($amount), 2) . '. Reason: ' . trim($reason), 'user', $userId);
        return ['ok' => true, 'msg' => 'Balance updated. New balance: $' . number_format($newBal, 2)];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok' => false, 'msg' => 'Adjustment failed.'];
    }
}

function admin_set_user_status(array $admin, int $userId, string $status): array {
    if (!in_array($status, ['active', 'suspended'], true)) return ['ok' => false, 'msg' => 'Invalid status.'];
    $pdo = db();
    $st = $pdo->prepare('SELECT username, status FROM users WHERE id=?');
    $st->execute([$userId]);
    $u = $st->fetch();
    if (!$u) return ['ok' => false, 'msg' => 'User not found.'];
    if ($u['status'] === $status) return ['ok' => false, 'msg' => 'User is already ' . $status . '.'];
    $pdo->prepare('UPDATE users SET status=? WHERE id=?')->execute([$status, $userId]);
    $show = $status === 'suspended' ? 'disabled' : 'active';
    notify($userId, 'Account status changed', 'Your account is now ' . $show . '. Contact support if you have questions.');
    log_activity($userId, 'status', 'Account ' . $show . ' by admin ' . $admin['username']);
    admin_log((int)$admin['id'], $status === 'suspended' ? 'suspend_user' : 'activate_user', 'Admin ' . $admin['username'] . ' set ' . $u['username'] . ' to ' . $show, 'user', $userId);
    return ['ok' => true, 'msg' => $u['username'] . '\'s account is now ' . $show . '.'];
}

function admin_approve_deposit(array $admin, int $depositId): array {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT d.*, u.username, u.email, u.referred_by_id, u.tier_id, t.display_name, t.display_rating FROM deposits d JOIN users u ON u.id=d.user_id LEFT JOIN tiers t ON t.id = u.tier_id WHERE d.id=? FOR UPDATE');
        $st->execute([$depositId]);
        $d = $st->fetch();
        if (!$d) { $pdo->rollBack(); return ['ok' => false, 'msg' => 'Deposit not found.']; }
        if ($d['status'] !== 'pending') { $pdo->rollBack(); return ['ok' => false, 'msg' => 'Deposit is already ' . $d['status'] . '. Duplicate processing blocked.']; }
        // Determine tier from tiers table (single source of truth)
        $tierSt = $pdo->prepare('SELECT * FROM tiers WHERE id = ? LIMIT 1');
        $tierSt->execute([(int)$d['tier_id']]);
        $tierRow = $tierSt->fetch();
        if ($tierRow) {
            $tierName = $tierRow['display_name'] ?? 'Beginner';
            $displayRating = $tierRow['display_rating'] ?? '⭐⭐';
        } else {
            $tierName = 'Free Tier';
            $displayRating = '';
        }
        $expiry = date('Y-m-d H:i:s', strtotime('+' . ($tierRow['duration_days'] ?? 30) . ' days'));
        $pdo->prepare('UPDATE users SET display_name=?, display_rating=?, tier_expires_at=? WHERE id=?')->execute([$tierName, $displayRating, $expiry, (int)$d['user_id']]);
        $pdo->prepare('UPDATE deposits SET status="approved" WHERE id=? AND status="pending"')->execute([$depositId]);
        // Referral reward: pending referral becomes earned, referrer credited
        $reward = cfg_referral_reward();
        $st = $pdo->prepare('SELECT * FROM referrals WHERE referred_id=? AND status="pending" LIMIT 1 FOR UPDATE');
        $st->execute([(int)$d['user_id']]);
        $ref = $st->fetch();
        if ($ref && $reward > 0) {
            $pdo->prepare('UPDATE referrals SET status="earned", reward=? WHERE id=?')->execute([$reward, (int)$ref['id']]);
            $pdo->prepare('UPDATE users SET balance=balance+?, total_earned=total_earned+?, referral_earnings=referral_earnings+? WHERE id=?')->execute([$reward, $reward, $reward, (int)$ref['referrer_id']]);
            $st = $pdo->prepare('SELECT balance, username, email FROM users WHERE id=?');
            $st->execute([(int)$ref['referrer_id']]);
            $rr = $st->fetch();
            $pdo->prepare('INSERT INTO transactions (user_id,type,amount,balance_after,status,reference,description) VALUES (?,?,?,?,?,?)')
                ->execute([(int)$ref['referrer_id'], 'Referral Reward', $reward, (float)$rr['balance'], 'completed', gen_reference('TXN'), 'Referral ' . $d['username'] . ' upgraded']);
            notify((int)$ref['referrer_id'], 'Referral reward earned', '$' . number_format($reward, 2) . ' for ' . $d['username'] . ' upgrading.');
            try { send_template($rr['email'], 'referral_earned', ['name' => $rr['username'], 'referred_user' => $d['username'], 'amount' => number_format($reward, 2)], (int)$ref['referrer_id']); } catch (Throwable $e) {}
        }
        notify((int)$d['user_id'], 'Upgrade active', 'Your ' . $tierName . ' is active until ' . date('M d, Y', strtotime($expiry)) . '.');
        log_activity((int)$d['user_id'], 'upgrade', $tierName . ' activated by admin ' . $admin['username']);
        $pdo->commit();
        try {
            $st = $pdo->prepare('SELECT email, username FROM users WHERE id=?');
            $st->execute([(int)$d['user_id']]);
            $uu = $st->fetch();
            send_template($uu['email'], 'deposit_approved', ['name' => $uu['username'], 'tier_name' => $tierName, 'reference' => $d['reference'], 'expiry' => date('M d, Y', strtotime($expiry))], (int)$d['user_id']);
            send_template($uu['email'], 'upgrade_success', ['name' => $uu['username'], 'tier_name' => $tierName, 'expiry' => date('M d, Y', strtotime($expiry))], (int)$d['user_id']);
        } catch (Throwable $e) {}
        admin_log((int)$admin['id'], 'deposit_approve', 'Approved deposit ' . $d['reference'] . ' (' . $d['username'] . ', ' . $tierName . ')', 'deposit', $depositId);
        return ['ok' => true, 'msg' => 'Deposit approved. ' . $tierName . ' active for ' . $d['username'] . '.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok' => false, 'msg' => 'Approval failed.'];
    }
}

function admin_reject_deposit(array $admin, int $depositId, string $reason): array {
    if (mb_strlen(trim($reason)) < 3) return ['ok' => false, 'msg' => 'A reason is required.'];
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT d.*, u.username, u.email FROM deposits d JOIN users u ON u.id=d.user_id WHERE d.id=? FOR UPDATE');
        $st->execute([$depositId]);
        $d = $st->fetch();
        if (!$d) { $pdo->rollBack(); return ['ok' => false, 'msg' => 'Deposit not found.']; }
        if ($d['status'] !== 'pending') { $pdo->rollBack(); return ['ok' => false, 'msg' => 'Deposit is already ' . $d['status'] . '.']; }
        $pdo->prepare('UPDATE deposits SET status="rejected", admin_note=? WHERE id=? AND status="pending"')->execute([mb_substr(trim($reason), 0, 255), $depositId]);
        notify((int)$d['user_id'], 'Deposit update', 'Your deposit ' . $d['reference'] . ' was not approved. Reason: ' . trim($reason));
        log_activity((int)$d['user_id'], 'deposit', 'Deposit ' . $d['reference'] . ' rejected by ' . $admin['username']);
        $pdo->commit();
        try { send_template($d['email'], 'deposit_rejected', ['name' => $d['username'], 'reference' => $d['reference'], 'reason' => trim($reason)], (int)$d['user_id']); } catch (Throwable $e) {}
        admin_log((int)$admin['id'], 'deposit_reject', 'Rejected deposit ' . $d['reference'] . ' (' . $d['username'] . '). Reason: ' . trim($reason), 'deposit', $depositId);
        return ['ok' => true, 'msg' => 'Deposit rejected. User notified.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok' => false, 'msg' => 'Rejection failed.'];
    }
}

function admin_withdrawal(array $admin, int $withdrawalId, string $to, string $note = ''): array {
    $allowed = [
        'pending' => ['approved', 'rejected'],
        'approved' => ['paid', 'rejected'],
    ];
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT w.*, u.username, u.email FROM withdrawals w JOIN users u ON u.id=w.user_id WHERE w.id=? FOR UPDATE');
        $st->execute([$withdrawalId]);
        $w = $st->fetch();
        if (!$w) { $pdo->rollBack(); return ['ok' => false, 'msg' => 'Withdrawal not found.']; }
        $from = $w['status'];
        if (!isset($allowed[$from]) || !in_array($to, $allowed[$from], true)) { $pdo->rollBack(); return ['ok' => false, 'msg' => "Cannot move withdrawal from $from to $to. Duplicate processing blocked."]; }
        if ($to === 'rejected' && mb_strlen(trim($note)) < 3) { $pdo->rollBack(); return ['ok' => false, 'msg' => 'A reason is required to reject.']; }
        $pdo->prepare('UPDATE withdrawals SET status=?, admin_note=? WHERE id=?')->execute([$to, mb_substr(trim($note), 0, 255), $withdrawalId]);
        if ($to === 'rejected') {
            // refund
            $st = $pdo->prepare('SELECT balance FROM users WHERE id=? FOR UPDATE');
            $st->execute([(int)$w['user_id']]);
            $bal = (float)$st->fetch()['balance'] + (float)$w['amount'];
            $pdo->prepare('UPDATE users SET balance=? WHERE id=?')->execute([$bal, (int)$w['user_id']]);
            $pdo->prepare('INSERT INTO transactions (user_id,type,amount,balance_after,status,reference,description) VALUES (?,?,?,?,?,?)')
                ->execute([(int)$w['user_id'], 'Withdrawal Reversal', (float)$w['amount'], $bal, 'completed', gen_reference('TXN'), 'Refund for rejected withdrawal ' . $w['reference']]);
        }
        notify((int)$w['user_id'], 'Withdrawal ' . $to, 'Your withdrawal ' . $w['reference'] . ' ($' . number_format((float)$w['amount'], 2) . ') is now ' . $to . ($note !== '' ? '. Note: ' . trim($note) : '.'));
        log_activity((int)$w['user_id'], 'withdraw', 'Withdrawal ' . $w['reference'] . " $from -> $to by " . $admin['username']);
        $pdo->commit();
        $slug = ['approved' => 'withdrawal_approved', 'rejected' => 'withdrawal_rejected', 'paid' => 'withdrawal_paid'][$to];
        try { send_template($w['email'], $slug, ['name' => $w['username'], 'amount' => number_format((float)$w['amount'], 2), 'method' => $w['method'], 'reference' => $w['reference'], 'reason' => trim($note)], (int)$w['user_id']); } catch (Throwable $e) {}
        admin_log((int)$admin['id'], 'withdrawal_' . $to, "$from -> $to withdrawal " . $w['reference'] . ' (' . $w['username'] . ', $' . number_format((float)$w['amount'], 2) . ')' . ($note !== '' ? '. Note: ' . trim($note) : ''), 'withdrawal', $withdrawalId);
        return ['ok' => true, 'msg' => "Withdrawal $to." . ($to === 'rejected' ? ' Amount refunded.' : '')];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok' => false, 'msg' => 'Update failed.'];
    }
}
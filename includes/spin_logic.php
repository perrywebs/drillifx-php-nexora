<?php
declare(strict_types=1);
// Server-authoritative spin engine.
// Order: auth -> eligibility -> predetermined outcome? -> weighted random -> record -> reward -> consume -> return.
require_once __DIR__ . '/settings.php';

function active_spin_outcomes(): array {
    try {
        $rows = db()->query('SELECT * FROM spin_outcomes WHERE active=1 ORDER BY sort, id')->fetchAll();
        if ($rows) return $rows;
    } catch (Throwable $e) {}
    // Fallback only when table is empty/unavailable: legacy config prizes
    $out = [];
    foreach ((array)(app_config('spin_prizes') ?? []) as $p) $out[] = ['id' => null, 'label' => '$' . number_format((float)$p['amount'], 2), 'amount' => (float)$p['amount'], 'weight' => (int)$p['weight']];
    return $out;
}

function pick_weighted_spin(array $outcomes): ?array {
    $total = 0;
    foreach ($outcomes as $o) $total += max(0, (int)$o['weight']);
    if ($total <= 0) return null;
    $roll = random_int(1, $total);
    foreach ($outcomes as $o) {
        $roll -= max(0, (int)$o['weight']);
        if ($roll <= 0) return $o;
    }
    return $outcomes[0];
}

function do_spin(int $userId): array {
    if (!cfg_flag('spin_enabled', true)) return ['ok' => false, 'msg' => 'Spin is currently disabled.'];
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT * FROM users WHERE id=? FOR UPDATE');
        $st->execute([$userId]);
        $u = $st->fetch();
        if (!$u || $u['status'] !== 'active') { $pdo->rollBack(); return ['ok' => false, 'msg' => 'Account not available.']; }
        $tierId = (int)$u['tier_id'];
        // Check tier daily limits from the unified tiers table
        $dailySpins = 1;
        $st = db()->prepare('SELECT * FROM tiers WHERE id = ? AND active = 1 LIMIT 1');
        $st->execute([$tierId]);
        $tier = $st->fetch();
        if ($tier) {
            $dailySpins = (int)($tier['daily_spin_allowance'] ?? 1);
        }
        if (!$tier) {
            // Fall back to tiers table tier expiry logic
            if (!empty($u['tier_expires_at']) && $tierId > 0 && strtotime($u['tier_expires_at']) < time()) {
                $tierId = 0;
                $pdo->prepare('UPDATE users SET tier_id=0, tier_expires_at=NULL WHERE id=?')->execute([$userId]);
            }
            $st = $pdo->prepare('SELECT * FROM tiers WHERE id=? LIMIT 1');
            $st->execute([$tierId]);
            $tier = $st->fetch();
            $limit = (int)($tier['daily_spins'] ?? 1);
        } else {
            $limit = $dailySpins;
        }
        $st = $pdo->prepare('SELECT COUNT(*) c FROM spins WHERE user_id=? AND DATE(created_at)=CURDATE()');
        $st->execute([$userId]);
        $done = (int)$st->fetch()['c'];
        if ($done >= $limit) { $pdo->rollBack(); return ['ok' => false, 'msg' => 'No spins left today. Come back at midnight UTC.']; }

        // 1) Predetermined outcome wins over random
        $st = $pdo->prepare('SELECT * FROM user_spin_outcomes WHERE user_id=? AND status="pending" ORDER BY id ASC LIMIT 1 FOR UPDATE');
        $st->execute([$userId]);
        $pre = $st->fetch();
        $source = 'random';
        if ($pre) {
            $reward = (float)$pre['amount'];
            $source = 'assigned';
            $st = $pdo->prepare('UPDATE user_spin_outcomes SET status="used", used_at=NOW() WHERE id=? AND status="pending"');
            $st->execute([(int)$pre['id']]);
            if ($st->rowCount() !== 1) { $pdo->rollBack(); return ['ok' => false, 'msg' => 'Spin could not be processed. Try again.']; }
        } else {
            $outcomes = active_spin_outcomes();
            $pick = pick_weighted_spin($outcomes);
            if (!$pick) { $pdo->rollBack(); return ['ok' => false, 'msg' => 'Spin is currently unavailable.']; }
            $reward = (float)$pick['amount'];
        }

        $st = $pdo->prepare('INSERT INTO spins (user_id, tier_id, reward) VALUES (?,?,?)');
        $st->execute([$userId, $tierId, $reward]);
        $st = $pdo->prepare('UPDATE users SET balance = balance + ?, total_earned = total_earned + ?, spin_earnings = spin_earnings + ? WHERE id=?');
        $st->execute([$reward, $reward, $reward, $userId]);
        $st = $pdo->prepare('SELECT balance FROM users WHERE id=?');
        $st->execute([$userId]);
        $bal = (float)$st->fetch()['balance'];
        $st = $pdo->prepare('INSERT INTO transactions (user_id,type,amount,balance_after,status,reference,description) VALUES (?,?,?,?,?,?,?)');
        $st->execute([$userId, 'Spin Reward', $reward, $bal, 'completed', gen_reference('TXN'), $source === 'assigned' ? 'Assigned spin reward' : 'Lucky wheel win']);
        log_activity($userId, 'spin', 'Spin won $' . number_format($reward, 2) . ($source === 'assigned' ? ' (assigned)' : ''));
        $pdo->commit();
        $left = $limit - $done - 1;
        return ['ok' => true, 'msg' => 'You won $' . number_format($reward, 2) . '!', 'reward' => $reward, 'spins_left' => max(0, $left), 'balance' => $bal];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok' => false, 'msg' => 'Unable to process spin. Try again.'];
    }
}
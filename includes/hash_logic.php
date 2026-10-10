<?php
declare(strict_types=1);
// Server-authoritative hash engine.
// Predetermined outcome first, else configured outcome (amount>0 wins) or tier per-hash rate.
require_once __DIR__ . '/settings.php';

function active_hash_outcomes(): array {
    try {
        $rows = db()->query('SELECT * FROM hash_outcomes WHERE active=1 ORDER BY sort, id')->fetchAll();
        if ($rows) return $rows;
    } catch (Throwable $e) {}
    return [];
}

function do_hash(int $userId): array {
    if (!cfg_flag('hash_enabled', true)) return ['ok' => false, 'msg' => 'Hashing is currently disabled.'];
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT * FROM users WHERE id=? FOR UPDATE');
        $st->execute([$userId]);
        $u = $st->fetch();
        if (!$u || $u['status'] !== 'active') { $pdo->rollBack(); return ['ok' => false, 'msg' => 'Account not available.']; }
        $tierId = (int)$u['tier_id'];
        // Check upgrade option daily limits first, fallback to tiers table
        $dailyHashes = 1;
        $perHash = 0.0000;
        $st = db()->prepare('SELECT * FROM tiers WHERE id = ? AND active = 1 LIMIT 1');
        $st->execute([$tierId]);
        $tier = $st->fetch();
        if ($tier) {
            $dailyHashes = (int)($tier['daily_hash_allowance'] ?? 1);
            $perHash = (float)($tier['per_hash'] ?? 0);
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
            $limit = (int)($tier['daily_hashes'] ?? 1);
            $perHash = (float)($tier['per_hash'] ?? 0);
        } else {
            $limit = $dailyHashes;
        }
        $st = $pdo->prepare('SELECT COUNT(*) c FROM hashes WHERE user_id=? AND DATE(created_at)=CURDATE()');
        $st->execute([$userId]);
        $done = (int)$st->fetch()['c'];
        if ($done >= $limit) { $pdo->rollBack(); return ['ok' => false, 'msg' => 'Daily hash limit reached. Upgrade for more hashes.']; }

        $st = $pdo->prepare('SELECT * FROM user_hash_outcomes WHERE user_id=? AND status="pending" ORDER BY id ASC LIMIT 1 FOR UPDATE');
        $st->execute([$userId]);
        $pre = $st->fetch();
        $source = 'tier';
        if ($pre) {
            $reward = (float)$pre['amount'];
            $source = 'assigned';
            $st = $pdo->prepare('UPDATE user_hash_outcomes SET status="used", used_at=NOW() WHERE id=? AND status="pending"');
            $st->execute([(int)$pre['id']]);
            if ($st->rowCount() !== 1) { $pdo->rollBack(); return ['ok' => false, 'msg' => 'Hash could not be processed. Try again.']; }
        } else {
            $outcomes = active_hash_outcomes();
            if ($outcomes) {
                $total = 0;
                foreach ($outcomes as $o) $total += max(0, (int)$o['weight']);
                if ($total <= 0) { $pdo->rollBack(); return ['ok' => false, 'msg' => 'Hashing is currently unavailable.']; }
                $roll = random_int(1, $total);
                $pick = $outcomes[0];
                foreach ($outcomes as $o) { $roll -= max(0, (int)$o['weight']); if ($roll <= 0) { $pick = $o; break; } }
                $reward = (float)$pick['amount'] > 0 ? (float)$pick['amount'] : $perHash;
                $source = (float)$pick['amount'] > 0 ? 'configured' : 'tier';
            } else {
                $reward = $perHash;
            }
        }

        $st = $pdo->prepare('INSERT INTO hashes (user_id, tier_id, reward) VALUES (?,?,?)');
        $st->execute([$userId, $tierId, $reward]);
        $st = $pdo->prepare('UPDATE users SET balance = balance + ?, total_earned = total_earned + ?, hash_earnings = hash_earnings + ? WHERE id=?');
        $st->execute([$reward, $reward, $reward, $userId]);
        $st = $pdo->prepare('SELECT balance FROM users WHERE id=?');
        $st->execute([$userId]);
        $bal = (float)$st->fetch()['balance'];
        $st = $pdo->prepare('INSERT INTO transactions (user_id,type,amount,balance_after,status,reference,description) VALUES (?,?,?,?,?,?,?)');
        $st->execute([$userId, 'Hash Reward', $reward, $bal, 'completed', gen_reference('TXN'), 'Hash #' . ($done + 1) . ' credited' . ($source === 'tier' ? '' : " ($source)")]);
        log_activity($userId, 'hash', 'Hash #' . ($done + 1) . ' earned $' . number_format($reward, 4));
        $pdo->commit();
        $left = $limit - $done - 1;
        return ['ok' => true, 'msg' => '+$' . number_format($reward, 4) . ' earned! (' . max(0, $left) . ' hashes left today)', 'reward' => $reward, 'hashes_left' => max(0, $left), 'balance' => $bal];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok' => false, 'msg' => 'Unable to process hash. Try again.'];
    }
}
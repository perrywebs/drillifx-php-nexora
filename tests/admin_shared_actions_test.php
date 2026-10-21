<?php
declare(strict_types=1);

/**
 * Tests for the shared admin actions in admin/includes/actions.php that are
 * used by pages other than /admin/user.php:
 *
 *   - admin_withdrawal()      (reject a withdrawal -> refund insert)
 *   - admin_reject_deposit()  (reject a deposit)
 *   - admin_approve_deposit() (approve a deposit -> tier + referral reward)
 *
 * These functions were part of the same fix (the transactions INSERT had 6
 * placeholders for 7 columns, so every refund/reward insert failed and the
 * whole operation rolled back).
 *
 *     php tests/admin_shared_actions_test.php
 *
 * Fixtures are created with SQL and removed again on exit; every assertion is
 * made against the real application behaviour over HTTP. User 26 (or whatever
 * TEST_USER_ID points at) is never touched by this script.
 */

// ---------------------------------------------------------------- config ----
$BASE = rtrim((string)(getenv('TEST_BASE_URL') ?: 'http://drillifx-php.test'), '/');
$ADMIN_USER = (string)(getenv('TEST_ADMIN_USER') ?: 'e2e_test_admin');
$ADMIN_PASS = (string)(getenv('TEST_ADMIN_PASS') ?: 'E2eLocalTest!2026');

$cfg = require __DIR__ . '/../config/database.php';
$pdo = new PDO(
    sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $cfg['host'], $cfg['port'], $cfg['db'], $cfg['charset']),
    $cfg['user'],
    $cfg['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$T_PASS = 0;
$T_FAIL = 0;

function check(string $name, bool $ok, string $detail = ''): void
{
    global $T_PASS, $T_FAIL;
    if ($ok) {
        $T_PASS++;
        echo "  PASS  $name\n";
    } else {
        $T_FAIL++;
        echo '  FAIL  ' . $name . ($detail !== '' ? "  -- $detail" : '') . "\n";
    }
}

function check_money(string $name, float $expected, $actual): void
{
    $ok = abs($expected - (float)$actual) < 0.0001;
    check($name, $ok, $ok ? '' : 'expected ' . number_format($expected, 2) . ' got ' . number_format((float)$actual, 2));
}

function row(string $sql, array $args = []): ?array
{
    global $pdo;
    $st = $pdo->prepare($sql);
    $st->execute($args);
    $r = $st->fetch();
    return $r ?: null;
}

function scalar(string $sql, array $args = [])
{
    global $pdo;
    $st = $pdo->prepare($sql);
    $st->execute($args);
    return $st->fetchColumn();
}

// ------------------------------------------------------------ http helpers ---
function parse_flashes(string $html): array
{
    preg_match_all('/text-sm bg-(red|green)-50[^>]*>([^<]*)<\/div>/', $html, $m, PREG_SET_ORDER);
    $out = [];
    foreach ($m as $hit) $out[] = ['type' => $hit[1] === 'green' ? 'success' : 'error', 'msg' => trim($hit[2])];
    return $out;
}

function flash_text(array $flashes): string
{
    return implode(' | ', array_map(fn($f) => $f['type'] . ': ' . $f['msg'], $flashes));
}

function flash_has(array $flashes, string $type, string $needle): bool
{
    foreach ($flashes as $f) if ($f['type'] === $type && stripos($f['msg'], $needle) !== false) return true;
    return false;
}

function http_req(string $jar, string $method, string $url, ?array $post = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post ?? []));
    }
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $hs = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    if (!is_string($raw)) return ['code' => $code, 'loc' => '', 'body' => '', 'flashes' => []];
    $headers = substr($raw, 0, $hs);
    $body = substr($raw, $hs);
    preg_match_all('/^Location:\s*(.+)$/mi', $headers, $m);
    return [
        'code' => $code,
        'loc' => isset($m[1][0]) ? trim($m[1][0]) : '',
        'body' => $body,
        'flashes' => parse_flashes($body),
    ];
}

function get_follow(string $jar, string $url, int $max = 5): array
{
    $flashes = [];
    $code = 0;
    $loc = $url;
    $body = '';
    for ($i = 0; $i <= $max && $loc !== ''; $i++) {
        $r = http_req($jar, 'GET', $loc);
        $code = $r['code'];
        $body = $r['body'];
        foreach ($r['flashes'] as $f) $flashes[] = $f;
        $loc = $r['loc'] !== '' ? (preg_match('#^https?://#i', $r['loc']) ? $r['loc'] : $BASE . $r['loc']) : '';
    }
    return ['code' => $code, 'body' => $body, 'flashes' => $flashes];
}

function post_follow(string $jar, string $url, array $post): array
{
    global $BASE;
    $p = http_req($jar, 'POST', $url, $post);
    $flashes = $p['flashes'];
    if ($p['loc'] !== '') {
        $g = get_follow($jar, $BASE . $p['loc'], 4);
        foreach ($g['flashes'] as $f) $flashes[] = $f;
        return ['post_code' => $p['code'], 'code' => $g['code'], 'body' => $g['body'], 'flashes' => $flashes];
    }
    return ['post_code' => $p['code'], 'code' => $p['code'], 'body' => $p['body'], 'flashes' => $flashes];
}

function csrf_from(string $html): ?string
{
    return preg_match('/name="csrf" value="([a-f0-9]+)"/', $html, $m) ? $m[1] : null;
}

// ---------------------------------------------------------------- fixture ----
$FIX = ['users' => [], 'deposits' => [], 'withdrawals' => []];
$CREATED_ADMIN = false;
$CLEANED = false;
$AUDIT_BASE = null;

function fixture_user(string $username, float $balance, ?int $referredBy = null): int
{
    global $pdo, $FIX;
    $pdo->prepare('INSERT INTO users (username,email,password_hash,referral_code,referred_by_id,balance,status) VALUES (?,?,?,?,?,?,?)')
        ->execute([
            $username,
            $username . '@local.test',
            password_hash('FixtureOnly!1', PASSWORD_DEFAULT),
            strtoupper(substr(md5($username), 0, 8)),
            $referredBy,
            $balance,
            'active',
        ]);
    $id = (int)$pdo->lastInsertId();
    $FIX['users'][] = $id;
    return $id;
}

function cleanup_actions(): void
{
    global $CLEANED, $FIX, $pdo, $CREATED_ADMIN, $AUDIT_BASE;
    if ($CLEANED) return;
    $CLEANED = true;
    if ($FIX['users']) {
        $in = implode(',', array_map('intval', $FIX['users']));
        foreach (['notifications', 'activity_log', 'transactions', 'email_logs', 'spins', 'hashes', 'withdrawals', 'deposits'] as $t) {
            try { $pdo->exec("DELETE FROM `$t` WHERE user_id IN ($in)"); } catch (Throwable $e) {}
        }
        try { $pdo->exec('DELETE FROM referrals WHERE referrer_id IN (' . $in . ') OR referred_id IN (' . $in . ')'); } catch (Throwable $e) {}
        $pdo->exec("DELETE FROM users WHERE id IN ($in)");
    }
    foreach ($FIX['deposits'] as $d) $pdo->prepare('DELETE FROM deposits WHERE id=?')->execute([$d]);
    foreach ($FIX['withdrawals'] as $w) $pdo->prepare('DELETE FROM withdrawals WHERE id=?')->execute([$w]);
    if ($CREATED_ADMIN) $pdo->prepare('DELETE FROM admins WHERE username=?')->execute(['e2e_test_admin']);
    // Audit rows this run wrote (login, deposit/withdrawal actions) - scoped by
    // the baseline so pre-existing log rows are never touched.
    if ($AUDIT_BASE !== null) {
        $pdo->prepare('DELETE FROM admin_audit_logs WHERE id > ?')->execute([$AUDIT_BASE]);
    }
}
register_shutdown_function('cleanup_actions');

// ================================================================ tests =====
echo "== Setup\n";
$AUDIT_BASE = (int)$pdo->query('SELECT COALESCE(MAX(id),0) FROM admin_audit_logs')->fetchColumn();
$st = $pdo->prepare('SELECT id FROM admins WHERE username=?');
$st->execute([$ADMIN_USER]);
$admin = $st->fetch();
if (!$admin) {
    $pdo->prepare('INSERT INTO admins (name,email,username,password_hash,role,status) VALUES (?,?,?,?,?,?)')
        ->execute(['E2E super_admin', $ADMIN_USER . '@local.test', $ADMIN_USER, password_hash($ADMIN_PASS, PASSWORD_DEFAULT), 'super_admin', 'active']);
    $CREATED_ADMIN = true;
    $adminId = (int)$pdo->lastInsertId();
} else {
    $adminId = (int)$admin['id'];
}

$jar = sys_get_temp_dir() . '/drillifx_e2e_actions.txt';
@unlink($jar);
$loginPage = http_req($jar, 'GET', $BASE . '/admin/login.php');
$csrf = csrf_from($loginPage['body']);
$lr = http_req($jar, 'POST', $BASE . '/admin/login.php', ['csrf' => $csrf, 'email' => $ADMIN_USER, 'password' => $ADMIN_PASS]);
check('admin can log in', $lr['code'] === 302 && strpos($lr['loc'], '/admin/index.php') !== false);

// fixtures --------------------------------------------------------------------
$referrerId = fixture_user('e2e_referrer_user', 0.0);
$depUserId = fixture_user('e2e_deposit_user', 0.0, $referrerId);
$wdUserId = fixture_user('e2e_withdraw_user', 100.0);
$pdo->prepare('INSERT INTO referrals (referrer_id,referred_id,reward,status) VALUES (?,?,0,"pending")')
    ->execute([$referrerId, $depUserId]);
$pdo->prepare('INSERT INTO deposits (user_id,tier_id,amount,status,reference,payment_method_name) VALUES (?,?,?,?,?,?)')
    ->execute([$depUserId, 1, 25.00, 'pending', 'E2EDEPREJ0001', 'Test method']);
$depRejectId = (int)$pdo->lastInsertId();
$FIX['deposits'][] = $depRejectId;
$pdo->prepare('INSERT INTO deposits (user_id,tier_id,amount,status,reference,payment_method_name) VALUES (?,?,?,?,?,?)')
    ->execute([$depUserId, 1, 50.00, 'pending', 'E2EDEPAPR0001', 'Test method']);
$depApproveId = (int)$pdo->lastInsertId();
$FIX['deposits'][] = $depApproveId;
$pdo->prepare('INSERT INTO withdrawals (user_id,amount,method,status,reference) VALUES (?,?,?,?,?)')
    ->execute([$wdUserId, 10.00, 'USDT', 'pending', 'E2EWDTEST0001']);
$wdId = (int)$pdo->lastInsertId();
$FIX['withdrawals'][] = $wdId;
echo "  fixtures: referrer #$referrerId, deposit user #$depUserId, withdraw user #$wdUserId, deposit #$depRejectId/#$depApproveId, withdrawal #$wdId\n";

// ------------------------------------------------- withdrawal reject + refund
echo "\n== Reject withdrawal (refund path)\n";
$g = get_follow($jar, $BASE . '/admin/withdrawals.php?status=pending');
$csrf = csrf_from($g['body']);
$r = post_follow($jar, $BASE . '/admin/withdrawals.php', [
    'csrf' => $csrf, 'id' => $wdId, 'to' => 'rejected', 'note' => 'E2E reject test', 'back_status' => 'pending',
]);
check('rejecting a pending withdrawal succeeds', flash_has($r['flashes'], 'success', 'Withdrawal rejected'), flash_text($r['flashes']));
check('withdrawal stored as rejected', 'rejected' === (string)row('SELECT status FROM withdrawals WHERE id=?', [$wdId])['status']);
check('admin note stored', 'E2E reject test' === (string)row('SELECT admin_note FROM withdrawals WHERE id=?', [$wdId])['admin_note']);
check_money('user balance refunded by exactly 10.00', 110.0, (float)row('SELECT balance FROM users WHERE id=?', [$wdUserId])['balance']);
$rev = row('SELECT * FROM transactions WHERE user_id=? AND type="Withdrawal Reversal" ORDER BY id DESC LIMIT 1', [$wdUserId]);
check('Withdrawal Reversal transaction row created', $rev !== null);
if ($rev) {
    check_money('refund transaction amount is 10.00', 10.0, (float)$rev['amount']);
    check_money('refund transaction balance_after is 110.00', 110.0, (float)$rev['balance_after']);
    check('refund transaction references the withdrawal', stripos((string)$rev['description'], 'E2EWDTEST0001') !== false, (string)$rev['description']);
}
check('user notified about the rejected withdrawal', (int)scalar('SELECT COUNT(*) FROM notifications WHERE user_id=? AND title LIKE "Withdrawal rejected%"', [$wdUserId]) >= 1);

// The list pages only render action forms while an item is pending, so the
// second submit reuses the token from the pending page: that is exactly what a
// double click / second tab does, and csrf_check accepts it because the token
// lives in the session.
$r = post_follow($jar, $BASE . '/admin/withdrawals.php', [
    'csrf' => $csrf, 'id' => $wdId, 'to' => 'rejected', 'note' => 'E2E reject test again', 'back_status' => 'rejected',
]);
check('processing the same withdrawal twice is blocked', flash_has($r['flashes'], 'error', 'Duplicate processing blocked'), flash_text($r['flashes']));
check_money('second attempt did not refund again', 110.0, (float)row('SELECT balance FROM users WHERE id=?', [$wdUserId])['balance']);
check('no second refund row', (int)scalar('SELECT COUNT(*) FROM transactions WHERE user_id=? AND type="Withdrawal Reversal"', [$wdUserId]) === 1);

// --------------------------------------------------------- deposit reject ----
echo "\n== Reject deposit\n";
$g = get_follow($jar, $BASE . '/admin/deposits.php?status=pending');
$csrf = csrf_from($g['body']);
$r = post_follow($jar, $BASE . '/admin/deposits.php', [
    'csrf' => $csrf, 'id' => $depRejectId, 'reject' => '1', 'reason' => 'E2E deposit reject test', 'back_status' => 'pending',
]);
check('rejecting a pending deposit succeeds', flash_has($r['flashes'], 'success', 'Deposit rejected'), flash_text($r['flashes']));
check('deposit stored as rejected', 'rejected' === (string)row('SELECT status FROM deposits WHERE id=?', [$depRejectId])['status']);
check('rejection reason stored on the deposit', 'E2E deposit reject test' === (string)row('SELECT admin_note FROM deposits WHERE id=?', [$depRejectId])['admin_note']);
check('user notified about the rejected deposit', (int)scalar('SELECT COUNT(*) FROM notifications WHERE user_id=? AND title="Deposit update"', [$depUserId]) >= 1);

// --------------------------------------------------- deposit approve + reward
echo "\n== Approve deposit (tier + referral reward)\n";
$reward = (float)(scalar("SELECT v FROM settings WHERE k='referral_reward'") ?: 10);
$refBefore = (float)row('SELECT balance FROM users WHERE id=?', [$referrerId])['balance'];
$g = get_follow($jar, $BASE . '/admin/deposits.php?status=pending');
$csrf = csrf_from($g['body']);
$r = post_follow($jar, $BASE . '/admin/deposits.php', [
    'csrf' => $csrf, 'id' => $depApproveId, 'approve' => '1', 'back_status' => 'pending',
]);
check('approving a pending deposit succeeds', flash_has($r['flashes'], 'success', 'Deposit approved'), flash_text($r['flashes']));
check('deposit stored as approved', 'approved' === (string)row('SELECT status FROM deposits WHERE id=?', [$depApproveId])['status']);
$tierNow = (string)row('SELECT tier_id FROM users WHERE id=?', [$depUserId])['tier_id'];
check('depositor tier assigned', '1' === $tierNow, "tier_id=$tierNow (deposit tier is 1)");
check('depositor tier expiry set', row('SELECT tier_expires_at FROM users WHERE id=?', [$depUserId])['tier_expires_at'] !== null);
check('referral marked as earned', 'earned' === (string)row('SELECT status FROM referrals WHERE referrer_id=? AND referred_id=?', [$referrerId, $depUserId])['status']);
check_money('referrer balance credited with the referral reward', $refBefore + $reward, (float)row('SELECT balance FROM users WHERE id=?', [$referrerId])['balance']);
$rr = row('SELECT * FROM transactions WHERE user_id=? AND type="Referral Reward" ORDER BY id DESC LIMIT 1', [$referrerId]);
check('Referral Reward transaction row created', $rr !== null);
if ($rr) {
    check_money('reward transaction amount matches the reward', $reward, (float)$rr['amount']);
    check_money('reward transaction balance_after matches the referrer balance', $refBefore + $reward, (float)$rr['balance_after']);
    check('reward transaction status completed', 'completed' === (string)$rr['status'], (string)$rr['status']);
}
check('referrer notified', (int)scalar('SELECT COUNT(*) FROM notifications WHERE user_id=? AND title="Referral reward earned"', [$referrerId]) >= 1);
check('depositor notified about the upgrade', (int)scalar('SELECT COUNT(*) FROM notifications WHERE user_id=? AND title="Upgrade active"', [$depUserId]) >= 1);

$r = post_follow($jar, $BASE . '/admin/deposits.php', [
    'csrf' => $csrf, 'id' => $depApproveId, 'approve' => '1', 'back_status' => 'approved',
]);
check('approving the same deposit twice is blocked', flash_has($r['flashes'], 'error', 'Duplicate processing blocked'), flash_text($r['flashes']));
check_money('second approval did not credit the reward again', $refBefore + $reward, (float)row('SELECT balance FROM users WHERE id=?', [$referrerId])['balance']);
check('only one Referral Reward row', (int)scalar('SELECT COUNT(*) FROM transactions WHERE user_id=? AND type="Referral Reward"', [$referrerId]) === 1);

// reject without a reason must be refused
$depReject2 = null;
$pdo->prepare('INSERT INTO deposits (user_id,tier_id,amount,status,reference,payment_method_name) VALUES (?,?,?,?,?,?)')
    ->execute([$depUserId, 1, 15.00, 'pending', 'E2EDEPNOR0001', 'Test method']);
$depReject2 = (int)$pdo->lastInsertId();
$FIX['deposits'][] = $depReject2;
$g = get_follow($jar, $BASE . '/admin/deposits.php?status=pending');
$csrf = csrf_from($g['body']);
$r = post_follow($jar, $BASE . '/admin/deposits.php', [
    'csrf' => $csrf, 'id' => $depReject2, 'reject' => '1', 'reason' => '', 'back_status' => 'pending',
]);
check('rejecting without a reason is refused', flash_has($r['flashes'], 'error', 'A reason is required'), flash_text($r['flashes']));
check('deposit still pending after the refused rejection', 'pending' === (string)row('SELECT status FROM deposits WHERE id=?', [$depReject2])['status']);

cleanup_actions();

echo "\n----------------------------------------\n";
echo "Passed: $T_PASS   Failed: $T_FAIL\n";
exit($T_FAIL > 0 ? 1 : 0);

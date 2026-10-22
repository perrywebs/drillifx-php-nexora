<?php
declare(strict_types=1);

/**
 * Functional tests for admin→user settings synchronization
 * (Naira rate, minimum withdrawal, tiers, referral flag, maintenance, branding).
 *
 *     php tests/settings_sync_test.php
 *
 * Optional environment variables:
 *   TEST_BASE_URL   base URL of the running app (default http://drillifx-php.test)
 *
 * Creates its own temporary admin + user accounts, changes settings through the
 * real admin UI over HTTP, verifies user-facing pages and server-side enforcement,
 * then restores every setting and deletes all temporary data.
 */

$BASE = rtrim((string)(getenv('TEST_BASE_URL') ?: 'http://drillifx-php.test'), '/');

$cfg = require __DIR__ . '/../config/database.php';
$pdo = new PDO(
    sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $cfg['host'], $cfg['port'], $cfg['db'], $cfg['charset']),
    $cfg['user'],
    $cfg['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

// --------------------------------------------------------------- helpers ---
$T_PASS = 0;
$T_FAIL = 0;

function section(string $name): void { echo "\n== $name\n"; }
function check(string $name, bool $ok, string $detail = ''): void
{
    global $T_PASS, $T_FAIL;
    if ($ok) { $T_PASS++; echo "  PASS  $name\n"; }
    else { $T_FAIL++; echo "  FAIL  $name" . ($detail !== '' ? "  -- $detail" : '') . "\n"; }
}
function check_eq(string $name, $expected, $actual): void
{
    $ok = (string)$expected === (string)$actual;
    check($name, $ok, $ok ? '' : 'expected [' . var_export($expected, true) . '] got [' . var_export($actual, true) . ']');
}
function check_money(string $name, float $expected, $actual): void
{
    $ok = abs($expected - (float)$actual) < 0.0001;
    check($name, $ok, $ok ? '' : 'expected ' . number_format($expected, 2) . ' got ' . number_format((float)$actual, 2));
}

function jar_new(): string { return sys_get_temp_dir() . '/drillifx_sync_' . bin2hex(random_bytes(6)) . '.txt'; }

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
    $err = curl_error($ch);
    curl_close($ch);
    if (!is_string($raw)) return ['code' => $code, 'loc' => '', 'body' => '', 'error' => $err];
    $headers = substr($raw, 0, $hs);
    $body = substr($raw, $hs);
    preg_match_all('/^Location:\s*(.+)$/mi', $headers, $m);
    return ['code' => $code, 'loc' => isset($m[1][0]) ? trim($m[1][0]) : '', 'body' => $body, 'error' => $err];
}

function abs_url(string $loc): string
{
    global $BASE;
    if ($loc === '') return '';
    if (preg_match('#^https?://#i', $loc)) return $loc;
    return $BASE . $loc;
}

function http_get_follow(string $jar, string $url, int $max = 5): array
{
    $code = 0; $body = ''; $loc = $url;
    for ($i = 0; $i <= $max && $loc !== ''; $i++) {
        $r = http_req($jar, 'GET', $loc);
        $code = $r['code']; $body = $r['body'];
        $loc = $r['loc'] !== '' ? abs_url($r['loc']) : '';
    }
    return ['code' => $code, 'body' => $body];
}

function csrf_of(string $html): ?string
{
    return preg_match('/name="csrf" value="([a-f0-9]+)"/', $html, $m) ? $m[1] : null;
}

function get_setting(string $k): ?string
{
    global $pdo;
    $st = $pdo->prepare('SELECT v FROM settings WHERE k=?');
    $st->execute([$k]);
    $v = $st->fetchColumn();
    return $v === false ? null : (string)$v;
}

function set_setting_direct(string $k, string $v): void
{
    global $pdo;
    $pdo->prepare('INSERT INTO settings (`k`,`v`) VALUES (?,?) ON DUPLICATE KEY UPDATE `v`=VALUES(`v`)')->execute([$k, $v]);
}

function scalar(string $sql, array $args = [])
{
    global $pdo;
    $st = $pdo->prepare($sql);
    $st->execute($args);
    return $st->fetchColumn();
}

/** Admin settings POST payload that preserves every current value except $changes. */
function settings_payload(array $changes): array
{
    $fields = ['site_name','site_title','site_description','support_email','contact_email','timezone','currency',
        'min_deposit','min_withdraw','fx_USD','fx_NGN','fx_GHS','referral_reward'];
    $flags = ['registration_enabled','referral_enabled','email_verification','mail_enabled','spin_enabled','hash_enabled','maintenance_mode'];
    $p = [];
    foreach ($fields as $f) $p[$f] = array_key_exists($f, $changes) ? (string)$changes[$f] : (string)(get_setting($f) ?? '');
    foreach ($flags as $f) {
        $on = array_key_exists($f, $changes) ? ((string)$changes[$f] === '1') : (get_setting($f) === '1');
        if ($on) $p[$f] = '1';
    }
    return $p;
}

function admin_login(string $jar, string $user, string $pass): array
{
    global $BASE;
    $g = http_req($jar, 'GET', $BASE . '/admin/login.php');
    $csrf = csrf_of($g['body']);
    if ($csrf === null) return ['ok' => false, 'csrf' => null];
    $r = http_req($jar, 'POST', $BASE . '/admin/login.php', ['csrf' => $csrf, 'email' => $user, 'password' => $pass]);
    return ['ok' => $r['code'] === 302 && strpos($r['loc'], '/admin/index.php') !== false, 'csrf' => $csrf];
}

function user_login(string $jar, string $email, string $pass): bool
{
    global $BASE;
    $g = http_req($jar, 'GET', $BASE . '/login.php');
    $csrf = csrf_of($g['body']);
    if ($csrf === null) return false;
    $r = http_req($jar, 'POST', $BASE . '/login.php', ['csrf' => $csrf, 'email' => $email, 'password' => $pass]);
    return $r['code'] === 302;
}

// ------------------------------------------------------------ snapshot ----
$SETTING_KEYS = ['fx_USD','fx_NGN','fx_GHS','min_withdraw','min_deposit','referral_reward','referral_enabled',
    'registration_enabled','email_verification','mail_enabled','spin_enabled','hash_enabled','maintenance_mode',
    'site_name','site_title','site_description','support_email','contact_email','timezone','currency'];
$SNAP = [];
foreach ($SETTING_KEYS as $k) $SNAP[$k] = get_setting($k);

$ADMIN_USER = 'sync_test_admin';
$ADMIN_PASS = 'SyncTest!2026';
$USER_EMAIL = 'sync_test_user_' . bin2hex(random_bytes(3)) . '@local.test';
$USER_NAME = 'syncusr' . bin2hex(random_bytes(3));
$USER_PASS = 'UserTest!2026';
$PIN = '1234';
$CREATED_USER_ID = 0;
$CREATED_ADMIN_ID = 0;
$CLEANED = false;

function cleanup(): void
{
    global $CLEANED, $SNAP, $pdo, $CREATED_USER_ID, $CREATED_ADMIN_ID;
    if ($CLEANED) return;
    $CLEANED = true;
    foreach ($SNAP as $k => $v) {
        if ($v === null) $pdo->exec("DELETE FROM settings WHERE `k`=" . $pdo->quote($k));
        else set_setting_direct($k, $v);
    }
    if ($CREATED_USER_ID > 0) {
        foreach (['notifications','activity_log','transactions','withdrawals','deposits','email_logs','email_verify_tokens','password_resets','hashes','spins','referrals'] as $t) {
            try { $pdo->prepare("DELETE FROM `$t` WHERE user_id=? OR referrer_id=? OR referred_id=?")->execute([$CREATED_USER_ID, $CREATED_USER_ID, $CREATED_USER_ID]); }
            catch (Throwable $e) { try { $pdo->prepare("DELETE FROM `$t` WHERE user_id=?")->execute([$CREATED_USER_ID]); } catch (Throwable $e2) {} }
        }
        try { $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$CREATED_USER_ID]); } catch (Throwable $e) {}
    }
    if ($CREATED_ADMIN_ID > 0) {
        try { $pdo->prepare('DELETE FROM admins WHERE id=?')->execute([$CREATED_ADMIN_ID]); } catch (Throwable $e) {}
    }
    try { $pdo->exec("DELETE FROM tiers WHERE name='e2e_sync_tier' OR name='e2e_hidden_tier'"); } catch (Throwable $e) {}
    echo "\n(cleanup: settings restored, temp rows removed)\n";
}
register_shutdown_function('cleanup');

try {
    // ------------------------------------------------------ temp accounts --
    section('Setup: temporary accounts');
    $pdo->prepare('INSERT INTO admins (name,email,username,password_hash,role,status) VALUES (?,?,?,?,?,?)')
        ->execute(['Sync Test', 'sync_admin@local.test', $ADMIN_USER, password_hash($ADMIN_PASS, PASSWORD_DEFAULT), 'super_admin', 'active']);
    $CREATED_ADMIN_ID = (int)$pdo->lastInsertId();
    check('temp super_admin created', $CREATED_ADMIN_ID > 0);

    $pdo->prepare('INSERT INTO users (username,email,password_hash,referral_code,balance,pin_hash,status,tier_id) VALUES (?,?,?,?,?,?,?,0)')
        ->execute([$USER_NAME, $USER_EMAIL, password_hash($USER_PASS, PASSWORD_DEFAULT), strtoupper(substr(bin2hex(random_bytes(5)),0,8)), 200.00, password_hash($PIN, PASSWORD_DEFAULT), 'active']);
    $CREATED_USER_ID = (int)$pdo->lastInsertId();
    check('temp user created with $200 balance + PIN', $CREATED_USER_ID > 0);

    $AJAR = jar_new();
    $UJAR = jar_new();
    check('admin login', admin_login($AJAR, $ADMIN_USER, $ADMIN_PASS)['ok']);
    check('user login', user_login($UJAR, $USER_EMAIL, $USER_PASS));

    // ------------------------------------- 1. admin settings: rate + min ---
    section('Admin General Settings: save NGN rate + min withdrawal over HTTP');
    $sg = http_get_follow($AJAR, $BASE . '/admin/settings.php');
    $acsrf = csrf_of($sg['body']);
    check('settings page loads with csrf', $sg['code'] === 200 && $acsrf !== null);
    check('min withdrawal field present', strpos($sg['body'], 'name="min_withdraw"') !== false);
    check('NGN rate field present', strpos($sg['body'], 'name="fx_NGN"') !== false);

    $r = http_req($AJAR, 'POST', $BASE . '/admin/settings.php', settings_payload(['fx_NGN' => '1505.5', 'min_withdraw' => '75']) + ['csrf' => $acsrf]);
    check('POST settings accepted (redirect)', $r['code'] === 302);
    check_eq('DB fx_NGN persisted', '1505.5', get_setting('fx_NGN'));
    check_eq('DB min_withdraw persisted', '75', get_setting('min_withdraw'));

    $sg = http_get_follow($AJAR, $BASE . '/admin/settings.php');
    check('reload shows NGN 1505.5', strpos($sg['body'], 'name="fx_NGN"') !== false && preg_match('/name="fx_NGN"[^>]*value="1505.5"/', $sg['body']) === 1);
    check('reload shows min 75', preg_match('/name="min_withdraw"[^>]*value="75"/', $sg['body']) === 1);

    // Invalid: zero rate rejected, DB unchanged
    $r = http_req($AJAR, 'POST', $BASE . '/admin/settings.php', settings_payload(['fx_NGN' => '0']) + ['csrf' => $acsrf]);
    $body = http_get_follow($AJAR, $BASE . '/admin/settings.php')['body'];
    check('zero NGN rate rejected with error', strpos($body, 'greater than zero') !== false || strpos($body, 'Invalid number') !== false);
    check_eq('fx_NGN unchanged after invalid save', '1505.5', get_setting('fx_NGN'));

    // ------------------------------- 2. user pages show admin-saved values --
    section('User pages reflect the new rate and minimum');
    $wd = http_get_follow($UJAR, $BASE . '/users/withdraw.php');
    check('withdraw page loads', $wd['code'] === 200);
    check('NGN option uses DB rate 1505.5000', strpos($wd['body'], 'value="1505.5000" data-code="NGN"') !== false);
    check('no hardcoded 1600.0000 left', strpos($wd['body'], '1600.0000') === false);
    check('header shows dynamic min $75.00', strpos($wd['body'], 'Min. Withdrawal') !== false && strpos($wd['body'], '$75.00') !== false);
    check('amount input min=75', preg_match('/name="amount"[^>]*min="75"/', $wd['body']) === 1 || preg_match('/min="75"[^>]*name="amount"/', $wd['body']) === 1 || (strpos($wd['body'], 'min="75"') !== false));
    check('info bullet shows $75.00', strpos($wd['body'], 'Minimum withdrawal amount is $75.00') !== false);
    check('currency select posts currency field', strpos($wd['body'], 'name="currency"') !== false);

    $sup = http_get_follow($UJAR, $BASE . '/users/support.php');
    check('support FAQ shows dynamic min $75.00', strpos($sup['body'], 'minimum withdrawal amount is $75.00') !== false);
    $rr = get_setting('referral_reward') ?? '10';
    check('support FAQ shows dynamic referral bonus', strpos($sup['body'], '$' . number_format((float)$rr, 2) . ' bonus') !== false);

    $ref = http_get_follow($UJAR, $BASE . '/users/referral.php');
    check('referral page shows dynamic reward', strpos($ref['body'], 'Earn $' . number_format((float)$rr, 2) . ' per referral') !== false);
    check('referral share links not hardcoded to drillifyx.org', strpos($ref['body'], 'drillifyx.org') === false);

    // ---------------------------------- 3. server-side min enforcement -----
    section('Withdrawal enforcement: minimum + rate (ajax endpoint)');
    $csrf = csrf_of($wd['body']);
    check('user csrf available', $csrf !== null);
    $wdCount = fn() => (int)scalar('SELECT COUNT(*) FROM withdrawals WHERE user_id=?', [$CREATED_USER_ID]);
    $bal = fn() => (float)scalar('SELECT balance FROM users WHERE id=?', [$CREATED_USER_ID]);

    $r = http_req($UJAR, 'POST', $BASE . '/ajax/withdraw.php', [
        'csrf' => $csrf, 'amount' => '74.99', 'payment_method' => 'USDT', 'currency' => 'USD',
        'withdrawal_pin' => $PIN, 'wallet_network' => 'TRC20 (Tron)', 'wallet_address' => 'TTestWalletAddress0001234567',
    ]);
    $j = json_decode($r['body'], true) ?: [];
    check('below minimum rejected (74.99 < 75)', ($j['success'] ?? false) === false && stripos($j['message'] ?? '', 'Minimum withdrawal is $75.00') !== false, $r['body']);
    check('no withdrawal row created', $wdCount() === 0);
    check_money('balance untouched after rejection', 200.0, $bal());

    foreach ([['amount' => '0', 'why' => 'zero'], ['amount' => '-5', 'why' => 'negative'], ['amount' => 'abc', 'why' => 'non-numeric'], ['amount' => '', 'why' => 'empty']] as $bad) {
        $r = http_req($UJAR, 'POST', $BASE . '/ajax/withdraw.php', [
            'csrf' => $csrf, 'amount' => $bad['amount'], 'payment_method' => 'USDT', 'currency' => 'USD',
            'withdrawal_pin' => $PIN, 'wallet_network' => 'TRC20 (Tron)', 'wallet_address' => 'TTestWalletAddress0001234567',
        ]);
        $j = json_decode($r['body'], true) ?: [];
        check($bad['why'] . ' amount rejected', ($j['success'] ?? false) === false);
    }
    check('still no rows after invalid attempts', $wdCount() === 0);
    check_money('balance still untouched', 200.0, $bal());

    // Exact minimum passes
    $r = http_req($UJAR, 'POST', $BASE . '/ajax/withdraw.php', [
        'csrf' => $csrf, 'amount' => '75', 'payment_method' => 'USDT', 'currency' => 'USD',
        'withdrawal_pin' => $PIN, 'wallet_network' => 'TRC20 (Tron)', 'wallet_address' => 'TTestWalletAddress0001234567',
    ]);
    $j = json_decode($r['body'], true) ?: [];
    check('exact minimum accepted', ($j['success'] ?? false) === true, $r['body']);
    check('one withdrawal row', $wdCount() === 1);
    check_money('balance debited by 75', 125.0, $bal());
    $row = $pdo->prepare('SELECT * FROM withdrawals WHERE user_id=? ORDER BY id DESC LIMIT 1'); $row->execute([$CREATED_USER_ID]); $row = $row->fetch();
    check_eq('row currency USD', 'USD', $row['currency_code']);
    check_money('row fx_rate 1', 1.0, $row['fx_rate']);

    // NGN above minimum: conversion uses the admin-saved rate server-side
    $r = http_req($UJAR, 'POST', $BASE . '/ajax/withdraw.php', [
        'csrf' => $csrf, 'amount' => '80', 'payment_method' => 'USDT', 'currency' => 'NGN',
        'withdrawal_pin' => $PIN, 'wallet_network' => 'TRC20 (Tron)', 'wallet_address' => 'TTestWalletAddress0001234567',
    ]);
    $j = json_decode($r['body'], true) ?: [];
    check('NGN withdrawal accepted', ($j['success'] ?? false) === true, $r['body']);
    $row = $pdo->prepare('SELECT * FROM withdrawals WHERE user_id=? ORDER BY id DESC LIMIT 1'); $row->execute([$CREATED_USER_ID]); $row = $row->fetch();
    check_eq('row currency NGN', 'NGN', $row['currency_code']);
    check_money('row fx_rate = admin rate 1505.5', 1505.5, $row['fx_rate']);
    check_money('row local_amount = 80 * 1505.5', 80 * 1505.5, $row['local_amount']);
    check_money('balance debited by 80 (USD side)', 45.0, $bal());

    // Insufficient balance still enforced
    $r = http_req($UJAR, 'POST', $BASE . '/ajax/withdraw.php', [
        'csrf' => $csrf, 'amount' => '100', 'payment_method' => 'USDT', 'currency' => 'USD',
        'withdrawal_pin' => $PIN, 'wallet_network' => 'TRC20 (Tron)', 'wallet_address' => 'TTestWalletAddress0001234567',
    ]);
    $j = json_decode($r['body'], true) ?: [];
    check('insufficient balance rejected', ($j['success'] ?? false) === false && stripos($j['message'] ?? '', 'Insufficient balance') !== false);

    // Wrong PIN (amount passes the minimum so the PIN check is reached)
    $r = http_req($UJAR, 'POST', $BASE . '/ajax/withdraw.php', [
        'csrf' => $csrf, 'amount' => '75', 'payment_method' => 'USDT', 'currency' => 'USD',
        'withdrawal_pin' => '9999', 'wallet_network' => 'TRC20 (Tron)', 'wallet_address' => 'TTestWalletAddress0001234567',
    ]);
    $j = json_decode($r['body'], true) ?: [];
    check('wrong PIN rejected', ($j['success'] ?? false) === false && stripos($j['message'] ?? '', 'Incorrect') !== false);
    check('wrong PIN created no row', $wdCount() === 2);

    // Non-JS fallback endpoint (users/withdraw.php POST, no X-Requested-With)
    $r = http_req($UJAR, 'POST', $BASE . '/users/withdraw.php', [
        'csrf' => $csrf, 'amount' => '10', 'payment_method' => 'USDT', 'currency' => 'USD',
        'withdrawal_pin' => $PIN, 'wallet_network' => 'TRC20 (Tron)', 'wallet_address' => 'TTestWalletAddress0001234567',
    ]);
    check('fallback POST below minimum redirected (PRG)', $r['code'] === 302);
    check('fallback endpoint created no row', $wdCount() === 2);
    check_money('balance unchanged after fallback reject', 45.0, $bal());

    // --------------------------- 4. change the minimum; new value applies --
    section('Changing min withdrawal takes effect immediately');
    $r = http_req($AJAR, 'POST', $BASE . '/admin/settings.php', settings_payload(['min_withdraw' => '120']) + ['csrf' => $acsrf]);
    check_eq('DB min_withdraw now 120', '120', get_setting('min_withdraw'));
    $wd = http_get_follow($UJAR, $BASE . '/users/withdraw.php');
    check('withdraw page now shows $120.00', strpos($wd['body'], '$120.00') !== false);
    $csrf = csrf_of($wd['body']);
    $r = http_req($UJAR, 'POST', $BASE . '/ajax/withdraw.php', [
        'csrf' => $csrf, 'amount' => '45', 'payment_method' => 'USDT', 'currency' => 'USD',
        'withdrawal_pin' => $PIN, 'wallet_network' => 'TRC20 (Tron)', 'wallet_address' => 'TTestWalletAddress0001234567',
    ]);
    $j = json_decode($r['body'], true) ?: [];
    check('45 now rejected by new minimum 120', ($j['success'] ?? false) === false && stripos($j['message'] ?? '', '$120.00') !== false);
    check('no extra row', $wdCount() === 2);

    // ------------------------------------------- 5. tier limits sync -------
    section('Admin tier edits write the enforced allowance columns');
    $tg = http_get_follow($AJAR, $BASE . '/admin/tiers.php');
    $tcsrf = csrf_of($tg['body']);
    $r = http_req($AJAR, 'POST', $BASE . '/admin/tiers.php?action=add', [
        'csrf' => $tcsrf, 'tier_name' => 'e2e_sync_tier', 'display_name' => 'E2E Sync', 'display_rating' => '⭐⭐⭐⭐⭐',
        'daily_hashes' => '5', 'daily_spins' => '4', 'price' => '10', 'duration_days' => '30',
        'referral_requirement' => '0', 'requirement_type' => 'fixed',
    ]);
    $tid = (int)scalar('SELECT id FROM tiers WHERE name=?', ['e2e_sync_tier']);
    check('tier created', $tid > 0);
    if ($tid > 0) {
        $t = $pdo->query('SELECT * FROM tiers WHERE id=' . $tid)->fetch();
        check_eq('legacy daily_hashes=5', 5, (int)$t['daily_hashes']);
        check_eq('enforced daily_hash_allowance=5', 5, (int)$t['daily_hash_allowance']);
        check_eq('legacy daily_spins=4', 4, (int)$t['daily_spins']);
        check_eq('enforced daily_spin_allowance=4', 4, (int)$t['daily_spin_allowance']);
        $r = http_req($AJAR, 'POST', $BASE . '/admin/tiers.php?action=edit&tier_id=' . $tid, [
            'csrf' => $tcsrf, 'tier_id' => (string)$tid, 'display_name' => 'E2E Sync', 'display_rating' => '⭐⭐⭐⭐⭐',
            'daily_hashes' => '7', 'daily_spins' => '6', 'price' => '10', 'duration_days' => '30',
            'referral_requirement' => '0', 'requirement_type' => 'fixed',
        ]);
        $t = $pdo->query('SELECT * FROM tiers WHERE id=' . $tid)->fetch();
        check_eq('edit syncs allowance hashes=7', 7, (int)$t['daily_hash_allowance']);
        check_eq('edit syncs allowance spins=6', 6, (int)$t['daily_spin_allowance']);
    }

    // -------------------------------------- 6. referral_enabled enforced ---
    section('referral_enabled flag is enforced');
    set_setting_direct('referral_enabled', '0');
    $refCode = (string)scalar('SELECT referral_code FROM users WHERE id=?', [$CREATED_USER_ID]);
    $newEmail = 'sync_ref_off_' . bin2hex(random_bytes(3)) . '@local.test';
    $rjar = jar_new();
    $rg = http_req($rjar, 'GET', $BASE . '/register.php');
    $rcsrf = csrf_of($rg['body']);
    check('registration page reachable', $rg['code'] === 200 && $rcsrf !== null);
    $r = http_req($rjar, 'POST', $BASE . '/register.php', [
        'csrf' => $rcsrf, 'register' => '1', 'username' => 'syncrefoff' . bin2hex(random_bytes(3)),
        'email' => $newEmail, 'password' => 'Test1234!', 'confirm_password' => 'Test1234!', 'referral_code' => $refCode,
    ]);
    $newUid = (int)scalar('SELECT id FROM users WHERE email=?', [$newEmail]);
    check('account created while referrals disabled', $newUid > 0 && $r['code'] === 302);
    check_eq('referred_by_id NOT set', '1', (string)scalar('SELECT IF(referred_by_id IS NULL,1,0) FROM users WHERE id=?', [$newUid]));
    check_eq('no referrals row', 0, (int)scalar('SELECT COUNT(*) FROM referrals WHERE referred_id=?', [$newUid]));
    if ($newUid > 0) { $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$newUid]); }

    set_setting_direct('referral_enabled', '1');
    $jar2 = jar_new();
    $rg = http_req($jar2, 'GET', $BASE . '/register.php');
    $rcsrf2 = csrf_of($rg['body']);
    $badEmail = 'sync_ref_bad2_' . bin2hex(random_bytes(3)) . '@local.test';
    $r = http_req($jar2, 'POST', $BASE . '/register.php', [
        'csrf' => $rcsrf2, 'register' => '1', 'username' => 'syncrefbad2' . bin2hex(random_bytes(3)),
        'email' => $badEmail, 'password' => 'Test1234!', 'confirm_password' => 'Test1234!', 'referral_code' => 'NOSUCH99',
    ]);
    $body = http_get_follow($jar2, abs_url($r['loc']) ?: $BASE . '/register.php')['body'];
    check('invalid referral code rejected when enabled', strpos($body, 'Invalid referral code') !== false);
    check_eq('no user created for invalid code', 0, (int)scalar('SELECT COUNT(*) FROM users WHERE email=?', [$badEmail]));

    // ------------------------------------------- 7. maintenance gate -------
    section('Maintenance mode gates public pages, keeps admin access');
    set_setting_direct('maintenance_mode', '1');
    $gjar = jar_new();
    $r = http_req($gjar, 'GET', $BASE . '/index.php');
    check('guest index redirects to maintenance', $r['code'] === 302 && strpos($r['loc'], '/maintenance.php') !== false, $r['code'] . ' ' . $r['loc']);
    $m = http_get_follow($gjar, $BASE . '/maintenance.php');
    check('maintenance page renders', $m['code'] === 200 && stripos($m['body'], 'Under maintenance') !== false);
    $r = http_req($gjar, 'GET', $BASE . '/login.php');
    check('guest login gated', $r['code'] === 302 && strpos($r['loc'], '/maintenance.php') !== false);
    $r = http_req($gjar, 'GET', $BASE . '/register.php');
    check('guest register gated', $r['code'] === 302 && strpos($r['loc'], '/maintenance.php') !== false);
    $r = http_req($AJAR, 'GET', $BASE . '/index.php');
    check('logged-in admin passes the gate', $r['code'] === 200);
    $r = http_req(jar_new(), 'GET', $BASE . '/admin/login.php');
    check('admin login page stays reachable for guests', $r['code'] === 200);
    $r = http_req($UJAR, 'GET', $BASE . '/users/dashboard.php');
    check('user dashboard held on maintenance notice', $r['code'] === 302 && strpos($r['loc'], '/maintenance.php') !== false);
    set_setting_direct('maintenance_mode', '0');
    $r = http_req(jar_new(), 'GET', $BASE . '/index.php');
    check('guest index restored after disabling', $r['code'] === 200);

    // --------------------------------------- 8. site name reaches pages ----
    section('Site name setting reaches public pages');
    $r = http_req($AJAR, 'POST', $BASE . '/admin/settings.php', settings_payload(['site_name' => 'E2E Sync Brand']) + ['csrf' => $acsrf]);
    $idx = http_get_follow(jar_new(), $BASE . '/index.php');
    check('index footer shows new site name', strpos($idx['body'], 'E2E Sync Brand. All rights reserved.') !== false);
    $lg = http_get_follow(jar_new(), $BASE . '/login.php');
    check('login page shows new site name', strpos($lg['body'], 'E2E Sync Brand') !== false);

    // ---------------------------------- 9. inactive tiers not purchasable --
    section('Deactivated tier hidden from upgrade/deposit');
    $pdo->prepare('INSERT INTO tiers (id,name,display_name,display_rating,daily_hash_allowance,daily_spin_allowance,active,price,duration_days,daily_hashes,daily_spins,referral_requirement,requirement_type) VALUES (90,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute(['e2e_hidden_tier', 'HiddenSync', '⭐⭐', 1, 1, 0, 5.00, 30, 1, 1, 0, 'fixed']);
    $hid = (int)scalar('SELECT id FROM tiers WHERE name=?', ['e2e_hidden_tier']);
    check('inactive test tier created', $hid > 0);
    $up = http_get_follow($UJAR, $BASE . '/users/upgrade.php');
    check('inactive tier hidden on upgrade page', strpos($up['body'], 'HiddenSync') === false);
    $dp = http_get_follow($UJAR, $BASE . '/users/deposit.php');
    check('inactive tier hidden on deposit page', strpos($dp['body'], 'HiddenSync') === false);
    // activate it, confirm it appears, then deactivate again and delete
    http_req($AJAR, 'POST', $BASE . '/admin/tiers.php?action=activate&tier_id=' . $hid, ['csrf' => $tcsrf]);
    $up = http_get_follow($UJAR, $BASE . '/users/upgrade.php');
    check('activated tier appears on upgrade page', strpos($up['body'], 'HiddenSync') !== false);

    echo "\n========================================\n";
    echo "PASSED: $T_PASS   FAILED: $T_FAIL\n";
    exit($T_FAIL > 0 ? 1 : 0);
} catch (Throwable $e) {
    echo "\nFATAL: " . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n";
    exit(2);
}

<?php
declare(strict_types=1);

/**
 * End-to-end tests for the admin user-management page (/admin/user.php).
 *
 *     php tests/admin_user_e2e_test.php
 *
 * Optional environment variables:
 *   TEST_BASE_URL     base URL of the running app (default http://drillifx-php.test)
 *   TEST_USER_ID      user that is updated during the tests (default 26)
 *   TEST_ADMIN_USER   reuse an existing super admin instead of a temporary one
 *   TEST_ADMIN_PASS   password for TEST_ADMIN_USER
 *   TEST_NGINX_LOG    server error log to check for the error_log() entries
 *
 * Every action is performed with real HTTP requests against the running app,
 * the database is inspected after each step, and the test user's original
 * balance, status and tier are restored before the script exits. Temporary
 * admin accounts (super_admin / support / finance) are created for the run and
 * deleted again on exit.
 */

// ---------------------------------------------------------------- config ----
$BASE = rtrim((string)(getenv('TEST_BASE_URL') ?: 'http://drillifx-php.test'), '/');
$TUID = (int)(getenv('TEST_USER_ID') ?: 26);
$ADMIN_USER = (string)(getenv('TEST_ADMIN_USER') ?: 'e2e_test_admin');
$ADMIN_PASS = (string)(getenv('TEST_ADMIN_PASS') ?: 'E2eLocalTest!2026');
$SUPPORT_USER = 'e2e_support_admin';
$FINANCE_USER = 'e2e_finance_admin';
$ROLE_PASS = 'E2eLocalTest!2026';
$CREATED_ADMINS = [];

$cfg = require __DIR__ . '/../config/database.php';
$pdo = new PDO(
    sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $cfg['host'], $cfg['port'], $cfg['db'], $cfg['charset']),
    $cfg['user'],
    $cfg['pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

// ------------------------------------------------------- test framework ----
$T_PASS = 0;
$T_FAIL = 0;

function section(string $name): void
{
    echo "\n== $name\n";
}

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

// ---------------------------------------------------------- http helpers ----
function jar_for(string $who): string
{
    return sys_get_temp_dir() . '/drillifx_e2e_' . md5($who) . '.txt';
}

function parse_flashes(string $html): array
{
    preg_match_all('/text-sm bg-(red|green)-50[^>]*>([^<]*)<\/div>/', $html, $m, PREG_SET_ORDER);
    $out = [];
    foreach ($m as $hit) {
        $out[] = ['type' => $hit[1] === 'green' ? 'success' : 'error', 'msg' => trim($hit[2])];
    }
    return $out;
}

function flash_text(array $flashes): string
{
    return implode(' | ', array_map(fn($f) => $f['type'] . ': ' . $f['msg'], $flashes));
}

function flash_has(array $flashes, string $type, string $needle): bool
{
    foreach ($flashes as $f) {
        if ($f['type'] === $type && stripos($f['msg'], $needle) !== false) return true;
    }
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
    $err = curl_error($ch);
    curl_close($ch);
    if (!is_string($raw)) {
        return ['code' => $code, 'loc' => '', 'body' => '', 'flashes' => [], 'error' => $err];
    }
    $headers = substr($raw, 0, $hs);
    $body = substr($raw, $hs);
    preg_match_all('/^Location:\s*(.+)$/mi', $headers, $m);
    $loc = isset($m[1][0]) ? trim($m[1][0]) : '';
    return ['code' => $code, 'loc' => $loc, 'body' => $body, 'flashes' => parse_flashes($body), 'error' => $err];
}

function abs_url(string $loc): string
{
    global $BASE;
    if ($loc === '') return '';
    if (preg_match('#^https?://#i', $loc)) return $loc;
    return $BASE . $loc;
}

/** GET $url following redirects the way a browser does; returns final body + every flash seen. */
function http_get_follow(string $jar, string $url, int $max = 5): array
{
    $flashes = [];
    $code = 0;
    $body = '';
    $loc = $url;
    for ($i = 0; $i <= $max && $loc !== ''; $i++) {
        $r = http_req($jar, 'GET', $loc);
        $code = $r['code'];
        $body = $r['body'];
        foreach ($r['flashes'] as $f) $flashes[] = $f;
        $loc = $r['loc'] !== '' ? abs_url($r['loc']) : '';
    }
    return ['code' => $code, 'body' => $body, 'flashes' => $flashes];
}

/** POST $url, then follow the redirect chain (PRG) and collect the flashes. */
function http_post_follow(string $jar, string $url, array $post): array
{
    $p = http_req($jar, 'POST', $url, $post);
    $flashes = $p['flashes'];
    $code = $p['code'];
    $body = $p['body'];
    if ($p['loc'] !== '') {
        $g = http_get_follow($jar, abs_url($p['loc']), 4);
        foreach ($g['flashes'] as $f) $flashes[] = $f;
        $code = $g['code'];
        $body = $g['body'];
    }
    return ['code' => $code, 'post_code' => $p['code'], 'post_loc' => $p['loc'], 'body' => $body, 'flashes' => $flashes];
}

// ----------------------------------------------------------- page helpers ---
function forms_of(string $html): array
{
    preg_match_all('#<form method="POST".*?</form>#s', $html, $m);
    $forms = $m[0];
    $pick = function (string $needle) use ($forms): ?string {
        foreach ($forms as $f) if (strpos($f, $needle) !== false) return $f;
        return null;
    };
    return [
        'csrf' => preg_match('/name="csrf" value="([a-f0-9]+)"/', $html, $c) ? $c[1] : null,
        'credit' => $pick('name="direction" value="credit"'),
        'debit' => $pick('name="direction" value="debit"'),
        'tier' => $pick('name="do_tier"'),
        'status' => $pick('name="do_status"'),
    ];
}

function field_of(?string $form, string $name): ?string
{
    if ($form === null) return null;
    return preg_match('/name="' . preg_quote($name, '/') . '" value="([^"]*)"/', $form, $m) ? $m[1] : null;
}

function load_page(string $jar, int $id): array
{
    global $BASE;
    $g = http_get_follow($jar, $BASE . '/admin/user.php?id=' . $id);
    $f = forms_of($g['body']);
    $f['code'] = $g['code'];
    $f['body'] = $g['body'];
    $f['flashes'] = $g['flashes'];
    return $f;
}

function page_balance(string $html): ?float
{
    if (preg_match('/Balance: <strong class="text-lg">\$([0-9,\.]+)</', $html, $m)) {
        return (float)str_replace(',', '', $m[1]);
    }
    return null;
}

// --------------------------------------------------------------- db state ---
function user_row(int $id): ?array
{
    global $pdo;
    $st = $pdo->prepare('SELECT id, username, balance, total_earned, status, tier_id FROM users WHERE id=?');
    $st->execute([$id]);
    $r = $st->fetch();
    return $r ?: null;
}

function count_rows(string $sql, array $args = []): int
{
    global $pdo;
    $st = $pdo->prepare($sql);
    $st->execute($args);
    return (int)$st->fetchColumn();
}

function last_tx(int $uid, string $type): ?array
{
    global $pdo;
    $st = $pdo->prepare('SELECT * FROM transactions WHERE user_id=? AND type=? ORDER BY id DESC LIMIT 1');
    $st->execute([$uid, $type]);
    $r = $st->fetch();
    return $r ?: null;
}

/** Highest auto-increment value seen before the run, so cleanup can delete only what this run wrote. */
function baseline_max_id(string $table, ?string $col = null, $val = null): int
{
    global $pdo;
    $sql = 'SELECT COALESCE(MAX(id),0) FROM `' . $table . '`' . ($col !== null ? " WHERE `$col` = ?" : '');
    $st = $pdo->prepare($sql);
    $st->execute($col !== null ? [$val] : []);
    return (int)$st->fetchColumn();
}

// --------------------------------------------------------------- accounts ---
function ensure_admin(PDO $pdo, string $username, string $role, string $password): array
{
    global $CREATED_ADMINS;
    $st = $pdo->prepare('SELECT id FROM admins WHERE username=?');
    $st->execute([$username]);
    $row = $st->fetch();
    if ($row) return ['id' => (int)$row['id'], 'created' => false];
    $pdo->prepare('INSERT INTO admins (name,email,username,password_hash,role,status) VALUES (?,?,?,?,?,?)')
        ->execute(['E2E ' . $role, $username . '@local.test', $username, password_hash($password, PASSWORD_DEFAULT), $role, 'active']);
    $id = (int)$pdo->lastInsertId();
    $CREATED_ADMINS[] = $id;
    return ['id' => $id, 'created' => true];
}

/**
 * Log in over HTTP. Returns the CSRF token of the (still same) session - the
 * token is created on the login form and survives admin_login(), which only
 * regenerates the session id. Needed for roles whose pages render no forms.
 */
function admin_login(string $jar, string $user, string $pass): array
{
    global $BASE;
    $g = http_req($jar, 'GET', $BASE . '/admin/login.php');
    $csrf = forms_of($g['body'])['csrf'];
    if ($csrf === null) return ['ok' => false, 'csrf' => null];
    $r = http_req($jar, 'POST', $BASE . '/admin/login.php', ['csrf' => $csrf, 'email' => $user, 'password' => $pass]);
    $ok = $r['code'] === 302 && strpos($r['loc'], '/admin/index.php') !== false;
    return ['ok' => $ok, 'csrf' => $csrf];
}

// ---------------------------------------------------------------- cleanup ---
$CLEANED = false;
$SNAPSHOT = null;
$BASELINE = null;
$ADMIN_JAR = '';

function cleanup(): void
{
    global $CLEANED, $SNAPSHOT, $BASELINE, $TUID, $BASE, $ADMIN_JAR, $CREATED_ADMINS, $pdo;
    if ($CLEANED) return;
    $CLEANED = true;
    if (!$SNAPSHOT) return;
    $notes = [];

    // 1. Balance back to the pre-test value - through the application itself.
    $u = user_row($TUID);
    if ($u && abs((float)$u['balance'] - $SNAPSHOT['balance']) > 0.0001) {
        $diff = round($SNAPSHOT['balance'] - (float)$u['balance'], 2);
        if ($diff != 0.0 && $ADMIN_JAR !== '' && is_file($ADMIN_JAR)) {
            $p = load_page($ADMIN_JAR, $TUID);
            if ($p['csrf'] && ($diff > 0 ? $p['credit'] : $p['debit'])) {
                $form = $diff > 0 ? $p['credit'] : $p['debit'];
                http_post_follow($ADMIN_JAR, $BASE . '/admin/user.php?id=' . $TUID, [
                    'csrf' => $p['csrf'],
                    'nonce' => field_of($form, 'nonce'),
                    'id' => (string)$TUID,
                    'direction' => $diff > 0 ? 'credit' : 'debit',
                    'amount' => (string)abs($diff),
                    'reason' => 'Test cleanup: restore original balance',
                ]);
            }
        }
        $u = user_row($TUID);
        if ($u && abs((float)$u['balance'] - $SNAPSHOT['balance']) > 0.0001) {
            $notes[] = 'balance still ' . $u['balance'] . ' (expected ' . $SNAPSHOT['balance'] . ')';
        }
    }

    // 2. Status + tier back to their original values (also through the app).
    $u = user_row($TUID);
    if ($u && $ADMIN_JAR !== '' && is_file($ADMIN_JAR)) {
        if ($u['status'] !== $SNAPSHOT['status']) {
            $p = load_page($ADMIN_JAR, $TUID);
            if ($p['csrf']) {
                http_post_follow($ADMIN_JAR, $BASE . '/admin/user.php?id=' . $TUID, [
                    'csrf' => $p['csrf'], 'id' => (string)$TUID, 'do_status' => $SNAPSHOT['status'],
                ]);
            }
        }
        $u = user_row($TUID);
        if ($u && (int)$u['tier_id'] !== $SNAPSHOT['tier_id']) {
            $p = load_page($ADMIN_JAR, $TUID);
            if ($p['csrf']) {
                http_post_follow($ADMIN_JAR, $BASE . '/admin/user.php?id=' . $TUID, [
                    'csrf' => $p['csrf'], 'id' => (string)$TUID, 'do_tier' => 'assign',
                    'new_tier' => (string)$SNAPSHOT['tier_id'],
                ]);
            }
        }
    }

    // 3. Admin credits add to users.total_earned while admin debits do not
    //    subtract it (existing product behaviour) - put the snapshot back.
    $u = user_row($TUID);
    if ($u && abs((float)$u['total_earned'] - $SNAPSHOT['total_earned']) > 0.0001) {
        $pdo->prepare('UPDATE users SET total_earned=? WHERE id=?')->execute([$SNAPSHOT['total_earned'], $TUID]);
        $notes[] = 'users.total_earned restored to ' . $SNAPSHOT['total_earned'] . ' (credits add to it, debits do not subtract)';
    }

    // 4. Temporary admin accounts + their audit rows.
    foreach ($CREATED_ADMINS as $aid) {
        $pdo->prepare('DELETE FROM admin_audit_logs WHERE admin_id=?')->execute([$aid]);
        $pdo->prepare('DELETE FROM admins WHERE id=?')->execute([$aid]);
    }
    $CREATED_ADMINS = [];

    // 5. Rows the run itself wrote for the target user, and the audit rows it
    //    produced (including those of the admin account it logged in with).
    if (is_array($BASELINE)) {
        foreach (['transactions' => 'tx', 'notifications' => 'notif', 'activity_log' => 'act', 'email_logs' => 'mail'] as $table => $key) {
            $pdo->prepare("DELETE FROM `$table` WHERE id > ? AND user_id = ?")->execute([$BASELINE[$key], $TUID]);
        }
        $pdo->prepare('DELETE FROM admin_audit_logs WHERE id > ?')->execute([$BASELINE['audit']]);
    }

    foreach ($notes as $n) echo "  NOTE  cleanup: $n\n";
}
register_shutdown_function('cleanup');

// ================================================================ tests =====
section('Setup');
$target = user_row($TUID);
if (!$target) {
    echo "  FATAL test user $TUID does not exist - aborting\n";
    exit(1);
}
$SNAPSHOT = [
    'balance' => (float)$target['balance'],
    'total_earned' => (float)$target['total_earned'],
    'status' => $target['status'],
    'tier_id' => (int)$target['tier_id'],
];
echo '  target user #' . $TUID . ' (' . $target['username'] . ') balance=' . number_format($SNAPSHOT['balance'], 2)
    . ' status=' . $SNAPSHOT['status'] . ' tier=' . $SNAPSHOT['tier_id'] . "\n";
echo '  database: ' . $cfg['db'] . ' @ ' . $cfg['host'] . ':' . $cfg['port'] . " (local development database)\n";
$BASELINE = [
    'tx' => baseline_max_id('transactions', 'user_id', $TUID),
    'notif' => baseline_max_id('notifications', 'user_id', $TUID),
    'act' => baseline_max_id('activity_log', 'user_id', $TUID),
    'mail' => baseline_max_id('email_logs', 'user_id', $TUID),
    'audit' => baseline_max_id('admin_audit_logs'),
];

$support = ensure_admin($pdo, $SUPPORT_USER, 'support', $ROLE_PASS);
$finance = ensure_admin($pdo, $FINANCE_USER, 'finance', $ROLE_PASS);
$main = ensure_admin($pdo, $ADMIN_USER, 'super_admin', $ADMIN_PASS);

$ADMIN_JAR = jar_for('main');
$SUPPORT_JAR = jar_for('support');
$FINANCE_JAR = jar_for('finance');
@unlink($ADMIN_JAR);
@unlink($SUPPORT_JAR);
@unlink($FINANCE_JAR);

$login = admin_login($ADMIN_JAR, $ADMIN_USER, $ADMIN_PASS);
check('super admin can log in over HTTP', $login['ok']);

// ------------------------------------------------------------- page load ----
section('Page loads');
$p = load_page($ADMIN_JAR, $TUID);
check('GET /admin/user.php?id=' . $TUID . ' returns 200', $p['code'] === 200, 'got ' . $p['code']);
check('page carries a CSRF token', is_string($p['csrf']) && $p['csrf'] !== '');
check('Add Money form present', is_string($p['credit']));
check('Remove Money form present', is_string($p['debit']));
check('Tier form present', is_string($p['tier']));
check('Enable/Disable form present', is_string($p['status']));
check('money forms carry a one-time nonce', field_of($p['credit'], 'nonce') !== null && field_of($p['debit'], 'nonce') !== null);
check('tier form carries a CSRF token', field_of($p['tier'], 'csrf') !== null);
check('tier form carries the hidden user id', field_of($p['tier'], 'id') === (string)$TUID);
check('no PHP warnings/notices leak into the HTML', !preg_match('/\b(Warning|Notice|Deprecated|Fatal error)\b:\s/', $p['body']));
check('page shows the current balance', page_balance($p['body']) !== null && abs(page_balance($p['body']) - $SNAPSHOT['balance']) < 0.0001);

// ------------------------------------------------- balance credit / debit ----
section('Add money (balance credit)');
$txBefore = count_rows('SELECT COUNT(*) FROM transactions WHERE user_id=?', [$TUID]);
$notifBefore = count_rows('SELECT COUNT(*) FROM notifications WHERE user_id=? AND title="Account credited"', [$TUID]);
$actBefore = count_rows('SELECT COUNT(*) FROM activity_log WHERE user_id=? AND type="admin_adjust"', [$TUID]);
$auditBefore = count_rows('SELECT COUNT(*) FROM admin_audit_logs WHERE admin_id=? AND action="credit_user"', [$main['id']]);
$creditPayload = [
    'csrf' => $p['csrf'],
    'nonce' => field_of($p['credit'], 'nonce'),
    'id' => (string)$TUID,
    'direction' => 'credit',
    'amount' => '100',
    'reason' => 'E2E balance credit test',
];
$r = http_post_follow($ADMIN_JAR, $BASE . '/admin/user.php?id=' . $TUID, $creditPayload);
check('POST Add Money redirects (post/redirect/get)', $r['post_code'] === 302, 'got ' . $r['post_code']);
check('success flash is shown', flash_has($r['flashes'], 'success', 'Balance updated'), flash_text($r['flashes']));
$after = user_row($TUID);
check_money('balance increased by exactly 100.00', $SNAPSHOT['balance'] + 100, (float)$after['balance']);
check('page now shows the new balance', page_balance($r['body']) !== null && abs(page_balance($r['body']) - (float)$after['balance']) < 0.0001);

$tx = last_tx($TUID, 'Admin Credit');
check('transaction row created', $tx !== null);
if ($tx) {
    check_money('transaction amount is 100.00', 100.0, (float)$tx['amount']);
    check_money('transaction balance_after matches the new balance', (float)$after['balance'], (float)$tx['balance_after']);
    check_eq('transaction status is completed', 'completed', $tx['status']);
    check('transaction reference starts with ADJ', strpos((string)$tx['reference'], 'ADJ') === 0, (string)$tx['reference']);
    check('transaction records the admin and the reason', stripos((string)$tx['description'], 'E2E balance credit test') !== false, (string)$tx['description']);
    check('transaction belongs to the right user', $TUID === (int)$tx['user_id']);
}
check('user notification created', count_rows('SELECT COUNT(*) FROM notifications WHERE user_id=? AND title="Account credited"', [$TUID]) === $notifBefore + 1);
check('activity log entry created', count_rows('SELECT COUNT(*) FROM activity_log WHERE user_id=? AND type="admin_adjust"', [$TUID]) === $actBefore + 1);
check('admin audit entry created', count_rows('SELECT COUNT(*) FROM admin_audit_logs WHERE admin_id=? AND action="credit_user"', [$main['id']]) === $auditBefore + 1);
check('exactly one transaction row added', count_rows('SELECT COUNT(*) FROM transactions WHERE user_id=?', [$TUID]) === $txBefore + 1);

$p = load_page($ADMIN_JAR, $TUID);
check('refreshed page shows the persisted balance', page_balance($p['body']) !== null && abs(page_balance($p['body']) - (float)$after['balance']) < 0.0001);

// ------------------------------------------------------- duplicate submit ----
section('Duplicate submission is blocked');
// exact replay of the payload that already succeeded (same CSRF + same nonce)
$r = http_post_follow($ADMIN_JAR, $BASE . '/admin/user.php?id=' . $TUID, $creditPayload);
check('replayed POST is rejected with a clear message', flash_has($r['flashes'], 'error', 'already submitted'), flash_text($r['flashes']));
check('replay did not change the balance', abs((float)user_row($TUID)['balance'] - (float)$after['balance']) < 0.0001);
check('replay created no extra transaction', count_rows('SELECT COUNT(*) FROM transactions WHERE user_id=?', [$TUID]) === $txBefore + 1);

$r = http_post_follow($ADMIN_JAR, $BASE . '/admin/user.php?id=' . $TUID, [
    'csrf' => $p['csrf'], 'id' => (string)$TUID, 'direction' => 'credit', 'amount' => '100', 'reason' => 'no nonce at all',
]);
check('POST without a nonce is rejected', flash_has($r['flashes'], 'error', 'already submitted'), flash_text($r['flashes']));
check('balance unchanged after the nonce-less POST', abs((float)user_row($TUID)['balance'] - (float)$after['balance']) < 0.0001);

// -------------------------------------------------- balance debit (reversal) -
section('Remove money (reversal of the test credit)');
$p = load_page($ADMIN_JAR, $TUID);
$r = http_post_follow($ADMIN_JAR, $BASE . '/admin/user.php?id=' . $TUID, [
    'csrf' => $p['csrf'],
    'nonce' => field_of($p['debit'], 'nonce'),
    'id' => (string)$TUID,
    'direction' => 'debit',
    'amount' => '100',
    'reason' => 'E2E reversal of credit test',
]);
check('POST Remove Money succeeds', flash_has($r['flashes'], 'success', 'Balance updated'), flash_text($r['flashes']));
$restored = user_row($TUID);
check_money('balance is back to the original value', $SNAPSHOT['balance'], (float)$restored['balance']);
$txd = last_tx($TUID, 'Admin Debit');
check('debit transaction row created', $txd !== null);
if ($txd) {
    check_money('debit amount is -100.00', -100.0, (float)$txd['amount']);
    check_money('debit balance_after matches the restored balance', $SNAPSHOT['balance'], (float)$txd['balance_after']);
    check('debit reference starts with ADJ', strpos((string)$txd['reference'], 'ADJ') === 0, (string)$txd['reference']);
    check('debit transaction belongs to the right user', $TUID === (int)$txd['user_id']);
}
check('net effect of credit + reversal on balance is zero', abs((float)user_row($TUID)['balance'] - $SNAPSHOT['balance']) < 0.0001);

// ------------------------------------------------------------ account status -
section('Enable / disable account');
$p = load_page($ADMIN_JAR, $TUID);
$r = http_post_follow($ADMIN_JAR, $BASE . '/admin/user.php?id=' . $TUID, [
    'csrf' => $p['csrf'], 'id' => (string)$TUID, 'do_status' => 'suspended',
]);
check('Disable Account succeeds', flash_has($r['flashes'], 'success', 'disabled'), flash_text($r['flashes']));
check('status stored as suspended', 'suspended' === (string)user_row($TUID)['status']);
check('page shows the account as Disabled', stripos($r['body'], 'Account: <strong class="text-red-600">Disabled') !== false);

$p = load_page($ADMIN_JAR, $TUID);
$r = http_post_follow($ADMIN_JAR, $BASE . '/admin/user.php?id=' . $TUID, [
    'csrf' => $p['csrf'], 'id' => (string)$TUID, 'do_status' => 'active',
]);
check('Enable Account succeeds', flash_has($r['flashes'], 'success', 'now active'), flash_text($r['flashes']));
check('status stored as active', 'active' === (string)user_row($TUID)['status']);

$p = load_page($ADMIN_JAR, $TUID);
$r = http_post_follow($ADMIN_JAR, $BASE . '/admin/user.php?id=' . $TUID, [
    'csrf' => $p['csrf'], 'id' => (string)$TUID, 'do_status' => 'active',
]);
check('repeating the same status action is idempotent', flash_has($r['flashes'], 'error', 'already active'), flash_text($r['flashes']));

// ---------------------------------------------------------- tier assignment --
section('Tier assignment');
$p = load_page($ADMIN_JAR, $TUID);
$r = http_post_follow($ADMIN_JAR, $BASE . '/admin/user.php?id=' . $TUID, [
    'csrf' => $p['csrf'], 'id' => (string)$TUID, 'do_tier' => 'assign', 'new_tier' => '1',
]);
check('Assign Tier succeeds (no fatal error, no 500)', $r['post_code'] === 302 && flash_has($r['flashes'], 'success', 'User tier updated'), flash_text($r['flashes']));
check('tier_id stored as 1', '1' === (string)user_row($TUID)['tier_id']);
check('page header shows the new plan', stripos($r['body'], 'Plan: beginner') !== false);

$p = load_page($ADMIN_JAR, $TUID);
$r = http_post_follow($ADMIN_JAR, $BASE . '/admin/user.php?id=' . $TUID, [
    'csrf' => $p['csrf'], 'id' => (string)$TUID, 'do_tier' => 'assign', 'new_tier' => '9999',
]);
check('unknown tier id reports "Tier not found"', flash_has($r['flashes'], 'error', 'Tier not found'), flash_text($r['flashes']));
check('tier unchanged after the unknown tier', '1' === (string)user_row($TUID)['tier_id']);

$p = load_page($ADMIN_JAR, $TUID);
$r = http_post_follow($ADMIN_JAR, $BASE . '/admin/user.php?id=' . $TUID, [
    'csrf' => $p['csrf'], 'id' => (string)$TUID, 'do_tier' => 'assign', 'new_tier' => '',
]);
check('empty tier selection is rejected clearly', flash_has($r['flashes'], 'error', 'Please select a tier'), flash_text($r['flashes']));

$p = load_page($ADMIN_JAR, $TUID);
$r = http_post_follow($ADMIN_JAR, $BASE . '/admin/user.php?id=' . $TUID, [
    'id' => (string)$TUID, 'do_tier' => 'assign', 'new_tier' => '2',
]);
check('tier POST without CSRF is rejected', flash_has($r['flashes'], 'error', 'Something went wrong'), flash_text($r['flashes']));
check('tier unchanged after the CSRF-less POST', '1' === (string)user_row($TUID)['tier_id']);

$p = load_page($ADMIN_JAR, $TUID);
$r = http_post_follow($ADMIN_JAR, $BASE . '/admin/user.php?id=' . $TUID, [
    'csrf' => $p['csrf'], 'id' => (string)$TUID, 'do_tier' => 'assign', 'new_tier' => (string)$SNAPSHOT['tier_id'],
]);
check('assigning the original tier back works (Free Tier = id 0)', flash_has($r['flashes'], 'success', 'User tier updated'), flash_text($r['flashes']));
check_eq('tier restored to its original value', $SNAPSHOT['tier_id'], (int)user_row($TUID)['tier_id']);

// ------------------------------------------- validation / edge cases --------
section('Validation and edge cases');
$balNow = (float)user_row($TUID)['balance'];
$txCountNow = count_rows('SELECT COUNT(*) FROM transactions WHERE user_id=?', [$TUID]);

$edge = function (string $name, array $overrides, string $expectNeedle) use (&$ADMIN_JAR, $BASE, $TUID, $balNow, $txCountNow) {
    $p = load_page($ADMIN_JAR, $TUID);
    $direction = $overrides['direction'] ?? 'credit';
    $form = $direction === 'debit' ? $p['debit'] : $p['credit'];
    $payload = [
        'csrf' => $p['csrf'],
        'nonce' => field_of($form, 'nonce'),
        'id' => (string)$TUID,
        'direction' => $direction,
        'amount' => $overrides['amount'] ?? '10',
        'reason' => $overrides['reason'] ?? 'edge case test',
    ];
    foreach ($overrides as $k => $v) {
        if ($k === 'direction') continue;
        $payload[$k] = $v;
    }
    $r = http_post_follow($ADMIN_JAR, $BASE . '/admin/user.php?id=' . $TUID, $payload);
    check($name . ' -> clear error message', flash_has($r['flashes'], 'error', $expectNeedle), flash_text($r['flashes']));
    check($name . ' -> balance untouched', abs((float)user_row($TUID)['balance'] - $balNow) < 0.0001);
    check($name . ' -> no transaction row written', count_rows('SELECT COUNT(*) FROM transactions WHERE user_id=?', [$TUID]) === $txCountNow);
};

$edge('missing amount', ['amount' => ''], 'Enter an amount greater than zero');
$edge('zero amount', ['amount' => '0'], 'Enter an amount greater than zero');
$edge('negative amount', ['amount' => '-25'], 'Enter an amount greater than zero');
$edge('non-numeric amount', ['amount' => 'abc'], 'Enter an amount greater than zero');
$edge('amount above the limit', ['amount' => '1000001'], 'exceeds the allowed limit');
$edge('missing reason', ['reason' => ''], 'A reason is required');
$edge('too-short reason', ['reason' => 'x'], 'A reason is required');
$edge('debit larger than the balance', ['direction' => 'debit', 'amount' => '500000'], 'Debit exceeds available balance');
$edge('unknown direction value', ['direction' => 'sideways'], 'That action is not valid');
$edge('bad CSRF token', ['csrf' => 'deadbeefdeadbeef'], 'Something went wrong');

$p = load_page($ADMIN_JAR, $TUID);
$r = http_post_follow($ADMIN_JAR, $BASE . '/admin/user.php?id=999999', [
    'csrf' => $p['csrf'],
    'nonce' => field_of($p['credit'], 'nonce'),
    'id' => '999999',
    'direction' => 'credit',
    'amount' => '10',
    'reason' => 'no such user',
]);
check('unknown user id -> clear "not found" message (no crash)', flash_has($r['flashes'], 'error', 'not found'), flash_text($r['flashes']));
check('unknown user id -> nothing written for it', count_rows('SELECT COUNT(*) FROM transactions WHERE user_id=999999') === 0);

$r = http_get_follow($ADMIN_JAR, $BASE . '/admin/user.php?id=abc');
check('non-numeric id redirects to the user list', flash_has($r['flashes'], 'error', 'could not be found'), flash_text($r['flashes']));
$r = http_get_follow($ADMIN_JAR, $BASE . '/admin/user.php');
check('missing id redirects to the user list', flash_has($r['flashes'], 'error', 'could not be found'), flash_text($r['flashes']));

// --------------------------------------------------- database failure --------
section('Database failure is logged and rolled back');
$p = load_page($ADMIN_JAR, $TUID);
$balBeforeFail = (float)user_row($TUID)['balance'];
$txBeforeFail = count_rows('SELECT COUNT(*) FROM transactions WHERE user_id=?', [$TUID]);
// Invalid UTF-8 bytes: the balance UPDATE runs first, then the INSERT into
// `transactions` fails on strict mode -> the whole transaction must roll back.
$badReason = str_repeat("\xC3\x28", 20);
$r = http_post_follow($ADMIN_JAR, $BASE . '/admin/user.php?id=' . $TUID, [
    'csrf' => $p['csrf'],
    'nonce' => field_of($p['credit'], 'nonce'),
    'id' => (string)$TUID,
    'direction' => 'credit',
    'amount' => '10',
    'reason' => $badReason,
]);
check('DB failure returns a clear error to the admin', flash_has($r['flashes'], 'error', 'Balance could not be updated'), flash_text($r['flashes']));
check('DB failure left the balance untouched (rollback)', abs((float)user_row($TUID)['balance'] - $balBeforeFail) < 0.0001);
check('DB failure left no partial transaction row', count_rows('SELECT COUNT(*) FROM transactions WHERE user_id=?', [$TUID]) === $txBeforeFail);

$logFile = null;
foreach ([(string)getenv('TEST_NGINX_LOG'), 'C:/Users/LENOVO/.config/herd/Log/nginx-error.log'] as $cand) {
    if ($cand !== '' && is_readable($cand)) {
        $logFile = $cand;
        break;
    }
}
if ($logFile) {
    $tail = '';
    $fh = @fopen($logFile, 'rb');
    if ($fh) {
        $size = (int)filesize($logFile);
        fseek($fh, -min(400000, $size), SEEK_END);
        $tail = (string)stream_get_contents($fh);
        fclose($fh);
    }
    check('DB failure was written to the server error log', strpos($tail, '[admin_adjust_balance]') !== false, 'log: ' . $logFile);
} else {
    echo "  SKIP  server error log not readable - could not verify the error_log() output\n";
}

// ------------------------------------------------------------- permissions ---
section('Permissions');
$sLogin = admin_login($SUPPORT_JAR, $SUPPORT_USER, $ROLE_PASS);
check('support admin can log in', $sLogin['ok']);
$pS = load_page($SUPPORT_JAR, $TUID);
check('support role sees no money forms', $pS['credit'] === null && $pS['debit'] === null);
check('support role sees no tier form', $pS['tier'] === null);
check('support role sees no enable/disable button', $pS['status'] === null);
$balBefore = (float)user_row($TUID)['balance'];
$r = http_post_follow($SUPPORT_JAR, $BASE . '/admin/user.php?id=' . $TUID, [
    'csrf' => $sLogin['csrf'], 'id' => (string)$TUID, 'direction' => 'credit', 'amount' => '100', 'reason' => 'unauthorized credit',
]);
check('support credit attempt is refused', flash_has($r['flashes'], 'error', 'You do not have permission'), flash_text($r['flashes']));
$r = http_post_follow($SUPPORT_JAR, $BASE . '/admin/user.php?id=' . $TUID, [
    'csrf' => $sLogin['csrf'], 'id' => (string)$TUID, 'do_status' => 'suspended',
]);
check('support status attempt is refused', flash_has($r['flashes'], 'error', 'You do not have permission'), flash_text($r['flashes']));
$r = http_post_follow($SUPPORT_JAR, $BASE . '/admin/user.php?id=' . $TUID, [
    'csrf' => $sLogin['csrf'], 'id' => (string)$TUID, 'do_tier' => 'assign', 'new_tier' => '2',
]);
check('support tier attempt is refused', flash_has($r['flashes'], 'error', 'You do not have permission'), flash_text($r['flashes']));
check('support attempts changed nothing', abs((float)user_row($TUID)['balance'] - $balBefore) < 0.0001
    && 'active' === (string)user_row($TUID)['status']
    && $SNAPSHOT['tier_id'] === (int)user_row($TUID)['tier_id']);

$fLogin = admin_login($FINANCE_JAR, $FINANCE_USER, $ROLE_PASS);
check('finance admin can log in', $fLogin['ok']);
$pF = load_page($FINANCE_JAR, $TUID);
check('finance role sees the money forms', $pF['credit'] !== null && $pF['debit'] !== null);
check('finance role does not see the tier form', $pF['tier'] === null);
check('finance role does not see enable/disable', $pF['status'] === null);
$r = http_post_follow($FINANCE_JAR, $BASE . '/admin/user.php?id=' . $TUID, [
    'csrf' => $fLogin['csrf'], 'id' => (string)$TUID, 'do_status' => 'active',
]);
check('finance status attempt is refused', flash_has($r['flashes'], 'error', 'You do not have permission'), flash_text($r['flashes']));
check('finance attempt changed nothing', 'active' === (string)user_row($TUID)['status']
    && abs((float)user_row($TUID)['balance'] - $balBefore) < 0.0001);

// --------------------------------------------------- related admin pages -----
section('Related admin pages (shared code was changed)');
foreach (['users', 'deposits', 'withdrawals', 'transactions', 'audit', 'index'] as $page) {
    $g = http_get_follow($ADMIN_JAR, $BASE . '/admin/' . $page . '.php');
    check("/admin/$page.php still loads", $g['code'] === 200, 'HTTP ' . $g['code']);
}

// --------------------------------------------------------------- final state -
section('Final state');
$final = user_row($TUID);
check_money('balance equals the original balance', $SNAPSHOT['balance'], (float)$final['balance']);
check_eq('status equals the original status', $SNAPSHOT['status'], $final['status']);
check_eq('tier equals the original tier', $SNAPSHOT['tier_id'], (int)$final['tier_id']);
check('page still loads after all actions', load_page($ADMIN_JAR, $TUID)['code'] === 200);

cleanup();

echo "\n----------------------------------------\n";
echo "Passed: $T_PASS   Failed: $T_FAIL\n";
exit($T_FAIL > 0 ? 1 : 0);

<?php
declare(strict_types=1);
// Central email service: templates from DB + SMTP delivery + logging.
// All application code must call send_template() — never inline SMTP.
require_once __DIR__ . '/settings.php';

function mail_configured(): bool {
    return cfg_flag('mail_enabled', true) && trim((string)setting('smtp_host', '')) !== '';
}

function render_template(string $body, array $vars): string {
    $base = [
        'site_name' => site_name(),
        'support_email' => (string)setting('support_email', ''),
        'date' => date('M d, Y g:i A'),
    ];
    $vars = array_merge($base, $vars);
    return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', function ($m) use ($vars) {
        $k = $m[1];
        return array_key_exists($k, $vars) && $vars[$k] !== null ? (string)$vars[$k] : '';
    }, $body);
}

function smtp_send(string $to, string $subject, string $body): array {
    $host = trim((string)setting('smtp_host', ''));
    $port = (int)(setting('smtp_port', 587) ?: 587);
    $user = (string)setting('smtp_user', '');
    $pass = (string)setting('smtp_pass', '');
    $enc = strtolower(trim((string)setting('smtp_enc', 'tls')));
    $fromEmail = (string)(setting('mail_from_email', '') ?: 'noreply@localhost');
    $fromName = (string)(setting('mail_from_name', site_name()) ?: site_name());
    if ($host === '') return ['ok' => false, 'error' => 'SMTP host not configured'];

    $remote = ($enc === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $errno = 0; $errstr = '';
    $fp = @stream_socket_client($remote, $errno, $errstr, 15);
    if (!$fp) return ['ok' => false, 'error' => "Connection failed: $errstr ($errno)"];
    stream_set_timeout($fp, 15);

    $read = function () use ($fp) {
        $out = '';
        while (($line = fgets($fp, 1024)) !== false) {
            $out .= $line;
            if (preg_match('/^\d{3} /', $line)) break;
        }
        return $out;
    };
    $cmd = function (string $c) use ($fp, $read) {
        if ($c !== '') fwrite($fp, $c . "\r\n");
        return $read();
    };
    $fail = function (string $error) use ($fp) {
        @fwrite($fp, "QUIT\r\n");
        @fclose($fp);
        return ['ok' => false, 'error' => $error];
    };

    try {
        $greet = $read();
        if (!preg_match('/^220/', $greet)) return $fail('Bad greeting: ' . trim($greet));
        $local = gethostname() ?: 'localhost';
        $ehlo = $cmd("EHLO $local");
        if (!preg_match('/^250/', $ehlo)) {
            $helo = $cmd("HELO $local");
            if (!preg_match('/^250/', $helo)) return $fail('HELO rejected');
        }
        if ($enc === 'tls') {
            $tls = $cmd('STARTTLS');
            if (!preg_match('/^220/', $tls)) return $fail('STARTTLS rejected: ' . trim($tls));
            if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) return $fail('TLS handshake failed');
            $ehlo2 = $cmd("EHLO $local");
            if (!preg_match('/^250/', $ehlo2)) return $fail('EHLO after TLS rejected');
        }
        if ($user !== '') {
            $auth = $cmd('AUTH LOGIN');
            if (!preg_match('/^334/', $auth)) return $fail('AUTH not accepted: ' . trim($auth));
            $u = $cmd(base64_encode($user));
            if (!preg_match('/^334/', $u)) return $fail('SMTP username rejected');
            $p = $cmd(base64_encode($pass));
            if (!preg_match('/^235/', $p)) return $fail('SMTP authentication failed (check username/password)');
        }
        $m = $cmd("MAIL FROM:<$fromEmail>");
        if (!preg_match('/^250/', $m)) return $fail('MAIL FROM rejected: ' . trim($m));
        $r = $cmd("RCPT TO:<$to>");
        if (!preg_match('/^2/', $r)) return $fail('Recipient rejected: ' . trim($r));
        $d = $cmd('DATA');
        if (!preg_match('/^354/', $d)) return $fail('DATA rejected: ' . trim($d));

        $safeName = str_replace(["\r", "\n", '"'], '', $fromName);
        $headers = [
            'From: "' . $safeName . '" <' . $fromEmail . '>',
            'Reply-To: ' . $fromEmail,
            'To: <' . $to . '>',
            'Subject: ' . mb_encode_mimeheader($subject, 'UTF-8'),
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'X-Mailer: Drillifyx-Mailer',
            'Date: ' . date(DATE_RFC2822),
        ];
        $cleanBody = str_replace("\r", '', $body);
        $cleanBody = preg_replace('/^\./m', '..', $cleanBody);
        fwrite($fp, implode("\r\n", $headers) . "\r\n\r\n" . $cleanBody . "\r\n.\r\n");
        $sent = $read();
        if (!preg_match('/^250/', $sent)) return $fail('Message rejected: ' . trim($sent));
        $cmd('QUIT');
        fclose($fp);
        return ['ok' => true, 'error' => ''];
    } catch (Throwable $e) {
        return $fail('Mailer error: ' . $e->getMessage());
    }
}

function log_email(?int $userId, string $recipient, string $slug, string $subject, string $status, string $error = ''): void {
    try {
        $st = db()->prepare('INSERT INTO email_logs (user_id, recipient, template_slug, subject, status, error) VALUES (?,?,?,?,?,?)');
        $st->execute([$userId, $recipient, $slug, mb_substr($subject, 0, 190), $status, mb_substr($error, 0, 500)]);
    } catch (Throwable $e) {}
}

// Central send: returns true if actually sent, false otherwise (logged either way).
function send_template(string $toEmail, string $slug, array $vars = [], ?int $userId = null): bool {
    try {
        $st = db()->prepare('SELECT * FROM email_templates WHERE slug=? LIMIT 1');
        $st->execute([$slug]);
        $t = $st->fetch();
        if (!$t || !(int)$t['enabled'] || !cfg_flag('mail_enabled', true)) {
            log_email($userId, $toEmail, $slug, $t['subject'] ?? $slug, 'skipped', !$t ? 'template missing' : 'disabled');
            return false;
        }
        $subject = render_template($t['subject'], $vars);
        $body = render_template($t['body'], $vars);
        $name = (string)($vars['name'] ?? '');
        if (!mail_configured()) {
            log_email($userId, $toEmail, $slug, $subject, 'skipped', 'SMTP not configured');
            return false;
        }
        $r = smtp_send($toEmail, $subject, $body);
        log_email($userId, $toEmail, $slug, $subject, $r['ok'] ? 'sent' : 'failed', $r['ok'] ? '' : $r['error']);
        return $r['ok'];
    } catch (Throwable $e) {
        log_email($userId, $toEmail, $slug, $slug, 'failed', substr($e->getMessage(), 0, 200));
        return false;
    }
}

function user_email_vars(array $u, array $extra = []): array {
    return array_merge([
        'name' => $u['username'] ?? '',
        'email' => $u['email'] ?? '',
        'balance' => isset($u['balance']) ? number_format((float)$u['balance'], 2) : '',
        'referral_code' => $u['referral_code'] ?? '',
    ], $extra);
}

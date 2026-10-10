<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/withdraw_logic.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(false, 'Invalid request.', [], 405);
$u = current_user();
if (!$u) json_out(false, 'Please sign in.', [], 401);
if (!csrf_check($_POST['csrf'] ?? null)) json_out(false, 'Invalid session. Refresh and try again.', [], 419);
$r = do_withdraw((int)$u['id'], $_POST);
if ($r['ok']) json_out(true, $r['msg'], ['reference' => $r['reference'] ?? '', 'balance' => $r['balance'] ?? 0]);
json_out(false, $r['msg'], ['need_pin' => $r['need_pin'] ?? false], 400);

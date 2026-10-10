<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/hash_logic.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(false, 'Invalid request.', [], 405);
$u = current_user();
if (!$u) json_out(false, 'Please sign in.', [], 401);
if (!csrf_check($_POST['csrf'] ?? null)) json_out(false, 'Invalid session. Refresh and try again.', [], 419);
$r = do_hash((int)$u['id']);
if ($r['ok']) json_out(true, $r['msg'], ['reward' => $r['reward'] ?? 0, 'hashes_left' => $r['hashes_left'] ?? 0, 'balance' => $r['balance'] ?? 0]);
json_out(false, $r['msg'], [], 400);

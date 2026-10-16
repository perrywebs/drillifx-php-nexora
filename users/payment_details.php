<?php
// Payment details now display inline on the deposit page (reference design).
// This file stays as a safe redirect for any existing links/bookmarks.
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();
$tierId = (int)($_GET['tier'] ?? $_GET['tier_id'] ?? 0);
redirect('/users/deposit.php' . ($tierId > 0 ? '?tier=' . $tierId : ''));

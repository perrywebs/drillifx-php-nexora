<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin_auth.php';
$a = current_admin();
if ($a) admin_log((int)$a['id'], 'admin_logout', $a['username'] . ' signed out');
admin_logout();
redirect('/admin/login.php');

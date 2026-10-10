<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', '/tmp/log3.txt');

$dbCfg = require 'config/database.php';
$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $dbCfg['host'], $dbCfg['port'], $dbCfg['db'], $dbCfg['charset']);
$pdo = new PDO($dsn, $dbCfg['user'], $dbCfg['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$r = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
echo "All tables:\n";
foreach($r as $t) echo " - $t\n";

echo "\n--- Admins table ---\n";
$r = $pdo->query('DESCRIBE admins')->fetchAll(PDO::FETCH_ASSOC);
if ($r) {
    foreach ($r as $col) {
        echo "  " . $col['Field'] . " " . $col['Type'] . " " . $col['Key'] . "\n";
    }
} else {
    echo "  (no results)\n";
}

echo "\n--- Admins data ---\n";
$r = $pdo->query('SELECT * FROM admins')->fetchAll(PDO::FETCH_ASSOC);
foreach ($r as $row) { print_r($row); }
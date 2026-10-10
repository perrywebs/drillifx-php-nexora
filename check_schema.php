<?php
$cfg = require 'config/database.php';
$pdo = new PDO("mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['db']};charset={$cfg['charset']}", $cfg['user'], $cfg['pass']);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== Tiers columns ===\n";
$st = $pdo->query('SHOW COLUMNS FROM tiers');
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $col) {
    echo "Column: " . $col['Field'] . " Type: " . $col['Type'] . "\n";
}

echo "\n=== Users columns ===\n";
$st = $pdo->query('SHOW COLUMNS FROM users');
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $col) {
    echo "Column: " . $col['Field'] . " Type: " . $col['Type'] . "\n";
}

echo "\n=== Deposits columns ===\n";
$st = $pdo->query('SHOW COLUMNS FROM deposits');
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $col) {
    echo "Column: " . $col['Field'] . " Type: " . $col['Type'] . "\n";
}

echo "\n=== Tiers data ===\n";
$st = $pdo->query("SELECT * FROM tiers ORDER BY id");
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo "ID={$row['id']} name={$row['name']} display_name={$row['display_name']} rating={$row['display_rating']}\n";
}
<?php
$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: 3308;
$db = getenv('DB_NAME') ?: 'drillifx';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: 'secret';
$charset = getenv('DB_CHARSET') ?: 'utf8mb4';
$pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=$charset", $user, $pass);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$st = $pdo->query("SELECT * FROM tiers ORDER BY id");
foreach ($st->fetchAll() as $row) {
    echo "ID={$row['id']} {$row['display_name']} ({$row['display_rating']}) hash={$row['daily_hash_allowance']}/day spin={$row['daily_spin_allowance']}/day ref={$row['referral_requirement']}({$row['requirement_type']}) price={$row['price']} days={$row['duration_days']} active={$row['active']}\n";
}
?>
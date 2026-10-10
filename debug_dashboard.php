<?php
$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: 3308;
$db = getenv('DB_NAME') ?: 'drillifx';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: 'secret';
$charset = getenv('DB_CHARSET') ?: 'utf8mb4';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=$charset", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== Debug Dashboard ===\n";
    
    // Check user's effective tier from unified tiers table
    $uId = 1; // Example user ID
    $st = $pdo->prepare('SELECT * FROM tiers WHERE id = ? AND active = 1 LIMIT 1');
    $st->execute([$uId]);
    $uo = $st->fetch();
    if ($uo) {
        echo "User {$uId} is on: {$uo['display_name']} ({$uo['display_rating']})\n";
        echo "  Hash allowance: {$uo['daily_hash_allowance']}/day\n";
        echo "  Spin allowance: {$uo['daily_spin_allowance']}/day\n";
        echo "  Referral requirement: {$uo['referral_requirement']} ({$uo['requirement_type']})\n";
    } else {
        echo "User {$uId} has no active tier assignment\n";
    }
    
    // Show all active tiers
    $st = $pdo->query("SELECT * FROM tiers WHERE active = 1 ORDER BY id");
    echo "\nActive tiers:\n";
    foreach ($st->fetchAll() as $t) {
        echo "  ID={$t['id']}: {$t['display_name']} {$t['display_rating']} - {$t['daily_hash_allowance']} hashes, {$t['daily_spin_allowance']} spins, ref={$t['referral_requirement']}({$t['requirement_type']})\n";
    }
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
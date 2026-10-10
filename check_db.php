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
    
    echo "Connected OK\n";
    
    // Check tiers table
    $st = $pdo->query("SELECT COUNT(*) c FROM tiers");
    echo "tiers rows: " . $st->fetch()['c'] . "\n";
    
    $st = $pdo->query("SELECT * FROM tiers ORDER BY id");
    foreach ($st->fetchAll() as $row) {
        echo "  ID={$row['id']} name={$row['name']} display={$row['display_name']} rating={$row['display_rating']} hash_allow={$row['daily_hash_allowance']} spin_allow={$row['daily_spin_allowance']} ref_req={$row['referral_requirement']} type={$row['requirement_type']} active={$row['active']}\n";
    }
    
    // Check users
    $st = $pdo->query("DESCRIBE users");
    echo "\nusers columns:\n";
    foreach ($st->fetchAll() as $row) {
        echo "  - {$row['Field']} ({$row['Type']})\n";
    }
    
    $st = $pdo->query("SELECT COUNT(*) c FROM users WHERE tier_id = 0");
    echo "\nUsers on Free Tier: " . $st->fetch()['c'] . "\n";
    
    // Show some user data with tier info
    $st = $pdo->query("SELECT u.username, u.tier_id, t.display_name as tier_name FROM users u LEFT JOIN tiers t ON t.id=u.tier_id LIMIT 5");
    echo "\nSample users:\n";
    foreach ($st->fetchAll() as $row) {
        echo "  user={$row['username']} tier={$row['tier_name']}\n";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
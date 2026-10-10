<?php
// Migration verification script
// Load config first
$cfg = require __DIR__ . '/config/database.php';

try {
    $pdo = new PDO("mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['db']};charset={$cfg['charset']}", $cfg['user'], $cfg['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check tiers table exists and has data
    echo "Tiers table check:\n";
    $st = $pdo->query("SELECT COUNT(*) c FROM tiers");
    $count = $st->fetch()['c'];
    echo "  Tiers rows: $count\n";
    
    // Check users table structure
    echo "\nusers table columns:" . PHP_EOL;
    $st = $pdo->query("DESCRIBE users");
    foreach ($st->fetchAll() as $row) {
        echo "  - " . $row['Field'] . " ({$row['Type']})" . PHP_EOL;
    }
    
    // Show tiers data
    echo "\nTiers data:\n";
    $st = $pdo->query("SELECT * FROM tiers ORDER BY id");
    foreach ($st->fetchAll() as $row) {
        echo "  ID={$row['id']} name={$row['name']} display={$row['display_name']} rating={$row['display_rating']} hashes={$row['daily_hashes']} spins={$row['daily_spins']} ref={$row['referral_requirement']} type={$row['requirement_type']}\n";
    }
    
    // Show users sample
    echo "\nUsers sample:\n";
    $st = $pdo->query("SELECT u.id, u.username, u.tier_id FROM users u LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($st as $row) {
        echo "  user={$row['username']} tier_id={$row['tier_id']}\n";
    }
    
    echo "\nSUCCESS - Migration check works!\n";
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . PHP_EOL;
}
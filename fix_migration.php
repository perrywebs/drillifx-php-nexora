<?php
// Migration fix script - with hardcoded DB connection
$pdo = new PDO("mysql:host=localhost;port=3308;dbname=drillifx;charset=utf8mb4", "root", "secret");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Check tiers
echo "Tiers:\n";
$st = $pdo->query("SELECT * FROM tiers ORDER BY id");
foreach ($st->fetchAll() as $row) {
    echo "ID={$row['id']} name={$row['name']} display={$row['display_name']} rating={$row['display_rating']} hash={$row['daily_hashes']} spin={$row['daily_spins']} ref={$row['referral_requirement']} type={$row['requirement_type']} active={$row['active']}\n";
}

// Verify users table structure
echo "\nUsers columns:\n";
$cols = $pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) { echo "  " . $c['Field'] . "\n"; }

// Check users sample
echo "\nUsers sample:\n";
$r = $pdo->query("SELECT id, username, tier_id FROM users LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
foreach ($r as $row) { echo "  user={$row['username']} tier_id={$row['tier_id']}\n"; }

echo "\n=== Done ===\n";
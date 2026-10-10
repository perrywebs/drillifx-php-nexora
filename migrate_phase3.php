<?php
// Phase 3 migration script - consolidated into tiers table
$cfg = require __DIR__ . '/config/database.php';

try {
    $pdo = new PDO("mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['db']};charset={$cfg['charset']}", $cfg['user'], $cfg['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== Consolidating Upgrade System into Tiers Table ===" . PHP_EOL;
    
    // Check current upgrade_options table status
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $hasUpgradeOptions = in_array('upgrade_options', $tables);
    echo "  upgrade_options table exists: " . ($hasUpgradeOptions ? "YES" : "NO") . PHP_EOL;
    
    // Verify tiers table has the three default upgrade tiers
    echo "\n  Tiers table verification:" . PHP_EOL;
    $st = $pdo->query("SELECT * FROM tiers WHERE display_rating != '' ORDER BY id");
    $upgradeTiers = $st->fetchAll(PDO::FETCH_ASSOC);
    foreach ($upgradeTiers as $t) {
        echo "  ID={$t['id']} {$t['display_name']} ({$t['display_rating']}) hashes={$t['daily_hashes']}/day spins={$t['daily_spins']}/day ref={$t['referral_requirement']}({$t['requirement_type']}) active={$t['active']}\n";
    }
    
    if (count($upgradeTiers) < 3) {
        echo "\n  WARNING: Expected 3 upgrade tiers in tiers table. Adding them..." . PHP_EOL;
        // Insert the three default upgrade tiers if they don't exist
        $pdo->exec("--
INSERT IGNORE INTO tiers (id, name, display_name, display_rating, daily_hashes, daily_spins, price, duration_days, referral_requirement, requirement_type, daily_hash_allowance, daily_spin_allowance, active) VALUES
(5, 'beginner', 'Beginner', '⭐⭐', 1, 1, 0.00, 30, 3, 'fixed', 1, 1, 1),
(6, 'amateur', 'Amateur', '⭐⭐⭐', 2, 2, 0.00, 30, 5, 'fixed', 2, 2, 1),
(7, 'expert', 'Expert', '⭐⭐⭐⭐', 3, 3, 0.00, 30, 11, 'threshold', 3, 3, 1)");
        echo "  Added upgrade tiers." . PHP_EOL;
    }
    
    // Verify users table no longer has upgrade_option_id
    $cols = $pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_ASSOC);
    $hasUpgradeOptionId = false;
    foreach ($cols as $c) {
        if ($c['Field'] === 'upgrade_option_id') $hasUpgradeOptionId = true;
    }
    echo "  users.upgrade_option_id column: " . ($hasUpgradeOptionId ? "exists" : "removed") . PHP_EOL;
    
    if ($hasUpgradeOptionId) {
        echo "  Removing upgrade_option_id from users table..." . PHP_EOL;
        $pdo->exec("ALTER TABLE users DROP COLUMN upgrade_option_id");
        echo "  Removed." . PHP_EOL;
    }
    
    // Drop upgrade_options table if it exists
    if ($hasUpgradeOptions) {
        echo "  Dropping upgrade_options table..." . PHP_EOL;
        $pdo->exec("DROP TABLE upgrade_options");
        echo "  Dropped." . PHP_EOL;
    }
    
    // Final verification
    echo "\n  Final tiers:" . PHP_EOL;
    $st = $pdo->query("SELECT * FROM tiers ORDER BY id");
    foreach ($st->fetchAll() as $t) {
        echo "    ID={$t['id']} {$t['name']} active={$t['active']}" . PHP_EOL;
    }
    
    echo "\n=== Consolidation Complete ===" . PHP_EOL;
    
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
}
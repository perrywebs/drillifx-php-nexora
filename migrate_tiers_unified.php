<?php
// Migration: Add unified tier columns to the tiers table and seed default tiers
$cfg = require __DIR__ . '/config/database.php';

try {
    $pdo = new PDO("mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['db']};charset={$cfg['charset']}", $cfg['user'], $cfg['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== Unified Tier Migration ===" . PHP_EOL;
    
    // 1. Add missing columns to tiers table
    echo "1. Adding columns to tiers table..." . PHP_EOL;
    
    // display_name
    try {
        $pdo->exec("ALTER TABLE `tiers` ADD COLUMN `display_name` VARCHAR(50) NOT NULL DEFAULT '' AFTER `name`");
        echo "   display_name added" . PHP_EOL;
    } catch (PDOException $e) {
        echo "   display_name: " . $e->getMessage() . PHP_EOL;
    }
    
    // display_rating
    try {
        $pdo->exec("ALTER TABLE `tiers` ADD COLUMN `display_rating` VARCHAR(20) NOT NULL DEFAULT '' AFTER `display_name`");
        echo "   display_rating added" . PHP_EOL;
    } catch (PDOException $e) {
        echo "   display_rating: " . $e->getMessage() . PHP_EOL;
    }
    
    // referral_requirement
    try {
        $pdo->exec("ALTER TABLE `tiers` ADD COLUMN `referral_requirement` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `display_rating`");
        echo "   referral_requirement added" . PHP_EOL;
    } catch (PDOException $e) {
        echo "   referral_requirement: " . $e->getMessage() . PHP_EOL;
    }
    
    // requirement_type
    try {
        $pdo->exec("ALTER TABLE `tiers` ADD COLUMN `requirement_type` ENUM('fixed','threshold') NOT NULL DEFAULT 'fixed' AFTER `referral_requirement`");
        echo "   requirement_type added" . PHP_EOL;
    } catch (PDOException $e) {
        echo "   requirement_type: " . $e->getMessage() . PHP_EOL;
    }
    
    // daily_hash_allowance
    try {
        $pdo->exec("ALTER TABLE `tiers` ADD COLUMN `daily_hash_allowance` TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER `requirement_type`");
        echo "   daily_hash_allowance added" . PHP_EOL;
    } catch (PDOException $e) {
        echo "   daily_hash_allowance: " . $e->getMessage() . PHP_EOL;
    }
    
    // daily_spin_allowance
    try {
        $pdo->exec("ALTER TABLE `tiers` ADD COLUMN `daily_spin_allowance` TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER `daily_hash_allowance`");
        echo "   daily_spin_allowance added" . PHP_EOL;
    } catch (PDOException $e) {
        echo "   daily_spin_allowance: " . $e->getMessage() . PHP_EOL;
    }
    
    // active
    try {
        $pdo->exec("ALTER TABLE `tiers` ADD COLUMN `active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `daily_spin_allowance`");
        echo "   active added" . PHP_EOL;
    } catch (PDOException $e) {
        echo "   active: " . $e->getMessage() . PHP_EOL;
    }
    
    // 2. Update existing Account Tiers (ids 1-4) with default values
    echo "2. Updating existing Account Tiers with new column defaults..." . PHP_EOL;
    
    // Set display_name to the tier name, preserve other values
    $pdo->exec("UPDATE `tiers` SET `display_name` = `name` WHERE `display_name` = ''");
    echo "   display_name set from name" . PHP_EOL;
    
    // Set daily_hash_allowance from daily_hashes where null
    $pdo->exec("UPDATE `tiers` SET `daily_hash_allowance` = `daily_hashes` WHERE `daily_hash_allowance` = 0");
    echo "   daily_hash_allowance set from daily_hashes" . PHP_EOL;
    
    // Set daily_spin_allowance from daily_spins where null
    $pdo->exec("UPDATE `tiers` SET `daily_spin_allowance` = `daily_spins` WHERE `daily_spin_allowance` = 0");
    echo "   daily_spin_allowance set from daily_spins" . PHP_EOL;
    
    // Set referral_requirement to 3 (default fixed) for existing tiers
    $pdo->exec("UPDATE `tiers` SET `referral_requirement` = 3 WHERE `referral_requirement` = 0");
    echo "   referral_requirement set to 3" . PHP_EOL;
    
    // 3. Insert the three default unified tiers
    echo "3. Inserting default unified tiers..." . PHP_EOL;
    
    // Beginner (id=5)
    $pdo->exec("
        INSERT IGNORE INTO `tiers` (`id`, `name`, `display_name`, `display_rating`, `daily_hashes`, `daily_spins`, `price`, `duration_days`, `referral_requirement`, `requirement_type`, `daily_hash_allowance`, `daily_spin_allowance`, `active`) VALUES
        (5, 'beginner', 'Beginner', '⭐⭐', 1, 1, 0.00, 30, 3, 'fixed', 1, 1, 1)
    ");
    echo "   Beginner tier inserted" . PHP_EOL;
    
    // Amateur (id=6)
    $pdo->exec("
        INSERT IGNORE INTO `tiers` (`id`, `name`, `display_name`, `display_rating`, `daily_hashes`, `daily_spins`, `price`, `duration_days`, `referral_requirement`, `requirement_type`, `daily_hash_allowance`, `daily_spin_allowance`, `active`) VALUES
        (6, 'amateur', 'Amateur', '⭐⭐⭐', 2, 2, 0.00, 30, 5, 'fixed', 2, 2, 1)
    ");
    echo "   Amateur tier inserted" . PHP_EOL;
    
    // Expert (id=7)
    $pdo->exec("
        INSERT IGNORE INTO `tiers` (`id`, `name`, `display_name`, `display_rating`, `daily_hashes`, `daily_spins`, `price`, `duration_days`, `referral_requirement`, `requirement_type`, `daily_hash_allowance`, `daily_spin_allowance`, `active`) VALUES
        (7, 'expert', 'Expert', '⭐⭐⭐⭐', 3, 3, 0.00, 30, 11, 'threshold', 3, 3, 1)
    ");
    echo "   Expert tier inserted" . PHP_EOL;
    
    // 4. Verify
    echo "4. Verifying tiers..." . PHP_EOL;
    $st = $pdo->query("SELECT * FROM `tiers` ORDER BY id");
    foreach ($st->fetchAll() as $row) {
        echo "   ID={$row['id']} name={$row['name']} display={$row['display_name']} rating={$row['display_rating']} hash_allow={$row['daily_hash_allowance']} spin_allow={$row['daily_spin_allowance']} ref_req={$row['referral_requirement']} type={$row['requirement_type']} active={$row['active']}" . PHP_EOL;
    }
    
    echo PHP_EOL . "=== Migration Complete ===" . PHP_EOL;
    
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
}
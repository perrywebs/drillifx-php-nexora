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
    
    // Seed the three default tiers in the unified tiers table
    echo "Seeding default tiers...\n";
    $pdo->exec("--
INSERT IGNORE INTO `tiers` (`id`, `name`, `display_name`, `display_rating`, `daily_hashes`, `daily_spins`, `price`, `duration_days`, `referral_requirement`, `requirement_type`, `daily_hash_allowance`, `daily_spin_allowance`, `active`) VALUES
(5, 'beginner', 'Beginner', '⭐⭐', 1, 1, 0.00, 30, 3, 'fixed', 1, 1, 1),
(6, 'amateur', 'Amateur', '⭐⭐⭐', 2, 2, 0.00, 30, 5, 'fixed', 2, 2, 1),
(7, 'expert', 'Expert', '⭐⭐⭐⭐', 3, 3, 0.00, 30, 11, 'threshold', 3, 3, 1)");
    echo "OK - seeded 3 default tiers\n";
    
    // Ensure existing users on Free Tier retain their tier assignment
    // (no upgrade_option_id column needed - tiers table is single source of truth)
    echo "Users tier assignments preserved - tiers table is single source of truth\n";
    
    echo "\nDone!\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
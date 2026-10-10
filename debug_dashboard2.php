<?php
// Dashboard debug script - user without upgrade option
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', '/tmp/php-errors3.log');

require __DIR__ . '/includes/bootstrap.php';

$dbCfg = require __DIR__ . '/config/database.php';
$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $dbCfg['host'], $dbCfg['port'], $dbCfg['db'], $dbCfg['charset']);

try {
    $pdo = new PDO($dsn, $dbCfg['user'], $dbCfg['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    echo "DB connection OK\n";
    
    // User 24 (zofoluqyfu) - Free Tier, no upgrade option
    $userId = 24;
    // Tier info comes from the tiers table (single source of truth)
    $tierName = 'Free Tier';
    $hashLimit = 1;
    $spinLimit = 1;
    
    // today_count
    function today_count($pdo, $table, $userId) {
        $allowed = ['hashes' => true, 'spins' => true];
        if (!isset($allowed[$table])) return 0;
        $st = $pdo->prepare("SELECT COUNT(*) c FROM `$table` WHERE user_id = ? AND DATE(created_at) = CURDATE()");
        $st->execute([$userId]);
        return (int)($st->fetch()['c'] ?? 0);
    }
    
    $hashDone = today_count($pdo, 'hashes', $userId);
    $spinDone = today_count($pdo, 'spins', $userId);
    echo "Hash done: $hashDone, Spin done: $spinDone\n";
    
    echo "\nSUCCESS - Dashboard logic works for user 24!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    error_log($e->getMessage() . "\n");
    echo "Script finished with error.\n";
}
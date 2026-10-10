<?php
// Debug test script
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', '/tmp/php-errors.log');

// Use PDO directly
$dbCfg = require __DIR__ . '/config/database.php';
$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $dbCfg['host'], $dbCfg['port'], $dbCfg['db'], $dbCfg['charset']);

try {
    $pdo = new PDO($dsn, $dbCfg['user'], $dbCfg['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    echo "DB connection OK\n";
    
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables: " . implode(", ", $tables) . "\n";
    
    echo "\n--- tiers ---\n";
    $r = $pdo->query("SELECT * FROM tiers")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($r as $row) { 
        print_r($row); 
        echo "\n"; 
    }
    
    echo "\n--- users (first 3) ---\n";
    $r = $pdo->query("SELECT id, username, tier_id FROM users LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($r as $row) { 
        print_r($row); 
        echo "\n"; 
    }
    
    echo "\nSUCCESS - Debug test works!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    error_log($e->getMessage() . "\n");
    echo "Script finished with error.\n";
}
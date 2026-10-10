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
    
    echo "=== Debug Dashboard (Free Tier) ===\n";
    
    // Check user's tier from unified tiers table
    $st = $pdo->prepare('SELECT * FROM tiers WHERE id = ? AND active = 1 LIMIT 1');
    $st->execute([1]);
    $uo = $st->fetch();
    echo "Beginner tier data: ";
    print_r($uo);
    
    $st = $pdo->prepare('SELECT * FROM tiers WHERE id = ? AND active = 1 LIMIT 1');
    $st->execute([2]);
    $uo = $st->fetch();
    echo "Amateur tier data: ";
    print_r($uo);
    
    $st = $pdo->prepare('SELECT * FROM tiers WHERE id = ? AND active = 1 LIMIT 1');
    $st->execute([3]);
    $uo = $st->fetch();
    echo "Expert tier data: ";
    print_r($uo);
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
<?php
$pdo = new PDO("mysql:host=localhost;port=3308;dbname=drillifx;charset=utf8mb4", "root", "secret");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$st = $pdo->prepare("SELECT * FROM tiers WHERE id > ? AND active = 1 ORDER BY id ASC LIMIT 2");
$st->execute([0]);
$r = $st->fetchAll();
print_r($r);
?>
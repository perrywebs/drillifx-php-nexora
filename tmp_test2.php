<?php
$pdo = new PDO("mysql:host=localhost;port=3308;dbname=drillifx;charset=utf8mb4", "root", "secret");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$st = $pdo->prepare("SELECT * FROM tiers WHERE id > ? AND active = 1 ORDER BY id ASC LIMIT 2");
$st->execute([5]);
$r = $st->fetchAll();
print_r("Current tier 5 (beginner): " . count($r) . " tiers found");
foreach ($r as $row) { echo "  id=" . $row['id'] . " name=" . $row['name'] . "\n"; }

$st2 = $pdo->query("SELECT * FROM tiers WHERE display_rating != '' ORDER BY id");
$r2 = $st2->fetchAll();
print_r("Upgrade tiers (display_rating != ''): " . count($r2) . " tiers found");
foreach ($r2 as $row) { echo "  id=" . $row['id'] . " name=" . $row['name'] . " rating=" . $row['display_rating'] . "\n"; }
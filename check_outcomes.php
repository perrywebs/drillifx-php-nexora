<?php
require 'includes/functions.php';
$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Check hash_outcomes table
echo "hash_outcomes columns:" . PHP_EOL;
$r = $pdo->query('DESCRIBE hash_outcomes')->fetchAll(PDO::FETCH_ASSOC);
foreach($r as $c) echo '  ' . $c['Field'] . ' (' . $c['Type'] . ')' . PHP_EOL;

// Check spin_outcomes table
echo "spin_outcomes columns:" . PHP_EOL;
$r = $pdo->query('DESCRIBE spin_outcomes')->fetchAll(PDO::FETCH_ASSOC);
foreach($r as $c) echo '  ' . $c['Field'] . ' (' . $c['Type'] . ')' . PHP_EOL;
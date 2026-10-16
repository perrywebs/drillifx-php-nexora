<?php
// Applies the payment-methods schema update safely (repeatable, idempotent).
// Usage (PowerShell): php database/migrate_payment_methods.php
// Uses the app's own config/database.php credentials; never prints secrets.
declare(strict_types=1);
$cfg = require __DIR__ . '/../config/database.php';
try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $cfg['host'], $cfg['port'], $cfg['db'], $cfg['charset']),
        $cfg['user'], $cfg['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );
} catch (PDOException $e) {
    fwrite(STDERR, "Connection failed (see error log).\n");
    error_log('migrate_payment_methods connection failed: ' . $e->getMessage());
    exit(1);
}
function hasColumn(PDO $pdo, string $table, string $col): bool {
    $st = $pdo->prepare('SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $st->execute([$table, $col]);
    return (bool)$st->fetchColumn();
}
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `payment_methods` (
      `id` bigint unsigned NOT NULL AUTO_INCREMENT,
      `name` varchar(100) NOT NULL,
      `description` text,
      `icon_path` varchar(255) DEFAULT NULL,
      `active` tinyint(1) NOT NULL DEFAULT 1,
      `display_order` int unsigned NOT NULL DEFAULT 0,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uk_payment_methods_name` (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `payment_method_fields` (
      `id` bigint unsigned NOT NULL AUTO_INCREMENT,
      `payment_method_id` bigint unsigned NOT NULL,
      `label` varchar(100) NOT NULL,
      `value` text NOT NULL,
      `display_type` enum('single_line','multiline','url','optional') NOT NULL DEFAULT 'single_line',
      `copy_enabled` tinyint(1) NOT NULL DEFAULT 0,
      `visible` tinyint(1) NOT NULL DEFAULT 1,
      `required_config` tinyint(1) NOT NULL DEFAULT 0,
      `display_order` int unsigned NOT NULL DEFAULT 0,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_fk_payment_method` (`payment_method_id`),
      CONSTRAINT `fk_payment_method_fields_method`
        FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    if (!hasColumn($pdo, 'deposits', 'payment_method_id')) {
        $pdo->exec('ALTER TABLE `deposits` ADD COLUMN `payment_method_id` bigint unsigned NULL DEFAULT NULL AFTER `tier_id`, ADD KEY `idx_deposits_payment_method` (`payment_method_id`)');
        echo "added deposits.payment_method_id\n";
    } else { echo "deposits.payment_method_id exists\n"; }
    if (!hasColumn($pdo, 'deposits', 'payment_method_name')) {
        $pdo->exec('ALTER TABLE `deposits` ADD COLUMN `payment_method_name` varchar(100) NULL DEFAULT NULL AFTER `payment_method_id`');
        echo "added deposits.payment_method_name\n";
    } else { echo "deposits.payment_method_name exists\n"; }
    if (!hasColumn($pdo, 'deposits', 'payment_snapshot')) {
        $pdo->exec('ALTER TABLE `deposits` ADD COLUMN `payment_snapshot` text NULL DEFAULT NULL AFTER `payment_method_name`');
        echo "added deposits.payment_snapshot\n";
    } else { echo "deposits.payment_snapshot exists\n"; }
    $pdo->exec("UPDATE `deposits` d JOIN `payment_methods` m ON m.id = d.payment_method_id
                SET d.payment_method_name = m.name
                WHERE (d.payment_method_name IS NULL OR d.payment_method_name = '')");
    echo "payment_methods migration applied OK\n";
} catch (PDOException $e) {
    fwrite(STDERR, "Migration failed (see error log).\n");
    error_log('migrate_payment_methods failed: ' . $e->getMessage());
    exit(1);
}

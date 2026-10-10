-- Drillifx Phase 1 schema (MySQL / MariaDB, phpMyAdmin-ready, utf8mb4)
-- Import via phpMyAdmin > Import.
-- On cPanel: create the database first ("MySQL Databases"; the name gets a
-- prefix like cpuser_drillifx), select it in phpMyAdmin, then import this
-- file. Either edit the CREATE/USE lines to your prefixed name or skip them
-- (phpMyAdmin imports into the selected database).
CREATE DATABASE IF NOT EXISTS `drillifx` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `drillifx`;

SET FOREIGN_KEY_CHECKS=0;

-- Tiers (matches upgrade.html cards + Free tier)
CREATE TABLE IF NOT EXISTS `tiers` (
  `id` TINYINT UNSIGNED NOT NULL PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `duration_days` INT UNSIGNED NOT NULL DEFAULT 0,
  `daily_hashes` INT UNSIGNED NOT NULL DEFAULT 1,
  `per_hash` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  `daily_spins` INT UNSIGNED NOT NULL DEFAULT 1,
  `max_spin` DECIMAL(18,4) NOT NULL DEFAULT 0.5000,
  `daily_earn` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  `weekly_earn` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  `monthly_earn` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  `total_earn` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tiers` (`id`,`name`,`price`,`duration_days`,`daily_hashes`,`per_hash`,`daily_spins`,`max_spin`,`daily_earn`,`weekly_earn`,`monthly_earn`,`total_earn`) VALUES
(0,'Free Tier',0.00,0,1,0.0000,1,0.5000,0.0000,0.0000,0.0000,0.0000),
(1,'Account Tier 1',25.00,20,4,12.5000,1,0.5000,50.0000,350.0000,1500.0000,1000.0000),
(2,'Account Tier 2',50.00,20,4,50.0000,1,0.5000,200.0000,1400.0000,6000.0000,4000.0000),
(3,'Account Tier 3',100.00,30,5,80.0000,10,0.5000,400.0000,2800.0000,12000.0000,12000.0000),
(4,'Account Tier 4',200.00,30,5,160.0000,10,0.5000,800.0000,5600.0000,24000.0000,24000.0000)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- Users
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(190) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `referral_code` VARCHAR(16) NOT NULL UNIQUE,
  `referred_by_id` INT UNSIGNED NULL,
  `tier_id` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `tier_expires_at` DATETIME NULL,
  `balance` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  `total_earned` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  `hash_earnings` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  `spin_earnings` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  `referral_earnings` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  `pin_hash` VARCHAR(255) NULL,
  `status` ENUM('active','suspended') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_users_referred_by` (`referred_by_id`),
  KEY `idx_users_tier` (`tier_id`),
  CONSTRAINT `fk_users_referred_by` FOREIGN KEY (`referred_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_users_tier` FOREIGN KEY (`tier_id`) REFERENCES `tiers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Password resets
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_token` (`token_hash`),
  KEY `idx_pr_user` (`user_id`),
  CONSTRAINT `fk_pr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Transactions (money = DECIMAL, never float)
CREATE TABLE IF NOT EXISTS `transactions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `type` VARCHAR(40) NOT NULL,
  `amount` DECIMAL(18,4) NOT NULL,
  `balance_after` DECIMAL(18,4) NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'completed',
  `reference` VARCHAR(40) NOT NULL UNIQUE,
  `description` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_tx_user_created` (`user_id`,`created_at`),
  KEY `idx_tx_type` (`type`),
  CONSTRAINT `fk_tx_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Hashes
CREATE TABLE IF NOT EXISTS `hashes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `tier_id` TINYINT UNSIGNED NOT NULL,
  `reward` DECIMAL(18,4) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_hash_user_created` (`user_id`,`created_at`),
  CONSTRAINT `fk_hash_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Spins
CREATE TABLE IF NOT EXISTS `spins` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `tier_id` TINYINT UNSIGNED NOT NULL,
  `reward` DECIMAL(18,4) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_spin_user_created` (`user_id`,`created_at`),
  CONSTRAINT `fk_spin_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Withdrawals
CREATE TABLE IF NOT EXISTS `withdrawals` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `amount` DECIMAL(18,4) NOT NULL,
  `currency_code` VARCHAR(10) NOT NULL DEFAULT 'USD',
  `fx_rate` DECIMAL(18,4) NOT NULL DEFAULT 1.0000,
  `local_amount` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  `method` VARCHAR(30) NOT NULL,
  `details` JSON NULL,
  `status` ENUM('pending','approved','rejected','paid') NOT NULL DEFAULT 'pending',
  `reference` VARCHAR(40) NOT NULL UNIQUE,
  `admin_note` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_wd_user_created` (`user_id`,`created_at`),
  KEY `idx_wd_status` (`status`),
  CONSTRAINT `fk_wd_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Deposits / upgrades (proof upload -> admin approves in Phase 2)
CREATE TABLE IF NOT EXISTS `deposits` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `tier_id` TINYINT UNSIGNED NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `proof_path` VARCHAR(255) NULL,
  `reference` VARCHAR(40) NOT NULL UNIQUE,
  `admin_note` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_dep_user_created` (`user_id`,`created_at`),
  CONSTRAINT `fk_dep_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_dep_tier` FOREIGN KEY (`tier_id`) REFERENCES `tiers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Referrals
CREATE TABLE IF NOT EXISTS `referrals` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `referrer_id` INT UNSIGNED NOT NULL,
  `referred_id` INT UNSIGNED NOT NULL UNIQUE,
  `reward` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  `status` ENUM('pending','earned') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_ref_referrer` (`referrer_id`),
  CONSTRAINT `fk_ref_referrer` FOREIGN KEY (`referrer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ref_referred` FOREIGN KEY (`referred_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notifications
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(120) NOT NULL,
  `message` VARCHAR(500) NOT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_notif_user_created` (`user_id`,`created_at`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Activity log (used by notifications ?tab=activity)
CREATE TABLE IF NOT EXISTS `activity_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `type` VARCHAR(40) NOT NULL,
  `message` VARCHAR(255) NOT NULL,
  `ip` VARCHAR(45) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_act_user_created` (`user_id`,`created_at`),
  CONSTRAINT `fk_act_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Support tickets (Phase 2 admin will manage; page stays static for now)
CREATE TABLE IF NOT EXISTS `support_tickets` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `subject` VARCHAR(150) NOT NULL,
  `message` TEXT NOT NULL,
  `status` ENUM('open','answered','closed') NOT NULL DEFAULT 'open',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_sup_user` (`user_id`),
  CONSTRAINT `fk_sup_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;

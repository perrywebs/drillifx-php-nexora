-- Drillifx Phase 3: Dynamic Upgrade Options
-- Add upgrade_options table and upgrade_option_id to users
-- Run after phase1 (drillifx.sql) and phase2 (phase2.sql)

SET FOREIGN_KEY_CHECKS=0;

-- ============================
-- New: upgrade_options table
-- ============================
CREATE TABLE IF NOT EXISTS `upgrade_options` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT 'Internal name e.g. beginner, amateur, expert',
  `display_name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT 'Display name e.g. Beginner, Amateur, Expert',
  `display_rating` VARCHAR(20) NOT NULL DEFAULT '' COMMENT 'Star rating e.g. ⭐⭐, ⭐⭐⭐, ⭐⭐⭐⭐',
  `daily_hash_allowance` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Hashes per day',
  `daily_spin_allowance` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Spins per day',
  `referral_requirement` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Number of referrals required',
  `requirement_type` ENUM('fixed','threshold') NOT NULL DEFAULT 'fixed' COMMENT 'fixed = exact number, threshold = more than X',
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Upgrade price in USD',
  `duration_days` INT UNSIGNED NOT NULL DEFAULT 30 COMMENT 'How long the upgrade lasts',
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Ordering for display',
  `active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Whether option is active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================
-- Modify: users table - add upgrade_option_id
-- ============================
ALTER TABLE `users` ADD COLUMN `upgrade_option_id` INT UNSIGNED NULL DEFAULT NULL AFTER `tier_id`,
  ADD CONSTRAINT `fk_users_upgrade_option` FOREIGN KEY (`upgrade_option_id`) REFERENCES `upgrade_options` (`id`) ON DELETE SET NULL;

-- ============================
-- Seed the first 3 upgrade options
-- Beginner, Amateur, Expert per requirements
-- ============================
INSERT IGNORE INTO `upgrade_options` (`id`,`name`,`display_name`,`display_rating`,`daily_hash_allowance`,`daily_spin_allowance`,`referral_requirement`,`requirement_type`,`price`,`duration_days`,`sort_order`,`active`) VALUES
(1,'beginner','Beginner','⭐⭐',1,1,3,'fixed',0.00,30,1,1),
(2,'amateur','Amateur','⭐⭐⭐',2,2,5,'fixed',0.00,30,2,1),
(3,'expert','Expert','⭐⭐⭐⭐',3,3,11,'threshold',0.00,30,3,1);

-- Ensure tier 0 (Free Tier) users without an upgrade option default to Beginner (id=1)
UPDATE `users` SET `upgrade_option_id` = 1 WHERE `upgrade_option_id` IS NULL AND `tier_id` = 0;

SET FOREIGN_KEY_CHECKS=1;
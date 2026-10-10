-- Drillifx Phase 2 migration (non-destructive; run AFTER database/drillifx.sql)
-- Import via phpMyAdmin > Import.
-- On cPanel the database has a prefixed name (e.g. cpuser_drillifx): either
-- edit the USE line below to match, or skip it (phpMyAdmin already selects
-- the target database on import).
USE `drillifx`;
SET FOREIGN_KEY_CHECKS=0;

-- Admins (separate from users)
CREATE TABLE IF NOT EXISTS `admins` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(190) NOT NULL UNIQUE,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('super_admin','admin','support','finance') NOT NULL DEFAULT 'admin',
  `status` ENUM('active','suspended') NOT NULL DEFAULT 'active',
  `last_login_at` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Key/value application settings
CREATE TABLE IF NOT EXISTS `settings` (
  `k` VARCHAR(100) NOT NULL PRIMARY KEY,
  `v` TEXT NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email templates (content only, never executed as code)
CREATE TABLE IF NOT EXISTS `email_templates` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `slug` VARCHAR(60) NOT NULL UNIQUE,
  `name` VARCHAR(120) NOT NULL,
  `subject` VARCHAR(190) NOT NULL,
  `body` TEXT NOT NULL,
  `enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email delivery log
CREATE TABLE IF NOT EXISTS `email_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NULL,
  `recipient` VARCHAR(190) NOT NULL,
  `template_slug` VARCHAR(60) NOT NULL,
  `subject` VARCHAR(190) NOT NULL,
  `status` ENUM('sent','failed','skipped') NOT NULL DEFAULT 'sent',
  `error` VARCHAR(500) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_el_user` (`user_id`),
  KEY `idx_el_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Configurable spin outcomes (weighted random)
CREATE TABLE IF NOT EXISTS `spin_outcomes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `label` VARCHAR(60) NOT NULL,
  `amount` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  `weight` INT UNSIGNED NOT NULL DEFAULT 1,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Predetermined spin outcomes per user (consumed once)
CREATE TABLE IF NOT EXISTS `user_spin_outcomes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `outcome_id` INT UNSIGNED NULL,
  `amount` DECIMAL(18,4) NOT NULL,
  `status` ENUM('pending','used','cancelled') NOT NULL DEFAULT 'pending',
  `assigned_by` INT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `used_at` DATETIME NULL,
  KEY `idx_uso_user_status` (`user_id`,`status`),
  CONSTRAINT `fk_uso_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_uso_outcome` FOREIGN KEY (`outcome_id`) REFERENCES `spin_outcomes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_uso_admin` FOREIGN KEY (`assigned_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Configurable hash outcomes (weighted random)
CREATE TABLE IF NOT EXISTS `hash_outcomes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `label` VARCHAR(60) NOT NULL,
  `amount` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  `weight` INT UNSIGNED NOT NULL DEFAULT 1,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Predetermined hash outcomes per user (consumed once)
CREATE TABLE IF NOT EXISTS `user_hash_outcomes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `outcome_id` INT UNSIGNED NULL,
  `amount` DECIMAL(18,4) NOT NULL,
  `status` ENUM('pending','used','cancelled') NOT NULL DEFAULT 'pending',
  `assigned_by` INT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `used_at` DATETIME NULL,
  KEY `idx_uho_user_status` (`user_id`,`status`),
  CONSTRAINT `fk_uho_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_uho_outcome` FOREIGN KEY (`outcome_id`) REFERENCES `hash_outcomes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_uho_admin` FOREIGN KEY (`assigned_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin audit trail
CREATE TABLE IF NOT EXISTS `admin_audit_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `admin_id` INT UNSIGNED NULL,
  `action` VARCHAR(80) NOT NULL,
  `target_type` VARCHAR(40) NULL,
  `target_id` VARCHAR(40) NULL,
  `description` VARCHAR(500) NOT NULL,
  `ip` VARCHAR(45) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_aal_admin_created` (`admin_id`,`created_at`),
  KEY `idx_aal_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email verification tokens
CREATE TABLE IF NOT EXISTS `email_verify_tokens` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `token_hash` CHAR(64) NOT NULL UNIQUE,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_evt_user` (`user_id`),
  CONSTRAINT `fk_evt_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Phase 1 users table: verification timestamp (nullable = legacy verified).
-- NOTE for MySQL (cPanel): MySQL does not support ADD COLUMN IF NOT EXISTS.
-- Safe to ignore error 1060 (duplicate column) if you re-import this file.
ALTER TABLE `users` ADD COLUMN `email_verified_at` DATETIME NULL AFTER `status`;

-- Default settings (INSERT IGNORE so re-imports never overwrite admin changes)
INSERT IGNORE INTO `settings` (`k`,`v`) VALUES
('site_name','Drillifyx'),
('site_title','Drillifyx - Hash & Earn USDC'),
('site_description','Hash, spin and earn USDC daily'),
('support_email','support@drillifyx.org'),
('contact_email','support@drillifyx.org'),
('timezone','UTC'),
('currency','USD'),
('maintenance_mode','0'),
('site_logo','/images/usd-coin-usdc-logo.png'),
('site_favicon',''),
('smtp_host',''),
('smtp_port','587'),
('smtp_user',''),
('smtp_pass',''),
('smtp_enc','tls'),
('mail_from_email','support@drillifyx.org'),
('mail_from_name','Drillifyx'),
('mail_enabled','1'),
('registration_enabled','1'),
('referral_enabled','1'),
('email_verification','0'),
('min_deposit','25'),
('min_withdraw','100'),
('fx_USD','1'),
('fx_NGN','1600'),
('fx_GHS','21.6'),
('referral_reward','10'),
('spin_enabled','1'),
('hash_enabled','1');

-- Spin outcomes mirror the Phase 1 wheel ($0.01-$0.50) so behaviour is unchanged
INSERT IGNORE INTO `spin_outcomes` (`id`,`label`,`amount`,`weight`,`active`,`sort`) VALUES
(1,'$0.01',0.0100,30,1,1),
(2,'$0.02',0.0200,24,1,2),
(3,'$0.05',0.0500,18,1,3),
(4,'$0.10',0.1000,12,1,4),
(5,'$0.15',0.1500,7,1,5),
(6,'$0.20',0.2000,5,1,6),
(7,'$0.25',0.2500,3,1,7),
(8,'$0.50',0.5000,1,1,8);

-- Hash outcomes: default single outcome (amount resolved per tier at runtime, see hash engine)
INSERT IGNORE INTO `hash_outcomes` (`id`,`label`,`amount`,`weight`,`active`,`sort`) VALUES
(1,'Standard hash',0.0000,100,1,1);

-- Email templates (no semicolons inside bodies by design)
INSERT IGNORE INTO `email_templates` (`slug`,`name`,`subject`,`body`,`enabled`) VALUES
('welcome','Welcome Email','Welcome to {{site_name}}','Hello {{name}}, Your {{site_name}} account has been created successfully. Start earning from your dashboard today. Your personal referral code is {{referral_code}}. Thank you for joining {{site_name}}',1),
('email_verify','Email Verification','Verify your email address','Hello {{name}}, Please verify your {{site_name}} account by opening this link within 24 hours. {{verify_link}} If you did not register, ignore this email',1),
('password_reset','Password Reset','Reset your {{site_name}} password','Hello {{name}}, Someone requested a password reset for your {{site_name}} account. Open this link within 1 hour to choose a new password. {{reset_link}} If you did not request this, you can safely ignore this email',1),
('password_changed','Password Changed','Your {{site_name}} password was changed','Hello {{name}}, Your {{site_name}} password was changed on {{date}}. If this was not you, contact {{support_email}} immediately',1),
('pin_changed','Withdrawal PIN Changed','Your withdrawal PIN was updated','Hello {{name}}, Your {{site_name}} withdrawal PIN was changed on {{date}}. If this was not you, contact {{support_email}} immediately',1),
('deposit_submitted','Deposit Submitted','Deposit {{reference}} received','Hello {{name}}, We received your {{tier_name}} deposit of ${{amount}} (reference {{reference}}). It is now under review and we will notify you once approved',1),
('deposit_approved','Deposit Approved','Deposit {{reference}} approved','Good news {{name}}, Your deposit {{reference}} was approved and {{tier_name}} is now active until {{expiry}}. Happy earning with {{site_name}}',1),
('deposit_rejected','Deposit Rejected','Update on deposit {{reference}}','Hello {{name}}, Your deposit {{reference}} could not be approved. Reason given: {{reason}}. Please contact {{support_email}} if you need help',1),
('withdrawal_submitted','Withdrawal Submitted','Withdrawal {{reference}} received','Hello {{name}}, Your withdrawal of ${{amount}} via {{method}} (reference {{reference}}) is pending. We will notify you once it is processed',1),
('withdrawal_approved','Withdrawal Approved','Withdrawal {{reference}} approved','Hello {{name}}, Your withdrawal of ${{amount}} (reference {{reference}}) has been approved and is being processed for payout',1),
('withdrawal_rejected','Withdrawal Rejected','Update on withdrawal {{reference}}','Hello {{name}}, Your withdrawal {{reference}} was rejected and ${{amount}} has been refunded to your balance. Reason given: {{reason}}',1),
('withdrawal_paid','Withdrawal Completed','Withdrawal {{reference}} completed','Hello {{name}}, Your withdrawal of ${{amount}} via {{method}} (reference {{reference}}) has been sent. Thank you for using {{site_name}}',1),
('upgrade_success','Upgrade Successful','Your {{tier_name}} is active','Congratulations {{name}}, Your {{tier_name}} upgrade is now active until {{expiry}}. Your new earning limits apply immediately',1),
('referral_earned','Referral Reward','You earned a referral reward','Hello {{name}}, {{referred_user}} upgraded to a paid tier. ${{amount}} has been added to your balance. Thank you for growing {{site_name}}',1),
('security_alert','Security Alert','Security alert on your {{site_name}} account','Hello {{name}}, {{event}} on {{date}}. If this was not you, secure your account and contact {{support_email}} immediately',1),
('test_email','Test Email','Test email from {{site_name}}','Hello, This is a test email sent from {{site_name}} on {{date}}. Your SMTP configuration is working correctly',1);

SET FOREIGN_KEY_CHECKS=1;

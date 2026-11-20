-- PhotoKY Database Initialization Script (Cleaned Schema - No Unused Tables)
-- MySQL 8.0 Compatible

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `photoky` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `photoky`;

-- Drop legacy unused tables
DROP TABLE IF EXISTS `folders`;

-- --------------------------------------------------------
-- Table: photos
-- Stores metadata, filename & relative file_path only (no BLOB)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `photos` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `uuid` VARCHAR(64) NOT NULL UNIQUE,
  `filename` VARCHAR(255) NOT NULL UNIQUE,
  `file_path` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Relative file path under uploads/',
  `original_name` VARCHAR(255) NOT NULL,
  `file_size` BIGINT UNSIGNED NOT NULL COMMENT 'File size in bytes',
  `mime_type` VARCHAR(100) NOT NULL,
  `file_ext` VARCHAR(10) NOT NULL,
  `width` INT UNSIGNED NOT NULL DEFAULT 0,
  `height` INT UNSIGNED NOT NULL DEFAULT 0,
  `delete_token` VARCHAR(64) NOT NULL UNIQUE,
  `title` VARCHAR(255) NULL,
  `description` TEXT NULL,
  `owner_name` VARCHAR(150) NULL COMMENT 'Photo owner display name',
  `owner_phone` VARCHAR(50) NULL COMMENT 'Photo owner phone number',
  `extra_info` TEXT NULL COMMENT 'Additional photo details / notes / credit',
  `is_public` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=Public in feed, 0=Unlisted',
  `is_approved` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0=Pending Admin Approval, 1=Approved, 2=Rejected',
  `views` INT UNSIGNED NOT NULL DEFAULT 0,
  `downloads` INT UNSIGNED NOT NULL DEFAULT 0,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` TEXT NULL,
  `exif_data` JSON NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_photos_uuid` (`uuid`),
  INDEX `idx_photos_delete_token` (`delete_token`),
  INDEX `idx_photos_owner` (`owner_name`),
  INDEX `idx_photos_public` (`is_public`),
  INDEX `idx_photos_approved` (`is_approved`),
  INDEX `idx_photos_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: comments
-- Linked to photos.id (Image ID) with nested Reply support
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `comments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `photo_id` INT UNSIGNED NOT NULL,
  `parent_id` INT UNSIGNED NULL DEFAULT NULL,
  `author_name` VARCHAR(100) NOT NULL,
  `content` TEXT NOT NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` VARCHAR(255) NULL,
  `is_approved` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_comments_photo` (`photo_id`),
  INDEX `idx_comments_parent` (`parent_id`),
  INDEX `idx_comments_ip_time` (`ip_address`, `created_at`),
  INDEX `idx_comments_created` (`created_at`),
  CONSTRAINT `fk_comments_photo` FOREIGN KEY (`photo_id`) REFERENCES `photos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_comments_parent` FOREIGN KEY (`parent_id`) REFERENCES `comments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: admins
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admins` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `recovery_code_hash` VARCHAR(255) NULL COMMENT 'Hashed security recovery key for password reset',
  `role` VARCHAR(20) NOT NULL DEFAULT 'admin',
  `last_login` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: admin_password_resets
-- Secure single-use hashed reset tokens with expiry
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin_password_resets` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `admin_id` INT UNSIGNED NOT NULL,
  `token_hash` VARCHAR(64) NOT NULL UNIQUE,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME NULL DEFAULT NULL,
  `ip_address` VARCHAR(45) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_resets_admin` (`admin_id`),
  INDEX `idx_resets_expires` (`expires_at`),
  CONSTRAINT `fk_resets_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: settings
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `setting_key` VARCHAR(50) PRIMARY KEY,
  `setting_value` TEXT NULL,
  `description` VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: system_logs
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `system_logs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `event` VARCHAR(100) NOT NULL,
  `details` TEXT NULL,
  `ip_address` VARCHAR(45) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_logs_event` (`event`),
  INDEX `idx_logs_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Initial Data Seeding
-- --------------------------------------------------------
INSERT INTO `admins` (`username`, `email`, `password_hash`, `recovery_code_hash`, `role`) VALUES
('admin', 'admin@photoky.local', '$2y$10$eQKjSHUGq5WzvTOWhJOTYeTQfwCWSK4XDVT0XflKK0YTNyEcVF74y', '$2y$10$cNXJPlMCtATnJMHhc7GmrurnYyZITyZp/hx.ka64ZN4l/3Ax4jeEq', 'superadmin')
ON DUPLICATE KEY UPDATE `password_hash` = VALUES(`password_hash`);

-- Default system settings
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) VALUES
('site_name', 'PhotoKY', 'Name of the website'),
('site_tagline', 'คลังรูปภาพออนไลน์และการแบ่งปันคุณภาพสูง', 'Hero slogan and subtitle'),
('max_upload_size_mb', '15', 'Maximum file size in MB per upload'),
('allowed_extensions', 'jpg,jpeg,png,webp,gif', 'Comma separated allowed extensions'),
('enable_public_gallery', '1', 'Show public photos in feed (1=yes, 0=no)'),
('maintenance_mode', '0', 'Maintenance mode status (0=off, 1=on)')
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;

SET FOREIGN_KEY_CHECKS = 1;

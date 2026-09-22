-- Migration to enhance push notification system
-- Adds support for contractors, delivery confirmation, and platform tracking

-- Add platform and contractor fields to notification_log
ALTER TABLE `notification_log` 
  ADD COLUMN `contractor_id` INT DEFAULT NULL AFTER `user_id`,
  ADD COLUMN `platform` VARCHAR(20) DEFAULT NULL COMMENT 'pwa, ios, android, web',
  ADD COLUMN `failed_count` INT DEFAULT 0 AFTER `success_count`,
  ADD COLUMN `delivery_status` ENUM('pending', 'sent', 'delivered', 'failed') DEFAULT 'pending' AFTER `failed_count`,
  ADD COLUMN `error_message` TEXT DEFAULT NULL AFTER `delivery_status`,
  ADD COLUMN `delivery_confirmed_at` DATETIME DEFAULT NULL AFTER `error_message`;

-- Add index for faster queries
ALTER TABLE `notification_log`
  ADD INDEX `idx_contractor_id` (`contractor_id`),
  ADD INDEX `idx_delivery_status` (`delivery_status`),
  ADD INDEX `idx_sent_at` (`sent_at`);

-- Add platform field to users table to track device type
ALTER TABLE `users`
  ADD COLUMN `device_platform` VARCHAR(20) DEFAULT NULL COMMENT 'pwa, ios, android, web' AFTER `fcm_token`;

-- Create notification_recipients table for tracking individual notification delivery
CREATE TABLE IF NOT EXISTS `notification_recipients` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `notification_log_id` INT NOT NULL,
  `user_id` INT DEFAULT NULL,
  `contractor_id` INT DEFAULT NULL,
  `fcm_token` VARCHAR(255) DEFAULT NULL,
  `platform` VARCHAR(20) DEFAULT NULL,
  `delivery_status` ENUM('pending', 'sent', 'delivered', 'failed') DEFAULT 'pending',
  `sent_at` DATETIME DEFAULT NULL,
  `delivered_at` DATETIME DEFAULT NULL,
  `failed_at` DATETIME DEFAULT NULL,
  `error_message` TEXT DEFAULT NULL,
  `fcm_response` TEXT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_notification_log_id` (`notification_log_id`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_contractor_id` (`contractor_id`),
  INDEX `idx_delivery_status` (`delivery_status`),
  FOREIGN KEY (`notification_log_id`) REFERENCES `notification_log`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

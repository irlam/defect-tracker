-- Defect Tracker production-readiness schema patch
-- Generated from the 2026-09-24 production dump review.
-- Safe to run against the live MySQL 8.4 database after taking a fresh backup.
-- Existing rows are preserved. Every schema operation is guarded for re-import.

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- Helper: execute a DDL statement only when the supplied information_schema
-- query returns zero. Recreated temporarily and removed at the end.
-- ---------------------------------------------------------------------------

DROP PROCEDURE IF EXISTS `dt_apply_if_missing`;
DELIMITER $$
CREATE PROCEDURE `dt_apply_if_missing`(
    IN object_count BIGINT,
    IN ddl_statement TEXT
)
BEGIN
    IF object_count = 0 THEN
        SET @dt_ddl = ddl_statement;
        PREPARE dt_statement FROM @dt_ddl;
        EXECUTE dt_statement;
        DEALLOCATE PREPARE dt_statement;
    END IF;
END$$
DELIMITER ;

-- ---------------------------------------------------------------------------
-- Defect deletion audit support required by /api/delete-defect.php
-- ---------------------------------------------------------------------------

SET @dt_count = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'defects'
      AND COLUMN_NAME = 'deleted_by'
);
CALL dt_apply_if_missing(
    @dt_count,
    'ALTER TABLE `defects` ADD COLUMN `deleted_by` INT DEFAULT NULL AFTER `deleted_at`'
);

SET @dt_count = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'defects'
      AND INDEX_NAME = 'idx_defects_deleted_by'
);
CALL dt_apply_if_missing(
    @dt_count,
    'ALTER TABLE `defects` ADD INDEX `idx_defects_deleted_by` (`deleted_by`)'
);

SET @dt_count = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'defects'
      AND CONSTRAINT_NAME = 'fk_defects_deleted_by'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
CALL dt_apply_if_missing(
    @dt_count,
    'ALTER TABLE `defects` ADD CONSTRAINT `fk_defects_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE'
);

-- ---------------------------------------------------------------------------
-- Notification delivery enhancements
-- ---------------------------------------------------------------------------

SET @dt_count = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notification_log' AND COLUMN_NAME = 'contractor_id');
CALL dt_apply_if_missing(@dt_count, 'ALTER TABLE `notification_log` ADD COLUMN `contractor_id` INT DEFAULT NULL AFTER `user_id`');

SET @dt_count = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notification_log' AND COLUMN_NAME = 'platform');
CALL dt_apply_if_missing(@dt_count, 'ALTER TABLE `notification_log` ADD COLUMN `platform` VARCHAR(20) DEFAULT NULL COMMENT ''pwa, ios, android, web'' AFTER `contractor_id`');

SET @dt_count = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notification_log' AND COLUMN_NAME = 'failed_count');
CALL dt_apply_if_missing(@dt_count, 'ALTER TABLE `notification_log` ADD COLUMN `failed_count` INT NOT NULL DEFAULT 0 AFTER `success_count`');

SET @dt_count = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notification_log' AND COLUMN_NAME = 'delivery_status');
CALL dt_apply_if_missing(@dt_count, 'ALTER TABLE `notification_log` ADD COLUMN `delivery_status` ENUM(''pending'',''sent'',''delivered'',''failed'') NOT NULL DEFAULT ''pending'' AFTER `failed_count`');

SET @dt_count = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notification_log' AND COLUMN_NAME = 'error_message');
CALL dt_apply_if_missing(@dt_count, 'ALTER TABLE `notification_log` ADD COLUMN `error_message` TEXT DEFAULT NULL AFTER `delivery_status`');

SET @dt_count = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notification_log' AND COLUMN_NAME = 'delivery_confirmed_at');
CALL dt_apply_if_missing(@dt_count, 'ALTER TABLE `notification_log` ADD COLUMN `delivery_confirmed_at` DATETIME DEFAULT NULL AFTER `error_message`');

SET @dt_count = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notification_log' AND INDEX_NAME = 'idx_contractor_id');
CALL dt_apply_if_missing(@dt_count, 'ALTER TABLE `notification_log` ADD INDEX `idx_contractor_id` (`contractor_id`)');

SET @dt_count = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notification_log' AND INDEX_NAME = 'idx_delivery_status');
CALL dt_apply_if_missing(@dt_count, 'ALTER TABLE `notification_log` ADD INDEX `idx_delivery_status` (`delivery_status`)');

SET @dt_count = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notification_log' AND INDEX_NAME = 'idx_sent_at');
CALL dt_apply_if_missing(@dt_count, 'ALTER TABLE `notification_log` ADD INDEX `idx_sent_at` (`sent_at`)');

SET @dt_count = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'device_platform');
CALL dt_apply_if_missing(@dt_count, 'ALTER TABLE `users` ADD COLUMN `device_platform` VARCHAR(20) DEFAULT NULL COMMENT ''pwa, ios, android, web'' AFTER `fcm_token`');

CREATE TABLE IF NOT EXISTS `notification_recipients` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `notification_log_id` INT NOT NULL,
    `user_id` INT DEFAULT NULL,
    `contractor_id` INT DEFAULT NULL,
    `fcm_token` VARCHAR(255) DEFAULT NULL,
    `platform` VARCHAR(20) DEFAULT NULL,
    `delivery_status` ENUM('pending', 'sent', 'delivered', 'failed') NOT NULL DEFAULT 'pending',
    `sent_at` DATETIME DEFAULT NULL,
    `delivered_at` DATETIME DEFAULT NULL,
    `failed_at` DATETIME DEFAULT NULL,
    `error_message` TEXT DEFAULT NULL,
    `fcm_response` TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_notification_log_id` (`notification_log_id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_contractor_id` (`contractor_id`),
    INDEX `idx_delivery_status` (`delivery_status`),
    CONSTRAINT `fk_notification_recipients_log`
        FOREIGN KEY (`notification_log_id`) REFERENCES `notification_log` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Missing Projects & Setup training module and lesson
-- ---------------------------------------------------------------------------

INSERT INTO `training_modules` (
    `slug`, `title`, `description`, `icon`, `accent`, `sort_order`, `is_active`
)
VALUES (
    'projects-setup',
    'Projects & Setup',
    'Create project records, maintain programme dates and status, and attach clearly labelled floor plans ready for defect use.',
    'bx-buildings',
    'cyan',
    50,
    1
) AS new
ON DUPLICATE KEY UPDATE
    `title` = new.`title`,
    `description` = new.`description`,
    `icon` = new.`icon`,
    `accent` = new.`accent`,
    `sort_order` = new.`sort_order`,
    `is_active` = new.`is_active`;

INSERT INTO `training_lessons` (
    `module_id`, `slug`, `title`, `description`, `estimated_minutes`,
    `difficulty`, `role_scope`, `content_json`, `audio_path`, `transcript`,
    `animation_path`, `sort_order`, `is_active`
)
SELECT
    m.`id`,
    'projects-setup',
    'Projects & Setup',
    'Learn to create a project, maintain programme information and prepare floor plans for defect use.',
    9,
    'Beginner',
    'admin,manager',
    '{"outcomes":["Create a project with clear programme information","Set project dates and status correctly","Review the project portfolio after setup","Upload and verify a floor plan against the correct project"],"steps":[{"title":"Open Projects Management","body":"Open the Projects directory and review the portfolio dashboard."},{"title":"Create the project","body":"Enter the project name, description, start date, end date and status."},{"title":"Check programme information","body":"Confirm dates and status reflect the real project programme because they drive progress and deadline indicators."},{"title":"Review the saved project","body":"Confirm the project appears in the portfolio with the expected status and programme information."},{"title":"Upload a floor plan","body":"Select the project, enter a meaningful floor name and level, then upload a supported drawing file."},{"title":"Verify the floor-plan library","body":"Check that the drawing is clearly named, linked to the right project and available for defect location."}],"try_url":"/projects.php"}',
    NULL,
    NULL,
    NULL,
    10,
    1
FROM `training_modules` m
WHERE m.`slug` = 'projects-setup'
ON DUPLICATE KEY UPDATE
    `module_id` = VALUES(`module_id`),
    `title` = VALUES(`title`),
    `description` = VALUES(`description`),
    `estimated_minutes` = VALUES(`estimated_minutes`),
    `difficulty` = VALUES(`difficulty`),
    `role_scope` = VALUES(`role_scope`),
    `content_json` = VALUES(`content_json`),
    `sort_order` = VALUES(`sort_order`),
    `is_active` = VALUES(`is_active`);

DROP PROCEDURE IF EXISTS `dt_apply_if_missing`;

-- ---------------------------------------------------------------------------
-- Verification output. Expected: one deleted_by column, one notification
-- recipient table, one device_platform column, and one Projects module/lesson.
-- ---------------------------------------------------------------------------

SELECT 'defects.deleted_by' AS verification, COUNT(*) AS found
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'defects' AND COLUMN_NAME = 'deleted_by'
UNION ALL
SELECT 'users.device_platform', COUNT(*)
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'device_platform'
UNION ALL
SELECT 'notification_recipients', COUNT(*)
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notification_recipients'
UNION ALL
SELECT 'projects-setup module', COUNT(*)
FROM `training_modules` WHERE `slug` = 'projects-setup'
UNION ALL
SELECT 'projects-setup lesson', COUNT(*)
FROM `training_lessons` WHERE `slug` = 'projects-setup';

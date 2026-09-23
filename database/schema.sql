-- Defect Tracker v3 schema-only baseline
-- All row data removed. Never commit production/test data here.

-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host intentionally omitted
-- Generation timestamp intentionally omitted
-- Server version: 8.0.43
-- PHP Version: 8.4.8

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database name intentionally omitted
--

-- --------------------------------------------------------

--
-- Stand-in structure for view `acceptance_history`
-- (See below for the actual view)
--
CREATE TABLE `acceptance_history` (
`acceptance_comment` text
,`accepted_at` datetime
,`accepted_by_user` varchar(50)
,`contractor_name` varchar(100)
,`defect_id` int
,`project_name` varchar(255)
,`status` enum('open','in_progress','pending','completed','verified','rejected','accepted')
,`title` varchar(200)
);

-- --------------------------------------------------------

--
-- Table structure for table `action_log`
--

CREATE TABLE `action_log` (
  `id` int NOT NULL,
  `action` varchar(255) NOT NULL,
  `user_id` int NOT NULL,
  `details` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int NOT NULL,
  `defect_id` int NOT NULL,
  `action` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` int NOT NULL,
  `action_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `details` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int NOT NULL,
  `entity_type` varchar(255) NOT NULL,
  `entity_id` int NOT NULL,
  `action` varchar(255) NOT NULL,
  `old_values` text,
  `user_id` int NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` int NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_by` int DEFAULT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_by` int DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `comments`
--

CREATE TABLE `comments` (
  `id` int NOT NULL,
  `defect_id` int NOT NULL,
  `user_id` int NOT NULL,
  `comment` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `company_settings`
--

CREATE TABLE `company_settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `contractors`
--

CREATE TABLE `contractors` (
  `id` bigint UNSIGNED NOT NULL,
  `company_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `trade` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address_line1` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_line2` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `county` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `postcode` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vat_number` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_number` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `insurance_info` text COLLATE utf8mb4_unicode_ci,
  `utr_number` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `status` enum('active','inactive','suspended') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_by` int NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  `license_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `contractors`
--
-- Data intentionally omitted from the schema-only baseline.

--
-- Triggers `contractors`
--
DELIMITER $$
CREATE TRIGGER `before_contractor_update` BEFORE UPDATE ON `contractors` FOR EACH ROW BEGIN
    DECLARE debug_info TEXT;
    
    SET debug_info = CONCAT('Contractor Update - ID: ', OLD.id,
                           ', Old Status: ', OLD.status,
                           ', New Status: ', NEW.status,
                           ', Updated By: ', NEW.updated_by,
                           ', Time: ', NOW());
    
    IF NEW.updated_by IS NOT NULL AND NOT EXISTS (
        SELECT 1 FROM users WHERE id = NEW.updated_by
    ) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Invalid user ID for updated_by';
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `contractors_before_insert` BEFORE INSERT ON `contractors` FOR EACH ROW BEGIN
    
    IF NEW.utr_number IS NOT NULL AND NEW.utr_number != '' AND 
       EXISTS (SELECT 1 FROM contractors WHERE utr_number = NEW.utr_number) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Duplicate UTR number';
    END IF;
    
    
    IF NEW.vat_number IS NOT NULL AND NEW.vat_number != '' AND 
       EXISTS (SELECT 1 FROM contractors WHERE vat_number = NEW.vat_number) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Duplicate VAT number';
    END IF;
    
    
    IF NEW.company_number IS NOT NULL AND NEW.company_number != '' AND 
       EXISTS (SELECT 1 FROM contractors WHERE company_number = NEW.company_number) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Duplicate Company number';
    END IF;
    
    
    SET NEW.created_at = NOW();
    SET NEW.updated_at = NOW();
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `contractors_before_update` BEFORE UPDATE ON `contractors` FOR EACH ROW BEGIN
    
    IF NEW.utr_number IS NOT NULL AND NEW.utr_number != '' AND 
       EXISTS (SELECT 1 FROM contractors WHERE utr_number = NEW.utr_number AND id != NEW.id) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Duplicate UTR number';
    END IF;
    
    
    IF NEW.vat_number IS NOT NULL AND NEW.vat_number != '' AND 
       EXISTS (SELECT 1 FROM contractors WHERE vat_number = NEW.vat_number AND id != NEW.id) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Duplicate VAT number';
    END IF;
    
    
    IF NEW.company_number IS NOT NULL AND NEW.company_number != '' AND 
       EXISTS (SELECT 1 FROM contractors WHERE company_number = NEW.company_number AND id != NEW.id) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Duplicate Company number';
    END IF;
    
    
    SET NEW.updated_at = NOW();
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `defects`
--

CREATE TABLE `defects` (
  `id` int NOT NULL,
  `project_id` int NOT NULL,
  `floor_plan_id` int NOT NULL,
  `reported_by` int NOT NULL,
  `assigned_to` bigint UNSIGNED DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `description` text,
  `status` enum('open','in_progress','pending','completed','verified','rejected','accepted') DEFAULT 'open',
  `closure_image` varchar(255) DEFAULT NULL,
  `rejection_comment` text,
  `acceptance_comment` text,
  `priority` enum('low','medium','high','critical') DEFAULT 'medium',
  `due_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `sync_status` enum('synced','pending','conflict') NOT NULL DEFAULT 'synced',
  `client_id` varchar(100) DEFAULT NULL,
  `sync_timestamp` datetime DEFAULT NULL,
  `device_id` varchar(100) DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `contractor_id` bigint UNSIGNED DEFAULT NULL,
  `created_by` int NOT NULL,
  `pin_x` float DEFAULT NULL,
  `pin_y` float DEFAULT NULL,
  `resolution_details` text,
  `attachment_paths` text,
  `comments` text,
  `deleted_at` datetime DEFAULT NULL,
  `rejection_status` enum('rejected','reopened') DEFAULT NULL,
  `reopened_reason` text,
  `accepted_by` int DEFAULT NULL,
  `accepted_at` datetime DEFAULT NULL,
  `reopened_at` datetime DEFAULT NULL,
  `reopened_by` int DEFAULT NULL,
  `has_pin` tinyint(1) NOT NULL DEFAULT '0',
  `rejected_by` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `defects`
--
-- Data intentionally omitted from the schema-only baseline.

--
-- Triggers `defects`
--
DELIMITER $$
CREATE TRIGGER `defects_before_update` BEFORE UPDATE ON `defects` FOR EACH ROW BEGIN
                    -- Only mark as pending if this is a direct update, not from the sync system
                    IF NEW.sync_status = 'synced' AND OLD.updated_at != NEW.updated_at THEN
                        SET NEW.sync_status = 'pending';
                        SET NEW.sync_timestamp = NOW();
                    END IF;
                END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `defect_assignments`
--

CREATE TABLE `defect_assignments` (
  `id` int NOT NULL,
  `defect_id` int NOT NULL,
  `user_id` int NOT NULL,
  `assigned_by` int NOT NULL,
  `assigned_at` datetime NOT NULL,
  `status` enum('active','completed','reassigned') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Table structure for table `defect_comments`
--

CREATE TABLE `defect_comments` (
  `id` int NOT NULL,
  `defect_id` int NOT NULL,
  `user_id` int NOT NULL,
  `comment` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `sync_status` enum('synced','pending','conflict') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'synced',
  `client_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sync_timestamp` datetime DEFAULT NULL,
  `device_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `defect_comments`
--
DELIMITER $$
CREATE TRIGGER `defect_comments_before_update` BEFORE UPDATE ON `defect_comments` FOR EACH ROW BEGIN
                    -- Only mark as pending if this is a direct update, not from the sync system
                    IF NEW.sync_status = 'synced' AND OLD.updated_at != NEW.updated_at THEN
                        SET NEW.sync_status = 'pending';
                        SET NEW.sync_timestamp = NOW();
                    END IF;
                END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `defect_history`
--

CREATE TABLE `defect_history` (
  `id` int NOT NULL,
  `defect_id` int NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `defect_history`
--
-- Data intentionally omitted from the schema-only baseline.

-- --------------------------------------------------------

--
-- Table structure for table `defect_images`
--

CREATE TABLE `defect_images` (
  `id` int NOT NULL,
  `defect_id` int NOT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `pin_path` varchar(255) DEFAULT NULL,
  `uploaded_by` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `uploaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sync_status` enum('synced','pending','conflict') NOT NULL DEFAULT 'synced',
  `client_id` varchar(100) DEFAULT NULL,
  `sync_timestamp` datetime DEFAULT NULL,
  `device_id` varchar(100) DEFAULT NULL,
  `is_edited` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `defect_images`
--
-- Data intentionally omitted from the schema-only baseline.

--
-- Triggers `defect_images`
--
DELIMITER $$
CREATE TRIGGER `defect_images_before_update` BEFORE UPDATE ON `defect_images` FOR EACH ROW BEGIN
                    -- Only mark as pending if this is a direct update, not from the sync system
                    IF NEW.sync_status = 'synced' AND OLD.uploaded_at != NEW.uploaded_at THEN
                        SET NEW.sync_status = 'pending';
                        SET NEW.sync_timestamp = NOW();
                    END IF;
                END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `export_logs`
--

CREATE TABLE `export_logs` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` int NOT NULL COMMENT 'References users.id',
  `export_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Type of export (e.g., dashboard, defects, contractors)',
  `file_format` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Format of the export (csv, excel, pdf)',
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Name of the exported file',
  `filesize` bigint UNSIGNED NOT NULL COMMENT 'Size of the exported file in bytes',
  `created_at` datetime NOT NULL COMMENT 'UTC timestamp of export creation',
  `downloaded_at` datetime DEFAULT NULL COMMENT 'UTC timestamp of first download',
  `download_count` int UNSIGNED DEFAULT '0' COMMENT 'Number of times downloaded',
  `status` enum('pending','completed','failed','expired') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `expiry_date` datetime NOT NULL COMMENT 'When the export file should be deleted',
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'IP address of the user who initiated the export',
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'User agent of the browser used for export'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `export_logs`
--
DELIMITER $$
CREATE TRIGGER `tr_export_logs_before_insert` BEFORE INSERT ON `export_logs` FOR EACH ROW BEGIN
    SET NEW.expiry_date = DATE_ADD(NEW.created_at, INTERVAL 30 DAY);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `floor_plans`
--

CREATE TABLE `floor_plans` (
  `id` int NOT NULL,
  `project_id` int NOT NULL,
  `floor_name` varchar(255) NOT NULL COMMENT 'Primary display name of the floor plan',
  `level` varchar(50) NOT NULL DEFAULT 'Level?' COMMENT 'Floor level designation (e.g., Ground Floor, Level 1)',
  `file_path` varchar(255) NOT NULL COMMENT 'Server path to the stored floor plan file',
  `image_path` varchar(255) DEFAULT NULL COMMENT 'Path to the image version if different from original file',
  `floor_number` int DEFAULT NULL COMMENT 'Numeric floor level for sorting (-1 for basement, 0 for ground, 1 for first, etc)',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Timestamp when record was created',
  `upload_date` datetime DEFAULT CURRENT_TIMESTAMP COMMENT 'Date and time when file was uploaded',
  `uploaded_by` int NOT NULL COMMENT 'User ID who uploaded the file',
  `file_size` int UNSIGNED DEFAULT NULL COMMENT 'Size of the file in bytes',
  `file_type` varchar(50) DEFAULT NULL COMMENT 'MIME type of the file',
  `description` text COMMENT 'Detailed description of the floor plan',
  `version` int DEFAULT '1' COMMENT 'Version number of the floor plan',
  `status` enum('active','inactive','deleted') NOT NULL DEFAULT 'active' COMMENT 'Current status of the floor plan',
  `last_modified` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last modification timestamp',
  `thumbnail_path` varchar(255) DEFAULT NULL COMMENT 'Path to thumbnail version of the floor plan',
  `original_filename` varchar(255) NOT NULL COMMENT 'Original name of the uploaded file',
  `created_by` int DEFAULT NULL COMMENT 'User ID who created the record (defaults to uploaded_by if not specified)',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Dumping data for table `floor_plans`
--
-- Data intentionally omitted from the schema-only baseline.

--
-- Triggers `floor_plans`
--
DELIMITER $$
CREATE TRIGGER `before_floor_plans_insert` BEFORE INSERT ON `floor_plans` FOR EACH ROW BEGIN
    IF NEW.created_by IS NULL THEN
        SET NEW.created_by = NEW.uploaded_by;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_log`
--

CREATE TABLE `maintenance_log` (
  `id` int NOT NULL,
  `action` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `tables_affected` text COLLATE utf8mb4_general_ci,
  `user_id` int DEFAULT NULL,
  `username` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `execution_time` datetime NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'success',
  `details` text COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `maintenance_log`
--
-- Data intentionally omitted from the schema-only baseline.

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `type` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `message` text COLLATE utf8mb4_general_ci NOT NULL,
  `link_url` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notification_log`
--

CREATE TABLE `notification_log` (
  `id` int NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `target_type` varchar(50) NOT NULL,
  `user_id` int DEFAULT NULL,
  `defect_id` int DEFAULT NULL,
  `success_count` int DEFAULT '0',
  `sent_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` int NOT NULL,
  `permission_name` varchar(100) DEFAULT NULL,
  `permission_key` varchar(100) DEFAULT NULL,
  `name` varchar(50) NOT NULL,
  `description` text,
  `created_by` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` varchar(50) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `permissions`
--
-- Data intentionally omitted from the schema-only baseline.

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `notes` text,
  `created_by` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `status` varchar(50) DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_active` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `projects`
--
-- Data intentionally omitted from the schema-only baseline.

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text,
  `created_by` varchar(50) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `roles`
--
-- Data intentionally omitted from the schema-only baseline.

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `role_id` int NOT NULL,
  `permission_id` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `role_permissions`
--
-- Data intentionally omitted from the schema-only baseline.

-- --------------------------------------------------------

--
-- Table structure for table `sync_conflicts`
--

CREATE TABLE `sync_conflicts` (
  `id` int NOT NULL,
  `sync_queue_id` int NOT NULL,
  `entity_type` enum('defect','defect_comment','defect_image') NOT NULL,
  `entity_id` int NOT NULL,
  `server_data` longtext NOT NULL,
  `client_data` longtext NOT NULL,
  `resolved` tinyint(1) NOT NULL DEFAULT '0',
  `resolution_type` enum('server_wins','client_wins','merge','manual') DEFAULT NULL,
  `resolved_by` varchar(50) DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Table structure for table `sync_devices`
--

CREATE TABLE `sync_devices` (
  `id` int NOT NULL,
  `device_id` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `device_name` varchar(100) DEFAULT NULL,
  `device_type` varchar(50) DEFAULT NULL,
  `last_sync` datetime DEFAULT NULL,
  `last_ip` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Table structure for table `sync_logs`
--

CREATE TABLE `sync_logs` (
  `id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `device_id` varchar(100) DEFAULT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime NOT NULL,
  `items_processed` int NOT NULL DEFAULT '0',
  `items_succeeded` int NOT NULL DEFAULT '0',
  `items_failed` int NOT NULL DEFAULT '0',
  `items_conflicted` int NOT NULL DEFAULT '0',
  `sync_direction` enum('upload','download','bidirectional') NOT NULL DEFAULT 'bidirectional',
  `status` enum('success','partial','failed') NOT NULL,
  `message` text,
  `details` longtext
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Dumping data for table `sync_logs`
--
-- Data intentionally omitted from the schema-only baseline.

-- --------------------------------------------------------

--
-- Table structure for table `sync_queue`
--

CREATE TABLE `sync_queue` (
  `id` int NOT NULL,
  `action` enum('create','update','delete') NOT NULL,
  `entity_type` enum('defect','defect_comment','defect_image') NOT NULL,
  `entity_id` int NOT NULL,
  `server_id` int DEFAULT NULL,
  `data` longtext,
  `base_timestamp` datetime DEFAULT NULL,
  `status` enum('pending','processing','completed','failed','conflict','awaiting_user_input') NOT NULL DEFAULT 'pending',
  `attempts` int NOT NULL DEFAULT '0',
  `force_sync` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `processed_at` datetime DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `device_id` varchar(100) DEFAULT NULL,
  `result` longtext
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Table structure for table `sync_settings`
--

CREATE TABLE `sync_settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text NOT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Table structure for table `system_logs`
--

CREATE TABLE `system_logs` (
  `id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `action` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `action_by` int DEFAULT NULL,
  `action_at` datetime NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `details` text COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_logs`
--
-- Data intentionally omitted from the schema-only baseline.

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_type` enum('admin','manager','contractor','inspector','viewer','client') COLLATE utf8mb4_unicode_ci DEFAULT 'viewer',
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `created_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `full_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','project_manager','contractor','client') COLLATE utf8mb4_unicode_ci NOT NULL,
  `avatar_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `theme_preference` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'light',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `role_id` int DEFAULT NULL,
  `contractor_id` int DEFAULT NULL,
  `contractor_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contractor_trade` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fcm_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--
-- Data intentionally omitted from the schema-only baseline.

--
-- Triggers `users`
--
DELIMITER $$
CREATE TRIGGER `users_insert_trigger` BEFORE INSERT ON `users` FOR EACH ROW BEGIN
    SET NEW.created_at = NOW();
    SET NEW.created_by = COALESCE(NEW.created_by, 'system');
    SET NEW.updated_at = NOW();
    SET NEW.updated_by = COALESCE(NEW.updated_by, 'system');
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `users_update_trigger` BEFORE UPDATE ON `users` FOR EACH ROW BEGIN
    SET NEW.updated_at = NOW();
    SET NEW.updated_by = COALESCE(NEW.updated_by, 'system');
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `user_logs`
--

CREATE TABLE `user_logs` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `action` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action_by` int NOT NULL,
  `action_at` datetime NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `details` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_logs`
--
-- Data intentionally omitted from the schema-only baseline.

-- --------------------------------------------------------

--
-- Table structure for table `user_permissions`
--

CREATE TABLE `user_permissions` (
  `user_id` int NOT NULL,
  `permission_id` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `user_recent_descriptions`
--

CREATE TABLE `user_recent_descriptions` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `description` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `user_recent_descriptions`
--
-- Data intentionally omitted from the schema-only baseline.

-- --------------------------------------------------------

--
-- Table structure for table `user_roles`
--

CREATE TABLE `user_roles` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `role_id` int NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_roles`
--
-- Data intentionally omitted from the schema-only baseline.

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `session_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `logged_in_at` datetime NOT NULL,
  `logged_out_at` datetime DEFAULT NULL,
  `last_activity` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `action_log`
--
ALTER TABLE `action_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_activity_defect` (`defect_id`),
  ADD KEY `idx_activity_user` (`user_id`),
  ADD KEY `idx_activity_created` (`created_at`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_category_name` (`name`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_category_status` (`deleted_at`),
  ADD KEY `idx_category_created_at` (`created_at`),
  ADD KEY `idx_categories_deleted_at` (`deleted_at`),
  ADD KEY `fk_categories_deleted_by` (`deleted_by`),
  ADD KEY `fk_categories_updated_by` (`updated_by`);

--
-- Indexes for table `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `defect_id` (`defect_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `company_settings`
--
ALTER TABLE `company_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `contractors`
--
ALTER TABLE `contractors`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_trade` (`trade`),
  ADD KEY `idx_county` (`county`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `idx_updated_by` (`updated_by`),
  ADD KEY `idx_deleted_by` (`deleted_by`),
  ADD KEY `id` (`id`);
ALTER TABLE `contractors` ADD FULLTEXT KEY `ft_company_contact` (`company_name`,`contact_name`);

--
-- Indexes for table `defects`
--
ALTER TABLE `defects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `floor_plan_id` (`floor_plan_id`),
  ADD KEY `fk_project_id` (`project_id`),
  ADD KEY `fk_reported_by` (`reported_by`),
  ADD KEY `fk_contractor_id` (`contractor_id`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `idx_deleted_at` (`deleted_at`),
  ADD KEY `fk_assigned_to_contractor` (`assigned_to`),
  ADD KEY `reopened_by` (`reopened_by`),
  ADD KEY `idx_defects_accepted_by` (`accepted_by`),
  ADD KEY `idx_defects_accepted_at` (`accepted_at`),
  ADD KEY `sync_status` (`sync_status`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `fk_rejected_by` (`rejected_by`);

--
-- Indexes for table `defect_assignments`
--
ALTER TABLE `defect_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `defect_id` (`defect_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `assigned_by` (`assigned_by`);

--
-- Indexes for table `defect_comments`
--
ALTER TABLE `defect_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `defect_id` (`defect_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `sync_status` (`sync_status`),
  ADD KEY `client_id` (`client_id`);

--
-- Indexes for table `defect_history`
--
ALTER TABLE `defect_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_defect_id` (`defect_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_updated_by` (`updated_by`);

--
-- Indexes for table `defect_images`
--
ALTER TABLE `defect_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `defect_id` (`defect_id`),
  ADD KEY `uploaded_by` (`uploaded_by`),
  ADD KEY `sync_status` (`sync_status`),
  ADD KEY `client_id` (`client_id`);

--
-- Indexes for table `export_logs`
--
ALTER TABLE `export_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_status_expiry` (`status`,`expiry_date`),
  ADD KEY `idx_filename` (`filename`);

--
-- Indexes for table `floor_plans`
--
ALTER TABLE `floor_plans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_upload_date` (`upload_date`),
  ADD KEY `idx_uploaded_by` (`uploaded_by`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_floor_plans_project` (`project_id`),
  ADD KEY `idx_floor_plans_created_at` (`created_at`),
  ADD KEY `idx_floor_plans_floor_name` (`floor_name`),
  ADD KEY `idx_floor_plans_original_filename` (`original_filename`),
  ADD KEY `idx_floor_plans_level` (`level`),
  ADD KEY `idx_floor_plans_floor_number` (`floor_number`),
  ADD KEY `idx_floor_plans_version` (`version`),
  ADD KEY `idx_floor_plans_last_modified` (`last_modified`),
  ADD KEY `fk_floor_plans_created_by` (`created_by`);

--
-- Indexes for table `maintenance_log`
--
ALTER TABLE `maintenance_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_execution_time` (`execution_time`),
  ADD KEY `idx_action` (`action`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `notification_log`
--
ALTER TABLE `notification_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD UNIQUE KEY `uk_permission_key` (`permission_key`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`role_id`,`permission_id`),
  ADD KEY `permission_id` (`permission_id`);

--
-- Indexes for table `sync_conflicts`
--
ALTER TABLE `sync_conflicts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `entity_type` (`entity_type`,`entity_id`),
  ADD KEY `resolved` (`resolved`),
  ADD KEY `sync_queue_id` (`sync_queue_id`);

--
-- Indexes for table `sync_devices`
--
ALTER TABLE `sync_devices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `device_id` (`device_id`),
  ADD KEY `username` (`username`);

--
-- Indexes for table `sync_logs`
--
ALTER TABLE `sync_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `username` (`username`),
  ADD KEY `start_time` (`start_time`);

--
-- Indexes for table `sync_queue`
--
ALTER TABLE `sync_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `status` (`status`),
  ADD KEY `entity_type` (`entity_type`,`entity_id`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `sync_settings`
--
ALTER TABLE `sync_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `action_by` (`action_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_user_type` (`user_type`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `role_id` (`role_id`);

--
-- Indexes for table `user_logs`
--
ALTER TABLE `user_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_action_by` (`action_by`);

--
-- Indexes for table `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD PRIMARY KEY (`user_id`,`permission_id`),
  ADD KEY `permission_id` (`permission_id`);

--
-- Indexes for table `user_recent_descriptions`
--
ALTER TABLE `user_recent_descriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_role` (`user_id`,`role_id`),
  ADD KEY `fk_user_roles_role` (`role_id`),
  ADD KEY `fk_user_roles_created_by` (`created_by`),
  ADD KEY `fk_user_roles_updated_by` (`updated_by`),
  ADD KEY `fk_user_roles_deleted_by` (`deleted_by`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `session_id` (`session_id`),
  ADD KEY `last_activity` (`last_activity`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `action_log`
--
ALTER TABLE `action_log`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `comments`
--
ALTER TABLE `comments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `contractors`
--
ALTER TABLE `contractors`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `defects`
--
ALTER TABLE `defects`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `defect_assignments`
--
ALTER TABLE `defect_assignments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `defect_comments`
--
ALTER TABLE `defect_comments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `defect_history`
--
ALTER TABLE `defect_history`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `defect_images`
--
ALTER TABLE `defect_images`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `export_logs`
--
ALTER TABLE `export_logs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `floor_plans`
--
ALTER TABLE `floor_plans`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `maintenance_log`
--
ALTER TABLE `maintenance_log`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notification_log`
--
ALTER TABLE `notification_log`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `sync_conflicts`
--
ALTER TABLE `sync_conflicts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sync_devices`
--
ALTER TABLE `sync_devices`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sync_logs`
--
ALTER TABLE `sync_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `sync_queue`
--
ALTER TABLE `sync_queue`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `user_logs`
--
ALTER TABLE `user_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `user_recent_descriptions`
--
ALTER TABLE `user_recent_descriptions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `user_roles`
--
ALTER TABLE `user_roles`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------

--
-- Structure for view `acceptance_history`
--
DROP TABLE IF EXISTS `acceptance_history`;

CREATE ALGORITHM=UNDEFINED DEFINER=`k87747_defecttracker`@`%` SQL SECURITY DEFINER VIEW `acceptance_history`  AS SELECT `d`.`id` AS `defect_id`, `d`.`title` AS `title`, `d`.`status` AS `status`, `d`.`acceptance_comment` AS `acceptance_comment`, `u`.`username` AS `accepted_by_user`, `d`.`accepted_at` AS `accepted_at`, `p`.`name` AS `project_name`, `c`.`company_name` AS `contractor_name` FROM (((`defects` `d` left join `users` `u` on((`d`.`accepted_by` = `u`.`id`))) left join `projects` `p` on((`d`.`project_id` = `p`.`id`))) left join `contractors` `c` on((`d`.`contractor_id` = `c`.`id`))) WHERE ((`d`.`status` = 'accepted') OR (`d`.`accepted_at` is not null)) ;

--
-- Table structure for table `training_modules`
--

CREATE TABLE `training_modules` (
  `id` int NOT NULL AUTO_INCREMENT,
  `slug` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `icon` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'bx-book-open',
  `accent` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'blue',
  `sort_order` int NOT NULL DEFAULT '100',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_training_modules_slug` (`slug`),
  KEY `idx_training_modules_active_sort` (`is_active`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `training_lessons`
--

CREATE TABLE `training_lessons` (
  `id` int NOT NULL AUTO_INCREMENT,
  `module_id` int NOT NULL,
  `slug` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `estimated_minutes` smallint unsigned NOT NULL DEFAULT '5',
  `difficulty` enum('Beginner','Intermediate','Advanced') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Beginner',
  `role_scope` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'all',
  `content_json` longtext COLLATE utf8mb4_unicode_ci,
  `audio_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transcript` mediumtext COLLATE utf8mb4_unicode_ci,
  `animation_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '100',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_training_lessons_slug` (`slug`),
  KEY `idx_training_lessons_module_sort` (`module_id`,`is_active`,`sort_order`),
  CONSTRAINT `fk_training_lessons_module` FOREIGN KEY (`module_id`) REFERENCES `training_modules` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `training_progress`
--

CREATE TABLE `training_progress` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `lesson_id` int NOT NULL,
  `status` enum('not_started','in_progress','completed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'not_started',
  `progress_percent` tinyint unsigned NOT NULL DEFAULT '0',
  `last_position` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_training_progress_user_lesson` (`user_id`,`lesson_id`),
  KEY `idx_training_progress_user_status` (`user_id`,`status`),
  KEY `idx_training_progress_lesson` (`lesson_id`),
  CONSTRAINT `fk_training_progress_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_training_progress_lesson` FOREIGN KEY (`lesson_id`) REFERENCES `training_lessons` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_training_progress_percent` CHECK ((`progress_percent` between 0 and 100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Data intentionally omitted from the training schema baseline.
--

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `fk_activity_defect` FOREIGN KEY (`defect_id`) REFERENCES `defects` (`id`),
  ADD CONSTRAINT `fk_activity_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `categories_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `categories_ibfk_3` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_categories_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_categories_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `comments`
--
ALTER TABLE `comments`
  ADD CONSTRAINT `comments_ibfk_1` FOREIGN KEY (`defect_id`) REFERENCES `defects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `contractors`
--
ALTER TABLE `contractors`
  ADD CONSTRAINT `fk_contractors_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_contractors_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_contractors_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `defects`
--
ALTER TABLE `defects`
  ADD CONSTRAINT `defects_ibfk_2` FOREIGN KEY (`floor_plan_id`) REFERENCES `floor_plans` (`id`),
  ADD CONSTRAINT `defects_ibfk_3` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `defects_ibfk_4` FOREIGN KEY (`reopened_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_assigned_to_contractor` FOREIGN KEY (`assigned_to`) REFERENCES `contractors` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_contractor_id` FOREIGN KEY (`contractor_id`) REFERENCES `contractors` (`id`),
  ADD CONSTRAINT `fk_defect_contractor` FOREIGN KEY (`contractor_id`) REFERENCES `contractors` (`id`),
  ADD CONSTRAINT `fk_defects_accepted_by` FOREIGN KEY (`accepted_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_project_id` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rejected_by` FOREIGN KEY (`rejected_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_reported_by` FOREIGN KEY (`reported_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `defect_assignments`
--
ALTER TABLE `defect_assignments`
  ADD CONSTRAINT `defect_assignments_ibfk_1` FOREIGN KEY (`defect_id`) REFERENCES `defects` (`id`),
  ADD CONSTRAINT `defect_assignments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `defect_assignments_ibfk_3` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `defect_comments`
--
ALTER TABLE `defect_comments`
  ADD CONSTRAINT `defect_comments_ibfk_1` FOREIGN KEY (`defect_id`) REFERENCES `defects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `defect_comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `defect_history`
--
ALTER TABLE `defect_history`
  ADD CONSTRAINT `defect_history_ibfk_1` FOREIGN KEY (`defect_id`) REFERENCES `defects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `defect_images`
--
ALTER TABLE `defect_images`
  ADD CONSTRAINT `defect_images_ibfk_1` FOREIGN KEY (`defect_id`) REFERENCES `defects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `defect_images_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `export_logs`
--
ALTER TABLE `export_logs`
  ADD CONSTRAINT `fk_export_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `floor_plans`
--
ALTER TABLE `floor_plans`
  ADD CONSTRAINT `fk_floor_plans_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_floor_plans_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_floor_plans_uploaded_by` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sync_conflicts`
--
ALTER TABLE `sync_conflicts`
  ADD CONSTRAINT `sync_conflicts_ibfk_1` FOREIGN KEY (`sync_queue_id`) REFERENCES `sync_queue` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD CONSTRAINT `system_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `system_logs_ibfk_2` FOREIGN KEY (`action_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);

--
-- Constraints for table `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD CONSTRAINT `user_permissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_recent_descriptions`
--
ALTER TABLE `user_recent_descriptions`
  ADD CONSTRAINT `user_recent_descriptions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD CONSTRAINT `fk_user_roles_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_user_roles_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_user_roles_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_user_roles_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_user_roles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

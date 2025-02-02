-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Feb 02, 2025 at 07:49 AM
-- Server version: 10.6.20-MariaDB
-- PHP Version: 8.3.15

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `dvntrack_defect-manager`
--

-- --------------------------------------------------------

--
-- Table structure for table `action_log`
--

CREATE TABLE `action_log` (
  `id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `user_id` int(11) NOT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `action_log`
--

INSERT INTO `action_log` (`id`, `action`, `user_id`, `details`, `created_at`) VALUES
(1, 'create_defect', 22, '{\"defect_id\":46,\"project_id\":11,\"reported_by\":22,\"assigned_to\":28,\"pin_image\":\"floor_plan_pin_46_20250129210749.png\",\"uploaded_images\":[\"uploads\\/images\\/Screenshot 2024-01-25 202814.png\"]}', '2025-01-29 21:07:51'),
(2, 'create_defect', 22, '{\"defect_id\":47,\"project_id\":11,\"reported_by\":22,\"assigned_to\":28,\"pin_image\":\"floor_plan_pin_47_20250129211619.png\",\"uploaded_images\":[\"uploads\\/images\\/Screenshot 2024-01-25 202814.png\"]}', '2025-01-29 21:16:21'),
(3, 'create_defect', 22, '{\"defect_id\":48,\"project_id\":11,\"reported_by\":22,\"assigned_to\":28,\"pin_image\":\"floor_plan_pin_48_20250129212247.png\",\"uploaded_images\":[\"uploads\\/images\\/Screenshot 2024-01-25 202814.png\"]}', '2025-01-29 21:22:49'),
(4, 'create_defect', 22, '{\"defect_id\":49,\"project_id\":11,\"reported_by\":22,\"assigned_to\":27,\"pin_image\":\"https:\\/\\/defects.dvntracker.site\\/floor_plan_pin_49_20250129220156.png\",\"uploaded_images\":[\"https:\\/\\/defects.dvntracker.site\\/uploads\\/images\\/Screenshot 2024-01-25 202840.png\"]}', '2025-01-29 22:01:58'),
(5, 'create_defect', 22, '{\"defect_id\":50,\"project_id\":11,\"reported_by\":22,\"assigned_to\":28,\"pin_image\":\"https:\\/\\/defects.dvntracker.site\\/floor_plan_pin_50_20250130150126.png\",\"uploaded_images\":[\"https:\\/\\/defects.dvntracker.site\\/uploads\\/images\\/LT-07.png\"]}', '2025-01-30 15:01:29'),
(6, 'create_defect', 22, '{\"defect_id\":54,\"project_id\":11,\"reported_by\":22,\"assigned_to\":28,\"pin_image\":\"\\/home\\/dvntrack\\/defects.dvntracker.site\\/uploads\\/defect_54\\/image.png\",\"uploaded_images\":[\"\\/home\\/dvntrack\\/defects.dvntracker.site\\/uploads\\/defect_54\\/20250130184201_679bc7f9bd82f_Screenshot 2024-01-25 202814.png\"]}', '2025-01-30 18:42:01'),
(7, 'create_defect', 22, '{\"defect_id\":55,\"project_id\":11,\"reported_by\":22,\"assigned_to\":28,\"pin_image\":\"\\/home\\/dvntrack\\/defects.dvntracker.site\\/uploads\\/defects\\/defect_55\\/image.png\",\"uploaded_images\":[\"\\/home\\/dvntrack\\/defects.dvntracker.site\\/uploads\\/defects\\/defect_55\\/20250130205945_679be841c354f_Screenshot 2024-01-25 202814.png\"]}', '2025-01-30 20:59:45'),
(8, 'create_defect', 22, '{\"defect_id\":56,\"project_id\":11,\"reported_by\":22,\"assigned_to\":28,\"pin_image\":\"\\/home\\/dvntrack\\/defects.dvntracker.site\\/uploads\\/defects\\/defect_56\\/image.png\",\"uploaded_images\":[\"\\/home\\/dvntrack\\/defects.dvntracker.site\\/uploads\\/defects\\/defect_56\\/20250130212044_679bed2c91c61_Screenshot 2024-01-25 202814.png\"]}', '2025-01-30 21:20:44'),
(9, 'create_defect', 22, '{\"defect_id\":57,\"project_id\":11,\"reported_by\":22,\"assigned_to\":27,\"pin_image\":\"\\/home\\/dvntrack\\/defects.dvntracker.site\\/uploads\\/defects\\/defect_57\\/image.png\",\"uploaded_images\":[\"\\/home\\/dvntrack\\/defects.dvntracker.site\\/uploads\\/defects\\/defect_57\\/20250130212238_679bed9ee3526_Screenshot 2024-01-25 202814.png\"]}', '2025-01-30 21:22:38'),
(10, 'create_defect', 22, '{\"defect_id\":58,\"project_id\":11,\"reported_by\":22,\"assigned_to\":28,\"pin_image\":\"\\/home\\/dvntrack\\/defects.dvntracker.site\\/uploads\\/defects\\/defect_58\\/image.png\",\"uploaded_images\":[\"\\/home\\/dvntrack\\/defects.dvntracker.site\\/uploads\\/defects\\/defect_58\\/20250130212643_679bee93e73d1_Screenshot 2024-01-25 202814.png\"]}', '2025-01-30 21:26:43'),
(11, 'create_defect', 22, '{\"defect_id\":59,\"project_id\":11,\"reported_by\":22,\"assigned_to\":28,\"pin_image\":\"\\/home\\/dvntrack\\/defects.dvntracker.site\\/uploads\\/defects\\/defect_59\\/image.png\",\"uploaded_images\":[\"\\/home\\/dvntrack\\/defects.dvntracker.site\\/uploads\\/defects\\/defect_59\\/20250130215810_679bf5f2de65e_Screenshot 2024-01-25 202814.png\"]}', '2025-01-30 21:58:10'),
(12, 'create_defect', 22, '{\"defect_id\":60,\"project_id\":11,\"reported_by\":22,\"assigned_to\":28,\"pin_image\":\"\\/home\\/dvntrack\\/defects.dvntracker.site\\/uploads\\/defects\\/defect_60\\/image.png\",\"uploaded_images\":[\"\\/home\\/dvntrack\\/defects.dvntracker.site\\/uploads\\/defects\\/defect_60\\/20250130221329_679bf989a0a1b_Screenshot 2024-01-25 202814.png\"]}', '2025-01-30 22:13:29'),
(13, 'create_defect', 22, '{\"defect_id\":61,\"project_id\":11,\"reported_by\":22,\"assigned_to\":28,\"pin_image\":\"\\/home\\/dvntrack\\/defects.dvntracker.site\\/uploads\\/defects\\/defect_61\\/image.png\",\"uploaded_images\":[\"\\/home\\/dvntrack\\/defects.dvntracker.site\\/uploads\\/defects\\/defect_61\\/20250131054027_679c624b0cacc_1000002877.png\"]}', '2025-01-31 05:40:27'),
(14, 'create_defect', 22, '{\"defect_id\":62,\"project_id\":11,\"reported_by\":22,\"assigned_to\":28,\"pin_image\":\"\\/home\\/dvntrack\\/defects.dvntracker.site\\/uploads\\/defects\\/defect_62\\/image.png\",\"uploaded_images\":[\"\\/home\\/dvntrack\\/defects.dvntracker.site\\/uploads\\/defects\\/defect_62\\/20250131073417_679c7cf9df361_Screenshot 2024-12-12 082951.png\"]}', '2025-01-31 07:34:17'),
(15, 'create_defect', 22, '{\"defect_id\":63,\"project_id\":11,\"reported_by\":22,\"assigned_to\":28,\"pin_image\":\"[\\\"\\\\\\/defect_63\\\\\\/image.png\\\"]\",\"uploaded_images\":[\"\\/defect_63\\/20250131102023_679ca3e722bd6_1000115227.jpg\"]}', '2025-01-31 10:20:23');

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `table_name` varchar(50) NOT NULL,
  `record_id` int(11) DEFAULT NULL,
  `action_by` varchar(50) NOT NULL,
  `action_at` datetime NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `action_type`, `table_name`, `record_id`, `action_by`, `action_at`, `created_at`) VALUES
(50, 'INSERT', 'contractors', 27, 'irlam', '2025-01-25 11:43:55', '2025-01-25 11:43:55'),
(51, 'INSERT', 'contractors', 28, 'irlam', '2025-01-25 21:46:09', '2025-01-25 21:46:09');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `comments`
--

CREATE TABLE `comments` (
  `id` int(11) NOT NULL,
  `defect_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `company_settings`
--

CREATE TABLE `company_settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contractors`
--

CREATE TABLE `contractors` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `company_name` varchar(100) NOT NULL,
  `contact_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `trade` varchar(50) NOT NULL,
  `address_line1` varchar(100) DEFAULT NULL,
  `address_line2` varchar(100) DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `county` varchar(50) DEFAULT NULL,
  `postcode` varchar(10) DEFAULT NULL,
  `vat_number` varchar(15) DEFAULT NULL,
  `company_number` varchar(10) DEFAULT NULL,
  `insurance_info` text DEFAULT NULL,
  `utr_number` varchar(10) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `license_number` varchar(50) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `contractors`
--

INSERT INTO `contractors` (`id`, `company_name`, `contact_name`, `email`, `phone`, `trade`, `address_line1`, `address_line2`, `city`, `county`, `postcode`, `vat_number`, `company_number`, `insurance_info`, `utr_number`, `notes`, `status`, `created_by`, `created_at`, `updated_by`, `updated_at`, `deleted_at`, `deleted_by`, `license_number`, `logo`) VALUES
(27, 'joiner', 'chris irlam', 'cirlam@gmail.com', '774351488', 'floor layer', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', 22, '2025-01-25 11:43:55', 22, '2025-01-25 11:43:55', NULL, NULL, NULL, NULL),
(28, 'Cara Brickwork', 'chris irlam', 'cirlam5@gmail.com', '07743514885', 'brick layer', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', 22, '2025-01-25 21:46:09', 22, '2025-01-25 21:46:09', NULL, NULL, NULL, NULL);

--
-- Triggers `contractors`
--
DELIMITER $$
CREATE TRIGGER `before_contractor_update` BEFORE UPDATE ON `contractors` FOR EACH ROW BEGIN
    DECLARE debug_info TEXT;
    
    -- Create debug information
    SET debug_info = CONCAT('Attempting update - Old updated_by: ', 
                           IFNULL(OLD.updated_by, 'NULL'),
                           ', New updated_by: ',
                           IFNULL(NEW.updated_by, 'NULL'),
                           ', Current time: ',
                           NOW());
    
    -- Log the debug information
    INSERT INTO system_logs (message, created_at)
    VALUES (debug_info, NOW());
    
    -- Verify user exists
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
CREATE TRIGGER `contractors_after_insert` AFTER INSERT ON `contractors` FOR EACH ROW BEGIN
    INSERT INTO `activity_logs` (
        `action_type`,
        `table_name`,
        `record_id`,
        `action_by`,
        `action_at`
    ) VALUES (
        'INSERT',
        'contractors',
        NEW.id,
        'irlam',
        NOW()
    );
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `contractors_after_update` AFTER UPDATE ON `contractors` FOR EACH ROW BEGIN
    IF OLD.status != NEW.status THEN
        INSERT INTO `activity_logs` (
            `action_type`,
            `table_name`,
            `record_id`,
            `action_by`,
            `action_at`
        ) VALUES (
            'STATUS_UPDATE',
            'contractors',
            NEW.id,
            'irlam',
            NOW()
        );
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
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `defects`
--

CREATE TABLE `defects` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `floor_plan_id` int(11) NOT NULL,
  `reported_by` int(11) NOT NULL,
  `assigned_to` bigint(20) UNSIGNED DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('open','in_progress','completed','verified') DEFAULT 'open',
  `closure_image` varchar(255) DEFAULT NULL,
  `rejection_comment` text DEFAULT NULL,
  `priority` enum('low','medium','high','critical') DEFAULT 'medium',
  `category` varchar(50) DEFAULT NULL,
  `x_coordinate` float DEFAULT NULL,
  `y_coordinate` float DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  `contractor_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `pin_x` float DEFAULT NULL,
  `pin_y` float DEFAULT NULL,
  `pin_image_path` varchar(255) DEFAULT NULL,
  `resolution_details` text DEFAULT NULL,
  `attachment_paths` text DEFAULT NULL,
  `comments` text DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `defects`
--

INSERT INTO `defects` (`id`, `project_id`, `floor_plan_id`, `reported_by`, `assigned_to`, `title`, `description`, `status`, `closure_image`, `rejection_comment`, `priority`, `category`, `x_coordinate`, `y_coordinate`, `due_date`, `created_at`, `updated_at`, `updated_by`, `contractor_id`, `created_by`, `pin_x`, `pin_y`, `pin_image_path`, `resolution_details`, `attachment_paths`, `comments`, `deleted_at`) VALUES
(48, 11, 82, 22, 28, 'nn', 'nn', 'open', NULL, NULL, 'critical', NULL, 0.697699, 0.212255, NULL, '2025-01-29 21:22:37', '2025-01-29 21:22:49', NULL, NULL, 22, 0.697699, 0.212255, 'floor_plan_pin_48_20250129212247.png', NULL, '[\"uploads\\/images\\/Screenshot 2024-01-25 202814.png\"]', NULL, NULL),
(49, 11, 83, 22, 27, 'nn', 'nn', '', 'uploads/defect_images/defect_679b89c9dd334_20250130_141641.png', 'll', 'medium', NULL, 0.633826, 0.344818, NULL, '2025-01-29 22:01:45', '2025-01-30 14:17:05', 22, NULL, 22, 0.633826, 0.344818, 'https://defects.dvntracker.site/floor_plan_pin_49_20250129220156.png', NULL, '[\"https:\\/\\/defects.dvntracker.site\\/uploads\\/images\\/Screenshot 2024-01-25 202840.png\"]', NULL, NULL),
(50, 11, 82, 22, 28, 'hh', 'hh', 'open', NULL, NULL, 'medium', NULL, 0.523798, 0.180835, NULL, '2025-01-30 15:01:16', '2025-01-30 15:01:29', NULL, NULL, 22, 0.523798, 0.180835, 'https://defects.dvntracker.site/floor_plan_pin_50_20250130150126.png', NULL, '[\"https:\\/\\/defects.dvntracker.site\\/uploads\\/images\\/LT-07.png\"]', NULL, NULL),
(53, 11, 82, 22, 28, 'mm', 'mm', 'open', NULL, NULL, 'low', NULL, 0.523228, 0.181097, NULL, '2025-01-30 18:25:37', '2025-01-30 18:25:37', NULL, NULL, 22, 0.523228, 0.181097, NULL, NULL, NULL, NULL, NULL),
(54, 11, 82, 22, 28, 'mm', 'mm', 'open', NULL, NULL, 'low', NULL, 0.523228, 0.181097, NULL, '2025-01-30 18:42:01', '2025-01-30 18:42:01', NULL, NULL, 22, 0.523228, 0.181097, '/home/dvntrack/defects.dvntracker.site/uploads/defect_54/image.png', NULL, '[\"\\/home\\/dvntrack\\/defects.dvntracker.site\\/uploads\\/defect_54\\/20250130184201_679bc7f9bd82f_Screenshot 2024-01-25 202814.png\"]', NULL, NULL),
(55, 11, 82, 22, 28, 'mm', 'mm', 'open', NULL, NULL, 'medium', NULL, 0.527326, 0.209112, NULL, '2025-01-30 20:59:45', '2025-01-30 20:59:45', NULL, NULL, 22, 0.527326, 0.209112, '/home/dvntrack/defects.dvntracker.site/uploads/defects/defect_55/image.png', NULL, '[\"\\/home\\/dvntrack\\/defects.dvntracker.site\\/uploads\\/defects\\/defect_55\\/20250130205945_679be841c354f_Screenshot 2024-01-25 202814.png\"]', NULL, NULL),
(56, 11, 82, 22, 28, 'mm', 'mm', 'open', NULL, NULL, 'low', NULL, 0.523228, 0.178658, NULL, '2025-01-30 21:20:44', '2025-01-30 21:20:44', NULL, NULL, 22, 0.523228, 0.178658, '/home/dvntrack/defects.dvntracker.site/uploads/defects/defect_56/image.png', NULL, '[\"\\/home\\/dvntrack\\/defects.dvntracker.site\\/uploads\\/defects\\/defect_56\\/20250130212044_679bed2c91c61_Screenshot 2024-01-25 202814.png\"]', NULL, NULL),
(57, 11, 82, 22, 27, 'nn', 'nn', 'open', NULL, NULL, 'low', NULL, 0.520496, 0.180473, NULL, '2025-01-30 21:22:38', '2025-01-30 21:22:38', NULL, NULL, 22, 0.520496, 0.180473, '/home/dvntrack/defects.dvntracker.site/uploads/defects/defect_57/image.png', NULL, '[\"\\/home\\/dvntrack\\/defects.dvntracker.site\\/uploads\\/defects\\/defect_57\\/20250130212238_679bed9ee3526_Screenshot 2024-01-25 202814.png\"]', NULL, NULL),
(58, 11, 82, 22, 28, 'kki', 'jjkjk', 'open', NULL, NULL, 'medium', NULL, 0.509567, 0.182344, NULL, '2025-01-30 21:26:43', '2025-01-30 21:26:43', NULL, NULL, 22, 0.509567, 0.182344, '/home/dvntrack/defects.dvntracker.site/uploads/defects/defect_58/image.png', NULL, '[\"\\/home\\/dvntrack\\/defects.dvntracker.site\\/uploads\\/defects\\/defect_58\\/20250130212643_679bee93e73d1_Screenshot 2024-01-25 202814.png\"]', NULL, NULL),
(59, 11, 82, 22, 28, 'nnn', 'nn', 'open', NULL, NULL, 'low', NULL, 0.520496, 0.193517, NULL, '2025-01-30 21:58:10', '2025-01-30 21:58:10', NULL, NULL, 22, 0.520496, 0.193517, '/home/dvntrack/defects.dvntracker.site/uploads/defects/defect_59/image.png', NULL, '[\"\\/home\\/dvntrack\\/defects.dvntracker.site\\/uploads\\/defects\\/defect_59\\/20250130215810_679bf5f2de65e_Screenshot 2024-01-25 202814.png\"]', NULL, NULL),
(60, 11, 82, 22, 28, 'mm', 'mm', 'open', NULL, NULL, 'medium', NULL, 0.51913, 0.178601, NULL, '2025-01-30 22:13:29', '2025-01-30 22:13:29', NULL, NULL, 22, 0.51913, 0.178601, '/home/dvntrack/defects.dvntracker.site/uploads/defects/defect_60/image.png', NULL, '[\"\\/home\\/dvntrack\\/defects.dvntracker.site\\/uploads\\/defects\\/defect_60\\/20250130221329_679bf989a0a1b_Screenshot 2024-01-25 202814.png\"]', NULL, NULL),
(61, 11, 82, 22, 28, 'W', 'W', 'open', NULL, NULL, 'low', NULL, 0.504982, 0.181654, NULL, '2025-01-31 05:40:27', '2025-01-31 05:40:27', NULL, NULL, 22, 0.504982, 0.181654, '/home/dvntrack/defects.dvntracker.site/uploads/defects/defect_61/image.png', NULL, '[\"\\/home\\/dvntrack\\/defects.dvntracker.site\\/uploads\\/defects\\/defect_61\\/20250131054027_679c624b0cacc_1000002877.png\"]', NULL, NULL),
(62, 11, 82, 22, 28, 'k', 'kkkk', 'open', NULL, NULL, 'medium', NULL, 0.510903, 0.157281, NULL, '2025-01-31 07:34:17', '2025-01-31 07:34:17', NULL, NULL, 22, 0.510903, 0.157281, '/home/dvntrack/defects.dvntracker.site/uploads/defects/defect_62/image.png', NULL, '[\"\\/home\\/dvntrack\\/defects.dvntracker.site\\/uploads\\/defects\\/defect_62\\/20250131073417_679c7cf9df361_Screenshot 2024-12-12 082951.png\"]', NULL, NULL),
(63, 11, 82, 22, 28, 'Ty', 'Gg', 'open', NULL, NULL, 'high', NULL, 0.550359, 0.406276, NULL, '2025-01-31 10:20:23', '2025-01-31 10:20:23', NULL, NULL, 22, 0.550359, 0.406276, '[\"\\/defect_63\\/image.png\"]', NULL, '[\"\\/defect_63\\/20250131102023_679ca3e722bd6_1000115227.jpg\"]', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `defect_comments`
--

CREATE TABLE `defect_comments` (
  `id` int(11) NOT NULL,
  `defect_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `defect_history`
--

CREATE TABLE `defect_history` (
  `id` int(11) NOT NULL,
  `defect_id` int(11) NOT NULL,
  `description` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_by` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `defect_images`
--

CREATE TABLE `defect_images` (
  `id` int(11) NOT NULL,
  `defect_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `uploaded_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `export_logs`
--

CREATE TABLE `export_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'References users.id',
  `export_type` varchar(50) NOT NULL COMMENT 'Type of export (e.g., dashboard, defects, contractors)',
  `file_format` varchar(10) NOT NULL COMMENT 'Format of the export (csv, excel, pdf)',
  `filename` varchar(255) NOT NULL COMMENT 'Name of the exported file',
  `filesize` bigint(20) UNSIGNED NOT NULL COMMENT 'Size of the exported file in bytes',
  `created_at` datetime NOT NULL COMMENT 'UTC timestamp of export creation',
  `downloaded_at` datetime DEFAULT NULL COMMENT 'UTC timestamp of first download',
  `download_count` int(10) UNSIGNED DEFAULT 0 COMMENT 'Number of times downloaded',
  `status` enum('pending','completed','failed','expired') NOT NULL DEFAULT 'pending',
  `expiry_date` datetime NOT NULL COMMENT 'When the export file should be deleted',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'IP address of the user who initiated the export',
  `user_agent` varchar(255) DEFAULT NULL COMMENT 'User agent of the browser used for export'
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
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `floor_name` varchar(255) NOT NULL COMMENT 'Primary display name of the floor plan',
  `level` varchar(50) NOT NULL DEFAULT 'Level?' COMMENT 'Floor level designation (e.g., Ground Floor, Level 1)',
  `file_path` varchar(255) NOT NULL COMMENT 'Server path to the stored floor plan file',
  `image_path` varchar(255) DEFAULT NULL COMMENT 'Path to the image version if different from original file',
  `floor_number` int(11) DEFAULT NULL COMMENT 'Numeric floor level for sorting (-1 for basement, 0 for ground, 1 for first, etc)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Timestamp when record was created',
  `upload_date` datetime DEFAULT current_timestamp() COMMENT 'Date and time when file was uploaded',
  `uploaded_by` int(11) NOT NULL COMMENT 'User ID who uploaded the file',
  `file_size` int(10) UNSIGNED DEFAULT NULL COMMENT 'Size of the file in bytes',
  `file_type` varchar(50) DEFAULT NULL COMMENT 'MIME type of the file',
  `description` text DEFAULT NULL COMMENT 'Detailed description of the floor plan',
  `version` int(11) DEFAULT 1 COMMENT 'Version number of the floor plan',
  `status` enum('active','inactive','deleted') NOT NULL DEFAULT 'active' COMMENT 'Current status of the floor plan',
  `last_modified` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Last modification timestamp',
  `thumbnail_path` varchar(255) DEFAULT NULL COMMENT 'Path to thumbnail version of the floor plan',
  `original_filename` varchar(255) NOT NULL COMMENT 'Original name of the uploaded file',
  `created_by` int(11) DEFAULT NULL COMMENT 'User ID who created the record (defaults to uploaded_by if not specified)',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL
) ;

--
-- Dumping data for table `floor_plans`
--

INSERT INTO `floor_plans` (`id`, `project_id`, `floor_name`, `level`, `file_path`, `image_path`, `floor_number`, `created_at`, `upload_date`, `uploaded_by`, `file_size`, `file_type`, `description`, `version`, `status`, `last_modified`, `thumbnail_path`, `original_filename`, `created_by`, `updated_at`, `updated_by`) VALUES
(82, 11, 'B1 - Level 1', 'B1 - Level 1', 'uploads/floor_plans/dvn/2025/01/dvn_b1-level-1_20250128_194921.pdf', NULL, NULL, '2025-01-28 19:49:21', '2025-01-28 19:49:21', 22, 491059, 'pdf', '', 1, 'active', '2025-01-28 19:49:21', NULL, 'B1 - 1st Floor.pdf', 22, '2025-01-28 19:49:21', NULL),
(83, 11, 'B1 - Level 2', 'B1 - Level 2', 'uploads/floor_plans/dvn/2025/01/dvn_b1-level-2_20250128_200601.pdf', NULL, NULL, '2025-01-28 20:06:01', '2025-01-28 20:06:01', 22, 788379, 'pdf', '', 1, 'active', '2025-01-28 20:06:01', NULL, 'B1 - 2nd Floor.pdf', 22, '2025-01-28 20:06:01', NULL),
(84, 11, 'B1 - Level 3', 'B1 - Level 3', 'uploads/floor_plans/dvn/2025/01/dvn_b1-level-3_20250128_201522.pdf', NULL, NULL, '2025-01-28 20:15:22', '2025-01-28 20:15:22', 22, 788082, 'pdf', '', 1, 'active', '2025-01-28 20:15:22', NULL, 'B1 - 3rd Floor.pdf', 22, '2025-01-28 20:15:22', NULL);

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
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `link_url` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` int(11) NOT NULL,
  `permission_name` varchar(100) DEFAULT NULL,
  `permission_key` varchar(100) DEFAULT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` varchar(50) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `permission_name`, `permission_key`, `name`, `description`, `created_by`, `created_at`, `updated_at`, `updated_by`, `deleted_at`, `deleted_by`) VALUES
(1, NULL, NULL, 'manage_users', 'Permission to manage users', 'system', '2025-01-24 15:04:05', '2025-01-24 15:11:00', NULL, NULL, NULL),
(2, NULL, NULL, 'view_reports', 'Permission to view reports', 'system', '2025-01-24 15:04:05', '2025-01-24 15:11:05', NULL, NULL, NULL),
(3, NULL, NULL, 'edit_projects', 'Permission to edit projects', 'system', '2025-01-24 15:04:56', '2025-01-24 15:11:11', NULL, NULL, NULL),
(4, NULL, NULL, 'delete_projects', 'Permission to delete projects', 'system', '2025-01-24 15:04:56', '2025-01-24 15:11:15', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `name`, `description`, `start_date`, `end_date`, `notes`, `created_by`, `updated_by`, `status`, `created_at`, `updated_at`, `is_active`) VALUES
(11, 'DVN', 'McGoff’s 237-Appartments -  Downtown Victoria North', '2025-01-24', '2028-01-24', NULL, 22, 22, 'active', '2025-01-24 17:23:33', '2025-01-24 17:36:21', 1);

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` varchar(50) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `description`, `created_by`, `created_at`, `updated_at`, `updated_by`) VALUES
(1, 'admin', 'Administrator role with full access', NULL, NULL, '2025-02-01 21:38:57', 'irlam'),
(2, 'manager', 'Project management and oversight capabilities', NULL, NULL, '2025-01-21 18:58:21', 'irlam'),
(3, 'contractor', 'Contractor access for defect updates and responses', NULL, NULL, '2025-01-21 18:58:21', 'irlam'),
(4, 'viewer', 'Read-only access to view defects and reports', NULL, NULL, '2025-01-21 18:58:21', 'irlam'),
(5, 'client', 'Client access to view and comment on defects', 'irlam', NULL, '2025-01-21 18:58:21', 'irlam');

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `role_permissions`
--

INSERT INTO `role_permissions` (`role_id`, `permission_id`, `created_at`) VALUES
(1, 1, '2025-01-25 13:28:35'),
(1, 2, '2025-01-25 13:28:35'),
(1, 3, '2025-01-25 13:28:35'),
(1, 4, '2025-01-25 13:28:35'),
(2, 2, '2025-01-25 13:28:35'),
(2, 3, '2025-01-25 13:28:35'),
(3, 2, '2025-01-25 13:28:35'),
(4, 2, '2025-01-25 13:28:35'),
(5, 2, '2025-01-25 13:28:35');

-- --------------------------------------------------------

--
-- Table structure for table `system_logs`
--

CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `action_by` int(11) DEFAULT NULL,
  `action_at` datetime NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `details` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `user_type` enum('admin','manager','contractor','inspector','viewer','client') DEFAULT 'viewer',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_by` varchar(50) DEFAULT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('admin','project_manager','contractor','client') NOT NULL,
  `avatar_url` varchar(255) DEFAULT NULL,
  `theme_preference` varchar(50) DEFAULT 'light',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` varchar(50) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `role_id` int(11) DEFAULT NULL,
  `contractor_id` int(11) DEFAULT NULL,
  `contractor_name` varchar(255) DEFAULT NULL,
  `contractor_trade` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `first_name`, `last_name`, `email`, `user_type`, `status`, `created_by`, `full_name`, `role`, `avatar_url`, `theme_preference`, `created_at`, `updated_at`, `updated_by`, `last_login`, `is_active`, `role_id`, `contractor_id`, `contractor_name`, `contractor_trade`) VALUES
(1, 'admin', '$2y$10$PdaKDe8zxWgDVZOBh3MMo.c5.eHI.0xfES86xJPuiysR2.c36xYAm', 'chris', 'irlam', 'cirlam@gmail.com', 'admin', 'active', 'irlam', 'Admin User', 'admin', 'assets/images/default-avatar.png', 'dark', '2025-01-15 10:58:42', '2025-01-28 18:59:22', 'irlam', '2025-01-28 18:59:22', 1, 1, NULL, NULL, NULL),
(22, 'irlam', '$2y$10$T5uTE5vYzwQJ9xJkNwNc1uIBroAZfbzSpA8pI8ZkL6C9hFpRcup2O', 'Chris', 'Irlam', 'irlam@example.com', 'admin', 'active', 'irlam', 'Chris Irlam', 'admin', 'assets/images/default-avatar.png', 'light', '2025-01-24 16:58:36', '2025-02-01 21:38:57', 'irlam', '2025-02-01 21:38:57', 1, 1, NULL, NULL, NULL),
(24, 'viewer', '$2y$10$.SJZvAwXl.Riz7xec6eBd.YgYGqmpuwG6e6kyMIRzMoMpLha61Yf6', 'chris', 'irlam', 'cirlam4@gmail.com', 'viewer', 'active', 'irlam', 'chris irlam', '', NULL, 'light', '2025-01-25 13:30:40', '2025-01-27 21:19:09', 'irlam', NULL, 1, 2, NULL, NULL, NULL),
(25, 'Contractor', '$2y$10$Hu.wEni1IV0d/Y/LdC1ZM.3DSweI5sfV75rFrmwqbw237Ea7gjZqi', 'christian', 'irlam', 'cirlam@6gmail.com', 'contractor', 'active', 'irlam', 'christian irlam', 'contractor', NULL, 'light', '2025-01-25 14:06:55', '2025-01-27 21:43:59', 'irlam', '2025-01-25 16:14:36', 1, 3, 28, 'Cara Brickwork', 'brick layer'),
(26, 'joiner', '$2y$10$default_hash_replace_this', NULL, NULL, 'cirlam@gmail.com', 'contractor', 'active', 'irlam', 'chris irlam', 'contractor', NULL, 'light', '2025-01-28 21:14:28', '2025-01-28 21:14:28', 'irlam', NULL, 1, 3, 27, 'joiner', 'floor layer');

--
-- Triggers `users`
--
DELIMITER $$
CREATE TRIGGER `users_insert_trigger` BEFORE INSERT ON `users` FOR EACH ROW BEGIN
    SET NEW.created_at = NOW();
    SET NEW.created_by = 'irlam';
    SET NEW.updated_at = NOW();
    SET NEW.updated_by = 'irlam';
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `users_update_trigger` BEFORE UPDATE ON `users` FOR EACH ROW BEGIN
    SET NEW.updated_at = NOW();
    SET NEW.updated_by = 'irlam';
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `user_logs`
--

CREATE TABLE `user_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `action_by` int(11) NOT NULL,
  `action_at` datetime NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_logs`
--

INSERT INTO `user_logs` (`id`, `user_id`, `action`, `action_by`, `action_at`, `ip_address`, `details`, `created_at`) VALUES
(48, 22, 'create_contractor', 22, '2025-01-25 09:10:36', '82.4.67.225', 'Created new contractor: Company Name 2', '2025-01-25 09:10:36'),
(49, 22, 'create_contractor', 22, '2025-01-25 09:10:41', '82.4.67.225', 'Created new contractor: Company Name 2', '2025-01-25 09:10:41'),
(50, 22, 'create_contractor', 22, '2025-01-25 09:10:44', '82.4.67.225', 'Created new contractor: Company Name 2', '2025-01-25 09:10:44'),
(51, 22, 'create_contractor', 22, '2025-01-25 09:14:42', '82.4.67.225', 'Created new contractor: Company Name 2', '2025-01-25 09:14:42'),
(52, 22, 'delete_contractor', 22, '2025-01-25 09:14:57', '82.4.67.225', 'Deleted contractor: test contracor (ID: 18)', '2025-01-25 09:14:57'),
(53, 22, 'delete_contractor', 22, '2025-01-25 09:15:02', '82.4.67.225', 'Deleted contractor: Company Name 2 (ID: 23)', '2025-01-25 09:15:02'),
(54, 22, 'delete_contractor', 22, '2025-01-25 09:15:07', '82.4.67.225', 'Deleted contractor: Company Name 2 (ID: 22)', '2025-01-25 09:15:07'),
(55, 22, 'delete_contractor', 22, '2025-01-25 09:15:15', '82.4.67.225', 'Deleted contractor: Company Name 2 (ID: 21)', '2025-01-25 09:15:15'),
(56, 22, 'create_contractor', 22, '2025-01-25 09:15:52', '82.4.67.225', 'Created new contractor: Company Name 2', '2025-01-25 09:15:52'),
(57, 22, 'delete_contractor', 22, '2025-01-25 10:07:02', '82.4.67.225', 'Deleted contractor: Company Name 2 (ID: 24)', '2025-01-25 10:07:02'),
(58, 22, 'create_contractor', 22, '2025-01-25 10:08:02', '82.4.67.225', 'Created new contractor: Test Company Name', '2025-01-25 10:08:02'),
(59, 22, 'delete_contractor', 22, '2025-01-25 10:40:18', '82.4.67.225', 'Deleted contractor: Test Company Name (ID: 25)', '2025-01-25 10:40:18'),
(60, 22, 'create_contractor', 22, '2025-01-25 11:36:53', '82.4.67.225', 'Created new contractor: joiner', '2025-01-25 11:36:53'),
(61, 22, 'delete_contractor', 22, '2025-01-25 11:40:56', '82.4.67.225', 'Deleted contractor: joiner (ID: 26)', '2025-01-25 11:40:56'),
(62, 22, 'delete_contractor', 22, '2025-01-25 11:43:29', '82.4.67.225', 'Deleted contractor: Company Name 2 (ID: 20)', '2025-01-25 11:43:29'),
(63, 22, 'create_contractor', 22, '2025-01-25 11:43:55', '82.4.67.225', 'Created new contractor: joiner', '2025-01-25 11:43:55'),
(64, 23, 'create_user', 22, '2025-01-25 12:27:35', '82.4.67.225', 'Created new user: manager (Type: manager)', '2025-01-25 12:27:35'),
(65, 23, 'user_edited', 22, '2025-01-25 12:28:15', '82.4.67.225', '{\"edited_by\":\"irlam\"}', '2025-01-25 12:28:15'),
(66, 24, 'create_user', 22, '2025-01-25 13:30:40', '82.4.67.225', '{\"username\":\"manager2\",\"email\":\"cirlam4@gmail.com\",\"user_type\":\"manager\",\"role\":\"manager\",\"role_id\":\"2\",\"contractor_id\":null,\"contractor_name\":null,\"created_by\":\"irlam\"}', '2025-01-25 13:30:40'),
(67, 24, 'user_edited', 22, '2025-01-25 13:54:20', '82.4.67.225', '{\"edited_by\":\"irlam\"}', '2025-01-25 13:54:20'),
(68, 24, 'type_changed', 22, '2025-01-25 14:05:39', '82.4.67.225', '{\"new_type\":\"manager\",\"new_role_id\":2,\"changed_by\":\"irlam\"}', '2025-01-25 14:05:39'),
(69, 23, 'type_changed', 22, '2025-01-25 14:05:48', '82.4.67.225', '{\"new_type\":\"viewer\",\"new_role_id\":4,\"changed_by\":\"irlam\"}', '2025-01-25 14:05:48'),
(70, 23, 'type_changed', 22, '2025-01-25 14:05:54', '82.4.67.225', '{\"new_type\":\"contractor\",\"new_role_id\":3,\"changed_by\":\"irlam\"}', '2025-01-25 14:05:54'),
(71, 25, 'create_user', 22, '2025-01-25 14:06:55', '82.4.67.225', '{\"username\":\"irlam9\",\"email\":\"cirlam@6gmail.com\",\"user_type\":\"contractor\",\"role\":\"contractor\",\"role_id\":\"3\",\"contractor_id\":\"27\",\"contractor_name\":\"joiner\",\"created_by\":\"irlam\"}', '2025-01-25 14:06:55'),
(72, 25, 'status_changed', 22, '2025-01-25 14:07:25', '82.4.67.225', '{\"new_status\":\"inactive\",\"changed_by\":\"irlam\"}', '2025-01-25 14:07:25'),
(73, 25, 'status_changed', 22, '2025-01-25 14:07:37', '82.4.67.225', '{\"new_status\":\"inactive\",\"changed_by\":\"irlam\"}', '2025-01-25 14:07:37'),
(74, 25, 'status_changed', 22, '2025-01-25 14:07:58', '82.4.67.225', '{\"new_status\":\"active\",\"changed_by\":\"irlam\"}', '2025-01-25 14:07:58'),
(75, 22, 'create_contractor', 22, '2025-01-25 21:46:09', '82.4.67.225', 'Created new contractor: Cara Brickwork', '2025-01-25 21:46:09'),
(76, 24, 'type_changed', 22, '2025-01-26 09:39:53', '82.4.67.225', '{\"new_type\":\"viewer\",\"new_role_id\":4,\"changed_by\":\"irlam\"}', '2025-01-26 09:39:53'),
(77, 23, 'type_changed', 22, '2025-01-26 09:40:50', '82.4.67.225', '{\"new_type\":\"manager\",\"new_role_id\":2,\"changed_by\":\"irlam\"}', '2025-01-26 09:40:50'),
(78, 24, 'user_edited', 22, '2025-01-27 20:29:52', '82.4.67.225', '{\"edited_by\":\"irlam\"}', '2025-01-27 20:29:52'),
(79, 24, 'type_changed', 22, '2025-01-27 20:30:08', '82.4.67.225', '{\"new_type\":\"contractor\",\"new_role_id\":3,\"changed_by\":\"irlam\"}', '2025-01-27 20:30:08'),
(80, 24, 'type_changed', 22, '2025-01-27 20:30:26', '82.4.67.225', '{\"new_type\":\"manager\",\"new_role_id\":2,\"changed_by\":\"irlam\"}', '2025-01-27 20:30:26'),
(81, 24, 'type_changed', 22, '2025-01-27 20:30:36', '82.4.67.225', '{\"new_type\":\"client\",\"new_role_id\":5,\"changed_by\":\"irlam\"}', '2025-01-27 20:30:36'),
(82, 24, 'type_changed', 22, '2025-01-27 20:30:44', '82.4.67.225', '{\"new_type\":\"viewer\",\"new_role_id\":4,\"changed_by\":\"irlam\"}', '2025-01-27 20:30:44'),
(83, 24, 'user_edited', 22, '2025-01-27 20:31:04', '82.4.67.225', '{\"edited_by\":\"irlam\"}', '2025-01-27 20:31:04'),
(84, 25, 'user_edited', 22, '2025-01-27 20:31:24', '82.4.67.225', '{\"edited_by\":\"irlam\"}', '2025-01-27 20:31:24'),
(85, 24, 'type_changed', 22, '2025-01-27 20:55:32', '82.4.67.225', '{\"new_type\":\"client\",\"new_role_id\":5,\"changed_by\":\"irlam\"}', '2025-01-27 20:55:32'),
(86, 23, 'type_changed', 22, '2025-01-27 20:57:43', '82.4.67.225', '{\"new_type\":\"contractor\",\"new_role_id\":3,\"changed_by\":\"irlam\"}', '2025-01-27 20:57:43'),
(87, 24, 'type_changed', 22, '2025-01-27 21:07:17', '82.4.67.225', '{\"new_type\":\"manager\",\"new_role_id\":2,\"changed_by\":\"irlam\"}', '2025-01-27 21:07:17'),
(88, 24, 'type_changed', 22, '2025-01-27 21:08:41', '82.4.67.225', '{\"new_type\":\"client\",\"new_role_id\":5,\"changed_by\":\"irlam\"}', '2025-01-27 21:08:41'),
(89, 24, 'type_changed', 22, '2025-01-27 21:13:11', '82.4.67.225', '{\"new_type\":\"viewer\",\"new_role_id\":4,\"new_role\":\"viewer\",\"changed_by\":\"irlam\",\"timestamp\":\"2025-01-27 21:13:11\"}', '2025-01-27 21:13:11'),
(90, 24, 'user_edited', 22, '2025-01-27 21:19:09', '82.4.67.225', '{\"edited_by\":\"irlam\"}', '2025-01-27 21:19:09'),
(91, 25, 'type_changed', 22, '2025-01-27 21:43:31', '82.4.67.225', '{\"new_type\":\"manager\",\"new_role\":\"project_manager\",\"new_role_id\":2,\"contractor_id\":null,\"contractor_name\":null,\"contractor_trade\":null,\"changed_by\":\"irlam\",\"timestamp\":\"2025-01-27 21:39:35\"}', '2025-01-27 21:43:31'),
(92, 25, 'type_changed', 22, '2025-01-27 21:43:45', '82.4.67.225', '{\"new_type\":\"manager\",\"new_role\":\"project_manager\",\"new_role_id\":2,\"contractor_id\":null,\"contractor_name\":null,\"contractor_trade\":null,\"changed_by\":\"irlam\",\"timestamp\":\"2025-01-27 21:39:35\"}', '2025-01-27 21:43:45'),
(93, 25, 'type_changed', 22, '2025-01-27 21:43:59', '82.4.67.225', '{\"new_type\":\"contractor\",\"new_role\":\"contractor\",\"new_role_id\":3,\"contractor_id\":\"28\",\"contractor_name\":\"Cara Brickwork\",\"contractor_trade\":\"brick layer\",\"changed_by\":\"irlam\",\"timestamp\":\"2025-01-27 21:39:35\"}', '2025-01-27 21:43:59'),
(94, 1, 'type_changed', 22, '2025-01-28 05:46:50', '216.169.133.20', '{\"new_type\":\"client\",\"new_role\":\"client\",\"new_role_id\":5,\"contractor_id\":null,\"contractor_name\":null,\"contractor_trade\":null,\"changed_by\":\"irlam\",\"timestamp\":\"2025-01-27 21:39:35\"}', '2025-01-28 05:46:50'),
(95, 1, 'type_changed', 22, '2025-01-28 05:47:44', '216.169.133.20', '{\"new_type\":\"admin\",\"new_role\":\"admin\",\"new_role_id\":1,\"contractor_id\":null,\"contractor_name\":null,\"contractor_trade\":null,\"changed_by\":\"irlam\",\"timestamp\":\"2025-01-27 21:39:35\"}', '2025-01-28 05:47:44'),
(96, 22, 'update_defect', 22, '2025-01-30 09:53:59', '41.180.248.70', '{\"defect_id\":49,\"new_status\":\"rejected\",\"updated_by\":22}', '2025-01-30 09:53:59'),
(97, 22, 'update_defect', 22, '2025-01-30 09:54:54', '41.180.248.70', '{\"defect_id\":49,\"new_status\":\"rejected\",\"updated_by\":22}', '2025-01-30 09:54:54'),
(98, 22, 'update_defect', 22, '2025-01-30 10:15:50', '41.180.248.70', '{\"defect_id\":49,\"new_status\":\"open\",\"updated_by\":22}', '2025-01-30 10:15:50'),
(99, 22, 'update_defect', 22, '2025-01-30 10:16:15', '41.180.248.70', '{\"defect_id\":49,\"new_status\":\"closed\",\"updated_by\":22}', '2025-01-30 10:16:15'),
(100, 22, 'update_defect', 22, '2025-01-30 10:16:29', '41.180.248.70', '{\"defect_id\":49,\"new_status\":\"rejected\",\"updated_by\":22}', '2025-01-30 10:16:29'),
(101, 22, 'update_defect', 22, '2025-01-30 12:35:15', '41.180.248.70', '{\"defect_id\":49,\"old_status\":\"\",\"new_status\":\"open\",\"updated_by\":22}', '2025-01-30 12:35:15'),
(102, 22, 'update_defect', 22, '2025-01-30 12:35:41', '41.180.248.70', '{\"defect_id\":49,\"old_status\":\"open\",\"new_status\":\"closed\",\"updated_by\":22}', '2025-01-30 12:35:41'),
(103, 22, 'update_defect', 22, '2025-01-30 12:37:01', '41.180.248.70', '{\"defect_id\":49,\"old_status\":\"\",\"new_status\":\"rejected\",\"updated_by\":22}', '2025-01-30 12:37:01'),
(104, 22, 'update_defect', 22, '2025-01-30 12:39:58', '41.180.248.70', '{\"defect_id\":49,\"old_status\":\"\",\"new_status\":\"rejected\",\"updated_by\":22}', '2025-01-30 12:39:58'),
(105, 22, 'update_defect', 22, '2025-01-30 12:44:03', '41.180.248.70', '{\"defect_id\":49,\"old_status\":\"\",\"new_status\":\"rejected\",\"updated_by\":22}', '2025-01-30 12:44:03'),
(106, 22, 'update_defect', 22, '2025-01-30 13:12:01', '41.180.248.70', '{\"defect_id\":49,\"old_status\":\"\",\"new_status\":\"closed\",\"updated_by\":22}', '2025-01-30 13:12:01'),
(107, 22, 'update_defect', 22, '2025-01-30 13:13:52', '41.180.248.70', '{\"defect_id\":49,\"old_status\":\"\",\"new_status\":\"rejected\",\"updated_by\":22}', '2025-01-30 13:13:52'),
(108, 22, 'update_defect', 22, '2025-01-30 13:23:29', '41.180.248.70', '{\"defect_id\":49,\"old_status\":\"\",\"new_status\":\"rejected\",\"updated_by\":22}', '2025-01-30 13:23:29'),
(109, 22, 'update_defect', 22, '2025-01-30 13:30:28', '41.180.248.70', '{\"defect_id\":49,\"old_status\":\"\",\"new_status\":\"rejected\",\"updated_by\":22}', '2025-01-30 13:30:28'),
(110, 22, 'update_defect', 22, '2025-01-30 14:05:16', '41.180.248.70', '{\"defect_id\":49,\"old_status\":\"\",\"new_status\":\"open\",\"updated_by\":22}', '2025-01-30 14:05:16'),
(111, 22, 'update_defect', 22, '2025-01-30 14:16:41', '41.180.248.70', '{\"defect_id\":49,\"old_status\":\"open\",\"new_status\":\"closed\",\"updated_by\":22}', '2025-01-30 14:16:41'),
(112, 22, 'update_defect', 22, '2025-01-30 14:17:05', '41.180.248.70', '{\"defect_id\":49,\"old_status\":\"\",\"new_status\":\"rejected\",\"updated_by\":22}', '2025-01-30 14:17:05');

-- --------------------------------------------------------

--
-- Table structure for table `user_permissions`
--

CREATE TABLE `user_permissions` (
  `user_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `user_permissions`
--

INSERT INTO `user_permissions` (`user_id`, `permission_id`, `created_at`) VALUES
(1, 1, '2025-01-24 15:12:52'),
(1, 2, '2025-01-24 15:12:52'),
(1, 3, '2025-01-24 15:12:52'),
(1, 4, '2025-01-24 15:12:52');

-- --------------------------------------------------------

--
-- Table structure for table `user_roles`
--

CREATE TABLE `user_roles` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_roles`
--

INSERT INTO `user_roles` (`id`, `user_id`, `role_id`, `created_at`, `updated_at`, `created_by`, `updated_by`, `deleted_at`, `deleted_by`) VALUES
(1, 1, 1, '2025-01-18 09:51:52', '2025-01-28 18:59:22', 1, 22, NULL, NULL),
(2, 24, 2, '2025-01-25 13:30:40', '2025-01-27 21:07:17', 22, 22, NULL, NULL),
(6, 25, 3, '2025-01-25 14:06:55', '2025-01-27 21:43:59', 22, 22, NULL, NULL),
(7, 22, 1, '2025-01-26 09:08:42', '2025-02-01 21:38:57', NULL, NULL, NULL, NULL),
(14, 24, 4, '2025-01-26 09:39:53', '2025-01-27 21:13:11', 22, 22, NULL, NULL),
(275, 24, 3, '2025-01-27 20:30:08', '2025-01-27 20:30:08', 22, NULL, NULL, NULL),
(277, 24, 5, '2025-01-27 20:30:36', '2025-01-27 21:08:41', 22, 22, NULL, NULL),
(286, 25, 2, '2025-01-27 21:43:31', '2025-01-27 21:43:45', 22, 22, NULL, NULL),
(298, 1, 5, '2025-01-28 05:46:50', '2025-01-28 05:46:50', 22, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `session_id` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `logged_in_at` datetime NOT NULL,
  `logged_out_at` datetime DEFAULT NULL,
  `last_activity` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
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
  ADD KEY `idx_table_record` (`table_name`,`record_id`),
  ADD KEY `idx_action_type` (`action_type`),
  ADD KEY `idx_action_by` (`action_by`),
  ADD KEY `idx_action_at` (`action_at`);

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
  ADD KEY `fk_assigned_to_contractor` (`assigned_to`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `idx_deleted_at` (`deleted_at`);

--
-- Indexes for table `defect_comments`
--
ALTER TABLE `defect_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `defect_id` (`defect_id`),
  ADD KEY `user_id` (`user_id`);

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
  ADD KEY `uploaded_by` (`uploaded_by`);

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
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `comments`
--
ALTER TABLE `comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `contractors`
--
ALTER TABLE `contractors`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `defects`
--
ALTER TABLE `defects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;

--
-- AUTO_INCREMENT for table `defect_comments`
--
ALTER TABLE `defect_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `defect_history`
--
ALTER TABLE `defect_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `defect_images`
--
ALTER TABLE `defect_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `export_logs`
--
ALTER TABLE `export_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `floor_plans`
--
ALTER TABLE `floor_plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=128;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `user_logs`
--
ALTER TABLE `user_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=113;

--
-- AUTO_INCREMENT for table `user_roles`
--
ALTER TABLE `user_roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=422;

--
-- AUTO_INCREMENT for table `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

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
  ADD CONSTRAINT `fk_assigned_to_contractor` FOREIGN KEY (`assigned_to`) REFERENCES `contractors` (`id`),
  ADD CONSTRAINT `fk_contractor_id` FOREIGN KEY (`contractor_id`) REFERENCES `contractors` (`id`),
  ADD CONSTRAINT `fk_defect_contractor` FOREIGN KEY (`contractor_id`) REFERENCES `contractors` (`id`),
  ADD CONSTRAINT `fk_project_id` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_reported_by` FOREIGN KEY (`reported_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

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

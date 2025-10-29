/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.11.11-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: 10.35.233.124    Database: k87747_defecttracker
-- ------------------------------------------------------
-- Server version	8.0.41

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Temporary table structure for view `acceptance_history`
--

DROP TABLE IF EXISTS `acceptance_history`;
/*!50001 DROP VIEW IF EXISTS `acceptance_history`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `acceptance_history` AS SELECT
 1 AS `defect_id`,
  1 AS `title`,
  1 AS `status`,
  1 AS `acceptance_comment`,
  1 AS `accepted_by_user`,
  1 AS `accepted_at`,
  1 AS `project_name`,
  1 AS `contractor_name` */;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `action_log`
--

DROP TABLE IF EXISTS `action_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `action_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `action` varchar(255) NOT NULL,
  `user_id` int NOT NULL,
  `details` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `action_log`
--

LOCK TABLES `action_log` WRITE;
/*!40000 ALTER TABLE `action_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `action_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `activity_logs`
--

DROP TABLE IF EXISTS `activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `defect_id` int NOT NULL,
  `action` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` int NOT NULL,
  `action_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `details` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_activity_defect` (`defect_id`),
  KEY `idx_activity_user` (`user_id`),
  KEY `idx_activity_created` (`created_at`),
  CONSTRAINT `fk_activity_defect` FOREIGN KEY (`defect_id`) REFERENCES `defects` (`id`),
  CONSTRAINT `fk_activity_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_logs`
--

LOCK TABLES `activity_logs` WRITE;
/*!40000 ALTER TABLE `activity_logs` DISABLE KEYS */;
INSERT INTO `activity_logs` VALUES
(8,229,'Defect assigned to user',22,'ASSIGN','Defect #229 assigned to contractor contractor ()','2025-02-20 18:53:22'),
(9,229,'Defect assigned to user',22,'ASSIGN','Defect #229 assigned to contractor contractor ()','2025-02-20 18:59:35'),
(10,229,'Defect assigned to user',22,'ASSIGN','Defect #229 assigned to contractor contractor () from contractor Panacea','2025-02-20 19:01:34'),
(11,229,'Defect assigned to user',22,'ASSIGN','Defect #229 assigned to contractor contractor () from contractor Panacea','2025-02-20 19:01:34'),
(12,229,'Defect assigned to user',22,'ASSIGN','Defect #229 assigned to contractor contractor () from contractor Panacea','2025-02-20 19:10:36'),
(13,229,'Defect assigned to user',22,'ASSIGN','Defect #229 assigned to contractor contractor () from contractor Panacea','2025-02-20 19:10:36'),
(14,229,'Defect assigned to user',22,'ASSIGN','Defect #229 assigned to contractor contractor () from contractor Panacea','2025-02-20 19:21:03'),
(15,229,'Defect assigned to user',22,'ASSIGN','Defect #229 assigned to contractor contractor () from contractor Panacea','2025-02-20 19:21:03'),
(16,229,'Defect assigned to user',22,'ASSIGN','Defect #229 assigned to contractor contractor () from contractor Panacea','2025-02-20 19:21:03'),
(17,229,'Defect assigned to user',22,'ASSIGN','Defect #229 assigned to contractor contractor () from contractor Panacea','2025-02-20 19:21:03'),
(18,229,'Defect assigned to user',22,'ASSIGN','Defect #229 assigned to contractor contractor () from contractor Panacea','2025-02-20 19:27:07'),
(19,229,'Defect assigned to user',22,'ASSIGN','Defect #229 assigned to contractor contractor () from contractor Panacea','2025-02-20 19:27:07'),
(20,243,'Defect assigned to user',22,'ASSIGN','Defect #243 assigned to test first name test last name (Panacea) from contractor McGoff','2025-02-20 19:29:20'),
(21,243,'Defect assigned to user',28,'ASSIGN','Defect #243 assigned to test first name test last name (Panacea) from contractor McGoff','2025-02-20 19:29:20'),
(22,242,'Defect assigned to user',28,'ASSIGN','Defect #242 assigned to test first name test last name (Panacea) from contractor Edencroft','2025-02-20 19:29:20'),
(23,246,'Defect assigned to user',22,'ASSIGN','Defect #246 assigned to contractor contractor () from contractor Branniff Joinery','2025-02-20 19:29:20'),
(24,319,'Defect assigned to user',22,'ASSIGN','Defect #319 assigned to test first name test last name (Branniff Joinery) from contractor Edencroft','2025-03-22 18:23:50'),
(25,320,'Defect assigned to user',22,'ASSIGN','Defect #320 assigned to test first name test last name (Branniff Joinery) from contractor McGoff','2025-03-24 14:40:58'),
(26,319,'Defect assigned to user',22,'ASSIGN','Defect #319 assigned to manager manager (Cara Brickwork) from contractor Edencroft','2025-03-24 14:43:25');
/*!40000 ALTER TABLE `activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `entity_type` varchar(255) NOT NULL,
  `entity_id` int NOT NULL,
  `action` varchar(255) NOT NULL,
  `old_values` text,
  `user_id` int NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` int NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_by` int DEFAULT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_by` int DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_category_name` (`name`),
  KEY `created_by` (`created_by`),
  KEY `idx_category_status` (`deleted_at`),
  KEY `idx_category_created_at` (`created_at`),
  KEY `idx_categories_deleted_at` (`deleted_at`),
  KEY `fk_categories_deleted_by` (`deleted_by`),
  KEY `fk_categories_updated_by` (`updated_by`),
  CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `categories_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`),
  CONSTRAINT `categories_ibfk_3` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_categories_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_categories_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `comments`
--

DROP TABLE IF EXISTS `comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `comments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `defect_id` int NOT NULL,
  `user_id` int NOT NULL,
  `comment` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `defect_id` (`defect_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `comments_ibfk_1` FOREIGN KEY (`defect_id`) REFERENCES `defects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `comments`
--

LOCK TABLES `comments` WRITE;
/*!40000 ALTER TABLE `comments` DISABLE KEYS */;
/*!40000 ALTER TABLE `comments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `company_settings`
--

DROP TABLE IF EXISTS `company_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `company_settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `company_settings`
--

LOCK TABLES `company_settings` WRITE;
/*!40000 ALTER TABLE `company_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `company_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contractors`
--

DROP TABLE IF EXISTS `contractors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `contractors` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
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
  `logo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_trade` (`trade`),
  KEY `idx_county` (`county`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_updated_by` (`updated_by`),
  KEY `idx_deleted_by` (`deleted_by`),
  KEY `id` (`id`),
  FULLTEXT KEY `ft_company_contact` (`company_name`,`contact_name`),
  CONSTRAINT `fk_contractors_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_contractors_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_contractors_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=57 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contractors`
--

LOCK TABLES `contractors` WRITE;
/*!40000 ALTER TABLE `contractors` DISABLE KEYS */;
INSERT INTO `contractors` VALUES
(29,'Cara Brickwork','chris irlam','cirlam@gmail.com','07743514885','','19 Bennetts Lane','smithills','BOLTON','lancs','BL1 6HY','','','','','','active',22,'2025-02-03 16:13:28',22,'2025-02-06 19:02:11',NULL,NULL,'','67a50733423ee_Cara-Brickwork-logo.png'),
(30,'RED Windows','RED Windows','cirlam@gmail.com','07743514885','','19 Bennetts Lane','smithills','BOLTON','lancs','BL1 6HY','','','','','','active',22,'2025-02-03 16:13:28',22,'2025-02-06 19:05:09',NULL,NULL,'','67a507e57e370_red-windows.png'),
(31,'Panacea','chris irlam','cirlam@gmail.com','07743514885','','19 Bennetts Lane','smithills','BOLTON','lancs','BL1 6HY','','','','','','active',22,'2025-02-03 18:23:55',22,'2025-02-20 07:09:55',NULL,NULL,'','67b6c73355688_1630461168847.jpeg'),
(52,'Heyrods','Heyrods','info@heyrod.co.uk','0161 683 4294','','Albion Works, Clowes Street,','Chadderton','Oldham','','OL9 7LY','','','','','','active',22,'2025-02-12 20:58:06',22,'2025-02-12 20:58:06',NULL,NULL,'','67ad0b5e5ec03_Heyrod-3079453049.png'),
(53,'McGoff','chris irlam','cirlam@gmail.com','07743514885','','1 St George\'s Ct','Broadheath','Altrincham','lancs','WA14 5UA','','','','','','active',27,'2025-02-13 08:46:37',27,'2025-02-13 08:46:37',NULL,NULL,'','67adb16de7b85_mcgoff.png'),
(54,'Craven','Craven','cirlam@gmail.com','07743514885','','19 Bennetts Lane','smithills','BOLTON','lancs','BL1 6HY','','','','','','active',22,'2025-02-20 07:01:26',22,'2025-02-20 07:01:26',NULL,NULL,'','67b6c5366a2bf_craven-logo-4226456076.png'),
(55,'Edencroft','chris irlam','cirlam@gmail.com','07743514885','','19 Bennetts Lane','smithills','BOLTON','lancs','BL1 6HY','','','','','','active',22,'2025-02-20 07:03:43',22,'2025-02-20 07:03:43',NULL,NULL,'','67b6c5bfbc505_300164-2153089336.jpg'),
(56,'Branniff Joinery','Branniff Joinery','branniff@branniff.com','01606 550529','','28 Mays Corner Road','Katesbridge','Bambridge','','BT32 5RB','','','','','','active',22,'2025-02-21 12:46:27',22,'2025-02-21 12:46:27',NULL,NULL,'','67b86793019ae_BRANNIFF.png');
/*!40000 ALTER TABLE `contractors` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`k87747_defecttracker`@`%`*/ /*!50003 TRIGGER `contractors_before_insert` BEFORE INSERT ON `contractors` FOR EACH ROW BEGIN
    
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
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`k87747_defecttracker`@`%`*/ /*!50003 TRIGGER `before_contractor_update` BEFORE UPDATE ON `contractors` FOR EACH ROW BEGIN
    DECLARE debug_info TEXT;
    
    SET debug_info = CONCAT('Contractor Update - ID: ', OLD.id,
                           ', Old Status: ', OLD.status,
                           ', New Status: ', NEW.status,
                           ', Updated By: ', NEW.updated_by,
                           ', Time: ', NOW());
    
    INSERT INTO system_logs (
        user_id,
        action,
        action_by,
        action_at,
        ip_address,
        details
    ) VALUES (
        NEW.updated_by,
        'UPDATE_CONTRACTOR',
        NEW.updated_by,
        NOW(),
        NULL,
        debug_info
    );
    
    IF NEW.updated_by IS NOT NULL AND NOT EXISTS (
        SELECT 1 FROM users WHERE id = NEW.updated_by
    ) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Invalid user ID for updated_by';
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`k87747_defecttracker`@`%`*/ /*!50003 TRIGGER `contractors_before_update` BEFORE UPDATE ON `contractors` FOR EACH ROW BEGIN
    
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
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `defect_assignments`
--

DROP TABLE IF EXISTS `defect_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `defect_assignments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `defect_id` int NOT NULL,
  `user_id` int NOT NULL,
  `assigned_by` int NOT NULL,
  `assigned_at` datetime NOT NULL,
  `status` enum('active','completed','reassigned') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `defect_id` (`defect_id`),
  KEY `user_id` (`user_id`),
  KEY `assigned_by` (`assigned_by`),
  CONSTRAINT `defect_assignments_ibfk_1` FOREIGN KEY (`defect_id`) REFERENCES `defects` (`id`),
  CONSTRAINT `defect_assignments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `defect_assignments_ibfk_3` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `defect_assignments`
--

LOCK TABLES `defect_assignments` WRITE;
/*!40000 ALTER TABLE `defect_assignments` DISABLE KEYS */;
/*!40000 ALTER TABLE `defect_assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `defect_comments`
--

DROP TABLE IF EXISTS `defect_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `defect_comments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `defect_id` int NOT NULL,
  `user_id` int NOT NULL,
  `comment` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `sync_status` enum('synced','pending','conflict') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'synced',
  `client_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sync_timestamp` datetime DEFAULT NULL,
  `device_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `defect_id` (`defect_id`),
  KEY `user_id` (`user_id`),
  KEY `sync_status` (`sync_status`),
  KEY `client_id` (`client_id`),
  CONSTRAINT `defect_comments_ibfk_1` FOREIGN KEY (`defect_id`) REFERENCES `defects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `defect_comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `defect_comments`
--

LOCK TABLES `defect_comments` WRITE;
/*!40000 ALTER TABLE `defect_comments` DISABLE KEYS */;
/*!40000 ALTER TABLE `defect_comments` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'IGNORE_SPACE,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`k87747_defecttracker`@`%`*/ /*!50003 TRIGGER `defect_comments_before_update` BEFORE UPDATE ON `defect_comments` FOR EACH ROW BEGIN
                    -- Only mark as pending if this is a direct update, not from the sync system
                    IF NEW.sync_status = 'synced' AND OLD.updated_at != NEW.updated_at THEN
                        SET NEW.sync_status = 'pending';
                        SET NEW.sync_timestamp = NOW();
                    END IF;
                END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `defect_history`
--

DROP TABLE IF EXISTS `defect_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `defect_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `defect_id` int NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_defect_id` (`defect_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_updated_by` (`updated_by`),
  CONSTRAINT `defect_history_ibfk_1` FOREIGN KEY (`defect_id`) REFERENCES `defects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `defect_history`
--

LOCK TABLES `defect_history` WRITE;
/*!40000 ALTER TABLE `defect_history` DISABLE KEYS */;
INSERT INTO `defect_history` VALUES
(5,243,'Defect status changed to pending after uploaded completed images.','2025-02-23 17:13:32','28'),
(6,242,'Defect status changed to pending after completed images were uploaded.','2025-02-23 17:18:01','28'),
(7,243,'Defect status changed to pending after completed images were uploaded.','2025-02-23 17:24:15','28');
/*!40000 ALTER TABLE `defect_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `defect_images`
--

DROP TABLE IF EXISTS `defect_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `defect_images` (
  `id` int NOT NULL AUTO_INCREMENT,
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
  `is_edited` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `defect_id` (`defect_id`),
  KEY `uploaded_by` (`uploaded_by`),
  KEY `sync_status` (`sync_status`),
  KEY `client_id` (`client_id`),
  CONSTRAINT `defect_images_ibfk_1` FOREIGN KEY (`defect_id`) REFERENCES `defects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `defect_images_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=293 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `defect_images`
--

LOCK TABLES `defect_images` WRITE;
/*!40000 ALTER TABLE `defect_images` DISABLE KEYS */;
INSERT INTO `defect_images` VALUES
(110,229,'uploads/defects/229/floorplan_with_pin_defect.png','uploads/defects/229/floorplan_with_pin_defect.png',22,'2025-02-19 17:20:29','2025-02-19 17:20:29','synced',NULL,NULL,NULL,0),
(111,229,'uploads/defects/229/1739989231_background.jpeg',NULL,22,'2025-02-19 17:20:29','2025-02-19 17:20:29','synced',NULL,NULL,NULL,0),
(112,230,'uploads/defects/230/floorplan_with_pin_defect.png','uploads/defects/230/floorplan_with_pin_defect.png',22,'2025-02-20 21:08:23','2025-02-20 21:08:23','synced',NULL,NULL,NULL,0),
(113,230,'uploads/defects/230/1740089305_Cara-Brickwork-logo.png',NULL,22,'2025-02-20 21:08:23','2025-02-20 21:08:23','synced',NULL,NULL,NULL,0),
(114,231,'uploads/defects/231/floorplan_with_pin_defect.png','uploads/defects/231/floorplan_with_pin_defect.png',22,'2025-02-20 21:26:17','2025-02-20 21:26:17','synced',NULL,NULL,NULL,0),
(115,232,'uploads/defects/232/floorplan_with_pin_defect.png','uploads/defects/232/floorplan_with_pin_defect.png',22,'2025-02-22 10:26:10','2025-02-22 10:26:10','synced',NULL,NULL,NULL,0),
(116,233,'uploads/defects/233/floorplan_with_pin_defect.png','uploads/defects/233/floorplan_with_pin_defect.png',22,'2025-02-22 10:27:07','2025-02-22 10:27:07','synced',NULL,NULL,NULL,0),
(117,234,'uploads/defects/234/floorplan_with_pin_defect.png','uploads/defects/234/floorplan_with_pin_defect.png',22,'2025-02-22 10:33:26','2025-02-22 10:33:26','synced',NULL,NULL,NULL,0),
(118,234,'uploads/defects/234/1740224008_File-Sharing-Construction.jpg',NULL,22,'2025-02-22 10:33:26','2025-02-22 10:33:26','synced',NULL,NULL,NULL,0),
(119,235,'uploads/defects/235/floorplan_with_pin_defect.png','uploads/defects/235/floorplan_with_pin_defect.png',22,'2025-02-22 10:39:28','2025-02-22 10:39:28','synced',NULL,NULL,NULL,0),
(120,235,'uploads/defects/235/1740224369_File-Sharing-Construction.jpg',NULL,22,'2025-02-22 10:39:28','2025-02-22 10:39:28','synced',NULL,NULL,NULL,0),
(121,236,'uploads/defects/236/floorplan_with_pin_defect.png','uploads/defects/236/floorplan_with_pin_defect.png',22,'2025-02-22 10:42:20','2025-02-22 10:42:20','synced',NULL,NULL,NULL,0),
(122,236,'uploads/defects/236/1740224542_File-Sharing-Construction.jpg',NULL,22,'2025-02-22 10:42:20','2025-02-22 10:42:20','synced',NULL,NULL,NULL,0),
(128,240,'uploads/defects/240/floorplan_with_pin_defect.png','uploads/defects/240/floorplan_with_pin_defect.png',22,'2025-02-22 10:54:09','2025-02-22 10:54:09','synced',NULL,NULL,NULL,0),
(129,240,'uploads/defects/240/1740225251_File-Sharing-Construction.jpg',NULL,22,'2025-02-22 10:54:09','2025-02-22 10:54:09','synced',NULL,NULL,NULL,0),
(130,241,'uploads/defects/241/floorplan_with_pin_defect.png','uploads/defects/241/floorplan_with_pin_defect.png',22,'2025-02-22 10:56:15','2025-02-22 10:56:15','synced',NULL,NULL,NULL,0),
(131,241,'uploads/defects/241/1740225377_panacea.jpg',NULL,22,'2025-02-22 10:56:15','2025-02-22 10:56:15','synced',NULL,NULL,NULL,0),
(132,242,'uploads/defects/242/floorplan_with_pin_defect.png','uploads/defects/242/floorplan_with_pin_defect.png',22,'2025-02-22 11:14:02','2025-02-22 11:14:02','synced',NULL,NULL,NULL,0),
(133,243,'uploads/defects/243/floorplan_with_pin_defect.png','uploads/defects/243/floorplan_with_pin_defect.png',22,'2025-02-22 11:18:01','2025-02-22 11:18:01','synced',NULL,NULL,NULL,0),
(134,243,'uploads/defects/243/1740226683_Cara-Brickwork-logo.png',NULL,22,'2025-02-22 11:18:01','2025-02-22 11:18:01','synced',NULL,NULL,NULL,0),
(138,243,'uploads/defects/243/complete_Screenshot 2024-01-14 090417.png',NULL,28,'2025-02-23 13:47:18','2025-02-23 13:47:18','synced',NULL,NULL,NULL,0),
(139,242,'uploads/defects/242/complete_Screenshot 2024-01-25 202723.png',NULL,28,'2025-02-23 13:56:37','2025-02-23 13:56:37','synced',NULL,NULL,NULL,0),
(140,242,'uploads/defects/242/complete_Screenshot 2024-01-25 202723.png',NULL,28,'2025-02-23 13:56:47','2025-02-23 13:56:47','synced',NULL,NULL,NULL,0),
(141,242,'uploads/defects/242/complete_Screenshot 2024-01-25 202814.png',NULL,28,'2025-02-23 13:56:47','2025-02-23 13:56:47','synced',NULL,NULL,NULL,0),
(142,242,'uploads/defects/242/complete_Screenshot 2024-01-25 202840.png',NULL,28,'2025-02-23 13:56:47','2025-02-23 13:56:47','synced',NULL,NULL,NULL,0),
(143,243,'uploads/defects/243/complete_background.jpeg',NULL,28,'2025-02-23 16:13:31','2025-02-23 16:13:31','synced',NULL,NULL,NULL,0),
(144,242,'uploads/defects/242/complete_File-Sharing-Construction.jpg',NULL,28,'2025-02-23 16:18:01','2025-02-23 16:18:01','synced',NULL,NULL,NULL,0),
(145,243,'uploads/defects/243/complete_File-Sharing-Construction.jpg',NULL,28,'2025-02-23 16:24:15','2025-02-23 16:24:15','synced',NULL,NULL,NULL,0),
(146,244,'uploads/defects/244/floorplan_with_pin_defect.png','uploads/defects/244/floorplan_with_pin_defect.png',28,'0000-00-00 00:00:00','0000-00-00 00:00:00','synced',NULL,NULL,NULL,0),
(147,244,'uploads/defects/244/1740328831_File-Sharing-Construction.jpg',NULL,28,'0000-00-00 00:00:00','0000-00-00 00:00:00','synced',NULL,NULL,NULL,0),
(148,244,'uploads/defects/244/1740328831_File-Sharing-Construction.jpg',NULL,28,'0000-00-00 00:00:00','0000-00-00 00:00:00','synced',NULL,NULL,NULL,0),
(149,245,'uploads/defects/245/floorplan_with_pin_defect.png','uploads/defects/245/floorplan_with_pin_defect.png',28,'0000-00-00 00:00:00','0000-00-00 00:00:00','synced',NULL,NULL,NULL,0),
(150,245,'uploads/defects/245/1740430472_File-Sharing-Construction.jpg',NULL,28,'0000-00-00 00:00:00','0000-00-00 00:00:00','synced',NULL,NULL,NULL,0),
(151,245,'uploads/defects/245/1740430472_File-Sharing-Construction.jpg',NULL,28,'0000-00-00 00:00:00','0000-00-00 00:00:00','synced',NULL,NULL,NULL,0),
(152,246,'uploads/defects/246/floorplan_with_pin_defect.png','uploads/defects/246/floorplan_with_pin_defect.png',28,'2025-02-24 20:11:28','2025-02-24 20:11:28','synced',NULL,NULL,NULL,0),
(153,246,'uploads/defects/246/1740431490_background.jpeg',NULL,28,'2025-02-24 20:11:28','2025-02-24 20:11:28','synced',NULL,NULL,NULL,0),
(154,246,'uploads/defects/246/1740431490_background.jpeg',NULL,28,'2025-02-24 20:11:28','2025-02-24 20:11:28','synced',NULL,NULL,NULL,0),
(155,247,'uploads/defects/247/floorplan_with_pin_defect.png','uploads/defects/247/floorplan_with_pin_defect.png',22,'0000-00-00 00:00:00','0000-00-00 00:00:00','synced',NULL,NULL,NULL,0),
(156,247,'uploads/defects/247/1740605050_Screenshot 2024-01-25 202840.png',NULL,22,'0000-00-00 00:00:00','0000-00-00 00:00:00','synced',NULL,NULL,NULL,0),
(157,247,'uploads/defects/247/1740605050_Screenshot 2024-01-25 202840.png',NULL,22,'0000-00-00 00:00:00','0000-00-00 00:00:00','synced',NULL,NULL,NULL,0),
(158,248,'uploads/defects/248/floorplan_with_pin_defect.png','uploads/defects/248/floorplan_with_pin_defect.png',22,'0000-00-00 00:00:00','0000-00-00 00:00:00','synced',NULL,NULL,NULL,0),
(159,248,'uploads/defects/248/1740733800_17407337514093205248474664566120.jpg',NULL,22,'0000-00-00 00:00:00','0000-00-00 00:00:00','synced',NULL,NULL,NULL,0),
(160,248,'uploads/defects/248/1740733800_17407337514093205248474664566120.jpg',NULL,22,'0000-00-00 00:00:00','0000-00-00 00:00:00','synced',NULL,NULL,NULL,0),
(161,249,'uploads/defects/249/floorplan_with_pin_defect.png','uploads/defects/249/floorplan_with_pin_defect.png',22,'0000-00-00 00:00:00','0000-00-00 00:00:00','synced',NULL,NULL,NULL,0),
(162,249,'uploads/defects/249/1741007079_Screenshot 2024-11-20 105811.png',NULL,22,'0000-00-00 00:00:00','0000-00-00 00:00:00','synced',NULL,NULL,NULL,0),
(163,249,'uploads/defects/249/1741007079_Screenshot 2024-11-20 105811.png',NULL,22,'0000-00-00 00:00:00','0000-00-00 00:00:00','synced',NULL,NULL,NULL,0),
(169,251,'uploads/defects/251/floorplan_with_pin_defect.png','uploads/defects/251/floorplan_with_pin_defect.png',22,'2025-03-03 18:53:34','2025-03-03 18:53:34','synced',NULL,NULL,NULL,0),
(170,251,'uploads/defects/251/1741031616_Screenshot 2024-11-21 200558.png',NULL,22,'2025-03-03 18:53:34','2025-03-03 18:53:34','synced',NULL,NULL,NULL,0),
(171,251,'uploads/defects/251/1741031616_Screenshot 2024-11-21 200558.png',NULL,22,'2025-03-03 18:53:34','2025-03-03 18:53:34','synced',NULL,NULL,NULL,0),
(172,252,'uploads/defects/252/floorplan_with_pin_defect.png','uploads/defects/252/floorplan_with_pin_defect.png',22,'2025-03-04 14:45:44','2025-03-04 14:45:44','synced',NULL,NULL,NULL,0),
(173,252,'uploads/defects/252/1741103146_17411030640411857659578433437549.jpg',NULL,22,'2025-03-04 14:45:44','2025-03-04 14:45:44','synced',NULL,NULL,NULL,0),
(174,252,'uploads/defects/252/1741103146_17411030640411857659578433437549.jpg',NULL,22,'2025-03-04 14:45:44','2025-03-04 14:45:44','synced',NULL,NULL,NULL,0),
(175,253,'uploads/defects/253/floorplan_with_pin_defect.png','uploads/defects/253/floorplan_with_pin_defect.png',22,'2025-03-05 06:19:50','2025-03-05 06:19:50','synced',NULL,NULL,NULL,0),
(176,254,'uploads/defects/254/floorplan_with_pin_defect.png','uploads/defects/254/floorplan_with_pin_defect.png',22,'2025-03-05 06:21:16','2025-03-05 06:21:16','synced',NULL,NULL,NULL,0),
(177,254,'uploads/defects/254/1741159278_Untitled.png',NULL,22,'2025-03-05 06:21:16','2025-03-05 06:21:16','synced',NULL,NULL,NULL,0),
(178,256,'uploads/defects/256/floorplan_with_pin_defect.png','uploads/defects/256/floorplan_with_pin_defect.png',22,'2025-03-07 08:54:27','2025-03-07 08:54:27','synced',NULL,NULL,NULL,0),
(179,257,'uploads/defects/257/floorplan_with_pin_defect.png','uploads/defects/257/floorplan_with_pin_defect.png',22,'2025-03-10 20:34:43','2025-03-10 20:34:43','synced',NULL,NULL,NULL,0),
(180,258,'uploads/defects/258/floorplan_with_pin_defect.png','uploads/defects/258/floorplan_with_pin_defect.png',22,'2025-03-10 20:34:45','2025-03-10 20:34:45','synced',NULL,NULL,NULL,0),
(181,259,'uploads/defects/259/floorplan_with_pin_defect.png','uploads/defects/259/floorplan_with_pin_defect.png',22,'2025-03-10 20:38:22','2025-03-10 20:38:22','synced',NULL,NULL,NULL,0),
(182,260,'uploads/defects/260/floorplan_with_pin_defect.png','uploads/defects/260/floorplan_with_pin_defect.png',22,'2025-03-10 20:38:23','2025-03-10 20:38:23','synced',NULL,NULL,NULL,0),
(183,261,'uploads/defects/261/floorplan_with_pin_defect.png','uploads/defects/261/floorplan_with_pin_defect.png',22,'2025-03-11 06:37:11','2025-03-11 06:37:11','synced',NULL,NULL,NULL,0),
(184,261,'uploads/defects/261/1741678633_McGoff-circle.png',NULL,22,'2025-03-11 06:37:11','2025-03-11 06:37:11','synced',NULL,NULL,NULL,0),
(185,262,'uploads/defects/262/floorplan_with_pin_defect.png','uploads/defects/262/floorplan_with_pin_defect.png',22,'2025-03-11 06:37:13','2025-03-11 06:37:13','synced',NULL,NULL,NULL,0),
(186,262,'uploads/defects/262/1741678635_McGoff-circle.png',NULL,22,'2025-03-11 06:37:13','2025-03-11 06:37:13','synced',NULL,NULL,NULL,0),
(187,263,'uploads/defects/263/floorplan_with_pin_defect.png','uploads/defects/263/floorplan_with_pin_defect.png',22,'2025-03-11 06:43:30','2025-03-11 06:43:30','synced',NULL,NULL,NULL,0),
(188,264,'uploads/defects/264/floorplan_with_pin_defect.png','uploads/defects/264/floorplan_with_pin_defect.png',22,'2025-03-11 06:43:32','2025-03-11 06:43:32','synced',NULL,NULL,NULL,0),
(189,265,'uploads/defects/265/floorplan_with_pin_defect.png','uploads/defects/265/floorplan_with_pin_defect.png',22,'2025-03-11 19:04:29','2025-03-11 19:04:29','synced',NULL,NULL,NULL,0),
(190,266,'uploads/defects/266/floorplan_with_pin_defect.png','uploads/defects/266/floorplan_with_pin_defect.png',22,'2025-03-11 19:04:31','2025-03-11 19:04:31','synced',NULL,NULL,NULL,0),
(191,267,'uploads/defects/267/floorplan_with_pin_defect.png','uploads/defects/267/floorplan_with_pin_defect.png',22,'2025-03-11 19:16:47','2025-03-11 19:16:47','synced',NULL,NULL,NULL,0),
(192,268,'uploads/defects/268/floorplan_with_pin_defect.png','uploads/defects/268/floorplan_with_pin_defect.png',22,'2025-03-11 19:16:49','2025-03-11 19:16:49','synced',NULL,NULL,NULL,0),
(193,269,'uploads/defects/269/floorplan_with_pin_defect.png','uploads/defects/269/floorplan_with_pin_defect.png',22,'2025-03-11 19:27:39','2025-03-11 19:27:39','synced',NULL,NULL,NULL,0),
(194,270,'uploads/defects/270/floorplan_with_pin_defect.png','uploads/defects/270/floorplan_with_pin_defect.png',22,'2025-03-11 19:29:42','2025-03-11 19:29:42','synced',NULL,NULL,NULL,0),
(195,270,'uploads/defects/270/1741724984_67a507e57e370_red-windows.png',NULL,22,'2025-03-11 19:29:42','2025-03-11 19:29:42','synced',NULL,NULL,NULL,0),
(196,271,'uploads/defects/271/floorplan_with_pin_defect.png','uploads/defects/271/floorplan_with_pin_defect.png',22,'2025-03-11 19:36:28','2025-03-11 19:36:28','synced',NULL,NULL,NULL,0),
(197,272,'uploads/defects/272/floorplan_with_pin_defect.png','uploads/defects/272/floorplan_with_pin_defect.png',22,'2025-03-11 19:37:13','2025-03-11 19:37:13','synced',NULL,NULL,NULL,0),
(198,273,'uploads/defects/273/floorplan_with_pin_defect.png','uploads/defects/273/floorplan_with_pin_defect.png',22,'2025-03-11 19:37:46','2025-03-11 19:37:46','synced',NULL,NULL,NULL,0),
(199,273,'uploads/defects/273/1741725468_67a50733423ee_Cara-Brickwork-logo.png',NULL,22,'2025-03-11 19:37:46','2025-03-11 19:37:46','synced',NULL,NULL,NULL,0),
(200,274,'uploads/defects/274/floorplan_with_pin_defect.png','uploads/defects/274/floorplan_with_pin_defect.png',22,'2025-03-11 19:44:56','2025-03-11 19:44:56','synced',NULL,NULL,NULL,0),
(201,275,'uploads/defects/275/floorplan_with_pin_defect.png','uploads/defects/275/floorplan_with_pin_defect.png',22,'2025-03-11 19:51:43','2025-03-11 19:51:43','synced',NULL,NULL,NULL,0),
(202,276,'uploads/defects/276/floorplan_with_pin_defect.png','uploads/defects/276/floorplan_with_pin_defect.png',22,'2025-03-11 19:56:05','2025-03-11 19:56:05','synced',NULL,NULL,NULL,0),
(203,276,'uploads/defects/276/1741726567_67a50733423ee_Cara-Brickwork-logo.png',NULL,22,'2025-03-11 19:56:05','2025-03-11 19:56:05','synced',NULL,NULL,NULL,0),
(204,277,'uploads/defects/277/floorplan_with_pin_defect.png','uploads/defects/277/floorplan_with_pin_defect.png',22,'2025-03-11 19:58:45','2025-03-11 19:58:45','synced',NULL,NULL,NULL,0),
(205,277,'uploads/defects/277/1741726726_67a50733423ee_Cara-Brickwork-logo.png',NULL,22,'2025-03-11 19:58:45','2025-03-11 19:58:45','synced',NULL,NULL,NULL,0),
(206,278,'uploads/defects/278/floorplan_with_pin_defect.png','uploads/defects/278/floorplan_with_pin_defect.png',22,'2025-03-11 20:02:35','2025-03-11 20:02:35','synced',NULL,NULL,NULL,0),
(207,279,'uploads/defects/279/floorplan_with_pin_defect.png','uploads/defects/279/floorplan_with_pin_defect.png',22,'2025-03-11 20:03:34','2025-03-11 20:03:34','synced',NULL,NULL,NULL,0),
(208,279,'uploads/defects/279/1741727016_67a50733423ee_Cara-Brickwork-logo.png',NULL,22,'2025-03-11 20:03:34','2025-03-11 20:03:34','synced',NULL,NULL,NULL,0),
(209,280,'uploads/defects/280/floorplan_with_pin_defect.png','uploads/defects/280/floorplan_with_pin_defect.png',22,'2025-03-11 20:04:18','2025-03-11 20:04:18','synced',NULL,NULL,NULL,0),
(210,281,'uploads/defects/281/floorplan_with_pin_defect.png','uploads/defects/281/floorplan_with_pin_defect.png',22,'2025-03-11 20:07:41','2025-03-11 20:07:41','synced',NULL,NULL,NULL,0),
(211,281,'uploads/defects/281/1741727263_67a50733423ee_Cara-Brickwork-logo.png',NULL,22,'2025-03-11 20:07:41','2025-03-11 20:07:41','synced',NULL,NULL,NULL,0),
(212,282,'uploads/defects/282/floorplan_with_pin_defect.png','uploads/defects/282/floorplan_with_pin_defect.png',22,'2025-03-11 20:08:14','2025-03-11 20:08:14','synced',NULL,NULL,NULL,0),
(213,283,'uploads/defects/283/floorplan_with_pin_defect.png','uploads/defects/283/floorplan_with_pin_defect.png',22,'2025-03-11 20:10:21','2025-03-11 20:10:21','synced',NULL,NULL,NULL,0),
(214,283,'uploads/defects/283/1741727423_67a50733423ee_Cara-Brickwork-logo.png',NULL,22,'2025-03-11 20:10:21','2025-03-11 20:10:21','synced',NULL,NULL,NULL,0),
(215,284,'uploads/defects/284/floorplan_with_pin_defect.png','uploads/defects/284/floorplan_with_pin_defect.png',22,'2025-03-11 20:11:19','2025-03-11 20:11:19','synced',NULL,NULL,NULL,0),
(216,285,'uploads/defects/285/floorplan_with_pin_defect.png','uploads/defects/285/floorplan_with_pin_defect.png',22,'2025-03-11 20:22:59','2025-03-11 20:22:59','synced',NULL,NULL,NULL,0),
(217,285,'uploads/defects/285/1741728180_67a50733423ee_Cara-Brickwork-logo.png',NULL,22,'2025-03-11 20:22:59','2025-03-11 20:22:59','synced',NULL,NULL,NULL,0),
(218,286,'uploads/defects/286/floorplan_with_pin_defect.png','uploads/defects/286/floorplan_with_pin_defect.png',22,'2025-03-11 20:23:38','2025-03-11 20:23:38','synced',NULL,NULL,NULL,0),
(219,287,'uploads/defects/287/floorplan_with_pin_defect.png','uploads/defects/287/floorplan_with_pin_defect.png',22,'2025-03-11 20:30:45','2025-03-11 20:30:45','synced',NULL,NULL,NULL,0),
(220,287,'uploads/defects/287/1741728647_67a50733423ee_Cara-Brickwork-logo.png',NULL,22,'2025-03-11 20:30:45','2025-03-11 20:30:45','synced',NULL,NULL,NULL,0),
(221,288,'uploads/defects/288/floorplan_with_pin_defect.png','uploads/defects/288/floorplan_with_pin_defect.png',22,'2025-03-11 20:31:30','2025-03-11 20:31:30','synced',NULL,NULL,NULL,0),
(222,289,'uploads/defects/289/floorplan_with_pin_defect.png','uploads/defects/289/floorplan_with_pin_defect.png',22,'2025-03-11 20:33:36','2025-03-11 20:33:36','synced',NULL,NULL,NULL,0),
(223,289,'uploads/defects/289/1741728818_67a50733423ee_Cara-Brickwork-logo.png',NULL,22,'2025-03-11 20:33:36','2025-03-11 20:33:36','synced',NULL,NULL,NULL,0),
(224,290,'uploads/defects/290/floorplan_with_pin_defect.png','uploads/defects/290/floorplan_with_pin_defect.png',22,'2025-03-11 20:44:14','2025-03-11 20:44:14','synced',NULL,NULL,NULL,0),
(225,290,'uploads/defects/290/1741729456_67a50733423ee_Cara-Brickwork-logo.png',NULL,22,'2025-03-11 20:44:14','2025-03-11 20:44:14','synced',NULL,NULL,NULL,0),
(226,291,'uploads/defects/291/floorplan_with_pin_defect.png','uploads/defects/291/floorplan_with_pin_defect.png',22,'2025-03-11 20:44:43','2025-03-11 20:44:43','synced',NULL,NULL,NULL,0),
(227,292,'uploads/defects/292/floorplan_with_pin_defect.png','uploads/defects/292/floorplan_with_pin_defect.png',22,'2025-03-11 20:53:59','2025-03-11 20:53:59','synced',NULL,NULL,NULL,0),
(228,292,'uploads/defects/292/1741730041_67d0b0f9c6c06_67a50733423ee_Cara-Brickwork-logo.png',NULL,22,'2025-03-11 20:53:59','2025-03-11 20:53:59','synced',NULL,NULL,NULL,0),
(229,293,'uploads/defects/293/floorplan_with_pin_defect.png','uploads/defects/293/floorplan_with_pin_defect.png',22,'2025-03-11 20:54:33','2025-03-11 20:54:33','synced',NULL,NULL,NULL,0),
(230,294,'uploads/defects/294/floorplan_with_pin_defect.png','uploads/defects/294/floorplan_with_pin_defect.png',22,'2025-03-11 21:00:38','2025-03-11 21:00:38','synced',NULL,NULL,NULL,0),
(231,294,'uploads/defects/294/1741730440_67d0b2888d8b7_67a50733423ee_Cara-Brickwork-logo.png',NULL,22,'2025-03-11 21:00:38','2025-03-11 21:00:38','synced',NULL,NULL,NULL,0),
(232,295,'uploads/defects/295/floorplan_with_pin_defect.png','uploads/defects/295/floorplan_with_pin_defect.png',22,'2025-03-11 21:01:06','2025-03-11 21:01:06','synced',NULL,NULL,NULL,0),
(233,296,'uploads/defects/296/floorplan_with_pin_defect.png','uploads/defects/296/floorplan_with_pin_defect.png',22,'2025-03-11 21:04:04','2025-03-11 21:04:04','synced',NULL,NULL,NULL,0),
(234,296,'uploads/defects/296/1741730645_67d0b355de646_67b6c5366a2bf_craven-logo-4226456076.png',NULL,22,'2025-03-11 21:04:04','2025-03-11 21:04:04','synced',NULL,NULL,NULL,0),
(235,297,'uploads/defects/297/floorplan_with_pin_defect.png','uploads/defects/297/floorplan_with_pin_defect.png',22,'2025-03-12 06:33:40','2025-03-12 06:33:40','synced',NULL,NULL,NULL,0),
(236,297,'uploads/defects/297/1741764822_67d138d638544_edited_image_1741764816124.png',NULL,22,'2025-03-12 06:33:40','2025-03-12 06:33:40','synced',NULL,NULL,NULL,1),
(237,298,'uploads/defects/298/floorplan_with_pin_defect.png','uploads/defects/298/floorplan_with_pin_defect.png',22,'2025-03-12 06:35:06','2025-03-12 06:35:06','synced',NULL,NULL,NULL,0),
(238,298,'uploads/defects/298/1741764907_67d1392bf1d20_edited_image_1741764903079.png',NULL,22,'2025-03-12 06:35:06','2025-03-12 06:35:06','synced',NULL,NULL,NULL,1),
(239,298,'uploads/defects/298/1741764907_67d1392bf2aab_Screenshot 2024-11-15 114811.png',NULL,22,'2025-03-12 06:35:06','2025-03-12 06:35:06','synced',NULL,NULL,NULL,0),
(240,299,'uploads/defects/299/floorplan_with_pin_defect.png','uploads/defects/299/floorplan_with_pin_defect.png',22,'2025-03-13 08:54:24','2025-03-13 08:54:24','synced',NULL,NULL,NULL,0),
(241,299,'uploads/defects/299/1741859666_67d2ab5228ae6_edited_image_1741859661459.png',NULL,22,'2025-03-13 08:54:24','2025-03-13 08:54:24','synced',NULL,NULL,NULL,1),
(242,300,'uploads/defects/300/floorplan_with_pin_defect.png','uploads/defects/300/floorplan_with_pin_defect.png',22,'2025-03-13 08:59:58','2025-03-13 08:59:58','synced',NULL,NULL,NULL,0),
(243,300,'uploads/defects/300/1741859999_67d2ac9fbe4e2_edited_image_1741859994573.png',NULL,22,'2025-03-13 08:59:58','2025-03-13 08:59:58','synced',NULL,NULL,NULL,1),
(244,300,'uploads/defects/300/1741859999_67d2ac9fbee46_Screenshot 2024-11-15 114811.png',NULL,22,'2025-03-13 08:59:58','2025-03-13 08:59:58','synced',NULL,NULL,NULL,0),
(245,301,'uploads/defects/301/floorplan_with_pin_defect.png','uploads/defects/301/floorplan_with_pin_defect.png',22,'2025-03-13 09:21:56','2025-03-13 09:21:56','synced',NULL,NULL,NULL,0),
(246,301,'uploads/defects/301/1741861317_67d2b1c5e65f8_edited_image_1741861313595.png',NULL,22,'2025-03-13 09:21:56','2025-03-13 09:21:56','synced',NULL,NULL,NULL,1),
(247,302,'uploads/defects/302/floorplan_with_pin_defect.png','uploads/defects/302/floorplan_with_pin_defect.png',22,'2025-03-13 11:28:23','2025-03-13 11:28:23','synced',NULL,NULL,NULL,0),
(248,302,'uploads/defects/302/1741868905_67d2cf69e50b7_builderstorm.png',NULL,22,'2025-03-13 11:28:23','2025-03-13 11:28:23','synced',NULL,NULL,NULL,0),
(249,303,'uploads/defects/303/floorplan_with_pin_defect.png','uploads/defects/303/floorplan_with_pin_defect.png',22,'2025-03-13 14:30:54','2025-03-13 14:30:54','synced',NULL,NULL,NULL,0),
(250,303,'uploads/defects/303/1741879856_67d2fa308e28e_edited_image_1741879851972.png',NULL,22,'2025-03-13 14:30:54','2025-03-13 14:30:54','synced',NULL,NULL,NULL,1),
(251,304,'uploads/defects/304/floorplan_with_pin_defect.png','uploads/defects/304/floorplan_with_pin_defect.png',22,'2025-03-13 19:18:59','2025-03-13 19:18:59','synced',NULL,NULL,NULL,0),
(252,304,'uploads/defects/304/1741897141_67d33db503721_edited_image_1741897142178.png',NULL,22,'2025-03-13 19:18:59','2025-03-13 19:18:59','synced',NULL,NULL,NULL,1),
(253,304,'uploads/defects/304/1741897141_67d33db5041ab_67ad0b5e5ec03_Heyrod-3079453049.png',NULL,22,'2025-03-13 19:18:59','2025-03-13 19:18:59','synced',NULL,NULL,NULL,0),
(254,305,'uploads/defects/305/floorplan_with_pin_defect.png','uploads/defects/305/floorplan_with_pin_defect.png',22,'2025-03-16 16:26:21','2025-03-16 16:26:21','synced',NULL,NULL,NULL,0),
(255,306,'uploads/defects/306/floorplan_with_pin_defect.png','uploads/defects/306/floorplan_with_pin_defect.png',22,'2025-03-16 17:54:32','2025-03-16 17:54:32','synced',NULL,NULL,NULL,0),
(256,308,'uploads/defects/308/floorplan_with_pin_defect.png','uploads/defects/308/floorplan_with_pin_defect.png',22,'2025-03-17 09:39:58','2025-03-17 09:39:58','synced',NULL,NULL,NULL,0),
(257,308,'uploads/defects/308/1742207999_67d7fbffca0a2_edited_image_1742207992598.png',NULL,22,'2025-03-17 09:39:58','2025-03-17 09:39:58','synced',NULL,NULL,NULL,1),
(258,308,'uploads/defects/308/1742207999_67d7fbffcaa2e_174220798225129808948097364251.jpg',NULL,22,'2025-03-17 09:39:58','2025-03-17 09:39:58','synced',NULL,NULL,NULL,0),
(259,309,'uploads/defects/309/floorplan_with_pin_defect.png','uploads/defects/309/floorplan_with_pin_defect.png',22,'2025-03-18 19:07:12','2025-03-18 19:07:12','synced',NULL,NULL,NULL,0),
(260,309,'uploads/defects/309/1742328434_67d9d27255e99_edited_image_1742328430049.png',NULL,22,'2025-03-18 19:07:12','2025-03-18 19:07:12','synced',NULL,NULL,NULL,1),
(261,310,'uploads/defects/310/floorplan_with_pin_defect.png','uploads/defects/310/floorplan_with_pin_defect.png',22,'2025-03-18 20:46:27','2025-03-18 20:46:27','synced',NULL,NULL,NULL,0),
(262,310,'uploads/defects/310/1742334389_67d9e9b5c3fff_67b6c5bfbc505_300164-2153089336.jpg',NULL,22,'2025-03-18 20:46:27','2025-03-18 20:46:27','synced',NULL,NULL,NULL,0),
(263,311,'uploads/defects/311/floorplan_with_pin_defect.png','uploads/defects/311/floorplan_with_pin_defect.png',22,'2025-03-19 19:34:50','2025-03-19 19:34:50','synced',NULL,NULL,NULL,0),
(264,311,'uploads/defects/311/1742416491_67db2a6bc2077_edited_image_1742416487074.png',NULL,22,'2025-03-19 19:34:50','2025-03-19 19:34:50','synced',NULL,NULL,NULL,1),
(265,311,'uploads/defects/311/1742416491_67db2a6bc2697_67b6c5366a2bf_craven-logo-4226456076.png',NULL,22,'2025-03-19 19:34:50','2025-03-19 19:34:50','synced',NULL,NULL,NULL,0),
(266,312,'uploads/defects/312/floorplan_with_pin_defect.png','uploads/defects/312/floorplan_with_pin_defect.png',22,'2025-03-19 20:37:22','2025-03-19 20:37:22','synced',NULL,NULL,NULL,0),
(267,312,'uploads/defects/312/1742420244_67db3914a0361_edited_image_1742420239191.png',NULL,22,'2025-03-19 20:37:22','2025-03-19 20:37:22','synced',NULL,NULL,NULL,1),
(268,313,'uploads/defects/313/floorplan_with_pin_defect.png','uploads/defects/313/floorplan_with_pin_defect.png',22,'2025-03-19 20:49:13','2025-03-19 20:49:13','synced',NULL,NULL,NULL,0),
(269,313,'uploads/defects/313/1742420954_67db3bda99034_edited_image_1742420950634.png',NULL,22,'2025-03-19 20:49:13','2025-03-19 20:49:13','synced',NULL,NULL,NULL,1),
(270,313,'uploads/defects/313/1742420954_67db3bda99948_67adb16de7b85_mcgoff.png',NULL,22,'2025-03-19 20:49:13','2025-03-19 20:49:13','synced',NULL,NULL,NULL,0),
(271,314,'uploads/defects/314/floorplan_with_pin_defect.png','uploads/defects/314/floorplan_with_pin_defect.png',22,'2025-03-20 14:16:57','2025-03-20 14:16:57','synced',NULL,NULL,NULL,0),
(272,314,'uploads/defects/314/1742483818_67dc316ae22bf_edited_image_1742483814296.png',NULL,22,'2025-03-20 14:16:57','2025-03-20 14:16:57','synced',NULL,NULL,NULL,1),
(273,314,'uploads/defects/314/1742483818_67dc316ae28d8_67ad0b5e5ec03_Heyrod-3079453049.png',NULL,22,'2025-03-20 14:16:57','2025-03-20 14:16:57','synced',NULL,NULL,NULL,0),
(274,315,'uploads/defects/315/floorplan_with_pin_defect.png','uploads/defects/315/floorplan_with_pin_defect.png',22,'2025-03-20 14:21:44','2025-03-20 14:21:44','synced',NULL,NULL,NULL,0),
(275,315,'uploads/defects/315/1742484106_67dc328a1aa3a_edited_image_1742484101800.png',NULL,22,'2025-03-20 14:21:44','2025-03-20 14:21:44','synced',NULL,NULL,NULL,1),
(276,315,'uploads/defects/315/1742484106_67dc328a1b1a4_67a50733423ee_Cara-Brickwork-logo.png',NULL,22,'2025-03-20 14:21:44','2025-03-20 14:21:44','synced',NULL,NULL,NULL,0),
(277,316,'uploads/defects/316/floorplan_with_pin_defect.png','uploads/defects/316/floorplan_with_pin_defect.png',22,'2025-03-20 14:43:52','2025-03-20 14:43:52','synced',NULL,NULL,NULL,0),
(278,316,'uploads/defects/316/1742485434_67dc37bac7585_67b6c5366a2bf_craven-logo-4226456076.png',NULL,22,'2025-03-20 14:43:52','2025-03-20 14:43:52','synced',NULL,NULL,NULL,0),
(279,317,'uploads/defects/317/floorplan_with_pin_defect.png','uploads/defects/317/floorplan_with_pin_defect.png',22,'2025-03-21 13:20:16','2025-03-21 13:20:16','synced',NULL,NULL,NULL,0),
(280,317,'uploads/defects/317/1742566818_67dd75a28f660_edited_image_1742566808506.png',NULL,22,'2025-03-21 13:20:16','2025-03-21 13:20:16','synced',NULL,NULL,NULL,1),
(281,317,'uploads/defects/317/1742566818_67dd75a28fec8_17425667962588291067984988156043.jpg',NULL,22,'2025-03-21 13:20:16','2025-03-21 13:20:16','synced',NULL,NULL,NULL,0),
(282,318,'uploads/defects/318/floorplan_with_pin_defect.png','uploads/defects/318/floorplan_with_pin_defect.png',22,'2025-03-22 12:49:03','2025-03-22 12:49:03','synced',NULL,NULL,NULL,0),
(283,318,'uploads/defects/318/1742651345_67debfd1a6c11_edited_image_1742651340853.png',NULL,22,'2025-03-22 12:49:03','2025-03-22 12:49:03','synced',NULL,NULL,NULL,1),
(284,319,'uploads/defects/319/floorplan_with_pin_defect.png','uploads/defects/319/floorplan_with_pin_defect.png',22,'2025-03-22 17:17:52','2025-03-22 17:17:52','synced',NULL,NULL,NULL,0),
(285,319,'uploads/defects/319/1742667474_67defed20e404_edited_image_1742667465736.png',NULL,22,'2025-03-22 17:17:52','2025-03-22 17:17:52','synced',NULL,NULL,NULL,1),
(286,319,'uploads/defects/319/1742667474_67defed20ee80_17426674415955206068123009608198.jpg',NULL,22,'2025-03-22 17:17:52','2025-03-22 17:17:52','synced',NULL,NULL,NULL,0),
(287,320,'uploads/defects/320/floorplan_with_pin_defect.png','uploads/defects/320/floorplan_with_pin_defect.png',22,'2025-03-24 13:36:39','2025-03-24 13:36:39','synced',NULL,NULL,NULL,0),
(288,320,'uploads/defects/320/1742827001_67e16df959150_edited_image_1742826990887.png',NULL,22,'2025-03-24 13:36:39','2025-03-24 13:36:39','synced',NULL,NULL,NULL,1),
(289,320,'uploads/defects/320/1742827001_67e16df959d7b_17428269752293098553741555037185.jpg',NULL,22,'2025-03-24 13:36:39','2025-03-24 13:36:39','synced',NULL,NULL,NULL,0),
(290,321,'uploads/defects/321/floorplan_with_pin_defect.png','uploads/defects/321/floorplan_with_pin_defect.png',22,'2025-03-24 14:13:02','2025-03-24 14:13:02','synced',NULL,NULL,NULL,0),
(291,322,'uploads/defects/322/floorplan_with_pin_defect.png','uploads/defects/322/floorplan_with_pin_defect.png',22,'2025-03-28 14:22:47','2025-03-28 14:22:47','synced',NULL,NULL,NULL,0),
(292,322,'uploads/defects/322/1743175368_67e6bec8eea5d_background.jpeg',NULL,22,'2025-03-28 14:22:47','2025-03-28 14:22:47','synced',NULL,NULL,NULL,0);
/*!40000 ALTER TABLE `defect_images` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'IGNORE_SPACE,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`k87747_defecttracker`@`%`*/ /*!50003 TRIGGER `defect_images_before_update` BEFORE UPDATE ON `defect_images` FOR EACH ROW BEGIN
                    -- Only mark as pending if this is a direct update, not from the sync system
                    IF NEW.sync_status = 'synced' AND OLD.uploaded_at != NEW.uploaded_at THEN
                        SET NEW.sync_status = 'pending';
                        SET NEW.sync_timestamp = NOW();
                    END IF;
                END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `defects`
--

DROP TABLE IF EXISTS `defects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `defects` (
  `id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `floor_plan_id` int NOT NULL,
  `reported_by` int NOT NULL,
  `assigned_to` bigint unsigned DEFAULT NULL,
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
  `contractor_id` bigint unsigned DEFAULT NULL,
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
  `rejected_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `floor_plan_id` (`floor_plan_id`),
  KEY `fk_project_id` (`project_id`),
  KEY `fk_reported_by` (`reported_by`),
  KEY `fk_contractor_id` (`contractor_id`),
  KEY `updated_by` (`updated_by`),
  KEY `idx_deleted_at` (`deleted_at`),
  KEY `fk_assigned_to_contractor` (`assigned_to`),
  KEY `reopened_by` (`reopened_by`),
  KEY `idx_defects_accepted_by` (`accepted_by`),
  KEY `idx_defects_accepted_at` (`accepted_at`),
  KEY `sync_status` (`sync_status`),
  KEY `client_id` (`client_id`),
  KEY `fk_rejected_by` (`rejected_by`),
  CONSTRAINT `defects_ibfk_2` FOREIGN KEY (`floor_plan_id`) REFERENCES `floor_plans` (`id`),
  CONSTRAINT `defects_ibfk_3` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`),
  CONSTRAINT `defects_ibfk_4` FOREIGN KEY (`reopened_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_assigned_to_contractor` FOREIGN KEY (`assigned_to`) REFERENCES `contractors` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_contractor_id` FOREIGN KEY (`contractor_id`) REFERENCES `contractors` (`id`),
  CONSTRAINT `fk_defect_contractor` FOREIGN KEY (`contractor_id`) REFERENCES `contractors` (`id`),
  CONSTRAINT `fk_defects_accepted_by` FOREIGN KEY (`accepted_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_project_id` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rejected_by` FOREIGN KEY (`rejected_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_reported_by` FOREIGN KEY (`reported_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=323 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `defects`
--

LOCK TABLES `defects` WRITE;
/*!40000 ALTER TABLE `defects` DISABLE KEYS */;
INSERT INTO `defects` VALUES
(229,12,104,22,31,'mm','mmmm','open',NULL,NULL,NULL,'medium','0000-00-00','2025-02-19 17:20:29','2025-02-23 11:14:58','synced',NULL,NULL,NULL,22,31,22,0.233263,0.462105,NULL,NULL,NULL,'2025-02-23 12:14:58',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(230,12,110,22,29,'ggg','gggg','open',NULL,NULL,NULL,'medium','0000-00-00','2025-02-20 21:08:23','2025-03-22 13:14:52','pending',NULL,'2025-03-22 14:14:52',NULL,NULL,29,22,0.431875,0.469147,NULL,NULL,NULL,'2025-03-22 14:14:52',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(231,12,112,22,29,'hh','m','open',NULL,NULL,NULL,'low','0000-00-00','2025-02-20 21:26:17','2025-03-22 16:11:49','pending',NULL,'2025-03-22 17:11:49',NULL,NULL,29,22,0.319641,0.57128,NULL,NULL,NULL,'2025-03-22 17:11:49',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(232,12,106,22,56,'tttt','ttttt','open',NULL,NULL,NULL,'medium','0000-00-00','2025-02-22 10:26:10','2025-03-22 16:11:43','pending',NULL,'2025-03-22 17:11:43',NULL,NULL,56,22,0.321133,0.462302,NULL,NULL,NULL,'2025-03-22 17:11:43',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(233,12,106,22,52,'kkk','kkkk','accepted','uploads/defect_images/defect_67bc6d429caeb_20250224_125946.png','not good','ok','medium','0000-00-00','2025-02-22 10:27:07','2025-03-22 16:11:15','pending',NULL,'2025-03-22 17:11:15',NULL,NULL,52,22,0.675325,0.459764,NULL,NULL,NULL,'2025-03-22 17:11:15',NULL,NULL,22,'2025-02-22 11:32:41',NULL,NULL,1,NULL),
(234,12,106,22,53,'mmm','mmmm','open',NULL,NULL,NULL,'high','0000-00-00','2025-02-22 10:33:26','2025-03-22 13:14:42','pending',NULL,'2025-03-22 14:14:42',NULL,NULL,53,22,0.422668,0.466955,NULL,NULL,NULL,'2025-03-22 14:14:42',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(235,12,106,22,54,'lll','llll','open',NULL,NULL,NULL,'medium','0000-00-00','2025-02-22 10:39:28','2025-03-22 16:11:38','pending',NULL,'2025-03-22 17:11:38',NULL,NULL,54,22,0.420307,0.58688,NULL,NULL,NULL,'2025-03-22 17:11:38',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(236,12,106,22,29,'bbb','bbb','open',NULL,NULL,NULL,'medium','0000-00-00','2025-02-22 10:42:20','2025-03-22 13:46:23','pending',NULL,'2025-03-22 14:46:23',NULL,NULL,29,22,0.309327,0.46484,NULL,NULL,NULL,'2025-03-22 14:46:23',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(240,12,106,22,56,'jjj','jjj','open',NULL,NULL,NULL,'high','0000-00-00','2025-02-22 10:54:09','2025-03-22 16:11:33','pending',NULL,'2025-03-22 17:11:33',NULL,NULL,56,22,0.533648,0.297538,NULL,NULL,NULL,'2025-03-22 17:11:33',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(241,12,106,22,30,'ddd','ddd','accepted','uploads/defect_images/defect_67bc6d429caeb_20250224_125946.png','not good','ok','high','0000-00-00','2025-02-22 10:56:15','2025-03-22 16:11:27','pending',NULL,'2025-03-22 17:11:27',NULL,NULL,30,22,0.212514,0.454899,NULL,NULL,NULL,'2025-03-22 17:11:27',NULL,NULL,22,'2025-02-24 13:00:33',NULL,NULL,1,NULL),
(242,12,106,22,55,'hhh','hhhhh','accepted','uploads/defect_images/defect_67bc6d429caeb_20250224_125946.png','not good','ok','medium','0000-00-00','2025-02-22 11:14:02','2025-02-24 13:01:58','synced',NULL,NULL,NULL,22,55,22,0.422668,0.673175,NULL,NULL,NULL,'2025-02-24 14:00:21',NULL,NULL,22,'2025-02-24 12:54:36',NULL,NULL,1,NULL),
(243,12,106,22,53,'ttt','ttt','accepted','uploads/defects/243/complete_File-Sharing-Construction.jpg','not good','ok','medium','0000-00-00','2025-02-22 11:18:01','2025-02-24 13:01:54','synced',NULL,NULL,NULL,NULL,53,22,0.107437,0.64018,NULL,NULL,NULL,'2025-02-24 14:00:15',NULL,NULL,22,'2025-02-24 12:49:24',NULL,NULL,1,NULL),
(244,12,106,28,30,'ooo','oooo','accepted','uploads/defect_images/defect_67bc6d429caeb_20250224_125946.png','not good','ok','high','0000-00-00','0000-00-00 00:00:00','2025-02-24 13:02:16','synced',NULL,NULL,NULL,28,30,28,0.22887,0.323617,NULL,NULL,NULL,'2025-02-24 09:23:36','rejected',NULL,22,'2025-02-24 08:22:23',NULL,NULL,1,NULL),
(245,12,104,28,55,'bbb','bbbb','open',NULL,NULL,NULL,'medium','0000-00-00','0000-00-00 00:00:00','2025-03-22 16:11:22','pending',NULL,'2025-03-22 17:11:22',NULL,NULL,55,28,0.236389,0.313117,NULL,NULL,NULL,'2025-03-22 17:11:22',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(246,12,104,28,56,'bbb','bbbb','open',NULL,NULL,NULL,'low','2025-03-03','2025-02-24 20:11:28','2025-02-24 21:11:28','synced',NULL,NULL,NULL,NULL,56,28,0.486919,0.649783,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(247,13,113,22,56,'blah','blah','accepted',NULL,NULL,'all ok','critical','0000-00-00','0000-00-00 00:00:00','2025-03-22 16:10:28','pending',NULL,'2025-02-26 22:24:34',NULL,NULL,56,22,0.348963,0.462988,NULL,NULL,NULL,'2025-03-22 17:10:28',NULL,NULL,22,'2025-02-26 21:24:34',NULL,NULL,1,NULL),
(248,12,107,22,56,'Site','Test site ','accepted',NULL,NULL,'Ok','medium','0000-00-00','0000-00-00 00:00:00','2025-03-22 16:11:07','pending',NULL,'2025-02-28 10:10:24',NULL,NULL,56,22,0.502276,0.362642,NULL,NULL,NULL,'2025-03-22 17:11:07',NULL,NULL,22,'2025-02-28 09:10:24',NULL,NULL,1,NULL),
(249,12,104,22,52,'yy','Defect Description test 1','open',NULL,NULL,NULL,'high','0000-00-00','0000-00-00 00:00:00','2025-03-22 16:11:00','pending',NULL,'2025-03-22 17:11:00',NULL,NULL,52,22,0.727696,0.378316,NULL,NULL,NULL,'2025-03-22 17:11:00',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(251,11,104,22,31,'TEST- DRUNK ','HMMMM','accepted',NULL,NULL,'OK IM DUNK','low','0000-00-00','2025-03-03 18:53:34','2025-03-22 16:10:55','pending',NULL,'2025-03-03 20:59:05',NULL,NULL,31,22,0.195985,0.448778,NULL,NULL,NULL,'2025-03-22 17:10:55',NULL,NULL,22,'2025-03-03 19:59:05',NULL,NULL,1,NULL),
(252,13,106,22,29,'Jhhh','Uuuu','open',NULL,NULL,NULL,'medium','0000-00-00','2025-03-04 14:45:44','2025-03-22 16:10:49','pending',NULL,'2025-03-22 17:10:49',NULL,NULL,29,22,0.254909,0.430474,NULL,NULL,NULL,'2025-03-22 17:10:49',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(253,12,104,22,56,'mmm','mmm','open',NULL,NULL,NULL,'low','0000-00-00','2025-03-05 06:19:50','2025-03-22 16:10:43','pending',NULL,'2025-03-22 17:10:43',NULL,NULL,56,22,0.148563,0.322554,NULL,NULL,NULL,'2025-03-22 17:10:43',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(254,11,108,22,55,'nn','nn','open',NULL,NULL,'ok','high','0000-00-00','2025-03-05 06:21:16','2025-03-22 16:10:37','pending',NULL,'2025-03-05 11:49:18',NULL,22,55,22,0.438726,0.448414,NULL,NULL,NULL,'2025-03-22 17:10:37',NULL,NULL,22,'2025-03-05 10:49:18',NULL,NULL,1,NULL),
(256,12,105,22,56,'jjjjjjjjjjjjjjjj','jjj','open',NULL,NULL,NULL,'low','0000-00-00','2025-03-07 08:54:27','2025-03-22 16:09:22','pending',NULL,'2025-03-22 17:09:22',NULL,NULL,56,22,0.290418,0.431646,NULL,NULL,NULL,'2025-03-22 17:09:22',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(257,12,112,22,54,'','Uuuu','open',NULL,NULL,NULL,'low','0000-00-00','2025-03-10 20:34:43','2025-03-22 16:10:18','pending',NULL,'2025-03-22 17:10:18',NULL,NULL,54,22,0.258264,0.493041,NULL,NULL,NULL,'2025-03-22 17:10:18',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(258,12,112,22,54,'','Uuuu','open',NULL,NULL,NULL,'low','0000-00-00','2025-03-10 20:34:45','2025-03-22 16:10:11','pending',NULL,'2025-03-22 17:10:11',NULL,NULL,54,22,0.258264,0.493041,NULL,NULL,NULL,'2025-03-22 17:10:11',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(259,12,109,22,54,'nnn','nn\r\njjj','open',NULL,NULL,NULL,'critical','0000-00-00','2025-03-10 20:38:22','2025-03-22 16:10:06','pending',NULL,'2025-03-22 17:10:06',NULL,NULL,54,22,0.501476,0.518978,NULL,NULL,NULL,'2025-03-22 17:10:06',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(260,12,109,22,54,'nnn','nn\r\njjj','open',NULL,NULL,NULL,'critical','0000-00-00','2025-03-10 20:38:23','2025-03-22 16:09:45','pending',NULL,'2025-03-22 17:09:45',NULL,NULL,54,22,0.501476,0.518978,NULL,NULL,NULL,'2025-03-22 17:09:45',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(261,12,106,22,54,'tes1','nn\r\njjj','open',NULL,NULL,NULL,'high','0000-00-00','2025-03-11 06:37:11','2025-03-22 16:10:00','pending',NULL,'2025-03-22 17:10:00',NULL,NULL,54,22,0.498428,0.507595,NULL,NULL,NULL,'2025-03-22 17:10:00',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(262,12,106,22,54,'tes1','nn\r\njjj','open',NULL,NULL,NULL,'high','0000-00-00','2025-03-11 06:37:13','2025-03-22 16:09:55','pending',NULL,'2025-03-22 17:09:55',NULL,NULL,54,22,0.498428,0.507595,NULL,NULL,NULL,'2025-03-22 17:09:55',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(263,13,106,22,56,'test2','test2','open',NULL,NULL,NULL,'low','0000-00-00','2025-03-11 06:43:30','2025-03-22 15:57:36','pending',NULL,'2025-03-22 16:57:36',NULL,NULL,56,22,0.330975,0.456962,NULL,NULL,NULL,'2025-03-22 16:57:36',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(264,13,106,22,56,'test2','test2','open',NULL,NULL,NULL,'low','0000-00-00','2025-03-11 06:43:32','2025-03-22 15:57:30','pending',NULL,'2025-03-22 16:57:30',NULL,NULL,56,22,0.330975,0.456962,NULL,NULL,NULL,'2025-03-22 16:57:30',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(265,12,104,22,52,'test5','test2','open',NULL,NULL,NULL,'high','0000-00-00','2025-03-11 19:04:29','2025-03-22 15:56:40','pending',NULL,'2025-03-22 16:56:40',NULL,NULL,52,22,0.478198,0.41838,NULL,NULL,NULL,'2025-03-22 16:56:40',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(266,12,104,22,52,'test5','test2','open',NULL,NULL,NULL,'high','0000-00-00','2025-03-11 19:04:31','2025-03-22 15:56:28','pending',NULL,'2025-03-22 16:56:28',NULL,NULL,52,22,0.478198,0.41838,NULL,NULL,NULL,'2025-03-22 16:56:28',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(267,12,104,22,31,'test6','test2','open',NULL,NULL,NULL,'medium','0000-00-00','2025-03-11 19:16:47','2025-03-22 15:56:21','pending',NULL,'2025-03-22 16:56:21',NULL,NULL,31,22,0.473969,0.61838,NULL,NULL,NULL,'2025-03-22 16:56:21',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(268,12,104,22,31,'test6','test2','open',NULL,NULL,NULL,'medium','0000-00-00','2025-03-11 19:16:49','2025-03-22 15:56:13','pending',NULL,'2025-03-22 16:56:13',NULL,NULL,31,22,0.473969,0.61838,NULL,NULL,NULL,'2025-03-22 16:56:13',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(269,12,104,22,56,'trrr','rrr','open',NULL,NULL,NULL,'high','0000-00-00','2025-03-11 19:27:39','2025-03-22 15:57:20','pending',NULL,'2025-03-22 16:57:20',NULL,NULL,56,22,0.610333,0.62259,NULL,NULL,NULL,'2025-03-22 16:57:20',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(270,12,112,22,52,'kkk','kkk','open',NULL,NULL,NULL,'low','0000-00-00','2025-03-11 19:29:42','2025-03-22 15:55:58','pending',NULL,'2025-03-22 16:55:58',NULL,NULL,52,22,0.500379,0.427155,NULL,NULL,NULL,'2025-03-22 16:55:58',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(271,13,110,22,54,'kkk','kkkk','open',NULL,NULL,NULL,'high','0000-00-00','2025-03-11 19:36:28','2025-03-22 15:57:14','pending',NULL,'2025-03-22 16:57:14',NULL,NULL,54,22,0.526668,0.364469,NULL,NULL,NULL,'2025-03-22 16:57:14',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(272,13,112,22,55,'kkkk','kkkk','open',NULL,NULL,NULL,'medium','0000-00-00','2025-03-11 19:37:13','2025-03-22 15:48:50','pending',NULL,'2025-03-22 16:48:50',NULL,NULL,55,22,0.558013,0.447111,NULL,NULL,NULL,'2025-03-22 16:48:50',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(273,12,106,22,56,'jnjn','jj','open',NULL,NULL,NULL,'low','0000-00-00','2025-03-11 19:37:46','2025-03-22 15:49:15','pending',NULL,'2025-03-22 16:49:15',NULL,NULL,56,22,0.540824,0.441931,NULL,NULL,NULL,'2025-03-22 16:49:15',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(274,12,104,22,29,'lll','lll','open',NULL,NULL,NULL,'low','0000-00-00','2025-03-11 19:44:56','2025-03-22 15:49:09','pending',NULL,'2025-03-22 16:49:09',NULL,NULL,29,22,0.525766,0.473117,NULL,NULL,NULL,'2025-03-22 16:49:09',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(275,12,108,22,56,'kkk','kkk','open',NULL,NULL,NULL,'low','0000-00-00','2025-03-11 19:51:43','2025-03-22 15:55:51','pending',NULL,'2025-03-22 16:55:51',NULL,NULL,56,22,0.504424,0.384031,NULL,NULL,NULL,'2025-03-22 16:55:51',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(276,12,105,22,56,'oooo','ooo','open',NULL,NULL,NULL,'low','0000-00-00','2025-03-11 19:56:05','2025-03-22 15:49:01','pending',NULL,'2025-03-22 16:49:01',NULL,NULL,56,22,0.495285,0.443524,NULL,NULL,NULL,'2025-03-22 16:49:01',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(277,12,104,22,29,'hhh','hh','open',NULL,NULL,NULL,'medium','0000-00-00','2025-03-11 19:58:45','2025-03-22 15:48:21','pending',NULL,'2025-03-22 16:48:21',NULL,NULL,29,22,0.589192,0.50259,NULL,NULL,NULL,'2025-03-22 16:48:21',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(278,12,104,22,56,'mmm','mmm','open',NULL,NULL,NULL,'low','0000-00-00','2025-03-11 20:02:35','2025-03-22 15:47:07','pending',NULL,'2025-03-22 16:47:07',NULL,NULL,56,22,0.541623,0.515222,NULL,NULL,NULL,'2025-03-22 16:47:07',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(279,12,104,22,56,'fff','fff','open',NULL,NULL,NULL,'low','0000-00-00','2025-03-11 20:03:34','2025-03-22 15:48:07','pending',NULL,'2025-03-22 16:47:31',NULL,22,56,22,0.312235,0.504169,NULL,NULL,NULL,'2025-03-22 16:48:07',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(280,12,104,22,56,'ddd','ddd','open',NULL,NULL,NULL,'low','0000-00-00','2025-03-11 20:04:18','2025-03-22 15:55:43','pending',NULL,'2025-03-22 16:55:43',NULL,NULL,56,22,0.169528,0.588906,NULL,NULL,NULL,'2025-03-22 16:55:43',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(281,12,108,22,56,'fff','fff','open',NULL,NULL,NULL,'low','0000-00-00','2025-03-11 20:07:41','2025-03-22 13:35:04','pending',NULL,'2025-03-22 14:35:04',NULL,NULL,56,22,0.491279,0.498197,NULL,NULL,NULL,'2025-03-22 14:35:04',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(282,12,105,22,56,'ggg','ggg','open',NULL,NULL,NULL,'low','0000-00-00','2025-03-11 20:08:14','2025-03-22 15:56:48','pending',NULL,'2025-03-22 16:56:48',NULL,NULL,56,22,0.55237,0.42647,NULL,NULL,NULL,'2025-03-22 16:56:48',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(283,12,112,22,56,'hhh','hhh','open',NULL,NULL,NULL,'low','0000-00-00','2025-03-11 20:10:21','2025-03-22 13:45:58','pending',NULL,'2025-03-22 14:45:58',NULL,NULL,56,22,0.50139,0.48037,NULL,NULL,NULL,'2025-03-22 14:45:58',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(284,12,104,22,56,'hhh','hh','open',NULL,NULL,NULL,'low','0000-00-00','2025-03-11 20:11:19','2025-03-22 13:35:35','pending',NULL,'2025-03-22 14:35:19',NULL,22,56,22,0.520481,0.471012,NULL,NULL,NULL,'2025-03-22 14:35:35',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(285,12,108,22,56,'ddd','ddd','open',NULL,NULL,NULL,'low','0000-00-00','2025-03-11 20:22:59','2025-03-22 13:33:33','pending',NULL,'2025-03-22 14:33:33',NULL,NULL,56,22,0.484201,0.502425,NULL,NULL,NULL,'2025-03-22 14:33:33',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(286,12,104,22,56,'vvvv','vvv','open',NULL,NULL,NULL,'low','0000-00-00','2025-03-11 20:23:38','2025-03-22 13:32:41','pending',NULL,'2025-03-22 14:32:41',NULL,NULL,56,22,0.890461,0.645748,NULL,NULL,NULL,'2025-03-22 14:32:41',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(287,12,106,22,56,'hhh','hhh','open',NULL,NULL,NULL,'low','0000-00-00','2025-03-11 20:30:45','2025-03-22 13:32:52','pending',NULL,'2025-03-22 14:32:52',NULL,NULL,56,22,0.498357,0.519842,NULL,NULL,NULL,'2025-03-22 14:32:52',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(288,12,105,22,56,'hhh','hhh','open',NULL,NULL,NULL,'high','0000-00-00','2025-03-11 20:31:30','2025-03-22 15:48:39','pending',NULL,'2025-03-22 16:48:33',NULL,22,56,22,0.862259,0.494157,NULL,NULL,NULL,'2025-03-22 16:48:39',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(289,12,107,22,56,'hhh','hhh','open',NULL,NULL,NULL,'low','0000-00-00','2025-03-11 20:33:36','2025-03-22 13:32:26','pending',NULL,'2025-03-22 14:16:33',NULL,22,56,22,0.543858,0.559534,NULL,NULL,NULL,'2025-03-22 14:32:26',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(290,12,105,22,29,'hhh','hhh','open',NULL,NULL,NULL,'low','0000-00-00','2025-03-11 20:44:14','2025-03-22 13:16:13','pending',NULL,'2025-03-22 14:16:13',NULL,NULL,29,22,0.53708,0.521583,NULL,NULL,NULL,'2025-03-22 14:16:13',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(291,12,106,22,29,'hhhhh','hhhhh','open',NULL,NULL,NULL,'medium','0000-00-00','2025-03-11 20:44:43','2025-03-22 13:16:04','pending',NULL,'2025-03-22 14:16:04',NULL,NULL,29,22,0.595425,0.502488,NULL,NULL,NULL,'2025-03-22 14:16:04',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(292,12,105,22,56,'ggg','ggg','open',NULL,NULL,NULL,'medium','0000-00-00','2025-03-11 20:53:59','2025-03-22 13:15:56','pending',NULL,'2025-03-22 14:15:27',NULL,22,56,22,0.823523,0.559558,NULL,NULL,NULL,'2025-03-22 14:15:56',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(293,12,105,22,56,'ggggg','ggggg','open',NULL,NULL,NULL,'medium','0000-00-00','2025-03-11 20:54:33','2025-03-22 13:15:21','pending',NULL,'2025-03-22 14:15:21',NULL,NULL,56,22,0.903034,0.462511,NULL,NULL,NULL,'2025-03-22 14:15:21',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(294,12,104,22,29,'ssssss','ssssss','open',NULL,NULL,NULL,'medium','0000-00-00','2025-03-11 21:00:38','2025-03-22 13:15:51','pending',NULL,'2025-03-22 14:15:51',NULL,NULL,29,22,0.0923609,0.681538,NULL,NULL,NULL,'2025-03-22 14:15:51',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(295,12,105,22,56,'ss','ss','open',NULL,NULL,NULL,'low','0000-00-00','2025-03-11 21:01:06','2025-03-22 13:15:14','pending',NULL,'2025-03-22 14:15:14',NULL,NULL,56,22,0.673676,0.479389,NULL,NULL,NULL,'2025-03-22 14:15:14',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(296,12,109,22,56,'bb','bb','open',NULL,NULL,NULL,'low','0000-00-00','2025-03-11 21:04:04','2025-03-22 13:14:58','pending',NULL,'2025-03-22 14:14:58',NULL,NULL,56,22,0.540824,0.374768,NULL,NULL,NULL,'2025-03-22 14:14:58',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(297,12,104,22,56,'ooooooooooooooooo','oooooooooooooooooooooo','open',NULL,NULL,NULL,'high','0000-00-00','2025-03-12 06:33:40','2025-03-22 13:46:03','pending',NULL,'2025-03-22 14:32:59',NULL,22,56,22,0.46423,0.495294,NULL,NULL,NULL,'2025-03-22 14:46:03',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(298,13,106,22,55,'jjjjjjjjjjjjjjjjjjjjjjjjj','jjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjjj','open',NULL,NULL,NULL,'high','0000-00-00','2025-03-12 06:35:06','2025-03-22 13:15:06','pending',NULL,'2025-03-22 14:15:06',NULL,NULL,55,22,0.693003,0.464557,NULL,NULL,NULL,'2025-03-22 14:15:06',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(299,12,104,22,56,'nnn','nnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnn','open',NULL,NULL,NULL,'high','0000-00-00','2025-03-13 08:54:24','2025-03-22 15:47:17','pending',NULL,'2025-03-22 16:47:17',NULL,NULL,56,22,0.362814,0.492941,NULL,NULL,NULL,'2025-03-22 16:47:17',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(300,12,104,22,56,'mmmmmmmmmmmmmmmmmmmmmmm','mmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmmm','open',NULL,NULL,NULL,'high','0000-00-00','2025-03-13 08:59:58','2025-03-22 13:14:17','pending',NULL,'2025-03-22 14:14:17',NULL,NULL,56,22,0.895833,0.245882,NULL,NULL,NULL,'2025-03-22 14:14:17',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(301,13,104,22,29,'mmmmmmmmmmmmmmmmmmm','mmmmmmmmmmmmmmmmmmm','open',NULL,NULL,NULL,'high',NULL,'2025-03-13 09:21:56','2025-03-22 13:35:30','pending',NULL,'2025-03-22 14:33:48',NULL,22,29,22,0.827437,0.671765,NULL,NULL,NULL,'2025-03-22 14:35:30',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(302,12,105,22,55,'bbbbbbbbbbbbbb','bbbbbbbbbbbbbbbbbbbbbbb','open',NULL,NULL,NULL,'high',NULL,'2025-03-13 11:28:23','2025-03-22 15:57:04','pending',NULL,'2025-03-22 16:56:58',NULL,22,55,22,0.649371,0.580488,NULL,NULL,NULL,'2025-03-22 16:57:04',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(303,12,104,22,56,'bbbbbbbbbbbbbbb','bbbbbbbbbbbbbbbbb','open',NULL,NULL,NULL,'high','2025-03-15','2025-03-13 14:30:54','2025-03-13 15:30:54','synced',NULL,NULL,NULL,NULL,56,22,0.906447,0.681176,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(304,12,105,22,56,'bbbbbbbbbbbbbbbbb','bbbbbbbbbbbbbbbbbbbbb','open',NULL,NULL,'all ok','medium','2025-03-18','2025-03-13 19:18:59','2025-03-13 20:27:07','pending',NULL,'2025-03-13 21:19:31',NULL,NULL,56,22,0.663482,0.601753,NULL,NULL,NULL,NULL,NULL,'not ok',22,'2025-03-13 20:19:31','2025-03-13 20:27:07',22,1,NULL),
(305,12,112,22,56,'','bbbbbbbbbbbbbbbbbbbbb','open',NULL,NULL,NULL,'low','2025-03-23','2025-03-16 16:26:21','2025-03-16 17:26:21','synced',NULL,NULL,NULL,NULL,56,22,0.452484,0.39216,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(306,12,113,22,56,'','bbbbbbbbbbbbbbbbbbbbbbb','accepted',NULL,NULL,'All ok','medium','2025-03-21','2025-03-16 17:54:32','2025-03-22 13:13:55','pending',NULL,'2025-03-17 11:38:43',NULL,NULL,56,22,0.54179,0.68925,NULL,NULL,NULL,'2025-03-22 14:13:55',NULL,NULL,22,'2025-03-17 10:38:43',NULL,NULL,1,NULL),
(308,12,105,22,53,'Block 1 flooring not good ','Floor needs attention ','accepted',NULL,NULL,'Akk ok','low','2025-03-24','2025-03-17 09:39:58','2025-03-17 10:40:25','pending',NULL,'2025-03-17 11:40:25',NULL,NULL,53,22,0.243596,0.480304,NULL,NULL,NULL,NULL,NULL,NULL,22,'2025-03-17 10:40:25',NULL,NULL,1,NULL),
(309,12,104,22,30,'test7','Floor needs attention ','open',NULL,NULL,NULL,'high','2025-03-20','2025-03-18 19:07:12','2025-03-22 13:11:51','pending',NULL,'2025-03-22 14:11:51',NULL,NULL,30,22,0.528938,0.696768,NULL,NULL,NULL,'2025-03-22 14:11:51',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(310,12,104,22,55,'test99','Floor needs attention 3','open',NULL,NULL,NULL,'high','2025-03-20','2025-03-18 20:46:27','2025-03-19 15:30:05','pending',NULL,'2025-03-19 17:30:05',NULL,22,55,22,0.877776,0.467294,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(311,12,108,22,54,'this is  long test title text to see how it is displayed','It is a long established fact that a reader will be distracted by the readable content of a page when looking at its layout. The point of using Lorem Ipsum is that it has a more-or-less normal distribution of letters, as opposed to using &#039;Content here, content here&#039;, making it look like readable English. Many desktop publishing packages and web page editors now use Lorem Ipsum as their default model text, and a search for &#039;lorem ipsum&#039; will uncover many web sites still in their infancy. Various versions have evolved over the years, sometimes by accident, sometimes on purpose (injected humour and the like).','accepted',NULL,NULL,'all ok','high','2025-03-21','2025-03-19 19:34:50','2025-03-22 13:11:23','pending',NULL,'2025-03-19 21:35:57',NULL,NULL,54,22,0.258264,0.643535,NULL,NULL,NULL,'2025-03-22 14:11:23',NULL,NULL,22,'2025-03-19 20:35:57',NULL,NULL,1,NULL),
(312,13,112,22,53,'another title test','It is a long established fact that a reader will be distracted by the readable content of a page when looking at its layout. The point of using Lorem Ipsum is that it has a more-or-less normal distribution of letters, as opposed to using &#039;Content here, content here&#039;, making it look like readable English. Many desktop publishing packages and web page editors now use Lorem Ipsum as their default model text, and a search for &#039;lorem ipsum&#039; will uncover many web sites still in their infancy. Various versions have evolved over the years, sometimes by accident, sometimes on purpose (injected humour and the like).','accepted',NULL,'kkk','kkk','high','2025-03-21','2025-03-19 20:37:22','2025-03-22 13:32:33','pending',NULL,'2025-03-20 08:27:26',NULL,NULL,53,22,0.801358,0.541792,NULL,NULL,NULL,'2025-03-22 14:32:33',NULL,'kkk',22,'2025-03-20 07:43:41','2025-03-20 07:43:33',22,1,22),
(313,14,113,22,52,'drunk again :-)','It is a long established fact that a reader will be distracted by the readable content of a page when looking at its layout. The point of using Lorem Ipsum is that it has a more-or-less normal distribution of letters, as opposed to using &#039;Content here, content here&#039;, making it look like readable English. Many desktop publishing packages and web page editors now use Lorem Ipsum as their default model text, and a search for &#039;lorem ipsum&#039; will uncover many web sites still in their infancy. Various versions have evolved over the years, sometimes by accident, sometimes on purpose (injected humour and the like).','','uploads/defect_images/defect_67db3ca496d41_20250319_215236.png',NULL,NULL,'low','2025-03-26','2025-03-19 20:49:13','2025-03-22 13:11:35','pending',NULL,'2025-03-19 22:52:36',NULL,22,52,22,0.179161,0.386512,NULL,NULL,NULL,'2025-03-22 14:11:35',NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(314,12,104,22,52,'new test','It is a long established fact that a reader will be distracted by the readable content of a page when looking at its layout. The point of using Lorem Ipsum is that it has a more-or-less normal distribution of letters, as opposed to using &#039;Content here, content here&#039;, making it look like readable English. Many desktop publishing packages and web page editors now use Lorem Ipsum as their default model text, and a search for &#039;lorem ipsum&#039; will uncover many web sites still in their infancy. Various versions have evolved over the years, sometimes by accident, sometimes on purpose (injected humour and the like).','open',NULL,NULL,NULL,'low','2025-03-27','2025-03-20 14:16:57','2025-03-20 15:16:57','synced',NULL,NULL,NULL,NULL,52,22,0.830508,0.489302,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(315,12,104,22,53,'test99','It is a long established fact that a reader will be distracted by the readable content of a page when looking at its layout. The point of using Lorem Ipsum is that it has a more-or-less normal distribution of letters, as opposed to using &#039;Content here, content here&#039;, making it look like readable English. Many desktop publishing packages and web page editors now use Lorem Ipsum as their default model text, and a search for &#039;lorem ipsum&#039; will uncover many web sites still in their infancy. Various versions have evolved over the years, sometimes by accident, sometimes on purpose (injected humour and the like).','open',NULL,NULL,NULL,'low','2025-03-27','2025-03-20 14:21:44','2025-03-22 12:33:17','pending',NULL,'2025-03-22 14:33:17',NULL,22,53,22,0.930837,0.278459,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(316,12,105,22,53,'kkkkkkkkkkkkkkkkkkkkkkkkkkkkk','It is a long established fact that a reader will be distracted by the readable content of a page when looking at its layout. The point of using Lorem Ipsum is that it has a more-or-less normal distribution of letters, as opposed to using &#039;Content here, content here&#039;, making it look like readable English. Many desktop publishing packages and web page editors now use Lorem Ipsum as their default model text, and a search for &#039;lorem ipsum&#039; will uncover many web sites still in their infancy. Various versions have evolved over the years, sometimes by accident, sometimes on purpose (injected humour and the like).','open',NULL,NULL,NULL,'low','2025-03-27','2025-03-20 14:43:52','2025-03-20 15:43:52','synced',NULL,NULL,NULL,NULL,53,22,0.742969,0.422495,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(317,12,107,22,55,'4444','It is a long established fact that a reader will be distracted by the readable content of a page when looking at its layout. The point of using Lorem Ipsum is that it has a more-or-less normal distribution of letters, as opposed to using &#039;Content here, content here&#039;, making it look like readable English. Many desktop publishing packages and web page editors now use Lorem Ipsum as their default model text, and a search for &#039;lorem ipsum&#039; will uncover many web sites still in their infancy. Various versions have evolved over the years, sometimes by accident, sometimes on purpose (injected humour and the like).','open',NULL,NULL,NULL,'medium','2025-03-26','2025-03-21 13:20:16','2025-03-21 14:20:16','synced',NULL,NULL,NULL,NULL,55,22,0.778538,0.370957,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(318,12,104,22,31,'defect1','It is a long established fact that a reader will be distracted by the readable content of a page when looking at its layout. The point of using Lorem Ipsum is that it has a more-or-less normal distribution of letters, as opposed to using &#039;Content here, content here&#039;, making it look like readable English. Many desktop publishing packages and web page editors now use Lorem Ipsum as their default model text, and a search for &#039;lorem ipsum&#039; will uncover many web sites still in their infancy. Various versions have evolved over the years, sometimes by accident, sometimes on purpose (injected humour and the like).','open',NULL,NULL,NULL,'low','2025-03-29','2025-03-22 12:49:03','2025-03-22 13:49:03','synced',NULL,NULL,NULL,NULL,31,22,0.432302,0.699976,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,NULL),
(319,12,104,22,55,'Test tv','Edencroft issue ','rejected',NULL,'Absolutely rubbish',NULL,'low','2025-03-29','2025-03-22 17:17:52','2025-03-22 17:19:18','pending',NULL,'2025-03-22 19:19:18',NULL,NULL,55,22,0.818677,0.656969,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,22),
(320,12,104,22,53,'Lanzarote ','It is a long established fact that a reader will be distracted by the readable content of a page when looking at its layout. The point of using Lorem Ipsum is that it has a more-or-less normal distribution of letters, as opposed to using &#039;Content here, content here&#039;, making it look like readable English. Many desktop publishing packages and web page editors now use Lorem Ipsum as their default model text, and a search for &#039;lorem ipsum&#039; will uncover many web sites still in their infancy. Various versions have evolved over the years, sometimes by accident, sometimes on purpose (injected humour and the like).','open',NULL,NULL,'Happy days','','2025-04-30','2025-03-24 13:36:39','2025-03-28 15:21:40','pending',NULL,'2025-03-24 15:37:11',NULL,22,53,22,0.731599,0.531551,NULL,NULL,NULL,'2025-03-28 16:21:40',NULL,'kk',22,'2025-03-24 14:38:55','2025-03-28 15:20:29',22,1,NULL),
(321,12,104,22,53,'Lanzarote test ','Lanzarote ','accepted',NULL,NULL,'all ok','low','2025-03-31','2025-03-24 14:13:02','2025-03-28 14:53:43','pending',NULL,'2025-03-28 15:53:43',NULL,NULL,53,22,0.580928,0.365855,NULL,NULL,NULL,NULL,NULL,NULL,22,'2025-03-28 14:53:43',NULL,NULL,1,NULL),
(322,12,104,22,31,'app 404','It is a long established fact that a reader will be distracted by the readable content of a page when looking at its layout. The point of using Lorem Ipsum is that it has a more-or-less normal distribution of letters, as opposed to using &#039;Content here, content here&#039;, making it look like readable English. Many desktop publishing packages and web page editors now use Lorem Ipsum as their default model text, and a search for &#039;lorem ipsum&#039; will uncover many web sites still in their infancy. Various versions have evolved over the years, sometimes by accident, sometimes on purpose (injected humour and the like).','accepted',NULL,NULL,'all ok','medium','2025-04-02','2025-03-28 14:22:47','2025-03-28 15:23:04','pending',NULL,'2025-03-28 16:23:04',NULL,NULL,31,22,0.0912623,0.701375,NULL,NULL,NULL,NULL,NULL,NULL,22,'2025-03-28 15:23:04',NULL,NULL,1,NULL);
/*!40000 ALTER TABLE `defects` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'IGNORE_SPACE,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`k87747_defecttracker`@`%`*/ /*!50003 TRIGGER `defects_before_update` BEFORE UPDATE ON `defects` FOR EACH ROW BEGIN
                    -- Only mark as pending if this is a direct update, not from the sync system
                    IF NEW.sync_status = 'synced' AND OLD.updated_at != NEW.updated_at THEN
                        SET NEW.sync_status = 'pending';
                        SET NEW.sync_timestamp = NOW();
                    END IF;
                END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `export_logs`
--

DROP TABLE IF EXISTS `export_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `export_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL COMMENT 'References users.id',
  `export_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Type of export (e.g., dashboard, defects, contractors)',
  `file_format` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Format of the export (csv, excel, pdf)',
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Name of the exported file',
  `filesize` bigint unsigned NOT NULL COMMENT 'Size of the exported file in bytes',
  `created_at` datetime NOT NULL COMMENT 'UTC timestamp of export creation',
  `downloaded_at` datetime DEFAULT NULL COMMENT 'UTC timestamp of first download',
  `download_count` int unsigned DEFAULT '0' COMMENT 'Number of times downloaded',
  `status` enum('pending','completed','failed','expired') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `expiry_date` datetime NOT NULL COMMENT 'When the export file should be deleted',
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'IP address of the user who initiated the export',
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'User agent of the browser used for export',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_status_expiry` (`status`,`expiry_date`),
  KEY `idx_filename` (`filename`),
  CONSTRAINT `fk_export_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `export_logs`
--

LOCK TABLES `export_logs` WRITE;
/*!40000 ALTER TABLE `export_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `export_logs` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`k87747_defecttracker`@`%`*/ /*!50003 TRIGGER `tr_export_logs_before_insert` BEFORE INSERT ON `export_logs` FOR EACH ROW BEGIN
    SET NEW.expiry_date = DATE_ADD(NEW.created_at, INTERVAL 30 DAY);
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `floor_plans`
--

DROP TABLE IF EXISTS `floor_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `floor_plans` (
  `id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `floor_name` varchar(255) NOT NULL COMMENT 'Primary display name of the floor plan',
  `level` varchar(50) NOT NULL DEFAULT 'Level?' COMMENT 'Floor level designation (e.g., Ground Floor, Level 1)',
  `file_path` varchar(255) NOT NULL COMMENT 'Server path to the stored floor plan file',
  `image_path` varchar(255) DEFAULT NULL COMMENT 'Path to the image version if different from original file',
  `floor_number` int DEFAULT NULL COMMENT 'Numeric floor level for sorting (-1 for basement, 0 for ground, 1 for first, etc)',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Timestamp when record was created',
  `upload_date` datetime DEFAULT CURRENT_TIMESTAMP COMMENT 'Date and time when file was uploaded',
  `uploaded_by` int NOT NULL COMMENT 'User ID who uploaded the file',
  `file_size` int unsigned DEFAULT NULL COMMENT 'Size of the file in bytes',
  `file_type` varchar(50) DEFAULT NULL COMMENT 'MIME type of the file',
  `description` text COMMENT 'Detailed description of the floor plan',
  `version` int DEFAULT '1' COMMENT 'Version number of the floor plan',
  `status` enum('active','inactive','deleted') NOT NULL DEFAULT 'active' COMMENT 'Current status of the floor plan',
  `last_modified` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last modification timestamp',
  `thumbnail_path` varchar(255) DEFAULT NULL COMMENT 'Path to thumbnail version of the floor plan',
  `original_filename` varchar(255) NOT NULL COMMENT 'Original name of the uploaded file',
  `created_by` int DEFAULT NULL COMMENT 'User ID who created the record (defaults to uploaded_by if not specified)',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_upload_date` (`upload_date`),
  KEY `idx_uploaded_by` (`uploaded_by`),
  KEY `idx_status` (`status`),
  KEY `idx_floor_plans_project` (`project_id`),
  KEY `idx_floor_plans_created_at` (`created_at`),
  KEY `idx_floor_plans_floor_name` (`floor_name`),
  KEY `idx_floor_plans_original_filename` (`original_filename`),
  KEY `idx_floor_plans_level` (`level`),
  KEY `idx_floor_plans_floor_number` (`floor_number`),
  KEY `idx_floor_plans_version` (`version`),
  KEY `idx_floor_plans_last_modified` (`last_modified`),
  KEY `fk_floor_plans_created_by` (`created_by`),
  CONSTRAINT `fk_floor_plans_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_floor_plans_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_floor_plans_uploaded_by` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=114 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `floor_plans`
--

LOCK TABLES `floor_plans` WRITE;
/*!40000 ALTER TABLE `floor_plans` DISABLE KEYS */;
INSERT INTO `floor_plans` VALUES
(104,12,'B1 - Level 1','1','uploads/floor_plans/block-1/2025/02/block-1_b1-level-1_20250209_133201.pdf','uploads/floor_plan_images/block-1_b1-level-1_20250209_133201.png',NULL,'2025-02-09 13:32:07','2025-02-09 13:32:07',22,768,'application/pdf',NULL,1,'active','2025-02-09 13:32:07',NULL,'B1 - 1st Floor - GA Plan_C02.pdf',22,'2025-02-09 13:32:07',NULL),
(105,12,'B1 - Level 2','2','uploads/floor_plans/block-1/2025/02/block-1_b1-level-2_20250219_223853.pdf','uploads/floor_plan_images/block-1_b1-level-2_20250219_223853.png',NULL,'2025-02-19 22:38:59','2025-02-19 23:38:59',22,768,'application/pdf',NULL,1,'active','2025-02-19 23:38:59',NULL,'B1 - 2nd Floor - GA Plan_C02.pdf',22,'2025-02-19 22:38:59',NULL),
(106,12,'B1 - Level 3','3','uploads/floor_plans/block-1/2025/02/block-1_b1-level-3_20250219_223926.pdf','uploads/floor_plan_images/block-1_b1-level-3_20250219_223926.png',NULL,'2025-02-19 22:39:32','2025-02-19 23:39:32',22,768,'application/pdf',NULL,1,'active','2025-02-19 23:39:32',NULL,'B1 - 3rd Floor - GA Plan_C02.pdf',22,'2025-02-19 22:39:32',NULL),
(107,12,'B1 - Level 4','4','uploads/floor_plans/block-1/2025/02/block-1_b1-level-4_20250219_223949.pdf','uploads/floor_plan_images/block-1_b1-level-4_20250219_223949.png',NULL,'2025-02-19 22:39:54','2025-02-19 23:39:54',22,767,'application/pdf',NULL,1,'active','2025-02-19 23:39:54',NULL,'B1 - 4th Floor - GA Plan_C02.pdf',22,'2025-02-19 22:39:54',NULL),
(108,12,'B1 - Level 5','5','uploads/floor_plans/block-1/2025/02/block-1_b1-level-5_20250219_224010.pdf','uploads/floor_plan_images/block-1_b1-level-5_20250219_224010.png',NULL,'2025-02-19 22:40:16','2025-02-19 23:40:16',22,767,'application/pdf',NULL,1,'active','2025-02-19 23:40:16',NULL,'B1 - 5th Floor - GA Plan_C02.pdf',22,'2025-02-19 22:40:16',NULL),
(109,12,'B1 - Level 6','6','uploads/floor_plans/block-1/2025/02/block-1_b1-level-6_20250219_224033.pdf','uploads/floor_plan_images/block-1_b1-level-6_20250219_224033.png',NULL,'2025-02-19 22:40:38','2025-02-19 23:40:38',22,769,'application/pdf',NULL,1,'active','2025-02-19 23:40:38',NULL,'B1 - 6th Floor - GA Plan_C02.pdf',22,'2025-02-19 22:40:38',NULL),
(110,12,'B1 - Level 7','7','uploads/floor_plans/block-1/2025/02/block-1_b1-level-7_20250219_224053.pdf','uploads/floor_plan_images/block-1_b1-level-7_20250219_224053.png',NULL,'2025-02-19 22:40:59','2025-02-19 23:40:59',22,771,'application/pdf',NULL,1,'active','2025-02-19 23:40:59',NULL,'B1 - 7th Floor - GA Plan_C02.pdf',22,'2025-02-19 22:40:59',NULL),
(111,12,'B1 - Level 8','8','uploads/floor_plans/block-1/2025/02/block-1_b1-level-8_20250219_224119.pdf','uploads/floor_plan_images/block-1_b1-level-8_20250219_224119.png',NULL,'2025-02-19 22:41:25','2025-02-19 23:41:25',22,770,'application/pdf',NULL,1,'active','2025-02-19 23:41:25',NULL,'B1 - 8th Floor - GA Plan_C02.pdf',22,'2025-02-19 22:41:25',NULL),
(112,12,'B1 - Level 10','10','uploads/floor_plans/block-1/2025/02/block-1_b1-level-10_20250219_224158.pdf','uploads/floor_plan_images/block-1_b1-level-10_20250219_224158.png',NULL,'2025-02-19 22:42:03','2025-02-19 23:42:03',22,421,'application/pdf',NULL,1,'active','2025-02-19 23:42:03',NULL,'B1 - 10th Floor - GA Plan_C02.pdf',22,'2025-02-19 22:42:03',NULL),
(113,12,'B1 - Basement','Basement','uploads/floor_plans/block-1/2025/02/block-1_b1-basement_20250219_224229.pdf','uploads/floor_plan_images/block-1_b1-basement_20250219_224229.png',NULL,'2025-02-19 22:42:33','2025-02-19 23:42:33',22,136,'application/pdf',NULL,1,'active','2025-02-19 23:42:33',NULL,'B1 - Basement - GA Plan_C01.pdf',22,'2025-02-19 22:42:33',NULL);
/*!40000 ALTER TABLE `floor_plans` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`k87747_defecttracker`@`%`*/ /*!50003 TRIGGER `before_floor_plans_insert` BEFORE INSERT ON `floor_plans` FOR EACH ROW BEGIN
    IF NEW.created_by IS NULL THEN
        SET NEW.created_by = NEW.uploaded_by;
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `maintenance_log`
--

DROP TABLE IF EXISTS `maintenance_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `maintenance_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `action` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `tables_affected` text COLLATE utf8mb4_general_ci,
  `user_id` int DEFAULT NULL,
  `username` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `execution_time` datetime NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'success',
  `details` text COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`id`),
  KEY `idx_execution_time` (`execution_time`),
  KEY `idx_action` (`action`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `maintenance_log`
--

LOCK TABLES `maintenance_log` WRITE;
/*!40000 ALTER TABLE `maintenance_log` DISABLE KEYS */;
INSERT INTO `maintenance_log` VALUES
(1,'backup',NULL,22,NULL,'162.158.216.178','2025-03-29 12:38:47','success','/var/www/vhosts/hosting215226.ae97b.netcup.net/mcgoff.defecttracker.uk/httpdocs/backups/backup_2025-03-29_12-38-47.sql'),
(2,'optimize','acceptance_history, action_log, activity_logs, audit_logs, categories, comments, company_settings, contractors, defect_assignments, defect_comments, defect_history, defect_images, defects, export_logs, floor_plans, maintenance_log, notification_log, notifications, permissions, projects, role_permissions, roles, sync_conflicts, sync_devices, sync_logs, sync_queue, sync_settings, system_logs, user_logs, user_permissions, user_recent_descriptions, user_roles, user_sessions, users',22,NULL,'172.71.241.34','2025-03-29 14:46:46','success',NULL),
(3,'repair','acceptance_history, action_log, activity_logs, audit_logs, categories, comments, company_settings, contractors, defect_assignments, defect_comments, defect_history, defect_images, defects, export_logs, floor_plans, maintenance_log, notification_log, notifications, permissions, projects, role_permissions, roles, sync_conflicts, sync_devices, sync_logs, sync_queue, sync_settings, system_logs, user_logs, user_permissions, user_recent_descriptions, user_roles, user_sessions, users',22,NULL,'172.71.241.34','2025-03-29 14:46:57','success',NULL),
(4,'optimize','acceptance_history, action_log, activity_logs, audit_logs, categories, comments, company_settings, contractors, defect_assignments, defect_comments, defect_history, defect_images, defects, export_logs, floor_plans, maintenance_log, notification_log, notifications, permissions, projects, role_permissions, roles, sync_conflicts, sync_devices, sync_logs, sync_queue, sync_settings, system_logs, user_logs, user_permissions, user_recent_descriptions, user_roles, user_sessions, users',22,NULL,'172.69.43.138','2025-03-29 14:55:30','success',NULL),
(5,'repair','acceptance_history, action_log, activity_logs, audit_logs, categories, comments, company_settings, contractors, defect_assignments, defect_comments, defect_history, defect_images, defects, export_logs, floor_plans, maintenance_log, notification_log, notifications, permissions, projects, role_permissions, roles, sync_conflicts, sync_devices, sync_logs, sync_queue, sync_settings, system_logs, user_logs, user_permissions, user_recent_descriptions, user_roles, user_sessions, users',22,NULL,'172.69.43.138','2025-03-29 14:55:47','success',NULL);
/*!40000 ALTER TABLE `maintenance_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notification_log`
--

DROP TABLE IF EXISTS `notification_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `notification_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `target_type` varchar(50) NOT NULL,
  `user_id` int DEFAULT NULL,
  `defect_id` int DEFAULT NULL,
  `success_count` int DEFAULT '0',
  `sent_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notification_log`
--

LOCK TABLES `notification_log` WRITE;
/*!40000 ALTER TABLE `notification_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `notification_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `type` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `message` text COLLATE utf8mb4_general_ci NOT NULL,
  `link_url` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `permission_name` varchar(100) DEFAULT NULL,
  `permission_key` varchar(100) DEFAULT NULL,
  `name` varchar(50) NOT NULL,
  `description` text,
  `created_by` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` varchar(50) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `uk_permission_key` (`permission_key`)
) ENGINE=InnoDB AUTO_INCREMENT=130 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` VALUES
(1,NULL,NULL,'manage_users','Permission to manage users','system','2025-01-24 15:04:05','2025-01-24 15:11:00',NULL,NULL,NULL),
(2,NULL,NULL,'view_reports','Permission to view reports','system','2025-01-24 15:04:05','2025-01-24 15:11:05',NULL,NULL,NULL),
(3,NULL,NULL,'edit_projects','Permission to edit projects','system','2025-01-24 15:04:56','2025-01-24 15:11:11',NULL,NULL,NULL),
(4,NULL,NULL,'delete_projects','Permission to delete projects','system','2025-01-24 15:04:56','2025-01-24 15:11:15',NULL,NULL,NULL),
(128,NULL,'manage_sync','Manage Synchronization','Allows user to access the sync dashboard and manage offline synchronization','irlam','2025-02-26 07:46:15','2025-02-26 09:47:49',NULL,NULL,NULL);
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `projects`
--

DROP TABLE IF EXISTS `projects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `projects` (
  `id` int NOT NULL AUTO_INCREMENT,
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
  `is_active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `projects`
--

LOCK TABLES `projects` WRITE;
/*!40000 ALTER TABLE `projects` DISABLE KEYS */;
INSERT INTO `projects` VALUES
(11,'Block 3','McGoff’s 237-Appartments -  Downtown Victoria North','2025-01-24','2028-01-24',NULL,22,22,'active','2025-01-24 17:23:33','2025-01-24 17:36:21',1),
(12,'Block 1','McGoff’s 237-Appartments - Downtown Victoria North','2025-02-02','2031-05-01',NULL,22,22,'active','2025-01-24 17:36:21','2025-01-24 16:36:21',1),
(13,'Block 2','McGoff’s 237-Appartments - Downtown Victoria North','2025-02-02','2029-01-02',NULL,22,22,'active','2025-01-24 17:36:21','2025-01-24 17:36:21',1),
(14,'Block 4','McGoff’s 237-Appartments - Downtown Victoria North','2025-02-07','2028-01-07',NULL,22,22,'active','2025-01-24 17:36:21','2025-01-24 17:36:21',1);
/*!40000 ALTER TABLE `projects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_permissions`
--

DROP TABLE IF EXISTS `role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_permissions` (
  `role_id` int NOT NULL,
  `permission_id` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `permission_id` (`permission_id`),
  CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_permissions`
--

LOCK TABLES `role_permissions` WRITE;
/*!40000 ALTER TABLE `role_permissions` DISABLE KEYS */;
INSERT INTO `role_permissions` VALUES
(1,1,'2025-01-25 13:28:35'),
(1,2,'2025-01-25 13:28:35'),
(1,3,'2025-01-25 13:28:35'),
(1,4,'2025-01-25 13:28:35'),
(2,2,'2025-01-25 13:28:35'),
(2,3,'2025-01-25 13:28:35'),
(3,2,'2025-01-25 13:28:35'),
(4,2,'2025-01-25 13:28:35'),
(5,2,'2025-01-25 13:28:35');
/*!40000 ALTER TABLE `role_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` text,
  `created_by` varchar(50) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES
(1,'admin','Administrator role with full access',NULL,NULL,'2025-03-29 13:27:52','irlam'),
(2,'manager','Project management and oversight capabilities',NULL,NULL,'2025-01-21 18:58:21','irlam'),
(3,'contractor','Contractor access for defect updates and responses',NULL,NULL,'2025-01-21 18:58:21','irlam'),
(4,'viewer','Read-only access to view defects and reports',NULL,NULL,'2025-01-21 18:58:21','irlam'),
(5,'client','Client access to view and comment on defects','irlam',NULL,'2025-01-21 18:58:21','irlam');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sync_conflicts`
--

DROP TABLE IF EXISTS `sync_conflicts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sync_conflicts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sync_queue_id` int NOT NULL,
  `entity_type` enum('defect','defect_comment','defect_image') NOT NULL,
  `entity_id` int NOT NULL,
  `server_data` longtext NOT NULL,
  `client_data` longtext NOT NULL,
  `resolved` tinyint(1) NOT NULL DEFAULT '0',
  `resolution_type` enum('server_wins','client_wins','merge','manual') DEFAULT NULL,
  `resolved_by` varchar(50) DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `entity_type` (`entity_type`,`entity_id`),
  KEY `resolved` (`resolved`),
  KEY `sync_queue_id` (`sync_queue_id`),
  CONSTRAINT `sync_conflicts_ibfk_1` FOREIGN KEY (`sync_queue_id`) REFERENCES `sync_queue` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sync_conflicts`
--

LOCK TABLES `sync_conflicts` WRITE;
/*!40000 ALTER TABLE `sync_conflicts` DISABLE KEYS */;
/*!40000 ALTER TABLE `sync_conflicts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sync_devices`
--

DROP TABLE IF EXISTS `sync_devices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sync_devices` (
  `id` int NOT NULL AUTO_INCREMENT,
  `device_id` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `device_name` varchar(100) DEFAULT NULL,
  `device_type` varchar(50) DEFAULT NULL,
  `last_sync` datetime DEFAULT NULL,
  `last_ip` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `device_id` (`device_id`),
  KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sync_devices`
--

LOCK TABLES `sync_devices` WRITE;
/*!40000 ALTER TABLE `sync_devices` DISABLE KEYS */;
/*!40000 ALTER TABLE `sync_devices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sync_logs`
--

DROP TABLE IF EXISTS `sync_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sync_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
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
  `details` longtext,
  PRIMARY KEY (`id`),
  KEY `username` (`username`),
  KEY `start_time` (`start_time`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sync_logs`
--

LOCK TABLES `sync_logs` WRITE;
/*!40000 ALTER TABLE `sync_logs` DISABLE KEYS */;
INSERT INTO `sync_logs` VALUES
(1,'irlam',NULL,'2025-02-26 11:22:55','2025-02-26 11:22:55',0,0,0,0,'bidirectional','success','Initial database setup',NULL),
(2,'irlam',NULL,'2025-02-26 11:25:26','2025-02-26 11:25:26',0,0,0,0,'bidirectional','success','Initial database setup',NULL),
(3,'irlam',NULL,'2025-02-26 11:25:27','2025-02-26 11:25:27',0,0,0,0,'bidirectional','success','Initial database setup',NULL),
(4,'irlam',NULL,'2025-02-26 11:25:28','2025-02-26 11:25:28',0,0,0,0,'bidirectional','success','Initial database setup',NULL);
/*!40000 ALTER TABLE `sync_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sync_queue`
--

DROP TABLE IF EXISTS `sync_queue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sync_queue` (
  `id` int NOT NULL AUTO_INCREMENT,
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
  `result` longtext,
  PRIMARY KEY (`id`),
  KEY `status` (`status`),
  KEY `entity_type` (`entity_type`,`entity_id`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sync_queue`
--

LOCK TABLES `sync_queue` WRITE;
/*!40000 ALTER TABLE `sync_queue` DISABLE KEYS */;
/*!40000 ALTER TABLE `sync_queue` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sync_settings`
--

DROP TABLE IF EXISTS `sync_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sync_settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text NOT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sync_settings`
--

LOCK TABLES `sync_settings` WRITE;
/*!40000 ALTER TABLE `sync_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `sync_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_logs`
--

DROP TABLE IF EXISTS `system_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `system_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `action` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `action_by` int DEFAULT NULL,
  `action_at` datetime NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `details` text COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `action_by` (`action_by`),
  CONSTRAINT `system_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `system_logs_ibfk_2` FOREIGN KEY (`action_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=345 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_logs`
--

LOCK TABLES `system_logs` WRITE;
/*!40000 ALTER TABLE `system_logs` DISABLE KEYS */;
INSERT INTO `system_logs` VALUES
(1,22,'UPDATE_CONTRACTOR',22,'2025-02-03 18:21:54',NULL,'Contractor Update - ID: 29, Old Status: active, New Status: active, Updated By: 22, Time: 2025-02-03 18:21:54'),
(2,22,'UPDATE_CONTRACTOR',22,'2025-02-03 18:22:05',NULL,'Contractor Update - ID: 30, Old Status: active, New Status: active, Updated By: 22, Time: 2025-02-03 18:22:05'),
(3,22,'UPDATE_CONTRACTOR',22,'2025-02-05 20:33:15',NULL,'Contractor Update - ID: 29, Old Status: active, New Status: active, Updated By: 22, Time: 2025-02-05 20:33:15'),
(4,22,'UPDATE_CONTRACTOR',22,'2025-02-06 15:42:50',NULL,'Contractor Update - ID: 31, Old Status: active, New Status: active, Updated By: 22, Time: 2025-02-06 15:42:50'),
(5,22,'UPDATE_CONTRACTOR',22,'2025-02-06 15:43:01',NULL,'Contractor Update - ID: 31, Old Status: active, New Status: active, Updated By: 22, Time: 2025-02-06 15:43:01'),
(6,22,'UPDATE_CONTRACTOR',22,'2025-02-06 18:44:26',NULL,'Contractor Update - ID: 51, Old Status: active, New Status: active, Updated By: 22, Time: 2025-02-06 18:44:26'),
(7,22,'UPDATE_CONTRACTOR',22,'2025-02-06 18:44:41',NULL,'Contractor Update - ID: 51, Old Status: active, New Status: active, Updated By: 22, Time: 2025-02-06 18:44:41'),
(8,22,'UPDATE_CONTRACTOR',22,'2025-02-06 18:52:31',NULL,'Contractor Update - ID: 31, Old Status: active, New Status: active, Updated By: 22, Time: 2025-02-06 18:52:31'),
(9,22,'UPDATE_CONTRACTOR',22,'2025-02-06 19:02:11',NULL,'Contractor Update - ID: 29, Old Status: active, New Status: active, Updated By: 22, Time: 2025-02-06 19:02:11'),
(10,22,'UPDATE_CONTRACTOR',22,'2025-02-06 19:05:09',NULL,'Contractor Update - ID: 30, Old Status: active, New Status: active, Updated By: 22, Time: 2025-02-06 19:05:09'),
(11,22,'UPDATE_CONTRACTOR',22,'2025-02-20 07:09:55',NULL,'Contractor Update - ID: 31, Old Status: active, New Status: active, Updated By: 22, Time: 2025-02-20 07:09:55'),
(12,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 11:04:18','172.70.90.111','Successfully accessed sync dashboard'),
(13,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 11:05:07','141.101.98.106','Successfully accessed sync dashboard'),
(14,22,'SYNC_SYSTEM_SETUP',22,'2025-02-26 11:22:55',NULL,'Offline synchronization system initialized'),
(15,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 11:23:38','172.70.85.23','Successfully accessed sync dashboard'),
(16,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 11:24:08','141.101.98.13','Successfully accessed sync dashboard'),
(17,22,'CLEAR_FAILED_SYNC',22,'2025-02-26 11:24:08',NULL,'Cleared failed sync items'),
(18,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 11:24:20','141.101.98.13','Successfully accessed sync dashboard'),
(19,22,'SYNC_SYSTEM_SETUP',22,'2025-02-26 11:25:26',NULL,'Offline synchronization system initialized'),
(20,22,'SYNC_SYSTEM_SETUP',22,'2025-02-26 11:25:27',NULL,'Offline synchronization system initialized'),
(21,22,'SYNC_SYSTEM_SETUP',22,'2025-02-26 11:25:28',NULL,'Offline synchronization system initialized'),
(22,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 11:25:34','172.70.162.53','Successfully accessed sync dashboard'),
(23,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 11:26:11','172.71.178.82','Successfully accessed sync dashboard'),
(24,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 11:28:58','172.70.91.104','Successfully accessed sync dashboard'),
(25,22,'RESOLVE_CONFLICTS',22,'2025-02-26 11:28:58',NULL,'Resolved 0 conflicts using server_wins strategy'),
(26,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 11:29:14','172.70.91.104','Successfully accessed sync dashboard'),
(27,22,'RETRY_FAILED_SYNC',22,'2025-02-26 11:29:14',NULL,'Retried 0 failed sync items'),
(28,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 11:42:38','172.71.241.78','Successfully accessed sync dashboard'),
(29,22,'RETRY_FAILED_SYNC',22,'2025-02-26 11:42:38',NULL,'Retried 0 failed sync items'),
(30,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 11:42:53','172.71.241.78','Successfully accessed sync dashboard'),
(31,22,'RETRY_FAILED_SYNC',22,'2025-02-26 11:42:53',NULL,'Retried 0 failed sync items'),
(32,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 11:43:00','172.71.241.78','Successfully accessed sync dashboard'),
(33,22,'CLEAR_COMPLETED_SYNC',22,'2025-02-26 11:43:00',NULL,'Cleared 0 completed sync items'),
(34,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 12:12:41','141.101.98.128','Successfully accessed sync dashboard'),
(35,22,'CLEAR_COMPLETED_SYNC',22,'2025-02-26 12:12:41',NULL,'Cleared 0 completed sync items'),
(36,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 12:16:31','141.101.98.27','Successfully accessed sync dashboard'),
(37,22,'CLEAR_COMPLETED_SYNC',22,'2025-02-26 12:16:31',NULL,'Cleared 0 completed sync items'),
(38,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 12:16:38','141.101.98.27','Successfully accessed sync dashboard'),
(39,22,'CLEAR_COMPLETED_SYNC',22,'2025-02-26 12:16:38',NULL,'Cleared 0 completed sync items'),
(40,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 12:16:45','141.101.98.27','Successfully accessed sync dashboard'),
(41,22,'RESOLVE_CONFLICTS',22,'2025-02-26 12:16:45',NULL,'Resolved 0 conflicts using server_wins strategy'),
(42,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 12:18:02','141.101.99.221','Successfully accessed sync dashboard'),
(43,22,'CLEAR_FAILED_SYNC',22,'2025-02-26 12:18:02',NULL,'Cleared failed sync items'),
(44,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 12:18:08','141.101.99.221','Successfully accessed sync dashboard'),
(45,22,'CLEAR_COMPLETED_SYNC',22,'2025-02-26 12:18:08',NULL,'Cleared 0 completed sync items'),
(46,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 12:18:31','141.101.99.221','Successfully accessed sync dashboard'),
(47,22,'CLEAR_COMPLETED_SYNC',22,'2025-02-26 12:18:31',NULL,'Cleared 0 completed sync items'),
(48,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 12:24:52','141.101.99.222','Successfully accessed sync dashboard'),
(49,22,'CLEAR_COMPLETED_SYNC',22,'2025-02-26 12:24:52',NULL,'Cleared 0 completed sync items'),
(50,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 12:41:48','172.69.195.158','Successfully accessed sync dashboard'),
(51,22,'CLEAR_COMPLETED_SYNC',22,'2025-02-26 12:41:48',NULL,'Cleared 0 completed sync items'),
(52,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 12:41:56','172.69.195.158','Successfully accessed sync dashboard'),
(53,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 12:42:00','172.69.195.158','Successfully accessed sync dashboard'),
(54,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 12:42:06','172.69.195.158','Successfully accessed sync dashboard'),
(55,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 12:42:10','172.69.195.158','Successfully accessed sync dashboard'),
(56,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 12:46:56','172.70.160.136','Successfully accessed sync dashboard'),
(57,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 12:47:07','172.70.160.136','Successfully accessed sync dashboard'),
(58,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 12:47:10','172.70.160.136','Successfully accessed sync dashboard'),
(59,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 12:48:35','172.70.163.143','Successfully accessed sync dashboard'),
(60,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 12:49:03','172.70.163.143','Successfully accessed sync dashboard'),
(61,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 12:50:04','141.101.99.221','Successfully accessed sync dashboard'),
(62,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 12:50:51','141.101.99.221','Successfully accessed sync dashboard'),
(63,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 12:51:52','141.101.98.27','Successfully accessed sync dashboard'),
(64,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 12:52:50','172.69.195.148','Successfully accessed sync dashboard'),
(65,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 12:53:56','141.101.98.87','Successfully accessed sync dashboard'),
(66,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 12:54:09','141.101.98.87','Successfully accessed sync dashboard'),
(67,22,'RESOLVE_CONFLICTS',22,'2025-02-26 12:54:09',NULL,'Resolved 0 conflicts using server_wins strategy'),
(68,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 12:55:10','172.71.178.157','Successfully accessed sync dashboard'),
(69,22,'RESOLVE_CONFLICTS',22,'2025-02-26 12:55:10',NULL,'Resolved 0 conflicts using server_wins strategy'),
(70,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 12:56:11','172.69.194.182','Successfully accessed sync dashboard'),
(71,22,'RESOLVE_CONFLICTS',22,'2025-02-26 12:56:11',NULL,'Resolved 0 conflicts using server_wins strategy'),
(72,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 12:57:12','172.70.160.252','Successfully accessed sync dashboard'),
(73,22,'RESOLVE_CONFLICTS',22,'2025-02-26 12:57:12',NULL,'Resolved 0 conflicts using server_wins strategy'),
(74,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 12:57:56','141.101.98.107','Successfully accessed sync dashboard'),
(75,22,'RESOLVE_CONFLICTS',22,'2025-02-26 12:57:56',NULL,'Resolved 0 conflicts using server_wins strategy'),
(76,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 12:58:02','141.101.98.107','Successfully accessed sync dashboard'),
(77,22,'RESOLVE_CONFLICTS',22,'2025-02-26 12:58:02',NULL,'Resolved 0 conflicts using server_wins strategy'),
(78,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 12:59:03','172.69.194.238','Successfully accessed sync dashboard'),
(79,22,'RESOLVE_CONFLICTS',22,'2025-02-26 12:59:03',NULL,'Resolved 0 conflicts using server_wins strategy'),
(80,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 12:59:18','172.69.194.238','Successfully accessed sync dashboard'),
(81,22,'RESOLVE_CONFLICTS',22,'2025-02-26 12:59:18',NULL,'Resolved 0 conflicts using server_wins strategy'),
(82,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 12:59:26','172.69.194.238','Successfully accessed sync dashboard'),
(83,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 12:59:44','172.69.194.238','Successfully accessed sync dashboard'),
(84,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 13:00:45','172.68.186.66','Successfully accessed sync dashboard'),
(85,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 13:01:46','172.71.241.123','Successfully accessed sync dashboard'),
(86,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 13:02:47','172.71.178.111','Successfully accessed sync dashboard'),
(87,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 13:03:48','141.101.99.160','Successfully accessed sync dashboard'),
(88,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 13:04:49','172.71.178.138','Successfully accessed sync dashboard'),
(89,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 13:05:50','172.71.178.47','Successfully accessed sync dashboard'),
(90,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 13:06:51','172.69.194.182','Successfully accessed sync dashboard'),
(91,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 13:07:52','141.101.98.107','Successfully accessed sync dashboard'),
(92,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 13:08:53','172.70.85.23','Successfully accessed sync dashboard'),
(93,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 13:09:54','172.71.178.75','Successfully accessed sync dashboard'),
(94,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 13:10:55','172.71.241.155','Successfully accessed sync dashboard'),
(95,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 13:11:56','172.70.162.20','Successfully accessed sync dashboard'),
(96,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 13:12:57','141.101.98.128','Successfully accessed sync dashboard'),
(97,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 13:13:58','172.70.160.239','Successfully accessed sync dashboard'),
(98,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 13:14:59','172.70.85.86','Successfully accessed sync dashboard'),
(99,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 13:16:00','172.70.85.22','Successfully accessed sync dashboard'),
(100,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 13:17:01','172.70.86.57','Successfully accessed sync dashboard'),
(101,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 13:18:02','141.101.98.156','Successfully accessed sync dashboard'),
(102,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 13:19:03','172.70.86.46','Successfully accessed sync dashboard'),
(103,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 13:20:04','172.71.241.53','Successfully accessed sync dashboard'),
(104,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 13:21:05','172.70.91.103','Successfully accessed sync dashboard'),
(105,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 13:22:06','172.70.91.103','Successfully accessed sync dashboard'),
(106,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 13:23:07','172.71.241.139','Successfully accessed sync dashboard'),
(107,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 13:24:08','172.71.178.112','Successfully accessed sync dashboard'),
(108,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 13:25:09','172.70.91.187','Successfully accessed sync dashboard'),
(109,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 13:26:10','172.71.178.10','Successfully accessed sync dashboard'),
(110,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 13:27:11','172.70.162.54','Successfully accessed sync dashboard'),
(111,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 13:28:12','172.69.43.206','Successfully accessed sync dashboard'),
(112,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 13:29:13','172.70.86.45','Successfully accessed sync dashboard'),
(113,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 13:30:14','172.70.160.238','Successfully accessed sync dashboard'),
(114,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 13:31:15','172.70.162.215','Successfully accessed sync dashboard'),
(115,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 13:32:16','172.70.86.96','Successfully accessed sync dashboard'),
(116,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 13:33:17','172.70.85.87','Successfully accessed sync dashboard'),
(117,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 13:34:18','172.70.91.187','Successfully accessed sync dashboard'),
(118,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 13:35:19','172.70.86.151','Successfully accessed sync dashboard'),
(119,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 13:36:20','141.101.99.21','Successfully accessed sync dashboard'),
(120,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 13:37:21','172.71.178.92','Successfully accessed sync dashboard'),
(121,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 13:38:22','172.71.178.74','Successfully accessed sync dashboard'),
(122,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 13:39:23','172.69.195.118','Successfully accessed sync dashboard'),
(123,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 13:40:24','172.71.241.100','Successfully accessed sync dashboard'),
(124,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 13:41:25','172.70.91.82','Successfully accessed sync dashboard'),
(125,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 13:42:26','172.70.91.97','Successfully accessed sync dashboard'),
(126,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 13:43:27','172.71.241.78','Successfully accessed sync dashboard'),
(127,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 13:44:28','172.70.160.137','Successfully accessed sync dashboard'),
(128,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 13:45:29','141.101.99.222','Successfully accessed sync dashboard'),
(129,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 13:46:30','172.70.91.104','Successfully accessed sync dashboard'),
(130,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 13:47:31','172.69.195.185','Successfully accessed sync dashboard'),
(131,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 13:48:32','172.68.186.173','Successfully accessed sync dashboard'),
(132,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 13:49:33','141.101.98.107','Successfully accessed sync dashboard'),
(133,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 13:50:34','141.101.99.221','Successfully accessed sync dashboard'),
(134,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 13:50:47','141.101.99.221','Successfully accessed sync dashboard'),
(135,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 13:52:58','172.70.86.96','Successfully accessed sync dashboard'),
(136,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 13:53:59','172.70.91.103','Successfully accessed sync dashboard'),
(137,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 13:55:00','172.70.85.34','Successfully accessed sync dashboard'),
(138,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 13:56:01','172.69.194.238','Successfully accessed sync dashboard'),
(139,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 13:57:02','172.70.90.60','Successfully accessed sync dashboard'),
(140,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 13:58:03','172.70.160.238','Successfully accessed sync dashboard'),
(141,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 13:58:31','172.70.160.238','Successfully accessed sync dashboard'),
(142,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 14:02:12','172.71.26.70','Successfully accessed sync dashboard'),
(143,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 14:02:39','172.71.26.70','Successfully accessed sync dashboard'),
(144,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 14:23:33','172.68.186.172','Successfully accessed sync dashboard'),
(145,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 14:23:48','172.68.186.172','Successfully accessed sync dashboard'),
(146,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 15:10:28','172.70.90.147','Successfully accessed sync dashboard'),
(147,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 15:18:19','141.101.98.11','Successfully accessed sync dashboard'),
(148,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 15:19:20','172.70.90.189','Successfully accessed sync dashboard'),
(149,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 15:20:21','172.68.186.109','Successfully accessed sync dashboard'),
(150,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 15:21:22','172.69.195.99','Successfully accessed sync dashboard'),
(151,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 15:22:23','172.70.85.22','Successfully accessed sync dashboard'),
(152,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 15:23:24','172.70.162.77','Successfully accessed sync dashboard'),
(153,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 15:24:25','172.70.91.82','Successfully accessed sync dashboard'),
(154,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 15:25:26','172.71.178.29','Successfully accessed sync dashboard'),
(155,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 15:26:27','172.71.178.10','Successfully accessed sync dashboard'),
(156,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 15:27:28','141.101.98.17','Successfully accessed sync dashboard'),
(157,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 15:28:29','172.69.195.158','Successfully accessed sync dashboard'),
(158,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 15:29:30','172.70.160.136','Successfully accessed sync dashboard'),
(159,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 15:30:31','172.71.241.132','Successfully accessed sync dashboard'),
(160,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 15:31:32','172.70.162.162','Successfully accessed sync dashboard'),
(161,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 15:32:33','172.70.91.188','Successfully accessed sync dashboard'),
(162,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 15:33:34','172.71.241.7','Successfully accessed sync dashboard'),
(163,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 15:34:35','172.69.195.90','Successfully accessed sync dashboard'),
(164,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 15:35:37','172.70.90.178','Successfully accessed sync dashboard'),
(165,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 15:36:38','172.71.241.34','Successfully accessed sync dashboard'),
(166,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 15:37:39','141.101.98.16','Successfully accessed sync dashboard'),
(167,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 15:38:40','172.70.90.99','Successfully accessed sync dashboard'),
(168,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 15:39:41','172.69.195.40','Successfully accessed sync dashboard'),
(169,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 15:40:42','172.70.91.187','Successfully accessed sync dashboard'),
(170,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 15:41:43','172.71.26.71','Successfully accessed sync dashboard'),
(171,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 15:42:44','172.70.85.190','Successfully accessed sync dashboard'),
(172,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 15:43:45','172.71.26.127','Successfully accessed sync dashboard'),
(173,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 15:44:46','172.70.90.179','Successfully accessed sync dashboard'),
(174,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 15:45:47','141.101.99.26','Successfully accessed sync dashboard'),
(175,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 15:46:48','172.71.178.92','Successfully accessed sync dashboard'),
(176,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 15:47:49','172.71.241.34','Successfully accessed sync dashboard'),
(177,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 15:48:50','172.69.195.58','Successfully accessed sync dashboard'),
(178,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 15:49:51','172.69.195.99','Successfully accessed sync dashboard'),
(179,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 15:50:52','172.70.91.188','Successfully accessed sync dashboard'),
(180,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 15:51:53','172.71.178.28','Successfully accessed sync dashboard'),
(181,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 15:52:54','141.101.98.99','Successfully accessed sync dashboard'),
(182,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 15:53:55','172.71.26.126','Successfully accessed sync dashboard'),
(183,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 15:54:56','172.70.85.87','Successfully accessed sync dashboard'),
(184,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 15:55:57','141.101.98.106','Successfully accessed sync dashboard'),
(185,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 15:56:58','141.101.98.27','Successfully accessed sync dashboard'),
(186,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 15:57:59','141.101.98.27','Successfully accessed sync dashboard'),
(187,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 15:59:00','141.101.98.27','Successfully accessed sync dashboard'),
(188,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 16:00:01','172.70.90.60','Successfully accessed sync dashboard'),
(189,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 16:01:02','172.69.195.29','Successfully accessed sync dashboard'),
(190,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 16:02:03','172.70.91.188','Successfully accessed sync dashboard'),
(191,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 16:03:04','172.71.26.71','Successfully accessed sync dashboard'),
(192,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 16:04:05','172.70.86.46','Successfully accessed sync dashboard'),
(193,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 16:05:06','172.70.90.188','Successfully accessed sync dashboard'),
(194,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 16:06:07','172.70.85.22','Successfully accessed sync dashboard'),
(195,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 16:07:08','172.69.195.57','Successfully accessed sync dashboard'),
(196,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 16:08:09','172.70.91.152','Successfully accessed sync dashboard'),
(197,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 16:09:10','172.69.43.148','Successfully accessed sync dashboard'),
(198,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 16:10:11','172.70.160.253','Successfully accessed sync dashboard'),
(199,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 16:11:12','172.69.195.30','Successfully accessed sync dashboard'),
(200,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 16:12:13','172.70.163.128','Successfully accessed sync dashboard'),
(201,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 16:13:14','172.70.162.161','Successfully accessed sync dashboard'),
(202,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 16:14:15','172.71.26.127','Successfully accessed sync dashboard'),
(203,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 16:15:16','172.69.195.57','Successfully accessed sync dashboard'),
(204,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 16:16:17','172.71.178.28','Successfully accessed sync dashboard'),
(205,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 16:17:18','172.70.86.151','Successfully accessed sync dashboard'),
(206,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 16:18:19','172.71.26.71','Successfully accessed sync dashboard'),
(207,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 16:19:20','172.70.163.108','Successfully accessed sync dashboard'),
(208,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 16:20:21','172.71.178.11','Successfully accessed sync dashboard'),
(209,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 16:21:22','172.70.162.20','Successfully accessed sync dashboard'),
(210,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 16:22:23','172.71.241.138','Successfully accessed sync dashboard'),
(211,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 16:23:24','172.70.162.54','Successfully accessed sync dashboard'),
(212,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 16:24:25','172.70.160.238','Successfully accessed sync dashboard'),
(213,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 16:25:26','172.71.241.34','Successfully accessed sync dashboard'),
(214,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 16:26:27','172.71.178.139','Successfully accessed sync dashboard'),
(215,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 16:27:28','172.70.91.152','Successfully accessed sync dashboard'),
(216,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 16:28:29','172.70.91.187','Successfully accessed sync dashboard'),
(217,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 16:29:30','172.70.90.188','Successfully accessed sync dashboard'),
(218,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 16:30:31','172.70.162.181','Successfully accessed sync dashboard'),
(219,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 16:31:32','172.71.103.21','Successfully accessed sync dashboard'),
(220,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 16:32:33','172.70.46.31','Successfully accessed sync dashboard'),
(221,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 16:33:34','172.71.183.110','Successfully accessed sync dashboard'),
(222,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 16:34:35','141.101.76.184','Successfully accessed sync dashboard'),
(223,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 16:35:36','172.70.163.163','Successfully accessed sync dashboard'),
(224,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 16:36:37','172.71.183.118','Successfully accessed sync dashboard'),
(225,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 16:37:38','172.70.162.196','Successfully accessed sync dashboard'),
(226,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 16:38:39','172.71.178.156','Successfully accessed sync dashboard'),
(227,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 16:39:40','172.70.162.19','Successfully accessed sync dashboard'),
(228,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 16:40:41','172.70.85.190','Successfully accessed sync dashboard'),
(229,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 16:41:42','172.70.91.188','Successfully accessed sync dashboard'),
(230,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 16:42:43','172.68.186.67','Successfully accessed sync dashboard'),
(231,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 16:43:44','141.101.98.10','Successfully accessed sync dashboard'),
(232,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 16:44:45','172.70.91.203','Successfully accessed sync dashboard'),
(233,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 16:45:46','172.70.86.165','Successfully accessed sync dashboard'),
(234,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 16:46:47','172.69.43.207','Successfully accessed sync dashboard'),
(235,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 16:47:48','141.101.99.159','Successfully accessed sync dashboard'),
(236,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 16:48:49','172.70.91.152','Successfully accessed sync dashboard'),
(237,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 16:49:50','172.71.241.156','Successfully accessed sync dashboard'),
(238,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 16:50:51','172.70.91.104','Successfully accessed sync dashboard'),
(239,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 16:51:52','172.69.43.197','Successfully accessed sync dashboard'),
(240,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 16:52:53','172.70.85.87','Successfully accessed sync dashboard'),
(241,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 16:53:54','141.101.99.26','Successfully accessed sync dashboard'),
(242,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 16:54:55','141.101.98.12','Successfully accessed sync dashboard'),
(243,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 16:55:56','172.71.178.67','Successfully accessed sync dashboard'),
(244,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 16:56:57','141.101.99.160','Successfully accessed sync dashboard'),
(245,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 16:57:58','172.69.195.186','Successfully accessed sync dashboard'),
(246,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 16:58:59','172.69.195.100','Successfully accessed sync dashboard'),
(247,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 17:00:00','172.70.85.86','Successfully accessed sync dashboard'),
(248,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 17:01:01','172.70.91.18','Successfully accessed sync dashboard'),
(249,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 17:02:04','172.69.195.117','Successfully accessed sync dashboard'),
(250,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 17:03:05','172.70.86.96','Successfully accessed sync dashboard'),
(251,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 17:04:06','172.69.43.139','Successfully accessed sync dashboard'),
(252,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 17:05:07','141.101.99.159','Successfully accessed sync dashboard'),
(253,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 17:06:08','172.71.178.83','Successfully accessed sync dashboard'),
(254,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 17:07:09','172.70.162.215','Successfully accessed sync dashboard'),
(255,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 17:08:11','172.71.241.160','Successfully accessed sync dashboard'),
(256,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 17:09:12','141.101.98.128','Successfully accessed sync dashboard'),
(257,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 17:10:13','172.70.91.82','Successfully accessed sync dashboard'),
(258,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 17:11:14','172.71.241.122','Successfully accessed sync dashboard'),
(259,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 17:12:15','172.69.195.157','Successfully accessed sync dashboard'),
(260,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 17:13:16','141.101.98.17','Successfully accessed sync dashboard'),
(261,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 17:14:17','172.70.162.78','Successfully accessed sync dashboard'),
(262,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 17:15:18','172.69.194.182','Successfully accessed sync dashboard'),
(263,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 17:16:19','172.71.241.78','Successfully accessed sync dashboard'),
(264,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 17:17:20','172.70.162.77','Successfully accessed sync dashboard'),
(265,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 17:18:21','172.68.186.66','Successfully accessed sync dashboard'),
(266,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 17:18:48','172.68.186.66','Successfully accessed sync dashboard'),
(267,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 17:19:13','172.68.186.66','Successfully accessed sync dashboard'),
(268,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 17:19:26','172.68.186.66','Successfully accessed sync dashboard'),
(269,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 17:20:27','172.70.90.179','Successfully accessed sync dashboard'),
(270,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 17:21:28','141.101.98.86','Successfully accessed sync dashboard'),
(271,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 17:22:29','172.70.86.57','Successfully accessed sync dashboard'),
(272,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 17:23:30','172.71.178.133','Successfully accessed sync dashboard'),
(273,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 17:24:31','172.70.163.95','Successfully accessed sync dashboard'),
(274,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 17:25:32','172.71.178.92','Successfully accessed sync dashboard'),
(275,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 17:26:33','172.70.160.238','Successfully accessed sync dashboard'),
(276,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 17:27:34','172.69.195.117','Successfully accessed sync dashboard'),
(277,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 17:28:35','141.101.98.106','Successfully accessed sync dashboard'),
(278,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 17:29:36','141.101.99.26','Successfully accessed sync dashboard'),
(279,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 17:30:37','141.101.98.10','Successfully accessed sync dashboard'),
(280,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 17:31:38','172.71.241.100','Successfully accessed sync dashboard'),
(281,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 17:32:39','141.101.98.129','Successfully accessed sync dashboard'),
(282,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 17:33:40','141.101.99.14','Successfully accessed sync dashboard'),
(283,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 17:34:41','172.70.163.142','Successfully accessed sync dashboard'),
(284,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 17:35:42','141.101.98.26','Successfully accessed sync dashboard'),
(285,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 17:36:43','141.101.99.21','Successfully accessed sync dashboard'),
(286,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 17:37:44','141.101.99.21','Successfully accessed sync dashboard'),
(287,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 17:38:45','141.101.99.21','Successfully accessed sync dashboard'),
(288,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 17:39:46','172.70.162.182','Successfully accessed sync dashboard'),
(289,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 17:40:47','172.70.86.96','Successfully accessed sync dashboard'),
(290,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 17:41:48','141.101.98.106','Successfully accessed sync dashboard'),
(291,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 17:42:49','172.70.85.23','Successfully accessed sync dashboard'),
(292,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 17:43:50','141.101.98.129','Successfully accessed sync dashboard'),
(293,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 17:44:51','172.70.91.151','Successfully accessed sync dashboard'),
(294,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 17:45:52','172.69.195.118','Successfully accessed sync dashboard'),
(295,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 17:46:53','172.70.163.162','Successfully accessed sync dashboard'),
(296,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 17:47:54','172.70.90.99','Successfully accessed sync dashboard'),
(297,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 17:48:54','141.101.99.159','Successfully accessed sync dashboard'),
(298,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 17:49:54','172.70.163.129','Successfully accessed sync dashboard'),
(299,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 17:50:55','172.71.241.79','Successfully accessed sync dashboard'),
(300,22,'SYNC_DASHBOARD_ACCESS',22,'2025-02-26 17:51:55','172.70.86.166','Successfully accessed sync dashboard'),
(301,NULL,'UNAUTHORIZED_ACCESS',NULL,'2025-02-27 08:28:15',NULL,'Attempted access to sync dashboard without login'),
(302,22,'SYNC_DASHBOARD_ACCESS',22,'2025-03-05 11:59:02','172.69.43.207','Successfully accessed sync dashboard'),
(303,22,'SYNC_DASHBOARD_ACCESS',22,'2025-03-05 12:00:03','172.69.43.207','Successfully accessed sync dashboard'),
(304,22,'SYNC_DASHBOARD_ACCESS',22,'2025-03-05 12:00:45','172.70.91.18','Successfully accessed sync dashboard'),
(305,22,'SYNC_DASHBOARD_ACCESS',22,'2025-03-05 12:00:51','172.70.91.18','Successfully accessed sync dashboard'),
(306,22,'SYNC_DASHBOARD_ACCESS',22,'2025-03-05 12:01:04','172.70.91.18','Successfully accessed sync dashboard'),
(307,22,'SYNC_DASHBOARD_ACCESS',22,'2025-03-05 12:01:14','172.70.91.18','Successfully accessed sync dashboard'),
(308,22,'SYNC_DASHBOARD_ACCESS',22,'2025-03-05 12:01:53','172.70.91.18','Successfully accessed sync dashboard'),
(309,22,'SYNC_DASHBOARD_ACCESS',22,'2025-03-05 12:02:05','172.70.91.18','Successfully accessed sync dashboard'),
(310,22,'SYNC_DASHBOARD_ACCESS',22,'2025-03-05 12:02:22','172.70.91.18','Successfully accessed sync dashboard'),
(311,22,'SYNC_DASHBOARD_ACCESS',22,'2025-03-05 12:02:25','172.70.91.18','Successfully accessed sync dashboard'),
(312,22,'SYNC_DASHBOARD_ACCESS',22,'2025-03-05 12:02:34','172.70.91.18','Successfully accessed sync dashboard'),
(313,22,'SYNC_DASHBOARD_ACCESS',22,'2025-03-05 12:03:06','141.101.99.22','Successfully accessed sync dashboard'),
(314,22,'SYNC_DASHBOARD_ACCESS',22,'2025-03-05 12:03:35','141.101.99.22','Successfully accessed sync dashboard'),
(315,22,'SYNC_DASHBOARD_ACCESS',22,'2025-03-05 12:04:07','141.101.99.22','Successfully accessed sync dashboard'),
(316,22,'SYNC_DASHBOARD_ACCESS',22,'2025-03-05 12:04:36','141.101.99.22','Successfully accessed sync dashboard'),
(317,22,'SYNC_DASHBOARD_ACCESS',22,'2025-03-05 12:05:08','141.101.99.22','Successfully accessed sync dashboard'),
(318,22,'SYNC_DASHBOARD_ACCESS',22,'2025-03-05 12:05:37','141.101.99.22','Successfully accessed sync dashboard'),
(319,22,'SYNC_DASHBOARD_ACCESS',22,'2025-03-05 12:06:09','141.101.98.10','Successfully accessed sync dashboard'),
(320,22,'SYNC_DASHBOARD_ACCESS',22,'2025-03-05 12:06:38','172.68.186.173','Successfully accessed sync dashboard'),
(321,22,'SYNC_DASHBOARD_ACCESS',22,'2025-03-05 12:07:10','172.70.162.20','Successfully accessed sync dashboard'),
(322,22,'SYNC_DASHBOARD_ACCESS',22,'2025-03-05 12:07:39','172.70.162.20','Successfully accessed sync dashboard'),
(323,22,'SYNC_DASHBOARD_ACCESS',22,'2025-03-05 12:08:11','172.68.186.172','Successfully accessed sync dashboard'),
(324,22,'SYNC_DASHBOARD_ACCESS',22,'2025-03-05 12:08:40','172.69.195.29','Successfully accessed sync dashboard'),
(325,22,'SYNC_DASHBOARD_ACCESS',22,'2025-03-05 12:09:12','172.69.195.29','Successfully accessed sync dashboard'),
(326,22,'SYNC_DASHBOARD_ACCESS',22,'2025-03-05 12:09:41','172.69.195.29','Successfully accessed sync dashboard'),
(327,22,'SYNC_DASHBOARD_ACCESS',22,'2025-03-05 12:10:13','172.69.195.29','Successfully accessed sync dashboard'),
(328,22,'SYNC_DASHBOARD_ACCESS',22,'2025-03-05 12:10:42','172.69.195.29','Successfully accessed sync dashboard'),
(329,22,'SYNC_DASHBOARD_ACCESS',22,'2025-03-05 12:11:14','172.69.195.100','Successfully accessed sync dashboard'),
(330,22,'SYNC_DASHBOARD_ACCESS',22,'2025-03-05 12:11:43','172.69.195.29','Successfully accessed sync dashboard'),
(331,22,'SYNC_DASHBOARD_ACCESS',22,'2025-03-05 12:11:59','172.69.195.100','Successfully accessed sync dashboard'),
(332,22,'SYNC_DASHBOARD_ACCESS',22,'2025-03-05 12:12:07','172.69.195.29','Successfully accessed sync dashboard'),
(333,22,'SYNC_DASHBOARD_ACCESS',22,'2025-03-05 12:12:18','172.69.195.29','Successfully accessed sync dashboard'),
(334,22,'SYNC_DASHBOARD_ACCESS',22,'2025-03-05 12:12:25','172.69.195.29','Successfully accessed sync dashboard'),
(335,22,'SYNC_DASHBOARD_ACCESS',22,'2025-03-05 12:12:29','172.69.195.29','Successfully accessed sync dashboard'),
(336,22,'SYNC_DASHBOARD_ACCESS',22,'2025-03-05 12:12:34','172.69.195.29','Successfully accessed sync dashboard'),
(337,22,'SYNC_DASHBOARD_ACCESS',22,'2025-03-05 12:12:45','172.69.195.29','Successfully accessed sync dashboard'),
(338,22,'SYNC_DASHBOARD_ACCESS',22,'2025-03-05 12:13:19','172.69.195.29','Successfully accessed sync dashboard'),
(339,22,'SYNC_DASHBOARD_ACCESS',22,'2025-03-05 12:14:21','172.70.160.252','Successfully accessed sync dashboard'),
(340,22,'SYNC_DASHBOARD_ACCESS',22,'2025-03-05 12:15:21','172.70.160.252','Successfully accessed sync dashboard'),
(341,22,'UNAUTHORIZED_ACCESS',22,'2025-03-28 18:05:42','172.70.86.236','Unauthorized access attempt to maintenance console by user ID: 22'),
(342,22,'UNAUTHORIZED_ACCESS',22,'2025-03-28 18:05:53','172.70.86.236','Unauthorized access attempt to maintenance console by user ID: 22'),
(343,22,'UNAUTHORIZED_ACCESS',22,'2025-03-28 18:06:42','172.70.160.136','Unauthorized access attempt to maintenance console by user ID: 22'),
(344,22,'UNAUTHORIZED_ACCESS',22,'2025-03-28 18:08:21','172.71.178.138','Unauthorized access attempt to maintenance console by user ID: 22');
/*!40000 ALTER TABLE `system_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_logs`
--

DROP TABLE IF EXISTS `user_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `action` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action_by` int NOT NULL,
  `action_at` datetime NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `details` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action_by` (`action_by`)
) ENGINE=InnoDB AUTO_INCREMENT=189 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_logs`
--

LOCK TABLES `user_logs` WRITE;
/*!40000 ALTER TABLE `user_logs` DISABLE KEYS */;
INSERT INTO `user_logs` VALUES
(121,22,'update_defect',22,'2025-02-09 02:19:30','82.4.67.225','{\"defect_id\":133,\"old_status\":\"open\",\"new_status\":\"closed\",\"updated_by\":22}','2025-02-09 02:19:30'),
(122,22,'update_defect',22,'2025-02-11 19:53:11','82.4.67.225','{\"defect_id\":192,\"old_status\":\"open\",\"new_status\":\"open\",\"updated_by\":22}','2025-02-11 19:53:11'),
(123,27,'create_user',22,'2025-02-13 08:41:57','41.180.248.70','{\"username\":\"manager\",\"email\":\"cirlam1@gmail.com\",\"user_type\":\"manager\",\"role\":\"manager\",\"role_id\":\"2\",\"contractor_id\":null,\"contractor_name\":null,\"created_by\":\"irlam\"}','2025-02-13 08:41:57'),
(124,24,'user_edited',22,'2025-02-18 16:16:48','172.71.241.77','{\"edited_by\":\"irlam\",\"timestamp\":\"2025-01-27 21:39:35\"}','2025-02-18 16:16:48'),
(125,25,'user_edited',22,'2025-02-18 16:17:51','172.71.241.2','{\"edited_by\":\"irlam\",\"timestamp\":\"2025-01-27 21:39:35\"}','2025-02-18 16:17:51'),
(126,27,'user_edited',22,'2025-02-18 16:20:52','172.70.86.131','{\"edited_by\":\"irlam\",\"timestamp\":\"2025-01-27 21:39:35\"}','2025-02-18 16:20:52'),
(127,27,'type_changed',22,'2025-02-18 16:21:06','172.70.86.131','{\"new_type\":\"manager\",\"new_role\":\"project_manager\",\"new_role_id\":2,\"contractor_id\":null,\"contractor_name\":null,\"contractor_trade\":null,\"changed_by\":\"irlam\",\"timestamp\":\"2025-01-27 21:39:35\"}','2025-02-18 16:21:06'),
(128,26,'status_changed',22,'2025-02-18 16:22:17','172.70.86.131','{\"new_status\":\"inactive\",\"changed_by\":\"irlam\",\"timestamp\":\"2025-01-27 21:39:35\"}','2025-02-18 16:22:17'),
(129,26,'status_changed',22,'2025-02-18 16:22:43','172.70.86.131','{\"new_status\":\"active\",\"changed_by\":\"irlam\",\"timestamp\":\"2025-01-27 21:39:35\"}','2025-02-18 16:22:43'),
(130,22,'update_defect',22,'2025-02-19 22:52:13','172.71.26.71','{\"defect_id\":229,\"old_status\":\"open\",\"new_status\":\"open\",\"updated_by\":22}','2025-02-19 22:52:13'),
(131,22,'update_defect',22,'2025-02-19 22:53:00','172.71.26.71','{\"defect_id\":229,\"old_status\":\"open\",\"new_status\":\"open\",\"updated_by\":22}','2025-02-19 22:53:00'),
(132,22,'update_defect',22,'2025-02-22 11:46:06','172.71.26.127','{\"defect_id\":237,\"old_status\":\"open\",\"new_status\":\"open\",\"updated_by\":22}','2025-02-22 11:46:06'),
(133,22,'update_defect',22,'2025-02-22 11:49:33','172.68.186.108','{\"defect_id\":239,\"old_status\":\"open\",\"new_status\":\"open\",\"updated_by\":22}','2025-02-22 11:49:33'),
(134,22,'update_defect',22,'2025-02-22 11:49:52','172.68.186.108','{\"defect_id\":238,\"old_status\":\"open\",\"new_status\":\"open\",\"updated_by\":22}','2025-02-22 11:49:52'),
(135,28,'create_user',22,'2025-02-22 15:40:42','172.68.186.108','{\"username\":\"test\",\"email\":\"test1@test.com\",\"user_type\":\"contractor\",\"role\":\"contractor\",\"role_id\":3,\"contractor_id\":\"31\",\"contractor_name\":\"Panacea\",\"created_by\":\"irlam\"}','2025-02-22 15:40:42'),
(136,22,'update_defect',22,'2025-02-24 12:59:46','172.70.86.12','{\"defect_id\":242,\"old_status\":\"\",\"new_status\":\"closed\",\"updated_by\":22}','2025-02-24 12:59:46'),
(137,28,'type_changed',22,'2025-03-01 13:44:58','172.70.163.128','{\"new_type\":\"contractor\",\"new_role\":\"contractor\",\"new_role_id\":3,\"contractor_id\":56,\"contractor_name\":\"Branniff Joinery\",\"contractor_trade\":\"\",\"changed_by\":\"irlam\",\"timestamp\":\"2025-02-27 18:21:25\"}','2025-03-01 13:44:58'),
(138,27,'type_changed',22,'2025-03-01 13:45:29','172.70.163.128','{\"new_type\":\"contractor\",\"new_role\":\"contractor\",\"new_role_id\":3,\"contractor_id\":53,\"contractor_name\":\"McGoff\",\"contractor_trade\":\"\",\"changed_by\":\"irlam\",\"timestamp\":\"2025-02-27 18:21:25\"}','2025-03-01 13:45:29'),
(139,25,'type_changed',22,'2025-03-01 13:46:04','172.70.163.128','{\"new_type\":\"manager\",\"new_role\":\"project_manager\",\"new_role_id\":2,\"contractor_id\":null,\"contractor_name\":null,\"contractor_trade\":null,\"changed_by\":\"irlam\",\"timestamp\":\"2025-02-27 18:21:25\"}','2025-03-01 13:46:04'),
(140,25,'type_changed',22,'2025-03-01 13:46:19','172.70.163.128','{\"new_type\":\"viewer\",\"new_role\":\"viewer\",\"new_role_id\":4,\"contractor_id\":null,\"contractor_name\":null,\"contractor_trade\":null,\"changed_by\":\"irlam\",\"timestamp\":\"2025-02-27 18:21:25\"}','2025-03-01 13:46:19'),
(141,25,'type_changed',22,'2025-03-01 13:46:39','172.70.163.128','{\"new_type\":\"contractor\",\"new_role\":\"contractor\",\"new_role_id\":3,\"contractor_id\":55,\"contractor_name\":\"Edencroft\",\"contractor_trade\":\"\",\"changed_by\":\"irlam\",\"timestamp\":\"2025-02-27 18:21:25\"}','2025-03-01 13:46:39'),
(142,25,'type_changed',22,'2025-03-01 13:47:02','172.70.163.128','{\"new_type\":\"contractor\",\"new_role\":\"contractor\",\"new_role_id\":3,\"contractor_id\":29,\"contractor_name\":\"Cara Brickwork\",\"contractor_trade\":\"\",\"changed_by\":\"irlam\",\"timestamp\":\"2025-02-27 18:21:25\"}','2025-03-01 13:47:02'),
(143,22,'update_defect',22,'2025-03-03 19:34:56','172.70.90.147','{\"defect_id\":250,\"old_status\":\"open\",\"new_status\":\"closed\",\"updated_by\":22}','2025-03-03 19:34:56'),
(144,29,'create_user',22,'2025-03-12 10:58:11','172.70.90.99','{\"username\":\"contractor1\",\"email\":\"contractor1@contractor.com\",\"user_type\":\"contractor\",\"role\":\"contractor\",\"role_id\":3,\"contractor_id\":\"53\",\"contractor_name\":\"McGoff\",\"created_by\":\"irlam\"}','2025-03-12 10:58:11'),
(145,29,'status_changed',22,'2025-03-13 15:36:43','172.69.194.118','{\"new_status\":\"inactive\",\"changed_by\":\"irlam\",\"timestamp\":\"2025-02-27 18:21:25\"}','2025-03-13 15:36:43'),
(146,29,'status_changed',22,'2025-03-13 15:37:03','172.69.194.118','{\"new_status\":\"active\",\"changed_by\":\"irlam\",\"timestamp\":\"2025-02-27 18:21:25\"}','2025-03-13 15:37:03'),
(147,29,'type_changed',22,'2025-03-13 15:37:46','172.71.241.6','{\"new_type\":\"contractor\",\"new_role\":\"contractor\",\"new_role_id\":3,\"contractor_id\":53,\"contractor_name\":\"McGoff\",\"contractor_trade\":\"\",\"changed_by\":\"irlam\",\"timestamp\":\"2025-02-27 18:21:25\"}','2025-03-13 15:37:46'),
(148,29,'type_changed',22,'2025-03-13 15:38:14','172.71.241.6','{\"new_type\":\"viewer\",\"new_role\":\"viewer\",\"new_role_id\":4,\"contractor_id\":null,\"contractor_name\":null,\"contractor_trade\":null,\"changed_by\":\"irlam\",\"timestamp\":\"2025-02-27 18:21:25\"}','2025-03-13 15:38:14'),
(149,29,'type_changed',22,'2025-03-13 15:38:45','172.71.241.6','{\"new_type\":\"contractor\",\"new_role\":\"contractor\",\"new_role_id\":3,\"contractor_id\":31,\"contractor_name\":\"Panacea\",\"contractor_trade\":\"\",\"changed_by\":\"irlam\",\"timestamp\":\"2025-02-27 18:21:25\"}','2025-03-13 15:38:45'),
(150,29,'type_changed',22,'2025-03-13 16:35:53','172.70.91.81','{\"new_type\":\"manager\",\"new_role\":\"project_manager\",\"new_role_id\":2,\"contractor_id\":null,\"contractor_name\":null,\"contractor_trade\":null,\"changed_by\":\"irlam\",\"timestamp\":\"2025-03-13 16:35:53\"}','2025-03-13 16:35:53'),
(151,29,'type_changed',22,'2025-03-13 16:36:17','172.70.91.81','{\"new_type\":\"contractor\",\"new_role\":\"contractor\",\"new_role_id\":3,\"contractor_id\":54,\"contractor_name\":\"Craven\",\"contractor_trade\":\"\",\"changed_by\":\"irlam\",\"timestamp\":\"2025-03-13 16:36:17\"}','2025-03-13 16:36:17'),
(152,29,'type_changed',22,'2025-03-13 16:36:30','172.70.91.81','{\"new_type\":\"contractor\",\"new_role\":\"contractor\",\"new_role_id\":3,\"contractor_id\":56,\"contractor_name\":\"Branniff Joinery\",\"contractor_trade\":\"\",\"changed_by\":\"irlam\",\"timestamp\":\"2025-03-13 16:36:30\"}','2025-03-13 16:36:30'),
(153,29,'type_changed',22,'2025-03-13 20:01:05','104.23.170.114','{\"new_type\":\"manager\",\"new_role\":\"project_manager\",\"new_role_id\":2,\"contractor_id\":53,\"contractor_name\":\"McGoff\",\"contractor_trade\":\"\",\"changed_by\":\"irlam\",\"timestamp\":\"2025-03-13 20:01:05\"}','2025-03-13 20:01:05'),
(154,29,'type_changed',22,'2025-03-13 20:01:42','104.23.170.114','{\"new_type\":\"contractor\",\"new_role\":\"contractor\",\"new_role_id\":3,\"contractor_id\":53,\"contractor_name\":\"McGoff\",\"contractor_trade\":\"\",\"changed_by\":\"irlam\",\"timestamp\":\"2025-03-13 20:01:42\"}','2025-03-13 20:01:42'),
(155,29,'type_changed',22,'2025-03-13 20:01:50','104.23.170.114','{\"new_type\":\"viewer\",\"new_role\":\"viewer\",\"new_role_id\":4,\"contractor_id\":null,\"contractor_name\":null,\"contractor_trade\":null,\"changed_by\":\"irlam\",\"timestamp\":\"2025-03-13 20:01:50\"}','2025-03-13 20:01:50'),
(156,29,'type_changed',22,'2025-03-13 20:01:57','104.23.170.114','{\"new_type\":\"admin\",\"new_role\":\"admin\",\"new_role_id\":1,\"contractor_id\":null,\"contractor_name\":null,\"contractor_trade\":null,\"changed_by\":\"irlam\",\"timestamp\":\"2025-03-13 20:01:57\"}','2025-03-13 20:01:57'),
(157,29,'type_changed',22,'2025-03-13 20:02:05','104.23.170.114','{\"new_type\":\"contractor\",\"new_role\":\"contractor\",\"new_role_id\":3,\"contractor_id\":53,\"contractor_name\":\"McGoff\",\"contractor_trade\":\"\",\"changed_by\":\"irlam\",\"timestamp\":\"2025-03-13 20:02:05\"}','2025-03-13 20:02:05'),
(158,29,'type_changed',22,'2025-03-13 20:02:19','104.23.170.114','{\"new_type\":\"contractor\",\"new_role\":\"contractor\",\"new_role_id\":3,\"contractor_id\":53,\"contractor_name\":\"McGoff\",\"contractor_trade\":\"\",\"changed_by\":\"irlam\",\"timestamp\":\"2025-03-13 20:02:19\"}','2025-03-13 20:02:19'),
(159,29,'type_changed',22,'2025-03-13 20:02:48','104.23.170.114','{\"new_type\":\"manager\",\"new_role\":\"project_manager\",\"new_role_id\":2,\"contractor_id\":53,\"contractor_name\":\"McGoff\",\"contractor_trade\":\"\",\"changed_by\":\"irlam\",\"timestamp\":\"2025-03-13 20:02:48\"}','2025-03-13 20:02:48'),
(160,29,'user_edited',22,'2025-03-13 20:08:13','141.101.99.159','{\"edited_by\":\"irlam\",\"timestamp\":\"2025-03-13 20:08:13\"}','2025-03-13 20:08:13'),
(161,29,'type_changed',22,'2025-03-13 20:08:44','172.70.85.22','{\"new_type\":\"contractor\",\"new_role\":\"contractor\",\"new_role_id\":3,\"contractor_id\":54,\"contractor_name\":\"Craven\",\"contractor_trade\":\"\",\"changed_by\":\"irlam\",\"timestamp\":\"2025-03-13 20:08:44\"}','2025-03-13 20:08:44'),
(162,27,'type_changed',22,'2025-03-13 20:09:23','172.71.178.156','{\"new_type\":\"manager\",\"new_role\":\"project_manager\",\"new_role_id\":2,\"contractor_id\":53,\"contractor_name\":\"McGoff\",\"contractor_trade\":\"\",\"changed_by\":\"irlam\",\"timestamp\":\"2025-03-13 20:09:23\"}','2025-03-13 20:09:23'),
(163,27,'type_changed',22,'2025-03-13 20:10:19','141.101.99.22','{\"new_type\":\"manager\",\"new_role\":\"project_manager\",\"new_role_id\":2,\"contractor_id\":53,\"contractor_name\":\"McGoff\",\"contractor_trade\":\"\",\"changed_by\":\"irlam\",\"timestamp\":\"2025-03-13 20:10:19\"}','2025-03-13 20:10:19'),
(164,27,'type_changed',22,'2025-03-13 20:10:31','141.101.99.22','{\"new_type\":\"manager\",\"new_role\":\"project_manager\",\"new_role_id\":2,\"contractor_id\":29,\"contractor_name\":\"Cara Brickwork\",\"contractor_trade\":\"\",\"changed_by\":\"irlam\",\"timestamp\":\"2025-03-13 20:10:31\"}','2025-03-13 20:10:31'),
(165,29,'type_changed',22,'2025-03-13 20:18:01','141.101.98.156','{\"new_type\":\"manager\",\"new_role\":\"project_manager\",\"new_role_id\":2,\"contractor_id\":56,\"contractor_name\":\"Branniff Joinery\",\"contractor_trade\":\"\",\"changed_by\":\"irlam\",\"timestamp\":\"2025-03-13 20:18:01\"}','2025-03-13 20:18:01'),
(166,29,'type_changed',22,'2025-03-13 20:18:16','141.101.98.156','{\"new_type\":\"manager\",\"new_role\":\"project_manager\",\"new_role_id\":2,\"contractor_id\":53,\"contractor_name\":\"McGoff\",\"contractor_trade\":\"\",\"changed_by\":\"irlam\",\"timestamp\":\"2025-03-13 20:18:16\"}','2025-03-13 20:18:16'),
(167,22,'update_defect',22,'2025-03-19 16:30:05','172.70.86.235','{\"defect_id\":310,\"old_status\":\"open\",\"new_status\":\"open\",\"updated_by\":22}','2025-03-19 16:30:05'),
(168,22,'update_defect',22,'2025-03-19 21:52:36','141.101.99.130','{\"defect_id\":313,\"old_status\":\"open\",\"new_status\":\"closed\",\"updated_by\":22}','2025-03-19 21:52:36'),
(169,25,'type_changed',22,'2025-03-20 15:44:49','172.69.195.157','{\"new_type\":\"viewer\",\"new_role\":\"viewer\",\"new_role_id\":4,\"contractor_id\":null,\"contractor_name\":null,\"contractor_trade\":null,\"changed_by\":\"irlam\",\"timestamp\":\"2025-03-20 15:44:49\"}','2025-03-20 15:44:49'),
(170,25,'type_changed',22,'2025-03-20 15:45:02','172.69.195.157','{\"new_type\":\"manager\",\"new_role\":\"project_manager\",\"new_role_id\":2,\"contractor_id\":52,\"contractor_name\":\"Heyrods\",\"contractor_trade\":\"\",\"changed_by\":\"irlam\",\"timestamp\":\"2025-03-20 15:45:02\"}','2025-03-20 15:45:02'),
(171,22,'update_defect',22,'2025-03-22 13:15:27','172.70.162.215','{\"defect_id\":292,\"old_status\":\"open\",\"new_status\":\"open\",\"updated_by\":22}','2025-03-22 13:15:27'),
(172,22,'update_defect',22,'2025-03-22 13:16:33','172.70.162.215','{\"defect_id\":289,\"old_status\":\"open\",\"new_status\":\"open\",\"updated_by\":22}','2025-03-22 13:16:33'),
(173,22,'update_defect',22,'2025-03-22 13:17:57','172.69.195.117','{\"defect_id\":289,\"old_status\":\"open\",\"new_status\":\"open\",\"updated_by\":22}','2025-03-22 13:17:57'),
(174,22,'update_defect',22,'2025-03-22 13:32:59','141.101.99.129','{\"defect_id\":297,\"old_status\":\"open\",\"new_status\":\"open\",\"updated_by\":22}','2025-03-22 13:32:59'),
(175,22,'update_defect',22,'2025-03-22 13:33:17','141.101.99.129','{\"defect_id\":315,\"old_status\":\"open\",\"new_status\":\"open\",\"updated_by\":22}','2025-03-22 13:33:17'),
(176,22,'update_defect',22,'2025-03-22 13:33:48','141.101.99.129','{\"defect_id\":301,\"old_status\":\"open\",\"new_status\":\"open\",\"updated_by\":22}','2025-03-22 13:33:48'),
(177,22,'update_defect',22,'2025-03-22 13:34:05','141.101.99.129','{\"defect_id\":301,\"old_status\":\"open\",\"new_status\":\"open\",\"updated_by\":22}','2025-03-22 13:34:05'),
(178,22,'update_defect',22,'2025-03-22 13:34:27','141.101.99.129','{\"defect_id\":301,\"old_status\":\"open\",\"new_status\":\"open\",\"updated_by\":22}','2025-03-22 13:34:27'),
(179,22,'update_defect',22,'2025-03-22 13:34:50','141.101.99.129','{\"defect_id\":301,\"old_status\":\"open\",\"new_status\":\"open\",\"updated_by\":22}','2025-03-22 13:34:50'),
(180,22,'update_defect',22,'2025-03-22 13:35:19','141.101.99.129','{\"defect_id\":284,\"old_status\":\"open\",\"new_status\":\"open\",\"updated_by\":22}','2025-03-22 13:35:19'),
(181,22,'update_defect',22,'2025-03-22 15:47:31','172.69.43.196','{\"defect_id\":279,\"old_status\":\"open\",\"new_status\":\"open\",\"updated_by\":22}','2025-03-22 15:47:31'),
(182,22,'update_defect',22,'2025-03-22 15:47:42','172.69.43.196','{\"defect_id\":279,\"old_status\":\"open\",\"new_status\":\"open\",\"updated_by\":22}','2025-03-22 15:47:42'),
(183,22,'update_defect',22,'2025-03-22 15:48:33','172.69.43.196','{\"defect_id\":288,\"old_status\":\"open\",\"new_status\":\"open\",\"updated_by\":22}','2025-03-22 15:48:33'),
(184,22,'update_defect',22,'2025-03-22 15:56:58','172.71.241.52','{\"defect_id\":302,\"old_status\":\"open\",\"new_status\":\"open\",\"updated_by\":22}','2025-03-22 15:56:58'),
(185,22,'update_defect',22,'2025-03-22 16:10:32','172.70.85.190','{\"defect_id\":254,\"old_status\":\"accepted\",\"new_status\":\"open\",\"updated_by\":22}','2025-03-22 16:10:32'),
(186,22,'update_defect',22,'2025-03-24 14:37:11','162.158.120.220','{\"defect_id\":320,\"old_status\":\"open\",\"new_status\":\"open\",\"updated_by\":22}','2025-03-24 14:37:11'),
(187,27,'type_changed',22,'2025-03-24 14:42:37','162.158.123.23','{\"new_type\":\"contractor\",\"new_role\":\"contractor\",\"new_role_id\":3,\"contractor_id\":29,\"contractor_name\":\"Cara Brickwork\",\"contractor_trade\":\"\",\"changed_by\":\"irlam\",\"timestamp\":\"2025-03-24 14:42:37\"}','2025-03-24 14:42:37'),
(188,25,'type_changed',22,'2025-03-24 14:42:45','162.158.123.23','{\"new_type\":\"contractor\",\"new_role\":\"contractor\",\"new_role_id\":3,\"contractor_id\":52,\"contractor_name\":\"Heyrods\",\"contractor_trade\":\"\",\"changed_by\":\"irlam\",\"timestamp\":\"2025-03-24 14:42:45\"}','2025-03-24 14:42:45');
/*!40000 ALTER TABLE `user_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_permissions`
--

DROP TABLE IF EXISTS `user_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_permissions` (
  `user_id` int NOT NULL,
  `permission_id` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`,`permission_id`),
  KEY `permission_id` (`permission_id`),
  CONSTRAINT `user_permissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_permissions`
--

LOCK TABLES `user_permissions` WRITE;
/*!40000 ALTER TABLE `user_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_recent_descriptions`
--

DROP TABLE IF EXISTS `user_recent_descriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_recent_descriptions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `description` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `user_recent_descriptions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=103 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_recent_descriptions`
--

LOCK TABLES `user_recent_descriptions` WRITE;
/*!40000 ALTER TABLE `user_recent_descriptions` DISABLE KEYS */;
INSERT INTO `user_recent_descriptions` VALUES
(26,28,'oooo','2025-02-23 16:40:30'),
(27,28,'bbbb','2025-02-24 20:54:31'),
(28,28,'bbbb','2025-02-24 21:11:28'),
(97,22,'It is a long established fact that a reader will be distracted by the readable content of a page when looking at its layout. The point of using Lorem Ipsum is that it has a more-or-less normal distribution of letters, as opposed to using &#039;Content here, content here&#039;, making it look like readable English. Many desktop publishing packages and web page editors now use Lorem Ipsum as their default model text, and a search for &#039;lorem ipsum&#039; will uncover many web sites still in their infancy. Various versions have evolved over the years, sometimes by accident, sometimes on purpose (injected humour and the like).','2025-03-21 14:20:16'),
(98,22,'It is a long established fact that a reader will be distracted by the readable content of a page when looking at its layout. The point of using Lorem Ipsum is that it has a more-or-less normal distribution of letters, as opposed to using &#039;Content here, content here&#039;, making it look like readable English. Many desktop publishing packages and web page editors now use Lorem Ipsum as their default model text, and a search for &#039;lorem ipsum&#039; will uncover many web sites still in their infancy. Various versions have evolved over the years, sometimes by accident, sometimes on purpose (injected humour and the like).','2025-03-22 13:49:03'),
(99,22,'Edencroft issue ','2025-03-22 18:17:52'),
(100,22,'It is a long established fact that a reader will be distracted by the readable content of a page when looking at its layout. The point of using Lorem Ipsum is that it has a more-or-less normal distribution of letters, as opposed to using &#039;Content here, content here&#039;, making it look like readable English. Many desktop publishing packages and web page editors now use Lorem Ipsum as their default model text, and a search for &#039;lorem ipsum&#039; will uncover many web sites still in their infancy. Various versions have evolved over the years, sometimes by accident, sometimes on purpose (injected humour and the like).','2025-03-24 14:36:39'),
(101,22,'Lanzarote ','2025-03-24 15:13:02'),
(102,22,'It is a long established fact that a reader will be distracted by the readable content of a page when looking at its layout. The point of using Lorem Ipsum is that it has a more-or-less normal distribution of letters, as opposed to using &#039;Content here, content here&#039;, making it look like readable English. Many desktop publishing packages and web page editors now use Lorem Ipsum as their default model text, and a search for &#039;lorem ipsum&#039; will uncover many web sites still in their infancy. Various versions have evolved over the years, sometimes by accident, sometimes on purpose (injected humour and the like).','2025-03-28 15:22:47');
/*!40000 ALTER TABLE `user_recent_descriptions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_roles`
--

DROP TABLE IF EXISTS `user_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `role_id` int NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_role` (`user_id`,`role_id`),
  KEY `fk_user_roles_role` (`role_id`),
  KEY `fk_user_roles_created_by` (`created_by`),
  KEY `fk_user_roles_updated_by` (`updated_by`),
  KEY `fk_user_roles_deleted_by` (`deleted_by`),
  CONSTRAINT `fk_user_roles_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_user_roles_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_user_roles_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_user_roles_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_user_roles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4628 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_roles`
--

LOCK TABLES `user_roles` WRITE;
/*!40000 ALTER TABLE `user_roles` DISABLE KEYS */;
INSERT INTO `user_roles` VALUES
(1941,27,2,'2025-02-13 08:41:57','2025-03-13 20:10:31',22,22,NULL,NULL),
(2324,28,3,'2025-02-22 15:40:42','2025-03-01 13:44:58',22,22,NULL,NULL),
(3032,27,3,'2025-03-01 13:45:29','2025-03-24 14:42:37',22,22,NULL,NULL);
/*!40000 ALTER TABLE `user_roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_sessions`
--

DROP TABLE IF EXISTS `user_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_sessions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `session_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `logged_in_at` datetime NOT NULL,
  `logged_out_at` datetime DEFAULT NULL,
  `last_activity` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `session_id` (`session_id`),
  KEY `last_activity` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_sessions`
--

LOCK TABLES `user_sessions` WRITE;
/*!40000 ALTER TABLE `user_sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
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
  `fcm_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `idx_username` (`username`),
  KEY `idx_email` (`email`),
  KEY `idx_user_type` (`user_type`),
  KEY `idx_status` (`status`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES
(22,'irlam','$2y$10$T5uTE5vYzwQJ9xJkNwNc1uIBroAZfbzSpA8pI8ZkL6C9hFpRcup2O','Chris','Irlam','cirlam@gmail.com','admin','active','irlam','Chris Irlam','admin','assets/images/default-avatar.png','light','2025-01-24 16:58:36','2025-03-29 14:27:52','irlam','2025-03-29 15:27:52',1,1,NULL,NULL,NULL,NULL),
(27,'manager','$2y$10$qHx1UPC.k75FojogyRy9O.q.ok5Bbs4U8PpAP9WCWCUsA7J1/p3nK','manager','manager','manager@manager.com','contractor','active','irlam','manageer manager','contractor',NULL,'light','2025-02-13 08:41:57','2025-03-24 14:42:37','irlam','2025-02-13 08:42:16',1,2,29,'Cara Brickwork','',NULL),
(28,'test','$2y$10$GTcFJdcKSXFVnDHgGc4yWumyNNdQBNoW.U5Y6K3oLN6o9RS1vqlDC','test first name','test last name','test1@test.com','contractor','active','irlam','test first name test last name','contractor',NULL,'light','2025-02-22 15:40:42','2025-03-01 13:44:58','irlam','2025-02-24 20:59:18',1,3,56,'Branniff Joinery','',NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`k87747_defecttracker`@`%`*/ /*!50003 TRIGGER `users_insert_trigger` BEFORE INSERT ON `users` FOR EACH ROW BEGIN
    SET NEW.created_at = NOW();
    SET NEW.created_by = 'irlam';
    SET NEW.updated_at = NOW();
    SET NEW.updated_by = 'irlam';
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`k87747_defecttracker`@`%`*/ /*!50003 TRIGGER `users_update_trigger` BEFORE UPDATE ON `users` FOR EACH ROW BEGIN
    SET NEW.updated_at = NOW();
    SET NEW.updated_by = 'irlam';
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Final view structure for view `acceptance_history`
--

/*!50001 DROP VIEW IF EXISTS `acceptance_history`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`k87747_defecttracker`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `acceptance_history` AS select `d`.`id` AS `defect_id`,`d`.`title` AS `title`,`d`.`status` AS `status`,`d`.`acceptance_comment` AS `acceptance_comment`,`u`.`username` AS `accepted_by_user`,`d`.`accepted_at` AS `accepted_at`,`p`.`name` AS `project_name`,`c`.`company_name` AS `contractor_name` from (((`defects` `d` left join `users` `u` on((`d`.`accepted_by` = `u`.`id`))) left join `projects` `p` on((`d`.`project_id` = `p`.`id`))) left join `contractors` `c` on((`d`.`assigned_to` = `c`.`id`))) where (`d`.`status` = 'accepted') order by `d`.`accepted_at` desc */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-03-29 15:55:50

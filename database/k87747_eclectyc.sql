-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 10.35.233.124:3306
-- Generation Time: Nov 08, 2025 at 11:03 AM
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
-- Database: `k87747_eclectyc`
--

-- --------------------------------------------------------

--
-- Table structure for table `ai_insights`
--

CREATE TABLE `ai_insights` (
  `id` bigint UNSIGNED NOT NULL,
  `meter_id` int UNSIGNED NOT NULL,
  `insight_date` date NOT NULL,
  `insight_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `recommendations` json DEFAULT NULL,
  `confidence_score` decimal(5,2) DEFAULT NULL COMMENT 'Percentage 0-100',
  `priority` enum('low','medium','high') COLLATE utf8mb4_unicode_ci DEFAULT 'medium',
  `is_dismissed` tinyint(1) DEFAULT '0',
  `dismissed_by` int UNSIGNED DEFAULT NULL,
  `dismissed_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `annual_aggregations`
--

CREATE TABLE `annual_aggregations` (
  `id` bigint UNSIGNED NOT NULL,
  `meter_id` int UNSIGNED NOT NULL,
  `year_start` date NOT NULL,
  `year_end` date NOT NULL,
  `total_consumption` decimal(15,3) NOT NULL,
  `peak_consumption` decimal(15,3) DEFAULT NULL,
  `off_peak_consumption` decimal(15,3) DEFAULT NULL,
  `min_daily_consumption` decimal(15,3) DEFAULT NULL,
  `max_daily_consumption` decimal(15,3) DEFAULT NULL,
  `day_count` int DEFAULT '0',
  `reading_count` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` int UNSIGNED DEFAULT NULL,
  `action` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_id` int UNSIGNED DEFAULT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `status` enum('pending','completed','failed','retrying') COLLATE utf8mb4_unicode_ci DEFAULT 'completed',
  `retry_count` int UNSIGNED DEFAULT '0',
  `parent_batch_id` varchar(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Reference to original batch if this is a retry',
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `companies`
--

CREATE TABLE `companies` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `registration_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vat_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `billing_address` text COLLATE utf8mb4_unicode_ci,
  `primary_contact_id` int UNSIGNED DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `comparison_snapshots`
--

CREATE TABLE `comparison_snapshots` (
  `id` bigint UNSIGNED NOT NULL,
  `meter_id` int UNSIGNED NOT NULL,
  `snapshot_date` date NOT NULL,
  `snapshot_type` enum('daily','weekly','monthly','annual') COLLATE utf8mb4_unicode_ci NOT NULL,
  `current_data` json NOT NULL,
  `comparison_data` json NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `daily_aggregations`
--

CREATE TABLE `daily_aggregations` (
  `id` bigint UNSIGNED NOT NULL,
  `meter_id` int UNSIGNED NOT NULL,
  `date` date NOT NULL,
  `total_consumption` decimal(15,3) NOT NULL,
  `peak_consumption` decimal(15,3) DEFAULT NULL,
  `off_peak_consumption` decimal(15,3) DEFAULT NULL,
  `min_reading` decimal(15,3) DEFAULT NULL,
  `max_reading` decimal(15,3) DEFAULT NULL,
  `reading_count` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `data_quality_issues`
--

CREATE TABLE `data_quality_issues` (
  `id` bigint UNSIGNED NOT NULL,
  `meter_id` int UNSIGNED NOT NULL,
  `issue_date` date NOT NULL,
  `issue_type` enum('missing_data','anomaly','outlier','negative_value','zero_reading') COLLATE utf8mb4_unicode_ci NOT NULL,
  `severity` enum('low','medium','high') COLLATE utf8mb4_unicode_ci DEFAULT 'medium',
  `description` text COLLATE utf8mb4_unicode_ci,
  `issue_data` json DEFAULT NULL,
  `is_resolved` tinyint(1) DEFAULT '0',
  `resolved_at` datetime DEFAULT NULL,
  `resolved_by` int UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `exports`
--

CREATE TABLE `exports` (
  `id` int UNSIGNED NOT NULL,
  `export_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `export_format` enum('csv','json','xml','excel') COLLATE utf8mb4_unicode_ci DEFAULT 'csv',
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` bigint DEFAULT NULL,
  `status` enum('pending','processing','completed','failed') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `created_by` int UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `external_calorific_values`
--

CREATE TABLE `external_calorific_values` (
  `id` bigint UNSIGNED NOT NULL,
  `region` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` date NOT NULL,
  `calorific_value` decimal(10,4) NOT NULL COMMENT 'Energy content',
  `unit` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'MJ/m3',
  `source` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'manual',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `external_carbon_intensity`
--

CREATE TABLE `external_carbon_intensity` (
  `id` bigint UNSIGNED NOT NULL,
  `region` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `datetime` datetime NOT NULL,
  `intensity` decimal(10,2) NOT NULL COMMENT 'gCO2/kWh',
  `forecast` decimal(10,2) DEFAULT NULL COMMENT 'Forecasted intensity',
  `actual` decimal(10,2) DEFAULT NULL COMMENT 'Actual intensity',
  `source` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'manual',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `external_temperature_data`
--

CREATE TABLE `external_temperature_data` (
  `id` bigint UNSIGNED NOT NULL,
  `location` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` date NOT NULL,
  `avg_temperature` decimal(5,2) DEFAULT NULL COMMENT 'Celsius',
  `min_temperature` decimal(5,2) DEFAULT NULL COMMENT 'Celsius',
  `max_temperature` decimal(5,2) DEFAULT NULL COMMENT 'Celsius',
  `source` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'manual',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `import_jobs`
--

CREATE TABLE `import_jobs` (
  `id` int UNSIGNED NOT NULL,
  `batch_id` varchar(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int UNSIGNED DEFAULT NULL,
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `import_type` enum('hh','daily') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'hh',
  `status` enum('queued','processing','completed','failed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'queued',
  `dry_run` tinyint(1) DEFAULT '0',
  `total_rows` int UNSIGNED DEFAULT NULL,
  `processed_rows` int UNSIGNED DEFAULT '0',
  `imported_rows` int UNSIGNED DEFAULT '0',
  `failed_rows` int UNSIGNED DEFAULT '0',
  `queued_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `summary` json DEFAULT NULL,
  `retry_count` int UNSIGNED DEFAULT '0' COMMENT 'Number of times this job has been retried',
  `max_retries` int UNSIGNED DEFAULT '3' COMMENT 'Maximum number of retries allowed',
  `retry_at` timestamp NULL DEFAULT NULL COMMENT 'When to retry this job (for delayed retries)',
  `last_error` text COLLATE utf8mb4_unicode_ci COMMENT 'Last error message (preserved across retries)',
  `notes` text COLLATE utf8mb4_unicode_ci COMMENT 'User notes about this import',
  `priority` enum('low','normal','high') COLLATE utf8mb4_unicode_ci DEFAULT 'normal' COMMENT 'Job priority',
  `tags` json DEFAULT NULL COMMENT 'Custom tags for categorization',
  `metadata` json DEFAULT NULL COMMENT 'Additional metadata (source, schedule info, etc)',
  `alert_sent` tinyint(1) DEFAULT '0' COMMENT 'Whether failure alert has been sent',
  `alert_sent_at` timestamp NULL DEFAULT NULL COMMENT 'When the alert was sent'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `meters`
--

CREATE TABLE `meters` (
  `id` int UNSIGNED NOT NULL,
  `site_id` int UNSIGNED NOT NULL,
  `supplier_id` int UNSIGNED DEFAULT NULL,
  `mpan` varchar(21) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Meter Point Administration Number',
  `serial_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meter_type` enum('electricity','gas','water','heat') COLLATE utf8mb4_unicode_ci DEFAULT 'electricity',
  `is_smart_meter` tinyint(1) DEFAULT '0',
  `is_half_hourly` tinyint(1) DEFAULT '0',
  `installation_date` date DEFAULT NULL,
  `removal_date` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `meter_readings`
--

CREATE TABLE `meter_readings` (
  `id` bigint UNSIGNED NOT NULL,
  `meter_id` int UNSIGNED NOT NULL,
  `reading_date` date NOT NULL,
  `reading_time` time DEFAULT NULL,
  `period_number` tinyint DEFAULT NULL COMMENT 'For half-hourly data: 1-48',
  `reading_value` decimal(15,3) NOT NULL COMMENT 'kWh or equivalent',
  `reading_type` enum('actual','estimated','manual') COLLATE utf8mb4_unicode_ci DEFAULT 'actual',
  `is_validated` tinyint(1) DEFAULT '0',
  `import_batch_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `monthly_aggregations`
--

CREATE TABLE `monthly_aggregations` (
  `id` bigint UNSIGNED NOT NULL,
  `meter_id` int UNSIGNED NOT NULL,
  `month_start` date NOT NULL,
  `month_end` date NOT NULL,
  `total_consumption` decimal(15,3) NOT NULL,
  `peak_consumption` decimal(15,3) DEFAULT NULL,
  `off_peak_consumption` decimal(15,3) DEFAULT NULL,
  `min_daily_consumption` decimal(15,3) DEFAULT NULL,
  `max_daily_consumption` decimal(15,3) DEFAULT NULL,
  `day_count` int DEFAULT '0',
  `reading_count` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `regions`
--

CREATE TABLE `regions` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `scheduler_alerts`
--

CREATE TABLE `scheduler_alerts` (
  `id` bigint UNSIGNED NOT NULL,
  `alert_type` enum('failure','warning','summary') COLLATE utf8mb4_unicode_ci NOT NULL,
  `range_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_acknowledged` tinyint(1) DEFAULT '0',
  `acknowledged_by` int UNSIGNED DEFAULT NULL,
  `acknowledged_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `scheduler_executions`
--

CREATE TABLE `scheduler_executions` (
  `id` bigint UNSIGNED NOT NULL,
  `execution_id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `range_type` enum('daily','weekly','monthly','annual') COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_date` date NOT NULL,
  `status` enum('running','completed','failed') COLLATE utf8mb4_unicode_ci DEFAULT 'running',
  `started_at` datetime NOT NULL,
  `completed_at` datetime DEFAULT NULL,
  `duration_seconds` decimal(10,3) DEFAULT NULL,
  `meters_processed` int DEFAULT '0',
  `error_count` int DEFAULT '0',
  `warning_count` int DEFAULT '0',
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `telemetry_data` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int UNSIGNED NOT NULL,
  `setting_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_unicode_ci,
  `setting_type` enum('string','integer','boolean','json') COLLATE utf8mb4_unicode_ci DEFAULT 'string',
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sites`
--

CREATE TABLE `sites` (
  `id` int UNSIGNED NOT NULL,
  `company_id` int UNSIGNED NOT NULL,
  `region_id` int UNSIGNED DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `postcode` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `site_type` enum('office','warehouse','retail','industrial','residential','other') COLLATE utf8mb4_unicode_ci DEFAULT 'other',
  `floor_area` decimal(10,2) DEFAULT NULL COMMENT 'Square meters',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tariffs`
--

CREATE TABLE `tariffs` (
  `id` int UNSIGNED NOT NULL,
  `supplier_id` int UNSIGNED DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `energy_type` enum('electricity','gas') COLLATE utf8mb4_unicode_ci NOT NULL,
  `tariff_type` enum('fixed','variable','time_of_use','dynamic') COLLATE utf8mb4_unicode_ci DEFAULT 'fixed',
  `unit_rate` decimal(10,4) DEFAULT NULL COMMENT 'Pence per kWh',
  `standing_charge` decimal(10,4) DEFAULT NULL COMMENT 'Pence per day',
  `valid_from` date NOT NULL,
  `valid_to` date DEFAULT NULL,
  `peak_rate` decimal(10,4) DEFAULT NULL,
  `off_peak_rate` decimal(10,4) DEFAULT NULL,
  `weekend_rate` decimal(10,4) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tariff_switching_analyses`
--

CREATE TABLE `tariff_switching_analyses` (
  `id` bigint UNSIGNED NOT NULL,
  `meter_id` int UNSIGNED NOT NULL,
  `current_tariff_id` int UNSIGNED DEFAULT NULL,
  `recommended_tariff_id` int UNSIGNED DEFAULT NULL,
  `analysis_start_date` date NOT NULL,
  `analysis_end_date` date NOT NULL,
  `current_cost` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'Total cost with current tariff',
  `recommended_cost` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'Total cost with recommended tariff',
  `potential_savings` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT 'Potential savings amount',
  `savings_percent` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT 'Potential savings percentage',
  `analysis_data` json DEFAULT NULL COMMENT 'Full analysis results including all alternatives',
  `analyzed_by` int UNSIGNED DEFAULT NULL COMMENT 'User who requested the analysis',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int UNSIGNED NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','manager','viewer') COLLATE utf8mb4_unicode_ci DEFAULT 'viewer',
  `is_active` tinyint(1) DEFAULT '1',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `weekly_aggregations`
--

CREATE TABLE `weekly_aggregations` (
  `id` bigint UNSIGNED NOT NULL,
  `meter_id` int UNSIGNED NOT NULL,
  `week_start` date NOT NULL,
  `week_end` date NOT NULL,
  `total_consumption` decimal(15,3) NOT NULL,
  `peak_consumption` decimal(15,3) DEFAULT NULL,
  `off_peak_consumption` decimal(15,3) DEFAULT NULL,
  `min_daily_consumption` decimal(15,3) DEFAULT NULL,
  `max_daily_consumption` decimal(15,3) DEFAULT NULL,
  `day_count` int DEFAULT '0',
  `reading_count` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `ai_insights`
--
ALTER TABLE `ai_insights`
  ADD PRIMARY KEY (`id`),
  ADD KEY `dismissed_by` (`dismissed_by`),
  ADD KEY `idx_meter` (`meter_id`),
  ADD KEY `idx_date` (`insight_date`),
  ADD KEY `idx_type` (`insight_type`),
  ADD KEY `idx_dismissed` (`is_dismissed`);

--
-- Indexes for table `annual_aggregations`
--
ALTER TABLE `annual_aggregations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_year` (`meter_id`,`year_start`),
  ADD KEY `idx_year` (`year_start`,`year_end`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_entity` (`entity_type`,`entity_id`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_parent_batch` (`parent_batch_id`);

--
-- Indexes for table `companies`
--
ALTER TABLE `companies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `registration_number` (`registration_number`),
  ADD KEY `primary_contact_id` (`primary_contact_id`),
  ADD KEY `idx_registration` (`registration_number`);

--
-- Indexes for table `comparison_snapshots`
--
ALTER TABLE `comparison_snapshots`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_snapshot` (`meter_id`,`snapshot_date`,`snapshot_type`),
  ADD KEY `idx_meter` (`meter_id`),
  ADD KEY `idx_date` (`snapshot_date`),
  ADD KEY `idx_type` (`snapshot_type`);

--
-- Indexes for table `daily_aggregations`
--
ALTER TABLE `daily_aggregations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_daily` (`meter_id`,`date`),
  ADD KEY `idx_date` (`date`);

--
-- Indexes for table `data_quality_issues`
--
ALTER TABLE `data_quality_issues`
  ADD PRIMARY KEY (`id`),
  ADD KEY `resolved_by` (`resolved_by`),
  ADD KEY `idx_meter` (`meter_id`),
  ADD KEY `idx_date` (`issue_date`),
  ADD KEY `idx_type` (`issue_type`),
  ADD KEY `idx_resolved` (`is_resolved`);

--
-- Indexes for table `exports`
--
ALTER TABLE `exports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `external_calorific_values`
--
ALTER TABLE `external_calorific_values`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_region_date` (`region`,`date`),
  ADD KEY `idx_region` (`region`),
  ADD KEY `idx_date` (`date`);

--
-- Indexes for table `external_carbon_intensity`
--
ALTER TABLE `external_carbon_intensity`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_region_datetime` (`region`,`datetime`),
  ADD KEY `idx_region` (`region`),
  ADD KEY `idx_datetime` (`datetime`);

--
-- Indexes for table `external_temperature_data`
--
ALTER TABLE `external_temperature_data`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_location_date` (`location`,`date`),
  ADD KEY `idx_location` (`location`),
  ADD KEY `idx_date` (`date`);

--
-- Indexes for table `import_jobs`
--
ALTER TABLE `import_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `batch_id` (`batch_id`),
  ADD KEY `idx_batch_id` (`batch_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_queued_at` (`queued_at`),
  ADD KEY `idx_completed_at` (`completed_at`),
  ADD KEY `idx_retry_at` (`retry_at`),
  ADD KEY `idx_priority` (`priority`,`queued_at`),
  ADD KEY `idx_alert_sent` (`alert_sent`,`status`);

--
-- Indexes for table `meters`
--
ALTER TABLE `meters`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `mpan` (`mpan`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `idx_mpan` (`mpan`),
  ADD KEY `idx_site` (`site_id`),
  ADD KEY `idx_type` (`meter_type`);

--
-- Indexes for table `meter_readings`
--
ALTER TABLE `meter_readings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_reading` (`meter_id`,`reading_date`,`reading_time`,`period_number`),
  ADD KEY `idx_meter_date` (`meter_id`,`reading_date`),
  ADD KEY `idx_date` (`reading_date`),
  ADD KEY `idx_batch` (`import_batch_id`);

--
-- Indexes for table `monthly_aggregations`
--
ALTER TABLE `monthly_aggregations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_month` (`meter_id`,`month_start`),
  ADD KEY `idx_month` (`month_start`,`month_end`);

--
-- Indexes for table `regions`
--
ALTER TABLE `regions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `idx_code` (`code`);

--
-- Indexes for table `scheduler_alerts`
--
ALTER TABLE `scheduler_alerts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `acknowledged_by` (`acknowledged_by`),
  ADD KEY `idx_alert_type` (`alert_type`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `scheduler_executions`
--
ALTER TABLE `scheduler_executions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `execution_id` (`execution_id`),
  ADD KEY `idx_execution_id` (`execution_id`),
  ADD KEY `idx_range_type` (`range_type`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_started` (`started_at`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `idx_key` (`setting_key`);

--
-- Indexes for table `sites`
--
ALTER TABLE `sites`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_company` (`company_id`),
  ADD KEY `idx_region` (`region_id`),
  ADD KEY `idx_postcode` (`postcode`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `idx_code` (`code`);

--
-- Indexes for table `tariffs`
--
ALTER TABLE `tariffs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `idx_code` (`code`),
  ADD KEY `idx_dates` (`valid_from`,`valid_to`);

--
-- Indexes for table `tariff_switching_analyses`
--
ALTER TABLE `tariff_switching_analyses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `analyzed_by` (`analyzed_by`),
  ADD KEY `idx_meter` (`meter_id`),
  ADD KEY `idx_current_tariff` (`current_tariff_id`),
  ADD KEY `idx_recommended_tariff` (`recommended_tariff_id`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_savings` (`potential_savings`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_role` (`role`);

--
-- Indexes for table `weekly_aggregations`
--
ALTER TABLE `weekly_aggregations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_week` (`meter_id`,`week_start`),
  ADD KEY `idx_week` (`week_start`,`week_end`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `ai_insights`
--
ALTER TABLE `ai_insights`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `annual_aggregations`
--
ALTER TABLE `annual_aggregations`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `companies`
--
ALTER TABLE `companies`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `comparison_snapshots`
--
ALTER TABLE `comparison_snapshots`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `daily_aggregations`
--
ALTER TABLE `daily_aggregations`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `data_quality_issues`
--
ALTER TABLE `data_quality_issues`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `exports`
--
ALTER TABLE `exports`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `external_calorific_values`
--
ALTER TABLE `external_calorific_values`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `external_carbon_intensity`
--
ALTER TABLE `external_carbon_intensity`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `external_temperature_data`
--
ALTER TABLE `external_temperature_data`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `import_jobs`
--
ALTER TABLE `import_jobs`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `meters`
--
ALTER TABLE `meters`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `meter_readings`
--
ALTER TABLE `meter_readings`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `monthly_aggregations`
--
ALTER TABLE `monthly_aggregations`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `regions`
--
ALTER TABLE `regions`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `scheduler_alerts`
--
ALTER TABLE `scheduler_alerts`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `scheduler_executions`
--
ALTER TABLE `scheduler_executions`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sites`
--
ALTER TABLE `sites`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tariffs`
--
ALTER TABLE `tariffs`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tariff_switching_analyses`
--
ALTER TABLE `tariff_switching_analyses`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `weekly_aggregations`
--
ALTER TABLE `weekly_aggregations`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `ai_insights`
--
ALTER TABLE `ai_insights`
  ADD CONSTRAINT `ai_insights_ibfk_1` FOREIGN KEY (`meter_id`) REFERENCES `meters` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ai_insights_ibfk_2` FOREIGN KEY (`dismissed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `annual_aggregations`
--
ALTER TABLE `annual_aggregations`
  ADD CONSTRAINT `annual_aggregations_ibfk_1` FOREIGN KEY (`meter_id`) REFERENCES `meters` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `companies`
--
ALTER TABLE `companies`
  ADD CONSTRAINT `companies_ibfk_1` FOREIGN KEY (`primary_contact_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `comparison_snapshots`
--
ALTER TABLE `comparison_snapshots`
  ADD CONSTRAINT `comparison_snapshots_ibfk_1` FOREIGN KEY (`meter_id`) REFERENCES `meters` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `daily_aggregations`
--
ALTER TABLE `daily_aggregations`
  ADD CONSTRAINT `daily_aggregations_ibfk_1` FOREIGN KEY (`meter_id`) REFERENCES `meters` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `data_quality_issues`
--
ALTER TABLE `data_quality_issues`
  ADD CONSTRAINT `data_quality_issues_ibfk_1` FOREIGN KEY (`meter_id`) REFERENCES `meters` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `data_quality_issues_ibfk_2` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `exports`
--
ALTER TABLE `exports`
  ADD CONSTRAINT `exports_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `import_jobs`
--
ALTER TABLE `import_jobs`
  ADD CONSTRAINT `import_jobs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `meters`
--
ALTER TABLE `meters`
  ADD CONSTRAINT `meters_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `meters_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `meter_readings`
--
ALTER TABLE `meter_readings`
  ADD CONSTRAINT `meter_readings_ibfk_1` FOREIGN KEY (`meter_id`) REFERENCES `meters` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `monthly_aggregations`
--
ALTER TABLE `monthly_aggregations`
  ADD CONSTRAINT `monthly_aggregations_ibfk_1` FOREIGN KEY (`meter_id`) REFERENCES `meters` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `scheduler_alerts`
--
ALTER TABLE `scheduler_alerts`
  ADD CONSTRAINT `scheduler_alerts_ibfk_1` FOREIGN KEY (`acknowledged_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `sites`
--
ALTER TABLE `sites`
  ADD CONSTRAINT `sites_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sites_ibfk_2` FOREIGN KEY (`region_id`) REFERENCES `regions` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `tariffs`
--
ALTER TABLE `tariffs`
  ADD CONSTRAINT `tariffs_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `tariff_switching_analyses`
--
ALTER TABLE `tariff_switching_analyses`
  ADD CONSTRAINT `tariff_switching_analyses_ibfk_1` FOREIGN KEY (`meter_id`) REFERENCES `meters` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tariff_switching_analyses_ibfk_2` FOREIGN KEY (`current_tariff_id`) REFERENCES `tariffs` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tariff_switching_analyses_ibfk_3` FOREIGN KEY (`recommended_tariff_id`) REFERENCES `tariffs` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tariff_switching_analyses_ibfk_4` FOREIGN KEY (`analyzed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `weekly_aggregations`
--
ALTER TABLE `weekly_aggregations`
  ADD CONSTRAINT `weekly_aggregations_ibfk_1` FOREIGN KEY (`meter_id`) REFERENCES `meters` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

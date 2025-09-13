<?php
// system_health.php

// Error reporting and logging setup
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

class SystemHealth {
    private $db;
    private $metrics = [];

    public function __construct($db) {
        $this->db = $db;
    }

    public function checkDatabaseConnection() {
        try {
            $this->db->query('SELECT 1');
            $this->metrics['database_connection'] = 'healthy';
            return true;
        } catch (Exception $e) {
            $this->metrics['database_connection'] = 'failed';
            return false;
        }
    }

    public function checkDiskSpace() {
        $totalSpace = disk_total_space('/');
        $freeSpace = disk_free_space('/');
        $usedPercentage = ($totalSpace - $freeSpace) / $totalSpace * 100;

        $this->metrics['disk_usage'] = [
            'total' => $totalSpace,
            'free' => $freeSpace,
            'used_percentage' => $usedPercentage
        ];

        return $usedPercentage < 90;
    }

    public function checkSystemLoad() {
        $load = sys_getloadavg();
        $this->metrics['system_load'] = $load;
        return $load[0] < 0.8;
    }

    public function getMetrics() {
        return $this->metrics;
    }
}

// Create necessary database tables
$db->exec("
    CREATE TABLE IF NOT EXISTS api_keys (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        api_key_hash VARCHAR(255) NOT NULL,
        revoked BOOLEAN DEFAULT FALSE,
        created_by VARCHAR(255),
        created_at DATETIME,
        updated_by VARCHAR(255),
        updated_at DATETIME,
        expires_at DATETIME,
        last_used_at DATETIME,
        FOREIGN KEY (user_id) REFERENCES users(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS system_configurations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        config_key VARCHAR(50) NOT NULL UNIQUE,
        config_value TEXT,
        created_by VARCHAR(255),
        created_at DATETIME,
        updated_by VARCHAR(255),
        updated_at DATETIME
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS email_templates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL UNIQUE,
        subject VARCHAR(255) NOT NULL,
        body TEXT NOT NULL,
        variables JSON,
        created_by VARCHAR(255),
        created_at DATETIME,
        updated_by VARCHAR(255),
        updated_at DATETIME
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS system_health_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        metric_name VARCHAR(50) NOT NULL,
        metric_value TEXT,
        status ENUM('healthy', 'warning', 'critical') NOT NULL,
        created_at DATETIME
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// Insert default email templates
$db->exec("
    INSERT IGNORE INTO email_templates (
        name, 
        subject, 
        body, 
        variables, 
        created_by, 
        created_at
    ) VALUES (
        'password_reset',
        'Password Reset Request',
        '<h2>Password Reset Request</h2><p>Dear {{username}},</p><p>Click the link below to reset your password:</p><p><a href=\"{{reset_link}}\">Reset Password</a></p>',
        '{\"username\": \"User\'s name\", \"reset_link\": \"Password reset URL\"}',
        'irlam',
        '2025-01-14 21:31:43'
    )
");

// Insert default system configurations
$db->exec("
    INSERT IGNORE INTO system_configurations (
        config_key, 
        config_value, 
        created_by, 
        created_at
    ) VALUES 
    ('password_policy', '{\"min_length\":12,\"require_special\":true,\"require_numbers\":true,\"require_uppercase\":true}', 'irlam', '2025-01-14 21:31:43'),
    ('session_timeout', '1800', 'irlam', '2025-01-14 21:31:43'),
    ('backup_retention_days', '30', 'irlam', '2025-01-14 21:31:43'),
    ('max_login_attempts', '5', 'irlam', '2025-01-14 21:31:43')
");
<?php
// config/database.php
// Configuration file for database connection and global settings

require_once __DIR__ . '/env.php';

if (!class_exists('Environment')) {
    throw new RuntimeException('Environment configuration loader is missing.');
}

class Database {
    // Database configuration - loaded from environment variables
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $conn = null;
    
    public function __construct() {
        // Load database configuration from environment variables
        $this->host = Environment::get('DB_HOST', '10.35.233.124:3306');
        $this->db_name = Environment::get('DB_NAME', 'k87747_defecttracker');
        $this->username = Environment::get('DB_USERNAME', 'k87747_defecttracker');
        $this->password = Environment::get('DB_PASSWORD', 'Subaru5554346');
    }

    // Get database connection
    public function getConnection() {
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                ]
            );
        } catch(PDOException $e) {
            // Log error instead of exposing it
            error_log("Database connection error: " . $e->getMessage());
            
            if (Environment::isDevelopment()) {
                throw new Exception("Database connection failed: " . $e->getMessage());
            } else {
                throw new Exception("Database connection failed. Please contact administrator.");
            }
        }
        return $this->conn;
    }
}

// Global configuration settings - loaded from environment variables
if (!defined('BASE_URL')) {
    define('BASE_URL', Environment::get('BASE_URL', 'http://localhost/'));
}

if (!defined('UPLOAD_PATH')) {
    define('UPLOAD_PATH', $_SERVER['DOCUMENT_ROOT'] . Environment::get('UPLOAD_PATH', '/uploads/'));
}

if (!defined('MAX_FILE_SIZE')) {
    define('MAX_FILE_SIZE', (int)Environment::get('MAX_FILE_SIZE', 5242880)); // 5MB in bytes
}

if (!defined('ALLOWED_FILE_TYPES')) {
    define('ALLOWED_FILE_TYPES', explode(',', Environment::get('ALLOWED_FILE_TYPES', 'jpg,jpeg,png,pdf')));
}

if (!defined('EMAIL_FROM')) {
    define('EMAIL_FROM', Environment::get('EMAIL_FROM', 'noreply@localhost.com'));
}

if (!defined('ITEMS_PER_PAGE')) {
    define('ITEMS_PER_PAGE', (int)Environment::get('ITEMS_PER_PAGE', 10));
}

// Security settings
if (!defined('CSRF_TOKEN_EXPIRES')) {
    define('CSRF_TOKEN_EXPIRES', (int)Environment::get('CSRF_TOKEN_EXPIRES', 3600));
}

if (!defined('SESSION_TIMEOUT')) {
    define('SESSION_TIMEOUT', (int)Environment::get('SESSION_TIMEOUT', 1800));
}

// Time zone setting
if (!ini_get('date.timezone')) {
    date_default_timezone_set('UTC');
}

// Error reporting based on environment
if (Environment::isDevelopment()) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
} else {
    error_reporting(E_ERROR | E_WARNING | E_PARSE);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}
ini_set('error_log', __DIR__ . '/../logs/error.log');
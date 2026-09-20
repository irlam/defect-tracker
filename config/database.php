<?php
// config/database.php
// Configuration file for database connection and global settings

require_once __DIR__ . '/env.php';

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $conn = null;

    public function __construct() {
        $this->host = Environment::get('DB_HOST', 'localhost');
        $this->db_name = Environment::get('DB_NAME', '');
        $this->username = Environment::get('DB_USERNAME', '');
        $this->password = Environment::get('DB_PASSWORD', '');
    }

    // Get database connection
    public function getConnection() {
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        } catch(PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
        }
        return $this->conn;
    }
}

// Global configuration settings
define('BASE_URL', 'https://mcgoff.defecttracker.uk/'); // Change this to your domain
define('UPLOAD_PATH', $_SERVER['DOCUMENT_ROOT'] . '/uploads/');
if (!defined('MAX_FILE_SIZE')) {
    define('MAX_FILE_SIZE', 5242880); // Preserve the legacy 5MB fallback.
}
define('ALLOWED_FILE_TYPES', ['jpg', 'jpeg', 'png', 'pdf']);
define('EMAIL_FROM', 'noreply@your-domain.com');
define('ITEMS_PER_PAGE', 10);

// Time zone setting
date_default_timezone_set('UTC');
?>

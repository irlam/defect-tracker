<?php
// config/database.php
// Configuration file for database connection and global settings


class Database {
    // Database configuration
    private $host = "10.35.233.124:3306";
    private $db_name = "k87747_defecttracker";
    private $username = "k87747_defecttracker";  // Change this to your MySQL username
    private $password = "7Mr@ww816";      // Change this to your MySQL password
    private $conn = null;

    // Get database connection
    public function getConnection() {
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8");
        } catch(PDOException $e) {
            echo "Connection error: " . $e->getMessage();
        }
        return $this->conn;
    }
}

// Global configuration settings
define('BASE_URL', 'https://mcgoff.defecttracker.uk/'); // Change this to your domain
define('UPLOAD_PATH', $_SERVER['DOCUMENT_ROOT'] . '/uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB in bytes
define('ALLOWED_FILE_TYPES', ['jpg', 'jpeg', 'png', 'pdf']);
define('EMAIL_FROM', 'noreply@your-domain.com');
define('ITEMS_PER_PAGE', 10);

// Time zone setting
date_default_timezone_set('UTC');
?>
<?php
require_once __DIR__ . '/env.php';

class Database {
    private $host;
    private $port;
    private $db_name;
    private $username;
    private $password;
    private $conn = null;

    public function __construct() {
        $rawHost = trim((string) Environment::get('DB_HOST', 'localhost'));
        $this->port = (int) Environment::get('DB_PORT', '3306');

        if (preg_match('/^\[(.+)\]:(\d+)$/', $rawHost, $m)) {
            $this->host = $m[1];
            $this->port = (int) $m[2];
        } elseif (preg_match('/^([^:]+):(\d+)$/', $rawHost, $m)) {
            $this->host = $m[1];
            $this->port = (int) $m[2];
        } else {
            $this->host = $rawHost;
        }

        $this->db_name = (string) Environment::get('DB_NAME', '');
        $this->username = (string) (Environment::get('DB_USERNAME', '') ?: Environment::get('DB_USER', ''));
        $this->password = (string) Environment::get('DB_PASSWORD', '');
    }

    public function getConnection() {
        if ($this->conn instanceof PDO) return $this->conn;
        try {
            $dsn = 'mysql:host=' . $this->host . ';port=' . $this->port . ';dbname=' . $this->db_name . ';charset=utf8mb4';
            $this->conn = new PDO($dsn, $this->username, $this->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            $this->conn = null;
        }
        return $this->conn;
    }
}

if (!defined('BASE_URL')) define('BASE_URL', rtrim((string) Environment::get('BASE_URL', '/'), '/') . '/');
if (!defined('UPLOAD_PATH')) define('UPLOAD_PATH', rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__)), '/') . '/uploads/');
if (!defined('MAX_FILE_SIZE')) define('MAX_FILE_SIZE', (int) Environment::get('MAX_FILE_SIZE', '5242880'));
if (!defined('ALLOWED_FILE_TYPES')) define('ALLOWED_FILE_TYPES', array_values(array_filter(array_map('trim', explode(',', (string) Environment::get('ALLOWED_FILE_TYPES', 'jpg,jpeg,png,pdf'))))));
if (!defined('EMAIL_FROM')) define('EMAIL_FROM', (string) Environment::get('MAIL_FROM', 'noreply@example.com'));
if (!defined('ITEMS_PER_PAGE')) define('ITEMS_PER_PAGE', 10);
date_default_timezone_set((string) Environment::get('APP_TIMEZONE', 'UTC'));

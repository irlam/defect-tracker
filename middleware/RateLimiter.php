<?php
// middleware/RateLimiter.php
class RateLimiter {
    private $redis;
    private $maxAttempts;
    private $decayMinutes;

    public function __construct($maxAttempts = 60, $decayMinutes = 1) {
        $this->redis = new Redis();
        $this->redis->connect('127.0.0.1', 6379);
        $this->maxAttempts = $maxAttempts;
        $this->decayMinutes = $decayMinutes;
    }

    public function attempt($key) {
        $attempts = $this->redis->incr($key);
        if ($attempts === 1) {
            $this->redis->expire($key, $this->decayMinutes * 60);
        }
        return $attempts <= $this->maxAttempts;
    }

    public function remaining($key) {
        $attempts = $this->redis->get($key) ?? 0;
        return max($this->maxAttempts - $attempts, 0);
    }
}

// Create necessary database tables
$db->exec("
    CREATE TABLE IF NOT EXISTS roles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL UNIQUE,
        description TEXT,
        created_by VARCHAR(255),
        created_at DATETIME,
        updated_by VARCHAR(255),
        updated_at DATETIME
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS permissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL UNIQUE,
        description TEXT,
        created_by VARCHAR(255),
        created_at DATETIME,
        updated_by VARCHAR(255),
        updated_at DATETIME
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS role_permissions (
        role_id INT,
        permission_id INT,
        created_by VARCHAR(255),
        created_at DATETIME,
        PRIMARY KEY (role_id, permission_id),
        FOREIGN KEY (role_id) REFERENCES roles(id),
        FOREIGN KEY (permission_id) REFERENCES permissions(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    CREATE TABLE IF NOT EXISTS user_roles (
        user_id INT,
        role_id INT,
        created_by VARCHAR(255),
        created_at DATETIME,
        PRIMARY KEY (user_id, role_id),
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (role_id) REFERENCES roles(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// Insert default roles and permissions
$db->exec("
    INSERT IGNORE INTO roles (name, description, created_by, created_at) VALUES
    ('admin', 'Full system access', 'irlam', '2025-01-14 21:22:10'),
    ('manager', 'Project management access', 'irlam', '2025-01-14 21:22:10'),
    ('contractor', 'Contractor access', 'irlam', '2025-01-14 21:22:10'),
    ('inspector', 'Inspector access', 'irlam', '2025-01-14 21:22:10'),
    ('viewer', 'Read-only access', 'irlam', '2025-01-14 21:22:10');

    INSERT IGNORE INTO permissions (name, description, created_by, created_at) VALUES
    ('manage_users', 'Can manage users', 'irlam', '2025-01-14 21:22:10'),
    ('manage_projects', 'Can manage projects', 'irlam', '2025-01-14 21:22:10'),
    ('manage_defects', 'Can manage defects', 'irlam', '2025-01-14 21:22:10'),
    ('view_reports', 'Can view reports', 'irlam', '2025-01-14 21:22:10'),
    ('export_data', 'Can export data', 'irlam', '2025-01-14 21:22:10');
");

// Security headers middleware
function setSecurityHeaders() {
    header("X-Frame-Options: SAMEORIGIN");
    header("X-XSS-Protection: 1; mode=block");
    header("X-Content-Type-Options: nosniff");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Content-Security-Policy: default-src 'self' https: 'unsafe-inline' 'unsafe-eval'");
    header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
}

// Password policy
function validatePassword($password) {
    $errors = [];
    
    if (strlen($password) < 12) {
        $errors[] = "Password must be at least 12 characters long";
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter";
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter";
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number";
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = "Password must contain at least one special character";
    }
    
    return empty($errors) ? true : $errors;
}

// Session timeout handling
function checkSessionTimeout() {
    $timeout = 30 * 60; // 30 minutes
    if (isset($_SESSION['last_activity']) && 
        (time() - $_SESSION['last_activity'] > $timeout)) {
        session_unset();
        session_destroy();
        header("Location: login.php?timeout=1");
        exit();
    }
    $_SESSION['last_activity'] = time();
}

// Input sanitization middleware
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}
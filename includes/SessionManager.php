<?php
/**
 * includes/SessionManager.php
 * Improved session management with security features
 * Current Date and Time (UTC): <?php echo gmdate('Y-m-d H:i:s'); ?>
 */

require_once __DIR__ . '/../config/database.php';

class SessionManager {
    private $sessionTimeout;
    
    public function __construct() {
        $this->sessionTimeout = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 1800;
        $this->initializeSession();
    }

    /**
     * Initialize secure session
     */
    private function initializeSession() {
        if (session_status() === PHP_SESSION_NONE) {
            // Set secure session configuration
            ini_set('session.use_only_cookies', 1);
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', Environment::isProduction() ? 1 : 0);
            ini_set('session.cookie_samesite', 'Strict');
            ini_set('session.use_strict_mode', 1);
            ini_set('session.gc_maxlifetime', $this->sessionTimeout);
            
            session_start();
            
            // Regenerate session ID periodically for security
            if (!isset($_SESSION['created'])) {
                $_SESSION['created'] = time();
            } else if (time() - $_SESSION['created'] > 1800) { // 30 minutes
                session_regenerate_id(true);
                $_SESSION['created'] = time();
            }
        }
    }

    /**
     * Check if user is authenticated
     */
    public function isAuthenticated() {
        return isset($_SESSION['username']) && 
               isset($_SESSION['user_id']) && 
               !empty($_SESSION['username']) &&
               $this->isSessionValid();
    }

    /**
     * Check if session is valid (not expired)
     */
    private function isSessionValid() {
        if (!isset($_SESSION['last_activity'])) {
            return false;
        }
        
        if ((time() - $_SESSION['last_activity']) > $this->sessionTimeout) {
            $this->logout();
            return false;
        }
        
        $_SESSION['last_activity'] = time();
        return true;
    }

    /**
     * Login user with enhanced security
     */
    public function login($username, $userId, $userType = null, $rememberMe = false) {
        // Regenerate session ID on login for security
        session_regenerate_id(true);
        
        $_SESSION['username'] = $username;
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_type'] = $userType;
        $_SESSION['last_activity'] = time();
        $_SESSION['created'] = time();
        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Set remember me cookie if requested
        if ($rememberMe) {
            $token = bin2hex(random_bytes(32));
            $expiry = time() + (30 * 24 * 60 * 60); // 30 days
            
            setcookie('remember_token', $token, $expiry, '/', '', Environment::isProduction(), true);
            
            // Store token in database (you'll need to create this table)
            $this->storeRememberToken($userId, $token, $expiry);
        }
        
        $this->logLogin($userId, $username);
    }

    /**
     * Logout user securely
     */
    public function logout() {
        $userId = $this->getUserId();
        $username = $this->getCurrentUser();
        
        // Clear remember me cookie and token
        if (isset($_COOKIE['remember_token'])) {
            $this->removeRememberToken($_COOKIE['remember_token']);
            setcookie('remember_token', '', time() - 3600, '/', '', Environment::isProduction(), true);
        }
        
        $this->logLogout($userId, $username);
        
        // Destroy session
        $_SESSION = array();
        
        // Delete session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
    }

    /**
     * Get current user
     */
    public function getCurrentUser() {
        return $_SESSION['username'] ?? null;
    }

    /**
     * Get user ID
     */
    public function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Get user type
     */
    public function getUserType() {
        return $_SESSION['user_type'] ?? null;
    }

    /**
     * Check session and redirect if not authenticated
     */
    public function checkSession($redirectUrl = 'login.php') {
        if (!$this->isAuthenticated()) {
            header('Location: ' . $redirectUrl);
            exit();
        }
        
        $this->validateSessionSecurity();
    }

    /**
     * Validate session security (IP and User Agent)
     */
    private function validateSessionSecurity() {
        $currentIp = $_SERVER['REMOTE_ADDR'] ?? '';
        $currentUserAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Check IP address change (optional, might cause issues with dynamic IPs)
        if (isset($_SESSION['ip_address']) && 
            Environment::isProduction() && 
            $_SESSION['ip_address'] !== $currentIp) {
            // Log suspicious activity
            error_log("Session IP mismatch for user " . $this->getCurrentUser() . 
                     ". Original: " . $_SESSION['ip_address'] . ", Current: " . $currentIp);
        }
        
        // Check User Agent change
        if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== $currentUserAgent) {
            // Log suspicious activity
            error_log("Session User Agent mismatch for user " . $this->getCurrentUser());
        }
    }

    /**
     * Store remember me token (placeholder - implement based on your database schema)
     */
    private function storeRememberToken($userId, $token, $expiry) {
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            $stmt = $db->prepare("
                INSERT INTO user_remember_tokens (user_id, token, expires_at, created_at) 
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                token = VALUES(token), expires_at = VALUES(expires_at)
            ");
            
            $stmt->execute([
                $userId, 
                hash('sha256', $token), 
                date('Y-m-d H:i:s', $expiry),
                gmdate('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            error_log("Failed to store remember token: " . $e->getMessage());
        }
    }

    /**
     * Remove remember me token
     */
    private function removeRememberToken($token) {
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            $stmt = $db->prepare("DELETE FROM user_remember_tokens WHERE token = ?");
            $stmt->execute([hash('sha256', $token)]);
        } catch (Exception $e) {
            error_log("Failed to remove remember token: " . $e->getMessage());
        }
    }

    /**
     * Log user login
     */
    private function logLogin($userId, $username) {
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            $stmt = $db->prepare("
                INSERT INTO user_activity_logs (user_id, activity, ip_address, user_agent, created_at) 
                VALUES (?, 'login', ?, ?, ?)
            ");
            
            $stmt->execute([
                $userId,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                gmdate('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            error_log("Failed to log login activity: " . $e->getMessage());
        }
    }

    /**
     * Log user logout
     */
    private function logLogout($userId, $username) {
        if (!$userId) return;
        
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            $stmt = $db->prepare("
                INSERT INTO user_activity_logs (user_id, activity, ip_address, user_agent, created_at) 
                VALUES (?, 'logout', ?, ?, ?)
            ");
            
            $stmt->execute([
                $userId,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                gmdate('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            error_log("Failed to log logout activity: " . $e->getMessage());
        }
    }

    /**
     * Clean expired sessions and tokens
     */
    public static function cleanup() {
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            // Clean expired remember tokens
            $stmt = $db->prepare("DELETE FROM user_remember_tokens WHERE expires_at < NOW()");
            $stmt->execute();
            
            // Clean old activity logs (keep 90 days)
            $stmt = $db->prepare("DELETE FROM user_activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
            $stmt->execute();
            
        } catch (Exception $e) {
            error_log("Failed to cleanup sessions: " . $e->getMessage());
        }
    }
}
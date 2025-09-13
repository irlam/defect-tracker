<?php
/**
 * Authentication Class
 * Current Date and Time (UTC): 2025-01-19 20:19:51
 * Current User's Login: irlam
 */

class Auth {
    private $db;
    private $sessionTimeout = 1800; // 30 minutes
    private $maxLoginAttempts = 5;
    private $lockoutDuration = 900; // 15 minutes

    public function __construct($db) {
        if (!$db) {
            throw new Exception('Database connection required');
        }
        $this->db = $db;
        $this->initializeSession();
    }

    /**
     * Initialize and secure session
     */
    private function initializeSession() {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', 1);
            ini_set('session.use_strict_mode', 1);
            session_start();
        }
    }

    /**
     * Check if user is authenticated
     * @return bool
     */
    public function isAuthenticated() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    /**
     * Check if user has specific role
     * @param int $userId
     * @param string $role
     * @return bool
     */
    public function hasRole($userId, $role) {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) 
                FROM user_roles ur
                JOIN roles r ON ur.role_id = r.id
                JOIN users u ON ur.user_id = u.id
                WHERE u.id = ? 
                AND r.name = ?
                AND u.status = 'active'
                AND ur.deleted_at IS NULL
            ");
            
            $stmt->execute([$userId, $role]);
            return (bool)$stmt->fetchColumn();
        } catch (Exception $e) {
            error_log("Error checking role: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get user roles
     * @param int $userId
     * @return array
     */
    public function getUserRoles($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT r.name, r.description
                FROM user_roles ur
                JOIN roles r ON ur.role_id = r.id
                WHERE ur.user_id = ?
                AND ur.deleted_at IS NULL
            ");
            
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error getting user roles: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Check if user has permission
     * @param int $userId
     * @param string $permission
     * @return bool
     */
    public function hasPermission($userId, $permission) {
        try {
            // First check if user has admin role
            if ($this->hasRole($userId, 'admin')) {
                return true;
            }

            $stmt = $this->db->prepare("
                SELECT COUNT(*) 
                FROM user_permissions up
                JOIN permissions p ON up.permission_id = p.id
                WHERE up.user_id = ? 
                AND p.name = ?
            ");
            
            $stmt->execute([$userId, $permission]);
            return (bool)$stmt->fetchColumn();
        } catch (Exception $e) {
            error_log("Error checking permission: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate user session
     * @return bool
     */
    public function validateSession() {
        if (!$this->isAuthenticated()) {
            return false;
        }

        // Check session lifetime (30 minutes)
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
            session_unset();
            session_destroy();
            return false;
        }

        $_SESSION['last_activity'] = time();
        return true;
    }

    /**
     * Log authentication attempt
     * @param string $username
     * @param bool $success
     * @return void
     */
    public function logAuthAttempt($username, $success) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO activity_logs (
                    action_type,
                    table_name,
                    action_by,
                    action_at,
                    details
                ) VALUES (
                    'AUTH_ATTEMPT',
                    'users',
                    ?,
                    UTC_TIMESTAMP(),
                    ?
                )
            ");
            
            $details = json_encode([
                'success' => $success,
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT']
            ]);
            
            $stmt->execute([$username, $details]);
        } catch (Exception $e) {
            error_log("Error logging auth attempt: " . $e->getMessage());
        }
    }
}
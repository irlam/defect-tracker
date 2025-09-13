<?php
// includes/functions.php
// Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): 2025-01-21 18:34:35
// Current User's Login: irlam

// Define constants
define('CURRENT_UTC_DATETIME', gmdate('d-m-Y H:i:s'));
define('CURRENT_USER', isset($_SESSION['username']) ? $_SESSION['username'] : 'irlam');

function isAdmin($userId = null) {
    global $db;
    
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    $userId = $userId ?? $_SESSION['user_id'];
    
    try {
        $stmt = $db->prepare("
            SELECT user_type 
            FROM users 
            WHERE id = ? 
            AND is_active = 1
            AND status = 'active'
        ");
        
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return ($result && $result['user_type'] === 'admin');
    } catch (Exception $e) {
        error_log("Error checking admin status: " . $e->getMessage());
        return false;
    }
}

if (!function_exists('loadUserPermissions')) {
    function loadUserPermissions($db, $userId) {
        try {
            // Query to get combined permissions from both direct user permissions and role-based permissions
            $query = "SELECT DISTINCT p.permission_name, p.permission_key
                     FROM (
                         SELECT p.permission_name, p.permission_key
                         FROM user_permissions up
                         JOIN permissions p ON up.permission_id = p.id 
                         WHERE up.user_id = :user_id
                         AND up.deleted_at IS NULL
                         
                         UNION
                         
                         SELECT p.permission_name, p.permission_key
                         FROM role_permissions rp
                         JOIN permissions p ON rp.permission_id = p.id
                         JOIN user_roles ur ON rp.role_id = ur.role_id
                         WHERE ur.user_id = :user_id
                         AND ur.deleted_at IS NULL
                         AND rp.deleted_at IS NULL
                     ) AS combined_permissions";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            
            $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Store permissions in session
            $_SESSION['user_permissions'] = array();
            foreach ($permissions as $permission) {
                $_SESSION['user_permissions'][] = $permission['permission_key'];
            }
            
            // Log the permissions load
            error_log(CURRENT_UTC_DATETIME . " - Permissions loaded for user: " . CURRENT_USER . " (ID: $userId)");
            
            return true;
        } catch (PDOException $e) {
            error_log(CURRENT_UTC_DATETIME . " - Error loading permissions for user " . CURRENT_USER . ": " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('getStatusBadgeClass')) {
    function getStatusBadgeClass($status) {
        switch($status) {
            case 'open':
                return 'bg-secondary';
            case 'in_progress':
                return 'bg-primary';
            case 'completed':
                return 'bg-success';
            case 'verified':
                return 'bg-info';
            default:
                return 'bg-secondary';
        }
    }
}

if (!function_exists('getPriorityBadgeClass')) {
    function getPriorityBadgeClass($priority) {
        switch($priority) {
            case 'critical':
                return 'bg-danger';
            case 'high':
                return 'bg-warning text-dark';
            case 'medium':
                return 'bg-info';
            case 'low':
                return 'bg-success';
            default:
                return 'bg-secondary';
        }
    }
}

if (!function_exists('userHasPermission')) {
    function userHasPermission($permission) {
        if (!isset($_SESSION['user_permissions'])) {
            return false;
        }
        return in_array($permission, $_SESSION['user_permissions']);
    }
}

function getRoleBadgeClass($role) {
    $classes = [
        'administrator' => 'bg-danger',
        'manager' => 'bg-success',
        'supervisor' => 'bg-warning text-dark',
        'user' => 'bg-primary'
    ];
    return $classes[strtolower($role)] ?? 'bg-primary';
}

// Single declaration of isActivePage
if (!function_exists('isActivePage')) {
    function isActivePage($page) {
        $currentPage = basename($_SERVER['PHP_SELF']);
        return ($currentPage === $page) ? 'active' : '';
    }
}

if (!function_exists('formatDate')) {
    function formatDate($date) {
        return date('d M, Y', strtotime($date));
    }
}

if (!function_exists('getStatusClass')) {
    function getStatusClass($status) {
        $classes = [
            'open' => 'warning',
            'in_progress' => 'info',
            'closed' => 'success'
        ];
        return $classes[$status] ?? 'secondary';
    }
}

if (!function_exists('getPriorityClass')) {
    function getPriorityClass($priority) {
        $classes = [
            'critical' => 'danger',
            'high' => 'warning',
            'medium' => 'info',
            'low' => 'secondary'
        ];
        return $classes[$priority] ?? 'secondary';
    }
}

if (!function_exists('loadUserPermissions')) {
    function loadUserPermissions($userId, $db) {
        try {
            $query = "SELECT p.name 
                      FROM user_permissions up
                      JOIN permissions p ON up.permission_id = p.id 
                      WHERE up.user_id = :user_id
                      UNION
                      SELECT p.name
                      FROM role_permissions rp
                      JOIN permissions p ON rp.permission_id = p.id
                      JOIN user_roles ur ON rp.role_id = ur.role_id
                      WHERE ur.user_id = :user_id
                      AND ur.deleted_at IS NULL";
            
            $stmt = $db->prepare($query);
            $stmt->execute([':user_id' => $userId]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            error_log("Error loading user permissions: " . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('hasPermission')) {
    function hasPermission($permissionName, $userPermissions) {
        return in_array($permissionName, $userPermissions);
    }
}

if (!function_exists('getCurrentDateTime')) {
    function getCurrentDateTime() {
        return CURRENT_UTC_DATETIME;
    }
}

if (!function_exists('getCurrentUser')) {
    function getCurrentUser() {
        return CURRENT_USER;
    }
}
    
// Correct (global scope)
function getStatusColor($status) {
    $classes = [
        'open' => 'warning',
        'in_progress' => 'info',
        'closed' => 'success',
        'rejected' => 'danger',
        'pending' => 'secondary'
    ];
    return $classes[$status] ?? 'secondary';
}

if (!function_exists('sanitizeOutput')) {
    function sanitizeOutput($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

// Define the logAction function
if (!function_exists('logAction')) {
    function logAction($db, $action, $userId, $details = []) {
        try {
            $stmt = $db->prepare("
                INSERT INTO action_log (action, user_id, details, created_at)
                VALUES (:action, :user_id, :details, UTC_TIMESTAMP())
            ");
            $stmt->execute([
                ':action' => $action,
                ':user_id' => $userId,
                ':details' => json_encode($details)
            ]);
        } catch (Exception $e) {
            // Log the error
            error_log("Failed to log action: " . $e->getMessage());
        }
    }
}
?>
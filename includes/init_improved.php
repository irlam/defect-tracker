<?php
/**
 * includes/init_improved.php
 * Improved application initialization with security and performance enhancements
 * Current Date and Time (UTC): <?php echo gmdate('Y-m-d H:i:s'); ?>
 */

// Load core configuration and error handling first
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/error_handler.php';

// Load essential classes
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/SessionManager.php';
require_once __DIR__ . '/../classes/Security.php';

// Initialize session manager
$sessionManager = new SessionManager();

// Security function to check if request is AJAX
function isAjaxRequest() {
    return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');
}

// Enhanced logging functions
function logError($message, $context = []) {
    ErrorHandler::logError($message, $context);
}

function logSecurity($message, $context = []) {
    ErrorHandler::logSecurity($message, $context);
}

function logInfo($message, $context = []) {
    ErrorHandler::logInfo($message, $context);
}

// CSRF token functions
function generateCSRFToken() {
    return Security::generateCSRFToken();
}

function validateCSRFToken($token) {
    return Security::validateCSRFToken($token);
}

// Input sanitization helper
function sanitizeInput($data) {
    return Security::sanitizeInput($data);
}

// Authentication helper
function requireAuth($redirectUrl = 'login.php') {
    global $sessionManager;
    $sessionManager->checkSession($redirectUrl);
}

// Check if user has specific permission
function hasPermission($permission) {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $stmt = $db->prepare("
            SELECT COUNT(*) FROM (
                SELECT 1 FROM user_permissions up
                JOIN permissions p ON up.permission_id = p.id
                WHERE up.user_id = ? AND p.name = ? AND up.deleted_at IS NULL
                
                UNION
                
                SELECT 1 FROM user_roles ur
                JOIN role_permissions rp ON ur.role_id = rp.role_id
                JOIN permissions p ON rp.permission_id = p.id
                WHERE ur.user_id = ? AND p.name = ?
            ) perms
        ");
        
        $stmt->execute([$_SESSION['user_id'], $permission, $_SESSION['user_id'], $permission]);
        return $stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        logError("Permission check failed: " . $e->getMessage(), [
            'user_id' => $_SESSION['user_id'] ?? null,
            'permission' => $permission
        ]);
        return false;
    }
}

// Check if user is admin
function isAdmin($userId = null) {
    $userId = $userId ?? $_SESSION['user_id'] ?? null;
    
    if (!$userId) {
        return false;
    }
    
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $stmt = $db->prepare("
            SELECT user_type 
            FROM users 
            WHERE id = ? AND is_active = 1 AND status = 'active'
        ");
        
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return ($result && $result['user_type'] === 'admin');
    } catch (Exception $e) {
        logError("Admin check failed: " . $e->getMessage(), ['user_id' => $userId]);
        return false;
    }
}

// Set timezone
if (defined('DEFAULT_TIMEZONE')) {
    date_default_timezone_set(DEFAULT_TIMEZONE);
} else {
    date_default_timezone_set('Europe/London');
}

// Set default template variables
$pageTitle = $pageTitle ?? (defined('APP_NAME') ? APP_NAME : 'Defect Tracker');
$currentUser = $_SESSION['username'] ?? null;
$currentUserId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['user_type'] ?? null;

// Generate CSRF token for forms
$csrfToken = generateCSRFToken();

// Set default success/error messages if not set
$success_message = $success_message ?? '';
$error_message = $error_message ?? '';

// Define export directories
define('EXPORT_DIR', __DIR__ . '/../exports');
define('TEMP_DIR', EXPORT_DIR . '/temp');

// Create necessary directories
$directories = [EXPORT_DIR, TEMP_DIR, __DIR__ . '/../logs'];
foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Clean up old temporary files (run occasionally)
if (rand(1, 100) === 1) { // 1% chance to run cleanup
    $files = glob(TEMP_DIR . '/*');
    $cutoff = time() - (24 * 60 * 60); // 24 hours ago
    
    foreach ($files as $file) {
        if (is_file($file) && filemtime($file) < $cutoff) {
            unlink($file);
        }
    }
}

// Add security logging for sensitive operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sensitiveActions = ['login', 'create', 'update', 'delete', 'upload'];
    $currentScript = basename($_SERVER['SCRIPT_NAME']);
    
    foreach ($sensitiveActions as $action) {
        if (strpos($currentScript, $action) !== false) {
            logSecurity("POST request to sensitive endpoint: {$currentScript}", [
                'user_id' => $currentUserId,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
            break;
        }
    }
}

// Debug information for development environment
if (Environment::isDevelopment() && isset($_GET['debug'])) {
    $debug = [
        'session' => $_SESSION,
        'server_vars' => [
            'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'],
            'REQUEST_URI' => $_SERVER['REQUEST_URI'],
            'HTTP_HOST' => $_SERVER['HTTP_HOST'],
            'REMOTE_ADDR' => $_SERVER['REMOTE_ADDR'],
            'HTTP_USER_AGENT' => $_SERVER['HTTP_USER_AGENT']
        ],
        'environment' => [
            'php_version' => PHP_VERSION,
            'environment' => Environment::get('ENVIRONMENT', 'production'),
            'timestamp' => gmdate('Y-m-d H:i:s')
        ]
    ];
    
    echo '<div class="alert alert-info"><h5>Debug Information</h5><pre>' . htmlspecialchars(print_r($debug, true)) . '</pre></div>';
}
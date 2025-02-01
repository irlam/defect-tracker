<?php
/**
 * Application Initialization
 * Created: 2025-01-19 14:18:01 UTC
 * Author: irlam
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Set error reporting based on environment
if (defined('ENVIRONMENT')) {
    switch (ENVIRONMENT) {
        case 'development':
            error_reporting(E_ALL);
            ini_set('display_errors', 1);
            break;
        case 'testing':
        case 'staging':
            error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
            ini_set('display_errors', 0);
            break;
        case 'production':
            error_reporting(0);
            ini_set('display_errors', 0);
            break;
        default:
            header('HTTP/1.1 503 Service Unavailable.', TRUE, 503);
            echo 'Environment is not properly set.';
            exit(1);
    }
}

// Set default timezone
date_default_timezone_set('UTC');

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.gc_maxlifetime', 3600); // 1 hour
ini_set('session.cookie_lifetime', 0); // Until browser closes

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\' \'unsafe-eval\' https://cdn.jsdelivr.net; style-src \'self\' \'unsafe-inline\' https://cdn.jsdelivr.net; img-src \'self\' data: https:; font-src \'self\' https://cdn.jsdelivr.net');

// Load required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

// Initialize database connection
try {
    $database = new Database();
    $db = $database->getConnection();
} catch (Exception $e) {
    logError('Database connection failed: ' . $e->getMessage());
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        die('Database connection failed: ' . $e->getMessage());
    } else {
        die('A system error has occurred. Please try again later.');
    }
}

// Check authentication if required
if (defined('AUTHENTICATION_REQUIRED') && AUTHENTICATION_REQUIRED === true) {
    if (!isset($_SESSION['user_id'])) {
        if (isAjaxRequest()) {
            http_response_code(401);
            die(json_encode([
                'status' => 'error',
                'message' => 'Authentication required',
                'redirect' => 'login.php',
                'timestamp' => date('Y-m-d H:i:s')
            ]));
        } else {
            header('Location: login.php');
            exit;
        }
    }
}

// Utility functions
function isAjaxRequest() {
    return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');
}

function logError($message, $context = []) {
    $timestamp = date('Y-m-d H:i:s');
    $user = $_SESSION['username'] ?? 'system';
    $logMessage = "[ERROR][$timestamp][$user] $message";
    
    if (!empty($context)) {
        $logMessage .= " Context: " . print_r($context, true);
    }
    
    error_log($logMessage);
}

function validateCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        throw new Exception('Invalid security token');
    }
}

function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Set some default template variables
$pageTitle = $pageTitle ?? 'Defect Tracker';
$currentUser = $_SESSION['username'] ?? null;
$currentUserId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['user_role'] ?? null;

// Generate CSRF token if not exists
generateCSRFToken();

// Set default success/error messages if not set
$success_message = $success_message ?? '';
$error_message = $error_message ?? '';

// Debug information for development environment
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    
    // Add debug information to the global scope
    $debug = [
        'session' => $_SESSION,
        'server' => $_SERVER,
        'request' => $_REQUEST,
        'files' => $_FILES,
        'timestamp' => date('Y-m-d H:i:s')
    ];
}

/**
 * init.php
 * Current Date and Time (UTC): 2025-01-26 17:04:45
 * Current User's Login: irlam
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

// Set timezone
date_default_timezone_set('UTC');

// Load autoloader
require_once __DIR__ . '/autoload.php';

// Define export directories
define('EXPORT_DIR', __DIR__ . '/../exports');
define('TEMP_DIR', EXPORT_DIR . '/temp');

// Create export directories if they don't exist
if (!file_exists(EXPORT_DIR)) {
    mkdir(EXPORT_DIR, 0755, true);
}
if (!file_exists(TEMP_DIR)) {
    mkdir(TEMP_DIR, 0755, true);
}

// Clean up old temporary files
$files = glob(TEMP_DIR . '/*');
$now = time();
foreach ($files as $file) {
    if (is_file($file)) {
        if ($now - filemtime($file) >= 24 * 3600) { // 24 hours
            unlink($file);
        }
    }
}
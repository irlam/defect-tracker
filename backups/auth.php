<?php
/**
 * McGoff Backup Manager - Authentication Handler
 * 
 * This file checks for an existing authenticated session from the main application
 * and ensures the user has appropriate permissions (admin or manager role).
 * 
 * Updated: 2025-11-04
 * Author: irlam
 */

// Helper function to check if request is AJAX
function isAjaxRequest(): bool {
    // Check X-Requested-With header
    $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    if (!empty($requestedWith) && strtolower($requestedWith) === 'xmlhttprequest') {
        return true;
    }
    
    // Check Accept header for JSON
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    if (!empty($accept) && strpos($accept, 'application/json') !== false) {
        return true;
    }
    
    // Check Content-Type header for JSON (handles both CONTENT_TYPE and HTTP_CONTENT_TYPE)
    $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
    if (!empty($contentType) && strpos($contentType, 'application/json') !== false) {
        return true;
    }
    
    return false;
}

// Helper function to send JSON error response
function sendJsonError(string $message, int $httpCode = 401): void {
    // Validate HTTP status code range
    if ($httpCode < 100 || $httpCode > 599) {
        $httpCode = 500; // Default to Internal Server Error for invalid codes
    }
    
    http_response_code($httpCode);
    header('Content-Type: application/json');
    // JSON encoding automatically escapes the message, preventing XSS
    echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_SLASHES);
    exit;
}

// Start session if not already started
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Check if user is logged in via main application session
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    if (isAjaxRequest()) {
        sendJsonError('Authentication required. Please log in.', 401);
    }
    // Redirect to main login page
    header('Location: ../login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Load database connection to check user roles
require_once __DIR__ . '/../config/database.php';

$userId = (int)$_SESSION['user_id'];
$isAdmin = false;
$isManager = false;

// Define manager role values for legacy role field checks
$managerRoles = ['manager', 'project_manager'];

// First check session-based user_type (primary method used by the application)
if (isset($_SESSION['user_type'])) {
    $userType = strtolower($_SESSION['user_type']);
    $isAdmin = $userType === 'admin';
    $isManager = $userType === 'manager';
}

// Also check legacy role field if exists
if (isset($_SESSION['role'])) {
    $lowerRole = strtolower($_SESSION['role']);
    if (!$isAdmin) {
        $isAdmin = $lowerRole === 'admin';
    }
    if (!$isManager) {
        $isManager = in_array($lowerRole, $managerRoles, true);
    }
}

// Additionally check user_roles table for role-based permissions
try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check user roles from user_roles table
    $stmt = $db->prepare('SELECT role_id FROM user_roles WHERE user_id = :user_id AND deleted_at IS NULL');
    $stmt->execute(['user_id' => $userId]);
    $userRoles = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    
    // Role IDs: 1 = Administrator, 2 = Manager
    // These checks supplement the session-based checks above
    if (!$isAdmin) {
        $isAdmin = in_array(1, $userRoles, true);
    }
    if (!$isManager) {
        $isManager = in_array(2, $userRoles, true);
    }
} catch (Exception $e) {
    error_log('Backup auth error: ' . $e->getMessage());
    // Session-based authentication checks have already been performed above.
    // The system gracefully degrades to session-only authentication when the database is unavailable.
}

// Require admin or manager role to access backup system
if (!$isAdmin && !$isManager) {
    if (isAjaxRequest()) {
        sendJsonError('Access denied. Admin or manager role required.', 403);
    }
    header('Location: ../dashboard.php');
    exit;
}

// Authentication successful - user can access backup system
?>
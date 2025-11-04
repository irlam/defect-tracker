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

// Start session if not already started
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Check if user is logged in via main application session
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    // Redirect to main login page
    header('Location: ../login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Load database connection to check user roles
require_once __DIR__ . '/../config/database.php';

$userId = (int)$_SESSION['user_id'];
$isAdmin = false;
$isManager = false;

// First check session-based user_type (primary method used by the application)
if (isset($_SESSION['user_type'])) {
    $userType = strtolower($_SESSION['user_type']);
    $isAdmin = $userType === 'admin';
    $isManager = $userType === 'manager';
}

// Also check legacy role field if exists
if (!$isAdmin && isset($_SESSION['role'])) {
    $isAdmin = strtolower($_SESSION['role']) === 'admin';
}
if (!$isManager && isset($_SESSION['role'])) {
    $isManager = strtolower($_SESSION['role']) === 'manager' || strtolower($_SESSION['role']) === 'project_manager';
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
    // Session-based checks already performed above, so we can continue
}

// Require admin or manager role to access backup system
if (!$isAdmin && !$isManager) {
    header('Location: ../dashboard.php');
    exit;
}

// Authentication successful - user can access backup system
?>
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

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check user roles from user_roles table
    $stmt = $db->prepare('SELECT role_id FROM user_roles WHERE user_id = :user_id AND deleted_at IS NULL');
    $stmt->execute(['user_id' => $userId]);
    $userRoles = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    
    // Role IDs: 1 = Administrator, 2 = Manager
    $isAdmin = in_array(1, $userRoles, true);
    $isManager = in_array(2, $userRoles, true);
    
    // Also check legacy role field if exists
    if (!$isAdmin && isset($_SESSION['role'])) {
        $isAdmin = strtolower($_SESSION['role']) === 'admin';
    }
    if (!$isManager && isset($_SESSION['user_type'])) {
        $isManager = strtolower($_SESSION['user_type']) === 'manager';
    }
} catch (Exception $e) {
    error_log('Backup auth error: ' . $e->getMessage());
    // Fall back to session-based role check
    if (isset($_SESSION['role'])) {
        $isAdmin = strtolower($_SESSION['role']) === 'admin';
    }
    if (isset($_SESSION['user_type'])) {
        $isManager = strtolower($_SESSION['user_type']) === 'manager';
    }
}

// Require admin or manager role to access backup system
if (!$isAdmin && !$isManager) {
    header('Location: ../dashboard.php');
    exit;
}

// Authentication successful - user can access backup system
?>
<?php
// includes/permissions.php
// Current Date and Time (UTC): 2025-01-16 19:19:45
// Current User: irlam

/**
 * Check if user has specific permission
 * @param string $permission Permission to check
 * @return bool True if user has permission, false otherwise
 */
function userHasPermission($permission) {
    // Make sure session is started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Check if user is logged in
    if (!isset($_SESSION['username'])) {
        return false;
    }

    // For debugging
    error_log("Checking permission: " . $permission . " for user: " . $_SESSION['username']);
    error_log("User role: " . ($_SESSION['user_role'] ?? 'not set'));

    // Define permission levels
    $permissions = [
        'admin' => [
            'view_contractor',
            'add_contractor',
            'edit_contractor',
            'delete_contractor',
            'view_defect',
            'add_defect',
            'edit_defect',
            'delete_defect',
            'manage_users',
            'view_audit_log'
        ],
        'manager' => [
            'view_contractor',
            'add_contractor',
            'edit_contractor',
            'view_defect',
            'add_defect',
            'edit_defect'
        ],
        'user' => [
            'view_contractor',
            'view_defect',
            'add_defect'
        ]
    ];

    // Get user's role from session
    $userRole = $_SESSION['user_role'] ?? 'user';

    // Check if user's role has the requested permission
    return isset($permissions[$userRole]) && in_array($permission, $permissions[$userRole]);
}

/**
 * Check if user has admin privileges
 * @return bool True if user is admin, false otherwise
 */
function isAdmin() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Check if user has manager privileges
 * @return bool True if user is manager or admin
 */
function isManager() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['manager', 'admin']);
}
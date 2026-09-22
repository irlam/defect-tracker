<?php
// includes/permissions.php
// Current Date and Time (UTC): 2025-02-03 13:20:59
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
    error_log("User role ID: " . ($_SESSION['role_id'] ?? 'not set'));

    // Define permission levels based on role_id
    $permissions = [
        1 => [ // admin
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
        2 => [ // manager
            'view_contractor',
            'add_contractor',
            'edit_contractor',
            'view_defect',
            'add_defect',
            'edit_defect'
        ],
        3 => [ // user
            'view_contractor',
            'view_defect',
            'add_defect'
        ]
    ];

    // Get user's role_id from session
    $roleId = $_SESSION['role_id'] ?? 3; // Default to 'user' if not set

    // For debugging
    error_log("Resolved role ID: " . $roleId);

    // Check if user's role has the requested permission
    $hasPermission = isset($permissions[$roleId]) && in_array($permission, $permissions[$roleId]);
    error_log("Permission check result: " . ($hasPermission ? 'granted' : 'denied'));

    return $hasPermission;
}

/**
 * Check if user has admin privileges
 * @return bool True if user is admin, false otherwise
 */
function isAdmin() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['role_id']) && $_SESSION['role_id'] === 1;
}

/**
 * Check if user has manager privileges
 * @return bool True if user is manager or admin
 */
function isManager() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['role_id']) && in_array($_SESSION['role_id'], [1, 2]);
}
?>
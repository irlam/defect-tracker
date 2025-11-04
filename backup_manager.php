<?php
/**
 * Backup Manager Redirect
 * 
 * This file redirects to the main backup manager interface.
 * The actual backup manager UI is located at /backups/index.php
 * 
 * Updated: 2025-11-04
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect to login if not logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Redirect to the backup manager interface
header("Location: /backups/index.php");
exit();
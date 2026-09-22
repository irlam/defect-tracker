<?php
// /includes/session.php

// Set error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include the SessionManager class
require_once __DIR__ . '/SessionManager.php';

// Create session manager instance
$sessionManager = new SessionManager();

// Set timezone
date_default_timezone_set('UTC');

// Check if user is authenticated
if (!$sessionManager->isAuthenticated()) {
    // Store the requested URL for redirect after login
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header('Location: login.php');
    exit();
}
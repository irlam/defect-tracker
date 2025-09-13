<?php
// api/delete_defect.php

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication check
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

require_once '../config/database.php';
require_once '../includes/functions.php';

// Check CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error_message'] = "CSRF token validation failed";
    header("Location: ../defects.php");
    exit();
}

// Get defect ID from POST
$defectId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$defectId) {
    $_SESSION['error_message'] = "Invalid defect ID";
    header("Location: ../defects.php");
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Soft delete defect
    $query = "UPDATE defects SET deleted_at = NOW() WHERE id = :id AND deleted_at IS NULL";
    $stmt = $db->prepare($query);
    $stmt->execute([':id' => $defectId]);

    if ($stmt->rowCount() > 0) {
        $_SESSION['success_message'] = "Defect deleted successfully";
    } else {
        $_SESSION['error_message'] = "Defect not found or already deleted";
    }

} catch (Exception $e) {
    error_log("Delete Defect Error: " . $e->getMessage());
    $_SESSION['error_message'] = "Error: " . $e->getMessage();
}

header("Location: ../defects.php");
exit();
?>
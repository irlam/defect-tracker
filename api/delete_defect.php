<?php
// api/delete_defect.php

error_reporting(E_ALL);
ini_set('display_errors', 0);
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

// Defect deletion is an administrative operation. Do not rely on whether the
// UI happened to render a delete button: enforce the permission here.
$isAdmin = strtolower((string)($_SESSION['user_type'] ?? '')) === 'admin';
if (!$isAdmin) {
    try {
        $authorizationDb = (new Database())->getConnection();
        $roleStatement = $authorizationDb->prepare(
            "SELECT 1
             FROM user_roles ur
             JOIN roles r ON r.id = ur.role_id
             WHERE ur.user_id = :user_id
               AND LOWER(r.name) = 'admin'
               AND ur.deleted_at IS NULL
             LIMIT 1"
        );
        $roleStatement->execute([':user_id' => (int)$_SESSION['user_id']]);
        $isAdmin = (bool)$roleStatement->fetchColumn();
    } catch (Throwable $authorizationError) {
        error_log('Delete defect authorization failed: ' . $authorizationError->getMessage());
        $isAdmin = false;
    }
}

if (!$isAdmin) {
    http_response_code(403);
    $_SESSION['error_message'] = 'You do not have permission to delete defects.';
    header('Location: ../defects.php');
    exit();
}

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
    $_SESSION['error_message'] = 'Unable to delete the defect.';
}

header("Location: ../defects.php");
exit();
?>

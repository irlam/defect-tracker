<?php
// accept_defect.php
// Current Date and Time (UTC): 2025-02-05 13:30:24
// Current User: irlam

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication check
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config/database.php';
require_once 'includes/functions.php';

$currentUser = $_SESSION['username'];
$currentUserId = (int)$_SESSION['user_id'];
$currentDateTime = date('Y-m-d H:i:s');

// Function to check if user has permission to accept defects
function canAcceptDefects($db, $userId) {
    $query = "SELECT r.name as role_name
              FROM users u
              JOIN user_roles ur ON u.id = ur.user_id
              JOIN roles r ON ur.role_id = r.id
              WHERE u.id = :user_id";
    
    $stmt = $db->prepare($query);
    $stmt->execute([':user_id' => $userId]);
    $roles = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    return in_array('admin', $roles) || in_array('manager', $roles);
}

try {
    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Validate CSRF token (add your CSRF protection here)
    // if (!validateCSRFToken($_POST['csrf_token'])) {
    //     throw new Exception('Invalid CSRF token');
    // }

    // Connect to database
    $database = new Database();
    $db = $database->getConnection();

    // Check permissions
    if (!canAcceptDefects($db, $currentUserId)) {
        throw new Exception('You do not have permission to accept defects');
    }
    
    // Validate required parameters
    if (!isset($_POST['defect_id']) || !is_numeric($_POST['defect_id'])) {
        throw new Exception('Valid defect ID is required');
    }
    
    $defectId = (int)$_POST['defect_id'];
    
    // Check if defect exists and can be accepted
    $checkQuery = "SELECT id, status FROM defects WHERE id = :defect_id";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->execute([':defect_id' => $defectId]);
    $defect = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$defect) {
        throw new Exception('Defect not found');
    }
    
    if ($defect['status'] !== 'assigned') {
        throw new Exception('Defect cannot be accepted in current status');
    }
    
    // Update defect status to accepted
    $updateQuery = "UPDATE defects SET 
                    status = 'in_progress',
                    updated_by = :updated_by,
                    updated_at = :updated_at
                    WHERE id = :defect_id";
    
    $updateStmt = $db->prepare($updateQuery);
    $result = $updateStmt->execute([
        ':defect_id' => $defectId,
        ':updated_by' => $currentUser,
        ':updated_at' => $currentDateTime
    ]);
    
    if (!$result) {
        throw new Exception('Failed to accept defect');
    }
    
    // Log the action
    $logQuery = "INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, created_at) 
                VALUES (:user_id, 'accept', 'defect', :entity_id, :details, :created_at)";
    
    $logStmt = $db->prepare($logQuery);
    $logStmt->execute([
        ':user_id' => $currentUserId,
        ':entity_id' => $defectId,
        ':details' => 'Defect accepted by ' . $currentUser,
        ':created_at' => $currentDateTime
    ]);
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Defect accepted successfully'
    ]);

} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
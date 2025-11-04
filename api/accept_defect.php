<?php
// accept_defect.php
// Current Date and Time (UTC): 2025-02-05 13:30:24
// Current User: irlam

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication check
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../config/database.php';

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
        throw new Exception('Invalid defect ID');
    }

    $defectId = (int)$_POST['defect_id'];
    $acceptanceComment = isset($_POST['acceptance_comment']) ? trim($_POST['acceptance_comment']) : '';

    // Check if defect exists and can be accepted
    $checkQuery = "SELECT id, status FROM defects WHERE id = :defect_id";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(':defect_id', $defectId);
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() === 0) {
        throw new Exception("Defect #{$defectId} not found");
    }
    
    $defect = $checkStmt->fetch(PDO::FETCH_ASSOC);
    $validStatusesForAcceptance = ['pending', 'completed', 'verified'];
    
    if (!in_array($defect['status'], $validStatusesForAcceptance)) {
        throw new Exception("Defect #{$defectId} cannot be accepted from current status: {$defect['status']}");
    }

    // Update the defect with acceptance information
    $query = "UPDATE defects 
              SET acceptance_comment = :acceptance_comment, 
                  accepted_by = :accepted_by, 
                  accepted_at = :accepted_at, 
                  status = 'accepted',
                  updated_at = :updated_at
              WHERE id = :defect_id";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':acceptance_comment', $acceptanceComment);
    $stmt->bindParam(':accepted_by', $currentUserId);
    $stmt->bindParam(':accepted_at', $currentDateTime);
    $stmt->bindParam(':updated_at', $currentDateTime);
    $stmt->bindParam(':defect_id', $defectId);

    if ($stmt->execute() && $stmt->rowCount() > 0) {
        // Log the action
        $logQuery = "INSERT INTO activity_logs (defect_id, user_id, action_type, details, created_at) 
                     VALUES (:defect_id, :user_id, 'accepted', :details, :created_at)";
        $logStmt = $db->prepare($logQuery);
        $details = "Defect accepted by {$currentUser}";
        $logStmt->execute([
            ':defect_id' => $defectId,
            ':user_id' => $currentUserId,
            ':details' => $details,
            ':created_at' => $currentDateTime
        ]);

        // Return success response
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => "Defect #{$defectId} has been accepted successfully.",
            'defect_id' => $defectId
        ]);
    } else {
        throw new Exception("Failed to update defect #{$defectId}");
    }
} catch (Exception $e) {
    error_log("Accept Defect API Error: " . $e->getMessage());
    error_log("User: {$currentUser}, User ID: {$currentUserId}");
    
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
?>
<?php
// api/delete-defect.php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_secure' => true,
        'cookie_samesite' => 'Lax'
    ]);
}

// Check authentication
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode(['error' => 'Unauthorized']));
}

// Verify CSRF token
if (!isset($_SERVER['HTTP_X_CSRF_TOKEN']) || $_SERVER['HTTP_X_CSRF_TOKEN'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    exit(json_encode(['error' => 'Invalid CSRF token']));
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Method not allowed']));
}

// Get JSON data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data || !isset($data['defect_id'])) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid request data']));
}

// Current timestamp and user
$currentDateTime = '2025-02-19 20:56:35';
$currentUser = 'irlam';
$userId = $_SESSION['user_id'];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check user permissions
    $permissionQuery = "SELECT r.name as role_name
                       FROM users u
                       JOIN user_roles ur ON u.id = ur.user_id
                       JOIN roles r ON ur.role_id = r.id
                       WHERE u.id = :user_id";
    
    $stmt = $db->prepare($permissionQuery);
    $stmt->execute([':user_id' => $userId]);
    $roles = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('admin', $roles)) {
        throw new Exception('Permission denied: Only administrators can delete defects');
    }

    // Start transaction
    $db->beginTransaction();

    // Get defect information for logging
    $getDefectQuery = "SELECT title, status FROM defects WHERE id = :id";
    $stmt = $db->prepare($getDefectQuery);
    $stmt->execute([':id' => $data['defect_id']]);
    $defect = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$defect) {
        throw new Exception('Defect not found');
    }

    // Soft delete the defect
    $updateQuery = "UPDATE defects 
                   SET deleted_at = :deleted_at,
                       deleted_by = :deleted_by,
                       updated_at = :updated_at,
                       updated_by = :updated_by
                   WHERE id = :id";
    
    $stmt = $db->prepare($updateQuery);
    $stmt->execute([
        ':deleted_at' => $currentDateTime,
        ':deleted_by' => $userId,
        ':updated_at' => $currentDateTime,
        ':updated_by' => $userId,
        ':id' => $data['defect_id']
    ]);

    // Log the deletion
    $logQuery = "INSERT INTO audit_log 
                (action, table_name, record_id, old_values, user_id, created_at)
                VALUES 
                (:action, 'defects', :record_id, :old_values, :user_id, :created_at)";
    
    $stmt = $db->prepare($logQuery);
    $stmt->execute([
        ':action' => 'DELETE',
        ':record_id' => $data['defect_id'],
        ':old_values' => json_encode($defect),
        ':user_id' => $userId,
        ':created_at' => $currentDateTime
    ]);

    // Commit transaction
    $db->commit();

    // Log to system log
    error_log("[$currentDateTime] User: $currentUser - Deleted defect ID: {$data['defect_id']} - Title: {$defect['title']}");

    // Return success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Defect deleted successfully',
        'defect_id' => $data['defect_id']
    ]);

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    error_log("[$currentDateTime] Error deleting defect: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error deleting defect: ' . $e->getMessage()
    ]);
}
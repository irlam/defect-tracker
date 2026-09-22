<?php
/**
 * API Endpoint: Send Push Notification
 * 
 * Allows programmatic sending of push notifications to users and/or contractors
 * Requires authentication and appropriate permissions
 */

// Error reporting and logging setup
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in API
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/api_error.log');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set headers
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized - Please log in']);
    exit;
}

// Include required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../push_notifications/notification_sender.php';

try {
    // Get POST data (support both JSON and form-data)
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        $input = json_decode(file_get_contents('php://input'), true);
    } else {
        $input = $_POST;
    }
    
    // Validate required fields
    $title = $input['title'] ?? '';
    $message = $input['message'] ?? '';
    $targetType = $input['target_type'] ?? 'all';
    
    if (empty($title) || empty($message)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Missing required fields: title and message are required'
        ]);
        exit;
    }
    
    // Optional fields
    $userId = !empty($input['user_id']) ? (int)$input['user_id'] : null;
    $contractorId = !empty($input['contractor_id']) ? (int)$input['contractor_id'] : null;
    $defectId = !empty($input['defect_id']) ? (int)$input['defect_id'] : null;
    
    // Validate target type
    $validTargetTypes = ['all', 'user', 'contractor', 'all_users', 'all_contractors'];
    if (!in_array($targetType, $validTargetTypes)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid target_type. Must be one of: ' . implode(', ', $validTargetTypes)
        ]);
        exit;
    }
    
    // Validate target-specific requirements
    if ($targetType === 'user' && empty($userId)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'user_id is required when target_type is "user"'
        ]);
        exit;
    }
    
    if ($targetType === 'contractor' && empty($contractorId)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'contractor_id is required when target_type is "contractor"'
        ]);
        exit;
    }
    
    // Send the notification
    $result = sendNotification($title, $message, $targetType, $userId, $contractorId, $defectId);
    
    // Return response
    if ($result['success']) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Notification sent successfully',
            'data' => [
                'recipients' => $result['recipients'],
                'failed' => $result['failed'] ?? 0,
                'total' => $result['total'],
                'log_id' => $result['log_id'] ?? null
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $result['error'] ?? 'Failed to send notification',
            'details' => $result['errors'] ?? []
        ]);
    }
    
} catch (Exception $e) {
    error_log("API Error in send_push_notification.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error occurred'
    ]);
}

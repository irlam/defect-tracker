<?php
/**
 * API Endpoint: Confirm Notification Delivery
 * 
 * This endpoint is called by mobile apps/PWA to confirm that a push notification
 * was successfully delivered and received by the device
 */

// Error reporting and logging setup
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/api_error.log');

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

// Include database configuration
require_once __DIR__ . '/../config/database.php';

try {
    // Get POST data (support both JSON and form-data)
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        $input = json_decode(file_get_contents('php://input'), true);
    } else {
        $input = $_POST;
    }
    
    // Validate required fields
    $recipientId = $input['recipient_id'] ?? null;
    $logId = $input['log_id'] ?? null;
    $userId = $input['user_id'] ?? null;
    $fcmToken = $input['fcm_token'] ?? null;
    
    if (empty($recipientId) && (empty($logId) || empty($userId))) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Missing required fields: either recipient_id or (log_id and user_id) must be provided'
        ]);
        exit;
    }
    
    // Connect to database
    $database = new Database();
    $db = $database->getConnection();
    
    // Update delivery status
    if (!empty($recipientId)) {
        // Update by recipient ID
        $stmt = $db->prepare(
            "UPDATE notification_recipients 
             SET delivery_status = 'delivered', delivered_at = NOW() 
             WHERE id = ?"
        );
        $stmt->execute([$recipientId]);
        $affectedRows = $stmt->rowCount();
    } else {
        // Update by log_id and user_id
        $stmt = $db->prepare(
            "UPDATE notification_recipients 
             SET delivery_status = 'delivered', delivered_at = NOW() 
             WHERE notification_log_id = ? AND user_id = ?"
        );
        $stmt->execute([$logId, $userId]);
        $affectedRows = $stmt->rowCount();
        
        // If no rows affected, it might be in the old format (no recipients table)
        if ($affectedRows === 0) {
            $stmt = $db->prepare(
                "UPDATE notification_log 
                 SET delivery_status = 'delivered', delivery_confirmed_at = NOW() 
                 WHERE id = ? AND user_id = ?"
            );
            $stmt->execute([$logId, $userId]);
            $affectedRows = $stmt->rowCount();
        }
    }
    
    // Check if we updated the main log to 'delivered' if all recipients are delivered
    if (!empty($logId)) {
        $stmt = $db->prepare(
            "SELECT COUNT(*) as total, 
                    SUM(CASE WHEN delivery_status = 'delivered' THEN 1 ELSE 0 END) as delivered
             FROM notification_recipients 
             WHERE notification_log_id = ?"
        );
        $stmt->execute([$logId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($stats && $stats['total'] > 0 && $stats['total'] == $stats['delivered']) {
            // All recipients delivered, update main log
            $stmt = $db->prepare(
                "UPDATE notification_log 
                 SET delivery_status = 'delivered', delivery_confirmed_at = NOW() 
                 WHERE id = ?"
            );
            $stmt->execute([$logId]);
        }
    }
    
    // Log the confirmation
    error_log("Notification delivery confirmed - recipient_id: $recipientId, log_id: $logId, user_id: $userId");
    
    // Return success
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Delivery confirmation recorded',
        'updated' => $affectedRows > 0
    ]);
    
} catch (PDOException $e) {
    error_log("Database Error in confirm_notification_delivery.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred'
    ]);
} catch (Exception $e) {
    error_log("API Error in confirm_notification_delivery.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error occurred'
    ]);
}

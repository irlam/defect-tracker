<?php
// api/update_fcm_token.php
// file to receive tokens from the Android app

// Error reporting and logging setup
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in API
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/api_error.log');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Allow CORS
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: POST');

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

// Include database configuration
require_once __DIR__ . '/../config/database.php';

// Get POST data
$user_id = $_POST['user_id'] ?? null;
$fcm_token = $_POST['fcm_token'] ?? null;
$platform = $_POST['platform'] ?? 'unknown'; // pwa, ios, android, web

// Log the received data
error_log("Received FCM token update for user_id: $user_id, token: $fcm_token, platform: $platform");

try {
    // Validate input
    if (empty($user_id) || empty($fcm_token)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required parameters']);
        exit;
    }
    
    // Validate platform
    $validPlatforms = ['pwa', 'ios', 'android', 'web', 'unknown'];
    if (!in_array($platform, $validPlatforms)) {
        $platform = 'unknown';
    }
    
    // Connect to database
    $database = new Database();
    $db = $database->getConnection();
    
    // Update user's FCM token and platform
    $stmt = $db->prepare("UPDATE users SET fcm_token = :token, device_platform = :platform WHERE id = :id");
    $stmt->bindParam(':token', $fcm_token);
    $stmt->bindParam(':platform', $platform);
    $stmt->bindParam(':id', $user_id);
    
    if ($stmt->execute()) {
        // Log success
        error_log("FCM token and platform updated successfully for user_id: $user_id (platform: $platform)");
        
        // Return success
        http_response_code(200);
        echo json_encode([
            'success' => true, 
            'message' => 'FCM token and platform updated successfully',
            'platform' => $platform
        ]);
    } else {
        error_log("Failed to update FCM token for user_id: $user_id");
        http_response_code(500);
        echo json_encode(['error' => 'Database update failed']);
    }
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error occurred']);
}
?>
<?php
// api/log-image-error.php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

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

if (!$data) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid JSON data']));
}

// Validate required fields
$requiredFields = ['timestamp', 'user', 'path', 'type'];
foreach ($requiredFields as $field) {
    if (!isset($data[$field])) {
        http_response_code(400);
        exit(json_encode(['error' => "Missing required field: $field"]));
    }
}

// Current timestamp and user for logging
$currentDateTime = '2025-02-19 20:56:35';
$currentUser = 'irlam';

// Log the error
$logEntry = [
    'timestamp' => $currentDateTime,
    'user' => $currentUser,
    'original_timestamp' => $data['timestamp'],
    'reported_user' => $data['user'],
    'path' => $data['path'],
    'type' => $data['type'],
    'server_path' => $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($data['path'], '/'),
    'ip_address' => $_SERVER['REMOTE_ADDR']
];

error_log('[IMAGE_ERROR] ' . json_encode($logEntry));

// Write to a separate image errors log file
$imageErrorLog = __DIR__ . '/../logs/image_errors.log';
file_put_contents(
    $imageErrorLog, 
    date('Y-m-d H:i:s') . ' | ' . json_encode($logEntry) . "\n", 
    FILE_APPEND
);

// Return success response
http_response_code(200);
echo json_encode([
    'success' => true,
    'message' => 'Error logged successfully',
    'log_id' => uniqid('IMG_ERR_')
]);
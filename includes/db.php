<?php
// db.php
// Current Date and Time (UTC): 2025-01-16 19:44:58
// Current User: irlam

require_once __DIR__ . '/../config/database.php';

$db = (new Database())->getConnection();
if (!$db) {
    http_response_code(503);
    die('Database connection unavailable.');
}

// Helper function to log system messages
function logSystem($message, $username = null) {
    global $db;
    try {
        $stmt = $db->prepare("
            INSERT INTO system_logs (message, created_by, created_at) 
            VALUES (:message, :created_by, UTC_TIMESTAMP())
        ");
        $stmt->execute([
            'message' => $message,
            'created_by' => $username
        ]);
    } catch (Exception $e) {
        error_log("Logging error: " . $e->getMessage());
    }
}

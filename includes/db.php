<?php
// db.php
// Current Date and Time (UTC): 2025-01-16 19:44:58
// Current User: irlam

try {
    $host = 'localhost';
    $dbname = 'dvntrack_defect-manager';
    $username = 'dvntrack_defect-manager';
    $password = '^cHMcJseC$%S';
    
    $db = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch(PDOException $e) {
    error_log("Database Connection Error: " . $e->getMessage());
    die("Connection failed: " . $e->getMessage());
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
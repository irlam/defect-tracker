<?php
require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../classes/BackupManager.php';
require_once '../classes/Logger.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $backupManager = new BackupManager($db);
    $logger = new Logger($db, 'irlam', '2025-01-14 21:47:42');

    $backupFile = $backupManager->createBackup();

    $logger->logActivity(
        'backup_created',
        ['file' => $backupFile],
        $_SESSION['user_id']
    );

    echo json_encode([
        'success' => true,
        'message' => 'Backup created successfully',
        'file' => $backupFile
    ]);

} catch (Exception $e) {
    error_log("Error creating backup: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error creating backup'
    ]);
}
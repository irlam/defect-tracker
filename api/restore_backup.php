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
    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['filename'])) {
        throw new Exception('Filename not provided');
    }

    $database = new Database();
    $db = $database->getConnection();
    $backupManager = new BackupManager($db);
    $logger = new Logger($db, 'irlam', '2025-01-14 21:47:42');

    $backupManager->restoreBackup($data['filename']);

    $logger->logActivity(
        'backup_restored',
        ['file' => $data['filename']],
        $_SESSION['user_id']
    );

    echo json_encode([
        'success' => true,
        'message' => 'Backup restored successfully'
    ]);

} catch (Exception $e) {
    error_log("Error restoring backup: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error restoring backup'
    ]);
}
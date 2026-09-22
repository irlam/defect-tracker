<?php
require_once '../config/database.php';
require_once '../includes/session.php';
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

    $backupPath = '../backups/' . basename($data['filename']);
    if (!file_exists($backupPath)) {
        throw new Exception('Backup file not found');
    }

    if (unlink($backupPath)) {
        $database = new Database();
        $db = $database->getConnection();
        $logger = new Logger($db, 'irlam', '2025-01-14 21:47:42');

        $logger->logActivity(
            'backup_deleted',
            ['file' => $data['filename']],
            $_SESSION['user_id']
        );

        echo json_encode([
            'success' => true,
            'message' => 'Backup deleted successfully'
        ]);
    } else {
        throw new Exception('Failed to delete backup file');
    }

} catch (Exception $e) {
    error_log("Error deleting backup: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error deleting backup'
    ]);
}
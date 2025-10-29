<?php
/**
 * McGoff Backup Manager - AJAX Backup Handler
 * 
 * This file handles the AJAX requests for backup creation and progress tracking.
 * It provides real-time progress updates during the backup process and handles
 * the creation of backups in the background.
 * 
 * Created: 2025-02-26 19:27:55
 * Author: irlam
 */
session_start();
require_once 'auth.php';
require_once 'config.php';
require_once 'functions.php';
require_once 'backup-manager.php';

// Set content type to JSON
header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Verify CSRF token
if (!isset($_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'CSRF token missing']);
    exit;
}

try {
    verify_csrf_token($_POST['csrf_token']);

    // Initialize backup manager
    $backupManager = new BackupManager();
    
    // Initialize or get the session progress tracker
    if (!isset($_SESSION['backup_progress'])) {
        $_SESSION['backup_progress'] = [
            'status' => 'starting',
            'progress' => 0,
            'message' => 'Starting backup...',
            'current_file' => '',
            'start_time' => time(),
        ];
    }
    
    // Get the action
    $action = $_POST['action'] ?? '';
    
    if ($action === 'start') {
        // Start a new backup process
        $_SESSION['backup_progress'] = [
            'status' => 'preparing',
            'progress' => 5,
            'message' => 'Preparing backup...',
            'current_file' => '',
            'start_time' => time(),
        ];
        
        // Return initial status
        echo json_encode([
            'success' => true,
            'status' => $_SESSION['backup_progress']
        ]);
        
    } elseif ($action === 'run') {
        // Run the actual backup process
        // This would normally be a long-running process, so we'd break it into stages
        
        // For this example, we'll just create the backup directly
        // In a real implementation, you'd want to break this into smaller steps
        $result = $backupManager->createFullBackup();
        
        // Update session with final status
        $_SESSION['backup_progress'] = [
            'status' => $result['success'] ? 'complete' : 'failed',
            'progress' => $result['success'] ? 100 : 0,
            'message' => $result['message'],
            'current_file' => '',
            'end_time' => time(),
            'filename' => $result['success'] ? $result['file'] : '',
        ];
        
        // Return final status
        echo json_encode([
            'success' => $result['success'],
            'status' => $_SESSION['backup_progress'],
            'message' => $result['message']
        ]);
        
    } elseif ($action === 'status') {
        // Try to read from the progress file first (more reliable for long-running operations)
        $progressFile = __DIR__ . '/tmp/backup_progress.json';
        
        if (file_exists($progressFile)) {
            $fileProgress = json_decode(file_get_contents($progressFile), true);
            // Only use if the file is recent (last 60 seconds)
            if (isset($fileProgress['last_updated']) && (time() - $fileProgress['last_updated']) < 60) {
                echo json_encode([
                    'success' => true,
                    'status' => $fileProgress
                ]);
                exit;
            }
        }
        
        // Fall back to session data if no file or file is outdated
        echo json_encode([
            'success' => true,
            'status' => $_SESSION['backup_progress'] ?? ['status' => 'unknown', 'progress' => 0]
        ]);
        
    } elseif ($action === 'reset') {
        // Reset progress tracker
        unset($_SESSION['backup_progress']);
        echo json_encode(['success' => true, 'message' => 'Progress reset']);
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
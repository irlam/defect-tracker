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
    
    // Authentication and CSRF checks are complete. Release the PHP session
    // lock before long-running work or status polling so parallel AJAX status
    // requests do not queue behind the backup process.
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

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
        $tmpDir = __DIR__ . '/tmp';
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        // Prevent accidental concurrent full backups from exhausting the
        // hosting account's FastCGI process pool.
        $lockHandle = fopen($tmpDir . '/backup.lock', 'c');
        if (!$lockHandle || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
            if ($lockHandle) fclose($lockHandle);
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'message' => 'A backup is already running. Please wait for it to finish.'
            ]);
            exit;
        }

        try {
            $result = $backupManager->createFullBackup();

            $finalStatus = [
                'status' => $result['success'] ? 'complete' : 'failed',
                'progress' => $result['success'] ? 100 : 0,
                'message' => $result['message'],
                'current_file' => '',
                'end_time' => time(),
                'filename' => $result['success'] ? $result['file'] : '',
            ];

            file_put_contents(
                $tmpDir . '/backup_progress.json',
                json_encode($finalStatus + ['last_updated' => time()]),
                LOCK_EX
            );

            echo json_encode([
                'success' => $result['success'],
                'status' => $finalStatus,
                'message' => $result['message']
            ]);
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
        
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
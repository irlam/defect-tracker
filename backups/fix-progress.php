<?php
/**
 * McGoff Backup Manager - Progress Bar Fix
 * 
 * This file fixes issues with the backup progress bar not showing updates
 * during the backup process.
 * 
 * Created: 2025-02-26 21:45:26
 * Author: irlam
 */

// Define paths
$backupManagerPath = __DIR__ . '/backup-manager.php';
$createBackupPath = __DIR__ . '/create-backup.php';

// Backup original files
if (file_exists($backupManagerPath)) {
    copy($backupManagerPath, $backupManagerPath . '.bak');
}

if (file_exists($createBackupPath)) {
    copy($createBackupPath, $createBackupPath . '.bak');
}

// Fix for backup-manager.php
$backupManagerContent = file_get_contents($backupManagerPath);

// Replace the updateProgress function with an improved version
$oldUpdateProgressFunc = <<<'EOD'
    /**
     * Update backup progress
     */
    private function updateProgress($status, $progress, $message, $currentFile = '') {
        if (isset($_SESSION['backup_progress'])) {
            $_SESSION['backup_progress'] = [
                'status' => $status,
                'progress' => $progress,
                'message' => $message,
                'current_file' => $currentFile,
                'last_updated' => time()
            ];
            // Force session data to be written
            session_write_close();
            // Reopen the session for next write
            session_start();
        }
    }
EOD;

$newUpdateProgressFunc = <<<'EOD'
    /**
     * Update backup progress
     */
    private function updateProgress($status, $progress, $message, $currentFile = '') {
        if (isset($_SESSION['backup_progress'])) {
            $_SESSION['backup_progress'] = [
                'status' => $status,
                'progress' => $progress,
                'message' => $message,
                'current_file' => $currentFile,
                'last_updated' => time()
            ];
            
            // Force session data to be written
            session_write_close();
            
            // Create or update a progress file for direct access
            $progressFile = __DIR__ . '/tmp/backup_progress.json';
            $progressDir = dirname($progressFile);
            
            if (!file_exists($progressDir)) {
                mkdir($progressDir, 0755, true);
            }
            
            file_put_contents($progressFile, json_encode($_SESSION['backup_progress']));
            
            // Reopen the session for next write
            session_start();
            
            // Sleep briefly to allow background processing and reduce CPU usage
            usleep(10000); // 10ms
        }
    }
EOD;

$backupManagerContent = str_replace($oldUpdateProgressFunc, $newUpdateProgressFunc, $backupManagerContent);

// Also fix the addFilesToZip method to update progress more frequently
$oldProgress = 'if ($fileCount % 50 === 0) {';
$newProgress = 'if ($fileCount % 20 === 0) {';
$backupManagerContent = str_replace($oldProgress, $newProgress, $backupManagerContent);

// Save the modified file
file_put_contents($backupManagerPath, $backupManagerContent);

// Fix for create-backup.php
$createBackupContent = file_get_contents($createBackupPath);

// Modify the status check to use the file if available
$oldStatusSection = <<<'EOD'
    } elseif ($action === 'status') {
        // Return current backup status
        echo json_encode([
            'success' => true,
            'status' => $_SESSION['backup_progress'] ?? ['status' => 'unknown', 'progress' => 0]
        ]);
EOD;

$newStatusSection = <<<'EOD'
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
EOD;

$createBackupContent = str_replace($oldStatusSection, $newStatusSection, $createBackupContent);

// Save the modified file
file_put_contents($createBackupPath, $createBackupContent);

// Update index.php for faster status checks
$indexPath = __DIR__ . '/index.php';
if (file_exists($indexPath)) {
    copy($indexPath, $indexPath . '.bak');
    $indexContent = file_get_contents($indexPath);
    
    // Make the status check frequency faster (1000ms → 500ms)
    $indexContent = str_replace(
        'statusCheckInterval = setInterval(checkBackupStatus, 1000);', 
        'statusCheckInterval = setInterval(checkBackupStatus, 500);', 
        $indexContent
    );
    
    file_put_contents($indexPath, $indexContent);
}

// Create the tmp directory if it doesn't exist
if (!file_exists(__DIR__ . '/tmp')) {
    mkdir(__DIR__ . '/tmp', 0755, true);
}

// Output success message
echo "✅ Progress bar fix applied successfully. Please test the backup system now.\n";
echo "The original files have been backed up as .bak files in case you need to restore them.\n";
?>
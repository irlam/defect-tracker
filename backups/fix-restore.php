<?php
/**
 * McGoff Backup Manager - Restore Function Fix
 * 
 * This file adds the missing restoreFile() method to the BackupManager class.
 * 
 * Current Date: 2025-02-28 12:50:33
 * Author: irlam
 */

// Define path to the backup manager
$backupManagerPath = __DIR__ . '/backup-manager.php';

// Check if the file exists
if (!file_exists($backupManagerPath)) {
    die("Error: backup-manager.php file not found!");
}

// Backup the original file
copy($backupManagerPath, $backupManagerPath . '.bak.' . date('Ymd-His'));

// Get the content of the file
$content = file_get_contents($backupManagerPath);

// Check if the class definition is present
if (strpos($content, 'class BackupManager') === false) {
    die("Error: BackupManager class not found in the file!");
}

// Check if the method already exists (just in case)
if (strpos($content, 'function restoreFile') !== false) {
    echo "The restoreFile() method already exists. No changes made.";
    exit;
}

// Find the position to insert the new method (before the last closing brace of the class)
$lastBracePos = strrpos($content, '}');
if ($lastBracePos === false) {
    die("Error: Could not find where to insert the method!");
}

// Define the new restoreFile method
$restoreFileMethod = <<<'EOD'

    /**
     * Restore a single file from a backup
     *
     * @param string $backupFile Path to the backup file
     * @param string $fileToRestore Path of the file within the backup to restore
     * @param string $destination Destination path (default is original location)
     * @return array Success status and message
     */
    public function restoreFile($backupFile, $fileToRestore, $destination = '') {
        // Make sure the backup file exists
        if (!file_exists($backupFile)) {
            return ['success' => false, 'message' => 'Backup file not found'];
        }
        
        // Create a temporary directory for extraction
        $tempDir = sys_get_temp_dir() . '/backup_restore_' . time();
        if (!mkdir($tempDir, 0755, true)) {
            return ['success' => false, 'message' => 'Failed to create temporary directory'];
        }
        
        try {
            // Open the ZIP file
            $zip = new ZipArchive();
            if ($zip->open($backupFile) !== true) {
                $this->cleanup($tempDir);
                return ['success' => false, 'message' => 'Failed to open backup archive'];
            }
            
            // Check if the file exists in the backup
            if ($zip->locateName($fileToRestore) === false) {
                $zip->close();
                $this->cleanup($tempDir);
                return ['success' => false, 'message' => 'File not found in the backup'];
            }
            
            // Extract the file to the temporary directory
            $zip->extractTo($tempDir, $fileToRestore);
            $zip->close();
            
            // Determine the destination path
            if (empty($destination)) {
                // Default to restoring to the original location
                $destination = DOCROOT . '/' . $fileToRestore;
            }
            
            // Create the destination directory if it doesn't exist
            $destDir = dirname($destination);
            if (!file_exists($destDir)) {
                if (!mkdir($destDir, 0755, true)) {
                    $this->cleanup($tempDir);
                    return ['success' => false, 'message' => 'Failed to create destination directory'];
                }
            }
            
            // Move the file to its destination
            $extractedFile = $tempDir . '/' . $fileToRestore;
            if (!rename($extractedFile, $destination)) {
                $this->cleanup($tempDir);
                return ['success' => false, 'message' => 'Failed to move restored file to destination'];
            }
            
            // Cleanup and return success
            $this->cleanup($tempDir);
            return [
                'success' => true, 
                'message' => 'File restored successfully',
                'destination' => $destination
            ];
            
        } catch (Exception $e) {
            $this->cleanup($tempDir);
            return ['success' => false, 'message' => 'Error during file restoration: ' . $e->getMessage()];
        }
    }
    
    /**
     * Clean up temporary files and directories
     *
     * @param string $dir Directory to remove
     */
    private function cleanup($dir) {
        if (!file_exists($dir)) {
            return;
        }
        
        // Simple recursive directory removal
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->cleanup($path) : unlink($path);
        }
        
        return rmdir($dir);
    }
EOD;

// Insert the method before the last closing brace
$newContent = substr_replace($content, $restoreFileMethod, $lastBracePos, 0);

// Save the updated file
if (file_put_contents($backupManagerPath, $newContent)) {
    echo "✅ Success! The restoreFile() method has been added to the BackupManager class.<br>";
    echo "The original file has been backed up as: <code>backup-manager.php.bak." . date('Ymd-His') . "</code><br>";
    echo "You can now try restoring files again.";
} else {
    echo "❌ Failed to update the file. Please check write permissions.";
}
?>
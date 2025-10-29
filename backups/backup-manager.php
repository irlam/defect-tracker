<?php
/**
 * McGoff Backup Manager - Core Backup Class
 * 
 * This file contains the main BackupManager class that handles all backup operations:
 * - Creating backups of files and databases
 * - Restoring individual files
 * - Restoring databases or full backups
 * - Tracking progress of backup operations
 * 
 * Created: 2025-02-26 19:27:55
 * Author: irlam
 */
require_once 'config.php';
require_once 'functions.php';

class BackupManager {
    private $excludePaths;
    
    public function __construct() {
        $this->excludePaths = unserialize(EXCLUDE_PATHS);
    }
    
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
    
    /**
     * Create a full backup (files + database)
     */
    public function createFullBackup() {
        $timestamp = date('d-m-Y_H-i-s'); // ISO format for filenames
        $backupFileName = BACKUP_NAME_PREFIX . '-' . $timestamp . '.zip';
        $backupFilePath = BACKUP_DIR . '/' . $backupFileName;
        
        // Create a temporary directory for the database dump
        $tempDir = BACKUP_DIR . '/temp_' . $timestamp;
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        
        try {
            // Update progress - Database backup starting
            $this->updateProgress('database', 10, 'Backing up database...', 'Database');
            
            // Backup database
            $dbBackupResult = $this->backupDatabase($tempDir);
            
            if (!$dbBackupResult['success']) {
                throw new Exception("Database backup failed: " . $dbBackupResult['message']);
            }
            
            $dbBackupFile = $dbBackupResult['file'];
            
            // Update progress - Creating ZIP archive
            $this->updateProgress('zip', 30, 'Creating ZIP archive...', '');
            
            // Create ZIP archive
            $zip = new ZipArchive();
            if ($zip->open($backupFilePath, ZipArchive::CREATE) !== TRUE) {
                throw new Exception("Cannot create ZIP archive");
            }
            
            // Update progress - Adding files
            $this->updateProgress('files', 40, 'Adding files to archive...', '');
            
            // Add files to the ZIP
            $this->addFilesToZip($zip, WEBSITE_ROOT, '');
            
            // Update progress - Adding database
            $this->updateProgress('files', 90, 'Adding database to archive...', 'Database dump');
            
            // Add the database dump
            if ($dbBackupFile) {
                $zip->addFile($dbBackupFile, 'database/' . basename($dbBackupFile));
            }
            
            $zip->close();
            
            // Update progress - Cleaning up
            $this->updateProgress('cleanup', 95, 'Cleaning up temporary files...', '');
            
            // Clean up temporary files and old backups
            $this->cleanupTempFiles($tempDir, $dbBackupFile);
            clean_old_backups();
            
            // Update progress - Complete
            $this->updateProgress('complete', 100, "Backup completed successfully: $backupFileName", '');
            
            return array(
                'success' => true,
                'message' => "Backup created successfully: $backupFileName",
                'file' => $backupFileName
            );
            
        } catch (Exception $e) {
            // Update progress - Failed
            $this->updateProgress('failed', 0, 'Backup failed: ' . $e->getMessage(), '');
            
            // Clean up in case of error
            $this->cleanupTempFiles($tempDir, isset($dbBackupFile) ? $dbBackupFile : null);
            if (file_exists($backupFilePath)) {
                unlink($backupFilePath);
            }
            
            return array(
                'success' => false,
                'message' => 'Backup failed: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Backup the database
     */
    private function backupDatabase($tempDir) {
        $timestamp = date('d-m-Y_H-i-s');
        $dbFile = $tempDir . '/database_' . $timestamp . '.sql';
        
        try {
            // Since we know mysqldump is available and working (from diagnostics),
            // use it directly as the primary method
            $command = sprintf(
                '%s --host=%s --user=%s --password=%s %s > %s 2>&1',
                MYSQLDUMP_PATH,
                escapeshellarg(DB_HOST),
                escapeshellarg(DB_USER),
                escapeshellarg(DB_PASS),
                escapeshellarg(DB_NAME),
                escapeshellarg($dbFile)
            );
            
            // Execute the command and capture any output
            exec($command, $output, $returnVar);
            
            if ($returnVar !== 0) {
                throw new Exception("mysqldump failed: " . implode("\n", $output));
            }
            
            return array(
                'success' => true,
                'message' => 'Database backup created using mysqldump',
                'file' => $dbFile
            );
            
        } catch (Exception $e) {
            // Fallback to PHP-based backup method
            try {
                // Update progress - Using fallback method
                $this->updateProgress('database', 15, 'Using PHP fallback method for database backup...', 'Database');
                
                // Use mysqli as fallback
                $mysqli = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
                
                if (!$mysqli) {
                    throw new Exception("Failed to connect to database: " . mysqli_connect_error());
                }
                
                // Get all tables
                $tables = array();
                $result = mysqli_query($mysqli, "SHOW TABLES");
                while ($row = mysqli_fetch_row($result)) {
                    $tables[] = $row[0];
                }
                
                if (empty($tables)) {
                    throw new Exception("No tables found in database");
                }
                
                // Start output buffering to capture the SQL commands
                ob_start();
                
                // Output database header
                echo "-- McGoff Website Backup\n";
                echo "-- Generation Time: " . date("d-m-Y H:i:s") . "\n";
                echo "-- Database: `" . DB_NAME . "`\n";
                echo "-- Generated by: " . CURRENT_USER . "\n";
                echo "-- --------------------------------------------------------\n\n";
                
                echo "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
                echo "SET time_zone = \"+00:00\";\n\n";
                
                // Export each table
                $tableCount = count($tables);
                $currentTable = 0;
                
                foreach ($tables as $table) {
                    $currentTable++;
                    $progressPercent = 15 + ($currentTable / $tableCount * 10); // Progress from 15%-25%
                    $this->updateProgress('database', $progressPercent, "Backing up table: $table", "Table: $table");
                    
                    // Table structure
                    echo "-- Table structure for table `$table`\n";
                    echo "DROP TABLE IF EXISTS `$table`;\n";
                    
                    $result = mysqli_query($mysqli, "SHOW CREATE TABLE `$table`");
                    $row = mysqli_fetch_row($result);
                    echo $row[1] . ";\n\n";
                    
                    // Table data
                    $result = mysqli_query($mysqli, "SELECT * FROM `$table`");
                    $num_fields = mysqli_num_fields($result);
                    $num_rows = mysqli_num_rows($result);
                    
                    if ($num_rows > 0) {
                        echo "-- Dumping data for table `$table`\n";
                        echo "INSERT INTO `$table` VALUES\n";
                        
                        $count = 0;
                        while ($row = mysqli_fetch_row($result)) {
                            $count++;
                            echo "(";
                            
                            for ($i = 0; $i < $num_fields; $i++) {
                                if (is_null($row[$i])) {
                                    echo "NULL";
                                } elseif (is_numeric($row[$i])) {
                                    echo $row[$i];
                                } else {
                                    echo "'" . mysqli_real_escape_string($mysqli, $row[$i]) . "'";
                                }
                                
                                if ($i < ($num_fields - 1)) {
                                    echo ", ";
                                }
                            }
                            
                            if ($count == $num_rows) {
                                echo ");\n\n";
                            } else {
                                echo "),\n";
                            }
                        }
                    }
                }
                
                // Get the buffered content
                $sql = ob_get_clean();
                
                // Save the SQL dump to file
                if (file_put_contents($dbFile, $sql) === false) {
                    throw new Exception("Failed to write to SQL dump file");
                }
                
                mysqli_close($mysqli);
                
                return array(
                    'success' => true,
                    'message' => 'Database backup created using PHP mysqli',
                    'file' => $dbFile
                );
            } catch (Exception $fallbackError) {
                // Log both errors for debugging
                error_log('mysqldump error: ' . $e->getMessage());
                error_log('mysqli fallback error: ' . $fallbackError->getMessage());
                
                return array(
                    'success' => false,
                    'message' => 'Both backup methods failed. Original error: ' . $e->getMessage() . 
                                '. Fallback error: ' . $fallbackError->getMessage(),
                    'file' => null
                );
            }
        }
    }
    
    /**
     * Add files recursively to ZIP archive
     */
    private function addFilesToZip($zip, $rootPath, $relativePath) {
        static $fileCount = 0;
        static $totalFiles = 0;
        
        if ($totalFiles === 0) {
            // First call, estimate total files
            $totalFiles = $this->estimateTotalFiles($rootPath);
        }
        
        $handle = opendir($rootPath . '/' . $relativePath);
        
        while (false !== ($entry = readdir($handle))) {
            if ($entry == '.' || $entry == '..') {
                continue;
            }
            
            $filePath = $rootPath . '/' . $relativePath . '/' . $entry;
            $filePathInZip = $relativePath ? $relativePath . '/' . $entry : $entry;
            
            // Skip excluded paths
            $skipFile = false;
            foreach ($this->excludePaths as $excludePath) {
                if (strpos($filePath, $excludePath) === 0) {
                    $skipFile = true;
                    break;
                }
            }
            
            if ($skipFile) {
                continue;
            }
            
            if (is_dir($filePath)) {
                // Create empty directory in ZIP
                $zip->addEmptyDir($filePathInZip);
                // Add files in this directory
                $this->addFilesToZip($zip, $rootPath, $filePathInZip);
            } else {
                // Add file to ZIP
                $zip->addFile($filePath, $filePathInZip);
                $fileCount++;
                
                // Update progress every few files
                if ($fileCount % 20 === 0) {
                    $progress = 40 + ($fileCount / $totalFiles * 50); // Progress from 40%-90%
                    $this->updateProgress('files', $progress, 'Adding files to archive...', $filePathInZip);
                }
            }
        }
        
        closedir($handle);
    }
    
    /**
     * Estimate total number of files to be backed up
     */
    private function estimateTotalFiles($rootPath) {
        $count = 0;
        $stack = array($rootPath);
        
        while (!empty($stack)) {
            $currentPath = array_pop($stack);
            
            // Skip excluded paths
            $skipDir = false;
            foreach ($this->excludePaths as $excludePath) {
                if (strpos($currentPath, $excludePath) === 0) {
                    $skipDir = true;
                    break;
                }
            }
            
            if ($skipDir) {
                continue;
            }
            
            $handle = opendir($currentPath);
            
            if (!$handle) {
                continue;
            }
            
            while (false !== ($entry = readdir($handle))) {
                if ($entry == '.' || $entry == '..') {
                    continue;
                }
                
                $path = $currentPath . '/' . $entry;
                
                if (is_dir($path)) {
                    $stack[] = $path;
                } else {
                    $count++;
                }
            }
            
            closedir($handle);
        }
        
        return max(1, $count); // Avoid division by zero
    }
    
    /**
     * Clean up temporary files
     */
    private function cleanupTempFiles($tempDir, $dbFile = null) {
        if ($dbFile && file_exists($dbFile)) {
            unlink($dbFile);
        }
        
        if (file_exists($tempDir)) {
            $files = scandir($tempDir);
            foreach ($files as $file) {
                if ($file != '.' && $file != '..') {
                    unlink($tempDir . '/' . $file);
                }
            }
            rmdir($tempDir);
        }
    }
    
    // Rest of the class methods remain the same...

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
    }}
?>
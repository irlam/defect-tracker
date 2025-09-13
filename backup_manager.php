<?php
// backup_manager.php
// Error reporting and logging setup
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');
class BackupManager {
    private $db;
    private $backupPath;
    private $logger;

    public function __construct($db, $backupPath = 'backups/') {
        $this->db = $db;
        $this->backupPath = $backupPath;
        $this->logger = new Logger($db);
    }

    public function createBackup() {
        $timestamp = date('Y-m-d_H-i-s');
        $filename = $this->backupPath . "backup_{$timestamp}.sql";

        // Create backup directory if it doesn't exist
        if (!is_dir($this->backupPath)) {
            mkdir($this->backupPath, 0755, true);
        }

        // Get database credentials from configuration
        $config = include('config/database.php');
        
        // Execute mysqldump command
        $command = sprintf(
            'mysqldump --host=%s --user=%s --password=%s %s > %s',
            escapeshellarg($config['host']),
            escapeshellarg($config['username']),
            escapeshellarg($config['password']),
            escapeshellarg($config['database']),
            escapeshellarg($filename)
        );

        exec($command, $output, $returnVar);

        if ($returnVar === 0) {
            $this->logger->logActivity(
                'backup_created',
                ['filename' => $filename],
                $this->currentUser
            );
            return $filename;
        }

        throw new Exception('Backup creation failed');
    }

    public function restoreBackup($backupFile) {
        if (!file_exists($backupFile)) {
            throw new Exception('Backup file not found');
        }

        $config = include('config/database.php');
        
        $command = sprintf(
            'mysql --host=%s --user=%s --password=%s %s < %s',
            escapeshellarg($config['host']),
            escapeshellarg($config['username']),
            escapeshellarg($config['password']),
            escapeshellarg($config['database']),
            escapeshellarg($backupFile)
        );

        exec($command, $output, $returnVar);

        if ($returnVar === 0) {
            $this->logger->logActivity(
                'backup_restored',
                ['filename' => $backupFile],
                $this->currentUser
            );
            return true;
        }

        throw new Exception('Backup restoration failed');
    }
}
<?php
/**
 * McGoff Backup Manager - Scheduled Backup Runner
 * 
 * This script is called by the cron job to check for and execute scheduled backups.
 * It should be set up to run frequently (e.g., every 5-15 minutes) to check if any
 * backups are due to run.
 * 
 * Created: 2025-02-26 19:48:11
 * Author: irlam
 */

// Set to script mode (no output headers, etc.)
define('SCHEDULER_MODE', true);

require_once 'config.php';
require_once 'functions.php';
require_once 'backup-manager.php';
require_once 'scheduled-backups.php';

// Start a log entry
$logFile = __DIR__ . '/scheduler/scheduler_log.txt';
$logEntry = date('Y-m-d H:i:s') . " - Scheduler started\n";

// Get all scheduled backups
$schedules = get_scheduled_backups();

if (empty($schedules)) {
    $logEntry .= "No schedules found\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
    exit;
}

// Initialize backup manager
$backupManager = new BackupManager();

// Check each schedule
foreach ($schedules as $id => $schedule) {
    if (should_run_schedule($schedule)) {
        $logEntry .= "Running schedule: {$schedule['name']} (ID: $id)\n";
        
        try {
            // Create the backup
            $result = $backupManager->createFullBackup();
            
            if ($result['success']) {
                $logEntry .= "Backup successful: {$result['file']}\n";
                
                // Update last run time
                update_schedule_last_run($id);
            } else {
                $logEntry .= "Backup failed: {$result['message']}\n";
            }
        } catch (Exception $e) {
            $logEntry .= "Error during scheduled backup: " . $e->getMessage() . "\n";
        }
    } else {
        $nextRun = date('Y-m-d H:i:s', get_next_run_time($schedule));
        $logEntry .= "Schedule {$schedule['name']} not due yet. Next run: $nextRun\n";
    }
}

// Write to log
$logEntry .= "Scheduler finished at " . date('Y-m-d H:i:s') . "\n\n";
file_put_contents($logFile, $logEntry, FILE_APPEND);
?>
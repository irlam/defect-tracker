<?php
/**
 * cleanup_old_logs.php
 * 
 * Maintenance script to clean up old log entries
 * Removes logs older than 30 days from user_logs and activity_logs tables
 * 
 * This script should be run periodically (e.g., daily via cron)
 * Example cron entry: 0 2 * * * /usr/bin/php /path/to/cleanup_old_logs.php
 */

// Only allow CLI execution for security
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from the command line.');
}

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/maintenance.log');

require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Calculate the date 30 days ago
    $thirtyDaysAgo = date('Y-m-d H:i:s', strtotime('-30 days'));
    
    echo "Starting log cleanup process...\n";
    echo "Removing logs older than: " . $thirtyDaysAgo . "\n\n";
    
    // Clean up user_logs
    $userLogsStmt = $db->prepare("DELETE FROM user_logs WHERE action_at < :thirtyDaysAgo");
    $userLogsStmt->bindParam(':thirtyDaysAgo', $thirtyDaysAgo);
    $userLogsStmt->execute();
    $userLogsDeleted = $userLogsStmt->rowCount();
    
    echo "Deleted {$userLogsDeleted} entries from user_logs table\n";
    
    // Clean up activity_logs
    $activityLogsStmt = $db->prepare("DELETE FROM activity_logs WHERE created_at < :thirtyDaysAgo");
    $activityLogsStmt->bindParam(':thirtyDaysAgo', $thirtyDaysAgo);
    $activityLogsStmt->execute();
    $activityLogsDeleted = $activityLogsStmt->rowCount();
    
    echo "Deleted {$activityLogsDeleted} entries from activity_logs table\n";
    
    $totalDeleted = $userLogsDeleted + $activityLogsDeleted;
    echo "\nTotal entries deleted: {$totalDeleted}\n";
    echo "Log cleanup completed successfully.\n";
    
    // Log the cleanup operation
    error_log("Log cleanup completed: {$userLogsDeleted} user_logs, {$activityLogsDeleted} activity_logs deleted (older than {$thirtyDaysAgo})");
    
} catch (Exception $e) {
    echo "Error during log cleanup: " . $e->getMessage() . "\n";
    error_log("Log cleanup error: " . $e->getMessage());
    exit(1);
}

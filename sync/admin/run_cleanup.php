<?php
/**
 * Sync System - Cleanup Script
 * Created: 2025-02-26 12:00:19
 * Updated by: irlam
 * 
 * This script removes old sync records based on configured retention periods.
 * It can be run manually from the admin interface or automatically via cron.
 */

// Determine if running from command line or web
$isCli = (php_sapi_name() == 'cli');

// If not running from CLI or admin page, require authentication
if (!$isCli && (!isset($username) || empty($username))) {
    // Include initialization for web usage
    require_once __DIR__ . '/../init.php';
    
    // Check if user is logged in and has admin privileges
    session_start();
    if (!isset($_SESSION['username']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
        echo "Unauthorized access\n";
        exit(1);
    }
    
    $username = $_SESSION['username'];
}

// Load config
if (!isset($config)) {
    $config = include __DIR__ . '/../config.php';
}

// Default settings if not already set in database
$settings = [
    'completed_retention_days' => 30,
    'failed_retention_days' => 90,
    'logs_retention_days' => 180,
    'conflicts_retention_days' => 365
];

// Log helper function
function log_message($message) {
    global $isCli;
    if ($isCli) {
        echo $message . "\n";
    }
}

// Initialize stats
$stats = [
    'completed_removed' => 0,
    'failed_removed' => 0,
    'logs_removed' => 0,
    'conflicts_removed' => 0
];

try {
    // Establish database connection
    $db = new PDO("mysql:host={$config['db_host']};dbname={$config['db_name']}", $config['db_user'], $config['db_pass']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get settings
    $stmt = $db->query("SELECT setting_key, setting_value FROM sync_settings WHERE setting_key LIKE 'cleanup_%'");
    $dbSettings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Update settings with values from database
    if (isset($dbSettings['cleanup_completed_retention_days'])) {
        $settings['completed_retention_days'] = (int)$dbSettings['cleanup_completed_retention_days'];
    }
    if (isset($dbSettings['cleanup_failed_retention_days'])) {
        $settings['failed_retention_days'] = (int)$dbSettings['cleanup_failed_retention_days'];
    }
    if (isset($dbSettings['cleanup_logs_retention_days'])) {
        $settings['logs_retention_days'] = (int)$dbSettings['cleanup_logs_retention_days'];
    }
    if (isset($dbSettings['cleanup_conflicts_retention_days'])) {
        $settings['conflicts_retention_days'] = (int)$dbSettings['cleanup_conflicts_retention_days'];
    }
    
    log_message("Starting cleanup with the following retention settings:");
    log_message("- Completed items: {$settings['completed_retention_days']} days");
    log_message("- Failed items: {$settings['failed_retention_days']} days");
    log_message("- Logs: {$settings['logs_retention_days']} days");
    log_message("- Resolved conflicts: {$settings['conflicts_retention_days']} days");
    
    // Clean completed sync queue items
    $stmt = $db->prepare("DELETE FROM sync_queue 
                         WHERE status = 'completed' 
                         AND processed_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
    $stmt->execute([$settings['completed_retention_days']]);
    $stats['completed_removed'] = $stmt->rowCount();
    log_message("Removed {$stats['completed_removed']} completed sync queue items");
    
    // Clean failed sync queue items
    $stmt = $db->prepare("DELETE FROM sync_queue 
                         WHERE status = 'failed' 
                         AND updated_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
    $stmt->execute([$settings['failed_retention_days']]);
    $stats['failed_removed'] = $stmt->rowCount();
    log_message("Removed {$stats['failed_removed']} failed sync queue items");
    
    // Clean old sync logs
    $stmt = $db->prepare("DELETE FROM sync_logs 
                         WHERE end_time < DATE_SUB(NOW(), INTERVAL ? DAY)");
    $stmt->execute([$settings['logs_retention_days']]);
    $stats['logs_removed'] = $stmt->rowCount();
    log_message("Removed {$stats['logs_removed']} sync log entries");
    
    // Clean resolved conflicts
    $stmt = $db->prepare("DELETE FROM sync_conflicts 
                         WHERE resolved = 1 
                         AND resolved_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
    $stmt->execute([$settings['conflicts_retention_days']]);
    $stats['conflicts_removed'] = $stmt->rowCount();
    log_message("Removed {$stats['conflicts_removed']} resolved conflicts");
    
    // Log the cleanup operation
    $user = $isCli ? 'cron' : ($username ?? 'unknown');
    $stmt = $db->prepare("INSERT INTO system_logs (action, action_by, action_at, details) 
                         VALUES ('SYNC_CLEANUP', ?, ?, ?)");
    $stmt->execute([$user, date('Y-m-d H:i:s'), json_encode($stats)]);
    
        // Update last cleanup time
    $now = date('Y-m-d H:i:s');
    $stmt = $db->prepare("INSERT INTO sync_settings (setting_key, setting_value, updated_by) 
                         VALUES (?, ?, ?) 
                         ON DUPLICATE KEY UPDATE 
                         setting_value = ?, updated_by = ?");
    $stmt->execute(['cleanup_last_run', $now, $user, $now, $user]);
    
    if (isset($dbSettings['cleanup_auto_enabled']) && $dbSettings['cleanup_auto_enabled'] === '1') {
        // Schedule next cleanup for tomorrow
        $nextScheduled = date('Y-m-d H:i:s', strtotime('+1 day'));
        $stmt = $db->prepare("INSERT INTO sync_settings (setting_key, setting_value, updated_by) 
                             VALUES (?, ?, ?) 
                             ON DUPLICATE KEY UPDATE 
                             setting_value = ?, updated_by = ?");
        $stmt->execute(['cleanup_next_scheduled', $nextScheduled, $user, $nextScheduled, $user]);
        log_message("Next cleanup scheduled for $nextScheduled");
    }
    
    log_message("Cleanup completed successfully");
    
    // Return true if called from another script
    if (!$isCli && isset($username)) {
        return true;
    }
    
} catch (PDOException $e) {
    $errorMsg = "Database error during cleanup: " . $e->getMessage();
    log_message($errorMsg);
    
    // Log the error if possible
    try {
        $user = $isCli ? 'cron' : ($username ?? 'unknown');
        $db->prepare("INSERT INTO system_logs (action, action_by, action_at, details) 
                    VALUES ('SYNC_CLEANUP_ERROR', ?, ?, ?)")
           ->execute([$user, date('Y-m-d H:i:s'), $errorMsg]);
    } catch (Exception $ex) {
        // Can't even log the error
        log_message("Additionally, could not log error to database: " . $ex->getMessage());
    }
    
    if ($isCli) {
        exit(1);
    } else if (isset($username)) {
        throw new Exception($errorMsg);
    }
}
    
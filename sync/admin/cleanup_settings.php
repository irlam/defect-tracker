<?php
/**
 * Sync System - Cleanup Settings
 * Created: 2025-02-26 12:00:19
 * Updated by: irlam
 */

// Include authentication and initialization
require_once __DIR__ . '/../init.php';

// Check if user is logged in and has admin privileges
session_start();
if (!isset($_SESSION['username']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header('Location: ../login.php');
    exit;
}

// Load config explicitly
if (!isset($config)) {
    $config = include __DIR__ . '/../config.php';
}

// Initialize variables
$pageTitle = 'Sync Cleanup Settings';
$username = $_SESSION['username'] ?? 'Unknown User';
$message = '';
$messageType = '';

// Get current settings
$settings = [
    'completed_retention_days' => 30,
    'failed_retention_days' => 90,
    'logs_retention_days' => 180,
    'conflicts_retention_days' => 365,
    'auto_cleanup_enabled' => false,
    'last_cleanup' => 'Never',
    'next_scheduled_cleanup' => 'Not scheduled'
];

// Establish database connection
try {
    $db = new PDO("mysql:host={$config['db_host']};dbname={$config['db_name']}", $config['db_user'], $config['db_pass']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if settings table exists, create if not
    $db->exec("CREATE TABLE IF NOT EXISTS sync_settings (
        setting_key VARCHAR(50) PRIMARY KEY,
        setting_value TEXT NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        updated_by VARCHAR(50)
    )");
    
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
    if (isset($dbSettings['cleanup_auto_enabled'])) {
        $settings['auto_cleanup_enabled'] = $dbSettings['cleanup_auto_enabled'] === '1';
    }
    if (isset($dbSettings['cleanup_last_run'])) {
        $settings['last_cleanup'] = $dbSettings['cleanup_last_run'];
    }
    if (isset($dbSettings['cleanup_next_scheduled'])) {
        $settings['next_scheduled_cleanup'] = $dbSettings['cleanup_next_scheduled'];
    }
    
} catch (PDOException $e) {
    $message = "Database connection failed: " . $e->getMessage();
    $messageType = "error";
}

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'save_settings':
                try {
                    // Validate and sanitize input
                    $completed = max(1, min(365, (int)$_POST['completed_retention_days']));
                    $failed = max(1, min(730, (int)$_POST['failed_retention_days']));
                    $logs = max(1, min(1095, (int)$_POST['logs_retention_days']));
                    $conflicts = max(1, min(1095, (int)$_POST['conflicts_retention_days']));
                    $autoEnabled = isset($_POST['auto_cleanup_enabled']) ? 1 : 0;
                    
                    // Update settings in database
                    $settingsToUpdate = [
                        'cleanup_completed_retention_days' => $completed,
                        'cleanup_failed_retention_days' => $failed,
                        'cleanup_logs_retention_days' => $logs,
                        'cleanup_conflicts_retention_days' => $conflicts,
                        'cleanup_auto_enabled' => $autoEnabled
                    ];
                    
                    foreach ($settingsToUpdate as $key => $value) {
                        $stmt = $db->prepare("INSERT INTO sync_settings (setting_key, setting_value, updated_by) 
                                             VALUES (?, ?, ?) 
                                             ON DUPLICATE KEY UPDATE 
                                             setting_value = ?, updated_by = ?");
                        $stmt->execute([$key, $value, $username, $value, $username]);
                    }
                    
                    // Calculate next scheduled cleanup if auto-cleanup is enabled
                    if ($autoEnabled) {
                        $nextScheduled = date('Y-m-d H:i:s', strtotime('+1 day'));
                        $stmt = $db->prepare("INSERT INTO sync_settings (setting_key, setting_value, updated_by) 
                                             VALUES (?, ?, ?) 
                                             ON DUPLICATE KEY UPDATE 
                                             setting_value = ?, updated_by = ?");
                        $stmt->execute(['cleanup_next_scheduled', $nextScheduled, $username, $nextScheduled, $username]);
                        $settings['next_scheduled_cleanup'] = $nextScheduled;
                    } else {
                        $stmt = $db->prepare("INSERT INTO sync_settings (setting_key, setting_value, updated_by) 
                                             VALUES (?, ?, ?) 
                                             ON DUPLICATE KEY UPDATE 
                                             setting_value = ?, updated_by = ?");
                        $stmt->execute(['cleanup_next_scheduled', 'Not scheduled', $username, 'Not scheduled', $username]);
                        $settings['next_scheduled_cleanup'] = 'Not scheduled';
                    }
                    
                    // Update local settings
                    $settings['completed_retention_days'] = $completed;
                    $settings['failed_retention_days'] = $failed;
                    $settings['logs_retention_days'] = $logs;
                    $settings['conflicts_retention_days'] = $conflicts;
                    $settings['auto_cleanup_enabled'] = $autoEnabled === 1;
                    
                    $message = "Settings updated successfully.";
                    $messageType = "success";
                    
                    // Log the action
                    $stmt = $db->prepare("INSERT INTO system_logs (user_id, action, action_by, action_at, details) 
                                         VALUES (?, 'UPDATE_CLEANUP_SETTINGS', ?, ?, ?)");
                    $stmt->execute([
                        $_SESSION['user_id'], 
                        $_SESSION['user_id'], 
                        date('Y-m-d H:i:s'),
                        "Updated sync cleanup settings"
                    ]);
                } catch (PDOException $e) {
                    $message = "Error saving settings: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
                
            case 'run_cleanup':
                try {
                    // Run cleanup script
                    require_once __DIR__ . '/run_cleanup.php';
                    
                    // Track when cleanup was last run
                    $now = date('Y-m-d H:i:s');
                    $stmt = $db->prepare("INSERT INTO sync_settings (setting_key, setting_value, updated_by) 
                                         VALUES (?, ?, ?) 
                                         ON DUPLICATE KEY UPDATE 
                                         setting_value = ?, updated_by = ?");
                    $stmt->execute(['cleanup_last_run', $now, $username, $now, $username]);
                    $settings['last_cleanup'] = $now;
                    
                    $message = "Cleanup completed successfully. See details in logs.";
                    $messageType = "success";
                } catch (Exception $e) {
                    $message = "Error running cleanup: " . $e->getMessage();
                    $messageType = "error";
                }
                break;
        }
    }
}

// Get cleanup stats from logs
$cleanupStats = [
    'total_cleanups' => 0,
    'items_removed' => 0,
    'last_cleanup_details' => 'No cleanup has been performed yet.'
];

try {
    // Get count of cleanup operations
    $stmt = $db->query("SELECT COUNT(*) FROM system_logs WHERE action = 'SYNC_CLEANUP'");
    $cleanupStats['total_cleanups'] = $stmt->fetchColumn();
    
    // Get total items removed
    $stmt = $db->query("SELECT 
                         SUM(CAST(JSON_EXTRACT(details, '$.completed_removed') AS UNSIGNED)) +
                         SUM(CAST(JSON_EXTRACT(details, '$.failed_removed') AS UNSIGNED)) +
                         SUM(CAST(JSON_EXTRACT(details, '$.logs_removed') AS UNSIGNED)) +
                         SUM(CAST(JSON_EXTRACT(details, '$.conflicts_removed') AS UNSIGNED)) AS total
                       FROM system_logs 
                       WHERE action = 'SYNC_CLEANUP'
                       AND details LIKE '%{%'");
    $totalRemoved = $stmt->fetchColumn();
    $cleanupStats['items_removed'] = $totalRemoved ? $totalRemoved : 0;
    
    // Get last cleanup details
    $stmt = $db->query("SELECT details, action_at FROM system_logs 
                       WHERE action = 'SYNC_CLEANUP'
                       ORDER BY action_at DESC LIMIT 1");
    $lastCleanup = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($lastCleanup) {
        $details = json_decode($lastCleanup['details'], true);
        if ($details) {
            $cleanupStats['last_cleanup_details'] = "Last cleanup on {$lastCleanup['action_at']}:<br>" .
                "- Completed items removed: " . ($details['completed_removed'] ?? 0) . "<br>" .
                "- Failed items removed: " . ($details['failed_removed'] ?? 0) . "<br>" .
                "- Logs removed: " . ($details['logs_removed'] ?? 0) . "<br>" .
                "- Resolved conflicts removed: " . ($details['conflicts_removed'] ?? 0);
        }
    }
} catch (PDOException $e) {
    // Just ignore, stats will show default values
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - Sync Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2ecc71;
            --danger-color: #e74c3c;
            --warning-color: #f39c12;
            --dark-color: #34495e;
            --light-color: #ecf0f1;
            --border-color: #ddd;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background-color: var(--dark-color);
            color: white;
            padding: 15px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        
        .navigation-links {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .btn {
            display: inline-block;
            padding: 8px 16px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            transition: background 0.2s;
        }
        
        .btn:hover {
            background-color: #2980b9;
        }
        
        .btn-success {
            background-color: var(--secondary-color);
        }
        
        .btn-success:hover {
            background-color: #27ae60;
        }
        
        .btn-danger {
            background-color: var(--danger-color);
        }
        
        .btn-danger:hover {
            background-color: #c0392b;
        }
        
        .card {
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .card-header {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-group input[type="number"], 
        .form-group input[type="text"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        .form-group .help-text {
            font-size: 12px;
            color: #777;
            margin-top: 5px;
        }
        
        .form-row {
            display: flex;
            gap: 20px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 3px;
        }
        
        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        
        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
        
        .stats-row {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .stat-box {
            flex: 1;
            min-width: 200px;
            padding: 15px;
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-box h3 {
            margin-top: 0;
            color: var(--dark-color);
            font-size: 16px;
        }
        
        .stat-box .value {
            font-size: 28px;
            font-weight: bold;
            color: var(--primary-color);
            margin: 10px 0;
        }
        
        .switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }
        
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .slider {
            background-color: var(--secondary-color);
        }
        
        input:checked + .slider:before {
            transform: translateX(26px);
        }
        
        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .stats-row {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
            <div class="user-info">
                <i class="fas fa-user-circle" style="font-size: 24px; margin-right: 10px;"></i>
                <span><?php echo htmlspecialchars($username); ?></span>
            </div>
        </div>
        
        <div class="navigation-links">
            <a href="dashboard.php" class="btn"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="admin_checkTriggers.php" class="btn"><i class="fas fa-cogs"></i> Check Triggers</a>
            <a href="resolve_conflict.php" class="btn"><i class="fas fa-exclamation-triangle"></i> Conflicts</a>
            <a href="sync_logs.php" class="btn"><i class="fas fa-history"></i> Logs</a>
            <a href="cleanup_settings.php" class="btn"><i class="fas fa-broom"></i> Cleanup</a>
            <a href="performance_metrics.php" class="btn"><i class="fas fa-chart-line"></i> Performance</a>
        </div>
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType === 'error' ? 'danger' : $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">Cleanup Statistics</div>
            <div class="stats-row">
                <div class="stat-box">
                    <h3>Total Cleanups Run</h3>
                    <div class="value"><?php echo $cleanupStats['total_cleanups']; ?></div>
                </div>
                <div class="stat-box">
                    <h3>Items Removed</h3>
                    <div class="value"><?php echo number_format($cleanupStats['items_removed']); ?></div>
                </div>
                <div class="stat-box">
                    <h3>Last Cleanup</h3>
                    <div class="value" style="font-size: 18px;"><?php echo $settings['last_cleanup']; ?></div>
                </div>
                <div class="stat-box">
                    <h3>Next Scheduled</h3>
                    <div class="value" style="font-size: 18px;"><?php echo $settings['next_scheduled_cleanup']; ?></div>
                </div>
            </div>
            <div>
                <p><strong>Last Cleanup Details:</strong></p>
                <p><?php echo $cleanupStats['last_cleanup_details']; ?></p>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">Cleanup Settings</div>
            <form method="post">
                <input type="hidden" name="action" value="save_settings">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="completed_retention_days">Completed Items Retention (days)</label>
                        <input type="number" id="completed_retention_days" name="completed_retention_days" 
                               value="<?php echo $settings['completed_retention_days']; ?>" 
                               min="1" max="365" required>
                        <div class="help-text">How long to keep completed sync items before removal</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="failed_retention_days">Failed Items Retention (days)</label>
                        <input type="number" id="failed_retention_days" name="failed_retention_days" 
                               value="<?php echo $settings['failed_retention_days']; ?>" 
                               min="1" max="730" required>
                        <div class="help-text">How long to keep failed sync items before removal</div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="logs_retention_days">Sync Logs Retention (days)</label>
                        <input type="number" id="logs_retention_days" name="logs_retention_days" 
                               value="<?php echo $settings['logs_retention_days']; ?>" 
                               min="1" max="1095" required>
                        <div class="help-text">How long to keep sync log entries before removal</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="conflicts_retention_days">Resolved Conflicts Retention (days)</label>
                        <input type="number" id="conflicts_retention_days" name="conflicts_retention_days" 
                               value="<?php echo $settings['conflicts_retention_days']; ?>" 
                               min="1" max="1095" required>
                        <div class="help-text">How long to keep resolved conflicts before removal</div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="switch">
                        <input type="checkbox" name="auto_cleanup_enabled" 
                               <?php echo $settings['auto_cleanup_enabled'] ? 'checked' : ''; ?>>
                        <span class="slider"></span>
                    </label>
                    <span style="margin-left: 10px;">Enable Automatic Daily Cleanup</span>
                    <div class="help-text">When enabled, cleanup will run daily using these settings</div>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Save Settings
                    </button>
                    
                    <button type="submit" class="btn btn-danger" name="action" value="run_cleanup" 
                            onclick="return confirm('Are you sure you want to run cleanup now? This will permanently delete old records based on your settings.');">
                        <i class="fas fa-broom"></i> Run Cleanup Now
                    </button>
                </div>
            </form>
        </div>
        
        <div class="card">
            <div class="card-header">Setup Auto Cleanup</div>
            <p>To set up automatic cleanup, you need to configure a cron job on your server that runs the cleanup script daily.</p>
            <p>Add the following line to your crontab (adjust path as needed):</p>
            <pre style="background-color: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto;">0 2 * * * php <?php echo realpath(__DIR__); ?>/run_cleanup.php</pre>
            <p>This will run the cleanup job every day at 2:00 AM.</p>
        </div>
    </div>
</body>
</html>
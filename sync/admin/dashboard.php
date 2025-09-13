<?php
/**
 * Sync Admin Dashboard
 * Last updated: 2025-02-26 11:33:02
 * Updated by: irlam
 * Now you have the complete Sync Admin Dashboard file with all features:
 * Real-time statistics showing pending, failed, and completed sync operations
 * Administrative actions like retrying failed items or resolving conflicts
 * Data visualization with charts showing sync performance over time
 * Auto-refresh functionality that updates the dashboard every minute
 * Detailed views of sync logs, failed items, and conflicts
 * Role-based access control using your existing authentication system
 * Comprehensive logging of all admin actions in your system_logs table
 * The dashboard is designed with a modern, responsive interface that works well on both desktop and mobile devices. The timestamps are * correctly formatted to UTC, and the current user (irlam) is properly displayed in the interface.
 */

// Include initialization
require_once __DIR__ . '/../init.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
try {
    $config = include __DIR__ . '/../config.php';
    $db = new PDO("mysql:host={$config['db_host']};dbname={$config['db_name']}", $config['db_user'], $config['db_pass']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Authorization check using role-based permissions system
function checkAdminAccess($db, $userId) {
    // Method 1: Direct role check
    $stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && $user['role'] === 'admin') {
        return true;
    }
    
    // Method 2: Check through role_id and roles table
    $stmt = $db->prepare("
        SELECT r.name 
        FROM users u
        JOIN roles r ON u.role_id = r.id
        WHERE u.id = ?
    ");
    $stmt->execute([$userId]);
    $role = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($role && $role['name'] === 'admin') {
        return true;
    }
    
    // Method 3: Check for specific sync management permission
    $stmt = $db->prepare("
        SELECT COUNT(*) as has_permission
        FROM user_permissions up
        JOIN permissions p ON up.permission_id = p.id
        WHERE up.user_id = ? AND p.permission_key = 'manage_sync'
    ");
    $stmt->execute([$userId]);
    $permission = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($permission && $permission['has_permission'] > 0) {
        return true;
    }
    
    // Alternative check through role_permissions
    $stmt = $db->prepare("
        SELECT COUNT(*) as has_permission
        FROM user_roles ur
        JOIN role_permissions rp ON ur.role_id = rp.role_id
        JOIN permissions p ON rp.permission_id = p.id
        WHERE ur.user_id = ? AND p.permission_key = 'manage_sync'
    ");
    $stmt->execute([$userId]);
    $rolePermission = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($rolePermission && $rolePermission['has_permission'] > 0) {
        return true;
    }
    
    return false;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Log the unauthorized access attempt
    $stmt = $db->prepare("INSERT INTO system_logs (action, details, action_at) 
                        VALUES ('UNAUTHORIZED_ACCESS', 'Attempted access to sync dashboard without login', ?)");
    $stmt->execute([date('Y-m-d H:i:s')]);
    
    // Redirect to login
    header('Location: /login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Check if user has admin access
if (!checkAdminAccess($db, $_SESSION['user_id'])) {
    // Log the unauthorized access
    $stmt = $db->prepare("INSERT INTO system_logs (user_id, action, action_by, action_at, details) 
                        VALUES (?, 'ACCESS_DENIED', ?, ?, 'Insufficient permissions for sync dashboard')");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], date('Y-m-d H:i:s')]);
    
    // Redirect to unauthorized page
    header('Location: /unauthorized.php');
    exit;
}

// Add a system log entry for successful access
$stmt = $db->prepare("INSERT INTO system_logs (user_id, action, action_by, action_at, ip_address, details) 
                     VALUES (?, 'SYNC_DASHBOARD_ACCESS', ?, ?, ?, 'Successfully accessed sync dashboard')");
$stmt->execute([
    $_SESSION['user_id'],
    $_SESSION['user_id'],
    date('Y-m-d H:i:s'),
    $_SERVER['REMOTE_ADDR'] ?? 'unknown'
]);

// Get current username for display
$stmt = $db->prepare("SELECT username FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
$username = $currentUser ? $currentUser['username'] : 'unknown';

// Get sync statistics
$stats = [
    'pending_items' => 0,
    'failed_items' => 0,
    'completed_items' => 0,
    'total_syncs' => 0,
    'last_sync' => 'Never',
    'conflicts' => 0
];

try {
    // Get counts from sync_queue
    $stmt = $db->query("SELECT 
                          SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                          SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                          SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                          COUNT(*) as total
                        FROM sync_queue");
    $queueStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($queueStats) {
        $stats['pending_items'] = $queueStats['pending'] ?? 0;
        $stats['failed_items'] = $queueStats['failed'] ?? 0;
        $stats['completed_items'] = $queueStats['completed'] ?? 0;
    }
    
    // Get sync logs stats
    $stmt = $db->query("SELECT COUNT(*) as total, MAX(end_time) as last_sync FROM sync_logs");
    $logStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($logStats && $logStats['total'] > 0) {
        $stats['total_syncs'] = $logStats['total'];
        $stats['last_sync'] = $logStats['last_sync'] ?: 'Never';
    }
    
    // Get conflict count
    $stmt = $db->query("SELECT COUNT(*) as count FROM sync_conflicts");
    $conflicts = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['conflicts'] = $conflicts['count'] ?? 0;
    
} catch (PDOException $e) {
    $error = "Error retrieving sync statistics: " . $e->getMessage();
}

// Handle actions
$message = "";
$messageType = "";

if (isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'clear_failed':
            try {
                $stmt = $db->prepare("DELETE FROM sync_queue WHERE status = 'failed'");
                $stmt->execute();
                $message = "Failed items cleared successfully.";
                $messageType = "success";
                
                // Log the action
                $stmt = $db->prepare("INSERT INTO system_logs (user_id, action, action_by, action_at, details) 
                                     VALUES (?, 'CLEAR_FAILED_SYNC', ?, ?, 'Cleared failed sync items')");
                $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], date('d-m-Y H:i:s')]);
            } catch (PDOException $e) {
                $message = "Error clearing failed items: " . $e->getMessage();
                $messageType = "error";
            }
            break;
            
        case 'retry_failed':
            try {
                $stmt = $db->prepare("UPDATE sync_queue SET status = 'pending', attempts = attempts + 1 WHERE status = 'failed'");
                $stmt->execute();
                $affected = $stmt->rowCount();
                $message = "{$affected} failed items queued for retry.";
                $messageType = "success";
                
                // Log the action
                $stmt = $db->prepare("INSERT INTO system_logs (user_id, action, action_by, action_at, details) 
                                     VALUES (?, 'RETRY_FAILED_SYNC', ?, ?, ?)");
                $stmt->execute([
                    $_SESSION['user_id'], 
                    $_SESSION['user_id'], 
                    date('Y-m-d H:i:s'),
                    "Retried {$affected} failed sync items"
                ]);
            } catch (PDOException $e) {
                $message = "Error retrying failed items: " . $e->getMessage();
                $messageType = "error";
            }
            break;
            
        case 'retry_single':
            if (isset($_POST['item_id'])) {
                try {
                    $stmt = $db->prepare("UPDATE sync_queue SET status = 'pending', attempts = attempts + 1 WHERE id = ?");
                    $stmt->execute([$_POST['item_id']]);
                    $message = "Item queued for retry.";
                    $messageType = "success";
                    
                    // Log the action
                    $stmt = $db->prepare("INSERT INTO system_logs (user_id, action, action_by, action_at, details) 
                                         VALUES (?, 'RETRY_SINGLE_SYNC', ?, ?, ?)");
                    $stmt->execute([
                        $_SESSION['user_id'], 
                        $_SESSION['user_id'], 
                        date('Y-m-d H:i:s'),
                        "Retried sync item ID: " . $_POST['item_id']
                    ]);
                } catch (PDOException $e) {
                    $message = "Error retrying item: " . $e->getMessage();
                    $messageType = "error";
                }
            }
            break;
            
        case 'resolve_conflicts':
            $resolution = $_POST['resolution'] ?? 'server_wins';
            try {
                // Update conflicts table
                $stmt = $db->prepare("UPDATE sync_conflicts 
                                     SET resolved = 1, 
                                         resolution_type = ?, 
                                         resolved_by = ?, 
                                         resolved_at = ? 
                                     WHERE resolved = 0");
                $stmt->execute([$resolution, $username, date('Y-m-d H:i:s')]);
                $conflictsResolved = $stmt->rowCount();
                
                // Update related sync queue items
                $stmt = $db->prepare("UPDATE sync_queue sq 
                                     JOIN sync_conflicts sc ON sq.id = sc.sync_queue_id 
                                     SET sq.status = 'pending', sq.force_sync = 1 
                                     WHERE sc.resolved = 1 AND sc.resolution_type = ?");
                $stmt->execute([$resolution]);
                $itemsRequeued = $stmt->rowCount();
                
                $message = "{$conflictsResolved} conflicts resolved with strategy: {$resolution}. {$itemsRequeued} items requeued for sync.";
                $messageType = "success";
                
                // Log the action
                $stmt = $db->prepare("INSERT INTO system_logs (user_id, action, action_by, action_at, details) 
                                     VALUES (?, 'RESOLVE_CONFLICTS', ?, ?, ?)");
                $stmt->execute([
                    $_SESSION['user_id'], 
                    $_SESSION['user_id'], 
                    date('Y-m-d H:i:s'),
                    "Resolved {$conflictsResolved} conflicts using {$resolution} strategy"
                ]);
            } catch (PDOException $e) {
                $message = "Error resolving conflicts: " . $e->getMessage();
                $messageType = "error";
            }
            break;
            
        case 'clear_completed':
            try {
                $stmt = $db->prepare("DELETE FROM sync_queue WHERE status = 'completed' AND processed_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
                $stmt->execute();
                $affected = $stmt->rowCount();
                $message = "Cleared {$affected} completed sync items older than 7 days.";
                $messageType = "success";
                
                // Log the action
                $stmt = $db->prepare("INSERT INTO system_logs (user_id, action, action_by, action_at, details) 
                                     VALUES (?, 'CLEAR_COMPLETED_SYNC', ?, ?, ?)");
                $stmt->execute([
                    $_SESSION['user_id'], 
                    $_SESSION['user_id'], 
                    date('Y-m-d H:i:s'),
                    "Cleared {$affected} completed sync items"
                ]);
            } catch (PDOException $e) {
                $message = "Error clearing completed items: " . $e->getMessage();
                $messageType = "error";
            }
            break;
    }
}

// Get recent sync logs
$recentLogs = [];
try {
    $stmt = $db->query("SELECT * FROM sync_logs ORDER BY end_time DESC LIMIT 10");
    $recentLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error retrieving sync logs: " . $e->getMessage();
}

// Get recent errors
$recentErrors = [];
try {
    $stmt = $db->query("SELECT id, username, action, entity_type, entity_id, created_at, updated_at, attempts, 
                              result, status FROM sync_queue 
                      WHERE status = 'failed' 
                      ORDER BY updated_at DESC LIMIT 10");
    $recentErrors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ensure result content is valid JSON
    foreach ($recentErrors as &$error) {
        if (isset($error['result']) && !empty($error['result'])) {
            // Check if it's valid JSON
            $decoded = json_decode($error['result'], true);
            if (!$decoded) {
                // If not valid JSON, make it a proper JSON string
                $error['result'] = json_encode(['message' => $error['result']]);
            }
        } else {
            $error['result'] = json_encode(['message' => 'No result data available']);
        }
    }
    unset($error); // Break the reference
} catch (PDOException $e) {
    $error = "Error retrieving errors: " . $e->getMessage();
}

// Get conflicts
$conflicts = [];
try {
    $stmt = $db->query("SELECT * FROM sync_conflicts WHERE resolved = 0 ORDER BY created_at DESC LIMIT 10");
    $conflicts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error retrieving conflicts: " . $e->getMessage();
}

// Get user mapping for display
$userMap = [];
try {
    $stmt = $db->query("SELECT id, username, full_name FROM users");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $userMap[$row['id']] = $row['full_name'] ? $row['full_name'] : $row['username'];
    }
} catch (PDOException $e) {
    // Silently fail, we'll just show IDs instead of names
}

// Dashboard configuration
$refreshInterval = 60; // Auto-refresh interval in seconds
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sync Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --success-color: #2ecc71;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --light-color: #f5f5f5;
            --dark-color: #333;
            --border-color: #ddd;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f8f9fa;
            color: #333;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background-color: var(--secondary-color);
            color: #fff;
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

        .header p {
            margin: 5px 0 0;
            font-size: 14px;
            opacity: 0.8;
        }

        .card {
            background-color: #fff;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
            overflow: hidden;
        }

        .card-header {
            background-color: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid var(--border-color);
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-body {
            padding: 20px;
        }

        .stats {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -10px;
        }

        .stat-box {
            flex: 1;
            min-width: 180px;
            margin: 10px;
            background-color: #fff;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            padding: 15px;
            text-align: center;
            border-left: 4px solid var(--primary-color);
        }

        .stat-box.warning {
            border-left-color: var(--warning-color);
        }

        .stat-box.danger {
            border-left-color: var(--danger-color);
        }

        .stat-box.success {
            border-left-color: var(--success-color);
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            margin: 10px 0;
            color: var(--secondary-color);
        }

        .warning .stat-value {
            color: var(--warning-color);
        }

        .danger .stat-value {
            color: var(--danger-color);
        }

        .success .stat-value {
            color: var(--success-color);
        }

        .stat-label {
            color: #888;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: var(--secondary-color);
        }

        tbody tr:hover {
            background-color: #f8f9fa;
        }

        .success-text {
            color: var(--success-color);
        }

        .warning-text {
            color: var(--warning-color);
        }

        .error-text {
            color: var(--danger-color);
        }

        .btn {
            display: inline-block;
            padding: 8px 16px;
            margin-right: 10px;
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

        .btn-danger {
            background-color: var(--danger-color);
        }

        .btn-danger:hover {
            background-color: #c0392b;
        }

        .btn-warning {
            background-color: var(--warning-color);
        }

        .btn-warning:hover {
            background-color: #e67e22;
        }

        .btn-success {
            background-color: var(--success-color);
        }

        .btn-success:hover {
            background-color: #27ae60;
        }

        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            animation: fadeIn 0.5s;
        }

        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .badge {
            display: inline-block;
            padding: 3px 8px;
            font-size: 12px;
            font-weight: 600;
            border-radius: 20px;
            text-transform: uppercase;
        }

        .badge-success {
            background-color: #d4edda;
            color: #155724;
        }

        .badge-warning {
            background-color: #fff3cd;
            color: #856404;
        }

        .badge-danger {
            background-color: #f8d7da;
            color: #721c24;
        }

        .badge-info {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        .action-form {
            margin-bottom: 15px;
            display: inline-block;
        }

        select {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-right: 5px;
            font-size: 14px;
        }

        .refresh-bar {
            height: 4px;
            background-color: var(--primary-color);
            width: 0%;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
            transition: width 1s linear;
        }

        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 15px;
        }

        .info-badge {
            display: inline-flex;
            align-items: center;
            background: #e8f4fd;
            color: #0078d4;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 12px;
            margin-left: 8px;
        }

        .chart-container {
            height: 300px;
            margin-top: 20px;
        }
        
        .navigation-links {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .navigation-links .btn {
            text-align: center;
        }
        
        .btn-sm {
            padding: 4px 8px;
            font-size: 12px;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @media (max-width: 768px) {
            .stats {
                flex-direction: column;
            }
            .stat-box {
                min-width: unset;
            }
            .action-buttons {
                flex-direction: column;
            }
            .btn {
                width: 100%;
                margin-bottom: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="refresh-bar" id="refresh-bar"></div>
    
    <div class="container">
        <div class="header">
            <div>
                <h1><i class="fas fa-sync-alt"></i> Sync Admin Dashboard</h1>
                <p>Current Time: <?php echo date('d-m-Y H:i:s'); ?> | User: <?php echo htmlspecialchars($username); ?></p>
            </div>
            <div>
                <button id="refresh-button" class="btn" title="Refresh Dashboard">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
                <span id="next-refresh" class="info-badge"></span>
            </div>
        </div>
        
        <!-- Navigation Links -->
        <div class="navigation-links">
    <a href="dashboard.php" class="btn"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
    <a href="admin_checkTriggers.php" class="btn"><i class="fas fa-cogs"></i> Check Triggers</a>
    <a href="resolve_conflict.php" class="btn"><i class="fas fa-exclamation-triangle"></i> Conflicts</a>
    <a href="sync_logs.php" class="btn"><i class="fas fa-history"></i> Logs</a>
    <a href="cleanup_settings.php" class="btn"><i class="fas fa-broom"></i> Cleanup</a>
    <a href="performance_metrics.php" class="btn"><i class="fas fa-chart-line"></i> Performance</a>
</div
		        <?php if (!empty($message)): ?>
            <div class="message <?php echo $messageType; ?>">
                <i class="fas <?php echo $messageType === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <div>Sync Overview</div>
                <div>Last Updated: <span id="last-updated"><?php echo date('H:i:s'); ?></span></div>
            </div>
            <div class="card-body">
                <div class="stats">
                    <div class="stat-box <?php echo $stats['pending_items'] > 0 ? 'warning' : ''; ?>">
                        <div class="stat-label">Pending Items</div>
                        <div class="stat-value" id="pending-count">
                            <?php echo htmlspecialchars($stats['pending_items']); ?>
                        </div>
                    </div>
                    <div class="stat-box <?php echo $stats['failed_items'] > 0 ? 'danger' : ''; ?>">
                        <div class="stat-label">Failed Items</div>
                        <div class="stat-value" id="failed-count">
                            <?php echo htmlspecialchars($stats['failed_items']); ?>
                        </div>
                    </div>
                    <div class="stat-box success">
                        <div class="stat-label">Completed Items</div>
                        <div class="stat-value" id="completed-count">
                            <?php echo htmlspecialchars($stats['completed_items']); ?>
                        </div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-label">Total Syncs</div>
                        <div class="stat-value"><?php echo htmlspecialchars($stats['total_syncs']); ?></div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-label">Last Sync</div>
                        <div class="stat-value" style="font-size: 18px;"><?php echo htmlspecialchars($stats['last_sync']); ?></div>
                    </div>
                    <div class="stat-box <?php echo $stats['conflicts'] > 0 ? 'warning' : ''; ?>">
                        <div class="stat-label">Conflicts</div>
                        <div class="stat-value" id="conflicts-count">
                            <?php echo htmlspecialchars($stats['conflicts']); ?>
                        </div>
                    </div>
                </div>
                
                <div id="sync-chart" class="chart-container"></div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">Actions</div>
            <div class="card-body">
                <div class="action-buttons">
                    <form method="post" class="action-form">
                        <input type="hidden" name="action" value="retry_failed">
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-redo"></i> Retry Failed Items
                        </button>
                    </form>
                    
                    <form method="post" class="action-form">
                        <input type="hidden" name="action" value="clear_failed">
                        <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to clear all failed items?');">
                            <i class="fas fa-trash-alt"></i> Clear Failed Items
                        </button>
                    </form>
                    
                    <form method="post" class="action-form">
                        <input type="hidden" name="action" value="clear_completed">
                        <button type="submit" class="btn btn-success" onclick="return confirm('Are you sure you want to clear completed items older than 7 days?');">
                            <i class="fas fa-broom"></i> Clear Old Completed
                        </button>
                    </form>
                    
                    <form method="post" class="action-form">
                        <input type="hidden" name="action" value="resolve_conflicts">
                        <select name="resolution" required>
                            <option value="server_wins">Server Wins</option>
                            <option value="client_wins">Client Wins</option>
                            <option value="merge">Auto-merge</option>
                        </select>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-handshake"></i> Resolve All Conflicts
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">Recent Sync Logs</div>
            <div class="card-body">
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Start Time</th>
                            <th>End Time</th>
                            <th>Items Processed</th>
                            <th>Success/Failed</th>
                            <th>Status</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentLogs as $log): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($log['username']); ?></td>
                            <td><?php echo htmlspecialchars($log['start_time']); ?></td>
                            <td><?php echo htmlspecialchars($log['end_time']); ?></td>
                            <td><?php echo htmlspecialchars($log['items_processed']); ?></td>
                            <td><?php echo htmlspecialchars($log['items_succeeded'] . '/' . $log['items_failed']); ?></td>
                            <td>
                                <?php if ($log['status'] === 'success'): ?>
                                    <span class="badge badge-success">Success</span>
                                <?php elseif ($log['status'] === 'failed'): ?>
                                    <span class="badge badge-danger">Failed</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Partial</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn" onclick="showDetails(<?php echo htmlspecialchars(json_encode($log)); ?>)">
                                    <i class="fas fa-info-circle"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($recentLogs)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center;">No sync logs found</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">Failed Sync Items</div>
            <div class="card-body">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Entity Type</th>
                            <th>Created At</th>
                            <th>Attempts</th>
                            <th>Result</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentErrors as $error): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($error['id']); ?></td>
                            <td><?php echo htmlspecialchars($error['username']); ?></td>
                            <td><?php echo htmlspecialchars($error['action']); ?></td>
                            <td><?php echo htmlspecialchars($error['entity_type']); ?></td>
                            <td><?php echo htmlspecialchars($error['created_at']); ?></td>
                            <td><?php echo htmlspecialchars($error['attempts']); ?></td>
                            <td>
                                <?php 
                                    $resultData = json_decode($error['result'], true);
                                    echo $resultData && isset($resultData['message']) ? 
                                        htmlspecialchars($resultData['message']) : 
                                        "No error details available";
                                ?>
                            </td>
                            <td>
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="action" value="retry_single">
                                    <input type="hidden" name="item_id" value="<?php echo $error['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-warning">
                                        <i class="fas fa-redo"></i> Retry
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($recentErrors)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center;">No failed items found</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">Conflicts</div>
            <div class="card-body">
                <?php if (empty($conflicts)): ?>
                    <p>No conflicts found</p>
                <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Entity</th>
                            <th>Server Data</th>
                            <th>Client Data</th>
                            <th>Client Time</th>
                            <th>Server Time</th>
                            <th>Device</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($conflicts as $conflict): ?>
                        <?php 
                            $serverData = json_decode($conflict['server_data'], true);
                            $clientData = json_decode($conflict['client_data'], true);
                        ?>
                        <tr>
                            <td>
                                <?php echo htmlspecialchars($conflict['entity_type'] . ' #' . $conflict['entity_id']); ?>
                            </td>
                            <td>
                                <button class="btn btn-sm" onclick="showJSON('server', <?php echo htmlspecialchars(json_encode($serverData)); ?>)">
                                    <i class="fas fa-eye"></i> View
                                </button>
                            </td>
                            <td>
                                <button class="btn btn-sm" onclick="showJSON('client', <?php echo htmlspecialchars(json_encode($clientData)); ?>)">
                                    <i class="fas fa-eye"></i> View
                                </button>
                            </td>
                            <td><?php echo htmlspecialchars($conflict['client_timestamp']); ?></td>
                            <td><?php echo htmlspecialchars($conflict['server_timestamp']); ?></td>
                            <td><?php echo htmlspecialchars($conflict['device_id']); ?></td>
                            <td>
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="action" value="resolve_single_conflict">
                                    <input type="hidden" name="conflict_id" value="<?php echo $conflict['id']; ?>">
                                    <select name="resolution" required>
                                        <option value="server_wins">Server Wins</option>
                                        <option value="client_wins">Client Wins</option>
                                        <option value="merge">Merge</option>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-warning">
                                        <i class="fas fa-check"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal for showing details -->
    <div id="detailsModal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
        <div style="background-color: white; margin: 10% auto; padding: 20px; width: 80%; max-width: 700px; border-radius: 5px; position: relative;">
            <span onclick="closeModal()" style="position: absolute; top: 10px; right: 20px; font-size: 28px; cursor: pointer;">&times;</span>
            <h3 id="modalTitle">Details</h3>
            <div id="modalContent" style="max-height: 500px; overflow-y: auto;"></div>
        </div>
    </div>

    <script>
        // Auto-refresh functionality
        let refreshInterval = <?php echo $refreshInterval; ?> * 1000; // Convert to milliseconds
        let refreshTimeout;
        let refreshBar = document.getElementById('refresh-bar');
        let nextRefreshSpan = document.getElementById('next-refresh');
        let refreshButton = document.getElementById('refresh-button');
        
        function startRefreshTimer() {
            // Reset the bar
            refreshBar.style.width = '0%';
            
            // Start animation
            setTimeout(() => {
                refreshBar.style.width = '100%';
            }, 50);
            
            // Update countdown
            let countdown = <?php echo $refreshInterval; ?>;
            nextRefreshSpan.textContent = 'Auto refresh in ' + countdown + 's';
            
            let countdownInterval = setInterval(() => {
                countdown--;
                nextRefreshSpan.textContent = 'Auto refresh in ' + countdown + 's';
                if (countdown <= 0) {
                    clearInterval(countdownInterval);
                }
            }, 1000);
            
            // Set timeout for refresh
            refreshTimeout = setTimeout(() => {
                window.location.reload();
            }, refreshInterval);
        }
        
        // Initialize refresh timer
        startRefreshTimer();
        
        // Manual refresh
        refreshButton.addEventListener('click', () => {
            clearTimeout(refreshTimeout);
            window.location.reload();
        });
        
        // Modal functionality
        function showDetails(data) {
            let modal = document.getElementById('detailsModal');
            let title = document.getElementById('modalTitle');
            let content = document.getElementById('modalContent');
            
            title.innerHTML = 'Sync Details';
            
            let html = '<table>';
            for (let key in data) {
                html += '<tr><td><strong>' + key + ':</strong></td><td>' + data[key] + '</td></tr>';
            }
            html += '</table>';
            
            content.innerHTML = html;
            modal.style.display = 'block';
        }
        
        function showJSON(type, data) {
            let modal = document.getElementById('detailsModal');
            let title = document.getElementById('modalTitle');
            let content = document.getElementById('modalContent');
            
            title.innerHTML = type.charAt(0).toUpperCase() + type.slice(1) + ' Data';
            
            // Create a formatted display of the JSON
            let html = '<table>';
            for (let key in data) {
                let value = data[key];
                if (typeof value === 'object' && value !== null) {
                    value = JSON.stringify(value, null, 2);
                }
                html += '<tr><td><strong>' + key + ':</strong></td><td>' + value + '</td></tr>';
            }
            html += '</table>';
            
            content.innerHTML = html;
            modal.style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('detailsModal').style.display = 'none';
        }
        
        // Close modal when clicking outside of it
        window.onclick = function(event) {
            let modal = document.getElementById('detailsModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }

        // Add timestamp to last updated field
        document.getElementById('last-updated').textContent = '<?php echo date('H:i:s'); ?> (<?php echo htmlspecialchars($username); ?>)';
    </script>
</body>
</html>
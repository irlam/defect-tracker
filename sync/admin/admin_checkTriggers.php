<?php
/**
 * Sync System Trigger Check Script
 * Created: 2025-02-26 11:44:06
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

// Initialize variables
$pageTitle = 'Database Trigger Status';
$username = $_SESSION['username'] ?? 'Unknown User';
$message = '';
$messageType = '';

// Load config explicitly
if (!isset($config)) {
    $config = include __DIR__ . '/../config.php';
}

// Establish database connection
try {
    $db = new PDO("mysql:host={$config['db_host']};dbname={$config['db_name']}", $config['db_user'], $config['db_pass']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    $message = "Database connection failed: " . $e->getMessage();
    $messageType = "error";
}

// Process form actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'fix_triggers') {
        try {
            // Execute the trigger creation script
            require_once __DIR__ . '/../setup_triggers.php';
            $message = "Triggers have been recreated successfully.";
            $messageType = "success";
        } catch (Exception $e) {
            $message = "Error creating triggers: " . $e->getMessage();
            $messageType = "error";
        }
    }
}

// Check if triggers exist
$defectsTriggerExists = false;
$imagesTriggerExists = false;
$commentsTriggerExists = false;
$allTriggersOk = false;
$allTriggers = [];

try {
    if (isset($db)) {
        $stmt = $db->query("SHOW TRIGGERS WHERE `Table` = 'defects' AND `Trigger` = 'defects_before_update'");
        $defectsTriggerExists = $stmt->rowCount() > 0;
        
        $stmt = $db->query("SHOW TRIGGERS WHERE `Table` = 'defect_images' AND `Trigger` = 'defect_images_before_update'");
        $imagesTriggerExists = $stmt->rowCount() > 0;
        
        $stmt = $db->query("SHOW TRIGGERS WHERE `Table` = 'defect_comments' AND `Trigger` = 'defect_comments_before_update'");
        $commentsTriggerExists = $stmt->rowCount() > 0;
        
        $allTriggersOk = $defectsTriggerExists && $imagesTriggerExists && $commentsTriggerExists;
        
        // Get all triggers in the database for reference
        $stmt = $db->query("SHOW TRIGGERS");
        $allTriggers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $message = "Error checking triggers: " . $e->getMessage();
    $messageType = "error";
    $allTriggersOk = false;
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
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        
        tr:hover {
            background-color: #f5f5f5;
        }
        
        .status-ok {
            color: var(--secondary-color);
        }
        
        .status-error {
            color: var(--danger-color);
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
        </div>
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType === 'error' ? 'danger' : $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">Trigger Status</div>
            <div class="card-body">
                <table>
                    <thead>
                        <tr>
                            <th>Table</th>
                            <th>Trigger Name</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>defects</td>
                            <td>defects_before_update</td>
                            <td>
                                <?php if ($defectsTriggerExists): ?>
                                    <span class="status-ok"><i class="fas fa-check-circle"></i> Installed</span>
                                <?php else: ?>
                                    <span class="status-error"><i class="fas fa-times-circle"></i> Missing</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td>defect_images</td>
                            <td>defect_images_before_update</td>
                            <td>
                                <?php if ($imagesTriggerExists): ?>
                                    <span class="status-ok"><i class="fas fa-check-circle"></i> Installed</span>
                                <?php else: ?>
                                    <span class="status-error"><i class="fas fa-times-circle"></i> Missing</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td>defect_comments</td>
                            <td>defect_comments_before_update</td>
                            <td>
                                <?php if ($commentsTriggerExists): ?>
                                    <span class="status-ok"><i class="fas fa-check-circle"></i> Installed</span>
                                <?php else: ?>
                                    <span class="status-error"><i class="fas fa-times-circle"></i> Missing</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <?php if (!$allTriggersOk): ?>
                    <form method="post">
                        <input type="hidden" name="action" value="fix_triggers">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-wrench"></i> Fix Missing Triggers
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">All Database Triggers</div>
            <div class="card-body">
                <?php if (empty($allTriggers)): ?>
                    <p>No triggers found in the database.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Trigger Name</th>
                                <th>Table</th>
                                <th>Event</th>
                                <th>Timing</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allTriggers as $trigger): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($trigger['Trigger'] ?? $trigger['TRIGGER_NAME'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($trigger['Table'] ?? $trigger['EVENT_OBJECT_TABLE'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($trigger['Event'] ?? $trigger['EVENT_MANIPULATION'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($trigger['Timing'] ?? $trigger['ACTION_TIMING'] ?? ''); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
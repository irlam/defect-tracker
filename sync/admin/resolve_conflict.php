<?php
/**
 * Sync System Conflict Resolution
 * Created: 2025-02-26 11:48:16
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
$pageTitle = 'Conflict Resolution';
$username = $_SESSION['username'] ?? 'Unknown User';
$message = '';
$messageType = '';

// Establish database connection
try {
    $db = new PDO("mysql:host={$config['db_host']};dbname={$config['db_name']}", $config['db_user'], $config['db_pass']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    $message = "Database connection failed: " . $e->getMessage();
    $messageType = "error";
}

// Process form actions
if (isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'resolve_all':
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
            
        case 'resolve_single':
            $conflictId = $_POST['conflict_id'] ?? 0;
            $resolution = $_POST['resolution'] ?? 'server_wins';
            try {
                // Update the single conflict
                $stmt = $db->prepare("UPDATE sync_conflicts 
                                     SET resolved = 1, 
                                         resolution_type = ?, 
                                         resolved_by = ?, 
                                         resolved_at = ? 
                                     WHERE id = ?");
                $stmt->execute([$resolution, $username, date('Y-m-d H:i:s'), $conflictId]);
                $conflictResolved = $stmt->rowCount();
                
                if ($conflictResolved) {
                    // Update related sync queue item
                    $stmt = $db->prepare("UPDATE sync_queue sq 
                                         JOIN sync_conflicts sc ON sq.id = sc.sync_queue_id 
                                         SET sq.status = 'pending', sq.force_sync = 1 
                                         WHERE sc.id = ?");
                    $stmt->execute([$conflictId]);
                    
                    $message = "Conflict #{$conflictId} resolved with strategy: {$resolution}. Item queued for sync.";
                    $messageType = "success";
                    
                    // Log the action
                    $stmt = $db->prepare("INSERT INTO system_logs (user_id, action, action_by, action_at, details) 
                                         VALUES (?, 'RESOLVE_SINGLE_CONFLICT', ?, ?, ?)");
                    $stmt->execute([
                        $_SESSION['user_id'], 
                        $_SESSION['user_id'], 
                        date('Y-m-d H:i:s'),
                        "Resolved conflict #{$conflictId} using {$resolution} strategy"
                    ]);
                } else {
                    $message = "Conflict #{$conflictId} not found or already resolved.";
                    $messageType = "warning";
                }
            } catch (PDOException $e) {
                $message = "Error resolving conflict: " . $e->getMessage();
                $messageType = "error";
            }
            break;
    }
}

// Get conflicts
$conflicts = [];
$conflictCount = 0;
try {
    // Get total count
    $stmt = $db->query("SELECT COUNT(*) FROM sync_conflicts WHERE resolved = 0");
    $conflictCount = $stmt->fetchColumn();
    
    // Get conflicts with pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $perPage = 20;
    $offset = ($page - 1) * $perPage;
    
    $stmt = $db->prepare("SELECT * FROM sync_conflicts 
                         WHERE resolved = 0 
                         ORDER BY created_at DESC 
                         LIMIT ? OFFSET ?");
    $stmt->bindValue(1, $perPage, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $conflicts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total pages
    $totalPages = ceil($conflictCount / $perPage);
    
} catch (PDOException $e) {
    $message = "Error retrieving conflicts: " . $e->getMessage();
    $messageType = "error";
}

// Get entity information
$entityMap = [];
try {
    // Map entity types to human-readable names based on your schema
    // For example: defects, defect_images, defect_comments, etc.
    $entityMap = [
        'defects' => 'Defect',
        'defect_images' => 'Defect Image',
        'defect_comments' => 'Comment',
        'users' => 'User',
        'projects' => 'Project'
    ];
} catch (PDOException $e) {
    // Just use default entity names if error
}

// Get device information
$deviceMap = [];
try {
    $stmt = $db->query("SELECT device_id, username, device_name FROM sync_devices");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $deviceName = !empty($row['device_name']) ? $row['device_name'] : "Device #{$row['device_id']}";
        $deviceMap[$row['device_id']] = "{$deviceName} ({$row['username']})";
    }
} catch (PDOException $e) {
    // Ignore errors and just use device IDs
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
        
        .btn-warning {
            background-color: var(--warning-color);
        }
        
        .btn-warning:hover {
            background-color: #e67e22;
        }
        
        .btn-danger {
            background-color: var(--danger-color);
        }
        
        .btn-danger:hover {
            background-color: #c0392b;
        }
        
        .btn-sm {
            padding: 4px 8px;
            font-size: 12px;
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
            display: flex;
            justify-content: space-between;
            align-items: center;
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
        
        .alert-warning {
            color: #856404;
            background-color: #fff3cd;
            border-color: #ffeeba;
        }
        
        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
        
        .badge {
            display: inline-block;
            padding: 3px 8px;
            font-size: 12px;
            font-weight: 600;
            border-radius: 20px;
            text-transform: uppercase;
        }
        
        .badge-warning {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            list-style: none;
            padding: 0;
            margin: 20px 0;
        }
        
        .pagination a, .pagination span {
            display: inline-block;
            padding: 8px 12px;
            margin: 0 4px;
            border: 1px solid var(--border-color);
            color: var(--primary-color);
            text-decoration: none;
            border-radius: 3px;
        }
        
        .pagination a:hover {
            background-color: #f5f5f5;
        }
        
        .pagination .active {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .pagination .disabled {
            color: #aaa;
            pointer-events: none;
        }
        
        select {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-right: 5px;
            font-size: 14px;
        }
        
        .diff-highlight {
            background-color: #ffffcc;
            font-weight: bold;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 20px;
            width: 80%;
            max-width: 700px;
            border-radius: 5px;
            position: relative;
        }
        
        .close-modal {
            position: absolute;
            top: 10px;
            right: 20px;
            font-size: 28px;
            cursor: pointer;
        }
        
        .data-comparison {
            display: flex;
            gap: 20px;
        }
        
        .data-column {
            flex: 1;
            padding: 15px;
            border: 1px solid var(--border-color);
            border-radius: 5px;
        }
        
        .server-data {
            background-color: #f0f7ff;
        }
        
        .client-data {
            background-color: #fff7f0;
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
            <div class="card-header">
                <span>Unresolved Conflicts (<?php echo $conflictCount; ?>)</span>
                
                <?php if ($conflictCount > 0): ?>
                <form method="post" style="display: inline-block;">
                    <input type="hidden" name="action" value="resolve_all">
                    <select name="resolution" required>
                        <option value="server_wins">Server Wins</option>
                        <option value="client_wins">Client Wins</option>
                        <option value="merge">Auto-merge</option>
                    </select>
                    <button type="submit" class="btn btn-warning" onclick="return confirm('Are you sure you want to resolve all conflicts?');">
                        <i class="fas fa-check-double"></i> Resolve All
                    </button>
                </form>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (empty($conflicts)): ?>
                    <p>No unresolved conflicts found.</p>
                <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Entity Type</th>
                            <th>Entity ID</th>
                            <th>Created</th>
                            <th>Device</th>
                            <th>Data Comparison</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($conflicts as $conflict): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($conflict['id']); ?></td>
                            <td>
                                <?php 
                                    $entityType = $conflict['entity_type'];
                                    echo htmlspecialchars($entityMap[$entityType] ?? $entityType);
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($conflict['entity_id']); ?></td>
                            <td><?php echo htmlspecialchars($conflict['created_at']); ?></td>
                            <td>
                                <?php 
                                    $deviceId = $conflict['device_id'];
                                    echo htmlspecialchars($deviceMap[$deviceId] ?? $deviceId);
                                ?>
                            </td>
                            <td>
                                <button class="btn btn-sm" onclick="showComparison(<?php echo $conflict['id']; ?>)">
                                    <i class="fas fa-code-branch"></i> Compare
                                </button>
                            </td>
                            <td>
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="action" value="resolve_single">
                                    <input type="hidden" name="conflict_id" value="<?php echo $conflict['id']; ?>">
                                    <select name="resolution" required>
                                        <option value="server_wins">Server Wins</option>
                                        <option value="client_wins">Client Wins</option>
                                        <option value="merge">Auto-merge</option>
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
                
                <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>">&laquo; Previous</a>
                    <?php else: ?>
                        <span class="disabled">&laquo; Previous</span>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>">Next &raquo;</a>
                    <?php else: ?>
                        <span class="disabled">Next &raquo;</span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Modal for data comparison -->
    <div id="comparisonModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal()">&times;</span>
            <h3>Data Comparison</h3>
            <div id="conflict-details"></div>
            <div class="data-comparison">
                <div class="data-column server-data">
                    <h4>Server Data</h4>
                    <div id="server-data"></div>
                </div>
                <div class="data-column client-data">
                    <h4>Client Data</h4>
                    <div id="client-data"></div>
                </div>
            </div>
            <div style="margin-top: 20px; text-align: center;">
                <form method="post" id="modal-resolution-form">
                    <input type="hidden" name="action" value="resolve_single">
                    <input type="hidden" name="conflict_id" id="modal-conflict-id" value="">
                    <select name="resolution" required>
                        <option value="server_wins">Server Wins</option>
                        <option value="client_wins">Client Wins</option>
                        <option value="merge">Auto-merge</option>
                    </select>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-check"></i> Resolve Conflict
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Comparison modal functionality
        function showComparison(conflictId) {
            fetch('get_conflict_data.php?id=' + conflictId)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    document.getElementById('conflict-details').innerHTML = 
                        '<p><strong>Entity:</strong> ' + data.entity_type + ' #' + data.entity_id + '</p>' +
                        '<p><strong>Created:</strong> ' + data.created_at + '</p>';
                    
                    // Format server data
                    let serverData = formatData(JSON.parse(data.server_data));
                    document.getElementById('server-data').innerHTML = serverData;
                    
                    // Format client data
                    let clientData = formatData(JSON.parse(data.client_data));
                    document.getElementById('client-data').innerHTML = clientData;
                    
                    // Set form conflict ID
                    document.getElementById('modal-conflict-id').value = conflictId;
                    
                    // Show modal
                    document.getElementById('comparisonModal').style.display = 'block';
                })
                .catch(error => {
                    console.error('Error fetching conflict data:', error);
                    alert('Error loading conflict data. Please try again.');
                });
        }
        
        function closeModal() {
            document.getElementById('comparisonModal').style.display = 'none';
        }
        
        function formatData(data) {
            if (!data) return 'No data';
            
            let html = '<table style="width:100%;">';
            for (let key in data) {
                if (data.hasOwnProperty(key)) {
                    let value = data[key];
                    if (typeof value === 'object' && value !== null) {
                        value = JSON.stringify(value);
                    }
                    html += '<tr><td><strong>' + key + ':</strong></td><td>' + value + '</td></tr>';
                }
            }
            html += '</table>';
            return html;
        }
        
        // Close modal when clicking outside of it
        window.onclick = function(event) {
            let modal = document.getElementById('comparisonModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>
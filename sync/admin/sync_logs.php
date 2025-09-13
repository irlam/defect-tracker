<?php
/**
 * Sync System - Synchronization Logs Viewer
 * Created: 2025-02-26 11:53:42
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
$pageTitle = 'Sync Logs';
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

// Handle exports
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    try {
        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="sync_logs_' . date('d-m-Y') . '.csv"');
        
        // Create output stream
        $output = fopen('php://output', 'w');
        
        // Add CSV header row
        fputcsv($output, [
            'ID', 'Username', 'Device ID', 'Start Time', 'End Time', 
            'Items Processed', 'Items Succeeded', 'Items Failed', 
            'Status', 'Details'
        ]);
        
        // Build query with filters
        $query = "SELECT * FROM sync_logs";
        $where = [];
        $params = [];
        
        if (!empty($_GET['username'])) {
            $where[] = "username LIKE ?";
            $params[] = '%' . $_GET['username'] . '%';
        }
        
        if (!empty($_GET['start_date'])) {
            $where[] = "start_time >= ?";
            $params[] = $_GET['start_date'] . ' 00:00:00';
        }
        
        if (!empty($_GET['end_date'])) {
            $where[] = "end_time <= ?";
            $params[] = $_GET['end_date'] . ' 23:59:59';
        }
        
        if (!empty($_GET['status'])) {
            $where[] = "status = ?";
            $params[] = $_GET['status'];
        }
        
        if (!empty($where)) {
            $query .= " WHERE " . implode(" AND ", $where);
        }
        
        $query .= " ORDER BY end_time DESC";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        
        // Output all rows
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, $row);
        }
        
        // Close output and exit
        fclose($output);
        exit;
    } catch (Exception $e) {
        $message = "Export error: " . $e->getMessage();
        $messageType = "error";
    }
}

// Process filters
$filters = [
    'username' => $_GET['username'] ?? '',
    'start_date' => $_GET['start_date'] ?? '',
    'end_date' => $_GET['end_date'] ?? '',
    'status' => $_GET['status'] ?? ''
];

// Get sync logs with pagination and filters
$logsCount = 0;
$logs = [];
try {
    // Count total matching logs
    $countQuery = "SELECT COUNT(*) FROM sync_logs";
    $whereClause = [];
    $params = [];
    
    if (!empty($filters['username'])) {
        $whereClause[] = "username LIKE ?";
        $params[] = '%' . $filters['username'] . '%';
    }
    
    if (!empty($filters['start_date'])) {
        $whereClause[] = "start_time >= ?";
        $params[] = $filters['start_date'] . ' 00:00:00';
    }
    
    if (!empty($filters['end_date'])) {
        $whereClause[] = "end_time <= ?";
        $params[] = $filters['end_date'] . ' 23:59:59';
    }
    
    if (!empty($filters['status'])) {
        $whereClause[] = "status = ?";
        $params[] = $filters['status'];
    }
    
    if (!empty($whereClause)) {
        $countQuery .= " WHERE " . implode(" AND ", $whereClause);
    }
    
    $stmt = $db->prepare($countQuery);
    $stmt->execute($params);
    $logsCount = $stmt->fetchColumn();
    
    // Get paginated results
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $perPage = 25;
    $offset = ($page - 1) * $perPage;
    
    // Build query with same filters
    $query = "SELECT * FROM sync_logs";
    if (!empty($whereClause)) {
        $query .= " WHERE " . implode(" AND ", $whereClause);
    }
    $query .= " ORDER BY end_time DESC LIMIT ? OFFSET ?";
    
    $stmt = $db->prepare($query);
    foreach ($params as $index => $param) {
        $stmt->bindValue($index + 1, $param);
    }
    $stmt->bindValue(count($params) + 1, $perPage, PDO::PARAM_INT);
    $stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate total pages
    $totalPages = ceil($logsCount / $perPage);
    
} catch (PDOException $e) {
    $message = "Error retrieving logs: " . $e->getMessage();
    $messageType = "error";
}

// Get unique usernames for filter dropdown
$usernames = [];
try {
    $stmt = $db->query("SELECT DISTINCT username FROM sync_logs ORDER BY username");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $usernames[] = $row['username'];
    }
} catch (PDOException $e) {
    // Silently fail, just won't show the dropdown
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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .filters {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .filter-group input, .filter-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .filter-buttons {
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
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .close-modal {
            position: absolute;
            top: 10px;
            right: 20px;
            font-size: 28px;
            cursor: pointer;
        }
        
        @media (max-width: 768px) {
            .filter-row {
                flex-direction: column;
                gap: 10px;
            }
            
            .filter-group {
                min-width: 100%;
            }
            
            .filter-buttons {
                flex-direction: column;
                gap: 10px;
            }
            
            .filter-buttons .btn {
                width: 100%;
                margin-bottom: 5px;
            }
            
            .card-header {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
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
        </div>
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType === 'error' ? 'danger' : $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <span>Filter Sync Logs</span>
            </div>
            <div class="card-body">
                <form method="get" action="sync_logs.php">
                    <div class="filters">
                        <div class="filter-row">
                            <div class="filter-group">
                                <label for="username">User:</label>
                                <select name="username" id="username">
                                    <option value="">All Users</option>
                                    <?php foreach ($usernames as $name): ?>
                                        <option value="<?php echo htmlspecialchars($name); ?>" <?php echo $filters['username'] === $name ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label for="start_date">Start Date:</label>
                                <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($filters['start_date']); ?>">
                            </div>
                            <div class="filter-group">
                                <label for="end_date">End Date:</label>
                                <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($filters['end_date']); ?>">
                            </div>
                            <div class="filter-group">
                                <label for="status">Status:</label>
                                <select name="status" id="status">
                                    <option value="">All Statuses</option>
                                    <option value="success" <?php echo $filters['status'] === 'success' ? 'selected' : ''; ?>>Success</option>
                                    <option value="partial" <?php echo $filters['status'] === 'partial' ? 'selected' : ''; ?>>Partial</option>
                                    <option value="failed" <?php echo $filters['status'] === 'failed' ? 'selected' : ''; ?>>Failed</option>
                                </select>
                            </div>
                        </div>
                        <div class="filter-buttons">
                            <div>
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-filter"></i> Apply Filters
                                </button>
                                <a href="sync_logs.php" class="btn">
                                    <i class="fas fa-undo"></i> Clear Filters
                                </a>
                            </div>
                            <div>
                                <?php 
                                // Build export URL with current filters
                                $exportUrl = 'sync_logs.php?export=csv';
                                foreach ($filters as $key => $value) {
                                    if (!empty($value)) {
                                        $exportUrl .= '&' . $key . '=' . urlencode($value);
                                    }
                                }
                                ?>
                                <a href="<?php echo $exportUrl; ?>" class="btn">
                                    <i class="fas fa-file-csv"></i> Export to CSV
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <span>Sync Logs (<?php echo $logsCount; ?> total)</span>
                <span>Current Time (UTC): <?php echo date('d-m-Y H:i:s'); ?></span>
            </div>
            <div class="card-body">
                <?php if (empty($logs)): ?>
                    <p>No sync logs found matching your criteria.</p>
                <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Start Time</th>
                            <th>End Time</th>
                            <th>Duration</th>
                            <th>Items Processed</th>
                            <th>Success/Failed</th>
                            <th>Status</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($log['id']); ?></td>
                            <td><?php echo htmlspecialchars($log['username']); ?></td>
                            <td><?php echo htmlspecialchars($log['start_time']); ?></td>
                            <td><?php echo htmlspecialchars($log['end_time']); ?></td>
                            <td>
                                <?php
                                    // Calculate duration in seconds
                                    $start = strtotime($log['start_time']);
                                    $end = strtotime($log['end_time']);
                                    $duration = $end - $start;
                                    echo $duration . 's';
                                ?>
                            </td>
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
                                <button class="btn" onclick="showDetails(<?php echo htmlspecialchars($log['id']); ?>)">
                                    <i class="fas fa-info-circle"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php 
                    // Create base URL for pagination that preserves filters
                    $baseUrl = 'sync_logs.php?';
                    foreach ($filters as $key => $value) {
                        if (!empty($value)) {
                            $baseUrl .= $key . '=' . urlencode($value) . '&';
                        }
                    }
                    ?>
                    
                    <?php if ($page > 1): ?>
                        <a href="<?php echo $baseUrl; ?>page=<?php echo $page - 1; ?>">&laquo; Previous</a>
                    <?php else: ?>
                        <span class="disabled">&laquo; Previous</span>
                    <?php endif; ?>
                    
                    <?php 
                    // Show a limited number of page links
                    $maxPages = 5;
                    $startPage = max(1, $page - floor($maxPages / 2));
                    $endPage = min($totalPages, $startPage + $maxPages - 1);
                    
                    // Adjust start page if needed
                    if ($endPage - $startPage + 1 < $maxPages) {
                        $startPage = max(1, $endPage - $maxPages + 1);
                    }
                    
                    // Show first page if not in range
                    if ($startPage > 1) {
                        echo '<a href="' . $baseUrl . 'page=1">1</a>';
                        if ($startPage > 2) {
                            echo '<span class="disabled">...</span>';
                        }
                    }
                    
                    // Show page numbers
                    for ($i = $startPage; $i <= $endPage; $i++): 
                    ?>
                        <?php if ($i == $page): ?>
                            <span class="active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="<?php echo $baseUrl; ?>page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    // Show last page if not in range
                    <?php if ($endPage < $totalPages): ?>
                        <?php if ($endPage < $totalPages - 1): ?>
                            <span class="disabled">...</span>
                        <?php endif; ?>
                        <a href="<?php echo $baseUrl; ?>page=<?php echo $totalPages; ?>"><?php echo $totalPages; ?></a>
                    <?php endif; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="<?php echo $baseUrl; ?>page=<?php echo $page + 1; ?>">Next &raquo;</a>
                    <?php else: ?>
                        <span class="disabled">Next &raquo;</span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Modal for log details -->
    <div id="detailsModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal()">&times;</span>
            <h3>Sync Log Details</h3>
            <div id="log-details">Loading...</div>
        </div>
    </div>
    
    <script>
        // Modal functionality
        function showDetails(logId) {
            let modal = document.getElementById('detailsModal');
            let content = document.getElementById('log-details');
            content.innerHTML = 'Loading details...';
            modal.style.display = 'block';
            
            // Fetch log details from server
            fetch('get_sync_log.php?id=' + logId)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        content.innerHTML = '<div class="alert alert-danger">' + data.error + '</div>';
                        return;
                    }
                    
                    let html = '<table style="width:100%;">';
                    
                    // Basic info
                    html += '<tr><th colspan="2" style="background-color: #f5f5f5;">Basic Information</th></tr>';
                    html += '<tr><td><strong>ID:</strong></td><td>' + data.id + '</td></tr>';
                    html += '<tr><td><strong>User:</strong></td><td>' + data.username + '</td></tr>';
                    html += '<tr><td><strong>Device ID:</strong></td><td>' + data.device_id + '</td></tr>';
                    html += '<tr><td><strong>Status:</strong></td><td>' + 
                        (data.status === 'success' ? 
                            '<span class="badge badge-success">Success</span>' : 
                            (data.status === 'failed' ? 
                                '<span class="badge badge-danger">Failed</span>' : 
                                '<span class="badge badge-warning">Partial</span>')) + 
                        '</td></tr>';
                    
                    // Time information
                    html += '<tr><th colspan="2" style="background-color: #f5f5f5;">Time Information</th></tr>';
                    html += '<tr><td><strong>Start Time:</strong></td><td>' + data.start_time + '</td></tr>';
                    html += '<tr><td><strong>End Time:</strong></td><td>' + data.end_time + '</td></tr>';
                    
                    // Calculate duration
                    let start = new Date(data.start_time);
                    let end = new Date(data.end_time);
                    let duration = (end - start) / 1000; // in seconds
                    html += '<tr><td><strong>Duration:</strong></td><td>' + duration + ' seconds</td></tr>';
                    
                    // Processing stats
                    html += '<tr><th colspan="2" style="background-color: #f5f5f5;">Processing Statistics</th></tr>';
                    html += '<tr><td><strong>Items Processed:</strong></td><td>' + data.items_processed + '</td></tr>';
                    html += '<tr><td><strong>Items Succeeded:</strong></td><td>' + data.items_succeeded + '</td></tr>';
                    html += '<tr><td><strong>Items Failed:</strong></td><td>' + data.items_failed + '</td></tr>';
                    
                    // Details information
                    if (data.details) {
                        html += '<tr><th colspan="2" style="background-color: #f5f5f5;">Details</th></tr>';
                        html += '<tr><td colspan="2"><pre style="white-space: pre-wrap;">' + data.details + '</pre></td></tr>';
                    }
                    
                    html += '</table>';
                    
                    content.innerHTML = html;
                })
                .catch(error => {
                    console.error('Error fetching log details:', error);
                    content.innerHTML = '<div class="alert alert-danger">Error loading details. Please try again.</div>';
                });
        }
        
        function closeModal() {
            document.getElementById('detailsModal').style.display = 'none';
        }
        
        // Close modal when clicking outside of it
        window.onclick = function(event) {
            let modal = document.getElementById('detailsModal');
            if (event.target == modal) {
                closeModal();
            }
        }
        
        // Date range validation
        document.addEventListener('DOMContentLoaded', function() {
            const startDate = document.getElementById('start_date');
            const endDate = document.getElementById('end_date');
            
            startDate.addEventListener('change', function() {
                if (endDate.value && startDate.value > endDate.value) {
                    endDate.value = startDate.value;
                }
            });
            
            endDate.addEventListener('change', function() {
                if (startDate.value && startDate.value > endDate.value) {
                    startDate.value = endDate.value;
                }
            });
        });
    </script>
</body>
</html>
<?php
/**
 * Sync System - Performance Metrics Dashboard
 * Created: 2025-02-26 13:14:31
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
$pageTitle = 'Performance Metrics';
$username = $_SESSION['username'] ?? 'Unknown User';
$message = '';
$messageType = '';

// Set time range for stats
$timeRange = $_GET['range'] ?? '30days';
$rangeOptions = [
    '7days' => '7 Days',
    '30days' => '30 Days', 
    '90days' => '90 Days',
    '1year' => '1 Year',
    'all' => 'All Time'
];

// Convert range to SQL interval
$sqlInterval = '30 DAY';
switch ($timeRange) {
    case '7days':
        $sqlInterval = '7 DAY';
        break;
    case '30days':
        $sqlInterval = '30 DAY';
        break;
    case '90days':
        $sqlInterval = '90 DAY';
        break;
    case '1year':
        $sqlInterval = '1 YEAR';
        break;
    case 'all':
        $sqlInterval = '100 YEAR'; // Effectively all records
        break;
}

// Establish database connection
try {
    $db = new PDO("mysql:host={$config['db_host']};dbname={$config['db_name']}", $config['db_user'], $config['db_pass']);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    $message = "Database connection failed: " . $e->getMessage();
    $messageType = "error";
    $db = null;
}

// Initialize empty performance data
$performanceData = [
    'successRate' => 0,
    'avgSyncTime' => 0,
    'totalSyncs' => 0,
    'totalItems' => 0,
    'conflictRate' => 0,
    'syncsByDay' => [],
    'itemsByType' => [],
    'topUsers' => [],
    'topDevices' => [],
    'syncStatus' => []
];

if ($db) {
    try {
        // Get total sync count - FIX: Don't use parameter binding for INTERVAL
        $sql = "SELECT COUNT(*) FROM sync_logs WHERE start_time > DATE_SUB(NOW(), INTERVAL {$sqlInterval})";
        $stmt = $db->query($sql);
        $performanceData['totalSyncs'] = (int)$stmt->fetchColumn();
        
        // Get total items processed - FIX: Don't use parameter binding for INTERVAL
        $sql = "SELECT SUM(items_processed) FROM sync_logs WHERE start_time > DATE_SUB(NOW(), INTERVAL {$sqlInterval})";
        $stmt = $db->query($sql);
        $performanceData['totalItems'] = (int)$stmt->fetchColumn();
        
        // Calculate success rate - FIX: Don't use parameter binding for INTERVAL
        if ($performanceData['totalItems'] > 0) {
            $sql = "SELECT SUM(items_succeeded) FROM sync_logs WHERE start_time > DATE_SUB(NOW(), INTERVAL {$sqlInterval})";
            $stmt = $db->query($sql);
            $totalSucceeded = (int)$stmt->fetchColumn();
            $performanceData['successRate'] = round(($totalSucceeded / $performanceData['totalItems']) * 100, 2);
        }
        
        // Get average sync time - FIX: Don't use parameter binding for INTERVAL
        $sql = "SELECT AVG(TIMESTAMPDIFF(SECOND, start_time, end_time)) FROM sync_logs WHERE start_time > DATE_SUB(NOW(), INTERVAL {$sqlInterval})";
        $stmt = $db->query($sql);
        $performanceData['avgSyncTime'] = round((float)$stmt->fetchColumn(), 2);
        
        // Calculate conflict rate - FIX: Don't use parameter binding for INTERVAL
        $sql = "SELECT COUNT(*) FROM sync_conflicts WHERE created_at > DATE_SUB(NOW(), INTERVAL {$sqlInterval})";
        $stmt = $db->query($sql);
        $totalConflicts = (int)$stmt->fetchColumn();
        
        $sql = "SELECT COUNT(*) FROM sync_queue WHERE created_at > DATE_SUB(NOW(), INTERVAL {$sqlInterval})";
        $stmt = $db->query($sql);
        $totalQueueItems = (int)$stmt->fetchColumn();
        
        if ($totalQueueItems > 0) {
            $performanceData['conflictRate'] = round(($totalConflicts / $totalQueueItems) * 100, 2);
        }
		        // Get syncs by day - FIX: Don't use parameter binding for INTERVAL
        $sql = "SELECT 
                DATE(end_time) as sync_date, 
                COUNT(*) as sync_count
            FROM sync_logs
            WHERE start_time > DATE_SUB(NOW(), INTERVAL {$sqlInterval})
            GROUP BY DATE(end_time)
            ORDER BY DATE(end_time)";
        $stmt = $db->query($sql);
        $syncsByDay = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get average time by day in a separate query
        $syncTimesByDay = [];
        foreach ($syncsByDay as $day) {
            $stmt = $db->prepare("SELECT 
                    AVG(TIMESTAMPDIFF(SECOND, start_time, end_time)) as avg_time
                FROM sync_logs
                WHERE DATE(end_time) = ?");
            $stmt->execute([$day['sync_date']]);
            $avgTime = $stmt->fetchColumn();
            $syncTimesByDay[$day['sync_date']] = round((float)$avgTime, 2);
        }
        
        // Get success rate by day in separate queries
        $syncSuccessByDay = [];
        foreach ($syncsByDay as $day) {
            $stmt = $db->prepare("SELECT 
                    SUM(items_processed) as total_processed,
                    SUM(items_succeeded) as total_succeeded
                FROM sync_logs
                WHERE DATE(end_time) = ?");
            $stmt->execute([$day['sync_date']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $successRate = 0;
            if (!empty($result['total_processed'])) {
                $successRate = ($result['total_succeeded'] / $result['total_processed']) * 100;
            }
            $syncSuccessByDay[$day['sync_date']] = round($successRate, 2);
        }
        
        // Combine the day data
        foreach ($syncsByDay as &$day) {
            $day['avg_time'] = $syncTimesByDay[$day['sync_date']] ?? 0;
            $day['success_rate'] = $syncSuccessByDay[$day['sync_date']] ?? 0;
        }
        $performanceData['syncsByDay'] = $syncsByDay;
        
        // Get items by type - FIX: Don't use parameter binding for INTERVAL
        $sql = "SELECT entity_type, COUNT(*) as count FROM sync_queue WHERE created_at > DATE_SUB(NOW(), INTERVAL {$sqlInterval}) GROUP BY entity_type";
        $stmt = $db->query($sql);
        $entityTypes = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $itemsByType = [];
        foreach ($entityTypes as $type => $count) {
            // For these individual entity type queries, we can safely use parameter binding 
            // for the entity_type but not for the interval
            
            // Get completed count
            $sql = "SELECT COUNT(*) FROM sync_queue WHERE entity_type = ? AND status = 'completed' AND created_at > DATE_SUB(NOW(), INTERVAL {$sqlInterval})";
            $stmt = $db->prepare($sql);
            $stmt->execute([$type]);
            $completed = (int)$stmt->fetchColumn();
            
            // Get failed count
            $sql = "SELECT COUNT(*) FROM sync_queue WHERE entity_type = ? AND status = 'failed' AND created_at > DATE_SUB(NOW(), INTERVAL {$sqlInterval})";
            $stmt = $db->prepare($sql);
            $stmt->execute([$type]);
            $failed = (int)$stmt->fetchColumn();
            
            // Get pending count
            $sql = "SELECT COUNT(*) FROM sync_queue WHERE entity_type = ? AND status = 'pending' AND created_at > DATE_SUB(NOW(), INTERVAL {$sqlInterval})";
            $stmt = $db->prepare($sql);
            $stmt->execute([$type]);
            $pending = (int)$stmt->fetchColumn();
            
            $itemsByType[] = [
                'entity_type' => $type,
                'count' => $count,
                'completed' => $completed,
                'failed' => $failed,
                'pending' => $pending
            ];
        }
        $performanceData['itemsByType'] = $itemsByType;
        
        // Get top users - FIX: Don't use parameter binding for INTERVAL
        $sql = "SELECT username, COUNT(*) as sync_count FROM sync_logs WHERE start_time > DATE_SUB(NOW(), INTERVAL {$sqlInterval}) GROUP BY username ORDER BY sync_count DESC LIMIT 10";
        $stmt = $db->query($sql);
        $topUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($topUsers as &$user) {
            // Get items processed - safe to use parameter binding for username but not interval
            $sql = "SELECT SUM(items_processed) FROM sync_logs WHERE username = ? AND start_time > DATE_SUB(NOW(), INTERVAL {$sqlInterval})";
            $stmt = $db->prepare($sql);
            $stmt->execute([$user['username']]);
            $user['items_processed'] = (int)$stmt->fetchColumn();
            
            // Get average time - safe to use parameter binding for username but not interval
            $sql = "SELECT AVG(TIMESTAMPDIFF(SECOND, start_time, end_time)) FROM sync_logs WHERE username = ? AND start_time > DATE_SUB(NOW(), INTERVAL {$sqlInterval})";
            $stmt = $db->prepare($sql);
            $stmt->execute([$user['username']]);
            $user['avg_time'] = round((float)$stmt->fetchColumn(), 2);
        }
        $performanceData['topUsers'] = $topUsers;
		        // Get top devices - FIX: Don't use parameter binding for INTERVAL
        $sql = "SELECT device_id, COUNT(*) as sync_count FROM sync_logs WHERE start_time > DATE_SUB(NOW(), INTERVAL {$sqlInterval}) GROUP BY device_id ORDER BY sync_count DESC LIMIT 10";
        $stmt = $db->query($sql);
        $topDevices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($topDevices as &$device) {
            // Get items processed - safe to use parameter binding for device_id but not interval
            $sql = "SELECT SUM(items_processed) FROM sync_logs WHERE device_id = ? AND start_time > DATE_SUB(NOW(), INTERVAL {$sqlInterval})";
            $stmt = $db->prepare($sql);
            $stmt->execute([$device['device_id']]);
            $device['items_processed'] = (int)$stmt->fetchColumn();
            
            // Get average time - safe to use parameter binding for device_id but not interval
            $sql = "SELECT AVG(TIMESTAMPDIFF(SECOND, start_time, end_time)) FROM sync_logs WHERE device_id = ? AND start_time > DATE_SUB(NOW(), INTERVAL {$sqlInterval})";
            $stmt = $db->prepare($sql);
            $stmt->execute([$device['device_id']]);
            $device['avg_time'] = round((float)$stmt->fetchColumn(), 2);
            
            // Get last sync time - safe to use parameter binding for device_id but not interval
            $sql = "SELECT MAX(end_time) FROM sync_logs WHERE device_id = ? AND start_time > DATE_SUB(NOW(), INTERVAL {$sqlInterval})";
            $stmt = $db->prepare($sql);
            $stmt->execute([$device['device_id']]);
            $device['last_sync'] = $stmt->fetchColumn();
        }
        $performanceData['topDevices'] = $topDevices;
        
        // Get sync status distribution - FIX: Don't use parameter binding for INTERVAL
        $sql = "SELECT status, COUNT(*) as count FROM sync_logs WHERE start_time > DATE_SUB(NOW(), INTERVAL {$sqlInterval}) GROUP BY status";
        $stmt = $db->query($sql);
        $statusDistribution = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $statusDistribution[$row['status']] = $row['count'];
        }
        $performanceData['syncStatus'] = $statusDistribution;
        
    } catch (PDOException $e) {
        $message = "Error retrieving performance data: " . $e->getMessage();
        $messageType = "error";
    }
}

// Prepare chart data
$chartData = [
    'dates' => [],
    'syncCounts' => [],
    'avgTimes' => [],
    'successRates' => []
];

foreach ($performanceData['syncsByDay'] as $day) {
    $chartData['dates'][] = $day['sync_date'];
    $chartData['syncCounts'][] = $day['sync_count'];
    $chartData['avgTimes'][] = $day['avg_time'];
    $chartData['successRates'][] = $day['success_rate'];
}

// Prepare data for entity type chart
$entityChartData = [
    'labels' => [],
    'completed' => [],
    'failed' => [],
    'pending' => []
];

foreach ($performanceData['itemsByType'] as $type) {
    $entityChartData['labels'][] = $type['entity_type'];
    $entityChartData['completed'][] = $type['completed'];
    $entityChartData['failed'][] = $type['failed'];
    $entityChartData['pending'][] = $type['pending'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - Sync Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <!-- Chart.js for visualizations -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
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
        
        .btn-active {
            background-color: #2980b9;
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
        
        .stats-row {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .stat-box {
            flex: 1;
            min-width: 180px;
            padding: 20px;
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
            font-size: 32px;
            font-weight: bold;
            color: var(--primary-color);
            margin: 15px 0;
        }
        
        .stat-box .unit {
            font-size: 14px;
            color: #777;
            margin-top: 5px;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            margin-top: 20px;
        }
        
        .chart-row {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .chart-col {
            flex: 1;
            min-width: 300px;
        }
        
        .filter-bar {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
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
        
        tbody tr:hover {
            background-color: #f5f5f5;
        }
        
        @media (max-width: 768px) {
            .stats-row {
                flex-direction: column;
                gap: 10px;
            }
            
            .chart-row {
                flex-direction: column;
            }
            
            .chart-col {
                min-width: 100%;
            }
            
            .filter-bar {
                flex-direction: column;
                gap: 10px;
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
        
        <div class="filter-bar">
            <div>
                <strong>Time Range:</strong>
                <?php foreach ($rangeOptions as $value => $label): ?>
                    <a href="?range=<?php echo $value; ?>" 
                       class="btn <?php echo $timeRange === $value ? 'btn-active' : ''; ?>">
                        <?php echo $label; ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <div>
                <span>Last Updated: <?php echo date('Y-m-d H:i:s'); ?> UTC</span>
                <a href="?range=<?php echo $timeRange; ?>" class="btn">
                    <i class="fas fa-sync-alt"></i> Refresh
                </a>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">Sync Performance Overview</div>
            <div class="stats-row">
                <div class="stat-box">
                    <h3>Success Rate</h3>
                    <div class="value"><?php echo $performanceData['successRate']; ?>%</div>
                    <div class="unit">Successful Items</div>
                </div>
                <div class="stat-box">
                    <h3>Average Sync Time</h3>
                    <div class="value"><?php echo $performanceData['avgSyncTime']; ?></div>
                    <div class="unit">Seconds</div>
                </div>
                <div class="stat-box">
                    <h3>Total Syncs</h3>
                    <div class="value"><?php echo number_format($performanceData['totalSyncs']); ?></div>
                    <div class="unit">Sync Operations</div>
                </div>
                <div class="stat-box">
                    <h3>Total Items</h3>
                    <div class="value"><?php echo number_format($performanceData['totalItems']); ?></div>
                    <div class="unit">Synced Items</div>
                </div>
                <div class="stat-box">
                    <h3>Conflict Rate</h3>
                    <div class="value"><?php echo $performanceData['conflictRate']; ?>%</div>
                    <div class="unit">Items with Conflicts</div>
                </div>
            </div>
        </div>
        
        <div class="chart-row">
            <div class="chart-col">
                <div class="card">
                    <div class="card-header">Sync Activity Over Time</div>
                    <div class="chart-container">
                        <canvas id="syncActivityChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="chart-col">
                <div class="card">
                    <div class="card-header">Average Sync Duration</div>
                    <div class="chart-container">
                        <canvas id="syncDurationChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="chart-row">
            <div class="chart-col">
                <div class="card">
                    <div class="card-header">Success Rate Over Time</div>
                    <div class="chart-container">
                        <canvas id="successRateChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="chart-col">
                <div class="card">
                    <div class="card-header">Items by Entity Type</div>
                    <div class="chart-container">
                        <canvas id="entityTypeChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">Sync Status Distribution</div>
            <div class="chart-container" style="height: 200px;">
                <canvas id="statusDistributionChart"></canvas>
            </div>
        </div>
        
        <div class="chart-row">
            <div class="chart-col">
                <div class="card">
                    <div class="card-header">Top Users by Sync Count</div>
                    <table>
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Syncs</th>
                                <th>Items</th>
                                <th>Avg Time (s)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($performanceData['topUsers'])): ?>
                                <tr>
                                    <td colspan="4" style="text-align: center;">No user data available</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($performanceData['topUsers'] as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo number_format($user['sync_count']); ?></td>
                                    <td><?php echo number_format($user['items_processed']); ?></td>
                                    <td><?php echo $user['avg_time']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="chart-col">
                <div class="card">
                    <div class="card-header">Top Devices by Sync Count</div>
                    <table>
                        <thead>
                            <tr>
                                <th>Device ID</th>
                                <th>Syncs</th>
                                <th>Items</th>
                                <th>Avg Time (s)</th>
                                <th>Last Sync</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($performanceData['topDevices'])): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center;">No device data available</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($performanceData['topDevices'] as $device): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($device['device_id']); ?></td>
                                    <td><?php echo number_format($device['sync_count']); ?></td>
                                    <td><?php echo number_format($device['items_processed']); ?></td>
                                    <td><?php echo $device['avg_time']; ?></td>
                                    <td><?php echo htmlspecialchars($device['last_sync']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Chart initialization
        document.addEventListener('DOMContentLoaded', function() {
            // Chart.js global defaults
            Chart.defaults.font.family = "'Segoe UI', 'Helvetica Neue', 'Helvetica', 'Arial', sans-serif";
            Chart.defaults.color = '#333';
            
            // Shared color scheme
            const colors = {
                blue: 'rgba(52, 152, 219, 0.7)',
                green: 'rgba(46, 204, 113, 0.7)',
                red: 'rgba(231, 76, 60, 0.7)',
                orange: 'rgba(243, 156, 18, 0.7)',
                purple: 'rgba(155, 89, 182, 0.7)',
                turquoise: 'rgba(26, 188, 156, 0.7)'
            };
            
            // Sync Activity Chart
            const syncActivityCtx = document.getElementById('syncActivityChart').getContext('2d');
            const syncActivityChart = new Chart(syncActivityCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($chartData['dates']); ?>,
                    datasets: [{
                        label: 'Number of Syncs',
                        data: <?php echo json_encode($chartData['syncCounts']); ?>,
                        backgroundColor: colors.blue,
                        borderColor: 'rgba(52, 152, 219, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Syncs'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Date'
                            }
                        }
                    }
                }
            });
            
            // Sync Duration Chart
            const syncDurationCtx = document.getElementById('syncDurationChart').getContext('2d');
            const syncDurationChart = new Chart(syncDurationCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($chartData['dates']); ?>,
                    datasets: [{
                        label: 'Average Sync Duration (s)',
                        data: <?php echo json_encode($chartData['avgTimes']); ?>,
                        backgroundColor: 'rgba(155, 89, 182, 0.2)',
                        borderColor: colors.purple,
                        borderWidth: 2,
                        tension: 0.3,
                        fill: true,
                        pointRadius: 3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Duration (seconds)'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Date'
                            }
                        }
                    }
                }
            });
            
            // Success Rate Chart
            const successRateCtx = document.getElementById('successRateChart').getContext('2d');
            const successRateChart = new Chart(successRateCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($chartData['dates']); ?>,
                    datasets: [{
                        label: 'Success Rate (%)',
                        data: <?php echo json_encode($chartData['successRates']); ?>,
                        backgroundColor: 'rgba(46, 204, 113, 0.2)',
                        borderColor: colors.green,
                        borderWidth: 2,
                        tension: 0.3,
                        fill: true,
                        pointRadius: 3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            min: 0,
                            max: 100,
                            title: {
                                display: true,
                                text: 'Success Rate (%)'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Date'
                            }
                        }
                    }
                }
            });
            
            // Entity Type Chart
            const entityTypeCtx = document.getElementById('entityTypeChart').getContext('2d');
            const entityTypeChart = new Chart(entityTypeCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($entityChartData['labels']); ?>,
                    datasets: [
                        {
                            label: 'Completed',
                            data: <?php echo json_encode($entityChartData['completed']); ?>,
                            backgroundColor: colors.green,
                            borderColor: 'rgba(46, 204, 113, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Failed',
                            data: <?php echo json_encode($entityChartData['failed']); ?>,
                            backgroundColor: colors.red,
                            borderColor: 'rgba(231, 76, 60, 1)',
                            borderWidth: 1
                        },
                        {
                            label: 'Pending',
                            data: <?php echo json_encode($entityChartData['pending']); ?>,
                            backgroundColor: colors.orange,
                            borderColor: 'rgba(243, 156, 18, 1)',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Items'
                            },
                            stacked: true
                        },
                        x: {
                            stacked: true,
                            title: {
                                display: true,
                                text: 'Entity Type'
                            }
                        }
                    }
                }
            });
            
            // Status Distribution Chart
            const statusDistributionCtx = document.getElementById('statusDistributionChart').getContext('2d');
            const statusLabels = Object.keys(<?php echo json_encode($performanceData['syncStatus']); ?>);
            const statusValues = Object.values(<?php echo json_encode($performanceData['syncStatus']); ?>).map(Number);
            
            const statusColors = {
                'success': colors.green,
                'failed': colors.red,
                'partial': colors.orange
            };
            
            const statusBackgroundColors = statusLabels.map(label => statusColors[label] || colors.blue);
            
            const statusDistributionChart = new Chart(statusDistributionCtx, {
                type: 'doughnut',
                data: {
                    labels: statusLabels.map(label => label.charAt(0).toUpperCase() + label.slice(1)),
                    datasets: [{
                        data: statusValues,
                        backgroundColor: statusBackgroundColors,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>
<?php
// reports.php
// Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): 2025-01-27 19:36:37
// Current User's Login: irlam

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Define INCLUDED constant for navbar security
define('INCLUDED', true);

require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/navbar.php'; // Include the navbar file

$pageTitle = 'Reports Dashboard';
$currentUser = $_SESSION['username'];
$currentUserId = (int)$_SESSION['user_id']; // Retrieve the user ID from the session

// Initialize $start_date and $end_date
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

try {
    $database = new Database();
    $db = $database->getConnection();

    // Initialize the Navbar class
    $navbar = new Navbar($db, $currentUserId);

    // Initialize arrays
    $defectStats = [
        'total' => 0,
        'open' => 0,
        'in_progress' => 0,
        'resolved' => 0,
        'closed' => 0
    ];

    $projectStats = [
        'total' => 0,
        'active' => 0,
        'completed' => 0,
        'pending' => 0,
        'on-hold' => 0
    ];

    // Get defect statistics
    $stmt = $db->prepare("
        SELECT 
            status,
            COUNT(*) as count
        FROM defects 
        WHERE created_at BETWEEN :start_date AND :end_date
        GROUP BY status
    ");
    $stmt->execute([
        ':start_date' => $start_date . ' 00:00:00',
        ':end_date' => $end_date . ' 23:59:59'
    ]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $defectStats[$row['status']] = $row['count'];
        $defectStats['total'] += $row['count'];
    }

    // Get project statistics
    $stmt = $db->prepare("
        SELECT 
            status,
            COUNT(*) as count
        FROM projects 
        WHERE created_at BETWEEN :start_date AND :end_date
        GROUP BY status
    ");
    $stmt->execute([
        ':start_date' => $start_date . ' 00:00:00',
        ':end_date' => $end_date . ' 23:59:59'
    ]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $projectStats[$row['status']] = $row['count'];
        $projectStats['total'] += $row['count'];
    }

    // Enhanced Contractor Performance Query (Fixed JOIN)
    $contractorPerformance = $db->prepare("
        SELECT 
            c.company_name,
            c.trade,
            COUNT(DISTINCT d.id) as total_defects,
            SUM(CASE WHEN d.priority = 'high' THEN 1 ELSE 0 END) as high_priority_defects,
            SUM(CASE WHEN d.status = 'open' THEN 1 ELSE 0 END) as open_defects,
            SUM(CASE WHEN d.status = 'closed' THEN 1 ELSE 0 END) as closed_defects,
            ROUND(
                (SUM(CASE WHEN d.status = 'closed' THEN 1 ELSE 0 END) * 100.0) / 
                NULLIF(COUNT(DISTINCT d.id), 0),
                1
            ) as resolution_rate,
            ROUND(
                AVG(CASE 
                    WHEN d.status = 'closed' 
                    THEN TIMESTAMPDIFF(DAY, d.created_at, d.updated_at)
                    ELSE NULL
                END),
                1
            ) as avg_resolution_time,
            COUNT(DISTINCT d.project_id) as total_projects
        FROM 
            contractors c
            LEFT JOIN defects d ON c.id = d.contractor_id 
                AND d.created_at BETWEEN :start_date AND :end_date
        WHERE 
            c.status = 'active'
        GROUP BY 
            c.id, c.company_name, c.trade
        HAVING 
            total_defects > 0
        ORDER BY 
            total_defects DESC
        LIMIT 10
    ");
    $contractorPerformance->execute([
        ':start_date' => $start_date . ' 00:00:00',
        ':end_date' => $end_date . ' 23:59:59'
    ]);
    $contractorStats = $contractorPerformance->fetchAll(PDO::FETCH_ASSOC);

    // Get recent defects with enhanced details (Fixed JOIN)
    $recentDefects = $db->prepare("
        SELECT 
            d.id,
            d.title,
            d.description,
            d.status,
            d.priority,
            d.created_at,
            d.updated_at,
            p.name as project_name,
            c.company_name,
            c.trade,
            u.username as reported_by
        FROM defects d
        LEFT JOIN projects p ON d.project_id = p.id
        LEFT JOIN contractors c ON d.contractor_id = c.id
        LEFT JOIN users u ON d.created_by = u.id
        WHERE d.created_at BETWEEN :start_date AND :end_date
        ORDER BY d.created_at DESC
        LIMIT 10
    ");
    $recentDefects->execute([
        ':start_date' => $start_date . ' 00:00:00',
        ':end_date' => $end_date . ' 23:59:59'
    ]);
    $recentDefectsList = $recentDefects->fetchAll(PDO::FETCH_ASSOC);

    // Get priority distribution with trends
    $priorityQuery = "
        SELECT 
            priority,
            COUNT(*) as count,
            ROUND((COUNT(*) * 100.0) / SUM(COUNT(*)) OVER(), 1) as percentage,
            SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as resolved_count,
            ROUND(
                (SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) * 100.0) / 
                COUNT(*),
                1
            ) as resolution_rate
        FROM defects
        WHERE created_at BETWEEN :start_date AND :end_date
        GROUP BY priority
        ORDER BY count DESC";
    
    $priorityStmt = $db->prepare($priorityQuery);
    $priorityStmt->execute([
        ':start_date' => $start_date . ' 00:00:00',
        ':end_date' => $end_date . ' 23:59:59'
    ]);
    $priorityStats = $priorityStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
    error_log("Error in reports.php: " . $e->getMessage() . " - User: irlam - Time: 2025-01-27 19:36:37");
}

// Helper Functions
function formatUKDate($date) {
    return date('d/m/Y', strtotime($date));
}

function formatUKDateTime($date) {
    return date('d/m/Y H:i', strtotime($date));
}

function formatNumber($number) {
    return number_format($number, 0);
}

function formatPercentage($number) {
    return number_format($number, 1) . '%';
}

function formatDays($days) {
    return number_format($days, 1) . ' days';
}

function getStatusBadgeClass($status) {
    $statusClasses = [
        'open' => 'danger',
        'in_progress' => 'warning',
        'resolved' => 'info',
        'closed' => 'success',
        'pending' => 'secondary'
    ];
    return $statusClasses[strtolower($status)] ?? 'secondary';
}

function getPriorityBadgeClass($priority) {
    $priorityClasses = [
        'high' => 'danger',
        'medium' => 'warning',
        'low' => 'success'
    ];
    return $priorityClasses[strtolower($priority)] ?? 'secondary';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Defect Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Screen Styles */
        .stats-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            transition: transform 0.2s;
        }
        
        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            min-height: 300px;
        }
        
        .date-filter {
            background: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .main-content {
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
            background-color: #f8f9fa;
        }

        .report-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .no-print {
            display: block;
        }

        /* Print Styles */
        @media print {
            body {
                background: white;
                font-size: 12pt;
            }

            .no-print {
                display: none !important;
            }

            .main-content {
                margin: 0;
                padding: 0;
            }

            .stats-card {
                break-inside: avoid;
                box-shadow: none;
                border: 1px solid #ddd;
                margin-bottom: 20px;
                page-break-inside: avoid;
            }

            .chart-container {
                height: 300px;
                page-break-inside: avoid;
            }

            .table {
                font-size: 10pt;
                border: 1px solid #ddd;
            }

            .page-break {
                page-break-before: always;
            }

            .badge {
                border: 1px solid #ddd;
            }

            .report-header {
                border-bottom: 2px solid #000;
                padding-bottom: 10px;
                margin-bottom: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Include Navbar -->
    <?php echo $navbar->render(); ?>

    <div class="main-content">
        <div class="container-fluid">
            <!-- Print Controls (no-print) -->
            <div class="row mb-4 no-print">
                <div class="col-12">
                    <button onclick="window.print()" class="btn btn-primary">
                        <i class='bx bx-printer'></i> Print Report
                    </button>
                    <a href="?" class="btn btn-secondary">
                        <i class='bx bx-refresh'></i> Reset Filters
                    </a>
                </div>
            </div>

            <!-- Report Header (prints) -->
            <div class="report-header">
                <h2>Defect Management Report</h2>
                <p class="text-muted">
                    Period: <?php echo formatUKDate($start_date); ?> to <?php echo formatUKDate($end_date); ?><br>
                    Generated: <?php echo formatUKDateTime(date('Y-m-d H:i:s')); ?><br>
                    Generated by: <?php echo htmlspecialchars($_SESSION['username']); ?>
                </p>
            </div>

            <!-- Date Filter (no-print) -->
            <div class="date-filter no-print">
                <form method="GET" class="row g-3 align-items-center">
                    <div class="col-auto">
                        <label class="form-label">Date Range:</label>
                    </div>
                    <div class="col-auto">
                        <input type="date" class="form-control" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                    </div>
                    <div class="col-auto">
                        <span>to</span>
                    </div>
                    <div class="col-auto">
                        <input type="date" class="form-control" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary">Apply Filter</button>
                    </div>
                </form>
            </div>
<?php
$error_message = ''; // Initialize the variable
?>
            <?php if ($error_message): ?>
    <div class="alert alert-danger alert-dismissible fade show no-print" role="alert">
        <?php echo htmlspecialchars($error_message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

            <!-- Statistics Overview -->
            <div class="row mb-4">
                <!-- Defect Statistics -->
                <div class="col-md-6">
                    <div class="stats-card p-4">
                        <h4>Defect Statistics</h4>
                        <div class="chart-container">
                            <canvas id="defectStatusChart"></canvas>
                        </div>
                        <div class="mt-3">
                            <p>Total Defects: <?php echo formatNumber($defectStats['total']); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Project Statistics -->
                <div class="col-md-6">
                    <div class="stats-card p-4">
                        <h4>Project Statistics</h4>
                        <div class="chart-container">
                            <canvas id="projectStatusChart"></canvas>
                        </div>
                        <div class="mt-3">
                            <p>Total Projects: <?php echo formatNumber($projectStats['total']); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Priority Distribution -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="stats-card p-4">
                        <h4>Priority Distribution</h4>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Priority</th>
                                        <th>Count</th>
                                        <th>Percentage</th>
                                        <th>Resolved</th>
                                        <th>Resolution Rate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($priorityStats as $stat): ?>
                                    <tr>
                                        <td><span class="badge bg-<?php echo getPriorityBadgeClass($stat['priority']); ?>"><?php echo ucfirst($stat['priority']); ?></span></td>
                                        <td><?php echo formatNumber($stat['count']); ?></td>
                                        <td><?php echo formatPercentage($stat['percentage']); ?></td>
                                        <td><?php echo formatNumber($stat['resolved_count']); ?></td>
                                        <td><?php echo formatPercentage($stat['resolution_rate']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Page Break for Print -->
            <div class="page-break"></div>

            <!-- Contractor Performance -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="stats-card p-4">
                        <h4>Contractor Performance</h4>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Contractor</th>
                                        <th>Trade</th>
                                        <th>Total Defects</th>
                                        <th>High Priority</th>
                                        <th>Open</th>
                                        <th>Closed</th>
                                        <th>Resolution Rate</th>
                                        <th>Avg Time</th>
                                        <th>Projects</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($contractorStats as $contractor): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($contractor['company_name']); ?></td>
                                        <td><?php echo htmlspecialchars($contractor['trade']); ?></td>
                                        <td><?php echo formatNumber($contractor['total_defects']); ?></td>
                                        <td><?php echo formatNumber($contractor['high_priority_defects']); ?></td>
                                        <td><?php echo formatNumber($contractor['open_defects']); ?></td>
                                        <td><?php echo formatNumber($contractor['closed_defects']); ?></td>
                                        <td><?php echo formatPercentage($contractor['resolution_rate']); ?></td>
                                        <td><?php echo formatDays($contractor['avg_resolution_time']); ?></td>
                                        <td><?php echo formatNumber($contractor['total_projects']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Defects -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="stats-card p-4">
                        <h4>Recent Defects</h4>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Title</th>
                                        <th>Project</th>
                                        <th>Contractor</th>
                                        <th>Status</th>
                                        <th>Priority</th>
                                        <th>Created</th>
                                        <th>By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentDefectsList as $defect): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($defect['id']); ?></td>
                                        <td><?php echo htmlspecialchars($defect['title']); ?></td>
                                        <td><?php echo htmlspecialchars($defect['project_name']); ?></td>
                                        <td><?php echo htmlspecialchars($defect['company_name']); ?> (<?php echo htmlspecialchars($defect['trade']); ?>)</td>
                                        <td><span class="badge bg-<?php echo getStatusBadgeClass($defect['status']); ?>"><?php echo ucfirst($defect['status']); ?></span></td>
                                        <td><span class="badge bg-<?php echo getPriorityBadgeClass($defect['priority']); ?>"><?php echo ucfirst($defect['priority']); ?></span></td>
                                        <td><?php echo formatUKDateTime($defect['created_at']); ?></td>
                                        <td><?php echo htmlspecialchars($defect['reported_by']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/main.js"></script>
    <script>
        // Charts Initialization
        document.addEventListener('DOMContentLoaded', function() {
            const ctxDefectStatus = document.getElementById('defectStatusChart').getContext('2d');
            const defectStatusChart = new Chart(ctxDefectStatus, {
                type: 'pie',
                data: {
                    labels: ['Open', 'In Progress', 'Resolved', 'Closed'],
                    datasets: [{
                        data: [
                            <?php echo $defectStats['open']; ?>,
                            <?php echo $defectStats['in_progress']; ?>,
                            <?php echo $defectStats['resolved']; ?>,
                            <?php echo $defectStats['closed']; ?>
                        ],
                        backgroundColor: ['#dc3545', '#ffc107', '#17a2b8', '#28a745']
                    }]
                }
            });

            const ctxProjectStatus = document.getElementById('projectStatusChart').getContext('2d');
            const projectStatusChart = new Chart(ctxProjectStatus, {
                type: 'pie',
                data: {
                    labels: ['Active', 'Completed', 'Pending', 'On-Hold'],
                    datasets: [{
                        data: [
                            <?php echo $projectStats['active']; ?>,
                            <?php echo $projectStats['completed']; ?>,
                            <?php echo $projectStats['pending']; ?>,
                            <?php echo $projectStats['on-hold']; ?>
                        ],
                        backgroundColor: ['#007bff', '#28a745', '#ffc107', '#6c757d']
                    }]
                }
            });
        });
    </script>
</body>
</html>
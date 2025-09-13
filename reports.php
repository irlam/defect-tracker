<?php
/**
 * reports.php
 * Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): 2025-02-25 07:53:22
 * Current User's Login: irlam
 */

// Attempt to enable output buffering
ini_set('output_buffering', '1'); // Or try '4096'
ob_start();

error_reporting(0); // Disable error reporting for production
//ini_set('display_errors', 1); // Comment out or remove for production
//ini_set('log_errors', 1);
//ini_set('error_log', __DIR__ . '/logs/error.log');

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
require_once 'includes/navbar.php';

$pageTitle = 'Reports Dashboard';
$currentUser = $_SESSION['username'];
$currentUserId = (int)$_SESSION['user_id']; // Retrieve the user ID from the session

// Initialize $start_date and $end_date
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

try {
    $database = new Database();
    $db = $database->getConnection();

    // Initialize arrays
    $defectStats = [
        'total' => 0,
        'open' => 0,
        'in_progress' => 0,
        'resolved' => 0,
        'closed' => 0,
        'pending' => 0,
        'rejected' => 0
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
    WHERE deleted_at IS NULL
    AND created_at BETWEEN :start_date AND :end_date
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

    // Enhanced Contractor Performance Query
    $contractorPerformanceQuery = "
    SELECT 
        c.id,
        c.company_name,
        c.logo,
        c.trade,
        c.status as contractor_status,
        COUNT(DISTINCT CASE WHEN d.deleted_at IS NULL THEN d.id ELSE NULL END) as total_defects,
        SUM(CASE WHEN d.priority = 'high' AND d.deleted_at IS NULL THEN 1 ELSE 0 END) as high_priority_defects,
        SUM(CASE WHEN d.status = 'open' AND d.deleted_at IS NULL THEN 1 ELSE 0 END) as open_defects,
        SUM(CASE WHEN d.status = 'pending' AND d.deleted_at IS NULL THEN 1 ELSE 0 END) as pending_defects,
        SUM(CASE WHEN d.status = 'accepted' AND d.deleted_at IS NULL THEN 1 ELSE 0 END) as closed_defects,
        SUM(CASE WHEN d.status = 'rejected' AND d.deleted_at IS NULL THEN 1 ELSE 0 END) as rejected_defects,
        SUM(CASE WHEN d.due_date < UTC_TIMESTAMP() AND d.status IN ('open', 'pending') AND d.deleted_at IS NULL THEN 1 ELSE 0 END) as overdue_defects,
        ROUND(
            (SUM(CASE WHEN (d.status = 'closed' OR d.status = 'accepted') AND d.deleted_at IS NULL THEN 1 ELSE 0 END) * 100.0) / 
            NULLIF(COUNT(DISTINCT CASE WHEN d.deleted_at IS NULL THEN d.id ELSE NULL END), 0),
            1
        ) as resolution_rate,
        ROUND(
            AVG(CASE 
                WHEN (d.status = 'closed' OR d.status = 'accepted') AND d.deleted_at IS NULL
                THEN TIMESTAMPDIFF(DAY, d.created_at, d.updated_at)
                ELSE NULL
            END),
            1
        ) as avg_resolution_time,
        COUNT(DISTINCT CASE WHEN d.deleted_at IS NULL THEN d.project_id ELSE NULL END) as total_projects,
        MAX(CASE WHEN d.deleted_at IS NULL THEN d.updated_at ELSE NULL END) as last_update
    FROM 
        contractors c
        LEFT JOIN defects d ON c.id = d.assigned_to
            AND d.created_at BETWEEN :start_date AND :end_date
    WHERE 
        c.status = 'active'
    GROUP BY 
        c.id, c.company_name, c.logo, c.trade, c.status
    HAVING 
        total_defects > 0
    ORDER BY 
        total_defects DESC
";

    $contractorPerformance = $db->prepare($contractorPerformanceQuery);
    $contractorPerformance->execute([
        ':start_date' => $start_date . ' 00:00:00',
        ':end_date' => $end_date . ' 23:59:59'
    ]);
    $contractorStats = $contractorPerformance->fetchAll(PDO::FETCH_ASSOC);

    // Defect Trend Data
    $trendQuery = "
    SELECT
        DATE(created_at) as defect_date,
        COUNT(*) as defect_count
    FROM defects
    WHERE deleted_at IS NULL
    AND created_at BETWEEN :start_date AND :end_date
    GROUP BY defect_date
    ORDER BY defect_date
";
    $trendStmt = $db->prepare($trendQuery);
    $trendStmt->execute([
        ':start_date' => $start_date . ' 00:00:00',
        ':end_date' => $end_date . ' 23:59:59'
    ]);
    $defectTrends = $trendStmt->fetchAll(PDO::FETCH_ASSOC);

    // Prepare data for the chart
    $trendLabels = [];
    $trendData = [];
    foreach ($defectTrends as $trend) {
        $trendLabels[] = formatUKDate($trend['defect_date']);
        $trendData[] = $trend['defect_count'];
    }

    // User Performance Data
    $userPerformanceQuery = "
    SELECT
        u.id,
        u.username,
        COUNT(CASE WHEN d.deleted_at IS NULL THEN d.id ELSE NULL END) as defects_reported
    FROM users u
    LEFT JOIN defects d ON u.id = d.created_by
    WHERE d.created_at BETWEEN :start_date AND :end_date
    AND (d.id IS NULL OR d.deleted_at IS NULL)
    GROUP BY u.id, u.username
    ORDER BY defects_reported DESC
    LIMIT 10
";
    $userPerformanceStmt = $db->prepare($userPerformanceQuery);
    $userPerformanceStmt->execute([
        ':start_date' => $start_date . ' 00:00:00',
        ':end_date' => $end_date . ' 23:59:59'
    ]);
    $userPerformanceData = $userPerformanceStmt->fetchAll(PDO::FETCH_ASSOC);

    // Prepare data for the User Performance chart
    $userLabels = [];
    $userDefectCounts = [];
    foreach ($userPerformanceData as $user) {
        $userLabels[] = $user['username'];
        $userDefectCounts[] = $user['defects_reported'];
    }

    // Get overall statistics
    $overallStatsQuery = "SELECT 
    (SELECT COUNT(*) FROM contractors WHERE status = 'active') as active_contractors,
    (SELECT COUNT(*) FROM defects WHERE status = 'open' AND deleted_at IS NULL AND created_at BETWEEN :start_date AND :end_date) as open_defects,
    (SELECT COUNT(*) FROM defects WHERE deleted_at IS NULL AND created_at BETWEEN :start_date AND :end_date) as total_defects,
    (SELECT COUNT(*) FROM defects WHERE status = 'pending' AND deleted_at IS NULL AND created_at BETWEEN :start_date AND :end_date) as pending_defects,
    (SELECT COUNT(*) FROM defects WHERE status = 'rejected' AND deleted_at IS NULL AND created_at BETWEEN :start_date AND :end_date) as rejected_defects,
    (SELECT COUNT(*) FROM defects WHERE (status = 'closed' OR status = 'accepted') AND deleted_at IS NULL AND created_at BETWEEN :start_date AND :end_date) as closed_defects,
    (SELECT COUNT(*) FROM defects WHERE due_date < UTC_TIMESTAMP() AND status IN ('open', 'pending') AND deleted_at IS NULL AND created_at BETWEEN :start_date AND :end_date) as overdue_defects";
    
    $overallStatsStmt = $db->prepare($overallStatsQuery);
    $overallStatsStmt->execute([
        ':start_date' => $start_date . ' 00:00:00',
        ':end_date' => $end_date . ' 23:59:59'
    ]);
    $overallStats = $overallStatsStmt->fetch(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
    error_log("Error in reports.php: " . $e->getMessage() . " - User: " . $currentUser . " - Time: " . date('Y-m-d H:i:s'));
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
        'accepted' => 'success',
        'pending' => 'secondary',
        'rejected' => 'danger'
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

function getTrendIndicator($value, $threshold = 0) {
    if ($value > $threshold) {
        return '<i class="bx bx-trending-up text-success"></i>';
    } elseif ($value < $threshold) {
        return '<i class="bx bx-trending-down text-danger"></i>';
    }
    return '<i class="bx bx-minus text-warning"></i>';
}

function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $subdir = ''; // Adjust if your application is in a subdirectory
    return $protocol . "://" . $host . $subdir;
}

// Export Functions
function exportToCsv($data, $filename = 'report.csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $output = fopen('php://output', 'w');

    // Add UTF-8 BOM (Byte Order Mark) to handle special characters correctly
    fwrite($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Add headers
    $headers = array_keys($data[0]);
    fputcsv($output, $headers);

    // Add data
    foreach ($data as $rowData) {
        fputcsv($output, $rowData);
    }

    fclose($output);
    exit;
}

// Handle Export Request
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    // Prepare data for export
    $exportData = [];
    foreach ($contractorStats as $contractor) {
        $exportData[] = [
            'Contractor' => $contractor['company_name'],
            'Trade' => $contractor['trade'],
            'Total' => $contractor['total_defects'],
            'Open' => $contractor['open_defects'],
            'Pending' => $contractor['pending_defects'],
            'Overdue' => $contractor['overdue_defects'],
            'Rejected' => $contractor['rejected_defects'],
            'Closed' => $contractor['closed_defects'],
            'Resolution Rate' => $contractor['resolution_rate'],
            'Avg Resolution Time' => $contractor['avg_resolution_time'],
            'Projects' => $contractor['total_projects']
        ];
    }
    exportToCsv($exportData, 'contractor_defects_report_' . date('dmYHis') . '.csv');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Reports Dashboard - Defect Tracker System">
    <meta name="author" content="<?php echo htmlspecialchars($currentUser); ?>">
    <meta name="last-modified" content="2025-02-25 07:58:23">
    <title><?php echo $pageTitle; ?> - Defect Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="/favicons/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicons/favicon.svg" />
    <link rel="shortcut icon" href="/favicons/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png" />
    <link rel="manifest" href="/favicons/site.webmanifest" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Base Layout */
		.main-content {
    padding: 20px;
    background-color: #f8f9fa;
    min-height: 100vh;
}
		@media (max-width: 768px) {
    .main-content {
        padding: 15px;
    }
}

        /* Gradient and Card Styles */
        .stats-card {
            transition: all 0.3s ease;
            border: none;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 4px 25px 0 rgba(0, 0, 0, 0.1);
            background: #fff;
            margin-bottom: 1.5rem;
        }
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 28px 0 rgba(0, 0, 0, 0.15);
        }
        .stats-card .gradient-layer {
            position: relative;
            padding: 1.5rem;
            border-radius: 20px;
            background: linear-gradient(45deg, var(--gradient-start), var(--gradient-end));
            color: white;
        }
        /* Card Variant Gradients */
        .stats-card.contractors { --gradient-start: #4158D0; --gradient-end: #C850C0; }
        .stats-card.open-defects { --gradient-start: #FF416C; --gradient-end: #FF4B2B; }
        .stats-card.total-defects { --gradient-start: #8EC5FC; --gradient-end: #E0C3FC; }
        .stats-card.pending-defects { --gradient-start: #F6D365; --gradient-end: #FDA085; }
        .stats-card.rejected-defects { --gradient-start: #FF6B6B; --gradient-end: #FF8E8E; }
        .stats-card.closed-defects { --gradient-start: #2ECC71; --gradient-end: #26C281; }
        .stats-card.overdue-defects { --gradient-start: #D3560E; --gradient-end: #FFA500; }

        /* Table Styling */
        .table th {
            border-top: none;
            background-color: rgba(0,0,0,0.02);
            font-weight: 600;
        }
        .table thead tr th:last-child {
            color: black !important;
        }

        /* Date Filter Styling */
        .date-filter {
            background: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        /* Chart container */
        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            min-height: 300px;
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
    </style>
</head>
<body>
<?php
$navbar = new Navbar($db, $_SESSION['user_id'], $_SESSION['username']);
$navbar->render();
?>
<br><br><br><br><br>
    <div class="main-content" id="content">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0"><?php echo htmlspecialchars($pageTitle); ?></h1>
                </div>
                <div class="system-info d-flex align-items-center no-print">
                    <div class="me-4">
                        <i class='bx bx-user-circle'></i>
                        <span class="ms-1"><?php echo htmlspecialchars($currentUser); ?></span>
                    </div>
                    <div>
                        <i class='bx bx-time'></i>
                        <span class="ms-1"><?php echo formatUKDateTime(date('Y-m-d H:i:s')); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Print and Export Controls (no-print) -->
            <div class="row mb-4 no-print">
                <div class="col-12">
                    <button onclick="window.print()" class="btn btn-primary">
                        <i class='bx bx-printer me-1'></i> Print Report
                    </button>
                    <a href="?" class="btn btn-secondary">
                        <i class='bx bx-refresh me-1'></i> Reset Filters
                    </a>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" class="btn btn-success">
                        <i class='bx bx-download me-1'></i> Export to CSV
                    </a>
                    <button onclick="window.location.href='/pdf_exports/export_pdf_reports.php?<?php echo http_build_query($_GET); ?>'" class="btn btn-secondary">
                        <i class='bx bx-export me-1'></i> Export to PDF
                    </button>
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

            <?php if (isset($error_message) && !empty($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show no-print" role="alert">
                    <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="row">
                <div class="col-xl-3 col-md-6">
                    <div class="stats-card total-defects">
                        <div class="gradient-layer">
                            <div class="stats-label">Total Defects</div>
                            <div class="stats-value"><?php echo formatNumber($overallStats['total_defects']); ?></div>
                            <div class="stats-trend">
                                <?php echo getTrendIndicator(1); ?> For Selected Period
                            </div>
                            <i class='bx bx-check-circle stats-icon'></i>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="stats-card open-defects">
                        <div class="gradient-layer">
                            <div class="stats-label">Open Defects</div>
                            <div class="stats-value"><?php echo formatNumber($overallStats['open_defects']); ?></div>
                            <div class="stats-trend">
                                <?php echo getTrendIndicator($overallStats['open_defects']); ?> Active Issues
                            </div>
                            <i class='bx bx-bug stats-icon'></i>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="stats-card pending-defects">
                        <div class="gradient-layer">
                            <div class="stats-label">Pending Defects</div>
                            <div class="stats-value"><?php echo formatNumber($overallStats['pending_defects']); ?></div>
                            <div class="stats-trend">
                                <?php echo getTrendIndicator($overallStats['pending_defects']); ?> Awaiting Action
                            </div>
                            <i class='bx bx-time stats-icon'></i>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="stats-card overdue-defects">
                        <div class="gradient-layer">
                            <div class="stats-label">Overdue Defects</div>
                            <div class="stats-value"><?php echo formatNumber($overallStats['overdue_defects']); ?></div>
                            <div class="stats-trend">
                                <?php echo getTrendIndicator($overallStats['overdue_defects']); ?> Past Due Date
                            </div>
                            <i class='bx bx-alarm-exclamation stats-icon'></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- More Statistics Cards -->
            <div class="row mt-4">
                <div class="col-xl-4 col-md-6">
                    <div class="stats-card rejected-defects">
                        <div class="gradient-layer">
                            <div class="stats-label">Rejected Defects</div>
                            <div class="stats-value"><?php echo formatNumber($overallStats['rejected_defects']); ?></div>
                            <div class="stats-trend">
                                <?php echo getTrendIndicator($overallStats['rejected_defects']); ?> Not Accepted
                            </div>
                            <i class='bx bx-x-circle stats-icon'></i>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-md-6">
                    <div class="stats-card closed-defects">
                        <div class="gradient-layer">
                            <div class="stats-label">Closed Defects</div>
                            <div class="stats-value"><?php echo formatNumber($overallStats['closed_defects']); ?></div>
                            <div class="stats-trend">
                                <?php echo getTrendIndicator($overallStats['closed_defects']); ?> Completed
                            </div>
                            <i class='bx bx-check-double stats-icon'></i>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-md-6">
                    <div class="stats-card contractors">
                        <div class="gradient-layer">
                            <div class="stats-label">Active Contractors</div>
                            <div class="stats-value"><?php echo formatNumber($overallStats['active_contractors']); ?></div>
                            <div class="stats-trend">
                                <?php echo getTrendIndicator(1); ?> Available Teams
                            </div>
                            <i class='bx bx-buildings stats-icon'></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Page Break for Print -->
            <div class="page-break"></div>

            <!-- Defect Trend Analysis -->
            <div class="row mt-4 mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class='bx bx-line-chart me-2'></i>Defect Trend Analysis</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="defectTrendChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contractor Performance -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class='bx bx-table me-2'></i>Contractor Performance
                            </h5>
                            <a href="pdf_exports/export_contractor_defects.php?<?php echo http_build_query($_GET); ?>" target="_blank" class="btn btn-sm btn-secondary export-pdf-btn no-print">
                                <i class="fas fa-file-pdf"></i> Export this section
                            </a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($contractorStats)): ?>
                                <div class="alert alert-info mb-0">No contractor performance data available for the selected date range.</div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Contractor</th>
                                                <th class="text-center">Total</th>
                                                <th class="text-center">Open</th>
                                                <th class="text-center">Pending</th>
                                                <th class="text-center">Overdue</th>
                                                <th class="text-center">Rejected</th>
                                                <th class="text-center">Closed</th>
                                                <th>Last Update</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($contractorStats as $stat): ?>
                                                <tr>
                                                    <td>
                                                        <?php if (!empty($stat['logo'])): ?>
                                                            <?php $logoUrl = getBaseUrl() . '/uploads/logos/' . $stat['logo']; ?>
                                                            <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="<?php echo htmlspecialchars($stat['company_name']); ?>" style="max-height: 30px; margin-right: 5px;">
                                                        <?php endif; ?>
                                                        <?php echo htmlspecialchars($stat['company_name']); ?>
                                                        <div class="small text-muted"><?php echo htmlspecialchars($stat['trade']); ?></div>
                                                    </td>
                                                    <td class="text-center"><?php echo formatNumber($stat['total_defects']); ?></td>
                                                    <td class="text-center">
                                                        <span class="badge bg-danger">
                                                            <?php echo formatNumber($stat['open_defects']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-warning">
                                                            <?php echo formatNumber($stat['pending_defects']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-danger">
                                                            <?php echo formatNumber($stat['overdue_defects']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-danger">
                                                            <?php echo formatNumber($stat['rejected_defects']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-success">
                                                            <?php echo formatNumber($stat['closed_defects']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <small class="text-muted">
                                                            <?php echo formatUKDate($stat['last_update'] ?? 'N/A'); ?>
                                                        </small>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="mt-3">
                                    <p class="small text-muted">
                                        <i class='bx bx-info-circle'></i> Resolution rates and average times are calculated for closed defects only.
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Page Break for Print -->
            <div class="page-break"></div>

            <!-- Additional Contractor Performance Metrics -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class='bx bx-pie-chart-alt me-2'></i>Contractor Performance Metrics</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($contractorStats)): ?>
                                <div class="alert alert-info mb-0">No contractor performance data available for the selected date range.</div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Contractor</th>
                                                <th class="text-center">Resolution Rate</th>
                                                <th class="text-center">Avg. Resolution Time</th>
                                                <th class="text-center">High Priority Defects</th>
                                                <th class="text-center">Total Projects</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($contractorStats as $stat): ?>
                                                <tr>
                                                    <td>
                                                        <?php echo htmlspecialchars($stat['company_name']); ?>
                                                        <div class="small text-muted"><?php echo htmlspecialchars($stat['trade']); ?></div>
                                                    </td>
                                                    <td class="text-center">
                                                        <?php
                                                            $resolutionRate = $stat['resolution_rate'] ?? 0;
                                                            $badgeClass = $resolutionRate >= 80 ? 'success' : ($resolutionRate >= 50 ? 'warning' : 'danger');
                                                        ?>
                                                        <span class="badge bg-<?php echo $badgeClass; ?>">
                                                            <?php echo formatPercentage($resolutionRate); ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <?php echo formatDays($stat['avg_resolution_time'] ?? 0); ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-danger">
                                                            <?php echo formatNumber($stat['high_priority_defects']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <?php echo formatNumber($stat['total_projects']); ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- User Performance -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class='bx bx-user-voice me-2'></i>User Performance</h5>
            </div>
            <div class="card-body">
                <?php if (empty($userPerformanceData)): ?>
                    <div class="alert alert-info mb-0">No user performance data available for the selected date range.</div>
                <?php else: ?>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>User</th>
                                            <th class="text-center">Defects Reported</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($userPerformanceData as $user): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                                <td class="text-center"><?php echo formatNumber($user['defects_reported']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="chart-container">
                                <canvas id="userPerformanceChart"></canvas>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- End of Main Container -->
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Charts Initialization
        document.addEventListener('DOMContentLoaded', function() {
            // Defect Trend Chart
            const ctxDefectTrend = document.getElementById('defectTrendChart')?.getContext('2d');
            if (ctxDefectTrend) {
                new Chart(ctxDefectTrend, {
                    type: 'line',
                    data: {
                        labels: <?php echo json_encode($trendLabels); ?>,
                        datasets: [{
                            label: 'Defect Count',
                            data: <?php echo json_encode($trendData); ?>,
                            borderColor: '#007bff',
                            backgroundColor: 'rgba(0, 123, 255, 0.1)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    precision: 0
                                }
                            }
                        },
                        plugins: {
                            title: {
                                display: true,
                                text: 'Daily Defect Count',
                                font: {
                                    size: 16
                                }
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false,
                            },
                            legend: {
                                position: 'top',
                            }
                        }
                    }
                });
            }

            // User Performance Chart
const ctxUserPerformance = document.getElementById('userPerformanceChart')?.getContext('2d');
if (ctxUserPerformance) {
    new Chart(ctxUserPerformance, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($userLabels); ?>,
            datasets: [{
                label: 'Defects Reported',
                data: <?php echo json_encode($userDefectCounts); ?>,
                backgroundColor: [
                    'rgba(54, 162, 235, 0.7)',
                    'rgba(255, 99, 132, 0.7)',
                    'rgba(75, 192, 192, 0.7)',
                    'rgba(255, 206, 86, 0.7)',
                    'rgba(153, 102, 255, 0.7)',
                    'rgba(255, 159, 64, 0.7)',
                    'rgba(59, 114, 183, 0.7)',
                    'rgba(130, 204, 221, 0.7)',
                    'rgba(166, 107, 190, 0.7)',
                    'rgba(89, 191, 157, 0.7)'
                ],
                borderColor: [
                    'rgba(54, 162, 235, 1)',
                    'rgba(255, 99, 132, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(255, 206, 86, 1)',
                    'rgba(153, 102, 255, 1)',
                    'rgba(255, 159, 64, 1)',
                    'rgba(59, 114, 183, 1)',
                    'rgba(130, 204, 221, 1)',
                    'rgba(166, 107, 190, 1)',
                    'rgba(89, 191, 157, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            },
            plugins: {
                title: {
                    display: true,
                    text: 'User Defect Reporting',
                    font: {
                        size: 16
                    }
                }
            }
        }
    });
}
});

// Update UK Time
function updateUKTime() {
    const now = new Date();
    const ukTime = new Intl.DateTimeFormat('en-GB', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false,
        timeZone: 'Europe/London'
    }).format(now);

    const ukTimeElement = document.getElementById('ukTime');
    if (ukTimeElement) {
        ukTimeElement.textContent = ukTime;
    }
}

// Initialize and set interval for UK time
updateUKTime();
setInterval(updateUKTime, 1000);
</script>
</body>
</html>
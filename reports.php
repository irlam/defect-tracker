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

date_default_timezone_set('Europe/London');

$pageTitle = 'Reports Dashboard';
$currentUser = $_SESSION['username'];
$currentUserId = (int)$_SESSION['user_id'];
$displayName = ucwords(str_replace(['.', '_'], [' ', ' '], $currentUser ?? 'User'));
$currentUserRoleSummary = ucwords(str_replace(['_', '-'], [' ', ' '], $_SESSION['user_type'] ?? 'User'));
$currentTimestamp = date('d/m/Y H:i');

$error_message = '';

// Initialize default collections
$defectStats = [
    'total' => 0,
    'open' => 0,
    'in_progress' => 0,
    'resolved' => 0,
    'closed' => 0,
    'pending' => 0,
    'rejected' => 0,
    'accepted' => 0
];

$projectStats = [
    'total' => 0,
    'active' => 0,
    'completed' => 0,
    'pending' => 0,
    'on-hold' => 0
];

$contractorStats = [];
$defectTrends = [];
$trendLabels = [];
$trendData = [];
$userPerformanceData = [];
$userLabels = [];
$userDefectCounts = [];

$overallStats = [
    'total_defects' => 0,
    'open_defects' => 0,
    'pending_defects' => 0,
    'rejected_defects' => 0,
    'closed_defects' => 0,
    'overdue_defects' => 0,
    'active_contractors' => 0
];

// Initialize $start_date and $end_date
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

try {
    $database = new Database();
    $db = $database->getConnection();

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
        $statusKey = strtolower($row['status'] ?? '');
        $count = (int) ($row['count'] ?? 0);
        if ($statusKey !== '') {
            $defectStats[$statusKey] = $count;
        }
        $defectStats['total'] += $count;
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
        $statusKey = strtolower($row['status'] ?? '');
        $count = (int) ($row['count'] ?? 0);
        if ($statusKey !== '') {
            $projectStats[$statusKey] = $count;
        }
        $projectStats['total'] += $count;
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

    // Aggregate overview metrics
    $activeContractorsStmt = $db->prepare("SELECT COUNT(*) FROM contractors WHERE status = 'active'");
    $activeContractorsStmt->execute();
    $activeContractors = (int) $activeContractorsStmt->fetchColumn();

    $overdueStmt = $db->prepare("
        SELECT COUNT(*)
        FROM defects
        WHERE deleted_at IS NULL
          AND status IN ('open', 'pending')
          AND due_date < UTC_TIMESTAMP()
          AND created_at BETWEEN :start_date AND :end_date
    ");
    $overdueStmt->execute([
        ':start_date' => $start_date . ' 00:00:00',
        ':end_date' => $end_date . ' 23:59:59'
    ]);
    $overdueDefects = (int) $overdueStmt->fetchColumn();

    $overallStats = [
        'total_defects' => (int) ($defectStats['total'] ?? 0),
        'open_defects' => (int) ($defectStats['open'] ?? 0),
        'pending_defects' => (int) ($defectStats['pending'] ?? 0),
        'rejected_defects' => (int) ($defectStats['rejected'] ?? 0),
        'closed_defects' => (int) (($defectStats['closed'] ?? 0) + ($defectStats['accepted'] ?? 0) + ($defectStats['resolved'] ?? 0)),
        'overdue_defects' => $overdueDefects,
        'active_contractors' => $activeContractors
    ];

} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
    error_log("Error in reports.php: " . $e->getMessage() . " - User: " . $currentUser . " - Time: " . date('Y-m-d H:i:s'));
}

$reportMetrics = [
    [
        'stat_key' => 'total_defects',
        'title' => 'Total Defects',
        'subtitle' => 'For selected period',
        'icon' => 'bx-check-circle',
        'class' => 'report-metric-card--total',
        'trend_value' => 1,
        'trend_summary' => 'For selected period'
    ],
    [
        'stat_key' => 'open_defects',
        'title' => 'Open Defects',
        'subtitle' => 'Active issues',
        'icon' => 'bx-bug',
        'class' => 'report-metric-card--open',
        'trend_value' => $overallStats['open_defects'] ?? 0,
        'trend_summary' => 'Active issues'
    ],
    [
        'stat_key' => 'pending_defects',
        'title' => 'Pending Defects',
        'subtitle' => 'Awaiting action',
        'icon' => 'bx-time',
        'class' => 'report-metric-card--pending',
        'trend_value' => $overallStats['pending_defects'] ?? 0,
        'trend_summary' => 'Awaiting action'
    ],
    [
        'stat_key' => 'overdue_defects',
        'title' => 'Overdue Defects',
        'subtitle' => 'Past due date',
        'icon' => 'bx-alarm-exclamation',
        'class' => 'report-metric-card--overdue',
        'trend_value' => $overallStats['overdue_defects'] ?? 0,
        'trend_summary' => 'Past due date'
    ],
    [
        'stat_key' => 'rejected_defects',
        'title' => 'Rejected Defects',
        'subtitle' => 'Not accepted',
        'icon' => 'bx-x-circle',
        'class' => 'report-metric-card--rejected',
        'trend_value' => $overallStats['rejected_defects'] ?? 0,
        'trend_summary' => 'Not accepted'
    ],
    [
        'stat_key' => 'closed_defects',
        'title' => 'Closed Defects',
        'subtitle' => 'Completed items',
        'icon' => 'bx-check-double',
        'class' => 'report-metric-card--closed',
        'trend_value' => $overallStats['closed_defects'] ?? 0,
        'trend_summary' => 'Completed items'
    ],
    [
        'stat_key' => 'active_contractors',
        'title' => 'Active Contractors',
        'subtitle' => 'Available teams',
        'icon' => 'bx-buildings',
        'class' => 'report-metric-card--contractors',
        'trend_value' => max($overallStats['active_contractors'] ?? 0, 1),
        'trend_summary' => 'Available teams'
    ]
];

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
    <meta name="author" content="<?php echo htmlspecialchars($currentUser ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    <title><?php echo htmlspecialchars($pageTitle ?? '', ENT_QUOTES, 'UTF-8'); ?> - Defect Tracker</title>
    <link rel="icon" type="image/png" href="/favicons/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicons/favicon.svg" />
    <link rel="shortcut icon" href="/favicons/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png" />
    <link rel="manifest" href="/favicons/site.webmanifest" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="css/app.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="tool-body" data-bs-theme="dark">
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top no-print">
        <div class="container-xl">
            <a class="navbar-brand fw-semibold" href="reports.php">
                <i class='bx bx-bar-chart-square me-2'></i>Reporting Intelligence Hub
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#reportsNavbar" aria-controls="reportsNavbar" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="reportsNavbar">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php"><i class='bx bx-doughnut-chart me-1'></i>Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="defects.php"><i class='bx bx-error me-1'></i>Defects</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="reports.php"><i class='bx bx-bar-chart-alt-2 me-1'></i>Reports</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="my_tasks.php"><i class='bx bx-list-check me-1'></i>My Tasks</a>
                    </li>
                    <?php if (!empty($_SESSION['is_admin'])): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="admin.php"><i class='bx bx-dial me-1'></i>Admin</a>
                    </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-3">
                    <li class="nav-item text-muted small d-none d-lg-flex align-items-center">
                        <i class='bx bx-time-five me-1'></i><span data-report-time><?php echo htmlspecialchars($currentTimestamp, ENT_QUOTES, 'UTF-8'); ?></span> UK
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="reportsUserMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class='bx bx-user-circle me-1'></i><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="reportsUserMenu">
                            <li><a class="dropdown-item" href="profile.php"><i class='bx bx-user'></i> Profile</a></li>
                            <li><a class="dropdown-item" href="my_tasks.php"><i class='bx bx-list-check'></i> My Tasks</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class='bx bx-log-out'></i> Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="tool-page container-xl py-4">
        <header class="tool-header mb-5">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <h1 class="h3 mb-2">Performance &amp; Reporting</h1>
                    <p class="text-muted mb-0">Analytics for projects and contractors between <?php echo formatUKDate($start_date); ?> and <?php echo formatUKDate($end_date); ?>.</p>
                </div>
                <div class="d-flex flex-column align-items-start text-muted small gap-1">
                    <span><i class='bx bx-user-voice me-1'></i><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></span>
                    <span><i class='bx bx-label me-1'></i><?php echo htmlspecialchars($currentUserRoleSummary, ENT_QUOTES, 'UTF-8'); ?></span>
                    <span><i class='bx bx-time-five me-1'></i><span data-report-time><?php echo htmlspecialchars($currentTimestamp, ENT_QUOTES, 'UTF-8'); ?></span> UK</span>
                </div>
            </div>
        </header>

        <?php if (!empty($error_message)): ?>
        <div class="system-callout system-callout--danger no-print" role="alert">
            <div class="system-callout__icon"><i class='bx bx-error-circle'></i></div>
            <div>
                <h2 class="system-callout__title">Database Error</h2>
                <p class="system-callout__body mb-0"><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        </div>
        <?php endif; ?>

        <section class="report-toolbar no-print mb-4">
            <div class="d-flex flex-wrap align-items-end gap-3">
                <form method="GET" class="d-flex flex-wrap align-items-end gap-3">
                    <div>
                        <label for="reportStartDate" class="form-label text-muted small mb-1">Start</label>
                        <input type="date" id="reportStartDate" name="start_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($start_date, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div>
                        <label for="reportEndDate" class="form-label text-muted small mb-1">End</label>
                        <input type="date" id="reportEndDate" name="end_date" class="form-control form-control-sm" value="<?php echo htmlspecialchars($end_date, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div>
                        <button type="submit" class="btn btn-sm btn-primary">
                            <i class='bx bx-filter-alt'></i>
                            Apply Filter
                        </button>
                    </div>
                </form>
                <div class="d-flex flex-wrap gap-2 ms-auto">
                    <button type="button" class="btn btn-sm btn-outline-light" onclick="window.print()">
                        <i class='bx bx-printer'></i>
                        Print Report
                    </button>
                    <a class="btn btn-sm btn-outline-light" href="?">
                        <i class='bx bx-refresh'></i>
                        Reset Filters
                    </a>
                    <a class="btn btn-sm btn-outline-light" href="?<?php echo htmlspecialchars(http_build_query(array_merge($_GET, ['export' => 'csv'])), ENT_QUOTES, 'UTF-8'); ?>">
                        <i class='bx bx-download'></i>
                        Export CSV
                    </a>
                    <a class="btn btn-sm btn-outline-light" href="pdf_exports/export_pdf_reports.php?<?php echo htmlspecialchars(http_build_query($_GET), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
                        <i class='bx bx-export'></i>
                        Export PDF
                    </a>
                </div>
            </div>
        </section>

        <section class="report-summary card border-0 mb-5">
            <div class="card-body">
                <h2 class="h5 mb-3">Defect Management Report</h2>
                <div class="report-summary__grid">
                    <div>
                        <span class="report-summary__label">Period</span>
                        <p class="mb-0"><?php echo formatUKDate($start_date); ?> &ndash; <?php echo formatUKDate($end_date); ?></p>
                    </div>
                    <div>
                        <span class="report-summary__label">Generated</span>
                        <p class="mb-0"><?php echo formatUKDateTime(date('Y-m-d H:i:s')); ?></p>
                    </div>
                    <div>
                        <span class="report-summary__label">Generated by</span>
                        <p class="mb-0"><?php echo htmlspecialchars($currentUser, ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                </div>
            </div>
        </section>

        <section class="mb-5">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
                <div>
                    <h2 class="h5 mb-1">Operational Snapshot</h2>
                    <p class="text-muted small mb-0">Key metrics refreshed for the selected period.</p>
                </div>
            </div>
            <div class="report-metrics-grid">
                <?php foreach ($reportMetrics as $metric): ?>
                <?php $statValue = (int) ($overallStats[$metric['stat_key']] ?? 0); ?>
                <article class="report-metric-card <?php echo htmlspecialchars($metric['class'], ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="report-metric-card__icon">
                        <i class='bx <?php echo htmlspecialchars($metric['icon'], ENT_QUOTES, 'UTF-8'); ?>'></i>
                    </div>
                    <div class="report-metric-card__content">
                        <h3 class="report-metric-card__title"><?php echo htmlspecialchars($metric['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                        <p class="report-metric-card__value mb-1"><?php echo number_format($statValue); ?></p>
                        <p class="report-metric-card__description mb-0">
                            <?php echo getTrendIndicator($metric['trend_value']); ?>
                            <span><?php echo htmlspecialchars($metric['subtitle'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </p>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        </section>

        <div class="report-page-break"></div>

        <section class="mb-5">
            <div class="card border-0">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h2 class="h5 mb-0"><i class='bx bx-line-chart me-2'></i>Defect Trend Analysis</h2>
                </div>
                <div class="card-body">
                    <div class="report-chart">
                        <canvas id="defectTrendChart"></canvas>
                    </div>
                </div>
            </div>
        </section>

        <section class="mb-5">
            <div class="card border-0">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h2 class="h5 mb-0"><i class='bx bx-table me-2'></i>Contractor Performance</h2>
                    <a href="pdf_exports/export_contractor_defects.php?<?php echo htmlspecialchars(http_build_query($_GET), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-light no-print">
                        <i class='bx bx-file'></i>
                        Export Section
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($contractorStats)): ?>
                        <div class="alert alert-info mb-0">No contractor performance data available for the selected date range.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-dark table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th scope="col">Contractor</th>
                                        <th scope="col" class="text-center">Total</th>
                                        <th scope="col" class="text-center">Open</th>
                                        <th scope="col" class="text-center">Pending</th>
                                        <th scope="col" class="text-center">Overdue</th>
                                        <th scope="col" class="text-center">Rejected</th>
                                        <th scope="col" class="text-center">Closed</th>
                                        <th scope="col">Last Update</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($contractorStats as $stat): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <?php if (!empty($stat['logo'])): ?>
                                                    <?php $logoUrl = getBaseUrl() . '/uploads/logos/' . $stat['logo']; ?>
                                                    <img src="<?php echo htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($stat['company_name'], ENT_QUOTES, 'UTF-8'); ?>" class="rounded-circle" width="32" height="32">
                                                <?php else: ?>
                                                    <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis"><i class='bx bx-building'></i></span>
                                                <?php endif; ?>
                                                <div>
                                                    <span class="fw-semibold"><?php echo htmlspecialchars($stat['company_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                    <div class="small text-muted"><?php echo htmlspecialchars($stat['trade'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center"><?php echo formatNumber($stat['total_defects']); ?></td>
                                        <td class="text-center"><span class="badge rounded-pill bg-danger-subtle text-danger-emphasis"><?php echo formatNumber($stat['open_defects']); ?></span></td>
                                        <td class="text-center"><span class="badge rounded-pill bg-warning-subtle text-warning-emphasis"><?php echo formatNumber($stat['pending_defects']); ?></span></td>
                                        <td class="text-center"><span class="badge rounded-pill bg-danger-subtle text-danger-emphasis"><?php echo formatNumber($stat['overdue_defects']); ?></span></td>
                                        <td class="text-center"><span class="badge rounded-pill bg-danger-subtle text-danger-emphasis"><?php echo formatNumber($stat['rejected_defects']); ?></span></td>
                                        <td class="text-center"><span class="badge rounded-pill bg-success-subtle text-success-emphasis"><?php echo formatNumber($stat['closed_defects']); ?></span></td>
                                        <td><small class="text-muted"><?php echo $stat['last_update'] ? formatUKDate($stat['last_update']) : 'N/A'; ?></small></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <p class="small text-muted mt-3 mb-0"><i class='bx bx-info-circle'></i> Resolution rates and average times are calculated for closed or accepted defects only.</p>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="mb-5">
            <div class="card border-0">
                <div class="card-header">
                    <h2 class="h5 mb-0"><i class='bx bx-pie-chart-alt me-2'></i>Contractor Performance Metrics</h2>
                </div>
                <div class="card-body">
                    <?php if (empty($contractorStats)): ?>
                        <div class="alert alert-info mb-0">No contractor performance data available for the selected date range.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-dark table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th scope="col">Contractor</th>
                                        <th scope="col" class="text-center">Resolution Rate</th>
                                        <th scope="col" class="text-center">Avg. Resolution Time</th>
                                        <th scope="col" class="text-center">High Priority Defects</th>
                                        <th scope="col" class="text-center">Total Projects</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($contractorStats as $stat): ?>
                                    <tr>
                                        <td>
                                            <span class="fw-semibold"><?php echo htmlspecialchars($stat['company_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <div class="small text-muted"><?php echo htmlspecialchars($stat['trade'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></div>
                                        </td>
                                        <td class="text-center">
                                            <?php $resolutionRate = $stat['resolution_rate'] ?? 0; $badgeClass = $resolutionRate >= 80 ? 'success' : ($resolutionRate >= 50 ? 'warning' : 'danger'); ?>
                                            <span class="badge bg-<?php echo $badgeClass; ?>"><?php echo formatPercentage($resolutionRate); ?></span>
                                        </td>
                                        <td class="text-center"><?php echo formatDays($stat['avg_resolution_time'] ?? 0); ?></td>
                                        <td class="text-center"><span class="badge rounded-pill bg-danger-subtle text-danger-emphasis"><?php echo formatNumber($stat['high_priority_defects']); ?></span></td>
                                        <td class="text-center"><?php echo formatNumber($stat['total_projects']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="mb-5">
            <div class="card border-0">
                <div class="card-header">
                    <h2 class="h5 mb-0"><i class='bx bx-user-voice me-2'></i>User Performance</h2>
                </div>
                <div class="card-body">
                    <?php if (empty($userPerformanceData)): ?>
                        <div class="alert alert-info mb-0">No user performance data available for the selected date range.</div>
                    <?php else: ?>
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="table-responsive">
                                    <table class="table table-dark table-hover align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th scope="col">User</th>
                                                <th scope="col" class="text-center">Defects Reported</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($userPerformanceData as $user): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td class="text-center"><?php echo formatNumber($user['defects_reported']); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="report-chart">
                                    <canvas id="userPerformanceChart"></canvas>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const trendCtx = document.getElementById('defectTrendChart');
        if (trendCtx) {
            new Chart(trendCtx.getContext('2d'), {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($trendLabels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
                    datasets: [{
                        label: 'Defect Count',
                        data: <?php echo json_encode($trendData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
                        borderColor: '#60a5fa',
                        backgroundColor: 'rgba(96, 165, 250, 0.18)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { precision: 0 }
                        }
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: { mode: 'index', intersect: false }
                    }
                }
            });
        }

        const userCtx = document.getElementById('userPerformanceChart');
        if (userCtx) {
            new Chart(userCtx.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($userLabels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
                    datasets: [{
                        label: 'Defects Reported',
                        data: <?php echo json_encode($userDefectCounts, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
                        backgroundColor: 'rgba(94, 234, 212, 0.45)',
                        borderColor: 'rgba(94, 234, 212, 0.85)',
                        hoverBackgroundColor: 'rgba(94, 234, 212, 0.65)',
                        borderWidth: 1.5
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { precision: 0 }
                        }
                    },
                    plugins: {
                        legend: { display: false }
                    }
                }
            });
        }

        const updateUKTime = () => {
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

            document.querySelectorAll('[data-report-time]').forEach(el => {
                el.textContent = ukTime;
            });
        };

        updateUKTime();
        setInterval(updateUKTime, 1000);
    });
    </script>
</body>
</html>

<?php
// contractor_stats.php
// Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): 2025-01-16 21:54:52
// Current User's Login: irlam
// Error reporting and logging setup
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication check
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}
// Define INCLUDED constant for navbar security
define('INCLUDED', true);
require_once 'includes/functions.php';
require_once 'config/database.php';

$pageTitle = 'Contractor Statistics';
$currentUser = $_SESSION['username'];
$currentDateTime = date('Y-m-d H:i:s');

try {
    $database = new Database();
    $db = $database->getConnection();

    // Get contractor statistics
    $contractorStatsQuery = "
        SELECT 
            c.id,
            c.company_name,
            c.trade,
            c.status,
            COUNT(DISTINCT d.id) as total_defects,
            SUM(CASE WHEN d.status = 'open' THEN 1 ELSE 0 END) as open_defects,
            SUM(CASE WHEN d.status = 'closed' THEN 1 ELSE 0 END) as closed_defects,
            SUM(CASE WHEN DATE(d.due_date) = CURDATE() THEN 1 ELSE 0 END) as due_today,
            SUM(CASE WHEN d.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN d.due_date < CURDATE() AND d.status != 'closed' THEN 1 ELSE 0 END) as overdue,
            AVG(CASE WHEN d.status = 'closed' 
                THEN TIMESTAMPDIFF(DAY, d.created_at, d.updated_at)
                ELSE NULL END) as avg_resolution_time
        FROM contractors c
        LEFT JOIN defects d ON d.contractor_id = c.id
        GROUP BY c.id, c.company_name, c.trade, c.status
        ORDER BY c.company_name";

    $stmt = $db->query($contractorStatsQuery);
    $contractorStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Contractor Stats Error: " . $e->getMessage());
    $error_message = "An error occurred while loading contractor statistics.";
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
    <style>
        .main-content {
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
            background-color: #f8f9fa;
        }

        .contractor-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            transition: transform 0.2s;
        }

        .contractor-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .contractor-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid rgba(0,0,0,.05);
            padding: 1rem;
            border-radius: 10px 10px 0 0;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            padding: 15px;
        }

        .stat-item {
            background: #fff;
            border-radius: 8px;
            padding: 12px;
            text-align: center;
            border: 1px solid rgba(0,0,0,.05);
        }

        .stat-value {
            font-size: 1.25rem;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.75rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        @media (max-width: 991.98px) {
            .main-content {
                margin-left: 0;
                padding-top: 60px;
            }
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            }
        }

        /* Print styles */
        @media print {
            .main-content {
                margin-left: 0;
                padding: 0;
            }
            .sidebar, .btn-toolbar {
                display: none !important;
            }
        }
    </style>
</head>
<body class="tool-body" data-bs-theme="dark">
    <!-- Include Navbar -->
    <?php include 'includes/navbar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">Contractor Statistics</h1>
            <div class="btn-toolbar mb-2 mb-md-0">
                <div class="btn-group me-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                        <i class='bx bx-printer'></i> Print Report
                    </button>
                </div>
            </div>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

       <!-- Statistics Cards Row -->
<div class="row g-4 mb-4">
    <?php foreach ($contractorStats as $contractor): ?>
        <div class="col-md-6 col-xl-3">
            <div class="card stat-card h-100">
                <div class="card-body">
                    <!-- Company Name and Status -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title h6 mb-0 text-truncate" style="max-width: 180px;" 
                            title="<?php echo htmlspecialchars($contractor['company_name']); ?>">
                            <?php echo htmlspecialchars($contractor['company_name']); ?>
                        </h5>
                        <span class="badge bg-<?php echo $contractor['status'] === 'active' ? 'success' : 'warning'; ?>">
                            <?php echo ucfirst($contractor['status']); ?>
                        </span>
                    </div>

                    <!-- Trade -->
                    <p class="text-muted small mb-4"><?php echo htmlspecialchars($contractor['trade']); ?></p>

                    <!-- Stats Grid -->
                    <div class="row g-2">
                        <!-- Open Defects -->
                        <div class="col-6">
                            <div class="p-2 rounded bg-light">
                                <div class="h4 mb-0 text-primary"><?php echo $contractor['open_defects']; ?></div>
                                <small class="text-muted">Open</small>
                            </div>
                        </div>

                        <!-- Due Today -->
                        <div class="col-6">
                            <div class="p-2 rounded bg-light">
                                <div class="h4 mb-0 text-warning"><?php echo $contractor['due_today']; ?></div>
                                <small class="text-muted">Due Today</small>
                            </div>
                        </div>

                        <!-- Closed -->
                        <div class="col-6">
                            <div class="p-2 rounded bg-light">
                                <div class="h4 mb-0 text-success"><?php echo $contractor['closed_defects']; ?></div>
                                <small class="text-muted">Closed</small>
                            </div>
                        </div>

                        <!-- Overdue -->
                        <div class="col-6">
                            <div class="p-2 rounded bg-light">
                                <div class="h4 mb-0 text-danger"><?php echo $contractor['overdue']; ?></div>
                                <small class="text-muted">Overdue</small>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Stats -->
                    <div class="mt-3 pt-3 border-top">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <small class="text-muted">Total Defects</small>
                            <span class="badge bg-secondary"><?php echo $contractor['total_defects']; ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">Avg. Resolution Time</small>
                            <span class="badge bg-info">
                                <?php echo round($contractor['avg_resolution_time'] ?? 0, 1); ?> days
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

    <!-- JavaScript includes -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/main.js"></script>
</body>
</html>
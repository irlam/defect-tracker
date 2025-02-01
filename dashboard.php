<?php
/**
 * dashboard.php
 * Current Date and Time (UTC): 2025-01-26 16:40:58
 * Current User's Login: irlam
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication check
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Initial debug logging
error_log("Session Debug for user {$_SESSION['username']}:");
error_log("Session data: " . print_r($_SESSION, true));

require_once 'includes/functions.php';
require_once 'config/database.php';
require_once 'includes/navbar.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // Standardize session variables - ensure user_id is set
    if (!isset($_SESSION['user_id']) && isset($_SESSION['username'])) {
        $stmt = $db->prepare("
            SELECT id, user_type, status, is_active 
            FROM users 
            WHERE username = ?
        ");
        $stmt->execute([$_SESSION['username']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_type'] = $user['user_type'];
            $_SESSION['user_status'] = $user['status'];
            $_SESSION['is_active'] = $user['is_active'];
        } else {
            error_log("Failed to find user data for username: {$_SESSION['username']}");
        }
    }

    // Verify and update admin role
    $adminRoleCheck = "
        INSERT INTO roles (id, name, description, created_at)
        VALUES (1, 'admin', 'Administrator role with full access', UTC_TIMESTAMP())
        ON DUPLICATE KEY UPDATE
            name = 'admin',
            description = 'Administrator role with full access',
            updated_at = UTC_TIMESTAMP()
    ";
    $db->exec($adminRoleCheck);

    // Update user permissions
    $userUpdate = "
        UPDATE users 
        SET 
            user_type = 'admin',
            status = 'active',
            is_active = 1,
            updated_at = UTC_TIMESTAMP()
        WHERE username = :username
        AND (user_type != 'admin' OR status != 'active' OR is_active != 1)
    ";
    $updateStmt = $db->prepare($userUpdate);
    $updateStmt->execute(['username' => $_SESSION['username']]);

    // Ensure user has admin role
    $roleUpdate = "
        INSERT INTO user_roles (user_id, role_id, created_at)
        SELECT u.id, 1, UTC_TIMESTAMP()
        FROM users u
        WHERE u.username = :username
        ON DUPLICATE KEY UPDATE
            role_id = 1,
            updated_at = UTC_TIMESTAMP()
    ";
    $roleStmt = $db->prepare($roleUpdate);
    $roleStmt->execute(['username' => $_SESSION['username']]);

    // Get and verify user permissions
    $stmt = $db->prepare("
        SELECT 
            u.id,
            u.username,
            u.email,
            u.user_type,
            u.status,
            u.is_active,
            ur.role_id,
            r.name as role_name
        FROM users u
        LEFT JOIN user_roles ur ON u.id = ur.user_id
        LEFT JOIN roles r ON ur.role_id = r.id
        WHERE u.username = ?
        AND u.is_active = 1
    ");

    $stmt->execute([$_SESSION['username']]);
    $userDetails = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$userDetails) {
        error_log("Invalid user details for username: {$_SESSION['username']}");
        session_destroy();
        header("Location: login.php?error=invalid_user");
        exit();
    }

    // Update session with verified user details
    $_SESSION['user_id'] = $userDetails['id'];
    $_SESSION['user_type'] = $userDetails['user_type'];
    $_SESSION['role_id'] = $userDetails['role_id'];
    $_SESSION['user_status'] = $userDetails['status'];
    $_SESSION['is_admin'] = ($userDetails['user_type'] === 'admin' && $userDetails['role_id'] === 1);

    // Set timezone to UK
    date_default_timezone_set('Europe/London');

    $pageTitle = 'Defects Dashboard';
    $currentUser = $_SESSION['username'];
    $currentDateTime = date('Y-m-d H:i:s');
    $error_message = '';

    // Get overall statistics
    $statsQuery = "SELECT 
        (SELECT COUNT(*) FROM contractors WHERE status = 'active') as active_contractors,
        (SELECT COUNT(*) FROM defects WHERE status = 'open') as open_defects,
        (SELECT COUNT(*) FROM defects) as total_defects,
        (SELECT COUNT(*) FROM defects WHERE status = 'pending') as pending_defects";

    $statsStmt = $db->prepare($statsQuery);
    $statsStmt->execute();
    $overallStats = $statsStmt->fetch(PDO::FETCH_ASSOC);

    // Get defects by contractor statistics
    $contractorsQuery = "SELECT 
        c.id,
        c.company_name,
        c.trade,
        c.status as contractor_status,
        COUNT(DISTINCT d.id) as total_defects,
        SUM(CASE WHEN d.status = 'open' THEN 1 ELSE 0 END) as open_defects,
        SUM(CASE WHEN d.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_defects,
        SUM(CASE WHEN d.status = 'closed' THEN 1 ELSE 0 END) as closed_defects,
        MAX(d.updated_at) as last_update
    FROM contractors c
    LEFT JOIN defects d ON c.id = d.contractor_id
    WHERE c.status = 'active'
    GROUP BY c.id, c.company_name, c.trade, c.status
    ORDER BY total_defects DESC, company_name ASC";

    $contractorsStmt = $db->prepare($contractorsQuery);
    $contractorsStmt->execute();
    $contractorStats = $contractorsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get recent defects
    $recentDefectsQuery = "SELECT 
        d.id,
        d.title,
        d.status,
        d.priority,
        d.created_at,
        d.updated_at,
        c.company_name as contractor_name,
        p.name as project_name
    FROM defects d
    LEFT JOIN contractors c ON d.contractor_id = c.id
    LEFT JOIN projects p ON d.project_id = p.id
    ORDER BY d.created_at DESC
    LIMIT 10";

    $recentDefectsStmt = $db->prepare($recentDefectsQuery);
    $recentDefectsStmt->execute();
    $recentDefects = $recentDefectsStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Dashboard Error: " . $e->getMessage());
    $error_message = "An error occurred while loading the dashboard: " . $e->getMessage();
}

// Helper functions
function getStatusColor($status) {
    switch (strtolower($status)) {
        case 'open':
            return 'danger';
        case 'in_progress':
            return 'warning';
        case 'pending':
            return 'info';
        case 'closed':
            return 'success';
        default:
            return 'secondary';
    }
}

function getPriorityColor($priority) {
    switch (strtolower($priority)) {
        case 'high':
            return 'danger';
        case 'medium':
            return 'warning';
        case 'low':
            return 'success';
        default:
            return 'secondary';
    }
}

function formatUKDate($date) {
    return $date ? date('d/m/Y H:i', strtotime($date)) : 'N/A';
}

function getTrendIndicator($value, $threshold = 0) {
    if ($value > $threshold) {
        return '<i class="bx bx-trending-up text-success"></i>';
    } elseif ($value < $threshold) {
        return '<i class="bx bx-trending-down text-danger"></i>';
    }
    return '<i class="bx bx-minus text-warning"></i>';
}

function getTimeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return "just now";
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . " min" . ($mins > 1 ? "s" : "") . " ago";
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . " hour" . ($hours > 1 ? "s" : "") . " ago";
    } else {
        $days = floor($diff / 86400);
        return $days . " day" . ($days > 1 ? "s" : "") . " ago";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Defect Tracker Dashboard">
    <meta name="author" content="<?php echo htmlspecialchars($currentUser); ?>">
    <meta name="last-modified" content="2025-01-26 16:42:03">
    <title><?php echo htmlspecialchars($pageTitle); ?> - Defect Tracker</title>
    
    <!-- Essential CSS Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="/css/style.css" rel="stylesheet">

    <style>
        /* Base Layout */
        .main-content {
            margin-left: 250px;
            padding: 20px;
            transition: margin-left 0.3s ease;
            background-color: #f8f9fa;
            min-height: 100vh;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
        }

        /* Card Styles */
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
            background: linear-gradient(45deg, var(--gradient-start), var(--gradient-end));
            color: white;
            border-radius: 20px;
        }

        /* Card Variants */
        .stats-card.contractors { --gradient-start: #4158D0; --gradient-end: #C850C0; }
        .stats-card.open-defects { --gradient-start: #FF416C; --gradient-end: #FF4B2B; }
        .stats-card.total-defects { --gradient-start: #8EC5FC; --gradient-end: #E0C3FC; }
        .stats-card.pending-defects { --gradient-start: #F6D365; --gradient-end: #FDA085; }

        /* Card Components */
        .stats-label {
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
            color: rgba(255, 255, 255, 0.9);
        }

        .stats-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: white;
        }

        .stats-trend {
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.9);
        }

        .stats-icon {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            font-size: 2.5rem;
            opacity: 0.8;
            color: white;
        }

        /* Table Styles */
        .table {
            margin-bottom: 0;
        }

        .table th {
            border-top: none;
            background-color: rgba(0,0,0,0.02);
            font-weight: 600;
        }

        .table td {
            vertical-align: middle;
        }

        /* Card Styles */
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 25px 0 rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
        }

        .card-header {
            background: linear-gradient(45deg, #1a237e, #1565c0);
            color: white;
            border-bottom: none;
            border-radius: 15px 15px 0 0 !important;
            padding: 1rem 1.5rem;
        }

        .card-header h5 {
            margin: 0;
            font-weight: 600;
        }

        /* Progress Bar */
        .progress {
            height: 8px;
            margin-top: 4px;
            border-radius: 4px;
        }

        /* Badge Styling */
        .badge {
            padding: 0.5em 0.75em;
            font-weight: 500;
        }

        /* Debug Panel */
        .debug-info {
            background: linear-gradient(45deg, #2c3e50, #3498db);
            color: white;
            border-radius: 15px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .debug-info h4 {
            color: #fff;
            margin-bottom: 15px;
        }

        .debug-info hr {
            border-color: rgba(255, 255, 255, 0.2);
            margin: 15px 0;
        }

        /* Alert Styling */
        .alert {
            border-radius: 15px;
            border: none;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .alert-dismissible .btn-close {
            padding: 1.25rem;
        }

        /* Large Green Button */
        .btn-create-defect {
            font-size: 1.25rem;
            padding: 10px 30px;
            border-radius: 10px;
            background-color: #28a745;
            color: white;
            transition: background-color 0.3s ease;
        }

        .btn-create-defect:hover {
            background-color: #218838;
            color: white;
        }
    </style>
</head>
<body>
    <?php
    try {
        $navbarDb = new Database();
        $navbar = new Navbar(
            $navbarDb->getConnection(),
            isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0
        );
        echo $navbar->render();
    } catch (Exception $e) {
        error_log("Navbar Error: " . $e->getMessage());
        echo '<div class="alert alert-danger">Error loading navigation: ' . 
             htmlspecialchars($e->getMessage()) . '</div>';
    }
    ?>

    <div class="main-content">
        <div class="container-fluid px-4">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0 text-gray-800"><?php echo htmlspecialchars($pageTitle); ?></h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item active" aria-current="page">Overview</li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex">
                    <a href="create_defect.php" class="btn btn-create-defect me-2">
                        <i class='bx bx-plus-circle me-2'></i>Create Defect
                    </a>
                    <button class="btn btn-primary me-2" onclick="window.location.reload();">
                        <i class='bx bx-refresh me-1'></i> Refresh
                    </button>
                    <div class="dropdown">
                        <button class="btn btn-secondary dropdown-toggle" type="button" id="exportDropdown" 
                                data-bs-toggle="dropdown" aria-expanded="false">
                            <i class='bx bx-export me-1'></i> Export
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="exportDropdown">
                            <li><a class="dropdown-item" href="#" onclick="exportData('pdf')">Export as PDF</a></li>
                            <li><a class="dropdown-item" href="#" onclick="exportData('excel')">Export as Excel</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="#" onclick="exportData('csv')">Export as CSV</a></li>
                        </ul>
                    </div>
                </div>
            </div>

            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class='bx bx-error-circle me-2'></i>
                    <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="row">
                <div class="col-xl-3 col-md-6">
                    <div class="stats-card contractors">
                        <div class="gradient-layer">
                            <div class="stats-label">Active Contractors</div>
                            <div class="stats-value"><?php echo $overallStats['active_contractors']; ?></div>
                            <div class="stats-trend">
                                <?php echo getTrendIndicator(1); ?> Active Teams
                            </div>
                            <i class='bx bx-buildings stats-icon'></i>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="stats-card open-defects">
                        <div class="gradient-layer">
                            <div class="stats-label">Open Defects</div>
                            <div class="stats-value"><?php echo $overallStats['open_defects']; ?></div>
                            <div class="stats-trend">
                                <?php echo getTrendIndicator($overallStats['open_defects']); ?> Active Issues
                            </div>
                            <i class='bx bx-bug stats-icon'></i>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="stats-card total-defects">
                        <div class="gradient-layer">
                            <div class="stats-label">Total Defects</div>
                            <div class="stats-value"><?php echo $overallStats['total_defects']; ?></div>
                            <div class="stats-trend">
                                <?php echo getTrendIndicator(1); ?> All Time
                            </div>
                            <i class='bx bx-check-circle stats-icon'></i>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="stats-card pending-defects">
                        <div class="gradient-layer">
                            <div class="stats-label">Pending Defects</div>
                            <div class="stats-value"><?php echo $overallStats['pending_defects']; ?></div>
                            <div class="stats-trend">
                                <?php echo getTrendIndicator($overallStats['pending_defects']); ?> Awaiting Action
                            </div>
                            <i class='bx bx-time stats-icon'></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content Cards -->
            <div class="row">
                <!-- Contractor Defects Table -->
                <div class="col-xl-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class='bx bx-table me-2'></i>Defects by Contractor</h5>
                            <button class="btn btn-sm btn-primary">
                                <i class='bx bx-export me-1'></i>Export
                            </button>
                        </div>
                        <div class="card-body">
                            <?php if (empty($contractorStats)): ?>
                                <div class="alert alert-info mb-0">No active contractors found.</div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Contractor</th>
                                                <th>Trade</th>
                                                <th class="text-center">Total</th>
                                                <th class="text-center">Open</th>
                                                <th class="text-center">Progress</th>
                                                <th>Last Update</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($contractorStats as $stat): ?>
                                                <tr>
                                                    <td>
                                                        <a href="contractors.php?id=<?php echo $stat['id']; ?>" 
                                                           class="text-decoration-none">
                                                            <?php echo htmlspecialchars($stat['company_name']); ?>
                                                        </a>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($stat['trade']); ?></td>
                                                    <td class="text-center"><?php echo $stat['total_defects']; ?></td>
                                                    <td class="text-center">
                                                        <span class="badge bg-danger">
                                                            <?php echo $stat['open_defects']; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $total = (int)$stat['total_defects'];
                                                        $closed = (int)$stat['closed_defects'];
                                                        $progress = $total > 0 ? ($closed / $total) * 100 : 0;
                                                        ?>
                                                        <div class="progress">
                                                            <div class="progress-bar bg-success" 
                                                                 role="progressbar" 
                                                                 style="width: <?php echo $progress; ?>%"
                                                                 aria-valuenow="<?php echo $progress; ?>" 
                                                                 aria-valuemin="0" 
                                                                 aria-valuemax="100">
                                                            </div>
                                                        </div>
                                                        <small class="text-muted"><?php echo round($progress); ?>%</small>
                                                    </td>
                                                    <td>
                                                        <small class="text-muted">
                                                            <?php echo formatUKDate($stat['last_update']); ?>
                                                        </small>
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
                <!-- Recent Defects -->
                <div class="col-xl-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class='bx bx-history me-2'></i>Recent Defects</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recentDefects)): ?>
                                <div class="alert alert-info mb-0">No recent defects found.</div>
                            <?php else: ?>
                                <?php foreach ($recentDefects as $defect): ?>
                                    <div class="d-flex align-items-center border-bottom py-3">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1">
                                                <a href="defects.php?id=<?php echo $defect['id']; ?>" 
                                                   class="text-decoration-none text-reset">
                                                    <?php echo htmlspecialchars($defect['title']); ?>
                                                </a>
                                            </h6>
                                            <div class="mb-2">
                                                <span class="badge bg-<?php echo getStatusColor($defect['status']); ?>">
                                                    <?php echo ucfirst($defect['status']); ?>
                                                </span>
                                                <span class="badge bg-<?php echo getPriorityColor($defect['priority']); ?> ms-1">
                                                    <?php echo ucfirst($defect['priority']); ?>
                                                </span>
                                            </div>
                                            <small class="text-muted">
                                                <i class='bx bx-buildings'></i> 
                                                <?php echo htmlspecialchars($defect['contractor_name']); ?> -
                                                <i class='bx bx-folder'></i>
                                                <?php echo htmlspecialchars($defect['project_name']); ?>
                                            </small>
                                            <br>
                                            <small class="text-muted">
                                                <i class='bx bx-time'></i> 
                                                <?php echo getTimeAgo($defect['created_at']); ?>
                                            </small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Essential JavaScript Dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Bootstrap components
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            });

            var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
            var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
                return new bootstrap.Popover(popoverTriggerEl)
            });

            // Auto hide alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert-dismissible:not(.debug-info)');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    new bootstrap.Alert(alert).close();
                }, 5000);
            });

            // Set up periodic refresh
            setupAutoRefresh();
        });

        function setupAutoRefresh() {
            // Refresh dashboard data every 5 minutes
            setInterval(function() {
                fetch(window.location.href)
                    .then(response => response.text())
                    .then(html => {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        
                        // Update statistics cards
                        const statsCards = doc.querySelectorAll('.stats-card');
                        statsCards.forEach((card, index) => {
                            const currentCard = document.querySelectorAll('.stats-card')[index];
                            if (currentCard) {
                                currentCard.innerHTML = card.innerHTML;
                            }
                        });

                        // Update tables
                        const tables = doc.querySelectorAll('.table');
                        tables.forEach((table, index) => {
                            const currentTable = document.querySelectorAll('.table')[index];
                            if (currentTable) {
                                currentTable.innerHTML = table.innerHTML;
                            }
                        });

                        // Update recent defects
                        const recentDefects = doc.querySelector('.col-xl-4 .card-body');
                        if (recentDefects) {
                            document.querySelector('.col-xl-4 .card-body').innerHTML = recentDefects.innerHTML;
                        }
                    })
                    .catch(error => console.error('Error refreshing data:', error));
            }, 300000); // 5 minutes
        }

        function exportData(format) {
            const timestamp = new Date().toISOString().replace(/[^0-9]/g, '').slice(0, 14);
            const filename = `defects-dashboard-${timestamp}.${format}`;
            
            // Show loading indicator
            const exportBtn = document.querySelector('#exportDropdown');
            const originalText = exportBtn.innerHTML;
            exportBtn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Exporting...';
            
            // Simulate export process
            setTimeout(() => {
                exportBtn.innerHTML = originalText;
                alert(`Data exported as ${format.toUpperCase()}: ${filename}`);
            }, 1000);

            // TODO: Implement actual export functionality
            console.log(`Exporting data in ${format} format...`);
        }
    </script>
</body>
</html>
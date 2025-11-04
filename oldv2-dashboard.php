<?php
/**
 * dashboard.php - Enhanced Responsive Version
 * Current Date and Time (UTC): 2025-03-17 13:49:30
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

    // Get defects by contractor statistics (updated to count 'pending' defects)
    $contractorsQuery = "SELECT 
        c.id,
        c.company_name,
        c.status as contractor_status,
        c.logo,
        c.trade,
        COUNT(d.id) as total_defects,
        SUM(CASE WHEN LOWER(d.status) = 'open' THEN 1 ELSE 0 END) as open_defects,
        SUM(CASE WHEN LOWER(d.status) = 'pending' THEN 1 ELSE 0 END) as pending_defects,
        SUM(CASE WHEN LOWER(d.status) = 'accepted' THEN 1 ELSE 0 END) as closed_defects,
        SUM(CASE WHEN LOWER(d.status) = 'rejected' THEN 1 ELSE 0 END) as rejected_defects,
        IFNULL(MAX(d.updated_at), 'N/A') as last_update
    FROM contractors c
    LEFT JOIN defects d ON c.id = d.assigned_to
    WHERE c.status = 'active'
    GROUP BY c.id, c.company_name, c.status, c.logo, c.trade
    ORDER BY total_defects DESC, company_name ASC";
    $contractorsStmt = $db->prepare($contractorsQuery);
    $contractorsStmt->execute();
    $contractorStats = $contractorsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get recent defects with enhanced details
    $recentDefects = $db->prepare("
        SELECT 
            d.id,
            d.title,
            d.description,
            d.status,
            d.priority,
            d.created_at,
            d.updated_at,
            c.company_name,
            c.trade,
            c.logo,
            u.username as reported_by
        FROM defects d
        LEFT JOIN contractors c ON d.assigned_to = c.id
        LEFT JOIN users u ON d.created_by = u.id
        ORDER BY d.created_at DESC
        LIMIT 10
    ");
    $recentDefects->execute();
    $recentDefectsList = $recentDefects->fetchAll(PDO::FETCH_ASSOC);

    // Prepare contractor data for JavaScript filters
    $contractorOptions = [];
    foreach ($contractorStats as $contractor) {
        $contractorOptions[] = [
            'id' => $contractor['id'],
            'name' => $contractor['company_name'],
            'trade' => $contractor['trade'] ?? 'N/A'
        ];
    }
    $contractorOptionsJSON = json_encode($contractorOptions);

} catch (Exception $e) {
    error_log("Dashboard Error: " . $e->getMessage());
    $error_message = "An error occurred while loading the dashboard: " . $e->getMessage();
}

// Helper functions
function getPriorityColor($priority) {
    switch (strtolower($priority)) {
        case 'high': return 'danger';
        case 'medium': return 'warning';
        case 'low': return 'success';
        default: return 'secondary';
    }
}

function formatUKDate($date) {
    return $date && $date !== 'N/A' ? date('d/m/Y H:i', strtotime($date)) : 'N/A';
}
function formatUKDateTime($date) {
    return $date ? date('d/m/Y H:i', strtotime($date)) : 'N/A';
}

function getStatusBadgeClass($status) {
    switch (strtolower($status)) {
        case 'open': return 'danger';
        case 'pending': return 'warning';
        case 'accepted': return 'success';
        case 'rejected': return 'secondary';
        default: return 'info';
    }
}

function getPriorityBadgeClass($priority) {
    switch (strtolower($priority)) {
        case 'high': return 'danger';
        case 'medium': return 'warning';
        case 'low': return 'success';
        default: return 'info';
    }
}

function getTimeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . 'm ago';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . 'h ago';
    } elseif ($diff < 604800) {
        return floor($diff / 86400) . 'd ago';
    } else {
        return date('d/m/Y', $time);
    }
}

$navbar = new Navbar($db, $_SESSION['user_id'], $_SESSION['username']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Defect Tracker Dashboard">
    <meta name="author" content="<?php echo htmlspecialchars($currentUser ?? ''); ?>">
    <title><?php echo htmlspecialchars($pageTitle ?? ''); ?> - Defect Tracker</title>
    <link rel="icon" type="image/png" href="/favicons/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicons/favicon.svg" />
    <link rel="shortcut icon" href="/favicons/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png" />
    <link rel="manifest" href="/favicons/site.webmanifest" />
    
    <!-- Essential CSS Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

    <style>
        :root {
            --primary-color: #4158D0;
            --secondary-color: #C850C0;
            --accent-color: #FF416C;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --card-border-radius: 16px;
            --box-shadow: 0 4px 25px 0 rgba(0, 0, 0, 0.1);
            --hover-box-shadow: 0 6px 28px 0 rgba(0, 0, 0, 0.15);
            --transition-speed: 0.3s;
        }

        /* Base Layout */
        body {
            background-color: #f5f7fa;
            font-family: 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
        }
        
        .main-content {
            padding: 25px 15px;
            background-color: #f5f7fa;
            min-height: calc(100vh - 56px);
            margin-top: 56px; /* Adjust based on the height of your navbar */
        }
		        /* Components */
        .dashboard-card {
            background-color: white;
            border-radius: var(--card-border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 20px;
            transition: box-shadow var(--transition-speed);
        }
        
        .dashboard-card:hover {
            box-shadow: var(--hover-box-shadow);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            background-color: white;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            border-radius: var(--card-border-radius) var(--card-border-radius) 0 0;
        }
        
        .card-body {
            padding: 20px;
        }
        
        /* Stats Cards */
        .stats-card {
            color: white;
            border-radius: var(--card-border-radius);
            height: 100%;
            overflow: hidden;
            position: relative;
        }
        
        .stats-card .gradient-layer {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.15), rgba(0, 0, 0, 0));
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 25px 20px;
            height: 100%;
        }
        
        .stats-card.contractors {
            background: linear-gradient(135deg, #4158D0, #C850C0);
        }
        
        .stats-card.open-defects {
            background: linear-gradient(135deg, #FF416C, #FF4B2B);
        }
        
        .stats-card.total-defects {
            background: linear-gradient(135deg, #0061FF, #60EFFF);
        }
        
        .stats-card.pending-defects {
            background: linear-gradient(135deg, #FFD166, #FF9B42);
        }
        
        .stats-card .card-title {
            font-size: 1rem;
            font-weight: 500;
            margin-bottom: 8px;
        }
        
        .stats-card .card-text {
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
        }
        
        .stats-card .card-icon {
            font-size: 3rem;
            opacity: 0.7;
        }
        
        .fade-in {
            animation: fadeIn 0.5s;
        }
        
        @keyframes fadeIn {
            0% { opacity: 0; }
            100% { opacity: 1; }
        }
        
        /* UK Time Display */
        .uk-time-display {
            background: linear-gradient(135deg, #3498db, #2c3e50);
            color: white;
            padding: 10px 20px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            font-weight: 600;
            box-shadow: var(--box-shadow);
        }
        
        .uk-time-display i {
            margin-right: 10px;
            opacity: 0.8;
        }
        
        /* Table Styles */
        .table {
            margin-bottom: 0;
        }
        
        .table th {
            font-weight: 600;
            background-color: rgba(0, 0, 0, 0.03);
            border-bottom-width: 1px;
        }
        
        .row-details {
            background-color: rgba(0, 0, 0, 0.02);
            padding: 15px;
            display: none;
            border-top: 1px dashed rgba(0, 0, 0, 0.1);
        }
        
        .expandable-row {
            cursor: pointer;
        }
        
        .company-logo {
            width: 30px;
            height: 30px;
            object-fit: contain;
            border-radius: 4px;
            background-color: #fff;
            box-shadow: 0 0 4px rgba(0, 0, 0, 0.1);
        }
        
        .company-icon {
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            background-color: rgba(0, 0, 0, 0.05);
            color: #888;
        }
        
        /* Mobile Specific Styles */
        @media (max-width: 767.98px) {
            .mobile-hide-table {
                display: none;
            }
            
            .data-card {
                background-color: white;
                border-radius: 10px;
                padding: 15px;
                margin-bottom: 15px;
                box-shadow: var(--box-shadow);
            }
            
            .data-card-title {
                font-size: 1rem;
                font-weight: 600;
                color: var(--primary-color);
                margin-bottom: 10px;
            }
            
            .data-card-label {
                font-size: 0.8rem;
                color: #6c757d;
                font-weight: 500;
                margin-bottom: 2px;
            }
            
            .data-card-value {
                font-size: 0.95rem;
                color: #343a40;
                margin-bottom: 8px;
            }
        }
        
        /* Action Buttons */
        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: none;
            background-color: rgba(0, 0, 0, 0.05);
            color: #6c757d;
            margin-left: 5px;
            transition: all var(--transition-speed);
        }
        
        .action-btn:hover {
            background-color: rgba(0, 0, 0, 0.1);
            color: var(--primary-color);
        }
        
        /* Create Defect Button */
        .btn-create-defect {
            background-image: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            color: white;
            border-radius: 20px;
            padding: 8px 20px;
            transition: transform var(--transition-speed), box-shadow var(--transition-speed);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        
        .btn-create-defect:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.15);
            color: white;
        }
        
        /* Loading/Refreshing Indicator */
        .refresh-indicator {
            width: 16px;
            height: 16px;
            border: 2px solid rgba(0, 0, 0, 0.1);
            border-top-color: #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            display: inline-block;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Toast Notifications */
        .toast-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1050;
        }
        
        .custom-toast {
            min-width: 250px;
            margin-top: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
    </style>
    
    <script>
        // Pass PHP data to JavaScript for filters
        const contractorData = <?php echo $contractorOptionsJSON; ?>;
    </script>
</head>
<body>
    <?php $navbar->render(); ?>
    
    <?php if ($error_message): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($error_message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>
	    <div class="main-content">
        <div class="container-fluid">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center flex-wrap mb-4">
                <h2 class="mb-0 text-dark fw-bold">
                    <i class="fas fa-tachometer-alt me-2 text-primary"></i> Defects Dashboard
                </h2>
                
                <div class="d-flex align-items-center">
                    <div class="uk-time-display me-3">
                        <i class="fas fa-clock"></i>
                        <span id="ukTime">Loading UK time...</span>
                    </div>
                    <a href="create_defect.php" class="btn btn-create-defect">
                        <i class="fas fa-plus-circle me-1"></i> Create Defect
                    </a>
                </div>
            </div>
            
            <!-- Statistics Row -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-4 mb-lg-0">
                    <div class="stats-card contractors">
                        <div class="gradient-layer">
                            <div>
                                <h6 class="card-title">Active Contractors</h6>
                                <p class="card-text"><?php echo intval($overallStats['active_contractors'] ?? 0); ?></p>
                            </div>
                            <i class="fas fa-hard-hat card-icon"></i>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4 mb-lg-0">
                    <div class="stats-card open-defects">
                        <div class="gradient-layer">
                            <div>
                                <h6 class="card-title">Open Defects</h6>
                                <p class="card-text"><?php echo intval($overallStats['open_defects'] ?? 0); ?></p>
                            </div>
                            <i class="fas fa-exclamation-circle card-icon"></i>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4 mb-lg-0">
                    <div class="stats-card total-defects">
                        <div class="gradient-layer">
                            <div>
                                <h6 class="card-title">Total Defects</h6>
                                <p class="card-text"><?php echo intval($overallStats['total_defects'] ?? 0); ?></p>
                            </div>
                            <i class="fas fa-clipboard-list card-icon"></i>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-4 mb-lg-0">
                    <div class="stats-card pending-defects">
                        <div class="gradient-layer">
                            <div>
                                <h6 class="card-title">Pending Review</h6>
                                <p class="card-text"><?php echo intval($overallStats['pending_defects'] ?? 0); ?></p>
                            </div>
                            <i class="fas fa-clock card-icon"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contractor Stats -->
            <div class="dashboard-card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Contractor Statistics</h5>
                    <div>
                        <button id="refreshContractors" class="action-btn" title="Refresh Data">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                        <button id="filterContractors" class="action-btn" title="Filter Data">
                            <i class="fas fa-filter"></i>
                        </button>
                        <button id="exportContractors" class="action-btn" title="Export to CSV">
                            <i class="fas fa-download"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Table for medium screens and up -->
                    <div class="table-responsive d-none d-md-block">
                        <table id="recentDefectsTable" class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Status</th>
                                    <th>Priority</th>
                                    <th>Title</th>
                                    <th>Contractor</th>
                                    <th>Created</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentDefectsList)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-3 text-muted">No defects available</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($recentDefectsList as $defect): ?>
                                    <tr class="expandable-row" data-defect-id="<?php echo $defect['id']; ?>">
                                        <td><?php echo $defect['id']; ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo getStatusBadgeClass($defect['status']); ?>">
                                                <?php echo ucfirst(htmlspecialchars($defect['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo getPriorityBadgeClass($defect['priority']); ?>">
                                                <?php echo ucfirst(htmlspecialchars($defect['priority'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($defect['title']); ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php if (!empty($defect['logo'])): ?>
                                                    <img src="<?php echo htmlspecialchars($defect['logo']); ?>" 
                                                         class="company-logo me-2" alt="Logo">
                                                <?php else: ?>
                                                    <div class="company-icon me-2">
                                                        <i class="fas fa-building"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <?php echo htmlspecialchars($defect['company_name'] ?? 'Unassigned'); ?>
                                            </div>
                                        </td>
                                        <td><?php echo formatUKDateTime($defect['created_at']); ?></td>
                                        <td class="text-center">
                                            <a href="#" class="toggle-details" title="Toggle Details">
                                                <i class="fas fa-chevron-down"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <tr id="details-<?php echo $defect['id']; ?>" class="details-row">
                                        <td colspan="7" class="p-0">
                                            <div class="row-details">
                                                <div class="row">
                                                    <div class="col-md-8">
                                                        <h6 class="mb-2">Description:</h6>
                                                        <p><?php echo nl2br(htmlspecialchars($defect['description'])); ?></p>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="mb-3">
                                                            <h6 class="mb-1">Reported By:</h6>
                                                            <p class="mb-3"><?php echo htmlspecialchars($defect['reported_by']); ?></p>
                                                            
                                                            <h6 class="mb-1">Last Updated:</h6>
                                                            <p class="mb-3"><?php echo formatUKDateTime($defect['updated_at']); ?></p>
                                                            
                                                            <h6 class="mb-1">Trade:</h6>
                                                            <p><?php echo htmlspecialchars($defect['trade'] ?? 'N/A'); ?></p>
                                                        </div>
                                                        
                                                        <a href="view_defect.php?id=<?php echo $defect['id']; ?>" 
                                                           class="btn btn-sm btn-primary">
                                                            View Full Details
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Cards for small screens -->
                    <div class="d-md-none">
                        <?php if (empty($contractorStats)): ?>
                        <div class="text-center py-3 text-muted">No contractor data available</div>
                        <?php else: ?>
                            <?php foreach ($contractorStats as $contractor): ?>
                            <div class="data-card">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div class="d-flex align-items-center">
                                        <?php if (!empty($contractor['logo'])): ?>
                                            <?php
                                            $logoFilename = $contractor['logo'];
                                            // Handle both old format (uploads/logos/filename.png) and new format (filename.png)
                                            if (stripos($logoFilename, 'uploads/logos/') === 0) {
                                                $logoFilename = substr($logoFilename, strlen('uploads/logos/'));
                                            }
                                            $logoSrc = '/uploads/logos/' . $logoFilename;
                                            ?>
                                            <img src="<?php echo htmlspecialchars($logoSrc); ?>" 
                                                 class="company-logo me-2" alt="Logo">
                                        <?php else: ?>
                                            <div class="company-icon me-2">
                                                <i class="fas fa-building"></i>
                                            </div>
                                        <?php endif; ?>
                                        <h6 class="data-card-title mb-0">
                                            <?php echo htmlspecialchars($contractor['company_name']); ?>
                                        </h6>
                                    </div>
                                    <span class="badge bg-light text-dark">
                                        <?php echo htmlspecialchars($contractor['trade'] ?? 'N/A'); ?>
                                    </span>
                                </div>
                                
                                <div class="row">
                                    <div class="col-3">
                                        <div class="data-card-label">Open</div>
                                        <div class="data-card-value">
                                            <span class="badge bg-danger">
                                                <?php echo intval($contractor['open_defects']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="data-card-label">Pending</div>
                                        <div class="data-card-value">
                                            <span class="badge bg-warning">
                                                <?php echo intval($contractor['pending_defects']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="data-card-label">Closed</div>
                                        <div class="data-card-value">
                                            <span class="badge bg-success">
                                                <?php echo intval($contractor['closed_defects']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-3">
                                        <div class="data-card-label">Rejected</div>
                                        <div class="data-card-value">
                                            <span class="badge bg-secondary">
                                                <?php echo intval($contractor['rejected_defects']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="text-muted mt-2" style="font-size: 0.8rem;">
                                    Last update: 
                                    <?php echo $contractor['last_update'] !== 'N/A' ? 
                                        formatUKDateTime($contractor['last_update']) : 'N/A'; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
			            <!-- Recent Defects -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h5 class="mb-0">Recent Defects</h5>
                    <div>
                        <button id="refreshDefects" class="action-btn" title="Refresh Data">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                        <button id="filterDefects" class="action-btn" title="Filter Data">
                            <i class="fas fa-filter"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Table for medium screens and up -->
                    <div class="table-responsive d-none d-md-block">
                        <table id="recentDefectsTable" class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Status</th>
                                    <th>Priority</th>
                                    <th>Title</th>
                                    <th>Contractor</th>
                                    <th>Created</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentDefectsList)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-3 text-muted">No defects available</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($recentDefectsList as $defect): ?>
                                    <tr class="expandable-row" data-defect-id="<?php echo $defect['id']; ?>">
                                        <td><?php echo $defect['id']; ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo getStatusBadgeClass($defect['status']); ?>">
                                                <?php echo ucfirst(htmlspecialchars($defect['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo getPriorityBadgeClass($defect['priority']); ?>">
                                                <?php echo ucfirst(htmlspecialchars($defect['priority'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($defect['title']); ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php if (!empty($defect['logo'])): ?>
                                                    <img src="<?php echo htmlspecialchars($defect['logo']); ?>" 
                                                         class="company-logo me-2" alt="Logo">
                                                <?php else: ?>
                                                    <div class="company-icon me-2">
                                                        <i class="fas fa-building"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <?php echo htmlspecialchars($defect['company_name'] ?? 'Unassigned'); ?>
                                            </div>
                                        </td>
                                        <td><?php echo formatUKDateTime($defect['created_at']); ?></td>
                                        <td class="text-center">
                                            <a href="#" class="toggle-details" title="Toggle Details">
                                                <i class="fas fa-chevron-down"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <tr id="details-<?php echo $defect['id']; ?>" class="details-row">
                                        <td colspan="7" class="p-0">
                                            <div class="row-details">
                                                <div class="row">
                                                    <div class="col-md-8">
                                                        <h6 class="mb-2">Description:</h6>
                                                        <p><?php echo nl2br(htmlspecialchars($defect['description'])); ?></p>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="mb-3">
                                                            <h6 class="mb-1">Reported By:</h6>
                                                            <p class="mb-3"><?php echo htmlspecialchars($defect['reported_by']); ?></p>
                                                            
                                                            <h6 class="mb-1">Last Updated:</h6>
                                                            <p class="mb-3"><?php echo formatUKDateTime($defect['updated_at']); ?></p>
                                                            
                                                            <h6 class="mb-1">Trade:</h6>
                                                            <p><?php echo htmlspecialchars($defect['trade'] ?? 'N/A'); ?></p>
                                                        </div>
                                                        
                                                        <a href="view_defect.php?id=<?php echo $defect['id']; ?>" 
                                                           class="btn btn-sm btn-primary">
                                                            View Full Details
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Cards for small screens -->
                    <div class="d-md-none">
                        <?php if (empty($recentDefectsList)): ?>
                        <div class="text-center py-3 text-muted">No defects available</div>
                        <?php else: ?>
                            <?php foreach ($recentDefectsList as $defect): ?>
                            <div class="data-card">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="data-card-title mb-0">
                                        #<?php echo $defect['id']; ?>: 
                                        <?php echo htmlspecialchars($defect['title']); ?>
                                    </h6>
                                    <div>
                                        <span class="badge bg-<?php echo getStatusBadgeClass($defect['status']); ?>">
                                            <?php echo ucfirst(htmlspecialchars($defect['status'])); ?>
                                        </span>
                                        <span class="badge bg-<?php echo getPriorityBadgeClass($defect['priority']); ?> ms-1">
                                            <?php echo ucfirst(htmlspecialchars($defect['priority'])); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="data-card-label">Assigned To:</div>
                                <div class="data-card-value">
                                    <div class="d-flex align-items-center">
                                        <?php if (!empty($defect['logo'])): ?>
                                            <img src="<?php echo htmlspecialchars($defect['logo']); ?>" 
                                                 class="company-logo me-2" alt="Logo" style="width: 20px; height: 20px;">
                                        <?php else: ?>
                                            <div class="company-icon me-2" style="width: 20px; height: 20px; font-size: 10px;">
                                                <i class="fas fa-building"></i>
                                            </div>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($defect['company_name'] ?? 'Unassigned'); ?>
                                    </div>
                                </div>
                                
                                <div class="text-muted mt-2" style="font-size: 0.8rem;">
                                    Created: <?php echo formatUKDateTime($defect['created_at']); ?>
                                </div>
                                
                                <div class="mt-2">
                                    <a href="view_defect.php?id=<?php echo $defect['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        View Details
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Toast Container -->
    <div class="toast-container"></div>
    
    <!-- Essential JavaScript Dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize Bootstrap tooltips and popovers
        [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            .forEach(function(tooltipEl) { new bootstrap.Tooltip(tooltipEl); });
        [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
            .forEach(function(popoverEl) { new bootstrap.Popover(popoverEl); });

        // Auto hide alerts after 5 seconds
        document.querySelectorAll('.alert-dismissible:not(.debug-info)')
            .forEach(function(alert) {
                setTimeout(function() { new bootstrap.Alert(alert).close(); }, 5000);
            });

        // Set up periodic refresh
        setupAutoRefresh();

        // Initialize the UK time display
        updateTimes();
        setInterval(updateTimes, 1000);

        // Initialize expandable rows
        initializeExpandableRows();

        // Initialize refresh buttons
        initializeRefreshButtons();

        // Initialize filter buttons
        initializeFilterButtons();

        // Initialize export buttons
        initializeExportButtons();

        // Create filter modals if they don't exist
        createFilterModals();
    });

    function setupAutoRefresh() {
        // Refresh dashboard data every 5 minutes
        setInterval(function() {
            // Show refresh indicator
            showToast('Refreshing dashboard data...', 'info');
            
            fetch(window.location.href)
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    
                    // Update statistics cards
                    doc.querySelectorAll('.stats-card').forEach((card, index) => {
                        const currentCard = document.querySelectorAll('.stats-card')[index];
                        if (currentCard) {
                            const cardText = card.querySelector('.card-text');
                            const currentCardText = currentCard.querySelector('.card-text');
                            if (cardText && currentCardText) {
                                // Check if value has changed
                                const oldValue = currentCardText.textContent;
                                const newValue = cardText.textContent;
                                
                                if (oldValue !== newValue) {
                                    currentCardText.textContent = newValue;
                                    currentCardText.classList.add('fade-in');
                                    setTimeout(() => {
                                        currentCardText.classList.remove('fade-in');
                                    }, 500);
                                }
                            }
                        }
                    });
                    
                    // Update contractor table body
                    const contractorsTable = document.getElementById('contractorsTable');
                    const newContractorsTable = doc.getElementById('contractorsTable');
                    if (contractorsTable && newContractorsTable) {
                        const tbody = contractorsTable.querySelector('tbody');
                        const newTbody = newContractorsTable.querySelector('tbody');
                        if (tbody && newTbody) {
                            tbody.innerHTML = newTbody.innerHTML;
                        }
                    }
                    
                    // Update defects table body
                    const recentDefectsTable = document.getElementById('recentDefectsTable');
                    const newRecentDefectsTable = doc.getElementById('recentDefectsTable');
                    if (recentDefectsTable && newRecentDefectsTable) {
                        const tbody = recentDefectsTable.querySelector('tbody');
                        const newTbody = newRecentDefectsTable.querySelector('tbody');
                        if (tbody && newTbody) {
                            tbody.innerHTML = newTbody.innerHTML;
                            // Re-initialize expandable rows
                            initializeExpandableRows();
                        }
                    }
                    
                    // Update mobile cards
                    document.querySelectorAll('.d-md-none').forEach((container, index) => {
                        const newContainer = doc.querySelectorAll('.d-md-none')[index];
                        if (container && newContainer) {
                            container.innerHTML = newContainer.innerHTML;
                        }
                    });
                    
                    showToast('Dashboard data refreshed successfully!', 'success');
                })
                .catch(error => {
                    console.error('Error refreshing data:', error);
                    showToast('Failed to refresh dashboard data', 'danger');
                });
        }, 300000); // 5 minutes
    }

    function updateTimes() {
        // Update the UK time display using the correct UTC time
        const now = new Date();
        const options = {
            timeZone: 'Europe/London',
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false
        };
        
        // Display formatted time
        const ukTimeString = now.toLocaleString('en-GB', options);
        document.getElementById('ukTime').textContent = ukTimeString;
    }

    function initializeExpandableRows() {
        // Add click event to expandable rows
        document.querySelectorAll('.expandable-row').forEach(row => {
            // Remove existing event listeners first
            const toggleBtn = row.querySelector('.toggle-details');
            if (toggleBtn) {
                const newToggleBtn = toggleBtn.cloneNode(true);
                toggleBtn.parentNode.replaceChild(newToggleBtn, toggleBtn);
                
                newToggleBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const defectId = row.dataset.defectId;
                    const detailsRow = document.getElementById('details-' + defectId);
                    const detailsContent = detailsRow.querySelector('.row-details');
                    const icon = this.querySelector('i');
                    
                    if (detailsContent.style.display === 'block') {
                        detailsContent.style.display = 'none';
                        icon.classList.replace('fa-chevron-up', 'fa-chevron-down');
                    } else {
                        detailsContent.style.display = 'block';
                        icon.classList.replace('fa-chevron-down', 'fa-chevron-up');
                    }
                });
            }
        });
    }

    function initializeRefreshButtons() {
        // Add click event for refresh buttons
        document.getElementById('refreshContractors').addEventListener('click', function() {
            refreshTableData('contractorsTable');
        });
        
        document.getElementById('refreshDefects').addEventListener('click', function() {
            refreshTableData('recentDefectsTable');
        });
    }

    function refreshTableData(tableId) {
        // Show loading indicator
        const button = document.getElementById(tableId === 'contractorsTable' ? 'refreshContractors' : 'refreshDefects');
        const originalIcon = button.innerHTML;
        button.innerHTML = '<div class="refresh-indicator"></div>';
        button.disabled = true;
        
        // Fetch updated data
        fetch(window.location.href)
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                
                const oldTable = document.getElementById(tableId);
                const newTable = doc.getElementById(tableId);
                
                if (oldTable && newTable) {
                    const oldTbody = oldTable.querySelector('tbody');
                    const newTbody = newTable.querySelector('tbody');
                    
                    if (oldTbody && newTbody) {
                        oldTbody.innerHTML = newTbody.innerHTML;
                        
                        // Re-initialize expandable rows if needed
                        if (tableId === 'recentDefectsTable') {
                            initializeExpandableRows();
                        }
                        
                        // Update mobile views too
                        const cardContainer = oldTable.closest('.card-body').querySelector('.d-md-none');
                        const newCardContainer = newTable.closest('.card-body').querySelector('.d-md-none');
                        
                        if (cardContainer && newCardContainer) {
                            cardContainer.innerHTML = newCardContainer.innerHTML;
                        }
                        
                        showToast('Data refreshed successfully!', 'success');
                    }
                }
                
                // Restore button
                button.innerHTML = originalIcon;
                button.disabled = false;
            })
            .catch(error => {
                console.error('Error refreshing data:', error);
                showToast('Failed to refresh data', 'danger');
                
                // Restore button
                button.innerHTML = originalIcon;
                button.disabled = false;
            });
    }

    function initializeFilterButtons() {
        // Add click event for filter buttons
        document.getElementById('filterContractors').addEventListener('click', function() {
            // Open the contractors filter modal
            const contractorsFilterModal = new bootstrap.Modal(document.getElementById('contractorsFilterModal'));
            contractorsFilterModal.show();
        });
        
        document.getElementById('filterDefects').addEventListener('click', function() {
            // Open the defects filter modal
            const defectsFilterModal = new bootstrap.Modal(document.getElementById('defectsFilterModal'));
            defectsFilterModal.show();
        });
    }

    function createFilterModals() {
        // Create contractors filter modal if it doesn't exist
        if (!document.getElementById('contractorsFilterModal')) {
            const contractorsModal = document.createElement('div');
            contractorsModal.className = 'modal fade';
            contractorsModal.id = 'contractorsFilterModal';
            contractorsModal.setAttribute('tabindex', '-1');
            contractorsModal.setAttribute('aria-hidden', 'true');
            
            // Get unique trade values from the contractor data
            const trades = [...new Set(contractorData.map(c => c.trade))].filter(t => t !== 'N/A').sort();
            
            // Get unique contractor names from the contractor data
            const contractors = contractorData.map(c => c.name).sort();
            
            contractorsModal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Filter Contractors</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form id="contractorsFilterForm">
                                <div class="mb-3">
                                    <label for="companyNameFilter" class="form-label">Company Name</label>
                                    <select class="form-select" id="companyNameFilter">
                                        <option value="">All Contractors</option>
                                        ${contractors.map(name => `<option value="${name}">${name}</option>`).join('')}
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="tradeFilter" class="form-label">Trade</label>
                                    <select class="form-select" id="tradeFilter">
                                        <option value="">All Trades</option>
                                        ${trades.map(trade => `<option value="${trade}">${trade}</option>`).join('')}
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="open" id="openDefectsFilter" checked>
                                        <label class="form-check-label" for="openDefectsFilter">
                                            Has Open Defects
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="pending" id="pendingDefectsFilter" checked>
                                        <label class="form-check-label" for="pendingDefectsFilter">
                                            Has Pending Defects
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="closed" id="closedDefectsFilter" checked>
                                        <label class="form-check-label" for="closedDefectsFilter">
                                            Has Closed Defects
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="rejected" id="rejectedDefectsFilter" checked>
                                        <label class="form-check-label" for="rejectedDefectsFilter">
                                            Has Rejected Defects
                                        </label>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-primary" id="resetContractorsFilter">Reset</button>
                            <button type="button" class="btn btn-success" id="applyContractorsFilter">Apply Filter</button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(contractorsModal);
            
            // Set up filter application
            document.getElementById('applyContractorsFilter').addEventListener('click', function() {
                applyContractorsFilter();
                bootstrap.Modal.getInstance(document.getElementById('contractorsFilterModal')).hide();
            });
            
            // Set up filter reset
            document.getElementById('resetContractorsFilter').addEventListener('click', function() {
                document.getElementById('companyNameFilter').value = '';
                document.getElementById('tradeFilter').value = '';
                document.getElementById('openDefectsFilter').checked = true;
                document.getElementById('pendingDefectsFilter').checked = true;
                document.getElementById('closedDefectsFilter').checked = true;
                document.getElementById('rejectedDefectsFilter').checked = true;
                
                resetContractorsFilter();
            });
        }
        
        // Create defects filter modal if it doesn't exist
        if (!document.getElementById('defectsFilterModal')) {
            const defectsModal = document.createElement('div');
            defectsModal.className = 'modal fade';
            defectsModal.id = 'defectsFilterModal';
            defectsModal.setAttribute('tabindex', '-1');
            defectsModal.setAttribute('aria-hidden', 'true');
            
            defectsModal.innerHTML = `
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Filter Defects</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form id="defectsFilterForm">
                                <div class="mb-3">
                                    <label for="defectStatusFilter" class="form-label">Status</label>
                                    <select class="form-select" id="defectStatusFilter">
                                        <option value="">All Statuses</option>
                                        <option value="open">Open</option>
                                        <option value="pending">Pending</option>
                                        <option value="accepted">Accepted</option>
                                        <option value="rejected">Rejected</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="defectPriorityFilter" class="form-label">Priority</label>
                                    <select class="form-select" id="defectPriorityFilter">
                                        <option value="">All Priorities</option>
                                        <option value="high">High</option>
                                        <option value="medium">Medium</option>
                                        <option value="low">Low</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="defectTitleFilter" class="form-label">Title Search</label>
                                    <input type="text" class="form-control" id="defectTitleFilter" placeholder="Search in defect titles">
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="defectDateFromFilter" class="form-label">Date From</label>
                                        <input type="date" class="form-control" id="defectDateFromFilter">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="defectDateToFilter" class="form-label">Date To</label>
                                        <input type="date" class="form-control" id="defectDateToFilter">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="defectContractorFilter" class="form-label">Contractor</label>
                                    <select class="form-select" id="defectContractorFilter">
                                        <option value="">All Contractors</option>
                                        ${contractors.map(name => `<option value="${name}">${name}</option>`).join('')}
                                    </select>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-primary" id="resetDefectsFilter">Reset</button>
                            <button type="button" class="btn btn-success" id="applyDefectsFilter">Apply Filter</button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(defectsModal);
            
            // Set up filter application
            document.getElementById('applyDefectsFilter').addEventListener('click', function() {
                applyDefectsFilter();
                bootstrap.Modal.getInstance(document.getElementById('defectsFilterModal')).hide();
            });
            
            // Set up filter reset
            document.getElementById('resetDefectsFilter').addEventListener('click', function() {
                document.getElementById('defectStatusFilter').value = '';
                document.getElementById('defectPriorityFilter').value = '';
                document.getElementById('defectTitleFilter').value = '';
                document.getElementById('defectDateFromFilter').value = '';
                document.getElementById('defectDateToFilter').value = '';
                document.getElementById('defectContractorFilter').value = '';
                
                resetDefectsFilter();
            });
        }
    }

    function applyContractorsFilter() {
        const companyName = document.getElementById('companyNameFilter').value;
        const trade = document.getElementById('tradeFilter').value;
        const showOpen = document.getElementById('openDefectsFilter').checked;
        const showPending = document.getElementById('pendingDefectsFilter').checked;
        const showClosed = document.getElementById('closedDefectsFilter').checked;
        const showRejected = document.getElementById('rejectedDefectsFilter').checked;
        
        let visibleCount = 0;
        let totalCount = 0;
        
        // Apply filter to the table rows
        document.querySelectorAll('#contractorsTable tbody tr').forEach(row => {
            totalCount++;
            
            const nameCell = row.cells[0];
            const tradeCell = row.cells[1];
            const openCell = row.cells[3];
            const pendingCell = row.cells[4];
            const closedCell = row.cells[5];
            const rejectedCell = row.cells[6];
            
            const rowCompanyName = nameCell.textContent.trim();
            const rowTrade = tradeCell.textContent.trim();
            const hasOpenDefects = parseInt(openCell.textContent.trim()) > 0;
            const hasPendingDefects = parseInt(pendingCell.textContent.trim()) > 0;
            const hasClosedDefects = parseInt(closedCell.textContent.trim()) > 0;
            const hasRejectedDefects = parseInt(rejectedCell.textContent.trim()) > 0;
            
            // Check if row matches all filter criteria
            const matchesName = companyName === '' || rowCompanyName === companyName;
            const matchesTrade = trade === '' || rowTrade === trade;
            const matchesStatus = 
                (showOpen && hasOpenDefects) || 
                (showPending && hasPendingDefects) || 
                (showClosed && hasClosedDefects) ||
                (showRejected && hasRejectedDefects);
            
            const shouldShow = matchesName && matchesTrade && matchesStatus;
            
            // Show/hide the row
            row.style.display = shouldShow ? '' : 'none';
            
            if (shouldShow) {
                visibleCount++;
            }
        });
        
        showToast(`Showing ${visibleCount} of ${totalCount} contractors`, 'info');
    }

    function resetContractorsFilter() {
        // Show all rows in the contractors table
        document.querySelectorAll('#contractorsTable tbody tr').forEach(row => {
            row.style.display = '';
        });
        
        showToast('Filter reset successfully', 'success');
    }

    function applyDefectsFilter() {
        const status = document.getElementById('defectStatusFilter').value.toLowerCase();
        const priority = document.getElementById('defectPriorityFilter').value.toLowerCase();
        const titleSearch = document.getElementById('defectTitleFilter').value.toLowerCase();
        const dateFrom = document.getElementById('defectDateFromFilter').value;
        const dateTo = document.getElementById('defectDateToFilter').value;
        const contractor = document.getElementById('defectContractorFilter').value;
        
        let visibleCount = 0;
        let totalCount = 0;
        let rowsProcessed = new Set(); // To track which rows we've processed
        
                    // Apply filter to the defects table rows
            document.querySelectorAll('#recentDefectsTable tbody tr.expandable-row').forEach(row => {
                totalCount++;
                
                const idCell = row.cells[0];
                const statusCell = row.cells[1];
                const priorityCell = row.cells[2];
                const titleCell = row.cells[3];
                const contractorCell = row.cells[4];
                const dateCell = row.cells[5];
                
                const rowId = idCell.textContent.trim();
                const rowStatus = statusCell.textContent.trim().toLowerCase();
                const rowPriority = priorityCell.textContent.trim().toLowerCase();
                const rowTitle = titleCell.textContent.trim().toLowerCase();
                const rowContractor = contractorCell.textContent.trim();
                const rowDate = dateCell.textContent.trim();
                
                // Convert date format for comparison (from dd/mm/yyyy to yyyy-mm-dd)
                let rowDateParts;
                let rowDateFormatted = '';
                if (rowDate.includes('/')) {
                    rowDateParts = rowDate.split(' ')[0].split('/');
                    rowDateFormatted = `${rowDateParts[2]}-${rowDateParts[1]}-${rowDateParts[0]}`;
                } else {
                    rowDateFormatted = rowDate; // Already in correct format
                }
                
                // Check if row matches all filter criteria
                const matchesStatus = status === '' || rowStatus.includes(status);
                const matchesPriority = priority === '' || rowPriority.includes(priority);
                const matchesTitle = titleSearch === '' || rowTitle.includes(titleSearch);
                const matchesContractor = contractor === '' || rowContractor === contractor;
                
                // Date range checks
                let matchesDateRange = true;
                if (dateFrom && rowDateFormatted < dateFrom) {
                    matchesDateRange = false;
                }
                if (dateTo && rowDateFormatted > dateTo) {
                    matchesDateRange = false;
                }
                
                const shouldShow = matchesStatus && matchesPriority && matchesTitle && 
                                  matchesContractor && matchesDateRange;
                
                // Show/hide the row and its details row
                row.style.display = shouldShow ? '' : 'none';
                const detailsRow = document.getElementById('details-' + row.dataset.defectId);
                if (detailsRow) {
                    detailsRow.style.display = shouldShow ? '' : 'none';
                }
                
                if (shouldShow) {
                    visibleCount++;
                }
                
                // Mark this row as processed
                rowsProcessed.add(rowId);
            });
            
            // Now handle the mobile view cards
            document.querySelectorAll('.d-md-none .data-card').forEach((card, index) => {
                if (index < totalCount) { // Only process cards that correspond to the table rows
                    const statusBadge = card.querySelector('.badge.bg-danger, .badge.bg-warning, .badge.bg-success, .badge.bg-secondary');
                    const priorityBadge = card.querySelector('.badge.bg-danger:not(:first-child), .badge.bg-warning:not(:first-child), .badge.bg-success:not(:first-child)');
                    const titleElement = card.querySelector('.data-card-title');
                    const contractorElement = card.querySelector('.data-card-value');
                    const dateElement = card.querySelector('.text-muted');
                    
                    if (statusBadge && priorityBadge && titleElement && contractorElement && dateElement) {
                        const cardStatus = statusBadge.textContent.trim().toLowerCase();
                        const cardPriority = priorityBadge.textContent.trim().toLowerCase();
                        const cardTitle = titleElement.textContent.trim().toLowerCase();
                        const cardContractor = contractorElement.textContent.trim();
                        const cardDateText = dateElement.textContent.trim();
                        
                        // Extract date from something like "Created: 17/03/2025 12:29"
                        let cardDate = '';
                        const dateParts = cardDateText.match(/\d{2}\/\d{2}\/\d{4}/);
                        if (dateParts && dateParts[0]) {
                            const parts = dateParts[0].split('/');
                            cardDate = `${parts[2]}-${parts[1]}-${parts[0]}`;
                        }
                        
                        // Check if card matches all filter criteria
                        const matchesStatus = status === '' || cardStatus.includes(status);
                        const matchesPriority = priority === '' || cardPriority.includes(priority);
                        const matchesTitle = titleSearch === '' || cardTitle.includes(titleSearch);
                        const matchesContractor = contractor === '' || cardContractor === contractor;
                        
                        // Date range checks
                        let matchesDateRange = true;
                        if (dateFrom && cardDate && cardDate < dateFrom) {
                            matchesDateRange = false;
                        }
                        if (dateTo && cardDate && cardDate > dateTo) {
                            matchesDateRange = false;
                        }
                        
                        const shouldShow = matchesStatus && matchesPriority && matchesTitle && 
                                          matchesContractor && matchesDateRange;
                        
                        // Show/hide the card
                        card.style.display = shouldShow ? 'block' : 'none';
                    }
                }
            });
            
            showToast(`Showing ${visibleCount} of ${totalCount} defects`, 'info');
        }

        function resetDefectsFilter() {
            // Show all rows in the defects table
            document.querySelectorAll('#recentDefectsTable tbody tr').forEach(row => {
                row.style.display = '';
            });
            
            // Show all mobile cards
            document.querySelectorAll('.d-md-none .data-card').forEach(card => {
                card.style.display = 'block';
            });
            
            showToast('Filter reset successfully', 'success');
        }

        function initializeExportButtons() {
            // Simple implementation of export buttons
            document.getElementById('exportContractors').addEventListener('click', function() {
                exportTableToCSV('contractorsTable', 'contractors_data.csv');
            });
        }

        function exportTableToCSV(tableId, filename) {
            const table = document.getElementById(tableId);
            if (!table) return;
            
            const rows = table.querySelectorAll('tr');
            let csv = [];
            
            for (let i = 0; i < rows.length; i++) {
                const row = [], cols = rows[i].querySelectorAll('td, th');
                
                for (let j = 0; j < cols.length; j++) {
                    // Get text content and clean it
                    let text = cols[j].textContent.trim();
                    text = text.replace(/"/g, '""'); // Escape double quotes
                    row.push('"' + text + '"');
                }
                
                csv.push(row.join(','));
            }
            
            const csvContent = 'data:text/csv;charset=utf-8,' + csv.join('\n');
            
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement('a');
            link.setAttribute('href', encodedUri);
            link.setAttribute('download', filename);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            showToast('Table data exported successfully!', 'success');
        }

        function showToast(message, type = 'info') {
            // Create toast element
            const toastElement = document.createElement('div');
            toastElement.className = `toast custom-toast bg-${type === 'info' ? 'info' : type === 'success' ? 'success' : type === 'danger' ? 'danger' : 'warning'} text-white`;
            toastElement.setAttribute('role', 'alert');
            toastElement.setAttribute('aria-live', 'assertive');
            toastElement.setAttribute('aria-atomic', 'true');
            
            // Set toast content
            toastElement.innerHTML = `
                <div class="toast-header bg-${type === 'info' ? 'info' : type === 'success' ? 'success' : type === 'danger' ? 'danger' : 'warning'} text-white">
                    <i class="fas fa-${type === 'info' ? 'info-circle' : type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-circle' : 'exclamation-triangle'} me-2"></i>
                    <strong class="me-auto">Notification</strong>
                    <small>${new Date().toLocaleTimeString()}</small>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
                <div class="toast-body">
                    ${message}
                </div>
            `;
            
            // Add to container
            const toastContainer = document.querySelector('.toast-container');
            toastContainer.appendChild(toastElement);
            
            // Initialize and show toast
            const toast = new bootstrap.Toast(toastElement, { autohide: true, delay: 3000 });
            toast.show();
            
            // Remove from DOM after hiding
            toastElement.addEventListener('hidden.bs.toast', function () {
                toastContainer.removeChild(toastElement);
            });
        }

        // System information
        const systemInfo = {
            currentTime: '2025-03-17 15:21:09', // Current UTC time as specified
            currentUser: 'irlam', // Current user's login as specified
            displaySystemInfo: function() {
                console.log(`Dashboard loaded by ${this.currentUser} at ${this.currentTime}`);
            }
        };

        // Initialize system info
        systemInfo.displaySystemInfo();
    </script>
</body>
</html>
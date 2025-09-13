<?php
/**
 * dashboard.php - Enhanced Responsive Version
 * Current Date and Time (UTC): 2025-03-18 20:58:28
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
    $error_message = '';

    // Get overall statistics
    $statsQuery = "SELECT 
    (SELECT COUNT(*) FROM contractors WHERE status = 'active') as active_contractors,
    (SELECT COUNT(*) FROM defects WHERE status = 'open' AND deleted_at IS NULL) as open_defects,
    (SELECT COUNT(*) FROM defects WHERE deleted_at IS NULL) as total_defects,
    (SELECT COUNT(*) FROM defects WHERE status = 'pending' AND deleted_at IS NULL) as pending_defects";
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
    SUM(CASE WHEN d.id IS NOT NULL AND d.deleted_at IS NULL THEN 1 ELSE 0 END) as total_defects,
    SUM(CASE WHEN d.status = 'open' AND d.deleted_at IS NULL THEN 1 ELSE 0 END) as open_defects,
    SUM(CASE WHEN d.status = 'pending' AND d.deleted_at IS NULL THEN 1 ELSE 0 END) as pending_defects,
    SUM(CASE WHEN d.status = 'accepted' AND d.deleted_at IS NULL THEN 1 ELSE 0 END) as closed_defects,
    SUM(CASE WHEN d.status = 'rejected' AND d.deleted_at IS NULL THEN 1 ELSE 0 END) as rejected_defects,
    IFNULL(MAX(CASE WHEN d.deleted_at IS NULL THEN d.updated_at ELSE NULL END), 'N/A') as last_update
FROM contractors c
LEFT JOIN defects d ON c.id = d.assigned_to
WHERE c.status = 'active'
GROUP BY c.id, c.company_name, c.status, c.logo, c.trade
ORDER BY total_defects DESC, company_name ASC";
    $contractorsStmt = $db->prepare($contractorsQuery);
    $contractorsStmt->execute();
    $contractorStats = $contractorsStmt->fetchAll(PDO::FETCH_ASSOC);
	
	// Add this debugging code to verify counts
$debugQuery = "SELECT COUNT(*) as total_open FROM defects 
               WHERE status = 'open' 
               AND deleted_at IS NULL";
$debugStmt = $db->prepare($debugQuery);
$debugStmt->execute();
$actualOpenCount = $debugStmt->fetchColumn();

// Log or display the debug information
error_log("Actual open defects: " . $actualOpenCount);

// Optionally display on page during development:
echo " Actual open defects: " . $actualOpenCount . " ";

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
        u.username as reported_by,
        GROUP_CONCAT(DISTINCT di.file_path) as image_paths
    FROM defects d
    LEFT JOIN contractors c ON d.assigned_to = c.id
    LEFT JOIN users u ON d.created_by = u.id
    LEFT JOIN defect_images di ON d.id = di.defect_id
    WHERE d.deleted_at IS NULL
    GROUP BY d.id, d.title, d.description, d.status, d.priority, d.created_at, d.updated_at, 
            c.company_name, c.trade, c.logo, u.username
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

// Helper function to correct defect image paths
function correctDefectImagePath($path) {
    // Paths stored like this: "uploads/defects/104/img_67a4dedb9c7d4_17388581853507281592734635484040.jpg"
    if (strpos($path, 'uploads/defects/') === 0) {
        return BASE_URL . $path;
    } else {
        return BASE_URL . 'uploads/defects/' . $path; 
    }
}

// Helper function to correct contractor logo paths
function correctContractorLogoPath($path) {
    // Assuming BASE_URL is defined in your config
    return BASE_URL . 'uploads/logos/' . $path;
}

// Initialize navbar
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
			        /* Defect Image Gallery */
        .defect-image-gallery {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
            margin-bottom: 15px;
        }

        .defect-image-container {
            width: 120px;
            height: 120px;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            position: relative;
        }

        .defect-thumbnail {
            width: 100%;
            height: 100%;
            object-fit: cover;
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        /* Image Modal for fullscreen view */
        .image-modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.9);
            overflow: auto;
            align-items: center;
            justify-content: center;
        }

        .modal-image-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            width: 100%;
            position: relative;
        }

        .modal-full-image {
            max-width: 90%;
            max-height: 80vh;
            object-fit: contain;
            margin: auto;
        }

        .close-image-modal {
            position: absolute;
            top: 20px;
            right: 20px;
            color: white;
            font-size: 30px;
            font-weight: bold;
            cursor: pointer;
            z-index: 2001;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: rgba(0,0,0,0.5);
            border-radius: 50%;
        }

        /* Mobile optimizations */
        @media (max-width: 767.98px) {
            .defect-image-container {
                width: 100px;
                height: 100px;
            }
            
            .modal-full-image {
                max-width: 95%;
                max-height: 85vh;
            }
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
<br><br>
    <div class="main-content">
        <div class="container-fluid">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center flex-wrap mb-4">
                <h2 class="mb-0 text-dark fw-bold">
                    <i class="fas fa-tachometer-alt me-2 text-primary"></i> Defects Dashboard
                </h2>
                
                <a href="create_defect.php" class="btn btn-create-defect">
                    <i class="fas fa-plus-circle me-1"></i> Create Defect
                </a>
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
                                <h6 class="card-title">Total Defects Created</h6>
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
                        <table id="contractorsTable" class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Company</th>
                                    <th>Total</th>
                                    <th>Open</th>
                                    <th>Pending</th>
                                    <th>Closed</th>
                                    <th>Rejected</th>
                                    <th>Last Update</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($contractorStats)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-3 text-muted">No contractor data available</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($contractorStats as $contractor): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php if (!empty($contractor['logo'])): ?>
                                                    <img src="<?php echo correctContractorLogoPath(htmlspecialchars($contractor['logo'])); ?>" 
                                                         class="company-logo me-2" alt="Logo">
                                                <?php else: ?>
                                                    <div class="company-icon me-2">
                                                        <i class="fas fa-building"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <?php echo htmlspecialchars($contractor['company_name']); ?>
                                            </div>
                                        </td>
                                        <td><?php echo intval($contractor['total_defects']); ?></td>
                                        <td>
                                            <span class="badge bg-danger">
                                                <?php echo intval($contractor['open_defects']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-warning">
                                                <?php echo intval($contractor['pending_defects']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-success">
                                                <?php echo intval($contractor['closed_defects']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?php echo intval($contractor['rejected_defects']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo $contractor['last_update'] !== 'N/A' ? 
                                                formatUKDateTime($contractor['last_update']) : 'N/A'; ?>
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
                                            <img src="<?php echo correctContractorLogoPath(htmlspecialchars($contractor['logo'])); ?>" 
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
                                                    <img src="<?php echo correctContractorLogoPath(htmlspecialchars($defect['logo'])); ?>" 
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
                                                        
                                                        <!-- Image Gallery -->
                                                        <?php if (!empty($defect['image_paths'])): ?>
                                                            <h6 class="mb-2 mt-3">Attachments:</h6>
                                                            <div class="defect-image-gallery">
                                                                <?php 
                                                                $image_paths = explode(',', $defect['image_paths']);
                                                                foreach ($image_paths as $image_path): 
                                                                    if (!empty(trim($image_path))):
                                                                ?>
                                                                    <div class="defect-image-container">
                                                                        <img src="<?php echo correctDefectImagePath(trim($image_path)); ?>" 
                                                                             class="defect-thumbnail zoomable-image" 
                                                                             alt="Defect Image"
                                                                             data-full-image="<?php echo correctDefectImagePath(trim($image_path)); ?>">
                                                                    </div>
                                                                <?php 
                                                                    endif;
                                                                endforeach; 
                                                                ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="mb-3">
                                                            <h6 class="mb-1">Reported By:</h6>
                                                            <p class="mb-3"><?php echo htmlspecialchars($defect['reported_by']); ?></p>
                                                            
                                                            <h6 class="mb-1">Last Updated:</h6>
                                                            <p class="mb-3"><?php echo formatUKDateTime($defect['updated_at']); ?></p>
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
                            <div class="data-card" data-defect-id="<?php echo $defect['id']; ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="d-flex gap-2">
                                            <span class="badge bg-<?php echo getStatusBadgeClass($defect['status']); ?>">
                                                <?php echo ucfirst(htmlspecialchars($defect['status'])); ?>
                                            </span>
                                            <span class="badge bg-<?php echo getPriorityBadgeClass($defect['priority']); ?>">
                                                <?php echo ucfirst(htmlspecialchars($defect['priority'])); ?>
                                            </span>
                                        </div>
                                        <h5 class="data-card-title mt-2">
                                            <?php echo htmlspecialchars($defect['title']); ?>
                                        </h5>
                                    </div>
                                    <div class="text-end">
                                        <div class="text-muted fs-6">#<?php echo $defect['id']; ?></div>
                                        <div class="mt-1 text-muted" style="font-size: 0.8rem;">
                                            <?php echo getTimeAgo($defect['created_at']); ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mt-3 mb-3">
                                    <div class="d-flex align-items-center">
                                        <?php if (!empty($defect['logo'])): ?>
                                            <img src="<?php echo correctContractorLogoPath(htmlspecialchars($defect['logo'])); ?>" 
                                                 class="company-logo me-2" alt="Logo">
                                        <?php else: ?>
                                            <div class="company-icon me-2">
                                                <i class="fas fa-building"></i>
                                            </div>
                                        <?php endif; ?>
                                        <span><?php echo htmlspecialchars($defect['company_name'] ?? 'Unassigned'); ?></span>
                                    </div>
                                </div>
                                
                                <div class="mobile-details-container">
                                    <div class="mb-3">
                                        <div class="data-card-label">Description:</div>
                                        <p class="data-card-value"><?php echo nl2br(htmlspecialchars(substr($defect['description'], 0, 100) . (strlen($defect['description']) > 100 ? '...' : ''))); ?></p>
                                    </div>

                                    <?php if (!empty($defect['image_paths'])): ?>
                                        <div class="data-card-label">Attachments:</div>
                                        <div class="defect-image-gallery">
                                            <?php 
                                            $image_paths = explode(',', $defect['image_paths']);
                                            $count = 0;
                                            foreach ($image_paths as $image_path): 
                                                if (!empty(trim($image_path)) && $count < 3):
                                                    $count++;
                                            ?>
                                                <div class="defect-image-container">
                                                    <img src="<?php echo correctDefectImagePath(trim($image_path)); ?>" 
                                                         class="defect-thumbnail zoomable-image" 
                                                         alt="Defect Image"
                                                         data-full-image="<?php echo correctDefectImagePath(trim($image_path)); ?>">
                                                </div>
                                            <?php 
                                                endif;
                                            endforeach; 
                                            
                                            if (count($image_paths) > 3): 
                                            ?>
                                                <div class="defect-image-container d-flex align-items-center justify-content-center bg-light">
                                                    <div class="text-center">
                                                        <span class="badge bg-primary">+<?php echo count($image_paths) - 3; ?> more</span>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="mt-3">
                                        <a href="view_defect.php?id=<?php echo $defect['id']; ?>" class="btn btn-sm btn-primary w-100">
                                            View Full Details
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Image Modal -->
    <div id="imageModal" class="image-modal" onclick="this.style.display='none';">
        <span class="close-image-modal">&times;</span>
        <div class="modal-image-content">
            <img id="modalImage" class="modal-full-image" src="" alt="Full size image">
        </div>
    </div>
    
    <!-- Toast Notification Container -->
    <div class="toast-container"></div>

    <!-- Essential JavaScript Dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.4/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    $(document).ready(function() {
        // Toggle defect details
        $('.toggle-details').on('click', function(e) {
            e.preventDefault();
            const defectId = $(this).closest('tr').data('defect-id');
            const detailsRow = $(`#details-${defectId}`);
            const icon = $(this).find('i');
            
            detailsRow.find('.row-details').slideToggle();
            icon.toggleClass('fa-chevron-down fa-chevron-up');
        });
        
        // Image viewer
        $('.zoomable-image').on('click', function() {
            const fullImageSrc = $(this).data('full-image');
            $('#modalImage').attr('src', fullImageSrc);
            $('#imageModal').css('display', 'flex');
        });
        
        // Close modal when clicking the close button
        $('.close-image-modal').on('click', function(e) {
            e.stopPropagation();
            $('#imageModal').css('display', 'none');
        });
    });
    </script>
	<!-- Filter Modals -->
<!-- Contractor Filter Modal -->
<div class="modal fade" id="contractorFilterModal" tabindex="-1" aria-labelledby="contractorFilterModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="contractorFilterModalLabel">Filter Contractors</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="contractorFilterForm">
                    <div class="mb-3">
                        <label for="filterContractorName" class="form-label">Company Name</label>
                        <input type="text" class="form-control" id="filterContractorName" placeholder="Enter company name">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Defect Status</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="filterHasOpenDefects" checked>
                            <label class="form-check-label" for="filterHasOpenDefects">
                                Has Open Defects
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="filterHasPendingDefects" checked>
                            <label class="form-check-label" for="filterHasPendingDefects">
                                Has Pending Defects
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="resetContractorFilter">Reset Filters</button>
                <button type="button" class="btn btn-primary" id="applyContractorFilter">Apply Filters</button>
            </div>
        </div>
    </div>
</div>

<!-- Defects Filter Modal -->
<div class="modal fade" id="defectsFilterModal" tabindex="-1" aria-labelledby="defectsFilterModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="defectsFilterModalLabel">Filter Defects</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="defectsFilterForm">
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <div class="d-flex flex-wrap gap-2">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" id="filterStatusOpen" value="open" checked>
                                <label class="form-check-label" for="filterStatusOpen">Open</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" id="filterStatusPending" value="pending" checked>
                                <label class="form-check-label" for="filterStatusPending">Pending</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" id="filterStatusAccepted" value="accepted" checked>
                                <label class="form-check-label" for="filterStatusAccepted">Accepted</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" id="filterStatusRejected" value="rejected" checked>
                                <label class="form-check-label" for="filterStatusRejected">Rejected</label>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Priority</label>
                        <div class="d-flex flex-wrap gap-2">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" id="filterPriorityHigh" value="high" checked>
                                <label class="form-check-label" for="filterPriorityHigh">High</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" id="filterPriorityMedium" value="medium" checked>
                                <label class="form-check-label" for="filterPriorityMedium">Medium</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" id="filterPriorityLow" value="low" checked>
                                <label class="form-check-label" for="filterPriorityLow">Low</label>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="filterDefectContractor" class="form-label">Contractor</label>
                        <select class="form-select" id="filterDefectContractor">
                            <option value="">All Contractors</option>
                            <!-- Will be populated from contractorData -->
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="filterDefectTitle" class="form-label">Title Search</label>
                        <input type="text" class="form-control" id="filterDefectTitle" placeholder="Search in defect titles">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="resetDefectsFilter">Reset Filters</button>
                <button type="button" class="btn btn-primary" id="applyDefectsFilter">Apply Filters</button>
            </div>
        </div>
    </div>
</div>

<!-- Add the JavaScript for filters to the end of your file, before the closing </body> tag -->
<script>
$(document).ready(function() {
    // Initialize toast notification function
    function showToast(message, type = 'info') {
        const toastId = 'toast-' + Date.now();
        const toast = `
            <div id="${toastId}" class="toast custom-toast" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="true" data-bs-delay="3000">
                <div class="toast-header">
                    <strong class="me-auto text-${type}">${type === 'info' ? 'Information' : (type === 'success' ? 'Success' : 'Error')}</strong>
                    <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
                <div class="toast-body">${message}</div>
            </div>
        `;
        $('.toast-container').append(toast);
        const toastElement = new bootstrap.Toast(document.getElementById(toastId));
        toastElement.show();
    }

    
    // Populate contractor options for defect filter
    contractorData.forEach(contractor => {
        $('#filterDefectContractor').append(`<option value="${contractor.id}">${contractor.name}</option>`);
    });
    
    // Filter button click handlers
    $('#filterContractors').click(function() {
        $('#contractorFilterModal').modal('show');
    });
    
    $('#filterDefects').click(function() {
        $('#defectsFilterModal').modal('show');
    });

    // Reset contractor filters
    $('#resetContractorFilter').click(function() {
        $('#filterContractorName').val('');
        $('#filterContractorTrade').val('');
        $('#filterHasOpenDefects').prop('checked', true);
        $('#filterHasPendingDefects').prop('checked', true);
    });

    // Reset defect filters
    $('#resetDefectsFilter').click(function() {
        $('#filterDefectTitle').val('');
        $('#filterDefectContractor').val('');
        $('#filterStatusOpen, #filterStatusPending, #filterStatusAccepted, #filterStatusRejected').prop('checked', true);
        $('#filterPriorityHigh, #filterPriorityMedium, #filterPriorityLow').prop('checked', true);
    });
    
    // Apply contractor filters
    $('#applyContractorFilter').click(function() {
        const nameFilter = $('#filterContractorName').val().toLowerCase();
        const hasOpenDefects = $('#filterHasOpenDefects').is(':checked');
        const hasPendingDefects = $('#filterHasPendingDefects').is(':checked');
        let visibleCount = 0;
        
        // Desktop view filtering
        $('#contractorsTable tbody tr').each(function() {
            const $row = $(this);
            const companyName = $row.find('td:nth-child(1)').text().toLowerCase();
            const trade = $row.find('td:nth-child(2)').text();
            const openDefects = parseInt($row.find('td:nth-child(4) .badge').text().trim()) > 0;
            const pendingDefects = parseInt($row.find('td:nth-child(5) .badge').text().trim()) > 0;
            
            const nameMatch = nameFilter === '' || companyName.includes(nameFilter);
            const statusMatch = (!hasOpenDefects || openDefects) && (!hasPendingDefects || pendingDefects);
            
            if (nameMatch && tradeMatch && statusMatch) {
                $row.show();
                visibleCount++;
            } else {
                $row.hide();
            }
        });
        
        // Mobile view filtering
        $('.d-md-none .data-card').each(function() {
            const $card = $(this);
            const companyName = $card.find('.data-card-title').text().toLowerCase();
            const trade = $card.find('.badge.bg-light').text();
            const openDefects = parseInt($card.find('.col-3:nth-child(1) .badge').text().trim()) > 0;
            const pendingDefects = parseInt($card.find('.col-3:nth-child(2) .badge').text().trim()) > 0;
            
            const nameMatch = nameFilter === '' || companyName.includes(nameFilter);
            const tradeMatch = tradeFilter === '' || trade === tradeFilter;
            const statusMatch = (!hasOpenDefects || openDefects) && (!hasPendingDefects || pendingDefects);
            
            if (nameMatch && tradeMatch && statusMatch) {
                $card.show();
            } else {
                $card.hide();
            }
        });
        
        $('#contractorFilterModal').modal('hide');
        showToast(`Filters applied: ${visibleCount} contractors shown`, 'success');
    });

    // Apply defect filters
    $('#applyDefectsFilter').click(function() {
        // Get filter values
        const titleFilter = $('#filterDefectTitle').val().toLowerCase();
        const contractorFilter = $('#filterDefectContractor').val();
        
        // Get selected statuses
        const selectedStatuses = [];
        if ($('#filterStatusOpen').is(':checked')) selectedStatuses.push('open');
        if ($('#filterStatusPending').is(':checked')) selectedStatuses.push('pending');
        if ($('#filterStatusAccepted').is(':checked')) selectedStatuses.push('accepted');
        if ($('#filterStatusRejected').is(':checked')) selectedStatuses.push('rejected');
        
        // Get selected priorities
        const selectedPriorities = [];
        if ($('#filterPriorityHigh').is(':checked')) selectedPriorities.push('high');
        if ($('#filterPriorityMedium').is(':checked')) selectedPriorities.push('medium');
        if ($('#filterPriorityLow').is(':checked')) selectedPriorities.push('low');
        
        let visibleCount = 0;
        
        // Filter desktop view
        $('#recentDefectsTable tbody tr.expandable-row').each(function() {
            const $row = $(this);
            const defectId = $row.data('defect-id');
            const title = $row.find('td:nth-child(4)').text().toLowerCase();
            const status = $row.find('td:nth-child(2) .badge').text().toLowerCase();
            const priority = $row.find('td:nth-child(3) .badge').text().toLowerCase();
            const contractor = $row.find('td:nth-child(5)').text().trim();
            const detailsRow = $('#details-' + defectId);
            
            const titleMatch = titleFilter === '' || title.includes(titleFilter);
            const statusMatch = selectedStatuses.length === 0 || selectedStatuses.includes(status);
            const priorityMatch = selectedPriorities.length === 0 || selectedPriorities.includes(priority);
            const contractorMatch = contractorFilter === '' || contractor.includes(contractorFilter);
            
            if (titleMatch && statusMatch && priorityMatch && contractorMatch) {
                $row.show();
                visibleCount++;
            } else {
                $row.hide();
                detailsRow.hide();
            }
        });
        
        // Filter mobile view
        $('.d-md-none .data-card[data-defect-id]').each(function() {
            const $card = $(this);
            const title = $card.find('.data-card-title').text().toLowerCase();
            const status = $card.find('.badge').first().text().toLowerCase();
            const priority = $card.find('.badge').eq(1).text().toLowerCase();
            const contractor = $card.find('.d-flex > span').text().trim();
            
            const titleMatch = titleFilter === '' || title.includes(titleFilter);
            const statusMatch = selectedStatuses.length === 0 || selectedStatuses.includes(status);
            const priorityMatch = selectedPriorities.length === 0 || selectedPriorities.includes(priority);
            const contractorMatch = contractorFilter === '' || contractor.includes(contractorFilter);
            
            if (titleMatch && statusMatch && priorityMatch && contractorMatch) {
                $card.show();
            } else {
                $card.hide();
            }
        });
        
        $('#defectsFilterModal').modal('hide');
        showToast(`Filters applied: ${visibleCount} defects shown`, 'success');
    });

    // Export contractors to CSV
    $('#exportContractors').click(function() {
        let csvContent = "Company,Total Defects,Open,Pending,Closed,Rejected,Last Update\n";
        
        // Get visible rows only
        $('#contractorsTable tbody tr:visible').each(function() {
            const $row = $(this);
            const cells = $row.find('td');
            if (cells.length) {
                const company = $(cells[0]).text().trim().replace(/,/g, ' ');
                const trade = $(cells[1]).text().trim().replace(/,/g, ' ');
                const total = $(cells[2]).text().trim();
                const open = $(cells[3]).text().trim();
                const pending = $(cells[4]).text().trim();
                const closed = $(cells[5]).text().trim();
                const rejected = $(cells[6]).text().trim();
                const lastUpdate = $(cells[7]).text().trim().replace(/,/g, ' ');
                
                csvContent += `${company},${total},${open},${pending},${closed},${rejected},${lastUpdate}\n`;
            }
        });
        
        const encodedUri = encodeURI("data:text/csv;charset=utf-8," + csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "contractor_stats_" + new Date().toISOString().slice(0,10) + ".csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        showToast("Contractor data exported successfully", "success");
    });
    
    // Refresh buttons functionality
    $('#refreshContractors').click(function() {
        const $button = $(this);
        const originalHtml = $button.html();
        $button.html('<span class="refresh-indicator"></span>');
        
        setTimeout(function() {
            window.location.reload();
        }, 500);
    });
    
    $('#refreshDefects').click(function() {
        const $button = $(this);
        const originalHtml = $button.html();
        $button.html('<span class="refresh-indicator"></span>');
        
        setTimeout(function() {
            window.location.reload();
        }, 500);
    });
});
</script>
</body>
</html>
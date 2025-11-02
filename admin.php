<?php
// admin.php
// Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): 2025-02-27 12:09:29
// Current User's Login: irlam

// Error reporting and logging setup
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect to login if not logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Include database configuration
$config = [
    'db_host' => '10.35.233.124:3306',
    'db_name' => 'k87747_defecttracker',
    'db_user' => 'k87747_defecttracker',
    'db_pass' => 'Subaru5554346'
];

try {
    $db = new PDO(
        "mysql:host={$config['db_host']};dbname={$config['db_name']}",
        $config['db_user'],
        $config['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    error_log("Database Connection Error: " . $e->getMessage());
    die("Connection failed: Database error");
}

// Get user role information
$user_id = $_SESSION['user_id'] ?? 0;
$username = $_SESSION['username'] ?? '';
$full_name = $_SESSION['full_name'] ?? '';

// Query to get user roles from user_roles table
$stmt = $db->prepare("SELECT role_id FROM user_roles WHERE user_id = :user_id AND deleted_at IS NULL");
$stmt->execute(['user_id' => $user_id]);
$user_roles = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Define role names and descriptions
$role_definitions = [
    1 => ['name' => 'Administrator', 'description' => 'Full system access with all administrative capabilities'],
    2 => ['name' => 'Manager', 'description' => 'Project management and oversight capabilities'],
    3 => ['name' => 'Contractor', 'description' => 'Contractor access for defect updates and responses'],
    4 => ['name' => 'Viewer', 'description' => 'Read-only access to view defects and reports'],
    5 => ['name' => 'Client', 'description' => 'Client access to view and comment on defects']
];

$systemTools = [
    [
        'title' => 'System Health Scan',
        'description' => 'Run environment diagnostics covering PHP, extensions, and disk usage.',
        'path' => 'system-tools/system_health.php',
        'icon' => 'bx-pulse',
        'badge' => 'health'
    ],
    [
        'title' => 'Database Check',
        'description' => 'Validate schema connectivity and run quick integrity checks.',
        'path' => 'system-tools/check_database.php',
        'icon' => 'bx-data',
        'badge' => 'database'
    ],
    [
        'title' => 'Database Optimizer',
        'description' => 'Analyze and optimize key tables to maintain performance.',
        'path' => 'system-tools/database_optimizer.php',
        'icon' => 'bx-trending-up',
        'badge' => 'performance'
    ],
    [
        'title' => 'GD & ImageMagick',
        'description' => 'Confirm server image libraries are available for uploads.',
        'path' => 'system-tools/check_gd.php',
        'icon' => 'bx-image-alt',
        'badge' => 'media'
    ],
    [
        'title' => 'File Structure Map',
        'description' => 'Browse the application directory structure for auditing.',
        'path' => 'system-tools/show_file_structure.php',
        'icon' => 'bx-network-chart',
        'badge' => 'insight'
    ],
    [
        'title' => 'Functional Test Suite',
        'description' => 'Execute scripted smoke tests to validate core workflows.',
        'path' => 'system-tools/functional_tests.php',
        'icon' => 'bx-check-shield',
        'badge' => 'tests'
    ],
    [
        'title' => 'System Analysis Report',
        'description' => 'Generate comprehensive environment and configuration report.',
        'path' => 'system-tools/system_analysis_report.php',
        'icon' => 'bx-file-find',
        'badge' => 'report'
    ],
    [
        'title' => 'Password Utility',
        'description' => 'Create hashed passwords for new administrator accounts.',
        'path' => 'system-tools/hashed-password.php',
        'icon' => 'bx-lock-alt',
        'badge' => 'security'
    ]
];

if (!defined('APP_THEME_LOADED')) {
    define('APP_THEME_LOADED', true);
}

// Function to check if user has a specific role
function hasRole($role_id, $user_roles) {
    return in_array($role_id, $user_roles);
}

// Check if user has admin access
$isAdmin = hasRole(1, $user_roles);
$isManager = hasRole(2, $user_roles);

// If user is not admin or manager, redirect to dashboard
if (!$isAdmin && !$isManager) {
    header("Location: dashboard.php");
    exit();
}

// Current time for display
$current_time = date('d-m-Y H:i:s');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - McGoff Construction Defect Tracker</title>
    
    <!-- Link to external CSS files -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="css/app.css" rel="stylesheet">
    
</head>
<body data-bs-theme="dark">
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">McGoff - Construction Defect Tracker</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <span class="nav-link">
                            <i class='bx bx-time'></i> <?php echo $current_time; ?>
                        </span>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" 
                           data-bs-toggle="dropdown" aria-expanded="false">
                            <i class='bx bx-user-circle'></i> <?php echo htmlspecialchars($username); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="profile.php"><i class='bx bx-user'></i> Profile</a></li>
                            <li><a class="dropdown-item" href="settings.php"><i class='bx bx-cog'></i> Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class='bx bx-log-out'></i> Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-lg-3 col-xl-2 sidebar pt-3 d-none d-lg-block">
                <div class="d-flex align-items-center mb-3 px-3">
                    <span class="status-indicator status-active"></span>
                    <span>
                        <?php echo htmlspecialchars($full_name ?: $username); ?><br>
                        <small class="text-muted">
                            <?php
                            $role_names = [];
                            foreach ($user_roles as $role) {
                                if (isset($role_definitions[$role])) {
                                    $role_names[] = $role_definitions[$role]['name'];
                                }
                            }
                            echo implode(', ', $role_names);
                            ?>
                        </small>
                    </span>
                </div>
                <hr class="mb-3">
                
                <nav>
					<!-- navbar link to admin dashboard -->
                    <a href="admin.php" class="sidebar-link active">
                        <i class='bx bx-grid-alt'></i> Admin Dashboard
                    </a>
					<br>
					<!-- navbar link to overview dashboard -->
					<a href=dashboard.php class="sidebar-link active">
                        <i class='bx bx-grid-alt'></i> Overview Dashboard
                    </a>
																	  
                </nav>
            </div>
            
            <!-- Mobile Sidebar Toggle -->
            <div class="col-12 d-lg-none py-3 px-4">
                <button class="btn btn-dark w-100" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar">
                    <i class='bx bx-menu'></i> Menu
                </button>
            </div>

            <!-- Mobile Sidebar -->
            <div class="offcanvas offcanvas-start" tabindex="-1" id="mobileSidebar">
                <div class="offcanvas-header offcanvas-header-dark">
                    <h5 class="offcanvas-title">Admin Menu</h5>
                    <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                </div>
                <div class="offcanvas-body p-0">
                    <div class="list-group list-group-flush">
                        <a href="admin.php" class="list-group-item list-group-item-action active">
                            <i class='bx bx-grid-alt'></i> Dashboard
                        </a>
                        
                        <?php if ($isAdmin): ?>
                        <a href="user_management.php" class="list-group-item list-group-item-action">
                            <i class='bx bx-user'></i> User Management
                        </a>
                        <?php endif; ?>
                        
                        <a href="projects.php" class="list-group-item list-group-item-action">
                            <i class='bx bx-building-house'></i> Projects
                        </a>
                        
                        <a href="contractors.php" class="list-group-item list-group-item-action">
                            <i class='bx bx-hard-hat'></i> Contractors
                        </a>
                        
                        <a href="reports.php" class="list-group-item list-group-item-action">
                            <i class='bx bx-bar-chart-alt-2'></i> Reports
                        </a>

                        <a href="dashboard.php" class="list-group-item list-group-item-action">
                            <i class='bx bx-arrow-back'></i> Return to Main Dashboard
                        </a>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-lg-9 col-xl-10 content">
                <div class="welcome-banner">
                    <h1 class="h4 mb-0">Welcome to the Admin Dashboard, <?php echo htmlspecialchars($full_name ?: $username); ?>!</h1>
                    <p class="mb-0">Manage the defect tracking system from here.</p>
                </div>
				<!-- Cards row placement 3 rows max -->
				<div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-4 mb-4">
					<!-- User Management Card -->
                    <div class="col">
                        <div class="card admin-card h-100">
                            <div class="card-header">
                                <h5 class="card-title mb-0">User Management</h5>
                            </div>
                            <div class="card-body text-center">
                                <div class="card-icon text-primary">
                                    <i class='bx bx-user-circle'></i>
                                </div>
                                <h5 class="card-title">Manage Users</h5>
                                <p class="card-text">Add, edit, or remove users and manage their permissions in the system.</p>
                                <div class="d-grid gap-2">
                                    <a href="user_management.php" class="btn btn-sm btn-primary">
                                        <i class='bx bx-list-ul'></i> View Users
                                    </a>
                                    <a href="add_user.php" class="btn btn-sm btn-outline-primary">
                                        <i class='bx bx-plus'></i> Add New User
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Projects Card -->
                    <div class="col">
                        <div class="card admin-card h-100">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Project Management</h5>
                            </div>
                            <div class="card-body text-center">
                                <div class="card-icon text-success">
                                    <i class='bx bx-building-house'></i>
                                </div>
                                <h5 class="card-title">Manage Projects</h5>
                                <p class="card-text">Create and manage projects, track progress.<br></p>
                                <a href="projects.php" class="btn btn-sm btn-success">
                                    <i class='bx bx-building'></i> Project Management
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Contractors Card -->
                    <div class="col">
                        <div class="card admin-card h-100">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Contractor Management</h5>
                            </div>
                            <div class="card-body text-center">
                                <div class="card-icon text-warning">
                                    <i class='bx bx-hard-hat'></i>
                                </div>
                                <h5 class="card-title">Manage Contractors</h5>
                                <p class="card-text">Add and manage contractors with company details, contact information, and trade specialties.</p>
                                <div class="d-grid gap-2">
                                    <a href="contractors.php" class="btn btn-sm btn-warning">
                                        <i class='bx bx-list-ul'></i> View Contractors
                                    </a>
                                    <a href="add_contractor.php" class="btn btn-sm btn-outline-warning">
                                        <i class='bx bx-plus'></i> Add New Contractor
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
					<!--Floor Plan Management -->
                    <div class="col">
                        <div class="card admin-card h-100">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Floor Plan Management</h5>
                            </div>
                            <div class="card-body text-center">
                                <div class="card-icon text-warning">
                                    <i class='bx bx-hard-hat'></i>
                                </div>
                                <h5 class="card-title">Manage Floor Plans</h5>
                                <p class="card-text">Organize and manage construction floor plans across all projects. Upload and maintain PDF 
floor plans, track file integrity, and ensure project teams have access to the most 
up-to-date building layouts for accurate defect tracking.</p>
                                <div class="d-grid gap-2">
                                    <a href="floor_plans.php" class="btn btn-sm btn-warning">
                                        <i class='bx bx-list-ul'></i> View Floor Plans
                                    </a>
                                    <a href="upload_floor_plan.php" class="btn btn-sm btn-outline-warning">
                                        <i class='bx bx-plus'></i> Upload Floor Plans
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Reports Card -->
                    <div class="col">
                        <div class="card admin-card h-100">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Reports</h5>
                            </div>
                            <div class="card-body text-center">
                                <div class="card-icon text-info">
                                    <i class='bx bx-bar-chart-alt-2'></i>
                                </div>
                                <h5 class="card-title">System Reports</h5>
                                <p class="card-text">Access and generate reports for defects, project status, and contractor performance.</p>
                                <a href="reports.php" class="btn btn-sm btn-info">
                                    <i class='bx bx-line-chart'></i> View Reports
                                </a>
                            </div>
                        </div>
                    </div>
					<!-- Backups card -->
                    <div class="col">
                        <div class="card admin-card h-100">
                            <div class="card-header">
                                <h5 class="card-title mb-0">System Backups</h5>
                            </div>
                            <div class="card-body text-center">
                                <div class="card-icon text-info">
                                    <i class='bx bx-bar-chart-alt-2' animation='spin'></i>
									
                                </div>
                                <h5 class="card-title">System Backups</h5>
                                <p class="card-text">Access and generate FULL Site backups.</p>
                                <a href="/backups/index.php" class="btn btn-sm btn-info">
                                    <i class='bx bx-line-chart'></i> View backups
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sync settings Card -->
                    <div class="col">
                        <div class="card admin-card h-100">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Offline Sync Settings</h5>
                            </div>
                            <div class="card-body text-center">
                                <div class="card-icon text-secondary">
                                    <i class='bx bx-cog'></i>
                                </div>
                                <h5 class="card-title">Configure Settings</h5>
                                <p class="card-text">Manage offline sync configurations, for when there is no network.</p>
                                <a href="/sync/admin/dashboard.php" class="btn btn-sm btn-info">
                                    <i class='bx bx-slider-alt'></i> Sync Settings
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
				
				<!-- user logs Card -->
                    <div class="col">
                        <div class="card admin-card h-100">
                            <div class="card-header">
                                <h5 class="card-title mb-0">User Logs & activities</h5>
                            </div>
                            <div class="card-body text-center">
                                <div class="card-icon text-secondary">
                                    <i class='bx bx-cog'></i>
                                </div>
                                <h5 class="card-title">User Logs</h5>
                                <p class="card-text">The User Logs page provides a complete history of all user activities in the Defect Tracker system over the past 90 days. This page helps you track who did what and when within the system..</p>
                                <a href="user_logs.php" class="btn btn-sm btn-secondary">
                                    <i class='bx bx-slider-alt'></i> User Logs
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Quick Access</h5>
                            </div>
                            <div class="card-body">
                                <div class="row row-cols-1 row-cols-md-2 g-4">
                                    <div class="col">
                                        <div class="d-grid gap-2">
                                            <a href="dashboard.php" class="btn btn-outline-primary">
                                                <i class='bx bx-arrow-back'></i> Return to Main Dashboard
                                            </a>
                                            <a href="add_defect.php" class="btn btn-outline-danger">
                                                <i class='bx bx-error-circle'></i> Add New Defect
                                            </a>
                                        </div>
                                    </div>
                                    <div class="col">
                                        <div class="d-grid gap-2">
                                            <a href="add_project.php" class="btn btn-outline-success">
                                                <i class='bx bx-plus-circle'></i> Create New Project
                                            </a>
                                            <a href="add_contractor.php" class="btn btn-outline-warning">
                                                <i class='bx bx-hard-hat'></i> Add New Contractor
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($isAdmin): ?>
                <div class="card system-tools-card mt-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title mb-0">System Tools</h5>
                            <small class="text-muted">Administrative diagnostics and maintenance utilities</small>
                        </div>
                        <span class="badge bg-gradient-info text-uppercase">Admin</span>
                    </div>
                    <div class="card-body">
                        <div class="system-tools-grid">
                            <?php foreach ($systemTools as $tool): ?>
                                <article class="system-tool-card">
                                    <div class="system-tool-card__icon">
                                        <i class='bx <?php echo htmlspecialchars($tool['icon']); ?>'></i>
                                    </div>
                                    <div class="system-tool-card__body">
                                        <span class="system-tool-card__tag system-tool-card__tag--<?php echo htmlspecialchars($tool['badge']); ?>"><?php echo htmlspecialchars($tool['badge']); ?></span>
                                        <h6 class="system-tool-card__title"><?php echo htmlspecialchars($tool['title']); ?></h6>
                                        <p class="system-tool-card__description"><?php echo htmlspecialchars($tool['description']); ?></p>
                                        <a class="btn btn-sm btn-outline-light system-tool-card__action" href="<?php echo htmlspecialchars($tool['path']); ?>" target="_blank" rel="noopener">
                                            <i class='bx bx-caret-right-circle'></i>
                                            Open Tool
                                        </a>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer-dark py-3 mt-4">
        <div class="container-fluid">
            <div class="row">
                <div class="col text-center">
                    <p class="mb-0 text-muted">&copy; <?php echo date('Y'); ?> Construction Defect Tracker. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Include Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Auto-dismiss alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            var alertList = document.querySelectorAll('.alert-dismissible');
            alertList.forEach(function(alert) {
                setTimeout(function() {
                    var bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });
    </script>
</body>
</html>
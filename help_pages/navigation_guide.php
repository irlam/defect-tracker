<?php
/**
 * Navigation Guide
 * 
 * Comprehensive guide to the navigation structure and role-based access
 * in the McGoff Defect Tracker application.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/SessionManager.php';
require_once __DIR__ . '/../includes/navbar.php';

SessionManager::start();
if (!SessionManager::isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

// Create a database connection for navbar
$database = new Database();
$db = $database->getConnection();

$pageTitle = 'Navigation Guide';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - Defect Tracker</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/app.css">
    <style>
        body {
            padding-top: 76px;
        }
        .role-section {
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: var(--surface-color);
            border-radius: var(--border-radius);
            border-left: 4px solid var(--primary-color);
        }
        .role-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .role-icon {
            font-size: 2rem;
            color: var(--primary-color);
        }
        .nav-list {
            list-style: none;
            padding-left: 0;
        }
        .nav-list li {
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--border-color);
        }
        .nav-list li:last-child {
            border-bottom: none;
        }
        .dropdown-section {
            margin-left: 1.5rem;
            margin-top: 0.5rem;
        }
        .dropdown-section ul {
            list-style: none;
            padding-left: 1rem;
        }
        .dropdown-section li {
            padding: 0.25rem 0;
            border-bottom: none;
        }
        .dropdown-section li::before {
            content: '→';
            margin-right: 0.5rem;
            color: var(--secondary-color);
        }
        .badge-access {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
    </style>
</head>
<body class="tool-body" data-bs-theme="dark">
    <?php
    // Render navbar
    $navbar = new Navbar($db, $_SESSION['user_id'], $_SESSION['username']);
    $navbar->render();
    ?>
    <div class="container my-5">
        <div class="row">
            <div class="col-12">
                <h1 class="mb-4">
                    <i class="fas fa-compass"></i> Navigation Guide
                </h1>
                <p class="lead">
                    Complete guide to the navigation structure and role-based access in the Defect Tracker application.
                </p>
            </div>
        </div>

        <!-- Admin Role -->
        <div class="role-section">
            <div class="role-header">
                <i class="fas fa-user-shield role-icon"></i>
                <div>
                    <h2 class="mb-0">Admin</h2>
                    <p class="text-muted mb-0">Full system access - All features available</p>
                </div>
            </div>
            <ul class="nav-list">
                <li><i class="fas fa-dashboard"></i> Dashboard <span class="badge bg-success badge-access">All Users</span></li>
                <li>
                    <i class="fas fa-bug"></i> Defect Ops
                    <div class="dropdown-section">
                        <strong>Defects</strong>
                        <ul>
                            <li>Defect Control Room</li>
                            <li>Create Defect</li>
                            <li>Assign Defects</li>
                            <li>Completion Evidence</li>
                            <li>Legacy Register</li>
                            <li>Visualise Defects</li>
                        </ul>
                        <strong>Quick Actions</strong>
                        <ul>
                            <li>View Defect</li>
                            <li>Upload Floor Plan</li>
                        </ul>
                    </div>
                </li>
                <li>
                    <i class="fas fa-building"></i> Projects
                    <div class="dropdown-section">
                        <ul>
                            <li>Projects Directory</li>
                            <li>Floor Plan Library</li>
                            <li>Floorplan Selector</li>
                            <li>Delete Floor Plan</li>
                        </ul>
                    </div>
                </li>
                <li>
                    <i class="fas fa-users"></i> Directory
                    <div class="dropdown-section">
                        <ul>
                            <li>User Management</li>
                            <li>Add User</li>
                            <li>Role Management</li>
                            <li>Contractor Directory</li>
                            <li>Add Contractor</li>
                            <li>Contractor Analytics</li>
                            <li>View Contractor</li>
                        </ul>
                    </div>
                </li>
                <li>
                    <i class="fas fa-images"></i> Assets
                    <div class="dropdown-section">
                        <ul>
                            <li>Brand Assets</li>
                            <li>Upload Floor Plan</li>
                            <li>Process Images</li>
                        </ul>
                    </div>
                </li>
                <li>
                    <i class="fas fa-chart-bar"></i> Reports
                    <div class="dropdown-section">
                        <ul>
                            <li>Reporting Hub</li>
                            <li>Data Exporter</li>
                            <li>PDF Exports</li>
                        </ul>
                    </div>
                </li>
                <li>
                    <i class="fas fa-comments"></i> Communications
                    <div class="dropdown-section">
                        <ul>
                            <li>Notification Centre</li>
                            <li>Broadcast Message</li>
                        </ul>
                    </div>
                </li>
                <li>
                    <i class="fas fa-cog"></i> System
                    <div class="dropdown-section">
                        <ul>
                            <li>Admin Console</li>
                            <li>System Settings</li>
                            <li>Site Presentation</li>
                            <li>Maintenance Planner</li>
                            <li>Backup Manager</li>
                        </ul>
                        <strong>Diagnostics</strong>
                        <ul>
                            <li>System Health</li>
                            <li>Database Check</li>
                            <li>Database Optimizer</li>
                            <li>GD Library Check</li>
                            <li>ImageMagick Check</li>
                            <li>File Structure Map</li>
                            <li>System Analysis Report</li>
                            <li>User Logs</li>
                        </ul>
                    </div>
                </li>
                <li><i class="fas fa-question-circle"></i> Help</li>
                <li><i class="fas fa-sign-out-alt"></i> Logout</li>
            </ul>
        </div>

        <!-- Manager Role -->
        <div class="role-section">
            <div class="role-header">
                <i class="fas fa-user-tie role-icon"></i>
                <div>
                    <h2 class="mb-0">Manager</h2>
                    <p class="text-muted mb-0">Managerial access - Most features available</p>
                </div>
            </div>
            <ul class="nav-list">
                <li><i class="fas fa-dashboard"></i> Dashboard</li>
                <li>
                    <i class="fas fa-bug"></i> Defects
                    <div class="dropdown-section">
                        <ul>
                            <li>Defect Control Room</li>
                            <li>Create Defect</li>
                            <li>Assign Defects</li>
                            <li>Upload Completion Evidence</li>
                            <li>Visualise Defects</li>
                        </ul>
                    </div>
                </li>
                <li>
                    <i class="fas fa-building"></i> Projects
                    <div class="dropdown-section">
                        <ul>
                            <li>Projects Directory</li>
                            <li>Project Explorer</li>
                            <li>Floor Plans</li>
                            <li>Upload Floor Plan</li>
                        </ul>
                    </div>
                </li>
                <li>
                    <i class="fas fa-users"></i> Directory
                    <div class="dropdown-section">
                        <ul>
                            <li>User Management</li>
                            <li>Add User</li>
                            <li>Contractors</li>
                            <li>Add Contractor</li>
                        </ul>
                    </div>
                </li>
                <li><i class="fas fa-chart-bar"></i> Reports</li>
                <li>
                    <i class="fas fa-comments"></i> Communications
                    <div class="dropdown-section">
                        <ul>
                            <li>Notification Centre</li>
                            <li>Broadcast Message</li>
                        </ul>
                    </div>
                </li>
                <li><i class="fas fa-question-circle"></i> Help</li>
                <li><i class="fas fa-sign-out-alt"></i> Logout</li>
            </ul>
        </div>

        <!-- Contractor Role -->
        <div class="role-section">
            <div class="role-header">
                <i class="fas fa-hard-hat role-icon"></i>
                <div>
                    <h2 class="mb-0">Contractor</h2>
                    <p class="text-muted mb-0">Limited access - Task focused</p>
                </div>
            </div>
            <ul class="nav-list">
                <li><i class="fas fa-dashboard"></i> Dashboard</li>
                <li><i class="fas fa-tasks"></i> Assigned Defects</li>
                <li><i class="fas fa-upload"></i> Submit Evidence</li>
                <li><i class="fas fa-bell"></i> Notification Centre</li>
                <li><i class="fas fa-question-circle"></i> Help</li>
                <li><i class="fas fa-sign-out-alt"></i> Logout</li>
            </ul>
        </div>

        <!-- Inspector Role -->
        <div class="role-section">
            <div class="role-header">
                <i class="fas fa-clipboard-check role-icon"></i>
                <div>
                    <h2 class="mb-0">Inspector</h2>
                    <p class="text-muted mb-0">Inspector access - Creation and viewing</p>
                </div>
            </div>
            <ul class="nav-list">
                <li><i class="fas fa-dashboard"></i> Dashboard</li>
                <li>
                    <i class="fas fa-bug"></i> Defects
                    <div class="dropdown-section">
                        <ul>
                            <li>Defect Control Room</li>
                            <li>Create Defect</li>
                            <li>Visualise Defects</li>
                        </ul>
                    </div>
                </li>
                <li>
                    <i class="fas fa-building"></i> Projects
                    <div class="dropdown-section">
                        <ul>
                            <li>Projects Directory</li>
                            <li>Project Explorer</li>
                            <li>Floor Plans</li>
                        </ul>
                    </div>
                </li>
                <li><i class="fas fa-chart-bar"></i> Reports</li>
                <li><i class="fas fa-bell"></i> Notification Centre</li>
                <li><i class="fas fa-question-circle"></i> Help</li>
                <li><i class="fas fa-sign-out-alt"></i> Logout</li>
            </ul>
        </div>

        <!-- Viewer Role -->
        <div class="role-section">
            <div class="role-header">
                <i class="fas fa-eye role-icon"></i>
                <div>
                    <h2 class="mb-0">Viewer</h2>
                    <p class="text-muted mb-0">Read-only access</p>
                </div>
            </div>
            <ul class="nav-list">
                <li><i class="fas fa-dashboard"></i> Dashboard</li>
                <li><i class="fas fa-bug"></i> Defect Control Room</li>
                <li><i class="fas fa-chart-bar"></i> Reports</li>
                <li><i class="fas fa-question-circle"></i> Help</li>
                <li><i class="fas fa-sign-out-alt"></i> Logout</li>
            </ul>
        </div>

        <!-- Client Role -->
        <div class="role-section">
            <div class="role-header">
                <i class="fas fa-user role-icon"></i>
                <div>
                    <h2 class="mb-0">Client</h2>
                    <p class="text-muted mb-0">Client view access</p>
                </div>
            </div>
            <ul class="nav-list">
                <li><i class="fas fa-dashboard"></i> Dashboard</li>
                <li><i class="fas fa-bug"></i> Defect Control Room</li>
                <li><i class="fas fa-chart-line"></i> Visualise Defects</li>
                <li><i class="fas fa-chart-bar"></i> Reports</li>
                <li><i class="fas fa-question-circle"></i> Help</li>
                <li><i class="fas fa-sign-out-alt"></i> Logout</li>
            </ul>
        </div>

        <div class="mt-5">
            <h3>Mobile Navigation</h3>
            <p>
                On mobile devices, the navigation menu collapses into a hamburger menu for better usability.
                All dropdown menus are touch-friendly and work seamlessly on smartphones and tablets.
            </p>
            <ul>
                <li>Tap the hamburger icon (☰) to expand the navigation menu</li>
                <li>Tap dropdown items to expand their sub-menus</li>
                <li>The clock and user information stack vertically on small screens</li>
                <li>Notifications are accessible via the bell icon</li>
            </ul>
        </div>

        <div class="mt-4">
            <a href="/help_index.php" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> Back to Help Index
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

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

require_once 'includes/navbar.php';

// Include database configuration
$config = [
    'db_host' => '10.35.233.124:3306',
    'db_name' => 'k87747_defecttracker',
    'db_user' => 'k87747_defecttracker',
    'db_pass' => 'Subaru5554346'
];

$user_id = $_SESSION['user_id'] ?? 0;
$username = $_SESSION['username'] ?? '';
$full_name = $_SESSION['full_name'] ?? '';
$sessionUserType = $_SESSION['user_type'] ?? 'viewer';

$navbar = null;

try {
    $db = new PDO(
        "mysql:host={$config['db_host']};dbname={$config['db_name']}",
        $config['db_user'],
        $config['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    if ($user_id > 0 && $username !== '') {
        $navbar = new Navbar($db, (int) $user_id, $username);
    }
} catch (PDOException $e) {
    error_log("Database Connection Error: " . $e->getMessage());
    die("Connection failed: Database error");
}

// Get user role information

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

$adminActionSections = [
    [
        'heading' => 'Core Operations',
        'subheading' => 'Manage people, projects, and the assets that power site inspections.',
        'items' => [
            [
                'tag' => 'users',
                'tag_label' => 'Users',
                'icon' => 'bx-user-circle',
                'title' => 'User Management',
                'description' => 'Add, edit, or deactivate users and adjust their access levels.',
                'links' => [
                    ['label' => 'View Users', 'href' => 'user_management.php', 'icon' => 'bx-list-ul', 'variant' => 'primary'],
                    ['label' => 'Add User', 'href' => 'add_user.php', 'icon' => 'bx-plus', 'variant' => 'outline'],
                ],
            ],
            [
                'tag' => 'projects',
                'tag_label' => 'Projects',
                'icon' => 'bx-building-house',
                'title' => 'Project Portfolio',
                'description' => 'Create new projects, update milestones, and monitor delivery status.',
                'links' => [
                    ['label' => 'Manage Projects', 'href' => 'projects.php', 'icon' => 'bx-building', 'variant' => 'success'],
                    ['label' => 'Add Project', 'href' => 'add_project.php', 'icon' => 'bx-plus-circle', 'variant' => 'outline'],
                ],
            ],
            [
                'tag' => 'contractors',
                'tag_label' => 'Contractors',
                'icon' => 'bx-hard-hat',
                'title' => 'Contractor Network',
                'description' => 'Maintain contractor profiles, trades, and engagement history.',
                'links' => [
                    ['label' => 'View Contractors', 'href' => 'contractors.php', 'icon' => 'bx-list-ul', 'variant' => 'warning'],
                    ['label' => 'Add Contractor', 'href' => 'add_contractor.php', 'icon' => 'bx-plus', 'variant' => 'outline'],
                ],
            ],
            [
                'tag' => 'floorplans',
                'tag_label' => 'Floor Plans',
                'icon' => 'bx-map-alt',
                'title' => 'Floor Plan Library',
                'description' => 'Upload, catalogue, and audit project floor plans for on-site teams.',
                'links' => [
                    ['label' => 'Browse Plans', 'href' => 'floor_plans.php', 'icon' => 'bx-images', 'variant' => 'primary'],
                    ['label' => 'Upload Plan', 'href' => 'upload_floor_plan.php', 'icon' => 'bx-cloud-upload', 'variant' => 'outline'],
                ],
            ],
        ],
    ],
    [
        'heading' => 'Monitoring & Maintenance',
        'subheading' => 'Stay ahead of reporting, data resilience, and activity auditing.',
        'items' => [
            [
                'tag' => 'report',
                'tag_label' => 'Reports',
                'icon' => 'bx-bar-chart-alt-2',
                'title' => 'Analytics & Reports',
                'description' => 'Generate performance dashboards for defects, projects, and contractors.',
                'links' => [
                    ['label' => 'View Reports', 'href' => 'reports.php', 'icon' => 'bx-line-chart', 'variant' => 'info'],
                ],
            ],
            [
                'tag' => 'backups',
                'tag_label' => 'Backups',
                'icon' => 'bx-cloud-download',
                'title' => 'Backup Control',
                'description' => 'Trigger full system backups and verify the latest recovery points.',
                'links' => [
                    ['label' => 'Open Backup Manager', 'href' => '/backups/index.php', 'icon' => 'bx-shield-quarter', 'variant' => 'info'],
                ],
            ],
            [
                'tag' => 'cleanup',
                'tag_label' => 'Cleanup',
                'icon' => 'bx-trash-alt',
                'title' => 'Website Cleanup',
                'description' => 'Clean all user data and create a fresh backup template. Use with caution!',
                'links' => [
                    ['label' => 'Cleanup Tool', 'href' => '/admin/cleanup_interface.php', 'icon' => 'bx-recycle', 'variant' => 'danger'],
                ],
            ],
            [
                'tag' => 'sync',
                'tag_label' => 'Sync',
                'icon' => 'bx-sync',
                'title' => 'Offline Sync Settings',
                'description' => 'Configure field sync profiles and troubleshoot offline job queues.',
                'links' => [
                    ['label' => 'Configure Sync', 'href' => '/sync/admin/dashboard.php', 'icon' => 'bx-slider-alt', 'variant' => 'outline'],
                ],
            ],
            [
                'tag' => 'logs',
                'tag_label' => 'Audit Logs',
                'icon' => 'bx-clipboard',
                'title' => 'User Activity Logs',
                'description' => 'Review detailed interaction history and export audit-ready summaries.',
                'links' => [
                    ['label' => 'Inspect Logs', 'href' => 'user_logs.php', 'icon' => 'bx-search-alt-2', 'variant' => 'outline'],
                ],
            ],
            [
                'tag' => 'presentation',
                'tag_label' => 'Presentation',
                'icon' => 'bx-presentation',
                'title' => 'Site Presentation',
                'description' => 'Access system analysis, training materials, and role documentation.',
                'links' => [
                    ['label' => 'System Analysis', 'href' => 'Site-presentation/index.php', 'icon' => 'bx-bar-chart-alt', 'variant' => 'primary'],
                    ['label' => 'Training', 'href' => 'Site-presentation/training.php', 'icon' => 'bx-book-open', 'variant' => 'outline'],
                    ['label' => 'Roles & Permissions', 'href' => 'Site-presentation/roles.php', 'icon' => 'bx-user-check', 'variant' => 'outline'],
                ],
            ],
        ],
    ],
];

if (!defined('APP_THEME_LOADED')) {
    define('APP_THEME_LOADED', true);
}

// Function to check if user has a specific role
function hasRole($role_id, $user_roles) {
    return in_array($role_id, $user_roles);
}

// Check if user has admin access
$isAdmin = hasRole(1, $user_roles) || $sessionUserType === 'admin';
$isManager = hasRole(2, $user_roles) || $sessionUserType === 'manager';

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
<body class="tool-body has-app-navbar" data-bs-theme="dark">
    <?php if ($navbar instanceof Navbar) { $navbar->render(); } ?>

    <main class="tool-page container-xl py-4">
        <div class="tool-header mb-5">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <h1 class="h3 mb-2">Administrative Control Center</h1>
                    <p class="text-muted mb-0">Command the full construction defect platform with streamlined access to operations, reporting, and diagnostics.</p>
                </div>
                <div class="d-flex flex-column align-items-start text-muted small gap-1">
                    <span><i class='bx bx-user-voice me-1'></i><?php echo htmlspecialchars($full_name ?: $username, ENT_QUOTES, 'UTF-8'); ?></span>
                    <span><i class='bx bx-label me-1'></i><?php
                        $role_names = [];
                        foreach ($user_roles as $role) {
                            if (isset($role_definitions[$role])) {
                                $role_names[] = $role_definitions[$role]['name'];
                            }
                        }
                        echo htmlspecialchars(implode(', ', $role_names) ?: 'Team Member', ENT_QUOTES, 'UTF-8');
                    ?></span>
                </div>
            </div>
        </div>

        <?php foreach ($adminActionSections as $section): ?>
            <section class="mb-5">
                <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
                    <div>
                        <h2 class="h5 mb-1"><?php echo htmlspecialchars($section['heading'], ENT_QUOTES, 'UTF-8'); ?></h2>
                        <p class="text-muted small mb-0"><?php echo htmlspecialchars($section['subheading'], ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                </div>
                <div class="system-tools-grid">
                    <?php foreach ($section['items'] as $item): ?>
                        <article class="system-tool-card">
                            <div class="system-tool-card__icon">
                                <i class='bx <?php echo htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8'); ?>'></i>
                            </div>
                            <div class="system-tool-card__body">
                                <?php if (!empty($item['tag'])): ?>
                                    <span class="system-tool-card__tag system-tool-card__tag--<?php echo htmlspecialchars($item['tag'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($item['tag_label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>
                                <h3 class="system-tool-card__title"><?php echo htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                <p class="system-tool-card__description"><?php echo htmlspecialchars($item['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <?php if (!empty($item['links'])): ?>
                                    <div class="d-flex flex-wrap gap-2 pt-1">
                                        <?php foreach ($item['links'] as $link):
                                            $variant = $link['variant'] ?? 'outline';
                                            $btnClass = match ($variant) {
                                                'primary' => 'btn btn-sm btn-primary',
                                                'success' => 'btn btn-sm btn-success',
                                                'warning' => 'btn btn-sm btn-warning text-dark fw-semibold',
                                                'info' => 'btn btn-sm btn-info',
                                                default => 'btn btn-sm btn-outline-light'
                                            };
                                        ?>
                                            <a class="<?php echo $btnClass; ?>" href="<?php echo htmlspecialchars($link['href'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <?php if (!empty($link['icon'])): ?><i class='bx <?php echo htmlspecialchars($link['icon'], ENT_QUOTES, 'UTF-8'); ?>'></i> <?php endif; ?>
                                                <?php echo htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8'); ?>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>

        <?php if ($isAdmin): ?>
            <section class="mb-5">
                <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
                    <div>
                        <h2 class="h5 mb-1">System Tools Suite</h2>
                        <p class="text-muted small mb-0">Administrative diagnostics and maintenance utilities.</p>
                    </div>
                    <span class="badge bg-gradient-info text-uppercase">Admin Only</span>
                </div>
                <div class="card system-tools-card border-0">
                    <div class="card-body">
                        <div class="system-tools-grid">
                            <?php foreach ($systemTools as $tool): ?>
                                <article class="system-tool-card">
                                    <div class="system-tool-card__icon">
                                        <i class='bx <?php echo htmlspecialchars($tool['icon'], ENT_QUOTES, 'UTF-8'); ?>'></i>
                                    </div>
                                    <div class="system-tool-card__body">
                                        <span class="system-tool-card__tag system-tool-card__tag--<?php echo htmlspecialchars($tool['badge'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($tool['badge'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <h3 class="system-tool-card__title"><?php echo htmlspecialchars($tool['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                        <p class="system-tool-card__description"><?php echo htmlspecialchars($tool['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                                        <a class="btn btn-sm btn-outline-light system-tool-card__action" href="<?php echo htmlspecialchars($tool['path'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
                                            <i class='bx bx-caret-right-circle'></i>
                                            Open Tool
                                        </a>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    </main>

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
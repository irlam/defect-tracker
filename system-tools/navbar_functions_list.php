<?php
/**
 * Navbar Functions List - Complete Reference
 * 
 * This script generates a comprehensive list of all functions available
 * in the McGoff Defect Tracker navbar across all user roles.
 * 
 * Created: 2025-11-04
 * Purpose: Document all system functions and their URLs
 * 
 * Security Note: This page provides system structure information.
 * In a production environment, consider adding authentication
 * or restricting access to admin users only.
 */

// Authentication check - Uncomment in production
// if (session_status() === PHP_SESSION_NONE) {
//     session_start();
// }
// if (!isset($_SESSION['username'])) {
//     header("Location: /login.php");
//     exit();
// }
// // Optional: Restrict to admin users only
// if ($_SESSION['user_type'] !== 'admin') {
//     header("Location: /dashboard.php");
//     exit();
// }

$pageTitle = 'Complete Navbar Functions Reference';

// Define all navbar items for each user role (extracted from navbar.php)
$navbarStructure = [
    'admin' => [
        ['label' => 'Dashboard', 'url' => '/dashboard.php'],
        ['label' => 'Defect Ops', 'dropdown' => [
            ['type' => 'header', 'label' => 'Defects'],
            ['label' => 'Defect Control Room', 'url' => '/defects.php'],
            ['label' => 'Create Defect', 'url' => '/create_defect.php'],
            ['label' => 'Assign Defects', 'url' => '/assign_to_user.php'],
            ['label' => 'Completion Evidence', 'url' => '/upload_completed_images.php'],
            ['label' => 'Legacy Register', 'url' => '/all_defects.php'],
            ['label' => 'Visualise Defects', 'url' => '/visualize_defects.php'],
            ['label' => '---divider---'],
            ['type' => 'header', 'label' => 'Quick Actions'],
            ['label' => 'View Defect', 'url' => '/view_defect.php'],
            ['label' => 'Upload Floor Plan', 'url' => '/upload_floor_plan.php'],
        ]],
        ['label' => 'Projects', 'dropdown' => [
            ['label' => 'Projects Directory', 'url' => '/projects.php'],
            ['label' => 'Floor Plan Library', 'url' => '/floor_plans.php'],
            ['label' => 'Floorplan Selector', 'url' => '/floorplan_selector.php'],
            ['label' => 'Delete Floor Plan', 'url' => '/delete_floor_plan.php'],
        ]],
        ['label' => 'Directory', 'dropdown' => [
            ['label' => 'User Management', 'url' => '/user_management.php'],
            ['label' => 'Add User', 'url' => '/add_user.php'],
            ['label' => 'Role Management', 'url' => '/role_management.php'],
            ['label' => 'Contractor Directory', 'url' => '/contractors.php'],
            ['label' => 'Add Contractor', 'url' => '/add_contractor.php'],
            ['label' => 'Contractor Analytics', 'url' => '/contractor_stats.php'],
            ['label' => 'View Contractor', 'url' => '/view_contractor.php'],
        ]],
        ['label' => 'Assets', 'dropdown' => [
            ['label' => 'Brand Assets', 'url' => '/add_logo.php'],
            ['label' => 'Upload Floor Plan', 'url' => '/upload_floor_plan.php'],
            ['label' => 'Process Images', 'url' => '/processDefectImages.php'],
        ]],
        ['label' => 'Reports', 'dropdown' => [
            ['label' => 'Reporting Hub', 'url' => '/reports.php'],
            ['label' => 'Data Exporter', 'url' => '/export.php'],
            ['label' => 'PDF Exports', 'url' => '/pdf_exports/export-pdf-defects-report-filtered.php'],
        ]],
        ['label' => 'Communications', 'dropdown' => [
            ['label' => 'Notification Centre', 'url' => '/notifications.php'],
            ['label' => 'Broadcast Message', 'url' => '/push_notifications/index.php'],
        ]],
        ['label' => 'System', 'dropdown' => [
            ['label' => 'Admin Console', 'url' => '/admin.php'],
            ['label' => 'System Settings', 'url' => '/admin/system_settings.php'],
            ['label' => 'Site Presentation', 'url' => '/Site-presentation/index.php'],
            ['label' => 'Maintenance Planner', 'url' => '/maintenance/maintenance.php'],
            ['label' => 'Backup Manager', 'url' => '/backup_manager.php'],
            ['label' => '---divider---'],
            ['type' => 'header', 'label' => 'Diagnostics'],
            ['label' => 'System Health', 'url' => '/system-tools/system_health.php'],
            ['label' => 'Database Check', 'url' => '/system-tools/check_database.php'],
            ['label' => 'Database Optimizer', 'url' => '/system-tools/database_optimizer.php'],
            ['label' => 'GD Library Check', 'url' => '/system-tools/check_gd.php'],
            ['label' => 'ImageMagick Check', 'url' => '/system-tools/check_imagemagick.php'],
            ['label' => 'File Structure Map', 'url' => '/system-tools/show_file_structure.php'],
            ['label' => 'System Analysis Report', 'url' => '/system-tools/system_analysis_report.php'],
            ['label' => 'Navbar Verification', 'url' => '/system-tools/navbar_verification.php'],
            ['label' => 'User Logs', 'url' => '/user_logs.php'],
        ]],
        ['label' => 'Help', 'url' => '/help_index.php'],
        ['label' => 'Logout', 'url' => '/logout.php'],
    ],
    'manager' => [
        ['label' => 'Dashboard', 'url' => '/dashboard.php'],
        ['label' => 'Defects', 'dropdown' => [
            ['label' => 'Defect Control Room', 'url' => '/defects.php'],
            ['label' => 'Create Defect', 'url' => '/create_defect.php'],
            ['label' => 'Assign Defects', 'url' => '/assign_to_user.php'],
            ['label' => 'Upload Completion Evidence', 'url' => '/upload_completed_images.php'],
            ['label' => 'Visualise Defects', 'url' => '/visualize_defects.php'],
        ]],
        ['label' => 'Projects', 'dropdown' => [
            ['label' => 'Projects Directory', 'url' => '/projects.php'],
            ['label' => 'Project Explorer', 'url' => '/project_details.php'],
            ['label' => 'Floor Plans', 'url' => '/floor_plans.php'],
            ['label' => 'Upload Floor Plan', 'url' => '/upload_floor_plan.php'],
        ]],
        ['label' => 'Directory', 'dropdown' => [
            ['label' => 'User Management', 'url' => '/user_management.php'],
            ['label' => 'Add User', 'url' => '/add_user.php'],
            ['label' => 'Contractors', 'url' => '/contractors.php'],
            ['label' => 'Add Contractor', 'url' => '/add_contractor.php'],
        ]],
        ['label' => 'Reports', 'url' => '/reports.php'],
        ['label' => 'Communications', 'dropdown' => [
            ['label' => 'Notification Centre', 'url' => '/notifications.php'],
            ['label' => 'Broadcast Message', 'url' => '/push_notifications/index.php'],
        ]],
        ['label' => 'Help', 'url' => '/help_index.php'],
        ['label' => 'Logout', 'url' => '/logout.php'],
    ],
    'contractor' => [
        ['label' => 'Dashboard', 'url' => '/dashboard.php'],
        ['label' => 'Assigned Defects', 'url' => '/my_tasks.php'],
        ['label' => 'Submit Evidence', 'url' => '/upload_completed_images.php'],
        ['label' => 'Notification Centre', 'url' => '/notifications.php'],
        ['label' => 'Help', 'url' => '/help_index.php'],
        ['label' => 'Logout', 'url' => '/logout.php'],
    ],
    'inspector' => [
        ['label' => 'Dashboard', 'url' => '/dashboard.php'],
        ['label' => 'Defects', 'dropdown' => [
            ['label' => 'Defect Control Room', 'url' => '/defects.php'],
            ['label' => 'Create Defect', 'url' => '/create_defect.php'],
            ['label' => 'Visualise Defects', 'url' => '/visualize_defects.php'],
        ]],
        ['label' => 'Projects', 'dropdown' => [
            ['label' => 'Projects Directory', 'url' => '/projects.php'],
            ['label' => 'Project Explorer', 'url' => '/project_details.php'],
            ['label' => 'Floor Plans', 'url' => '/floor_plans.php'],
        ]],
        ['label' => 'Reports', 'url' => '/reports.php'],
        ['label' => 'Notification Centre', 'url' => '/notifications.php'],
        ['label' => 'Help', 'url' => '/help_index.php'],
        ['label' => 'Logout', 'url' => '/logout.php'],
    ],
    'viewer' => [
        ['label' => 'Dashboard', 'url' => '/dashboard.php'],
        ['label' => 'Defect Control Room', 'url' => '/defects.php'],
        ['label' => 'Reports', 'url' => '/reports.php'],
        ['label' => 'Help', 'url' => '/help_index.php'],
        ['label' => 'Logout', 'url' => '/logout.php'],
    ],
    'client' => [
        ['label' => 'Dashboard', 'url' => '/dashboard.php'],
        ['label' => 'Defect Control Room', 'url' => '/defects.php'],
        ['label' => 'Visualise Defects', 'url' => '/visualize_defects.php'],
        ['label' => 'Reports', 'url' => '/reports.php'],
        ['label' => 'Help', 'url' => '/help_index.php'],
        ['label' => 'Logout', 'url' => '/logout.php'],
    ],
];

// Function to check if file exists
function checkFileExists($url) {
    $path = $_SERVER['DOCUMENT_ROOT'] . $url;
    
    if (substr($url, -1) === '/') {
        if (file_exists($path . 'index.php')) return ['exists' => true, 'type' => 'directory'];
        if (file_exists($path . 'index.html')) return ['exists' => true, 'type' => 'directory'];
        return ['exists' => false, 'type' => 'missing'];
    }
    
    return ['exists' => file_exists($path), 'type' => file_exists($path) ? 'file' : 'missing'];
}

// Extract all unique URLs
$allUrls = [];
$seenUrls = []; // Use associative array for O(1) lookups
foreach ($navbarStructure as $role => $items) {
    foreach ($items as $item) {
        if (isset($item['url']) && !isset($seenUrls[$item['url']])) {
            $check = checkFileExists($item['url']);
            $allUrls[] = [
                'url' => $item['url'],
                'label' => $item['label'],
                'role' => $role,
                'exists' => $check['exists']
            ];
            $seenUrls[$item['url']] = true;
        }
        if (isset($item['dropdown'])) {
            foreach ($item['dropdown'] as $subItem) {
                if (isset($subItem['url']) && !isset($seenUrls[$subItem['url']])) {
                    $check = checkFileExists($subItem['url']);
                    $allUrls[] = [
                        'url' => $subItem['url'],
                        'label' => $subItem['label'],
                        'role' => $role,
                        'parent' => $item['label'],
                        'exists' => $check['exists']
                    ];
                    $seenUrls[$subItem['url']] = true;
                }
            }
        }
    }
}

// Sort by URL
usort($allUrls, function($a, $b) { return strcmp($a['url'], $b['url']); });

$baseUrl = 'https://' . $_SERVER['HTTP_HOST'];
$totalCount = count($allUrls);
$existingCount = count(array_filter($allUrls, fn($u) => $u['exists']));
$missingCount = $totalCount - $existingCount;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - Defect Tracker</title>
    <link rel="icon" type="image/png" href="/favicons/favicon-96x96.png" sizes="96x96" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="/css/app.css" rel="stylesheet">
    <style>
        body { padding: 2rem 0; }
        .status-icon { font-size: 1.2rem; }
        .url-code { font-family: 'Courier New', monospace; font-size: 0.9rem; }
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body class="tool-body" data-bs-theme="dark">
    <div class="container-xl">
        <header class="mb-4">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h1 class="h3 mb-2">
                        <i class='bx bx-list-check'></i> Complete Navbar Functions Reference
                    </h1>
                    <p class="text-muted mb-0">
                        Comprehensive list of all functions available in the McGoff Defect Tracker
                    </p>
                </div>
                <div class="no-print">
                    <button class="btn btn-sm btn-outline-light" onclick="window.print()">
                        <i class='bx bx-printer'></i> Print
                    </button>
                </div>
            </div>
        </header>

        <!-- Summary -->
        <section class="mb-4">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="card border-0">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3">
                                <div class="fs-2 text-primary"><i class='bx bx-list-ul'></i></div>
                                <div>
                                    <div class="text-muted small">Total Functions</div>
                                    <div class="h4 mb-0"><?php echo $totalCount; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3">
                                <div class="fs-2 text-success"><i class='bx bx-check-circle'></i></div>
                                <div>
                                    <div class="text-muted small">Valid URLs</div>
                                    <div class="h4 mb-0"><?php echo $existingCount; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3">
                                <div class="fs-2 text-danger"><i class='bx bx-error-circle'></i></div>
                                <div>
                                    <div class="text-muted small">Missing Files</div>
                                    <div class="h4 mb-0"><?php echo $missingCount; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- All Functions List -->
        <section class="mb-4">
            <div class="card border-0">
                <div class="card-header">
                    <h2 class="h5 mb-0">All Navbar Functions (Alphabetical by URL)</h2>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-dark table-hover mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 50px;">Status</th>
                                    <th>Function</th>
                                    <th>Full URL</th>
                                    <th>Parent Menu</th>
                                    <th>First Appearing In</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allUrls as $urlData): ?>
                                <tr>
                                    <td class="text-center">
                                        <?php if ($urlData['exists']): ?>
                                            <i class='bx bx-check-circle text-success status-icon'></i>
                                        <?php else: ?>
                                            <i class='bx bx-error-circle text-danger status-icon'></i>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($urlData['label']); ?></strong>
                                    </td>
                                    <td>
                                        <code class="url-code"><?php echo htmlspecialchars($baseUrl . $urlData['url']); ?></code>
                                    </td>
                                    <td>
                                        <?php if (isset($urlData['parent'])): ?>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($urlData['parent']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo htmlspecialchars($urlData['role']); ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <!-- Functions by Role -->
        <section class="mb-4">
            <div class="card border-0">
                <div class="card-header">
                    <h2 class="h5 mb-0">Functions by User Role</h2>
                </div>
                <div class="card-body">
                    <?php foreach ($navbarStructure as $role => $items): ?>
                    <div class="mb-4">
                        <h3 class="h6 text-uppercase mb-3">
                            <i class='bx bx-user'></i> <?php echo ucfirst($role); ?> Role
                        </h3>
                        <div class="list-group list-group-flush">
                            <?php foreach ($items as $item): ?>
                            <div class="list-group-item bg-transparent border-secondary">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <strong><?php echo htmlspecialchars($item['label']); ?></strong>
                                        <?php if (isset($item['url'])): ?>
                                            <br><small><code class="url-code"><?php echo htmlspecialchars($baseUrl . $item['url']); ?></code></small>
                                        <?php endif; ?>
                                        
                                        <?php if (isset($item['dropdown'])): ?>
                                        <div class="mt-2 ms-3">
                                            <?php foreach ($item['dropdown'] as $subItem): ?>
                                                <?php if (isset($subItem['url'])): ?>
                                                <div class="py-1">
                                                    <small>→ <?php echo htmlspecialchars($subItem['label']); ?></small>
                                                    <br><small><code class="url-code"><?php echo htmlspecialchars($baseUrl . $subItem['url']); ?></code></small>
                                                </div>
                                                <?php elseif (($subItem['label'] ?? '') === '---divider---'): ?>
                                                <hr class="my-2">
                                                <?php elseif (($subItem['type'] ?? '') === 'header'): ?>
                                                <div class="py-1">
                                                    <small class="text-uppercase text-muted fw-bold"><?php echo htmlspecialchars($subItem['label']); ?></small>
                                                </div>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- Documentation -->
        <section class="mb-4">
            <div class="card border-0">
                <div class="card-header">
                    <h2 class="h5 mb-0">About This Reference</h2>
                </div>
                <div class="card-body">
                    <p>
                        This page provides a complete reference of all navigation functions available in the McGoff Defect Tracker.
                        Functions are organized by user role, showing exactly which features each type of user can access.
                    </p>
                    <h3 class="h6 mt-3">User Roles:</h3>
                    <ul>
                        <li><strong>Admin</strong>: Full system access with all administrative features</li>
                        <li><strong>Manager</strong>: Comprehensive access for project management and oversight</li>
                        <li><strong>Inspector</strong>: Can create and view defects, access projects and reports</li>
                        <li><strong>Contractor</strong>: Limited access focused on assigned tasks</li>
                        <li><strong>Viewer</strong>: Read-only access to defects and reports</li>
                        <li><strong>Client</strong>: Client-facing view of defects and reports</li>
                    </ul>
                    <h3 class="h6 mt-3">Status Indicators:</h3>
                    <ul>
                        <li><i class='bx bx-check-circle text-success'></i> Function file exists and is accessible</li>
                        <li><i class='bx bx-error-circle text-danger'></i> Function file is missing or inaccessible</li>
                    </ul>
                </div>
            </div>
        </section>

        <div class="no-print text-center">
            <p class="text-muted small">
                Generated on <?php echo date('Y-m-d H:i:s'); ?> | McGoff Defect Tracker
            </p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

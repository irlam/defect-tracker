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

$displayName = ucwords(str_replace(['.', '_'], [' ', ' '], $_SESSION['username'] ?? 'User'));
$currentUserRoleSummary = ucwords(str_replace(['_', '-'], [' ', ' '], $_SESSION['user_type'] ?? 'User'));
date_default_timezone_set('Europe/London');
$currentTimestamp = date('d/m/Y H:i');
$dashboardMetrics = [];
$contractorStats = [];
$recentDefectsList = [];
$contractorOptionsJSON = '[]';

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

    $displayNameSource = $userDetails['username'] ?? $currentUser;
    $displayName = ucwords(str_replace(['.', '_'], [' ', ' '], $displayNameSource));

    $rawRole = $userDetails['role_name'] ?? $userDetails['user_type'] ?? '';
    $currentUserRoleSummary = $rawRole ? ucwords(str_replace(['_', '-'], [' ', ' '], $rawRole)) : 'User';

    // Set timezone to UK
    date_default_timezone_set('Europe/London');
    $currentTimestamp = date('d/m/Y H:i');

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
    $overallStats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $activeContractors = (int) ($overallStats['active_contractors'] ?? 0);
    $openDefects = (int) ($overallStats['open_defects'] ?? 0);
    $pendingDefects = (int) ($overallStats['pending_defects'] ?? 0);
    $totalDefects = (int) ($overallStats['total_defects'] ?? 0);
    $resolvedDefects = max($totalDefects - $openDefects - $pendingDefects, 0);

    $dashboardMetrics = [
        [
            'icon' => 'bx-building-house',
            'title' => 'Active Contractors',
            'stat' => $activeContractors,
            'description' => 'Active trade partners maintaining live workloads.',
            'tag' => 'contractors',
            'tag_label' => 'Workforce',
            'action' => [
                'href' => 'contractors.php',
                'label' => 'Manage Contractors',
                'icon' => 'bx-right-arrow-alt'
            ]
        ],
        [
            'icon' => 'bx-error-circle',
            'title' => 'Open Defects',
            'stat' => $openDefects,
            'description' => 'Outstanding items requiring immediate contractor action.',
            'tag' => 'open',
            'tag_label' => 'Attention',
            'action' => [
                'href' => 'defects.php?status=open',
                'label' => 'View Open Issues',
                'icon' => 'bx-search'
            ]
        ],
        [
            'icon' => 'bx-time-five',
            'title' => 'Pending Acceptance',
            'stat' => $pendingDefects,
            'description' => 'Defects awaiting acceptance or return confirmation.',
            'tag' => 'pending',
            'tag_label' => 'Awaiting',
            'action' => [
                'href' => 'defects.php?status=pending',
                'label' => 'Review Pending',
                'icon' => 'bx-timer'
            ]
        ],
        [
            'icon' => 'bx-badge-check',
            'title' => 'Resolved This Cycle',
            'stat' => $resolvedDefects,
            'description' => 'Defects resolved since the last reporting sync.',
            'tag' => 'overview',
            'tag_label' => 'Progress',
            'action' => [
                'href' => 'reports.php',
                'label' => 'Open Reports',
                'icon' => 'bx-bar-chart-alt-2'
            ]
        ]
    ];

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

// Log the debug information during development
error_log("Actual open defects: " . $actualOpenCount);

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
    $contractorOptionsJSON = json_encode(
        $contractorOptions,
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
    );
    if ($contractorOptionsJSON === false) {
        $contractorOptionsJSON = '[]';
    }
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
function correctContractorLogoPath(?string $path): string
{
    if (empty($path)) {
        return BASE_URL . 'assets/icons/company-placeholder.png';
    }

    $logoFilename = $path;
    if (stripos($logoFilename, 'uploads/logos/') === 0) {
        $logoFilename = substr($logoFilename, strlen('uploads/logos/'));
    }

    return BASE_URL . 'uploads/logos/' . $logoFilename;
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="css/app.css" rel="stylesheet">
    <script src="/reload.js"></script>
    <script>
        const contractorData = <?php echo $contractorOptionsJSON; ?>;
    </script>
</head>
<body class="tool-body has-app-navbar" data-bs-theme="dark">
    <?php if ($navbar instanceof Navbar) { $navbar->render(); } ?>

    <main class="tool-page container-xl py-4">
        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <header class="tool-header dashboard-hero mb-4">
            <div class="dashboard-hero__main">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                    <div>
                        <h1 class="h3 mb-2">Operational Defect Command</h1>
                        <p class="text-muted mb-0">Real-time insight into contractor workloads and outstanding issues.</p>
                    </div>
                    <div class="dashboard-hero__meta text-muted small">
                        <span><i class='bx bx-user-voice'></i><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></span>
                        <span><i class='bx bx-label'></i><?php echo htmlspecialchars($currentUserRoleSummary, ENT_QUOTES, 'UTF-8'); ?></span>
                        <span><i class='bx bx-calendar-event'></i><?php echo htmlspecialchars($currentTimestamp, ENT_QUOTES, 'UTF-8'); ?> UK</span>
                    </div>
                </div>
                <div class="dashboard-hero__cta">
                    <a class="btn btn-sm btn-outline-light" href="defects.php">
                        <i class='bx bx-list-ol'></i>
                        View Defects
                    </a>
                    <a class="btn btn-sm btn-outline-light" href="reports.php">
                        <i class='bx bx-bar-chart'></i>
                        Reports Hub
                    </a>
                </div>
            </div>
        </header>

        <section class="dashboard-launch mb-5">
            <a class="dashboard-launch-card" href="create_defect.php">
                <div class="dashboard-launch-card__icon-wrap">
                    <span class="dashboard-launch-card__icon"><i class='bx bx-plus-circle'></i></span>
                </div>
                <div class="dashboard-launch-card__content">
                    <span class="dashboard-launch-card__tag"><i class='bx bx-bolt-circle'></i>Quick capture</span>
                    <h2 class="dashboard-launch-card__title">Log a New Defect</h2>
                    <p class="dashboard-launch-card__lead">Open a defect in seconds with guided fields, evidence uploads, and instant contractor alerts.</p>
                    <ul class="dashboard-launch-card__features">
                        <li><i class='bx bx-check-shield'></i>Streamlined workflow with validation at each step</li>
                        <li><i class='bx bx-upload'></i>Attach photos, plans, and punch-list notes on the fly</li>
                        <li><i class='bx bx-broadcast'></i>Automatic notifications to the delivery team of choice</li>
                    </ul>
                </div>
                <div class="dashboard-launch-card__cta" aria-hidden="true">
                    <span class="dashboard-launch-card__cta-label">Start New Defect</span>
                    <span class="dashboard-launch-card__cta-icon"><i class='bx bx-right-arrow-alt'></i></span>
                </div>
            </a>
        </section>

        <?php if (!empty($dashboardMetrics)): ?>
        <section class="mb-5">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
                <div>
                    <h2 class="h5 mb-1">Today's Snapshot</h2>
                    <p class="text-muted small mb-0">Key delivery metrics pulled from the latest data sync.</p>
                </div>
            </div>
            <div class="system-tools-grid">
                <?php foreach ($dashboardMetrics as $metric): ?>
                <article class="system-tool-card">
                    <div class="system-tool-card__icon">
                        <i class='bx <?php echo htmlspecialchars($metric['icon'], ENT_QUOTES, 'UTF-8'); ?>'></i>
                    </div>
                    <div class="system-tool-card__body">
                        <?php if (!empty($metric['tag'])): ?>
                        <span class="system-tool-card__tag system-tool-card__tag--<?php echo htmlspecialchars($metric['tag'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($metric['tag_label'], ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                        <?php endif; ?>
                        <h3 class="system-tool-card__title"><?php echo htmlspecialchars($metric['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                        <p class="system-tool-card__stat mb-0"><?php echo number_format((int) $metric['stat']); ?></p>
                        <p class="system-tool-card__description"><?php echo htmlspecialchars($metric['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php if (!empty($metric['action']['href'])): ?>
                        <a class="btn btn-sm btn-outline-light system-tool-card__action" href="<?php echo htmlspecialchars($metric['action']['href'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php if (!empty($metric['action']['icon'])): ?><i class='bx <?php echo htmlspecialchars($metric['action']['icon'], ENT_QUOTES, 'UTF-8'); ?>'></i><?php endif; ?>
                            <?php echo htmlspecialchars($metric['action']['label'], ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                        <?php endif; ?>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <section class="mb-5">
            <div class="card border-0">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <h2 class="h5 mb-1">Contractor Performance</h2>
                        <p class="text-muted small mb-0">Track open workloads and response progress by delivery partner.</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-sm btn-outline-light" id="refreshContractors">
                            <i class='bx bx-refresh'></i>
                            Refresh
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-light" id="filterContractors" data-bs-toggle="modal" data-bs-target="#contractorFilterModal">
                            <i class='bx bx-filter-alt'></i>
                            Filter
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-light" id="exportContractors">
                            <i class='bx bx-export'></i>
                            Export CSV
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="contractorsTable" class="table table-dark table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Company</th>
                                    <th scope="col">Trade</th>
                                    <th scope="col" class="text-center">Total</th>
                                    <th scope="col" class="text-center">Open</th>
                                    <th scope="col" class="text-center">Pending</th>
                                    <th scope="col" class="text-center">Accepted</th>
                                    <th scope="col" class="text-center">Rejected</th>
                                    <th scope="col">Last Update</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($contractorStats)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">No contractor data available.</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($contractorStats as $contractor): ?>
                                <tr
                                    data-company="<?php echo htmlspecialchars(strtolower((string) $contractor['company_name']), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-trade="<?php echo htmlspecialchars(strtolower((string) ($contractor['trade'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-total="<?php echo (int) $contractor['total_defects']; ?>"
                                    data-open="<?php echo (int) $contractor['open_defects']; ?>"
                                    data-pending="<?php echo (int) $contractor['pending_defects']; ?>"
                                    data-accepted="<?php echo (int) $contractor['closed_defects']; ?>"
                                    data-rejected="<?php echo (int) $contractor['rejected_defects']; ?>"
                                >
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <?php if (!empty($contractor['logo'])): ?>
                                                <img src="<?php echo htmlspecialchars(correctContractorLogoPath($contractor['logo']), ENT_QUOTES, 'UTF-8'); ?>" alt="Logo" class="rounded-circle" width="32" height="32">
                                            <?php else: ?>
                                                <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis"><i class='bx bx-building-house'></i></span>
                                            <?php endif; ?>
                                            <span class="fw-semibold"><?php echo htmlspecialchars($contractor['company_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($contractor['trade'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="text-center"><?php echo number_format((int) $contractor['total_defects']); ?></td>
                                    <td class="text-center">
                                        <span class="badge rounded-pill bg-danger-subtle text-danger-emphasis fw-semibold"><?php echo (int) $contractor['open_defects']; ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis fw-semibold"><?php echo (int) $contractor['pending_defects']; ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge rounded-pill bg-success-subtle text-success-emphasis fw-semibold"><?php echo (int) $contractor['closed_defects']; ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis fw-semibold"><?php echo (int) $contractor['rejected_defects']; ?></span>
                                    </td>
                                    <td><?php echo $contractor['last_update'] !== 'N/A' ? formatUKDateTime($contractor['last_update']) : 'N/A'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <section>
            <div class="card border-0">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <h2 class="h5 mb-1">Recent Defects</h2>
                        <p class="text-muted small mb-0">Latest submissions with quick access to supporting media.</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-sm btn-outline-light" id="refreshDefects">
                            <i class='bx bx-refresh'></i>
                            Refresh
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-light" id="filterDefects" data-bs-toggle="modal" data-bs-target="#defectsFilterModal">
                            <i class='bx bx-filter-alt'></i>
                            Filter
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="recentDefectsTable" class="table table-dark table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">ID</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Priority</th>
                                    <th scope="col">Title</th>
                                    <th scope="col">Contractor</th>
                                    <th scope="col">Created</th>
                                    <th scope="col">Updated</th>
                                    <th scope="col" class="text-center">Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentDefectsList)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">No defects available.</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($recentDefectsList as $defect): ?>
                                <tr
                                    class="expandable-row"
                                    data-defect-id="<?php echo (int) $defect['id']; ?>"
                                    data-status="<?php echo htmlspecialchars(strtolower($defect['status']), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-priority="<?php echo htmlspecialchars(strtolower($defect['priority']), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-contractor="<?php echo htmlspecialchars(strtolower($defect['company_name'] ?? 'unassigned'), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-title="<?php echo htmlspecialchars(strtolower($defect['title']), ENT_QUOTES, 'UTF-8'); ?>"
                                >
                                    <td class="fw-semibold">#<?php echo (int) $defect['id']; ?></td>
                                    <td>
                                        <span class="badge rounded-pill bg-<?php echo getStatusBadgeClass($defect['status']); ?>">
                                            <?php echo ucfirst(htmlspecialchars($defect['status'], ENT_QUOTES, 'UTF-8')); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge rounded-pill bg-<?php echo getPriorityBadgeClass($defect['priority']); ?>">
                                            <?php echo ucfirst(htmlspecialchars($defect['priority'], ENT_QUOTES, 'UTF-8')); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($defect['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <?php if (!empty($defect['logo'])): ?>
                                                <img src="<?php echo htmlspecialchars(correctContractorLogoPath($defect['logo']), ENT_QUOTES, 'UTF-8'); ?>" alt="Logo" class="rounded-circle" width="28" height="28">
                                            <?php else: ?>
                                                <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis"><i class='bx bx-building'></i></span>
                                            <?php endif; ?>
                                            <span><?php echo htmlspecialchars($defect['company_name'] ?? 'Unassigned', ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                    </td>
                                    <td><?php echo formatUKDateTime($defect['created_at']); ?></td>
                                    <td><?php echo formatUKDateTime($defect['updated_at']); ?></td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-outline-light toggle-details" data-defect-id="<?php echo (int) $defect['id']; ?>">
                                            <i class='bx bx-chevron-down'></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr id="details-<?php echo (int) $defect['id']; ?>" class="details-row" style="display: none;">
                                    <td colspan="8" class="p-0">
                                        <div class="row-details">
                                            <div class="row g-4">
                                                <div class="col-lg-8">
                                                    <h6 class="mb-2">Description</h6>
                                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($defect['description'], ENT_QUOTES, 'UTF-8')); ?></p>
                                                    <?php if (!empty($defect['image_paths'])): ?>
                                                    <h6 class="mb-2 mt-4">Attachments</h6>
                                                    <div class="defect-image-gallery">
                                                        <?php
                                                        $image_paths = explode(',', $defect['image_paths']);
                                                        foreach ($image_paths as $image_path):
                                                            $trimmedPath = trim($image_path);
                                                            if ($trimmedPath === '') {
                                                                continue;
                                                            }
                                                        ?>
                                                        <div class="defect-image-container">
                                                            <img
                                                                src="<?php echo htmlspecialchars(correctDefectImagePath($trimmedPath), ENT_QUOTES, 'UTF-8'); ?>"
                                                                class="defect-thumbnail zoomable-image"
                                                                alt="Defect Image"
                                                                data-full-image="<?php echo htmlspecialchars(correctDefectImagePath($trimmedPath), ENT_QUOTES, 'UTF-8'); ?>"
                                                            >
                                                        </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="col-lg-4">
                                                    <div class="mb-3">
                                                        <h6 class="mb-1">Reported By</h6>
                                                        <p class="mb-3"><?php echo htmlspecialchars($defect['reported_by'], ENT_QUOTES, 'UTF-8'); ?></p>
                                                        <h6 class="mb-1">Last Updated</h6>
                                                        <p class="mb-0"><?php echo formatUKDateTime($defect['updated_at']); ?></p>
                                                    </div>
                                                    <a href="view_defect.php?id=<?php echo (int) $defect['id']; ?>" class="btn btn-sm btn-outline-light">
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
                </div>
            </div>
        </section>
    </main>

    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1080;"></div>

    <!-- Image Modal -->
    <div id="imageModal" class="image-modal" onclick="this.style.display='none';">
        <span class="close-image-modal">&times;</span>
        <div class="modal-image-content">
            <img id="modalImage" class="modal-full-image" src="" alt="Full size image">
        </div>
    </div>

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
                                <label class="form-check-label" for="filterHasOpenDefects">Has Open Defects</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="filterHasPendingDefects" checked>
                                <label class="form-check-label" for="filterHasPendingDefects">Has Pending Defects</label>
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

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.4/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    $(document).ready(function() {
        $('.toggle-details').on('click', function() {
            const defectId = $(this).data('defect-id');
            const detailsRow = $(`#details-${defectId}`);
            const content = detailsRow.find('.row-details');
            const icon = $(this).find('i');

            if (detailsRow.is(':visible')) {
                content.slideUp(160, function() {
                    detailsRow.hide();
                });
                icon.removeClass('bx-chevron-up').addClass('bx-chevron-down');
            } else {
                detailsRow.show();
                content.hide().slideDown(160);
                icon.removeClass('bx-chevron-down').addClass('bx-chevron-up');
            }
        });

        $('.zoomable-image').on('click', function() {
            const fullImageSrc = $(this).data('full-image');
            $('#modalImage').attr('src', fullImageSrc);
            $('#imageModal').css('display', 'flex');
        });

        $('.close-image-modal').on('click', function(e) {
            e.stopPropagation();
            $('#imageModal').css('display', 'none');
        });
    });
    </script>

    <script>
    $(document).ready(function() {
        const toastHost = $('.toast-container');

        function showToast(message, type = 'info') {
            const toastId = `toast-${Date.now()}`;
            const tone = type === 'success' ? 'success' : type === 'error' ? 'danger' : 'info';
            const toastMarkup = $(`
                <div id="${toastId}" class="toast align-items-center text-bg-dark border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="true" data-bs-delay="3200">
                    <div class="d-flex align-items-center gap-3 px-3 py-2">
                        <i class='bx bx-info-circle text-${tone}'></i>
                        <span>${message}</span>
                        <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            `);
            toastHost.append(toastMarkup);
            const toastInstance = new bootstrap.Toast(document.getElementById(toastId));
            toastInstance.show();
        }

        const contractorSelect = $('#filterDefectContractor');
        contractorData.forEach(contractor => {
            if (!contractor || !contractor.name) {
                return;
            }
            const option = $('<option/>', {
                value: contractor.name.toLowerCase(),
                text: contractor.name
            });
            contractorSelect.append(option);
        });

        $('#resetContractorFilter').on('click', function() {
            $('#filterContractorName').val('');
            $('#filterHasOpenDefects').prop('checked', true);
            $('#filterHasPendingDefects').prop('checked', true);
        });

        $('#resetDefectsFilter').on('click', function() {
            $('#filterDefectTitle').val('');
            $('#filterDefectContractor').val('');
            $('#filterStatusOpen, #filterStatusPending, #filterStatusAccepted, #filterStatusRejected').prop('checked', true);
            $('#filterPriorityHigh, #filterPriorityMedium, #filterPriorityLow').prop('checked', true);
        });

        $('#applyContractorFilter').on('click', function() {
            const nameFilter = $('#filterContractorName').val().toLowerCase().trim();
            const requireOpen = $('#filterHasOpenDefects').is(':checked');
            const requirePending = $('#filterHasPendingDefects').is(':checked');
            let visibleCount = 0;

            $('#contractorsTable tbody tr').each(function() {
                const $row = $(this);
                if ($row.find('td').length <= 1) {
                    return;
                }

                const companyKey = String($row.data('company') || '');
                const openCount = Number($row.data('open') || 0);
                const pendingCount = Number($row.data('pending') || 0);

                const matchesName = !nameFilter || companyKey.includes(nameFilter);
                const matchesOpen = !requireOpen || openCount > 0;
                const matchesPending = !requirePending || pendingCount > 0;

                if (matchesName && matchesOpen && matchesPending) {
                    $row.show();
                    visibleCount++;
                } else {
                    $row.hide();
                }
            });

            const contractorModal = bootstrap.Modal.getInstance(document.getElementById('contractorFilterModal'))
                || new bootstrap.Modal(document.getElementById('contractorFilterModal'));
            contractorModal.hide();

            showToast(`Filters applied: ${visibleCount} contractor${visibleCount === 1 ? '' : 's'} shown`, 'success');
        });

        $('#applyDefectsFilter').on('click', function() {
            const titleFilter = $('#filterDefectTitle').val().toLowerCase().trim();
            const contractorFilter = ($('#filterDefectContractor').val() || '').toLowerCase();

            const selectedStatuses = [];
            if ($('#filterStatusOpen').is(':checked')) selectedStatuses.push('open');
            if ($('#filterStatusPending').is(':checked')) selectedStatuses.push('pending');
            if ($('#filterStatusAccepted').is(':checked')) selectedStatuses.push('accepted');
            if ($('#filterStatusRejected').is(':checked')) selectedStatuses.push('rejected');

            const selectedPriorities = [];
            if ($('#filterPriorityHigh').is(':checked')) selectedPriorities.push('high');
            if ($('#filterPriorityMedium').is(':checked')) selectedPriorities.push('medium');
            if ($('#filterPriorityLow').is(':checked')) selectedPriorities.push('low');

            let visibleCount = 0;

            $('#recentDefectsTable tbody tr.expandable-row').each(function() {
                const $row = $(this);
                const defectId = $row.data('defect-id');
                const titleKey = String($row.data('title') || '');
                const statusKey = String($row.data('status') || '');
                const priorityKey = String($row.data('priority') || '');
                const contractorKey = String($row.data('contractor') || '');
                const detailsRow = $(`#details-${defectId}`);
                const icon = $row.find('.toggle-details i');

                const matchesTitle = !titleFilter || titleKey.includes(titleFilter);
                const matchesStatus = selectedStatuses.length === 0 || selectedStatuses.includes(statusKey);
                const matchesPriority = selectedPriorities.length === 0 || selectedPriorities.includes(priorityKey);
                const matchesContractor = !contractorFilter || contractorKey === contractorFilter;

                if (matchesTitle && matchesStatus && matchesPriority && matchesContractor) {
                    $row.show();
                    visibleCount++;
                } else {
                    $row.hide();
                    detailsRow.hide();
                    detailsRow.find('.row-details').hide();
                    icon.removeClass('bx-chevron-up').addClass('bx-chevron-down');
                }
            });

            const defectsModal = bootstrap.Modal.getInstance(document.getElementById('defectsFilterModal'))
                || new bootstrap.Modal(document.getElementById('defectsFilterModal'));
            defectsModal.hide();

            showToast(`Filters applied: ${visibleCount} defect${visibleCount === 1 ? '' : 's'} shown`, 'success');
        });

        $('#exportContractors').on('click', function() {
            let csvContent = 'Company,Trade,Total,Open,Pending,Accepted,Rejected,Last Update\n';

            $('#contractorsTable tbody tr:visible').each(function() {
                const $row = $(this);
                const cells = $row.find('td');
                if (cells.length !== 8) {
                    return;
                }

                const clean = value => String(value).trim().replace(/\s+/g, ' ');
                const company = clean($(cells[0]).text()).replace(/,/g, ' ');
                const trade = clean($(cells[1]).text()).replace(/,/g, ' ');
                const total = $row.data('total') ?? (Number(clean($(cells[2]).text())) || 0);
                const open = $row.data('open') ?? (Number(clean($(cells[3]).text())) || 0);
                const pending = $row.data('pending') ?? (Number(clean($(cells[4]).text())) || 0);
                const accepted = $row.data('accepted') ?? (Number(clean($(cells[5]).text())) || 0);
                const rejected = $row.data('rejected') ?? (Number(clean($(cells[6]).text())) || 0);
                const lastUpdate = clean($(cells[7]).text()).replace(/,/g, ' ');

                const line = [company, trade, total, open, pending, accepted, rejected, lastUpdate]
                    .map(value => `"${String(value).replace(/"/g, '""')}"`)
                    .join(',');
                csvContent += `${line}\n`;
            });

            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const downloadUrl = URL.createObjectURL(blob);
            const tempLink = document.createElement('a');
            tempLink.href = downloadUrl;
            tempLink.setAttribute('download', `contractor_stats_${new Date().toISOString().slice(0, 10)}.csv`);
            document.body.appendChild(tempLink);
            tempLink.click();
            document.body.removeChild(tempLink);
            URL.revokeObjectURL(downloadUrl);

            showToast('Contractor data exported successfully', 'success');
        });

        $('#refreshContractors, #refreshDefects').on('click', function() {
            $(this).prop('disabled', true);
            setTimeout(() => window.location.reload(), 300);
        });
    });
    </script>
</body>
</html>

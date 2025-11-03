<?php
/**
 * defects.php
 * Defect register with themed operations centre layout.
 */

ini_set('output_buffering', '1');
ob_start();

error_reporting(0);
// ini_set('display_errors', 1);
// ini_set('log_errors', 1);
// ini_set('error_log', __DIR__ . '/logs/error.log');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('INCLUDED', true);

require_once 'includes/functions.php';
require_once 'config/database.php';
require_once 'config/constants.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit();
}

date_default_timezone_set('Europe/London');

$pageTitle = 'Defects';
$currentUser = $_SESSION['username'] ?? 'user';
$currentUserId = (int) ($_SESSION['user_id'] ?? 0);
$displayName = ucwords(str_replace(['.', '_'], [' ', ' '], $currentUser));
$currentUserRoleSummary = ucwords(str_replace(['_', '-'], [' ', ' '], $_SESSION['user_type'] ?? 'user'));
$currentTimestamp = date('d/m/Y H:i');
$currentDateTimeIso = date('c');

$canEdit = false;
$canDelete = false;
$userRoles = [];
$defects = [];
$statuses = [];
$priorities = [];
$contractors = [];
$projects = [];
$totalRecords = 0;
$totalPages = 0;
$startRecord = 0;
$endRecord = 0;
$lastUpdateDisplay = 'No updates recorded';
$totalDefects = 0;
$activeDefects = 0;
$criticalDefects = 0;
$overdueDefects = 0;
$closedDefects = 0;
$rejectedDefects = 0;
$projectCount = 0;
$contractorCount = 0;

$statusFilter = $_GET['status'] ?? 'all';
$priorityFilter = $_GET['priority'] ?? 'all';
$contractorFilter = $_GET['contractor'] ?? 'all';
$projectFilter = $_GET['project'] ?? 'all';
$dateAddedFilter = $_GET['date_added'] ?? '';
$searchTerm = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

/**
 * Determine user permissions for edit and delete capabilities.
 */
function checkUserPermissions(PDO $db, int $userId): array
{
    $query = "SELECT r.name AS role_name
              FROM users u
              JOIN user_roles ur ON u.id = ur.user_id
              JOIN roles r ON ur.role_id = r.id
              WHERE u.id = :user_id";

    $stmt = $db->prepare($query);
    $stmt->execute([':user_id' => $userId]);
    $roles = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

    return [
        'canEdit' => in_array('admin', $roles, true) || in_array('manager', $roles, true),
        'canDelete' => in_array('admin', $roles, true),
        'roles' => $roles,
    ];
}

/**
 * Check whether a due date has passed.
 */
function isDueDateOverdue(?string $dueDate): bool
{
    if (empty($dueDate)) {
        return false;
    }

    $currentDate = new DateTime('now', new DateTimeZone('Europe/London'));
    $dueDateObj = new DateTime($dueDate, new DateTimeZone('Europe/London'));

    return $currentDate > $dueDateObj;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $permissions = checkUserPermissions($db, $currentUserId);
    $canEdit = $permissions['canEdit'];
    $canDelete = $permissions['canDelete'];
    $userRoles = $permissions['roles'];

    $whereClauses = ['d.deleted_at IS NULL'];
    $params = [];

    if ($statusFilter !== 'all') {
        $whereClauses[] = 'd.status = :status';
        $params[':status'] = $statusFilter;
    }

    if ($priorityFilter !== 'all') {
        if ($priorityFilter === 'overdue') {
            $whereClauses[] = 'd.due_date IS NOT NULL AND d.due_date < CURRENT_DATE()';
        } else {
            $whereClauses[] = 'd.priority = :priority';
            $params[':priority'] = $priorityFilter;
        }
    }

    if ($contractorFilter !== 'all') {
        $whereClauses[] = 'd.assigned_to = :contractor_id';
        $params[':contractor_id'] = $contractorFilter;
    }

    if ($projectFilter !== 'all') {
        $whereClauses[] = 'd.project_id = :project_id';
        $params[':project_id'] = $projectFilter;
    }

    if (!empty($dateAddedFilter)) {
        $whereClauses[] = 'DATE(d.created_at) = :date_added';
        $params[':date_added'] = $dateAddedFilter;
    }

    if (!empty($searchTerm)) {
        $whereClauses[] = '(d.title LIKE :search OR d.description LIKE :search OR c.company_name LIKE :search)';
        $params[':search'] = "%{$searchTerm}%";
    }

    $whereSql = implode(' AND ', $whereClauses);

    $query = "SELECT 
                d.*, 
                c.company_name AS contractor_name,
                c.logo AS contractor_logo,
                p.name AS project_name,
                u.username AS created_by_user,
                CONCAT(u.first_name, ' ', u.last_name) AS created_by_full_name,
                GROUP_CONCAT(DISTINCT di.file_path) AS image_paths,
                GROUP_CONCAT(DISTINCT di.pin_path) AS pin_paths,
                (SELECT COUNT(*) FROM defect_comments dc WHERE dc.defect_id = d.id) AS comment_count,
                rej_user.username AS rejected_by_user,
                reo_user.username AS reopened_by_user,
                acc_user.username AS accepted_by_user
              FROM defects d
              LEFT JOIN contractors c ON d.assigned_to = c.id
              LEFT JOIN projects p ON d.project_id = p.id
              LEFT JOIN users u ON d.created_by = u.id
              LEFT JOIN defect_images di ON d.id = di.defect_id
              LEFT JOIN users rej_user ON d.rejected_by = rej_user.id
              LEFT JOIN users reo_user ON d.reopened_by = reo_user.id
              LEFT JOIN users acc_user ON d.accepted_by = acc_user.id
              WHERE {$whereSql}
              GROUP BY d.id
              ORDER BY d.updated_at DESC
              LIMIT {$perPage} OFFSET {$offset}";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $defects = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $countQuery = "SELECT COUNT(DISTINCT d.id) AS total
                   FROM defects d
                   LEFT JOIN contractors c ON d.assigned_to = c.id
                   LEFT JOIN projects p ON d.project_id = p.id
                   LEFT JOIN users u ON d.created_by = u.id
                   LEFT JOIN defect_images di ON d.id = di.defect_id
                   WHERE {$whereSql}";

    $countStmt = $db->prepare($countQuery);
    $countStmt->execute($params);
    $totalRecords = (int) ($countStmt->fetchColumn() ?? 0);
    $totalPages = $totalRecords > 0 ? (int) ceil($totalRecords / $perPage) : 0;

    if ($totalRecords > 0) {
        $startRecord = $offset + 1;
        $endRecord = min($offset + count($defects), $totalRecords);
    }

    $metricsQuery = "SELECT
                        COUNT(DISTINCT d.id) AS total_defects,
                        SUM(CASE WHEN d.status IN ('open', 'pending', 'in_progress') THEN 1 ELSE 0 END) AS active_defects,
                        SUM(CASE WHEN d.priority = 'critical' THEN 1 ELSE 0 END) AS critical_defects,
                        SUM(CASE WHEN d.due_date IS NOT NULL AND d.due_date < CURRENT_DATE() AND d.status NOT IN ('closed', 'accepted', 'resolved', 'verified') THEN 1 ELSE 0 END) AS overdue_defects,
                        SUM(CASE WHEN d.status = 'rejected' THEN 1 ELSE 0 END) AS rejected_defects,
                        SUM(CASE WHEN d.status IN ('closed', 'accepted', 'resolved', 'verified') THEN 1 ELSE 0 END) AS closed_defects,
                        COUNT(DISTINCT d.project_id) AS project_count,
                        COUNT(DISTINCT d.assigned_to) AS contractor_count,
                        MAX(d.updated_at) AS last_update
                     FROM defects d
                     LEFT JOIN contractors c ON d.assigned_to = c.id
                     LEFT JOIN projects p ON d.project_id = p.id
                     WHERE {$whereSql}";

    $metricsStmt = $db->prepare($metricsQuery);
    $metricsStmt->execute($params);
    $metrics = $metricsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $totalDefects = (int) ($metrics['total_defects'] ?? 0);
    $activeDefects = (int) ($metrics['active_defects'] ?? 0);
    $criticalDefects = (int) ($metrics['critical_defects'] ?? 0);
    $overdueDefects = (int) ($metrics['overdue_defects'] ?? 0);
    $rejectedDefects = (int) ($metrics['rejected_defects'] ?? 0);
    $closedDefects = (int) ($metrics['closed_defects'] ?? 0);
    $projectCount = (int) ($metrics['project_count'] ?? 0);
    $contractorCount = (int) ($metrics['contractor_count'] ?? 0);

    if (!empty($metrics['last_update'])) {
        $lastUpdateDisplay = date('d M Y, H:i', strtotime($metrics['last_update'])) . ' UK';
    }

    $statusQuery = "SELECT DISTINCT status FROM defects WHERE deleted_at IS NULL ORDER BY status";
    $priorityQuery = "SELECT DISTINCT priority FROM defects WHERE deleted_at IS NULL ORDER BY priority";
    $contractorQuery = "SELECT id, company_name FROM contractors WHERE status = 'active' ORDER BY company_name";
    $projectQuery = "SELECT id, name FROM projects WHERE status = 'active' ORDER BY name";

    $statuses = $db->query($statusQuery)->fetchAll(PDO::FETCH_COLUMN) ?: [];
    $priorities = $db->query($priorityQuery)->fetchAll(PDO::FETCH_COLUMN) ?: [];
    $contractors = $db->query($contractorQuery)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $projects = $db->query($projectQuery)->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {
    error_log('Defects Error: ' . $e->getMessage());
    error_log('Session Debug for user ' . $currentUser . ":\n" . print_r($_SESSION, true));
    $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit();
}

function correctDefectImagePath(string $path): string
{
    return (strpos($path, 'uploads/defects/') === 0)
        ? BASE_URL . $path
        : BASE_URL . 'uploads/defects/' . ltrim($path, '/');
}

function correctPinImagePath(string $path): string
{
    return (strpos($path, 'uploads/defects/') === 0)
        ? BASE_URL . $path
        : BASE_URL . 'uploads/defects/' . ltrim($path, '/');
}

function correctContractorLogoPath(?string $path): string
{
    return !empty($path)
        ? BASE_URL . 'uploads/logos/' . ltrim($path, '/')
        : BASE_URL . 'assets/icons/company-placeholder.png';
}

$filtersApplied = $statusFilter !== 'all'
    || $priorityFilter !== 'all'
    || $contractorFilter !== 'all'
    || $projectFilter !== 'all'
    || !empty($dateAddedFilter)
    || !empty($searchTerm);

$filterSummaryParts = [];

if ($statusFilter !== 'all') {
    $filterSummaryParts[] = 'Status: ' . ucwords(str_replace('_', ' ', $statusFilter));
}

if ($priorityFilter !== 'all') {
    $filterSummaryParts[] = 'Priority: ' . ucfirst($priorityFilter);
}

if ($contractorFilter !== 'all') {
    $contractorName = '';
    foreach ($contractors as $contractor) {
        if ((int) $contractor['id'] === (int) $contractorFilter) {
            $contractorName = $contractor['company_name'];
            break;
        }
    }
    if (!empty($contractorName)) {
        $filterSummaryParts[] = 'Contractor: ' . $contractorName;
    }
}

if ($projectFilter !== 'all') {
    $projectName = '';
    foreach ($projects as $project) {
        if ((int) $project['id'] === (int) $projectFilter) {
            $projectName = $project['name'];
            break;
        }
    }
    if (!empty($projectName)) {
        $filterSummaryParts[] = 'Project: ' . $projectName;
    }
}

if (!empty($dateAddedFilter)) {
    $filterSummaryParts[] = 'Date: ' . date('d M Y', strtotime($dateAddedFilter));
}

if (!empty($searchTerm)) {
    $filterSummaryParts[] = 'Search: "' . $searchTerm . '"';
}

$filterSummary = $filtersApplied ? implode(' • ', $filterSummaryParts) : 'Showing all active defects';

$defectMetrics = [
    [
        'title' => 'Total Defects',
        'subtitle' => 'Current scope',
        'icon' => 'bx-list-ul',
        'class' => 'report-metric-card--total',
        'value' => $totalDefects,
        'description' => 'All matching records',
        'description_icon' => 'bx-layer',
    ],
    [
        'title' => 'Active Defects',
        'subtitle' => 'Open & in progress',
        'icon' => 'bx-bolt-circle',
        'class' => 'report-metric-card--open',
        'value' => $activeDefects,
        'description' => 'Needs action',
        'description_icon' => 'bx-time-five',
    ],
    [
        'title' => 'Critical Priority',
        'subtitle' => 'Highest urgency',
        'icon' => 'bx-error',
        'class' => 'report-metric-card--critical',
        'value' => $criticalDefects,
        'description' => 'Priority: Critical',
        'description_icon' => 'bx-radar',
    ],
    [
        'title' => 'Overdue Defects',
        'subtitle' => 'Past target dates',
        'icon' => 'bx-timer',
        'class' => 'report-metric-card--overdue',
        'value' => $overdueDefects,
        'description' => 'Requires escalation',
        'description_icon' => 'bx-alarm-exclamation',
    ],
    [
        'title' => 'Closed & Resolved',
        'subtitle' => 'Verified fixes',
        'icon' => 'bx-check-circle',
        'class' => 'report-metric-card--closed',
        'value' => $closedDefects,
        'description' => 'Accepted or resolved',
        'description_icon' => 'bx-badge-check',
    ],
];

$exportQueryString = http_build_query($_GET);
$pdfReportUrl = BASE_URL . 'pdf_exports/export-pdf-defects-report-filtered.php';
if (!empty($exportQueryString)) {
    $pdfReportUrl .= '?' . $exportQueryString;
}

$statusBadgeMap = [
    'open' => 'badge rounded-pill bg-warning-subtle text-warning-emphasis',
    'pending' => 'badge rounded-pill bg-warning-subtle text-warning-emphasis',
    'in_progress' => 'badge rounded-pill bg-info-subtle text-info-emphasis',
    'completed' => 'badge rounded-pill bg-success-subtle text-success-emphasis',
    'verified' => 'badge rounded-pill bg-success-subtle text-success-emphasis',
    'accepted' => 'badge rounded-pill bg-success-subtle text-success-emphasis',
    'resolved' => 'badge rounded-pill bg-success-subtle text-success-emphasis',
    'closed' => 'badge rounded-pill bg-success-subtle text-success-emphasis',
    'rejected' => 'badge rounded-pill bg-danger-subtle text-danger-emphasis',
];

$priorityBadgeMap = [
    'critical' => 'badge rounded-pill bg-danger-subtle text-danger-emphasis',
    'high' => 'badge rounded-pill bg-warning-subtle text-warning-emphasis',
    'medium' => 'badge rounded-pill bg-primary-subtle text-primary-emphasis',
    'low' => 'badge rounded-pill bg-secondary-subtle text-secondary-emphasis',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Defects register - Defect Tracker">
    <meta name="author" content="<?php echo htmlspecialchars($currentUser ?? 'Unknown', ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="last-modified" content="<?php echo htmlspecialchars($currentDateTimeIso, ENT_QUOTES, 'UTF-8'); ?>">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?> - Defect Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="/favicons/favicon-96x96.png" sizes="96x96">
    <link rel="icon" type="image/svg+xml" href="/favicons/favicon.svg">
    <link rel="shortcut icon" href="/favicons/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png">
    <link rel="manifest" href="/favicons/site.webmanifest">
    <link href="css/app.css" rel="stylesheet">
</head>
<body class="tool-body" data-bs-theme="dark">
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top no-print">
        <div class="container-xl">
            <a class="navbar-brand fw-semibold" href="defects.php">
                <i class='bx bx-bug-alt me-2'></i>Defect Operations Centre
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#defectsNavbar" aria-controls="defectsNavbar" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="defectsNavbar">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php"><i class='bx bx-doughnut-chart me-1'></i>Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="defects.php"><i class='bx bx-bug me-1'></i>Defects</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php"><i class='bx bx-bar-chart-alt-2 me-1'></i>Reports</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="my_tasks.php"><i class='bx bx-list-check me-1'></i>My Tasks</a>
                    </li>
                    <?php if (($_SESSION['user_type'] ?? '') === 'admin'): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="adminOpsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class='bx bx-dial me-1'></i>Admin Ops
                        </a>
                        <div class="dropdown-menu dropdown-menu-dark shadow" aria-labelledby="adminOpsDropdown">
                            <h6 class="dropdown-header text-uppercase text-muted small">Defect Operations</h6>
                            <a class="dropdown-item" href="defects.php"><i class='bx bx-bug me-1'></i>Defect Control Room</a>
                            <a class="dropdown-item" href="create_defect.php"><i class='bx bx-plus-circle me-1'></i>Create Defect</a>
                            <a class="dropdown-item" href="assign_to_user.php"><i class='bx bx-transfer-alt me-1'></i>Assign Defects</a>
                            <a class="dropdown-item" href="upload_completed_images.php"><i class='bx bx-upload me-1'></i>Completion Evidence</a>
                            <div class="dropdown-divider"></div>
                            <h6 class="dropdown-header text-uppercase text-muted small">Directory</h6>
                            <a class="dropdown-item" href="user_management.php"><i class='bx bx-group me-1'></i>User Management</a>
                            <a class="dropdown-item" href="add_user.php"><i class='bx bx-user-plus me-1'></i>Add User</a>
                            <a class="dropdown-item" href="contractors.php"><i class='bx bx-hard-hat me-1'></i>Contractors</a>
                            <div class="dropdown-divider"></div>
                            <h6 class="dropdown-header text-uppercase text-muted small">System</h6>
                            <a class="dropdown-item" href="admin.php"><i class='bx bx-command me-1'></i>Admin Console</a>
                            <a class="dropdown-item" href="maintenance/maintenance.php"><i class='bx bx-wrench me-1'></i>Maintenance Planner</a>
                            <a class="dropdown-item" href="backup_manager.php"><i class='bx bx-shield-quarter me-1'></i>Backup Manager</a>
                            <a class="dropdown-item" href="system-tools/system_health.php"><i class='bx bx-pulse me-1'></i>System Health</a>
                            <a class="dropdown-item" href="user_logs.php"><i class='bx bx-notepad me-1'></i>User Logs</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="help_index.php"><i class='bx bx-help-circle me-1'></i>Help Centre</a>
                        </div>
                    </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-3">
                    <li class="nav-item text-muted small d-none d-lg-flex align-items-center">
                        <i class='bx bx-time-five me-1'></i><span data-report-time><?php echo htmlspecialchars($currentTimestamp, ENT_QUOTES, 'UTF-8'); ?></span> UK
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="defectsUserMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class='bx bx-user-circle me-1'></i><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="defectsUserMenu">
                            <li><a class="dropdown-item" href="profile.php"><i class='bx bx-user'></i> Profile</a></li>
                            <li><a class="dropdown-item" href="my_tasks.php"><i class='bx bx-list-check'></i> My Tasks</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class='bx bx-log-out'></i> Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div class="text-muted small">
                <?php if ($totalRecords > 0): ?>
                    Showing <?php echo number_format($startRecord); ?>–<?php echo number_format($endRecord); ?> of <?php echo number_format($totalRecords); ?> results
                <?php else: ?>
                    No defects found for the current filters
                <?php endif; ?>
                <?php if (!empty($lastUpdateDisplay) && $lastUpdateDisplay !== 'No updates recorded'): ?>
                    <span class="ms-2"><i class='bx bx-refresh me-1'></i>Last update <?php echo htmlspecialchars($lastUpdateDisplay, ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endif; ?>
            </div>
            <div class="d-flex flex-wrap gap-2 no-print">
                <?php if ($canEdit): ?>
                <a href="<?php echo BASE_URL; ?>create_defect.php" class="btn btn-sm btn-primary">
                    <i class='bx bx-plus-circle'></i> New Defect
                </a>
                <?php endif; ?>
            </div>
        </div>

        <section class="mb-5">
            <div class="report-metrics-grid">
                <?php foreach ($defectMetrics as $metric): ?>
                    <article class="report-metric-card <?php echo htmlspecialchars($metric['class'], ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="report-metric-card__icon">
                            <i class='bx <?php echo htmlspecialchars($metric['icon'], ENT_QUOTES, 'UTF-8'); ?>'></i>
                        </div>
                        <div class="report-metric-card__content">
                            <h3 class="report-metric-card__title"><?php echo htmlspecialchars($metric['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                            <?php if (!empty($metric['subtitle'])): ?>
                                <p class="report-metric-card__subtitle mb-2"><?php echo htmlspecialchars($metric['subtitle'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <?php endif; ?>
                            <p class="report-metric-card__value mb-1"><?php echo number_format((int) $metric['value']); ?></p>
                            <p class="report-metric-card__description mb-0">
                                <i class='bx <?php echo htmlspecialchars($metric['description_icon'], ENT_QUOTES, 'UTF-8'); ?>'></i>
                                <span><?php echo htmlspecialchars($metric['description'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </p>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="mb-5">
            <div class="row g-4 align-items-stretch">
                <div class="col-xl-5">
                    <article class="system-tool-card h-100">
                        <div class="system-tool-card__icon">
                            <i class='bx bx-pulse'></i>
                        </div>
                        <div class="system-tool-card__body">
                            <span class="system-tool-card__tag system-tool-card__tag--insight">Live overview</span>
                            <h2 class="system-tool-card__title">Portfolio Snapshot</h2>
                            <p class="system-tool-card__description">Where the workload sits across projects and delivery partners.</p>
                            <span class="system-tool-card__stat"><?php echo number_format($activeDefects); ?></span>
                            <p class="text-muted small mb-3">Currently open or in progress.</p>
                            <ul class="list-unstyled text-muted small mb-0 d-flex flex-column gap-1">
                                <li><i class='bx bx-building-house me-1'></i><?php echo number_format($projectCount); ?> projects engaged</li>
                                <li><i class='bx bx-user-voice me-1'></i><?php echo number_format($contractorCount); ?> contractors assigned</li>
                                <li><i class='bx bx-refresh me-1'></i><?php echo htmlspecialchars($lastUpdateDisplay, ENT_QUOTES, 'UTF-8'); ?></li>
                            </ul>
                        </div>
                    </article>
                </div>
                <div class="col-xl-7">
                    <div class="system-callout system-callout--info h-100" role="status">
                        <div class="system-callout__icon"><i class='bx bx-target-lock'></i></div>
                        <div>
                            <h2 class="system-callout__title">Triage Focus</h2>
                            <p class="system-callout__body mb-3">Direct teams to the high-impact items demanding attention right now.</p>
                            <div class="d-flex flex-wrap gap-3 text-muted small mb-0">
                                <span><i class='bx bx-error me-1'></i><?php echo number_format($criticalDefects); ?> critical</span>
                                <span><i class='bx bx-timer me-1'></i><?php echo number_format($overdueDefects); ?> overdue</span>
                                <span><i class='bx bx-x-circle me-1'></i><?php echo number_format($rejectedDefects); ?> rejected</span>
                                <span><i class='bx bx-check-circle me-1'></i><?php echo number_format($closedDefects); ?> resolved</span>
                            </div>
                            <p class="text-muted small mb-0 mt-3"><i class='bx bx-slider-alt me-1'></i><?php echo htmlspecialchars($filterSummary, ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="filter-panel no-print mb-5">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                <div>
                    <h2 class="h6 mb-1 text-uppercase text-muted">Filters</h2>
                    <p class="text-muted small mb-0">Use the controls to narrow down the register in real time.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-sm btn-outline-light" href="defects.php"><i class='bx bx-reset'></i> Reset</a>
                    <a class="btn btn-sm btn-outline-light" href="<?php echo BASE_URL; ?>pdf_exports/export-pdf-defects-report-filtered.php?<?php echo htmlspecialchars($exportQueryString, ENT_QUOTES, 'UTF-8'); ?>" target="_blank"><i class='bx bxs-file-pdf'></i> PDF Report</a>
                </div>
            </div>
            <?php if ($filtersApplied && !empty($filterSummaryParts)): ?>
                <div class="filter-panel__summary d-flex flex-wrap gap-2 mb-3">
                    <?php foreach ($filterSummaryParts as $summaryPart): ?>
                        <span><?php echo htmlspecialchars($summaryPart, ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <form method="GET" class="filter-panel__form">
                <div class="row g-3">
                    <div class="col-12 col-sm-6 col-lg-4 col-xxl-3">
                        <label class="form-label">Project</label>
                        <select name="project" class="form-select">
                            <option value="all">All Projects</option>
                            <?php foreach ($projects as $project): ?>
                                <option value="<?php echo (int) $project['id']; ?>" <?php echo ((int) $projectFilter === (int) $project['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($project['name'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-4 col-xxl-3">
                        <label class="form-label">Contractor</label>
                        <select name="contractor" class="form-select">
                            <option value="all">All Contractors</option>
                            <?php foreach ($contractors as $contractor): ?>
                                <option value="<?php echo (int) $contractor['id']; ?>" <?php echo ((int) $contractorFilter === (int) $contractor['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($contractor['company_name'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-sm-6 col-lg-2 col-xxl-2">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="all">All Status</option>
                            <?php foreach ($statuses as $status): ?>
                                <option value="<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($statusFilter === $status) ? 'selected' : ''; ?>>
                                    <?php echo ucwords(str_replace('_', ' ', $status)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-sm-6 col-lg-2 col-xxl-2">
                        <label class="form-label">Priority</label>
                        <select name="priority" class="form-select">
                            <option value="all">All Priorities</option>
                            <option value="overdue" <?php echo ($priorityFilter === 'overdue') ? 'selected' : ''; ?>>Overdue</option>
                            <?php foreach ($priorities as $priority): ?>
                                <option value="<?php echo htmlspecialchars($priority, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($priorityFilter === $priority) ? 'selected' : ''; ?>>
                                    <?php echo ucfirst($priority); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-sm-6 col-lg-2 col-xxl-2">
                        <label class="form-label">Date Added</label>
                        <input type="date" name="date_added" class="form-control" value="<?php echo htmlspecialchars($dateAddedFilter, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-12 col-sm-6 col-lg-4 col-xxl-3">
                        <label class="form-label">Search</label>
                        <div class="input-group">
                            <input type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search defects...">
                            <button type="submit" class="btn btn-outline-light"><i class='bx bx-search'></i></button>
                        </div>
                    </div>
                </div>
                <div class="filter-panel__actions mt-3">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class='bx bx-filter-alt'></i> Apply Filters
                    </button>
                </div>
            </form>
        </section>

        <section class="mb-5">
            <div class="card border-0">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <h2 class="h5 mb-1"><i class='bx bx-grid-alt me-2'></i>Defect Register</h2>
                        <p class="text-muted small mb-0">Latest entries ordered by recent activity and updates.</p>
                    </div>
                    <?php if ($totalPages > 1): ?>
                    <div class="text-muted small">Page <?php echo number_format($page); ?> of <?php echo number_format($totalPages); ?></div>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (empty($defects)): ?>
                        <div class="system-callout system-callout--info" role="status">
                            <div class="system-callout__icon"><i class='bx bx-search-alt-2'></i></div>
                            <div>
                                <h2 class="system-callout__title">No Defects Found</h2>
                                <p class="system-callout__body mb-0">Adjust your filters or reset to view the full defect log.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive defect-table">
                            <table class="table table-dark table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th scope="col">ID</th>
                                        <th scope="col">Project</th>
                                        <th scope="col">Contractor</th>
                                        <th scope="col" class="text-center">Status</th>
                                        <th scope="col" class="text-center">Priority</th>
                                        <th scope="col">Created</th>
                                        <th scope="col" class="text-center no-print">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($defects as $defect): ?>
                                        <?php
                                            $statusKey = strtolower($defect['status'] ?? '');
                                            $priorityKey = strtolower($defect['priority'] ?? '');
                                            $statusBadgeClass = $statusBadgeMap[$statusKey] ?? 'badge rounded-pill bg-secondary-subtle text-secondary-emphasis';
                                            $priorityBadgeClass = $priorityBadgeMap[$priorityKey] ?? 'badge rounded-pill bg-secondary-subtle text-secondary-emphasis';
                                            $createdDisplay = date('d M Y, H:i', strtotime($defect['created_at']));
                                            $createdBy = $defect['created_by_user'] ?? 'Unknown';
                                            $isOverdue = !empty($defect['due_date']) ? isDueDateOverdue($defect['due_date']) : false;
                                            $dueDisplay = !empty($defect['due_date']) ? date('d M Y', strtotime($defect['due_date'])) : null;
                                            $dueBadgeClass = $isOverdue ? 'badge rounded-pill bg-danger-subtle text-danger-emphasis' : 'badge rounded-pill bg-success-subtle text-success-emphasis';
                                        ?>
                                        <tr>
                                            <td class="fw-semibold">#<?php echo (int) $defect['id']; ?></td>
                                            <td>
                                                <span class="fw-semibold d-block"><?php echo htmlspecialchars($defect['project_name'] ?? 'Unassigned', ENT_QUOTES, 'UTF-8'); ?></span>
                                                <span class="text-muted small">Ref: <?php echo htmlspecialchars($defect['title'] ?? 'Untitled', ENT_QUOTES, 'UTF-8'); ?></span>
                                            </td>
                                            <td>
                                                <div class="defect-table__contractor">
                                                    <?php if (!empty($defect['contractor_logo'])): ?>
                                                        <img src="<?php echo htmlspecialchars(correctContractorLogoPath($defect['contractor_logo']), ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($defect['contractor_name'] ?? 'Contractor', ENT_QUOTES, 'UTF-8'); ?> Logo" class="defect-table__contractor-logo">
                                                    <?php endif; ?>
                                                    <div>
                                                        <span class="fw-semibold d-block"><?php echo htmlspecialchars($defect['contractor_name'] ?? 'Unassigned', ENT_QUOTES, 'UTF-8'); ?></span>
                                                        <?php if (!empty($defect['assigned_to'])): ?>
                                                            <span class="text-muted small">ID: <?php echo (int) $defect['assigned_to']; ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-center">
                                                <span class="<?php echo $statusBadgeClass; ?>"><?php echo ucwords(str_replace('_', ' ', $defect['status'] ?? 'Unknown')); ?></span>
                                            </td>
                                            <td class="text-center">
                                                <div class="d-flex flex-column align-items-center gap-1">
                                                    <span class="<?php echo $priorityBadgeClass; ?>"><?php echo ucfirst($priorityKey ?: 'n/a'); ?></span>
                                                    <?php if ($dueDisplay): ?>
                                                        <span class="<?php echo $dueBadgeClass; ?>"><i class='bx bx-calendar me-1'></i><?php echo $dueDisplay; ?></span>
                                                    <?php else: ?>
                                                        <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis">No due date</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="d-block"><?php echo htmlspecialchars($createdDisplay, ENT_QUOTES, 'UTF-8'); ?></span>
                                                <span class="text-muted small">by <?php echo htmlspecialchars($createdBy, ENT_QUOTES, 'UTF-8'); ?></span>
                                            </td>
                                            <td class="text-center no-print">
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-sm btn-outline-light" data-bs-toggle="modal" data-bs-target="#viewDefectModal<?php echo (int) $defect['id']; ?>">
                                                        <i class='bx bx-show-alt'></i> View
                                                    </button>
                                                    <?php if ($canEdit): ?>
                                                    <a href="<?php echo htmlspecialchars(BASE_URL . 'edit_defect.php?id=' . $defect['id'], ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-outline-light">
                                                        <i class='bx bx-edit'></i> Edit
                                                    </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($totalPages > 1): ?>
                        <nav aria-label="Defects pagination" class="mt-4">
                            <ul class="pagination pagination-sm justify-content-center">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&amp;status=<?php echo urlencode($statusFilter); ?>&amp;priority=<?php echo urlencode($priorityFilter); ?>&amp;contractor=<?php echo urlencode($contractorFilter); ?>&amp;project=<?php echo urlencode($projectFilter); ?>&amp;date_added=<?php echo urlencode($dateAddedFilter); ?>&amp;search=<?php echo urlencode($searchTerm); ?>" tabindex="-1">Previous</a>
                                </li>
                                <?php
                                $startPage = max(1, min($page - 2, $totalPages - 4));
                                $endPage = min($totalPages, max(5, $page + 2));
                                if ($startPage > 1) {
                                    echo '<li class="page-item"><a class="page-link" href="?page=1&status=' . urlencode($statusFilter) . '&priority=' . urlencode($priorityFilter) . '&contractor=' . urlencode($contractorFilter) . '&project=' . urlencode($projectFilter) . '&date_added=' . urlencode($dateAddedFilter) . '&search=' . urlencode($searchTerm) . '">1</a></li>';
                                    if ($startPage > 2) {
                                        echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                                    }
                                }
                                for ($i = $startPage; $i <= $endPage; $i++) {
                                    $activeClass = ($page === $i) ? ' active' : '';
                                    echo '<li class="page-item' . $activeClass . '"><a class="page-link" href="?page=' . $i . '&status=' . urlencode($statusFilter) . '&priority=' . urlencode($priorityFilter) . '&contractor=' . urlencode($contractorFilter) . '&project=' . urlencode($projectFilter) . '&date_added=' . urlencode($dateAddedFilter) . '&search=' . urlencode($searchTerm) . '">' . $i . '</a></li>';
                                }
                                if ($endPage < $totalPages) {
                                    if ($endPage < $totalPages - 1) {
                                        echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                                    }
                                    echo '<li class="page-item"><a class="page-link" href="?page=' . $totalPages . '&status=' . urlencode($statusFilter) . '&priority=' . urlencode($priorityFilter) . '&contractor=' . urlencode($contractorFilter) . '&project=' . urlencode($projectFilter) . '&date_added=' . urlencode($dateAddedFilter) . '&search=' . urlencode($searchTerm) . '">' . $totalPages . '</a></li>';
                                }
                                ?>
                                <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&amp;status=<?php echo urlencode($statusFilter); ?>&amp;priority=<?php echo urlencode($priorityFilter); ?>&amp;contractor=<?php echo urlencode($contractorFilter); ?>&amp;project=<?php echo urlencode($projectFilter); ?>&amp;date_added=<?php echo urlencode($dateAddedFilter); ?>&amp;search=<?php echo urlencode($searchTerm); ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <div class="text-muted small text-end mt-4">
            <i class='bx bx-time-five me-1'></i>Rendered <?php echo htmlspecialchars($currentTimestamp, ENT_QUOTES, 'UTF-8'); ?> UK • User <?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    </main>

    <?php if (is_array($defects) && !empty($defects)): ?>
        <?php foreach ($defects as $defect): ?>
            <?php
                $statusKey = strtolower($defect['status'] ?? '');
                $priorityKey = strtolower($defect['priority'] ?? '');
                $statusBadgeClass = $statusBadgeMap[$statusKey] ?? 'badge rounded-pill bg-secondary-subtle text-secondary-emphasis';
                $priorityBadgeClass = $priorityBadgeMap[$priorityKey] ?? 'badge rounded-pill bg-secondary-subtle text-secondary-emphasis';
                $dueDate = $defect['due_date'] ?? null;
                $isOverdue = $dueDate ? isDueDateOverdue($dueDate) : false;
            ?>
            <div class="modal fade" id="viewDefectModal<?php echo (int) $defect['id']; ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Defect #<?php echo (int) $defect['id']; ?> Details</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <?php if ($canEdit && in_array($defect['status'], ['open', 'pending', 'rejected', 'accepted'], true)): ?>
                                <div class="mb-4">
                                    <div class="row g-2">
                                        <div class="col-12 col-md-4">
                                            <button type="button" class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#acceptDefectModal<?php echo (int) $defect['id']; ?>">
                                                <i class='bx bx-check-circle me-1'></i> Accept
                                            </button>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <button type="button" class="btn btn-danger w-100" data-bs-toggle="modal" data-bs-target="#rejectDefectModal<?php echo (int) $defect['id']; ?>">
                                                <i class='bx bx-x-circle me-1'></i> Reject
                                            </button>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <button type="button" class="btn btn-info w-100" data-bs-toggle="modal" data-bs-target="#reopenDefectModal<?php echo (int) $defect['id']; ?>">
                                                <i class='bx bx-refresh me-1'></i> Reopen
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div class="row g-4">
                                <div class="col-lg-8">
                                    <h5><?php echo htmlspecialchars($defect['title'] ?? 'Untitled', ENT_QUOTES, 'UTF-8'); ?></h5>
                                    <p class="text-muted"><?php echo nl2br(htmlspecialchars($defect['description'] ?? '', ENT_QUOTES, 'UTF-8')); ?></p>

                                    <?php if (($defect['status'] ?? '') === 'rejected'): ?>
                                        <div class="system-callout system-callout--danger mb-3">
                                            <div class="system-callout__icon"><i class='bx bx-error-circle'></i></div>
                                            <div>
                                                <h3 class="system-callout__title">Rejection Reason</h3>
                                                <p class="system-callout__body mb-2"><?php echo nl2br(htmlspecialchars($defect['rejection_comment'] ?? '', ENT_QUOTES, 'UTF-8')); ?></p>
                                                <p class="text-muted small mb-0">Rejected by <?php echo htmlspecialchars($defect['rejected_by_user'] ?? 'Unknown', ENT_QUOTES, 'UTF-8'); ?> on <?php echo date('d M Y, H:i', strtotime($defect['updated_at'] ?? 'now')); ?></p>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (($defect['status'] ?? '') === 'accepted'): ?>
                                        <div class="system-callout system-callout--success mb-3">
                                            <div class="system-callout__icon"><i class='bx bx-check-shield'></i></div>
                                            <div>
                                                <h3 class="system-callout__title">Acceptance Comment</h3>
                                                <p class="system-callout__body mb-2"><?php echo nl2br(htmlspecialchars($defect['acceptance_comment'] ?? '', ENT_QUOTES, 'UTF-8')); ?></p>
                                                <p class="text-muted small mb-0">Accepted by <?php echo htmlspecialchars($defect['accepted_by_user'] ?? 'Unknown', ENT_QUOTES, 'UTF-8'); ?> on <?php echo date('d M Y, H:i', strtotime($defect['accepted_at'] ?? 'now')); ?></p>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($defect['reopened_reason'])): ?>
                                        <div class="system-callout system-callout--info mb-3">
                                            <div class="system-callout__icon"><i class='bx bx-refresh'></i></div>
                                            <div>
                                                <h3 class="system-callout__title">Reason for Reopening</h3>
                                                <p class="system-callout__body mb-2"><?php echo nl2br(htmlspecialchars($defect['reopened_reason'] ?? '', ENT_QUOTES, 'UTF-8')); ?></p>
                                                <p class="text-muted small mb-0">Reopened by <?php echo htmlspecialchars($defect['reopened_by_user'] ?? 'Unknown', ENT_QUOTES, 'UTF-8'); ?> on <?php echo date('d M Y, H:i', strtotime($defect['reopened_at'] ?? 'now')); ?></p>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($defect['pin_paths'])):
                                        $pinPaths = array_filter(array_map('trim', explode(',', (string) $defect['pin_paths'])));
                                        foreach ($pinPaths as $pinPath):
                                            if (stripos($pinPath, 'floorplan_with_pin_defect.png') !== false) {
                                                continue;
                                            }
                                    ?>
                                        <div class="mb-3">
                                            <span class="text-muted small d-block mb-2">Pin Image</span>
                                            <div class="ratio ratio-16x9">
                                                <img src="<?php echo htmlspecialchars(correctPinImagePath($pinPath), ENT_QUOTES, 'UTF-8'); ?>" alt="Pin Image" class="object-fit-contain rounded-3 border border-secondary-subtle p-2 zoomable-image">
                                            </div>
                                        </div>
                                    <?php endforeach; endif; ?>

                                    <?php if (!empty($defect['image_paths'])):
                                        $imagePaths = array_filter(array_map('trim', explode(',', (string) $defect['image_paths'])));
                                        foreach ($imagePaths as $imagePath): ?>
                                            <div class="mb-3">
                                                <span class="text-muted small d-block mb-2">Attachment</span>
                                                <div class="ratio ratio-16x9">
                                                    <img src="<?php echo htmlspecialchars(correctDefectImagePath($imagePath), ENT_QUOTES, 'UTF-8'); ?>" alt="Attachment" class="object-fit-contain rounded-3 border border-secondary-subtle p-2 zoomable-image">
                                                </div>
                                            </div>
                                        <?php endforeach;
                                    endif; ?>
                                </div>
                                <div class="col-lg-4">
                                    <div class="card bg-dark border-0 shadow-sm">
                                        <div class="card-body">
                                            <dl class="row mb-0 small text-muted">
                                                <dt class="col-5">Status</dt>
                                                <dd class="col-7">
                                                    <span class="<?php echo $statusBadgeClass; ?>"><?php echo ucwords(str_replace('_', ' ', $defect['status'] ?? 'Unknown')); ?></span>
                                                </dd>
                                                <dt class="col-5">Priority</dt>
                                                <dd class="col-7">
                                                    <span class="<?php echo $priorityBadgeClass; ?>"><?php echo ucfirst($priorityKey ?: 'n/a'); ?></span>
                                                </dd>
                                                <dt class="col-5">Due Date</dt>
                                                <dd class="col-7">
                                                    <?php if ($dueDate): ?>
                                                        <span class="<?php echo $isOverdue ? 'text-danger fw-semibold' : 'text-success'; ?>">
                                                            <i class='bx bx-calendar me-1'></i><?php echo date('d M Y', strtotime($dueDate)); ?>
                                                            <?php if ($isOverdue): ?>
                                                                <span class="badge rounded-pill bg-danger-subtle text-danger-emphasis ms-1">Overdue</span>
                                                            <?php endif; ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not set</span>
                                                    <?php endif; ?>
                                                </dd>
                                                <dt class="col-5">Project</dt>
                                                <dd class="col-7"><?php echo htmlspecialchars($defect['project_name'] ?? 'Unassigned', ENT_QUOTES, 'UTF-8'); ?></dd>
                                                <dt class="col-5">Contractor</dt>
                                                <dd class="col-7">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <?php if (!empty($defect['contractor_logo'])): ?>
                                                            <img src="<?php echo htmlspecialchars(correctContractorLogoPath($defect['contractor_logo']), ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($defect['contractor_name'] ?? 'Contractor', ENT_QUOTES, 'UTF-8'); ?> Logo" class="defect-table__contractor-logo">
                                                        <?php endif; ?>
                                                        <span><?php echo htmlspecialchars($defect['contractor_name'] ?? 'Unassigned', ENT_QUOTES, 'UTF-8'); ?></span>
                                                    </div>
                                                </dd>
                                                <dt class="col-5">Created</dt>
                                                <dd class="col-7">
                                                    <?php echo date('d M Y, H:i', strtotime($defect['created_at'] ?? 'now')); ?><br>
                                                    <span class="text-muted">by <?php echo htmlspecialchars($defect['created_by_user'] ?? 'Unknown', ENT_QUOTES, 'UTF-8'); ?></span>
                                                </dd>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <a href="<?php echo BASE_URL; ?>pdf_exports/pdf-defect.php?defect_id=<?php echo (int) $defect['id']; ?>" class="btn btn-outline-light" target="_blank"><i class='bx bx-download me-1'></i> Download PDF</a>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <?php if ($canEdit): ?>
                                <a href="<?php echo htmlspecialchars(BASE_URL . 'edit_defect.php?id=' . $defect['id'], ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary">Edit Defect</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($canEdit && ($defect['status'] ?? '') !== 'accepted'): ?>
                <div class="modal fade" id="acceptDefectModal<?php echo (int) $defect['id']; ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="POST" action="<?php echo BASE_URL; ?>accept_defect.php" class="needs-validation" novalidate>
                                <input type="hidden" name="defect_id" value="<?php echo (int) $defect['id']; ?>">
                                <input type="hidden" name="action" value="accept_defect">
                                <div class="modal-header bg-success text-white">
                                    <h5 class="modal-title">Accept Defect #<?php echo (int) $defect['id']; ?></h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="form-label required">Acceptance Comment</label>
                                        <textarea name="acceptance_comment" class="form-control" rows="4" placeholder="Provide any additional comments about accepting this defect..." required></textarea>
                                        <div class="invalid-feedback">Please provide an acceptance comment.</div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-success"><i class='bx bx-check-circle me-1'></i> Confirm Acceptance</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($canEdit && ($defect['status'] ?? '') !== 'rejected'): ?>
                <div class="modal fade" id="rejectDefectModal<?php echo (int) $defect['id']; ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="POST" action="<?php echo BASE_URL; ?>reject_defect.php" class="needs-validation" novalidate>
                                <input type="hidden" name="defect_id" value="<?php echo (int) $defect['id']; ?>">
                                <input type="hidden" name="action" value="reject_defect">
                                <div class="modal-header bg-danger text-white">
                                    <h5 class="modal-title">Reject Defect #<?php echo (int) $defect['id']; ?></h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="form-label required">Reason for Rejection</label>
                                        <textarea name="rejection_comment" class="form-control" rows="4" placeholder="Provide a reason for rejecting this defect..." required></textarea>
                                        <div class="invalid-feedback">Please provide a reason for rejection.</div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-danger"><i class='bx bx-x-circle me-1'></i> Confirm Rejection</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($canEdit && in_array($defect['status'] ?? '', ['rejected', 'accepted'], true)): ?>
                <div class="modal fade" id="reopenDefectModal<?php echo (int) $defect['id']; ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="POST" action="<?php echo BASE_URL; ?>reopen_defect.php" class="needs-validation" novalidate>
                                <input type="hidden" name="defect_id" value="<?php echo (int) $defect['id']; ?>">
                                <input type="hidden" name="action" value="reopen_defect">
                                <div class="modal-header bg-info text-white">
                                    <h5 class="modal-title">Reopen Defect #<?php echo (int) $defect['id']; ?></h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="form-label required">Reason for Reopening</label>
                                        <textarea name="reopen_comment" class="form-control" rows="4" placeholder="Provide the reason for reopening this defect..." required></textarea>
                                        <div class="invalid-feedback">Please provide a reason for reopening.</div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-info"><i class='bx bx-refresh me-1'></i> Confirm Reopen</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.modal').forEach(function(modalElement) {
                modalElement.addEventListener('hidden.bs.modal', function () {
                    document.body.classList.remove('modal-open');
                    const modalBackdrops = document.getElementsByClassName('modal-backdrop');
                    while (modalBackdrops.length > 0) {
                        modalBackdrops[0].parentNode.removeChild(modalBackdrops[0]);
                    }
                });
            });

            const filterPanel = document.getElementById('defectFilters');
            const filterForm = filterPanel ? filterPanel.querySelector('.filter-panel__form') : document.querySelector('.filter-panel__form');

            if (filterPanel) {
                const panelBody = filterPanel.querySelector('.filter-panel__body');
                const toggleButton = filterPanel.querySelector('[data-action="toggle-filters"]');
                const toggleLabel = toggleButton ? toggleButton.querySelector('span') : null;
                const toggleIcon = toggleButton ? toggleButton.querySelector('i') : null;
                const pdfButton = filterPanel.querySelector('[data-action="open-pdf-report"]');

                if (panelBody) {
                    panelBody.hidden = false;
                }

                if (toggleButton && panelBody) {
                    const setToggleState = function(collapsed) {
                        filterPanel.classList.toggle('filter-panel--collapsed', collapsed);
                        panelBody.hidden = collapsed;
                        toggleButton.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                        if (toggleLabel) {
                            toggleLabel.textContent = collapsed ? 'Show filters' : 'Hide filters';
                        }
                        if (toggleIcon) {
                            toggleIcon.classList.toggle('bx-chevron-up', !collapsed);
                            toggleIcon.classList.toggle('bx-chevron-down', collapsed);
                        }
                    };

                    toggleButton.addEventListener('click', function() {
                        const collapsed = !filterPanel.classList.contains('filter-panel--collapsed');
                        setToggleState(collapsed);
                    });

                    if (window.matchMedia('(max-width: 991px)').matches) {
                        setToggleState(true);
                    }
                }

                if (pdfButton) {
                    pdfButton.addEventListener('click', function(event) {
                        event.preventDefault();
                        const reportUrl = pdfButton.getAttribute('data-report-url');
                        if (reportUrl) {
                            window.open(reportUrl, '_blank', 'noopener');
                        }
                    });
                }
            }

            if (filterForm) {
                const searchInput = filterForm.querySelector('input[name="search"]');
                let searchTimeout;
                if (searchInput) {
                    searchInput.addEventListener('input', function() {
                        clearTimeout(searchTimeout);
                        searchTimeout = setTimeout(function() {
                            filterForm.submit();
                        }, 500);
                    });
                }
            }

            <?php if (isset($_SESSION['success_message'])): ?>
                showNotification("<?php echo addslashes($_SESSION['success_message']); ?>", 'success');
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>
            <?php if (isset($_SESSION['error_message'])): ?>
                showNotification("<?php echo addslashes($_SESSION['error_message']); ?>", 'danger');
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            document.querySelectorAll('.zoomable-image').forEach(function(img) {
                img.addEventListener('click', function() {
                    this.classList.toggle('zoomed');
                });
            });
        });

        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
            notification.style.zIndex = '1050';
            notification.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            document.body.appendChild(notification);
            setTimeout(() => notification.remove(), 5000);
        }
    </script>
</body>
</html>

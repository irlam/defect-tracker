<?php
/**
 * my_tasks.php
 * Display the current user's assigned tasks with the System Tools theme.
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

define('INCLUDED', true);

require_once 'includes/functions.php';
require_once 'config/database.php';
require_once 'includes/navbar.php';

$pageTitle     = 'My Assigned Tasks';
$currentUser   = $_SESSION['username'];
$currentUserId = (int) $_SESSION['user_id'];

$displayName = ucwords(str_replace(['.', '_'], [' ', ' '], $currentUser ?? 'User'));
$currentUserRoleSummary = ucwords(str_replace(['_', '-'], [' ', ' '], $_SESSION['user_type'] ?? 'User'));

date_default_timezone_set('Europe/London');
$currentTimestamp = date('d/m/Y H:i');

$error_message = '';
$tasks = [];
$taskStats = [
    'total'       => 0,
    'open_tasks'  => 0,
    'in_progress' => 0,
    'completed'   => 0,
    'overdue'     => 0,
];

try {
    $database = new Database();
    $db = $database->getConnection();

    $query = "
        SELECT 
            d.id,
            d.title,
            d.description,
            d.status,
            d.priority,
            d.due_date,
            d.created_at,
            p.name AS project_name,
            c.company_name AS contractor_name
        FROM defects d
        LEFT JOIN defect_assignments da ON d.id = da.defect_id
        LEFT JOIN projects p ON d.project_id = p.id
        LEFT JOIN contractors c ON d.assigned_to = c.id
        WHERE da.user_id = :user_id
          AND da.status = 'active'
        ORDER BY 
            CASE 
                WHEN d.status = 'open' THEN 1
                WHEN d.status = 'in_progress' THEN 2
                ELSE 3
            END,
            CASE 
                WHEN d.priority = 'critical' THEN 1
                WHEN d.priority = 'high' THEN 2
                WHEN d.priority = 'medium' THEN 3
                ELSE 4
            END,
            d.due_date ASC
    ";

    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $currentUserId, PDO::PARAM_INT);
    $stmt->execute();
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $statsQuery = "
        SELECT 
            COALESCE(COUNT(*), 0) AS total,
            COALESCE(SUM(CASE WHEN d.status = 'open' THEN 1 ELSE 0 END), 0) AS open_tasks,
            COALESCE(SUM(CASE WHEN d.status = 'in_progress' THEN 1 ELSE 0 END), 0) AS in_progress,
            COALESCE(SUM(CASE WHEN d.status = 'closed' THEN 1 ELSE 0 END), 0) AS completed,
            COALESCE(SUM(CASE WHEN d.due_date < CURRENT_DATE AND d.status != 'closed' THEN 1 ELSE 0 END), 0) AS overdue
        FROM defects d
        LEFT JOIN defect_assignments da ON d.id = da.defect_id
        WHERE da.user_id = :user_id
          AND da.status = 'active'
    ";

    $statsStmt = $db->prepare($statsQuery);
    $statsStmt->bindParam(":user_id", $currentUserId, PDO::PARAM_INT);
    $statsStmt->execute();
    $statsResult = $statsStmt->fetch(PDO::FETCH_ASSOC);

    if ($statsResult) {
        $taskStats = array_merge($taskStats, $statsResult);
    }
} catch (Exception $e) {
    $error_message = 'Database error: ' . $e->getMessage();
    error_log("My Tasks Error: " . $e->getMessage());
}

$projectNames = [];
$nextDueDate = null;

foreach ($tasks as $task) {
    if (!empty($task['project_name'])) {
        $projectNames[] = $task['project_name'];
    }

    if (!empty($task['due_date']) && strtotime($task['due_date'])) {
        $dueDateObject = new DateTime($task['due_date']);
        if ($nextDueDate === null || $dueDateObject < $nextDueDate) {
            $nextDueDate = $dueDateObject;
        }
    }
}

$activeProjects = count(array_unique($projectNames));
$nextDueDateDisplay = $nextDueDate ? $nextDueDate->format('d M Y') : 'No scheduled due dates';
$completedTasks = (int) ($taskStats['completed'] ?? 0);
$overdueCount = (int) ($taskStats['overdue'] ?? 0);

$taskMetrics = [
    [
        'title' => 'Total Tasks',
        'subtitle' => 'Assigned to you',
        'icon' => 'bx-task',
        'class' => 'task-metric-card--total',
        'value' => (int) ($taskStats['total'] ?? 0),
        'description' => 'Across all statuses',
        'description_icon' => 'bx-layer',
    ],
    [
        'title' => 'Open Tasks',
        'subtitle' => 'Awaiting action',
        'icon' => 'bx-bell',
        'class' => 'task-metric-card--open',
        'value' => (int) ($taskStats['open_tasks'] ?? 0),
        'description' => 'Needs your attention',
        'description_icon' => 'bx-error-circle',
    ],
    [
        'title' => 'In Progress',
        'subtitle' => 'Actively moving',
        'icon' => 'bx-loader',
        'class' => 'task-metric-card--progress',
        'value' => (int) ($taskStats['in_progress'] ?? 0),
        'description' => 'Being worked right now',
        'description_icon' => 'bx-sync',
    ],
    [
        'title' => 'Overdue',
        'subtitle' => 'Requires escalation',
        'icon' => 'bx-timer',
        'class' => 'task-metric-card--overdue',
        'value' => (int) ($taskStats['overdue'] ?? 0),
        'description' => 'Past expected resolution',
        'description_icon' => 'bx-time-five',
    ],
];

$priorityBadgeMap = [
    'critical' => 'badge rounded-pill bg-danger-subtle text-danger-emphasis',
    'high'      => 'badge rounded-pill bg-warning-subtle text-warning-emphasis',
    'medium'    => 'badge rounded-pill bg-info-subtle text-info-emphasis',
    'low'       => 'badge rounded-pill bg-secondary-subtle text-secondary-emphasis',
];

$statusBadgeMap = [
    'open'         => 'badge rounded-pill bg-warning-subtle text-warning-emphasis',
    'in_progress'  => 'badge rounded-pill bg-info-subtle text-info-emphasis',
    'pending'      => 'badge rounded-pill bg-info-subtle text-info-emphasis',
    'closed'       => 'badge rounded-pill bg-success-subtle text-success-emphasis',
    'accepted'     => 'badge rounded-pill bg-success-subtle text-success-emphasis',
    'resolved'     => 'badge rounded-pill bg-success-subtle text-success-emphasis',
    'rejected'     => 'badge rounded-pill bg-danger-subtle text-danger-emphasis',
];

function computeExpectedResolutionDate($createdAt, $priority, $dueDate = null) {
    if ($dueDate && strtotime($dueDate)) {
        return new DateTime($dueDate);
    }

    $expected = new DateTime($createdAt);

    switch (strtolower($priority)) {
        case 'critical':
            $expected->modify('+1 day');
            break;
        case 'high':
            $expected->modify('+2 days');
            break;
        case 'medium':
            $expected->modify('+5 days');
            break;
        case 'low':
        default:
            $expected->modify('+7 days');
            break;
    }

    return $expected;
}

function isOverdue($dueDate, $priority, $createdAt, $status) {
    if (strtolower($status) === 'closed') {
        return false;
    }

    $expected = computeExpectedResolutionDate($createdAt, $priority, $dueDate);
    $now = new DateTime();

    return $now > $expected;
}

function formatTaskDate($date, $format = 'd M Y') {
    if (empty($date) || !strtotime($date)) {
        return null;
    }

    return date($format, strtotime($date));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="My assigned tasks - Defect Tracker">
    <meta name="author" content="<?php echo htmlspecialchars($currentUser ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    <title><?php echo htmlspecialchars($pageTitle ?? '', ENT_QUOTES, 'UTF-8'); ?> - Defect Tracker</title>
    <link rel="icon" type="image/png" href="/favicons/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicons/favicon.svg" />
    <link rel="shortcut icon" href="/favicons/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png" />
    <link rel="manifest" href="/favicons/site.webmanifest" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="css/app.css" rel="stylesheet">
</head>
<body class="tool-body" data-bs-theme="dark">
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top no-print">
        <div class="container-xl">
            <a class="navbar-brand fw-semibold" href="my_tasks.php">
                <i class='bx bx-list-check me-2'></i>Task Command Centre
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#tasksNavbar" aria-controls="tasksNavbar" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="tasksNavbar">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php"><i class='bx bx-doughnut-chart me-1'></i>Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="defects.php"><i class='bx bx-error me-1'></i>Defects</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php"><i class='bx bx-bar-chart-alt-2 me-1'></i>Reports</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="my_tasks.php"><i class='bx bx-list-check me-1'></i>My Tasks</a>
                    </li>
                    <?php if (!empty($_SESSION['is_admin'])): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="admin.php"><i class='bx bx-dial me-1'></i>Admin</a>
                    </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-3">
                    <li class="nav-item text-muted small d-none d-lg-flex align-items-center">
                        <i class='bx bx-time-five me-1'></i><span data-report-time><?php echo htmlspecialchars($currentTimestamp, ENT_QUOTES, 'UTF-8'); ?></span> UK
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="tasksUserMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class='bx bx-user-circle me-1'></i><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="tasksUserMenu">
                            <li><a class="dropdown-item" href="profile.php"><i class='bx bx-user'></i> Profile</a></li>
                            <li><a class="dropdown-item" href="my_tasks.php"><i class='bx bx-list-check'></i> My Tasks</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class='bx bx-log-out'></i> Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="tool-page container-xl py-4">
        <header class="tool-header mb-5">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <h1 class="h3 mb-2">Assignments Dashboard</h1>
                    <p class="text-muted mb-0">Every defect currently routed to you, prioritised for rapid resolution.</p>
                </div>
                <div class="d-flex flex-column align-items-start text-muted small gap-1">
                    <span><i class='bx bx-user-voice me-1'></i><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></span>
                    <span><i class='bx bx-label me-1'></i><?php echo htmlspecialchars($currentUserRoleSummary, ENT_QUOTES, 'UTF-8'); ?></span>
                    <span><i class='bx bx-time-five me-1'></i><span data-report-time><?php echo htmlspecialchars($currentTimestamp, ENT_QUOTES, 'UTF-8'); ?></span> UK</span>
                </div>
            </div>
        </header>

        <?php if (!empty($error_message)): ?>
        <div class="system-callout system-callout--danger no-print" role="alert">
            <div class="system-callout__icon"><i class='bx bx-error-circle'></i></div>
            <div>
                <h2 class="system-callout__title">Database Error</h2>
                <p class="system-callout__body mb-0"><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        </div>
        <?php endif; ?>

        <section class="mb-5">
            <div class="task-metrics-grid">
                <?php foreach ($taskMetrics as $metric): ?>
                    <article class="task-metric-card <?php echo htmlspecialchars($metric['class'], ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="task-metric-card__icon">
                            <i class='bx <?php echo htmlspecialchars($metric['icon'], ENT_QUOTES, 'UTF-8'); ?>'></i>
                        </div>
                        <div class="task-metric-card__content">
                            <h3 class="task-metric-card__title"><?php echo htmlspecialchars($metric['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                            <?php if (!empty($metric['subtitle'])): ?>
                                <p class="task-metric-card__subtitle mb-2"><?php echo htmlspecialchars($metric['subtitle'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <?php endif; ?>
                            <p class="task-metric-card__value mb-1"><?php echo number_format((int) $metric['value']); ?></p>
                            <p class="task-metric-card__description mb-0">
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
                <div class="col-lg-5">
                    <article class="system-tool-card h-100">
                        <div class="system-tool-card__icon">
                            <i class='bx bx-pulse'></i>
                        </div>
                        <div class="system-tool-card__body">
                            <span class="system-tool-card__tag system-tool-card__tag--insight">Snapshot</span>
                            <h2 class="system-tool-card__title">Assignment Overview</h2>
                            <p class="system-tool-card__description">A quick look at workload distribution across your projects.</p>
                            <span class="system-tool-card__stat"><?php echo number_format((int) ($taskStats['total'] ?? 0)); ?></span>
                            <p class="text-muted small mb-3">Total tasks currently assigned to you.</p>
                            <ul class="list-unstyled text-muted small mb-0 d-flex flex-column gap-1">
                                <li><i class='bx bx-building-house me-1'></i><?php echo number_format($activeProjects); ?> active projects</li>
                                <li><i class='bx bx-calendar-star me-1'></i><?php echo htmlspecialchars($nextDueDateDisplay, ENT_QUOTES, 'UTF-8'); ?></li>
                            </ul>
                        </div>
                    </article>
                </div>
                <div class="col-lg-7">
                    <div class="system-callout system-callout--info h-100" role="status">
                        <div class="system-callout__icon"><i class='bx bx-tachometer'></i></div>
                        <div>
                            <h2 class="system-callout__title">Progress Pulse</h2>
                            <p class="system-callout__body mb-3">Stay ahead of deadlines by monitoring the live mix of open, active, and escalated work.</p>
                            <div class="d-flex flex-wrap gap-3 text-muted small mb-0">
                                <span><i class='bx bx-envelope-open me-1'></i><?php echo number_format((int) ($taskStats['open_tasks'] ?? 0)); ?> open</span>
                                <span><i class='bx bx-sync me-1'></i><?php echo number_format((int) ($taskStats['in_progress'] ?? 0)); ?> in progress</span>
                                <span><i class='bx bx-error-circle me-1'></i><?php echo number_format($overdueCount); ?> overdue</span>
                                <span><i class='bx bx-check-double me-1'></i><?php echo number_format($completedTasks); ?> closed overall</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="mb-5">
            <div class="card border-0">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <h2 class="h5 mb-1"><i class='bx bx-list-check me-2'></i>Task Inventory</h2>
                        <p class="text-muted small mb-0">Detailed register of every defect currently assigned to you.</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2 no-print">
                        <a class="btn btn-sm btn-outline-light" href="defects.php"><i class='bx bx-list-ol'></i> All Defects</a>
                        <a class="btn btn-sm btn-primary" href="create_defect.php"><i class='bx bx-plus-circle'></i> New Defect</a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($tasks)): ?>
                        <div class="system-callout system-callout--info" role="status">
                            <div class="system-callout__icon"><i class='bx bx-task'></i></div>
                            <div>
                                <h2 class="system-callout__title">No Tasks Assigned</h2>
                                <p class="system-callout__body mb-0">You're all caught up. Once a defect is routed to you, it will appear here instantly.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-dark table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th scope="col">Title</th>
                                        <th scope="col">Project</th>
                                        <th scope="col">Contractor</th>
                                        <th scope="col" class="text-center">Priority</th>
                                        <th scope="col" class="text-center">Expected Resolution</th>
                                        <th scope="col" class="text-center">Status</th>
                                        <th scope="col" class="text-center">Overdue?</th>
                                        <th scope="col" class="text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tasks as $task): ?>
                                        <?php
                                            $priorityKey = strtolower($task['priority'] ?? '');
                                            $statusKey = strtolower($task['status'] ?? '');

                                            $priorityBadgeClass = $priorityBadgeMap[$priorityKey] ?? 'badge rounded-pill bg-secondary-subtle text-secondary-emphasis';
                                            $statusBadgeClass = $statusBadgeMap[$statusKey] ?? 'badge rounded-pill bg-secondary-subtle text-secondary-emphasis';

                                            $priorityLabel = $priorityKey ? ucfirst($priorityKey) : 'N/A';
                                            $statusLabel = $statusKey ? ucwords(str_replace('_', ' ', $statusKey)) : 'Unknown';

                                            $createdDisplay = formatTaskDate($task['created_at']);
                                            $dueDisplay = formatTaskDate($task['due_date']);

                                            $expectedDate = computeExpectedResolutionDate($task['created_at'], $task['priority'], $task['due_date']);
                                            $expectedDisplay = $expectedDate->format('d M Y');

                                            $isTaskOverdue = isOverdue($task['due_date'], $task['priority'], $task['created_at'], $task['status']);
                                        ?>
                                        <tr>
                                            <td>
                                                <a href="view_defect_mytasks.php?id=<?php echo (int) $task['id']; ?>" class="fw-semibold text-decoration-none link-light">
                                                    <?php echo htmlspecialchars($task['title'] ?? 'Untitled', ENT_QUOTES, 'UTF-8'); ?>
                                                </a>
                                                <div class="small text-muted">
                                                    <?php if ($createdDisplay) { echo 'Created ' . htmlspecialchars($createdDisplay, ENT_QUOTES, 'UTF-8'); } ?>
                                                    <?php if ($dueDisplay) { echo ($createdDisplay ? ' &bull; ' : '') . 'Due ' . htmlspecialchars($dueDisplay, ENT_QUOTES, 'UTF-8'); } ?>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($task['project_name'] ?: 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($task['contractor_name'] ?: 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="text-center">
                                                <span class="<?php echo htmlspecialchars($priorityBadgeClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($priorityLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge rounded-pill bg-primary-subtle text-primary-emphasis"><?php echo htmlspecialchars($expectedDisplay, ENT_QUOTES, 'UTF-8'); ?></span>
                                            </td>
                                            <td class="text-center">
                                                <span class="<?php echo htmlspecialchars($statusBadgeClass, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($isTaskOverdue): ?>
                                                    <span class="badge rounded-pill bg-danger-subtle text-danger-emphasis"><i class='bx bx-error me-1'></i>Overdue</span>
                                                <?php else: ?>
                                                    <span class="badge rounded-pill bg-success-subtle text-success-emphasis"><i class='bx bx-check me-1'></i>On Track</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <a href="view_defect_mytasks.php?id=<?php echo (int) $task['id']; ?>" class="btn btn-sm btn-outline-light">
                                                    <i class='bx bx-show-alt'></i> View
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const updateUKTime = () => {
                const now = new Date();
                const ukTime = new Intl.DateTimeFormat('en-GB', {
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: false,
                    timeZone: 'Europe/London'
                }).format(now);

                document.querySelectorAll('[data-report-time]').forEach((el) => {
                    el.textContent = ukTime;
                });
            };

            updateUKTime();
            setInterval(updateUKTime, 1000);
        });
    </script>
</body>
</html>

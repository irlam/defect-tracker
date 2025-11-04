<?php
// projects.php
// Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): 2025-01-24 17:36:21
// Current User's Login: irlam
// Error reporting and logging setup
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Define INCLUDED constant for navbar security
define('INCLUDED', true);
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/navbar.php';

$pageTitle = 'Projects Management';
$success_message = '';
$error_message = '';
$currentUser = $_SESSION['user_id'];

date_default_timezone_set('Europe/London');
$currentDateTime = date('Y-m-d H:i:s');
$navbar = null;

try {
    $database = new Database();
    $db = $database->getConnection();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    try {
        $navbar = new Navbar($db, $_SESSION['user_id'], $_SESSION['username']);
    } catch (Exception $navbarException) {
        error_log('Navbar initialisation failed in projects.php: ' . $navbarException->getMessage());
    }

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'create_project':
                    $project_name = trim($_POST['project_name']);
                    $description = trim($_POST['description']);
                    $start_date = $_POST['start_date'];
                    $end_date = $_POST['end_date'];
                    $status = $_POST['status'];

                    $stmt = $db->prepare("
                        INSERT INTO projects (
                            name, 
                            description, 
                            start_date, 
                            end_date, 
                            status, 
                            created_by,
                            updated_by,
                            created_at, 
                            updated_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    if ($stmt->execute([
                        $project_name,
                        $description,
                        $start_date,
                        $end_date,
                        $status,
                        $currentUser,
                        $currentUser,
                        $currentDateTime,
                        $currentDateTime
                    ])) {
                        $success_message = "Project created successfully";
                    } else {
                        $error_message = "Error creating project";
                    }
                    break;
					                case 'update_project':
                    $project_id = (int)$_POST['project_id'];
                    $project_name = trim($_POST['project_name']);
                    $description = trim($_POST['description']);
                    $start_date = $_POST['start_date'];
                    $end_date = $_POST['end_date'];
                    $status = $_POST['status'];

                    $stmt = $db->prepare("
                        UPDATE projects 
                        SET 
                            name = ?,
                            description = ?, 
                            start_date = ?, 
                            end_date = ?, 
                            status = ?, 
                            updated_by = ?,
                            updated_at = ? 
                        WHERE id = ?
                    ");
                    
                    if ($stmt->execute([
                        $project_name,
                        $description,
                        $start_date,
                        $end_date,
                        $status,
                        $currentUser,
                        $currentDateTime,
                        $project_id
                    ])) {
                        $success_message = "Project updated successfully";
                    } else {
                        $error_message = "Error updating project";
                    }
                    break;

                case 'delete_project':
                    $project_id = (int)$_POST['project_id'];
                    
                    // Check for associated defects
                    $checkDefects = $db->prepare("SELECT COUNT(*) FROM defects WHERE project_id = ?");
                    $checkDefects->execute([$project_id]);
                    $defectCount = $checkDefects->fetchColumn();

                    if ($defectCount > 0) {
                        $error_message = "Cannot delete project. There are defects associated with this project.";
                    } else {
                        $stmt = $db->prepare("DELETE FROM projects WHERE id = ?");
                        if ($stmt->execute([$project_id])) {
                            $success_message = "Project deleted successfully";
                        } else {
                            $error_message = "Error deleting project";
                        }
                    }
                    break;
            }
        }
    }

    // Get all projects with additional date information
    $query = "
        SELECT 
            p.id,
            p.name AS project_name,
            p.description,
            p.start_date,
            p.end_date,
            p.status,
            p.created_at,
            p.updated_at,
            DATEDIFF(p.end_date, CURRENT_DATE()) as days_remaining,
            DATEDIFF(p.end_date, p.start_date) as total_days,
            DATEDIFF(CURRENT_DATE(), p.start_date) as days_elapsed
        FROM projects AS p 
        ORDER BY p.created_at DESC
    ";
    $projects = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);

    $totalProjects = count($projects);
    $statusCounts = [
        'pending' => 0,
        'active' => 0,
        'completed' => 0,
        'on-hold' => 0,
        'archived' => 0,
        'other' => 0,
    ];
    $upcomingCount = 0;
    $overdueCount = 0;
    $progressSum = 0;
    $progressCount = 0;
    $latestUpdate = null;
    $nextDeadlineProject = null;

    foreach ($projects as &$project) {
        $statusKey = strtolower((string) ($project['status'] ?? 'pending'));
        if (!array_key_exists($statusKey, $statusCounts)) {
            $statusCounts['other']++;
        } else {
            $statusCounts[$statusKey]++;
        }

        $progress = calculateProgress($project['start_date'], $project['end_date']);
        $project['progress'] = $progress;
        $progressSum += $progress;
        $progressCount++;

        $daysRemaining = is_numeric($project['days_remaining']) ? (int) $project['days_remaining'] : null;
        $project['days_remaining'] = $daysRemaining;

        if ($daysRemaining !== null) {
            if ($daysRemaining >= 0 && $daysRemaining <= 14) {
                $upcomingCount++;
            }

            if ($daysRemaining < 0) {
                $overdueCount++;
            }

            if ($daysRemaining >= 0 && ($nextDeadlineProject === null || $daysRemaining < $nextDeadlineProject['days_remaining'])) {
                $nextDeadlineProject = [
                    'name' => $project['project_name'],
                    'days_remaining' => $daysRemaining,
                ];
            }
        }

        $project['total_days'] = is_numeric($project['total_days']) ? (int) $project['total_days'] : null;
        $project['days_elapsed'] = is_numeric($project['days_elapsed']) ? (int) $project['days_elapsed'] : null;

        $updatedAt = $project['updated_at'] ?? $project['created_at'] ?? null;
        if ($updatedAt) {
            if ($latestUpdate === null || $updatedAt > $latestUpdate) {
                $latestUpdate = $updatedAt;
            }
            $project['updated_relative'] = formatRelativeTime($updatedAt);
        } else {
            $project['updated_relative'] = 'No updates logged';
        }

        $project['status_icon'] = getStatusIcon($statusKey);
        $project['status_key'] = $statusKey;
    }
    unset($project);

    $averageProgress = $progressCount > 0 ? (int) round($progressSum / $progressCount) : 0;
    $lastUpdateRelative = $latestUpdate ? formatRelativeTime($latestUpdate) : 'No recent updates';

    if ($totalProjects === 0) {
        $heroSubtitle = 'Create your first project to kick-start the programme tracker.';
    } else {
        $heroDetails = [];
        $heroDetails[] = 'Tracking ' . number_format($totalProjects) . ' projects across the portfolio.';
        if ($upcomingCount > 0) {
            $heroDetails[] = number_format($upcomingCount) . ' approaching their completion window.';
        }
        if ($overdueCount > 0) {
            $heroDetails[] = number_format($overdueCount) . ' require recovery support.';
        }
        if (empty($heroDetails)) {
            $heroDetails[] = 'All projects are currently on track.';
        }
        $heroSubtitle = implode(' ', $heroDetails);
    }

    if ($overdueCount === 0) {
        $portfolioHealthSummary = 'Delivery cadence holding steady.';
    } elseif ($overdueCount >= max(1, (int) ceil(($statusCounts['active'] ?? 0) * 0.4))) {
        $portfolioHealthSummary = 'Escalate focus on critical recoveries.';
    } else {
        $portfolioHealthSummary = 'Monitor the flagged programmes closely.';
    }

    $projectHeroMetrics = [
        [
            'icon' => 'bx-rocket',
            'label' => 'Active Projects',
            'value_display' => number_format($statusCounts['active'] ?? 0),
            'note' => ($statusCounts['pending'] ?? 0) > 0
                ? number_format($statusCounts['pending']) . ' ready to launch'
                : 'All teams mobilised',
            'variant' => 'indigo',
        ],
        [
            'icon' => 'bx-calendar-event',
            'label' => 'Due Within 14 Days',
            'value_display' => number_format($upcomingCount),
            'note' => $nextDeadlineProject
                ? ($nextDeadlineProject['days_remaining'] === 0
                    ? 'Next: ' . $nextDeadlineProject['name'] . ' completes today'
                    : 'Next: ' . $nextDeadlineProject['name'] . ' in ' . $nextDeadlineProject['days_remaining'] . 'd')
                : 'No imminent deadlines',
            'variant' => 'amber',
        ],
        [
            'icon' => 'bx-line-chart',
            'label' => 'Average Progress',
            'value_display' => number_format($averageProgress) . '%',
            'note' => $portfolioHealthSummary,
            'variant' => 'teal',
        ],
        [
            'icon' => 'bx-error-circle',
            'label' => 'Overdue Projects',
            'value_display' => number_format($overdueCount),
            'note' => $overdueCount > 0 ? 'Prioritise recovery plans' : 'All milestones on schedule',
            'variant' => 'crimson',
        ],
    ];

} catch (Exception $e) {
    error_log("Database error in projects.php: " . $e->getMessage());
    $error_message = "Database error: " . $e->getMessage();
}

// Helper function for status badge classes
function getStatusIcon($status)
{
    switch (strtolower((string) $status)) {
        case 'active':
            return 'bx-rocket';
        case 'pending':
            return 'bx-hourglass';
        case 'completed':
            return 'bx-badge-check';
        case 'on-hold':
            return 'bx-pause-circle';
        case 'archived':
            return 'bx-archive';
        default:
            return 'bx-folder';
    }
}

function formatRelativeTime(?string $dateTime): string
{
    if (empty($dateTime)) {
        return 'Not recorded';
    }

    try {
        $target = new DateTime($dateTime, new DateTimeZone('UTC'));
        $now = new DateTime('now', new DateTimeZone('UTC'));
        $diff = $now->diff($target);

        if ($diff->invert === 0) {
            // Future date
            if ($diff->d > 0) {
                return 'In ' . $diff->d . 'd';
            }
            if ($diff->h > 0) {
                return 'In ' . $diff->h . 'h';
            }
            if ($diff->i > 0) {
                return 'In ' . $diff->i . 'm';
            }
            return 'Moments away';
        }

        if ($diff->y > 0) {
            return $diff->y . 'y ago';
        }
        if ($diff->m > 0) {
            return $diff->m . 'mo ago';
        }
        if ($diff->d > 0) {
            return $diff->d . 'd ago';
        }
        if ($diff->h > 0) {
            return $diff->h . 'h ago';
        }
        if ($diff->i > 0) {
            return $diff->i . 'm ago';
        }
        return 'Just now';
    } catch (Exception $e) {
        error_log('Relative time parsing error: ' . $e->getMessage());
        return 'Unknown';
    }
}

function formatDate($date)
{
    return date('d M, Y', strtotime($date));
}

function calculateProgress($start_date, $end_date)
{
    $start = strtotime($start_date);
    $end = strtotime($end_date);
    $now = time();

    if (!$start || !$end || $start >= $end) {
        return 0;
    }
    if ($now >= $end) {
        return 100;
    }
    if ($now <= $start) {
        return 0;
    }

    $total = $end - $start;
    $elapsed = $now - $start;
    $progress = ($elapsed / $total) * 100;

    return round(max(0, min(100, $progress)));
}

function formatProjectDeadline(?int $daysRemaining): string
{
    if ($daysRemaining === null) {
        return 'Target date pending';
    }

    if ($daysRemaining > 1) {
        return $daysRemaining . ' days remaining';
    }

    if ($daysRemaining === 1) {
        return '1 day remaining';
    }

    if ($daysRemaining === 0) {
        return 'Due today';
    }

    if ($daysRemaining === -1) {
        return '1 day overdue';
    }

    return abs($daysRemaining) . ' days overdue';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Projects Management - Defect Tracker System">
    <meta name="author" content="<?php echo htmlspecialchars($_SESSION['username']); ?>">
    <meta name="last-modified" content="<?php echo htmlspecialchars($currentDateTime); ?>">
    <title>Projects Management - Defect Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="/favicons/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicons/favicon.svg" />
    <link rel="shortcut icon" href="/favicons/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png" />
    <link rel="manifest" href="/favicons/site.webmanifest" />
    <link href="/css/app.css?v=20251103" rel="stylesheet">
</head>
<body class="tool-body projects-page-body has-app-navbar" data-bs-theme="dark">
<?php
try {
    if ($navbar instanceof Navbar) {
        $navbar->render();
    }
} catch (Exception $renderException) {
    error_log('Navbar render error on projects.php: ' . $renderException->getMessage());
    echo '<div class="alert alert-danger m-3" role="alert">Navigation failed to load. Refresh the page or contact support.</div>';
}
?>

<div class="app-content-offset"></div>

<main class="projects-page container-fluid px-4 pb-5">
    <section class="projects-hero shadow-lg mb-4">
        <div class="projects-hero__headline">
            <div>
                <span class="projects-hero__pill"><i class='bx bx-map-pin me-1'></i>Programme Delivery</span>
                <h1 class="projects-hero__title">Projects Management</h1>
                <p class="projects-hero__subtitle"><?php echo htmlspecialchars($heroSubtitle); ?></p>
            </div>
            <div class="projects-hero__actions text-end">
                <button type="button" class="btn btn-primary btn-lg shadow-sm" data-bs-toggle="modal" data-bs-target="#createProjectModal">
                    <i class='bx bx-plus-circle me-2'></i>Create Project
                </button>
                <p class="projects-hero__timestamp mt-3">
                    <i class='bx bx-time-five me-1'></i>Updated <?php echo htmlspecialchars($lastUpdateRelative); ?>
                </p>
            </div>
        </div>
        <div class="projects-hero__metrics">
            <?php foreach ($projectHeroMetrics as $metric): ?>
                <article class="projects-metric projects-metric--<?php echo htmlspecialchars($metric['variant']); ?>">
                    <div class="projects-metric__icon"><i class='bx <?php echo htmlspecialchars($metric['icon']); ?>'></i></div>
                    <div class="projects-metric__details">
                        <span class="projects-metric__label"><?php echo htmlspecialchars($metric['label']); ?></span>
                        <span class="projects-metric__value"><?php echo htmlspecialchars($metric['value_display']); ?></span>
                        <span class="projects-metric__note"><?php echo htmlspecialchars($metric['note']); ?></span>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm projects-alert" role="alert">
            <i class='bx bx-check-circle me-2'></i><?php echo htmlspecialchars($success_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm projects-alert" role="alert">
            <i class='bx bx-error-circle me-2'></i><?php echo htmlspecialchars($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <section class="projects-controls row g-3 align-items-center mb-4">
            </div>
        </div>
        <div class="col-12 col-lg-6 d-flex flex-wrap gap-2 projects-controls__filters">
            <button type="button" class="projects-filter__button is-active" data-filter="all">
                <i class='bx bx-show me-1'></i>All
                <span class="projects-filter__count"><?php echo number_format($totalProjects); ?></span>
            </button>
            <button type="button" class="projects-filter__button" data-filter="active">
                <i class='bx bx-rocket me-1'></i>Active
                <span class="projects-filter__count"><?php echo number_format($statusCounts['active'] ?? 0); ?></span>
            </button>
            <button type="button" class="projects-filter__button" data-filter="pending">
                <i class='bx bx-hourglass me-1'></i>Pending
                <span class="projects-filter__count"><?php echo number_format($statusCounts['pending'] ?? 0); ?></span>
            </button>
            <button type="button" class="projects-filter__button" data-filter="completed">
                <i class='bx bx-badge-check me-1'></i>Completed
                <span class="projects-filter__count"><?php echo number_format($statusCounts['completed'] ?? 0); ?></span>
            </button>
            <button type="button" class="projects-filter__button" data-filter="on-hold">
                <i class='bx bx-pause-circle me-1'></i>On Hold
                <span class="projects-filter__count"><?php echo number_format($statusCounts['on-hold'] ?? 0); ?></span>
            </button>
            <button type="button" class="projects-filter__button" data-filter="archived">
                <i class='bx bx-archive me-1'></i>Archived
                <span class="projects-filter__count"><?php echo number_format($statusCounts['archived'] ?? 0); ?></span>
            </button>
        </div>
    </section>

    <?php if ($totalProjects === 0): ?>
        <section class="projects-empty-state text-center py-5">
            <div class="projects-empty-state__icon mb-3"><i class='bx bx-folder-open'></i></div>
            <h2 class="h4 mb-3">No projects in the tracker yet</h2>
            <p class="text-muted mb-4">Kick off your portfolio management by creating a new project. You can import milestones and deliverables once the record is created.</p>
            <button type="button" class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#createProjectModal">
                <i class='bx bx-plus-circle me-2'></i>Create your first project
            </button>
        </section>
    <?php else: ?>
        <section class="projects-grid row g-3" id="projectsGrid">
            <?php foreach ($projects as $project): ?>
                <div class="col-12 col-lg-6 col-xxl-4 projects-grid__item" data-project-status="<?php echo htmlspecialchars($project['status_key']); ?>" data-project-name="<?php echo htmlspecialchars($project['project_name']); ?>">
                    <article class="project-card">
                        <header class="project-card__header">
                            <div>
                                <span class="project-card__pill project-card__pill--<?php echo htmlspecialchars($project['status_key']); ?>">
                                    <i class='bx <?php echo htmlspecialchars($project['status_icon']); ?>'></i>
                                    <?php echo ucwords(str_replace('-', ' ', htmlspecialchars($project['status_key']))); ?>
                                </span>
                                <h2 class="project-card__title"><?php echo htmlspecialchars($project['project_name']); ?></h2>
                            </div>
                            <div class="project-card__actions">
                                <button type="button" class="btn btn-sm btn-outline-light" data-bs-toggle="modal" data-bs-target="#editProjectModal<?php echo $project['id']; ?>">
                                    <i class='bx bx-edit-alt'></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmDeleteProject(<?php echo (int) $project['id']; ?>)">
                                    <i class='bx bx-trash'></i>
                                </button>
                            </div>
                        </header>
                        <div class="project-card__body">
                            <p class="project-card__description">
                                <?php echo htmlspecialchars($project['description'] ?: 'No description provided yet.'); ?>
                            </p>
                            <div class="project-card__schedule">
                                <div class="project-card__date">
                                    <span class="project-card__date-label"><i class='bx bx-calendar'></i>Start</span>
                                    <span class="project-card__date-value"><?php echo formatDate($project['start_date']); ?></span>
                                </div>
                                <div class="project-card__date">
                                    <span class="project-card__date-label"><i class='bx bx-calendar-check'></i>Completion</span>
                                    <span class="project-card__date-value"><?php echo formatDate($project['end_date']); ?></span>
                                </div>
                                <div class="project-card__date">
                                    <span class="project-card__date-label"><i class='bx bx-refresh'></i>Updated</span>
                                    <span class="project-card__date-value"><?php echo htmlspecialchars($project['updated_relative']); ?></span>
                                </div>
                            </div>
                            <div class="project-card__progress" title="<?php echo (int) $project['progress']; ?>% complete">
                                <div class="project-card__progress-bar" style="width: <?php echo (int) $project['progress']; ?>%"></div>
                                <div class="project-card__progress-value"><?php echo (int) $project['progress']; ?>%</div>
                            </div>
                            <div class="project-card__deadline">
                                <i class='bx bx-time-five'></i>
                                <span><?php echo htmlspecialchars(formatProjectDeadline($project['days_remaining'])); ?></span>
                            </div>
                        </div>
                    </article>
                </div>

                <div class="modal fade" id="editProjectModal<?php echo $project['id']; ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                        <div class="modal-content">
                            <form method="POST" class="needs-validation" novalidate>
                                <input type="hidden" name="action" value="update_project">
                                <input type="hidden" name="project_id" value="<?php echo $project['id']; ?>">
                                <div class="modal-header">
                                    <h5 class="modal-title"><i class='bx bx-edit me-2'></i>Edit Project</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label required">Project Name</label>
                                            <input type="text" name="project_name" class="form-control" value="<?php echo htmlspecialchars($project['project_name']); ?>" required>
                                            <div class="invalid-feedback">Project name is required.</div>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Description</label>
                                            <textarea name="description" class="form-control" rows="3" placeholder="Add programme notes or scope details"><?php echo htmlspecialchars($project['description']); ?></textarea>
                                        </div>
                                        <div class="col-12 col-md-6">
                                            <label class="form-label">Start Date</label>
                                            <input type="date" name="start_date" class="form-control" value="<?php echo date('Y-m-d', strtotime($project['start_date'])); ?>" required>
                                        </div>
                                        <div class="col-12 col-md-6">
                                            <label class="form-label">End Date</label>
                                            <input type="date" name="end_date" class="form-control" value="<?php echo date('Y-m-d', strtotime($project['end_date'])); ?>" required>
                                        </div>
                                        <div class="col-12 col-md-6">
                                            <label class="form-label">Status</label>
                                            <select name="status" class="form-select" required>
                                                <option value="pending" <?php echo $project['status_key'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="active" <?php echo $project['status_key'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                                <option value="completed" <?php echo $project['status_key'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                <option value="on-hold" <?php echo $project['status_key'] === 'on-hold' ? 'selected' : ''; ?>>On Hold</option>
                                                <option value="archived" <?php echo $project['status_key'] === 'archived' ? 'selected' : ''; ?>>Archived</option>
                                            </select>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-text text-muted">
                                                <i class='bx bx-info-circle me-1'></i>Adjust dates and status to keep programme visibility accurate.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <div class="projects-footer-actions d-flex flex-wrap justify-content-between align-items-center gap-3 mt-4">
        <div class="text-muted small"><i class='bx bx-info-circle me-1'></i>Need to bulk import projects? Contact support for onboarding options.</div>
        <button type="button" class="btn btn-outline-light" data-bs-toggle="modal" data-bs-target="#createProjectModal">
            <i class='bx bx-plus-circle me-1'></i>New Project
        </button>
    </div>
</main>

<div class="modal fade" id="createProjectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="action" value="create_project">
                <div class="modal-header">
                    <h5 class="modal-title"><i class='bx bx-briefcase-alt-2 me-2'></i>Create New Project</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label required">Project Name</label>
                            <input type="text" name="project_name" class="form-control" placeholder="e.g. Plot Handover Programme" required>
                            <div class="invalid-feedback">Provide a name so the team can recognise this programme.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Key deliverables, stakeholders, or scope notes"></textarea>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" class="form-control" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">End Date</label>
                            <input type="date" name="end_date" class="form-control" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select" required>
                                <option value="pending">Pending</option>
                                <option value="active">Active</option>
                                <option value="completed">Completed</option>
                                <option value="on-hold">On Hold</option>
                                <option value="archived">Archived</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <div class="form-text text-muted">
                                <i class='bx bx-calendar-week me-1'></i>Set accurate dates to power progress tracking and reporting insights.
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Project</button>
                </div>
            </form>
        </div>
    </div>
</div>

<form id="deleteProjectForm" method="POST" class="d-none">
    <input type="hidden" name="action" value="delete_project">
    <input type="hidden" name="project_id" id="deleteProjectId">
</form>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const forms = document.querySelectorAll('.needs-validation');
        Array.from(forms).forEach((form) => {
            form.addEventListener('submit', (event) => {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            });
        });

        const projectItems = document.querySelectorAll('.projects-grid__item');
        const filterButtons = document.querySelectorAll('.projects-filter__button');
        const searchInput = document.getElementById('projectsSearch');

        function applyFilters() {
            const activeFilter = document.querySelector('.projects-filter__button.is-active')?.dataset.filter ?? 'all';
            const searchTerm = searchInput.value.trim().toLowerCase();

            projectItems.forEach((item) => {
                const matchesStatus = activeFilter === 'all' || item.dataset.projectStatus === activeFilter;
                const matchesSearch = searchTerm.length === 0 || item.dataset.projectName.toLowerCase().includes(searchTerm);
                item.style.display = matchesStatus && matchesSearch ? '' : 'none';
            });
        }

        filterButtons.forEach((button) => {
            button.addEventListener('click', () => {
                filterButtons.forEach((btn) => btn.classList.remove('is-active'));
                button.classList.add('is-active');
                applyFilters();
            });
        });

        searchInput.addEventListener('input', () => {
            applyFilters();
        });

        window.confirmDeleteProject = (projectId) => {
            if (confirm('Are you sure you want to delete this project? This action cannot be undone.')) {
                document.getElementById('deleteProjectId').value = projectId;
                document.getElementById('deleteProjectForm').submit();
            }
        };

        applyFilters();
    });
</script>
</body>
</html>
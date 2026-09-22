<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/logs/error.log');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['username'], $_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/navbar.php';

date_default_timezone_set('Europe/London');

$pageTitle = 'Contractor Statistics';
$displayName = ucwords(str_replace(['.', '_'], [' ', ' '], $_SESSION['username'] ?? 'User'));
$currentUserRoleSummary = ucwords(str_replace(['_', '-'], [' ', ' '], $_SESSION['user_type'] ?? 'User'));
$currentTimestamp = date('d/m/Y H:i');
$error_message = '';

$contractorStats = [];
$aggregate = [
    'total_contractors' => 0,
    'active_contractors' => 0,
    'total_defects' => 0,
    'open_defects' => 0,
    'in_progress_defects' => 0,
    'closed_defects' => 0,
    'due_today' => 0,
    'overdue_defects' => 0,
    'avg_resolution_time' => null,
];

$resolutionSamples = [];
$db = null;
$navbar = null;

try {
    $database = new Database();
    $db = $database->getConnection();
} catch (Throwable $dbError) {
    $error_message = 'Unable to connect to the database. Please try again later.';
    error_log('Contractor stats DB connection error: ' . $dbError->getMessage());
}

if ($db instanceof PDO) {
    try {
        $navbar = new Navbar($db, (int) ($_SESSION['user_id'] ?? 0), $_SESSION['username'] ?? '');
    } catch (Throwable $navbarError) {
        error_log('Navbar initialisation error on contractor_stats.php: ' . $navbarError->getMessage());
        $navbar = null;
    }

    try {
        $contractorStatsQuery = "
            SELECT
                c.id,
                c.company_name,
                c.trade,
                c.status,
                COUNT(DISTINCT d.id) AS total_defects,
                SUM(CASE WHEN d.status = 'open' THEN 1 ELSE 0 END) AS open_defects,
                SUM(CASE WHEN d.status IN ('pending', 'in_progress', 'reopened') THEN 1 ELSE 0 END) AS in_progress_defects,
                SUM(CASE WHEN d.status IN ('accepted', 'completed', 'resolved', 'closed') THEN 1 ELSE 0 END) AS closed_defects,
                SUM(CASE WHEN DATE(d.due_date) = CURDATE() THEN 1 ELSE 0 END) AS due_today,
                SUM(CASE WHEN d.due_date < CURDATE() AND d.due_date IS NOT NULL AND d.status NOT IN ('accepted', 'completed', 'resolved', 'closed') THEN 1 ELSE 0 END) AS overdue_defects,
                AVG(CASE WHEN d.status IN ('accepted', 'completed', 'resolved', 'closed')
                        THEN TIMESTAMPDIFF(DAY, d.created_at, d.updated_at)
                    END) AS avg_resolution_time
            FROM contractors c
            LEFT JOIN defects d ON d.assigned_to = c.id AND d.deleted_at IS NULL
            WHERE c.deleted_at IS NULL
            GROUP BY c.id, c.company_name, c.trade, c.status
            ORDER BY c.company_name ASC
        ";

        $stmt = $db->query($contractorStatsQuery);
        $contractorStats = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $queryError) {
        $error_message = 'Unable to load contractor statistics. Please try again later.';
        error_log('Contractor stats query error: ' . $queryError->getMessage());
    }
} else {
    $error_message = $error_message ?: 'Database connection unavailable.';
}

if (!function_exists('contractorStatusBadgeClass')) {
    function contractorStatusBadgeClass(string $status): string {
        return match (strtolower($status)) {
            'active' => 'badge rounded-pill bg-success-subtle text-success-emphasis',
            'inactive' => 'badge rounded-pill bg-secondary-subtle text-secondary-emphasis',
            'suspended' => 'badge rounded-pill bg-warning-subtle text-warning-emphasis',
            default => 'badge rounded-pill bg-secondary-subtle text-secondary-emphasis',
        };
    }
}

foreach ($contractorStats as &$contractor) {
    $contractor['total_defects'] = (int) ($contractor['total_defects'] ?? 0);
    $contractor['open_defects'] = (int) ($contractor['open_defects'] ?? 0);
    $contractor['in_progress_defects'] = (int) ($contractor['in_progress_defects'] ?? 0);
    $contractor['closed_defects'] = (int) ($contractor['closed_defects'] ?? 0);
    $contractor['due_today'] = (int) ($contractor['due_today'] ?? 0);
    $contractor['overdue_defects'] = (int) ($contractor['overdue_defects'] ?? 0);

    if ($contractor['avg_resolution_time'] !== null) {
        $contractor['avg_resolution_time'] = round((float) $contractor['avg_resolution_time'], 1);
        $resolutionSamples[] = $contractor['avg_resolution_time'];
    } else {
        $contractor['avg_resolution_time'] = null;
    }

    $aggregate['total_contractors']++;
    if (strtolower((string) ($contractor['status'] ?? '')) === 'active') {
        $aggregate['active_contractors']++;
    }

    $aggregate['total_defects'] += $contractor['total_defects'];
    $aggregate['open_defects'] += $contractor['open_defects'];
    $aggregate['in_progress_defects'] += $contractor['in_progress_defects'];
    $aggregate['closed_defects'] += $contractor['closed_defects'];
    $aggregate['due_today'] += $contractor['due_today'];
    $aggregate['overdue_defects'] += $contractor['overdue_defects'];
}
unset($contractor);

if (!empty($resolutionSamples)) {
    $aggregate['avg_resolution_time'] = round(array_sum($resolutionSamples) / count($resolutionSamples), 1);
}

$heroMetrics = [
    [
        'icon' => 'bx-buildings',
        'title' => 'Active Contractors',
        'stat' => number_format($aggregate['active_contractors']),
        'description' => 'Partners currently delivering on live projects.',
    ],
    [
        'icon' => 'bx-error',
        'title' => 'Open Defects',
        'stat' => number_format($aggregate['open_defects']),
        'description' => 'Outstanding issues still awaiting contractor action.',
    ],
    [
        'icon' => 'bx-timer',
        'title' => 'Due Today',
        'stat' => number_format($aggregate['due_today']),
        'description' => 'Defects scheduled for completion before midnight.',
    ],
    [
        'icon' => 'bx-time-five',
        'title' => 'Avg. Resolution',
        'stat' => $aggregate['avg_resolution_time'] !== null ? number_format($aggregate['avg_resolution_time'], 1) . ' d' : '—',
        'description' => 'Mean close-out cycle across all contractors.',
    ],
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?> - Defect Tracker</title>
    <meta name="description" content="Portfolio-wide contractor performance metrics for the McGoff Defect Tracker.">
    <meta name="author" content="<?php echo htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="last-modified" content="<?php echo htmlspecialchars(date('c'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="icon" type="image/png" href="/favicons/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicons/favicon.svg" />
    <link rel="shortcut icon" href="/favicons/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png" />
    <link rel="manifest" href="/favicons/site.webmanifest" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="/css/app.css?v=20251103" rel="stylesheet">
</head>
<body class="tool-body" data-bs-theme="dark">
    <?php
    if ($navbar instanceof Navbar) {
        $navbar->render();
    }
    ?>
    <div class="app-content-offset"></div>

    <main class="tool-page container-xl py-4">
        <header class="tool-header mb-5">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <h1 class="h3 mb-2">Contractor Performance Command</h1>
                    <p class="text-muted mb-0">Live insight into contractor throughput, turnaround, and outstanding workloads.</p>
                </div>
                <div class="d-flex flex-column align-items-start text-muted small gap-1">
                    <span><i class='bx bx-user-voice me-1'></i><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></span>
                    <span><i class='bx bx-label me-1'></i><?php echo htmlspecialchars($currentUserRoleSummary, ENT_QUOTES, 'UTF-8'); ?></span>
                    <span><i class='bx bx-time-five me-1'></i><?php echo htmlspecialchars($currentTimestamp, ENT_QUOTES, 'UTF-8'); ?> UK</span>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2 mt-3">
                <a class="btn btn-sm btn-outline-light" href="contractors.php"><i class='bx bx-hard-hat me-1'></i>Contractor Directory</a>
                <button type="button" class="btn btn-sm btn-outline-light" onclick="window.print()"><i class='bx bx-printer me-1'></i>Print Report</button>
            </div>
        </header>

        <?php if ($error_message !== ''): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class='bx bx-error-circle me-1'></i><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($heroMetrics)): ?>
        <section class="mb-5">
            <div class="system-tools-grid">
                <?php foreach ($heroMetrics as $metric): ?>
                    <article class="system-tool-card">
                        <div class="system-tool-card__icon"><i class='bx <?php echo htmlspecialchars($metric['icon'], ENT_QUOTES, 'UTF-8'); ?>'></i></div>
                        <div class="system-tool-card__body">
                            <h2 class="system-tool-card__title"><?php echo htmlspecialchars($metric['title'], ENT_QUOTES, 'UTF-8'); ?></h2>
                            <p class="system-tool-card__stat mb-0 fs-3 fw-semibold"><?php echo htmlspecialchars($metric['stat'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <p class="system-tool-card__description"><?php echo htmlspecialchars($metric['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <section class="contractor-stats-section">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                <div>
                    <h2 class="h5 mb-1">Contractor Breakdown</h2>
                    <p class="text-muted small mb-0">Performance, workload, and turnaround metrics by delivery partner.</p>
                </div>
                <span class="badge bg-secondary-subtle text-secondary-emphasis">
                    <?php echo number_format($aggregate['total_contractors']); ?> contractors tracked
                </span>
            </div>

            <?php if (empty($contractorStats)): ?>
                <div class="system-callout system-callout--info" role="status">
                    <div class="system-callout__icon"><i class='bx bx-info-circle'></i></div>
                    <div>
                        <h3 class="system-callout__title">No contractor data available</h3>
                        <p class="system-callout__body mb-0">Add contractors or sync recent activity to populate this dashboard.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="contractor-stat-grid">
                    <?php foreach ($contractorStats as $contractor): ?>
                        <article class="contractor-stat-card">
                            <header class="contractor-stat-card__header">
                                <div class="contractor-stat-card__title">
                                    <h3 class="mb-1 h6 text-truncate" title="<?php echo htmlspecialchars($contractor['company_name'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($contractor['company_name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                    <p class="contractor-stat-card__trade mb-0 text-muted small text-uppercase"><?php echo htmlspecialchars($contractor['trade'] ?? 'Trade not specified', ENT_QUOTES, 'UTF-8'); ?></p>
                                </div>
                                <span class="<?php echo contractorStatusBadgeClass((string) ($contractor['status'] ?? '')); ?>">
                                    <?php echo htmlspecialchars(ucfirst((string) ($contractor['status'] ?? 'Unknown')), ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </header>

                            <div class="contractor-stat-card__metrics">
                                <div class="contractor-stat-metric contractor-stat-metric--open">
                                    <span class="contractor-stat-metric__value"><?php echo number_format($contractor['open_defects']); ?></span>
                                    <span class="contractor-stat-metric__label">Open Defects</span>
                                </div>
                                <div class="contractor-stat-metric contractor-stat-metric--due">
                                    <span class="contractor-stat-metric__value"><?php echo number_format($contractor['due_today']); ?></span>
                                    <span class="contractor-stat-metric__label">Due Today</span>
                                </div>
                                <div class="contractor-stat-metric contractor-stat-metric--progress">
                                    <span class="contractor-stat-metric__value"><?php echo number_format($contractor['in_progress_defects']); ?></span>
                                    <span class="contractor-stat-metric__label">In Progress</span>
                                </div>
                                <div class="contractor-stat-metric contractor-stat-metric--overdue">
                                    <span class="contractor-stat-metric__value"><?php echo number_format($contractor['overdue_defects']); ?></span>
                                    <span class="contractor-stat-metric__label">Overdue</span>
                                </div>
                                <div class="contractor-stat-metric contractor-stat-metric--closed">
                                    <span class="contractor-stat-metric__value"><?php echo number_format($contractor['closed_defects']); ?></span>
                                    <span class="contractor-stat-metric__label">Closed</span>
                                </div>
                                <div class="contractor-stat-metric contractor-stat-metric--total">
                                    <span class="contractor-stat-metric__value"><?php echo number_format($contractor['total_defects']); ?></span>
                                    <span class="contractor-stat-metric__label">Total Logged</span>
                                </div>
                            </div>

                            <footer class="contractor-stat-card__footer">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted small">Avg. Resolution</span>
                                    <span class="badge bg-primary-subtle text-primary-emphasis">
                                        <?php echo $contractor['avg_resolution_time'] !== null ? htmlspecialchars(number_format($contractor['avg_resolution_time'], 1) . ' days', ENT_QUOTES, 'UTF-8') : 'No data'; ?>
                                    </span>
                                </div>
                            </footer>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
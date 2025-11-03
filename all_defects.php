<?php
// all_defects.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/all_defects.log');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication check
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Initialize variables
$pageTitle = 'All Defects';
$currentUser = $_SESSION['username'];

// Define INCLUDED constant for navbar security
define('INCLUDED', true);

require_once 'includes/functions.php';
require_once 'config/database.php';
require_once 'includes/navbar.php';
require_once 'includes/all_defects_PDF_converter.php';

$projects = [];
$floorPlans = [];
$defects = [];
$selectedDefect = null;
$error_message = $error_message ?? '';

try {
    $database = new Database();
    $db = $database->getConnection();

    // Fetch projects
    $projectsQuery = "SELECT id, name FROM projects ORDER BY name ASC";
    $projectsStmt = $db->prepare($projectsQuery);
    $projectsStmt->execute();
    $projects = $projectsStmt->fetchAll(PDO::FETCH_ASSOC);

    $pdfConverter = new AllDefectsPdfConverter();

    // Initialize selected project and floor plan IDs
    $selectedProjectId = isset($_GET['project_id']) ? $_GET['project_id'] : null;
    $selectedFloorPlanId = isset($_GET['floor_plan_id']) ? $_GET['floor_plan_id'] : null;
    $selectedDefectId = isset($_GET['defect_id']) ? $_GET['defect_id'] : null;

    // Fetch floor plans for the selected project
    $floorPlans = [];
    if ($selectedProjectId) {
        $floorPlansQuery = "SELECT id, level, image_path FROM floor_plans 
                           WHERE project_id = :project_id 
                           ORDER BY CASE 
                               WHEN level = 'Basement' THEN 0 
                               ELSE CAST(REGEXP_REPLACE(level, '[^0-9]', '') AS SIGNED) 
                           END";
        $floorPlansStmt = $db->prepare($floorPlansQuery);
        $floorPlansStmt->bindParam(':project_id', $selectedProjectId);
        $floorPlansStmt->execute();
        $floorPlans = $floorPlansStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Fetch defects for the selected floor plan
    $defects = [];
    if ($selectedFloorPlanId) {
        $defectsQuery = "SELECT
                            d.id AS defect_id,
                            d.title,
                            d.description,
                            d.status,
                            d.priority,
                            d.pin_x,
                            d.pin_y,
                            d.created_at,
                            c.company_name,
                            c.logo AS contractor_logo,
                            c.id AS contractor_id,
                            u.username AS reported_by
                         FROM defects d
                         LEFT JOIN contractors c ON d.contractor_id = c.id
                         LEFT JOIN users u ON d.reported_by = u.id
                         WHERE d.floor_plan_id = :floor_plan_id 
                         AND d.deleted_at IS NULL
                         ORDER BY d.created_at DESC";
        $defectsStmt = $db->prepare($defectsQuery);
        $defectsStmt->bindParam(':floor_plan_id', $selectedFloorPlanId);
        $defectsStmt->execute();
        $defects = $defectsStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Fetch selected defect details
    $selectedDefect = null;
    if ($selectedDefectId) {
        $selectedDefectQuery = "SELECT
                                    d.*,
                                    c.company_name,
                                    c.logo AS contractor_logo,
                                    c.id AS contractor_id,
                                    u.username AS reported_by,
                                    u2.username AS assigned_to_user
                                 FROM defects d
                                 LEFT JOIN contractors c ON d.contractor_id = c.id
                                 LEFT JOIN users u ON d.reported_by = u.id
                                 LEFT JOIN users u2 ON d.assigned_to = u2.id
                                 WHERE d.id = :defect_id AND d.deleted_at IS NULL";
        $selectedDefectStmt = $db->prepare($selectedDefectQuery);
        $selectedDefectStmt->bindParam(':defect_id', $selectedDefectId);
        $selectedDefectStmt->execute();
        $selectedDefect = $selectedDefectStmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log("All Defects Error: " . $e->getMessage());
    $error_message = "An error occurred while loading defects: " . $e->getMessage();
}

// Helper functions
function formatDefectTitle($title) {
    return strlen($title) > 50 ? substr($title, 0, 50) . '...' : $title;
}

function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    return $protocol . "://" . $host;
}

function formatDate($date) {
    return date('d M Y H:i', strtotime($date));
}

function getImageUrl($imagePath) {
    // Base URL for the images
    $baseUrl = 'https://mcgoff.defecttracker.uk';
    
    // If the path already starts with http/https, return it as is
    if (strpos($imagePath, 'http') === 0) {
        return $imagePath;
    }
    
    // Ensure the path starts with a forward slash
    if (strpos($imagePath, '/') !== 0) {
        $imagePath = '/' . $imagePath;
    }
    
    return $baseUrl . $imagePath;
}

// Status and priority colors
$statusColors = [
    'open' => 'danger',
    'in_progress' => 'warning',
    'completed' => 'info',
    'verified' => 'primary',
    'rejected' => 'secondary',
    'accepted' => 'success'
];

$priorityColors = [
    'low' => 'success',
    'medium' => 'warning',
    'high' => 'danger',
    'critical' => 'dark'
];
$navbar = new Navbar($db, $_SESSION['user_id'], $_SESSION['username']);
$displayName = ucwords(str_replace(['.', '_'], [' ', ' '], $_SESSION['username'] ?? 'User'));
$currentTimestamp = date('d/m/Y H:i');
$totalProjects = isset($projects) ? count($projects) : 0;
$totalFloorPlans = isset($floorPlans) ? count($floorPlans) : 0;
$totalDefects = isset($defects) ? count($defects) : 0;
$activeFloorPlan = null;
if ($selectedFloorPlanId && !empty($floorPlans)) {
    foreach ($floorPlans as $plan) {
        if ((string) $plan['id'] === (string) $selectedFloorPlanId) {
            $activeFloorPlan = $plan;
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="/favicons/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicons/favicon.svg" />
    <link rel="shortcut icon" href="/favicons/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png" />
    <link rel="manifest" href="/favicons/site.webmanifest" />
    <style>
        :root {
            --defect-primary: #2563eb;
            --defect-secondary: #38bdf8;
            --defect-surface: rgba(15, 23, 42, 0.88);
            --defect-surface-alt: rgba(30, 41, 59, 0.9);
            --defect-border: rgba(148, 163, 184, 0.18);
            --defect-text: rgba(226, 232, 240, 0.92);
            --defect-muted: rgba(148, 163, 184, 0.75);
        }

        body {
            background:
                radial-gradient(140% 120% at 0% 0%, rgba(37, 99, 235, 0.22), transparent 55%),
                radial-gradient(120% 140% at 100% 0%, rgba(14, 165, 233, 0.18), transparent 50%),
                rgba(3, 7, 18, 0.98);
            color: var(--defect-text);
            min-height: 100vh;
        }

        .defects-page {
            color: var(--defect-text);
        }

        .defects-hero {
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.95), rgba(30, 64, 175, 0.85));
            border: 1px solid var(--defect-border);
            border-radius: 1.5rem;
            padding: 2.25rem;
            box-shadow: 0 28px 48px -18px rgba(15, 23, 42, 0.65);
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 1.75rem;
        }

        .defects-hero h1 {
            color: rgba(248, 250, 252, 0.96);
        }

        .defects-hero__subtitle {
            color: var(--defect-muted);
            max-width: 640px;
            margin-bottom: 0;
        }

        .defects-hero__meta {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            font-size: 0.9rem;
            color: rgba(191, 219, 254, 0.85);
        }

        .defects-hero__meta i {
            color: rgba(59, 130, 246, 0.85);
        }

        .defects-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            padding: 0.35rem 0.85rem;
            border-radius: 999px;
            background: rgba(96, 165, 250, 0.2);
            color: rgba(191, 219, 254, 0.9);
            font-size: 0.8rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-bottom: 0.75rem;
        }

        .defects-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.2rem;
        }

        .defects-stat-card {
            background: var(--defect-surface);
            border: 1px solid var(--defect-border);
            border-radius: 1.1rem;
            padding: 1.1rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: 0 18px 30px -20px rgba(15, 23, 42, 0.65);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .defects-stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 22px 40px -18px rgba(59, 130, 246, 0.35);
        }

        .defects-stat-card__icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: rgba(59, 130, 246, 0.18);
            color: rgba(191, 219, 254, 0.95);
            font-size: 1.4rem;
        }

        .defects-stat-card__label {
            font-size: 0.85rem;
            color: var(--defect-muted);
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .defects-stat-card__value {
            font-size: 1.8rem;
            font-weight: 600;
            color: var(--defect-text);
        }

        .defects-card {
            background: var(--defect-surface);
            border: 1px solid var(--defect-border);
            border-radius: 1.3rem;
            padding: 1.75rem;
            box-shadow: 0 22px 48px -24px rgba(15, 23, 42, 0.65);
            margin-bottom: 1.5rem;
        }

        .defects-card__title {
            font-size: 1.05rem;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            color: rgba(148, 197, 255, 0.9);
        }

        .filter-form .form-label {
            font-weight: 500;
            color: var(--defect-muted);
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.08em;
        }

        .form-control,
        .form-select {
            background: var(--defect-surface-alt);
            border: 1px solid rgba(148, 163, 184, 0.35);
            color: var(--defect-text);
            border-radius: 0.8rem;
            padding: 0.75rem 0.9rem;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: rgba(96, 165, 250, 0.65);
            box-shadow: 0 0 0 0.15rem rgba(59, 130, 246, 0.25);
            background: rgba(15, 23, 42, 0.95);
        }

        .floor-plans-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
        }

        .floor-plan-card {
            background: rgba(30, 41, 59, 0.65);
            border: 1px solid rgba(148, 163, 184, 0.25);
            border-radius: 1rem;
            padding: 1rem;
            cursor: pointer;
            transition: transform 0.2s ease, border-color 0.2s ease, background-color 0.2s ease;
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }

        .floor-plan-card h5 {
            color: var(--defect-text);
            font-size: 1rem;
            margin-bottom: 0;
        }

        .floor-plan-card:hover {
            transform: translateY(-4px);
            border-color: rgba(96, 165, 250, 0.6);
        }

        .floor-plan-card.selected {
            border-color: rgba(96, 165, 250, 0.8);
            background: rgba(37, 99, 235, 0.18);
            box-shadow: 0 16px 34px -18px rgba(59, 130, 246, 0.55);
        }

        .floor-plan-container {
            position: relative;
            width: 100%;
            border-radius: 1.1rem;
            background: rgba(2, 6, 23, 0.85);
            border: 1px solid rgba(148, 163, 184, 0.25);
            overflow: hidden;
        }

        .floor-plan-image {
            width: 100%;
            height: auto;
            display: block;
            object-fit: contain;
        }

        .defect-pin {
            position: absolute;
            width: 28px;
            height: 28px;
            transform: translate(-50%, -50%);
            cursor: pointer;
            z-index: 100;
        }

        .defect-pin::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image: url('/uploads/images/location-pin.svg');
            background-size: contain;
            background-repeat: no-repeat;
            filter: drop-shadow(0 4px 8px rgba(37, 99, 235, 0.55));
            transition: transform 0.2s ease, filter 0.2s ease;
        }

        .defect-pin:hover::before,
        .defect-pin.active::before {
            transform: scale(1.1);
            filter: drop-shadow(0 6px 12px rgba(59, 130, 246, 0.85));
        }

        .defect-tooltip {
            position: absolute;
            background: rgba(15, 23, 42, 0.92);
            border: 1px solid rgba(148, 163, 184, 0.25);
            padding: 0.85rem;
            border-radius: 0.9rem;
            box-shadow: 0 20px 35px -18px rgba(15, 23, 42, 0.65);
            z-index: 102;
            min-width: 220px;
            max-width: 280px;
            display: none;
            top: calc(100% + 12px);
            left: 50%;
            transform: translateX(-50%);
            font-size: 0.85rem;
            color: var(--defect-text);
        }

        .defect-tooltip::before {
            content: '';
            position: absolute;
            top: -10px;
            left: 50%;
            transform: translateX(-50%);
            border-width: 0 10px 10px 10px;
            border-style: solid;
            border-color: transparent transparent rgba(148, 163, 184, 0.25) transparent;
        }

        .defect-tooltip small {
            color: var(--defect-muted);
        }

        .defect-pin:hover .defect-tooltip,
        .defect-pin.active .defect-tooltip {
            display: block;
        }

        .defects-table {
            width: 100%;
        }

        .defects-table thead th {
            background: rgba(15, 23, 42, 0.92);
            border-bottom: 1px solid rgba(148, 163, 184, 0.25);
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.08em;
            color: var(--defect-muted);
        }

        .defects-table tbody tr {
            transition: transform 0.15s ease, background-color 0.15s ease;
        }

        .defects-table tbody tr:hover {
            background: rgba(59, 130, 246, 0.12);
            transform: translateX(4px);
        }

        .defects-table tbody tr.selected {
            background: rgba(30, 64, 175, 0.25);
        }

        .contractor-logo {
            width: 40px;
            height: 40px;
            object-fit: contain;
            border-radius: 0.6rem;
            background: rgba(15, 23, 42, 0.85);
            border: 1px solid rgba(148, 163, 184, 0.3);
        }

        .status-badge,
        .priority-badge {
            border-radius: 999px;
            font-size: 0.75rem;
            text-transform: capitalize;
            padding: 0.25rem 0.7rem;
        }

        .badge-soft {
            background: rgba(59, 130, 246, 0.18);
            color: rgba(191, 219, 254, 0.95);
            border-radius: 999px;
            padding: 0.35rem 0.85rem;
            font-size: 0.8rem;
        }

        .defect-details {
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.96), rgba(30, 41, 59, 0.9));
        }

        .defect-details__grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
        }

        .defect-details__grid dl {
            margin-bottom: 0;
        }

        .defect-details__grid dt {
            font-size: 0.75rem;
            text-transform: uppercase;
            color: var(--defect-muted);
            letter-spacing: 0.08em;
        }

        .defect-details__grid dd {
            margin-bottom: 0.65rem;
            color: var(--defect-text);
        }

        .defects-empty-state {
            color: var(--defect-muted);
        }

        .defects-empty-state i {
            font-size: 2.5rem;
            color: rgba(148, 163, 184, 0.6);
        }

        .loading-overlay {
            position: fixed;
            inset: 0;
            background: rgba(2, 6, 23, 0.78);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1200;
        }

        .spinner {
            width: 48px;
            height: 48px;
            border: 4px solid rgba(148, 163, 184, 0.3);
            border-top: 4px solid rgba(96, 165, 250, 0.85);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        @media (max-width: 768px) {
            .defects-hero {
                padding: 1.75rem;
            }

            .defects-card {
                padding: 1.35rem;
            }

            .defects-stats {
                grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            }
        }
    </style>
</head>
<body class="tool-body" data-bs-theme="dark">
    <?php if ($navbar instanceof Navbar) { $navbar->render(); } ?>
    <div class="app-content-offset"></div>

    <div class="loading-overlay">
        <div class="spinner"></div>
    </div>

    <main class="defects-page container-xxl py-4">
        <header class="defects-hero mb-4">
            <div>
                <span class="defects-pill"><i class='bx bx-radar me-1'></i>Defect intelligence</span>
                <h1 class="h3 mb-2"><i class='bx bx-bug-alt me-2'></i><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
                <p class="defects-hero__subtitle">Navigate every issue across the portfolio with rich floor plan context, live pins, and responsive registers.</p>
            </div>
            <div class="defects-hero__meta">
                <span><i class='bx bx-user-circle me-1'></i><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></span>
                <span><i class='bx bx-time-five me-1'></i><?php echo htmlspecialchars($currentTimestamp, ENT_QUOTES, 'UTF-8'); ?> UK</span>
            </div>
        </header>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                <i class="bx bx-error-circle me-1"></i><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <section class="defects-stats mb-4">
            <article class="defects-stat-card">
                <div class="defects-stat-card__icon"><i class='bx bx-buildings'></i></div>
                <div>
                    <div class="defects-stat-card__label">Active Projects</div>
                    <div class="defects-stat-card__value"><?php echo number_format($totalProjects); ?></div>
                </div>
            </article>
            <article class="defects-stat-card">
                <div class="defects-stat-card__icon"><i class='bx bx-layer'></i></div>
                <div>
                    <div class="defects-stat-card__label">Floor Plans Loaded</div>
                    <div class="defects-stat-card__value"><?php echo number_format($totalFloorPlans); ?></div>
                </div>
            </article>
            <article class="defects-stat-card">
                <div class="defects-stat-card__icon"><i class='bx bx-list-check'></i></div>
                <div>
                    <div class="defects-stat-card__label">Defects In View</div>
                    <div class="defects-stat-card__value"><?php echo number_format($totalDefects); ?></div>
                </div>
            </article>
        </section>

        <section class="defects-card filter-card">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
                <h2 class="defects-card__title mb-0"><i class='bx bx-filter-alt me-2'></i>Filters</h2>
                <?php if ($selectedProjectId || $selectedFloorPlanId): ?>
                    <a class="btn btn-outline-light btn-sm" href="all_defects.php"><i class='bx bx-x-circle me-1'></i>Clear</a>
                <?php endif; ?>
            </div>
            <form method="GET" class="row g-3 align-items-end filter-form" data-loading="true">
                <div class="col-md-6 col-lg-4">
                    <label for="project_id" class="form-label">Project</label>
                    <select class="form-select" id="project_id" name="project_id" onchange="this.form.submit()">
                        <option value="">Choose project…</option>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?php echo htmlspecialchars($project['id']); ?>" <?php echo ($selectedProjectId == $project['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($project['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($selectedProjectId && !empty($floorPlans)): ?>
                    <div class="col-md-6 col-lg-4">
                        <label for="floor_plan_id" class="form-label">Floor Plan</label>
                        <select class="form-select" id="floor_plan_id" name="floor_plan_id" onchange="this.form.submit()">
                            <option value="">Choose level…</option>
                            <?php foreach ($floorPlans as $floorPlan): ?>
                                <option value="<?php echo htmlspecialchars($floorPlan['id']); ?>" <?php echo ($selectedFloorPlanId == $floorPlan['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($floorPlan['level']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
            </form>
        </section>

        <?php if ($selectedProjectId): ?>
            <section class="defects-card">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
                    <h2 class="defects-card__title mb-0"><i class='bx bx-layer me-2'></i>Floor Plans</h2>
                    <span class="badge badge-soft"><?php echo number_format($totalFloorPlans); ?> levels</span>
                </div>
                <?php if (!empty($floorPlans)): ?>
                    <div class="floor-plans-grid">
                        <?php foreach ($floorPlans as $floorPlan): ?>
                            <div class="floor-plan-card <?php echo ($selectedFloorPlanId == $floorPlan['id']) ? 'selected' : ''; ?>"
                                 onclick="window.location.href='?project_id=<?php echo htmlspecialchars($selectedProjectId); ?>&floor_plan_id=<?php echo htmlspecialchars($floorPlan['id']); ?>'">
                                <span class="text-muted small text-uppercase">Level</span>
                                <h5 class="mb-0"><?php echo htmlspecialchars($floorPlan['level']); ?></h5>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="defects-empty-state text-center py-5">
                        <i class='bx bx-line-chart-down mb-3'></i>
                        <p class="mb-0">No floor plans available for this project just yet.</p>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if ($activeFloorPlan): ?>
            <section class="defects-card">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
                    <div>
                        <h2 class="defects-card__title mb-1"><i class='bx bx-map-alt me-2'></i><?php echo htmlspecialchars($activeFloorPlan['level']); ?> plan</h2>
                        <p class="text-muted small mb-0">Hover or tap pins to preview live activity on this level.</p>
                    </div>
                </div>
                <div class="floor-plan-container mt-3">
                    <img id="floorPlanImage"
                         src="<?php echo htmlspecialchars(getImageUrl($activeFloorPlan['image_path']), ENT_QUOTES, 'UTF-8'); ?>"
                         alt="Floor plan"
                         class="floor-plan-image">
                    <?php foreach ($defects as $defect): ?>
                        <?php if (isset($defect['pin_x'], $defect['pin_y'])): ?>
                            <div class="defect-pin"
                                 data-defect-id="<?php echo htmlspecialchars($defect['defect_id']); ?>"
                                 data-pin-x="<?php echo htmlspecialchars($defect['pin_x']); ?>"
                                 data-pin-y="<?php echo htmlspecialchars($defect['pin_y']); ?>">
                                <div class="defect-tooltip">
                                    <h6 class="mb-2"><?php echo htmlspecialchars($defect['title']); ?></h6>
                                    <p class="mb-2 d-flex gap-1 flex-wrap">
                                        <span class="status-badge bg-<?php echo $statusColors[$defect['status']]; ?>"><?php echo htmlspecialchars(str_replace('_', ' ', $defect['status'])); ?></span>
                                        <span class="priority-badge bg-<?php echo $priorityColors[$defect['priority']]; ?>"><?php echo htmlspecialchars($defect['priority']); ?></span>
                                    </p>
                                    <small>
                                        Reported by <?php echo htmlspecialchars($defect['reported_by'] ?? 'Unknown'); ?><br>
                                        <?php echo formatDate($defect['created_at']); ?>
                                    </small>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($selectedFloorPlanId): ?>
            <section class="defects-card">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
                    <div>
                        <h2 class="defects-card__title mb-1"><i class='bx bx-list-check me-2'></i>Defects Register</h2>
                        <p class="text-muted small mb-0">Click a row to focus the matching pin on the plan.</p>
                    </div>
                    <span class="badge badge-soft"><?php echo number_format($totalDefects); ?> entries</span>
                </div>
                <?php if (!empty($defects)): ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover align-middle defects-table">
                            <thead>
                                <tr>
                                    <th scope="col">ID</th>
                                    <th scope="col">Title</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Priority</th>
                                    <th scope="col">Contractor</th>
                                    <th scope="col">Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($defects as $defect): ?>
                                    <tr data-defect-id="<?php echo htmlspecialchars($defect['defect_id']); ?>" class="<?php echo ($selectedDefectId == $defect['defect_id']) ? 'selected' : ''; ?>"
                                        onclick="highlightDefect('<?php echo htmlspecialchars($defect['defect_id']); ?>')">
                                        <td><?php echo htmlspecialchars($defect['defect_id']); ?></td>
                                        <td><?php echo htmlspecialchars(formatDefectTitle($defect['title'])); ?></td>
                                        <td>
                                            <span class="status-badge bg-<?php echo $statusColors[$defect['status']]; ?>"><?php echo htmlspecialchars(str_replace('_', ' ', $defect['status'])); ?></span>
                                        </td>
                                        <td>
                                            <span class="priority-badge bg-<?php echo $priorityColors[$defect['priority']]; ?>"><?php echo htmlspecialchars($defect['priority']); ?></span>
                                        </td>
                                        <td class="d-flex align-items-center gap-2">
                                            <?php if (!empty($defect['contractor_logo'])): ?>
                                                <img src="<?php echo htmlspecialchars(getImageUrl('/uploads/logos/' . $defect['contractor_logo'])); ?>" alt="Contractor logo" class="contractor-logo">
                                            <?php endif; ?>
                                            <span><?php echo htmlspecialchars($defect['company_name'] ?? 'Not Assigned'); ?></span>
                                        </td>
                                        <td><?php echo formatDate($defect['created_at']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="defects-empty-state text-center py-5">
                        <i class='bx bx-search-alt-2 mb-3'></i>
                        <p class="mb-0">No defects recorded on this floor yet.</p>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if ($selectedDefect): ?>
            <section class="defects-card defect-details">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                    <div>
                        <h2 class="defects-card__title mb-1"><i class='bx bx-bug me-2'></i><?php echo htmlspecialchars($selectedDefect['title']); ?></h2>
                        <p class="text-muted small mb-2">Defect ID <?php echo htmlspecialchars($selectedDefect['id']); ?> · Reported <?php echo formatDate($selectedDefect['created_at']); ?></p>
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($selectedDefect['description'] ?: 'No description provided.')); ?></p>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <span class="status-badge bg-<?php echo $statusColors[$selectedDefect['status']] ?? 'secondary'; ?>"><?php echo htmlspecialchars(str_replace('_', ' ', $selectedDefect['status'])); ?></span>
                        <span class="priority-badge bg-<?php echo $priorityColors[$selectedDefect['priority']] ?? 'secondary'; ?>"><?php echo htmlspecialchars($selectedDefect['priority']); ?></span>
                    </div>
                </div>
                <div class="defect-details__grid">
                    <dl>
                        <dt>Contractor</dt>
                        <dd><?php echo htmlspecialchars($selectedDefect['company_name'] ?? 'Not assigned'); ?></dd>
                        <dt>Assigned To</dt>
                        <dd><?php echo htmlspecialchars($selectedDefect['assigned_to_user'] ?? 'Awaiting allocation'); ?></dd>
                    </dl>
                    <dl>
                        <dt>Reported By</dt>
                        <dd><?php echo htmlspecialchars($selectedDefect['reported_by'] ?? 'Unknown'); ?></dd>
                        <dt>Target Date</dt>
                        <dd><?php echo !empty($selectedDefect['target_date']) ? formatDate($selectedDefect['target_date']) : 'Not set'; ?></dd>
                    </dl>
                    <dl>
                        <dt>Last Updated</dt>
                        <dd><?php echo !empty($selectedDefect['updated_at']) ? formatDate($selectedDefect['updated_at']) : formatDate($selectedDefect['created_at']); ?></dd>
                        <dt>Location</dt>
                        <dd><?php echo htmlspecialchars($activeFloorPlan['level'] ?? ''); ?></dd>
                    </dl>
                </div>
            </section>
        <?php endif; ?>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function highlightDefect(defectId) {
            const pins = document.querySelectorAll('.defect-pin');
            const tableRows = document.querySelectorAll('.defects-table tbody tr');
            const urlParams = new URLSearchParams(window.location.search);

            urlParams.set('defect_id', defectId);
            window.history.pushState({}, '', `${window.location.pathname}?${urlParams.toString()}`);

            pins.forEach(pin => {
                const isMatch = pin.dataset.defectId === defectId;
                pin.classList.toggle('active', isMatch);
                pin.style.zIndex = isMatch ? '104' : '100';
            });

            tableRows.forEach(row => {
                const isMatch = row.dataset.defectId === defectId;
                row.classList.toggle('selected', isMatch);
                if (isMatch) {
                    row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            const loadingOverlay = document.querySelector('.loading-overlay');
            const floorPlanImage = document.getElementById('floorPlanImage');
            const defectPins = document.querySelectorAll('.defect-pin');
            const urlParams = new URLSearchParams(window.location.search);
            const selectedDefectId = urlParams.get('defect_id');

            if (loadingOverlay) {
                document.querySelectorAll('[data-loading="true"]').forEach(element => {
                    if (element.tagName === 'FORM') {
                        element.addEventListener('submit', () => {
                            loadingOverlay.style.display = 'flex';
                        });
                    } else {
                        element.addEventListener('click', () => {
                            loadingOverlay.style.display = 'flex';
                        });
                    }
                });

                window.addEventListener('load', () => {
                    loadingOverlay.style.display = 'none';
                });
            }

            function adjustPinPositions() {
                if (!floorPlanImage) {
                    return;
                }

                const imgWidth = floorPlanImage.clientWidth;
                const imgHeight = floorPlanImage.clientHeight;

                if (!imgWidth || !imgHeight) {
                    return;
                }

                defectPins.forEach(pin => {
                    const pinRatioX = parseFloat(pin.getAttribute('data-pin-x'));
                    const pinRatioY = parseFloat(pin.getAttribute('data-pin-y'));

                    if (!Number.isNaN(pinRatioX) && !Number.isNaN(pinRatioY)) {
                        pin.style.left = (imgWidth * pinRatioX) + 'px';
                        pin.style.top = (imgHeight * pinRatioY) + 'px';
                    }
                });
            }

            if (floorPlanImage) {
                if (floorPlanImage.complete) {
                    adjustPinPositions();
                } else {
                    floorPlanImage.addEventListener('load', adjustPinPositions);
                }
                window.addEventListener('resize', adjustPinPositions);
            }

            if (selectedDefectId) {
                highlightDefect(selectedDefectId);
            }

            defectPins.forEach(pin => {
                pin.addEventListener('click', () => {
                    highlightDefect(pin.dataset.defectId);
                });
            });
        });
    </script>
</body>
</html>
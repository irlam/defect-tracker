<?php
// visualize_defects.php

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/logs/visualize_defects.log');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure user is authenticated before allowing access
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/navbar.php';

$floorplan_image = '';
$defects = [];
$pageError = '';
$db = null;
$navbar = null;

try {
    $database = new Database();
    $db = $database->getConnection();

    try {
        $navbar = new Navbar($db, (int) $_SESSION['user_id'], $_SESSION['username'] ?? '');
    } catch (Throwable $navbarException) {
        error_log('Navbar init failure on visualize_defects.php: ' . $navbarException->getMessage());
        $navbar = null;
    }

    // Get selected project and floor plan from request (fallback to sensible defaults)
    $selected_project_id = filter_input(INPUT_GET, 'project_id', FILTER_VALIDATE_INT) ?? 1;
    $selected_floor_plan_id = filter_input(INPUT_GET, 'floor_plan_id', FILTER_VALIDATE_INT) ?? 1;

    $sql = "
        SELECT f.file_path AS floorplan_image,
               d.id AS defect_id,
               d.title,
               d.description,
               d.pin_x,
               d.pin_y
        FROM floor_plans f
        JOIN defects d ON f.id = d.floor_plan_id
        WHERE f.project_id = :project_id
          AND f.id = :floor_plan_id
          AND d.status IN ('open', 'in_progress', 'completed', 'verified', 'rejected', 'accepted')
          AND d.deleted_at IS NULL
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':project_id' => $selected_project_id,
        ':floor_plan_id' => $selected_floor_plan_id,
    ]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($floorplan_image === '') {
            $floorplan_image = (string) ($row['floorplan_image'] ?? '');
        }
        $defects[] = $row;
    }
} catch (Throwable $exception) {
    $pageError = Environment::isDevelopment()
        ? 'Unable to visualise defects: ' . $exception->getMessage()
        : 'Unable to load defect visualisation right now. Please try again later.';
    error_log('visualize_defects.php error: ' . $exception->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualise Defects</title>
    <link rel="icon" type="image/png" href="/favicons/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicons/favicon.svg" />
    <link rel="shortcut icon" href="/favicons/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png" />
    <link rel="manifest" href="/favicons/site.webmanifest" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="/css/app.css?v=20251103" rel="stylesheet">
    <style>
        .visualizer-wrapper {
            padding: clamp(1.5rem, 3vw, 3rem);
        }

        .visualizer-card {
            background: var(--surface-color);
            border: 1px solid rgba(148, 163, 184, 0.25);
            border-radius: var(--border-radius-xl);
            box-shadow: var(--shadow-lg);
            overflow: hidden;
        }

        .visualizer-floorplan {
            position: relative;
            display: inline-block;
        }

        .visualizer-pin {
            position: absolute;
            width: 20px;
            height: 20px;
            background: linear-gradient(135deg, #f87171, #ef4444);
            border-radius: 50%;
            cursor: pointer;
            transform: translate(-50%, -50%);
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.35);
        }

        .visualizer-pin:hover::after {
            content: attr(data-title);
            position: absolute;
            top: -28px;
            left: 50%;
            transform: translateX(-50%);
            background-color: rgba(15, 23, 42, 0.95);
            border: 1px solid rgba(148, 163, 184, 0.4);
            color: var(--text-color);
            padding: 6px 10px;
            border-radius: var(--border-radius-sm);
            white-space: nowrap;
            font-size: 0.75rem;
            box-shadow: var(--shadow-md);
            z-index: 10;
        }
    </style>
</head>
<body class="tool-body" data-bs-theme="dark">
    <?php
    if ($navbar instanceof Navbar) {
        $navbar->render();
    }
    ?>
    <div class="app-content-offset"></div>

    <main class="visualizer-wrapper container-fluid">
        <div class="row justify-content-center">
            <div class="col-12 col-xl-10">
                <div class="visualizer-card p-4 p-md-5">
                    <header class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                        <div>
                            <h1 class="h3 mb-1">Defect Visualiser</h1>
                            <p class="text-muted mb-0">Mapped pins for the selected floor plan</p>
                        </div>
                        <a href="floorplan_selector.php" class="btn btn-outline-light">
                            <i class='bx bx-layer me-1'></i>Choose Floor Plan
                        </a>
                    </header>

                    <?php if ($pageError !== ''): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class='bx bx-error-circle me-1'></i><?php echo htmlspecialchars($pageError); ?>
                        </div>
                    <?php elseif ($floorplan_image !== ''): ?>
                        <div class="visualizer-floorplan w-100">
                            <img id="floorplan-image" class="img-fluid rounded" src="/uploads/floor_plan_images/<?php echo htmlspecialchars($floorplan_image); ?>" alt="Floorplan">
                            <?php foreach ($defects as $defect): ?>
                                <div class="visualizer-pin" style="left: <?php echo htmlspecialchars((string) $defect['pin_x']); ?>px; top: <?php echo htmlspecialchars((string) $defect['pin_y']); ?>px;" data-title="<?php echo htmlspecialchars($defect['title'] . ': ' . $defect['description']); ?>"></div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info" role="alert">
                            <i class='bx bx-info-circle me-1'></i>No floorplan image available for the selected filters.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
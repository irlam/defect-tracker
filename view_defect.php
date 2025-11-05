<?php
/**
 * view_defect.php
 * View and manage individual defect details
 * Current Date and Time (UTC): 2025-01-30 15:12:02
 * Current User's Login: irlam
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('INCLUDED', true);

require_once 'config/database.php';
require_once 'config/constants.php'; // Add constants file
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/upload_constants.php'; // Include upload constants
require_once 'includes/navbar.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? '';
$errors = [];
$success = false;
$pageTitle = 'View Defect';
$currentUsername = $_SESSION['username'] ?? '';
$navbar = null;
$debugEnabled = defined('DEBUG') ? (bool) constant('DEBUG') : false;

// Get defect ID from URL
$defectId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$defectId) {
    header('Location: defects.php');
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    date_default_timezone_set('Europe/London');

    $navbar = new Navbar($db, $userId, $currentUsername);

    // Fetch defect details and related information
    $stmt = $db->prepare("
        SELECT 
            d.*,
            p.name AS project_name,
            fp.floor_name,
            fp.level AS floor_level,
            fp.file_path AS floor_plan_path,
            c.company_name AS contractor_name,
            c.trade AS contractor_trade,
            u1.username AS reported_by_user,
            u2.username AS assigned_by_user,
            u3.username AS updated_by_user
        FROM defects d
        LEFT JOIN projects p ON d.project_id = p.id
        LEFT JOIN floor_plans fp ON d.floor_plan_id = fp.id
        LEFT JOIN contractors c ON d.assigned_to = c.id
        LEFT JOIN users u1 ON d.reported_by = u1.id
        LEFT JOIN users u2 ON d.created_by = u2.id
        LEFT JOIN users u3 ON d.updated_by = u3.id
        WHERE d.id = :id AND d.deleted_at IS NULL
    ");
    $stmt->execute([':id' => $defectId]);
    $defect = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$defect) {
        throw new Exception("Defect not found.");
    }

    // Format dates for display
    $defect['created_at'] = new DateTime($defect['created_at']);
    $defect['updated_at'] = new DateTime($defect['updated_at']);

    // Get defect images
    $attachmentPaths = json_decode($defect['attachment_paths'] ?? '[]', true);
    $images = [];
    foreach ($attachmentPaths as $path) {
        // Ensure the path is properly formatted for display
        $images[] = SITE_URL . '/' . ltrim($path, '/');
    }

    // Format pin image path
    if (!empty($defect['pin_image_path'])) {
        $defect['pin_image_url'] = SITE_URL . '/' . ltrim($defect['pin_image_path'], '/');
    }

    // Get defect history
    $historyStmt = $db->prepare("
        SELECT 
            dh.*,
            u.username AS updated_by_user
        FROM defect_history dh
        LEFT JOIN users u ON dh.updated_by = u.id
        WHERE dh.defect_id = :defect_id
        ORDER BY dh.created_at DESC
    ");
    $historyStmt->execute([':defect_id' => $defectId]);
    $history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

    // Format history dates
    foreach ($history as &$record) {
        $record['created_at'] = new DateTime($record['created_at']);
    }
    unset($record);

} catch (Exception $e) {
    error_log("Error in view_defect.php: " . $e->getMessage());
    $_SESSION['error_message'] = "An error occurred while retrieving the defect details.";
    header('Location: defects.php');
    exit;
}

$statusLabel = ucfirst(str_replace('_', ' ', $defect['status'] ?? 'Unknown'));
$priorityLabel = ucfirst($defect['priority'] ?? 'Unknown');
$projectLabel = $defect['project_name'] ?? 'Unassigned Project';
$floorName = $defect['floor_name'] ?? '';
$floorLevel = $defect['floor_level'] ?? '';
$floorDisplay = $floorName !== '' ? $floorName : 'Unassigned Floor';
if ($floorLevel !== '' && $floorLevel !== null) {
    if ($floorName !== '') {
        $floorDisplay .= ' / Level ' . (string) $floorLevel;
    } else {
        $floorDisplay .= ' Level ' . (string) $floorLevel;
    }
}

$reportedBy = $defect['reported_by_user'] ?? 'System';
$assignedBy = $defect['assigned_by_user'] ?? '—';
$updatedBy = $defect['updated_by_user'] ?? '—';
$contractorName = $defect['contractor_name'] ?? 'Unassigned Contractor';
$contractorTrade = $defect['contractor_trade'] ?? '';
$contractorDisplay = trim($contractorTrade) !== ''
    ? sprintf('%s / %s', $contractorName, $contractorTrade)
    : $contractorName;

$createdAtFormatted = $defect['created_at'] instanceof DateTime ? $defect['created_at']->format('d/m/Y H:i') : '—';
$updatedAtFormatted = $defect['updated_at'] instanceof DateTime ? $defect['updated_at']->format('d/m/Y H:i') : '—';

$hasPinImage = !empty($defect['pin_image_url']);
$hasGalleryImages = !empty($images);
$hasHistory = !empty($history);
$galleryImageCount = is_array($images) ? count($images) : 0;
$historyCount = is_array($history) ? count($history) : 0;

$defectReference = '#' . (int) ($defect['id'] ?? 0);
$defectTitle = $defect['title'] ?? 'Untitled Defect';
$rawDescription = trim((string)($defect['description'] ?? ''));
$hasDescription = $rawDescription !== '';
$defectDescription = $hasDescription ? $rawDescription : 'No description has been provided for this defect yet.';

$projectSummary = $projectLabel;
if (trim($floorName) !== '') {
    $projectSummary .= ' / ' . $floorName;
}
if ($floorLevel !== '' && $floorLevel !== null) {
    $projectSummary .= ' / Level ' . (string) $floorLevel;
}

$statusColorClass = getStatusColor($defect['status']);
$priorityColorClass = getPriorityColor($defect['priority']);
$statusColorClass = $statusColorClass ?: 'secondary';
$priorityColorClass = $priorityColorClass ?: 'secondary';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Detailed summary for <?php echo htmlspecialchars($defectReference . ' ' . $defectTitle); ?> at <?php echo htmlspecialchars($projectLabel); ?>">
    <title><?php echo htmlspecialchars($pageTitle); ?> - Defect Tracker</title>
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="css/app.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="/favicons/favicon-96x96.png" sizes="96x96">
    <link rel="icon" type="image/svg+xml" href="/favicons/favicon.svg">
    <link rel="shortcut icon" href="/favicons/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png">
    <link rel="manifest" href="/favicons/site.webmanifest">
    <style>
        .defect-hero {
            position: relative;
            overflow: hidden;
            padding: clamp(1.75rem, 3.5vw, 2.75rem);
            border-radius: var(--border-radius-lg);
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.28), rgba(14, 165, 233, 0.18));
            border: 1px solid rgba(148, 163, 184, 0.2);
            box-shadow: 0 35px 80px -60px rgba(14, 165, 233, 0.55);
        }

        .defect-hero::after {
            content: "";
            position: absolute;
            inset: auto -40% -80% 40%;
            height: 120%;
            background: radial-gradient(circle at top, rgba(34, 211, 238, 0.35), transparent 65%);
            opacity: 0.6;
        }

        .defect-hero__icon {
            flex: 0 0 auto;
            width: clamp(58px, 6vw, 70px);
            height: clamp(58px, 6vw, 70px);
            border-radius: 22px;
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.95), rgba(14, 165, 233, 0.85));
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: clamp(1.75rem, 2.8vw, 2.25rem);
            color: var(--white);
            box-shadow: 0 24px 45px -32px rgba(37, 99, 235, 0.75);
        }

        .defect-hero__title {
            font-weight: 600;
            font-size: clamp(1.75rem, 4vw, 2.55rem);
            color: var(--white);
        }

        .defect-badge {
            font-size: 0.85rem;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            padding: 0.45rem 0.9rem;
            font-weight: 600;
        }

        .defect-badge--outline {
            background: rgba(34, 211, 238, 0.12);
            border: 1px solid currentColor;
        }

        .defect-meta {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: var(--spacing-sm);
        }

        .defect-meta__item {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: var(--border-radius-md);
            padding: var(--spacing-sm) var(--spacing-md);
            box-shadow: 0 20px 35px -35px rgba(37, 99, 235, 0.75);
        }

        .defect-meta__label {
            display: block;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-muted-color);
            margin-bottom: 0.3rem;
        }

        .defect-meta__value {
            font-weight: 600;
            color: var(--text-color);
        }

        .defect-meta__hint {
            display: block;
            margin-top: 0.3rem;
            font-size: 0.75rem;
            color: var(--text-muted-color);
        }

        .glass-panel {
            background: linear-gradient(135deg, rgba(22, 33, 61, 0.92), rgba(15, 23, 42, 0.78));
            border: 1px solid rgba(148, 163, 184, 0.14);
            border-radius: var(--border-radius-lg);
            backdrop-filter: blur(14px);
            box-shadow: 0 38px 80px -60px rgba(14, 165, 233, 0.5);
        }

        .glass-panel .card-header {
            border-bottom: 1px solid rgba(148, 163, 184, 0.12);
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.16), rgba(14, 165, 233, 0.08));
            padding: clamp(1rem, 2vw, 1.4rem);
        }

        .glass-panel .card-body {
            padding: clamp(1.25rem, 2vw, 1.75rem);
        }

        .section-heading {
            letter-spacing: 0.08em;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-muted-color);
        }

        .defect-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: var(--spacing-sm);
            margin-bottom: var(--spacing-lg);
        }

        .defect-info {
            background: rgba(15, 23, 42, 0.55);
            border: 1px solid rgba(148, 163, 184, 0.12);
            border-radius: var(--border-radius-md);
            padding: var(--spacing-md);
        }

        .defect-info__label {
            display: block;
            font-size: 0.75rem;
            text-transform: uppercase;
            color: var(--text-muted-color);
            letter-spacing: 0.08em;
            margin-bottom: 0.35rem;
        }

        .defect-info__value {
            font-weight: 600;
            color: var(--text-color);
            word-break: break-word;
        }

        .defect-map {
            position: relative;
            border-radius: var(--border-radius-lg);
            overflow: hidden;
            border: 1px solid rgba(148, 163, 184, 0.18);
            background: var(--surface-muted);
        }

        .defect-map__image {
            display: block;
            width: 100%;
            height: auto;
            transition: transform var(--transition-base);
        }

        .defect-map:hover .defect-map__image {
            transform: scale(1.02);
        }

        .defect-map__cta {
            position: absolute;
            right: var(--spacing-md);
            bottom: var(--spacing-md);
            border-radius: 999px;
            padding: 0.4rem 1rem;
            border: 1px solid rgba(148, 163, 184, 0.35);
            background: rgba(15, 23, 42, 0.85);
            color: var(--white);
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            backdrop-filter: blur(10px);
            transition: transform var(--transition-fast), background var(--transition-fast);
        }

        .defect-map__cta:hover,
        .defect-map__cta:focus {
            transform: translateY(-2px);
            background: rgba(37, 99, 235, 0.9);
            color: var(--white);
        }

        .defect-media-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: var(--spacing-sm);
        }

        .defect-media-tile {
            position: relative;
            border: none;
            padding: 0;
            border-radius: var(--border-radius-lg);
            overflow: hidden;
            cursor: pointer;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(148, 163, 184, 0.12);
            transition: transform var(--transition-base), box-shadow var(--transition-base);
        }

        .defect-media-tile:focus {
            outline: 2px solid var(--secondary-color);
            outline-offset: 3px;
        }

        .defect-media-tile:hover {
            transform: translateY(-3px);
            box-shadow: 0 22px 38px -28px rgba(14, 165, 233, 0.55);
        }

        .defect-media-tile img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: transform var(--transition-base);
        }

        .defect-media-tile:hover img {
            transform: scale(1.05);
        }

        .defect-media-tile__badge {
            position: absolute;
            top: 0.65rem;
            left: 0.65rem;
            background: rgba(15, 23, 42, 0.75);
            border-radius: 999px;
            padding: 0.25rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--white);
            border: 1px solid rgba(148, 163, 184, 0.18);
            backdrop-filter: blur(6px);
        }

        .defect-timeline {
            position: relative;
            padding-left: 1.5rem;
        }

        .defect-timeline::before {
            content: "";
            position: absolute;
            left: 0.4rem;
            top: 0.3rem;
            bottom: 0.3rem;
            width: 2px;
            background: linear-gradient(180deg, rgba(37, 99, 235, 0.6), rgba(14, 165, 233, 0.2));
        }

        .defect-timeline__item {
            position: relative;
            padding: var(--spacing-md);
            margin-bottom: var(--spacing-sm);
            border-radius: var(--border-radius-md);
            background: rgba(15, 23, 42, 0.55);
            border: 1px solid rgba(148, 163, 184, 0.12);
            box-shadow: 0 18px 32px -28px rgba(14, 165, 233, 0.55);
        }

        .defect-timeline__item::before {
            content: "";
            position: absolute;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--secondary-color);
            left: -0.95rem;
            top: 1.2rem;
            box-shadow: 0 0 0 4px rgba(34, 211, 238, 0.18);
        }

        .defect-timeline__timestamp {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-muted-color);
            margin-bottom: 0.35rem;
        }

        .defect-timeline__meta {
            font-size: 0.8rem;
            color: var(--text-muted-color);
        }

        .defect-fact-list {
            display: grid;
            gap: var(--spacing-sm);
        }

        .defect-fact {
            padding: var(--spacing-md);
            border-radius: var(--border-radius-md);
            background: rgba(15, 23, 42, 0.55);
            border: 1px solid rgba(148, 163, 184, 0.12);
        }

        .defect-fact dt {
            margin: 0 0 0.35rem;
            font-size: 0.8rem;
            color: var(--text-muted-color);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .defect-fact dd {
            margin: 0;
            font-weight: 600;
            color: var(--text-color);
            word-break: break-word;
        }

        .defect-actions .btn {
            padding: 0.75rem;
            font-weight: 600;
            border-radius: var(--border-radius-md);
        }

        .defect-actions .btn-primary {
            border: none;
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.95), rgba(14, 165, 233, 0.9));
        }

        .defect-actions .btn-primary:hover {
            background: linear-gradient(135deg, rgba(37, 99, 235, 1), rgba(14, 165, 233, 1));
        }

        .defect-actions .btn-outline-danger {
            border-width: 1px;
        }

        .quick-access-card {
            text-align: center;
        }

        .quick-access-card img {
            border-radius: var(--border-radius-md);
            border: 1px solid rgba(148, 163, 184, 0.18);
            padding: 0.75rem;
            background: rgba(15, 23, 42, 0.6);
        }

        .quick-access-card p {
            margin-bottom: 0;
            color: var(--text-muted-color);
        }

        .alert-glow {
            border: 1px solid rgba(34, 211, 238, 0.45);
            box-shadow: 0 26px 50px -40px rgba(34, 211, 238, 0.55);
        }

        @media (max-width: 991.98px) {
            .defect-hero {
                padding: var(--spacing-lg);
            }

            .defect-meta {
                grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            }
        }
    </style>
</head>
<body class="tool-body has-app-navbar" data-bs-theme="dark">
    <?php if ($navbar instanceof Navbar) { $navbar->render(); } ?>

    <main class="tool-page container-xxl py-4 py-lg-5">
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-glow alert-dismissible fade show mb-4" role="alert">
                <i class="bx bx-check-circle me-2"></i>
                <?php echo htmlspecialchars($_SESSION['success_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <section class="defect-hero mb-5">
            <div class="row g-4 align-items-start">
                <div class="col-lg-8">
                    <div class="d-flex gap-3 align-items-start">
                        <div class="defect-hero__icon">
                            <i class="bx bx-wrench"></i>
                        </div>
                        <div class="defect-hero__content">
                            <div class="defect-hero__badges d-flex flex-wrap align-items-center gap-2 mb-2">
                                <span class="badge rounded-pill defect-badge bg-<?php echo htmlspecialchars($statusColorClass); ?>">
                                    <i class="bx bx-bullseye me-1"></i><?php echo htmlspecialchars($statusLabel); ?>
                                </span>
                                <span class="badge rounded-pill defect-badge defect-badge--outline border-<?php echo htmlspecialchars($priorityColorClass); ?> text-<?php echo htmlspecialchars($priorityColorClass); ?>">
                                    <i class="bx bx-bar-chart-alt me-1"></i>Priority <?php echo htmlspecialchars($priorityLabel); ?>
                                </span>
                            </div>
                            <h1 class="defect-hero__title mb-2">
                                <?php echo htmlspecialchars($defectReference . ' - ' . $defectTitle); ?>
                            </h1>
                            <p class="text-muted mb-0">
                                <i class="bx bx-map-pin me-1"></i>
                                <?php echo htmlspecialchars($projectSummary); ?>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="defect-meta">
                        <div class="defect-meta__item">
                            <span class="defect-meta__label">Reported By</span>
                            <span class="defect-meta__value"><?php echo htmlspecialchars($reportedBy); ?></span>
                        </div>
                        <div class="defect-meta__item">
                            <span class="defect-meta__label">Created</span>
                            <span class="defect-meta__value"><?php echo htmlspecialchars($createdAtFormatted); ?></span>
                        </div>
                        <div class="defect-meta__item">
                            <span class="defect-meta__label">Last Updated</span>
                            <span class="defect-meta__value"><?php echo htmlspecialchars($updatedAtFormatted); ?></span>
                            <span class="defect-meta__hint">by <?php echo htmlspecialchars($updatedBy); ?></span>
                        </div>
                        <div class="defect-meta__item">
                            <span class="defect-meta__label">Timeline</span>
                            <span class="defect-meta__value"><?php echo number_format($historyCount); ?> event<?php echo $historyCount === 1 ? '' : 's'; ?></span>
                            <span class="defect-meta__hint"><?php echo number_format($galleryImageCount); ?> supporting image<?php echo $galleryImageCount === 1 ? '' : 's'; ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div class="row g-4 align-items-start">
            <div class="col-12 col-xl-8">
                <div class="card glass-panel shadow-none">
                    <div class="card-header d-flex flex-wrap justify-content-between align-items-start gap-3">
                        <div>
                            <h2 class="card-title h5 mb-1">Defect Overview</h2>
                            <p class="text-muted small mb-0">A snapshot of what needs attention and who is involved.</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <section class="mb-4">
                            <h3 class="section-heading mb-2">Description</h3>
                            <p class="mb-0 <?php echo $hasDescription ? '' : 'text-muted'; ?>">
                                <?php echo nl2br(htmlspecialchars($defectDescription)); ?>
                            </p>
                        </section>

                        <div class="defect-info-grid">
                            <div class="defect-info">
                                <span class="defect-info__label">Assigned Contractor</span>
                                <span class="defect-info__value"><?php echo htmlspecialchars($contractorDisplay); ?></span>
                            </div>
                            <div class="defect-info">
                                <span class="defect-info__label">Assigned By</span>
                                <span class="defect-info__value"><?php echo htmlspecialchars($assignedBy); ?></span>
                            </div>
                            <div class="defect-info">
                                <span class="defect-info__label">Reported By</span>
                                <span class="defect-info__value"><?php echo htmlspecialchars($reportedBy); ?></span>
                            </div>
                            <div class="defect-info">
                                <span class="defect-info__label">Last Updated By</span>
                                <span class="defect-info__value"><?php echo htmlspecialchars($updatedBy); ?></span>
                            </div>
                        </div>

                        <?php if ($hasPinImage): ?>
                            <section class="<?php echo $hasGalleryImages ? 'mb-4' : ''; ?>">
                                <h3 class="section-heading mb-2">Pin Location</h3>
                                <div class="defect-map">
                                    <img src="<?php echo htmlspecialchars($defect['pin_image_url']); ?>" alt="Pin location for <?php echo htmlspecialchars($defectReference); ?>" class="defect-map__image">
                                    <button type="button" class="defect-map__cta" onclick="openImageModal(<?php echo json_encode($defect['pin_image_url']); ?>, 'Pin Location');">
                                        <i class="bx bx-fullscreen me-1"></i> View full
                                    </button>
                                </div>
                            </section>
                        <?php endif; ?>

                        <?php if ($hasGalleryImages): ?>
                            <section>
                                <h3 class="section-heading mb-2">Supporting Images</h3>
                                <div class="defect-media-grid">
                                    <?php foreach ($images as $index => $image): ?>
                                        <button type="button"
                                                class="defect-media-tile"
                                                onclick="openImageModal(<?php echo json_encode($image); ?>, <?php echo json_encode('Defect Image ' . ($index + 1)); ?>);"
                                                aria-label="Open defect image <?php echo (int)($index + 1); ?> in full screen">
                                            <img src="<?php echo htmlspecialchars($image); ?>" alt="Defect image <?php echo (int)($index + 1); ?>">
                                            <span class="defect-media-tile__badge">#<?php echo (int)($index + 1); ?></span>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($hasHistory): ?>
                    <div class="card glass-panel shadow-none mt-4">
                        <div class="card-header">
                            <h2 class="card-title h5 mb-0">Activity History</h2>
                        </div>
                        <div class="card-body">
                            <div class="defect-timeline">
                                <?php foreach ($history as $record): ?>
                                    <article class="defect-timeline__item">
                                        <span class="defect-timeline__timestamp"><?php echo $record['created_at'] instanceof DateTime ? $record['created_at']->format('d/m/Y H:i') : ''; ?></span>
                                        <p class="mb-1"><?php echo nl2br(htmlspecialchars($record['description'] ?? '')); ?></p>
                                        <span class="defect-timeline__meta">by <?php echo htmlspecialchars($record['updated_by_user'] ?? 'System'); ?></span>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="col-12 col-xl-4">
                <div class="card glass-panel shadow-none mb-4">
                    <div class="card-header">
                        <h2 class="card-title h6 mb-0">Quick Facts</h2>
                    </div>
                    <div class="card-body">
                        <dl class="defect-fact-list">
                            <div class="defect-fact">
                                <dt><i class="bx bx-hash text-secondary"></i>Defect Reference</dt>
                                <dd><?php echo htmlspecialchars($defectReference); ?></dd>
                            </div>
                            <div class="defect-fact">
                                <dt><i class="bx bx-briefcase text-secondary"></i>Project</dt>
                                <dd><?php echo htmlspecialchars($projectLabel); ?></dd>
                            </div>
                            <div class="defect-fact">
                                <dt><i class="bx bx-current-location text-secondary"></i>Location</dt>
                                <dd><?php echo htmlspecialchars($floorDisplay); ?></dd>
                            </div>
                            <div class="defect-fact">
                                <dt><i class="bx bx-user-check text-secondary"></i>Assigned Contractor</dt>
                                <dd><?php echo htmlspecialchars($contractorDisplay); ?></dd>
                            </div>
                            <div class="defect-fact">
                                <dt><i class="bx bx-user-voice text-secondary"></i>Assigned By</dt>
                                <dd><?php echo htmlspecialchars($assignedBy); ?></dd>
                            </div>
                            <div class="defect-fact">
                                <dt><i class="bx bx-user-pin text-secondary"></i>Reported By</dt>
                                <dd><?php echo htmlspecialchars($reportedBy); ?></dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <?php if ($userRole === 'admin' || $userRole === 'manager'): ?>
                    <div class="card glass-panel shadow-none defect-actions mb-4">
                        <div class="card-header">
                            <h2 class="card-title h6 mb-0">Actions</h2>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editDefectModal">
                                    <i class="fas fa-edit me-2"></i> Update Status
                                </button>
                                <button type="button" class="btn btn-outline-danger" onclick="confirmDelete(<?php echo (int)$defectId; ?>);">
                                    <i class="fas fa-trash me-2"></i> Delete Defect
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="card glass-panel shadow-none quick-access-card">
                    <div class="card-header">
                        <h2 class="card-title h6 mb-0">Quick Access</h2>
                    </div>
                    <div class="card-body">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&amp;data=<?php echo urlencode(SITE_URL . '/view_defect.php?id=' . $defectId); ?>" alt="QR code linking to this defect" class="img-fluid mb-3">
                        <p>Scan to open this record on a mobile device.</p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Image Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="imageModalLabel"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalImage" src="" alt="" class="img-fluid">
                </div>
            </div>
        </div>
    </div>

    <?php if ($userRole === 'admin' || $userRole === 'manager'): ?>
        <!-- Edit Status Modal -->
        <div class="modal fade" id="editDefectModal" tabindex="-1" aria-hidden="true">
            <!-- ... (Edit modal content) ... -->
        </div>
    <?php endif; ?>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.4.16/dist/sweetalert2.min.js"></script>
    <script>
        /**
         * view_defect.js
         * JavaScript for View Defect Page
         * Current Date and Time (UTC): 2025-01-30 15:14:22
         * Current User's Login: irlam
         */

        // Debug mode and constants
        const DEBUG = <?php echo $debugEnabled ? 'true' : 'false'; ?>;
        const CURRENT_USER = '<?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>';
        const CURRENT_TIMESTAMP = '<?php echo date('Y-m-d H:i:s'); ?>';
        const SITE_URL = '<?php echo SITE_URL; ?>';

        // Image modal handling
        function openImageModal(src, title) {
            const modal = document.getElementById('imageModal');
            const modalImage = document.getElementById('modalImage');
            const modalTitle = document.getElementById('imageModalLabel');

            modalImage.src = src;
            modalTitle.textContent = title;

            new bootstrap.Modal(modal).show();
        }

        // Delete confirmation
        function confirmDelete(defectId) {
            Swal.fire({
                title: 'Are you sure?',
                text: "This will permanently delete this defect and all associated data.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    deleteDefect(defectId);
                }
            });
        }

        // Delete defect
        const editForm = document.getElementById('editDefectForm');
        if (editForm) {
            editForm.addEventListener('submit', handleFormSubmit);
        }

        function handleFormSubmit(e) {
            Swal.push(e.target);

            try {
                const formData = new FormData(editForm);

                if (FormData.isNotEmpty(formData)) {
                    const closureImageDiv = document.getElementById('closureImageDiv');
                    const rejectionCommentDiv = document.getElementById('rejectionCommentDiv');

                    editForm.reset();

                    const statusSelect = document.getElementById('status');
                    if (statusSelect && statusSelect.value) {
                        updateFormFields(statusSelect.value);
                    }

                    deleteDefect(formData.get('defect_id').toString());
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: `Failed to process form submission. Please try again later.`,
                    showConfirmButton: false
                });
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const statusSelect = document.getElementById('status');

            if (statusSelect) {
                statusSelect.addEventListener('change', function() {
                    updateFormFields(this.value);
                });
            }

            if (statusSelect && statusSelect.value) {
                updateFormFields(statusSelect.value);
            }

            const editFormInstance = document.getElementById('editDefectForm');
            if (editFormInstance) {
                editFormInstance.addEventListener('submit', handleFormSubmit);
            }
        });

        function updateFormFields(status) {
            const closureImageDiv = document.getElementById('closureImageDiv');
            const rejectionCommentDiv = document.getElementById('rejectionCommentDiv');
            const closureImage = document.getElementById('closureImage');
            const rejectionComment = document.getElementById('rejectionComment');

            closureImageDiv.style.display = 'none';
            rejectionCommentDiv.style.display = 'none';
            closureImage.required = false;
            rejectionComment.required = false;

            if (status === 'closed') {
                closureImageDiv.style.display = 'block';
                closureImage.required = true;
            } else if (status === 'rejected') {
                rejectionCommentDiv.style.display = 'block';
                rejectionComment.required = true;
            }
        }

        function deleteDefect(defectId) {
            const formData = new FormData();
            formData.append('action', 'delete_defect');
            formData.append('defect_id', defectId);

            fetch('delete_defect.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Deleted!',
                        text: 'The defect has been deleted.',
                        showConfirmButton: false,
                        timer: 1500
                    }).then(() => {
                        window.location.href = 'defects.php';
                    });
                } else {
                    throw new Error(data.message || 'Failed to delete defect.');
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message || 'An error occurred while deleting the defect.',
                    showConfirmButton: false
                });
            });
        }
    </script>
</body>
</html>
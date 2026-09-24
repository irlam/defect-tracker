<?php
/**
 * view_defect.php
 * View and manage individual defect details
 * Current Date and Time (UTC): 2025-01-30 15:12:02
 * Current User's Login: irlam
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');

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

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$userId = (int)$_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? '';
$errors = [];
$success = false;
$pageTitle = 'View Defect';
$currentUsername = $_SESSION['username'] ?? '';
$navbar = null;
$debugEnabled = defined('DEBUG') ? (bool) constant('DEBUG') : false;

if (!function_exists('getPriorityColor')) {
    function getPriorityColor(string $priority): string
    {
        switch (strtolower($priority)) {
            case 'high':
                return 'danger';
            case 'medium':
                return 'warning';
            case 'low':
                return 'success';
            default:
                return 'secondary';
        }
    }
}

if (!function_exists('buildMediaUrl')) {
    function buildMediaUrl(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        $trimmed = trim($path);
        if ($trimmed === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $trimmed) === 1) {
            return $trimmed;
        }

        $normalized = ltrim($trimmed, '/');
        $normalized = str_replace(['../', './'], '', $normalized);

        return SITE_URL . '/' . $normalized;
    }
}

if (!function_exists('isDisplayableImage')) {
    function isDisplayableImage(?string $url): bool
    {
        if (!$url) {
            return false;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (!$path) {
            return false;
        }

        return (bool) preg_match('/\.(?:png|jpe?g|gif|webp|bmp)$/i', $path);
    }
}

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
        $normalized = buildMediaUrl(is_string($path) ? $path : null);
        if ($normalized && !in_array($normalized, $images, true)) {
            $images[] = $normalized;
        }
    }

    $imageStmt = $db->prepare("SELECT file_path FROM defect_images WHERE defect_id = :defect_id AND file_path IS NOT NULL AND file_path <> '' ORDER BY created_at ASC");
    $imageStmt->execute([':defect_id' => $defectId]);
    $rowImages = $imageStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    foreach ($rowImages as $path) {
        $normalized = buildMediaUrl($path);
        if ($normalized && !in_array($normalized, $images, true)) {
            $images[] = $normalized;
        }
    }

    // Format pin image path
    if (!empty($defect['pin_image_path'])) {
        $defect['pin_image_url'] = buildMediaUrl($defect['pin_image_path']);
    }

    $closureImageUrl = buildMediaUrl($defect['closure_image'] ?? null);

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
$assignedBy = $defect['assigned_by_user'] ?? 'N/A';
$updatedBy = $defect['updated_by_user'] ?? 'N/A';
$contractorName = $defect['contractor_name'] ?? 'Unassigned Contractor';
$contractorTrade = $defect['contractor_trade'] ?? '';
$contractorDisplay = trim($contractorTrade) !== ''
    ? sprintf('%s / %s', $contractorName, $contractorTrade)
    : $contractorName;

$createdAtFormatted = $defect['created_at'] instanceof DateTime ? $defect['created_at']->format('d/m/Y H:i') : 'â€”';
$updatedAtFormatted = $defect['updated_at'] instanceof DateTime ? $defect['updated_at']->format('d/m/Y H:i') : 'â€”';

$hasPinImage = !empty($defect['pin_image_url']);
$floorPlanUrl = buildMediaUrl($defect['floor_plan_path'] ?? null);
if (!isDisplayableImage($floorPlanUrl)) {
    $floorPlanUrl = null;
}

if (!$floorPlanUrl && !empty($images)) {
    foreach ($images as $candidateImage) {
        if (!isDisplayableImage($candidateImage)) {
            continue;
        }

        $candidateName = strtolower(basename(parse_url($candidateImage, PHP_URL_PATH) ?? ''));
        if (strpos($candidateName, 'floor') !== false || strpos($candidateName, 'plan') !== false || strpos($candidateName, 'pin') !== false) {
            $floorPlanUrl = $candidateImage;
            break;
        }
    }
}

if (!$floorPlanUrl && $hasPinImage) {
    $floorPlanUrl = $defect['pin_image_url'];
}

$hasFloorPlan = !empty($floorPlanUrl);
$showSeparatePinImage = $hasPinImage && (!$hasFloorPlan || $defect['pin_image_url'] !== $floorPlanUrl);

$pinX = $defect['pin_x'] ?? null;
$pinY = $defect['pin_y'] ?? null;
$hasPinCoordinates = $hasFloorPlan && is_numeric($pinX) && is_numeric($pinY);
$pinLeftPercent = $hasPinCoordinates ? max(0, min(100, (float)$pinX * 100)) : null;
$pinTopPercent = $hasPinCoordinates ? max(0, min(100, (float)$pinY * 100)) : null;

$hasGalleryImages = !empty($images);
$hasClosureImage = !empty($closureImageUrl);
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

        .print-action {
            align-self: flex-start;
        }

        .print-action .btn {
            border-radius: var(--border-radius-md);
            font-weight: 600;
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
            grid-template-columnsç½÷¶‰žËkºwµçqÍÁ•¥…±¡…ÉÌ ‘™±½½ÉA±…¹UÉ°°9Q}EU=QL¤ì€üøˆ…±Ðô‰±½½ÈÁ±…¸™½È€ðýÁ¡À•¡¼¡Ñµ±ÍÁ•¥…±¡…ÉÌ ‘ÁÉ½©•ÑMÕµµ…Éä¤ì€üøˆ±…ÍÌô‰‘•™•Ðµµ…Á}}¥µ…”ˆø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ðýÁ¡À¥˜€ ‘¡…ÍA¥¹½½É‘¥¹…Ñ•Ì¤è€üø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñÍÁ…¸±…ÍÌô‰™±½½ÉÁ±…¸µÁ¥¸ˆÍÑå±”ô‰±•™Ðè€ðýÁ¡À•¡¼¡Ñµ±ÍÁ•¥…±¡…ÉÌ¡¹Õµ‰•É}™½Éµ…Ð ¡™±½…Ð¤‘Á¥¹1•™ÑA•É•¹Ð°€È¤¤ì€üø”ìÑ½Àè€ðýÁ¡À•¡¼¡Ñµ±ÍÁ•¥…±¡…ÉÌ¡¹Õµ‰•É}™½Éµ…Ð ¡™±½…Ð¤‘Á¥¹Q½ÁA•É•¹Ð°€È¤¤ì€üø”ìˆ…É¥„µ±…‰•°ô‰•™•ÐÁ¥¸±½…Ñ¥½¸ˆøð½ÍÁ…¸ø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ðýÁ¡À•¹‘¥˜ì€üø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ‰ÕÑÑ½¸ÑåÁ”ô‰‰ÕÑÑ½¸ˆ±…ÍÌô‰‘•™•Ðµµ…Á}}Ñ„ˆ½¹±¥¬ô‰½Á•¹%µ…•5½‘…° œðýÁ¡À•¡¼¡Ñµ±ÍÁ•¥…±¡…ÉÌ ‘™±½½ÉA±…¹UÉ°°9Q}EU=QL¤ì€üøœ°€±½½ÈA±…¸œ¤ìˆø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ¤±…ÍÌô‰‰à‰àµ™Õ±±ÍÉ••¸µ”´Äˆøð½¤øY¥•Ü™Õ±°4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ð½‰ÕÑÑ½¸ø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ð½‘¥Øø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ðýÁ¡À•¹‘¥˜ì€üø4(4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ðýÁ¡À¥˜€ ‘Í¡½ÝM•Á…É…Ñ•A¥¹%µ…”¤è€üø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ‘¥Ø±…ÍÌô‰‘•™•Ðµµ…Àˆø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ¥µœÍÉŒôˆðýÁ¡À•¡¼¡Ñµ±ÍÁ•¥…±¡…ÉÌ ‘‘•™•ÑlÁ¥¹}¥µ…•}ÕÉ°t°9Q}EU=QL¤ì€üøˆ…±Ðô‰A¥¸±½…Ñ¥½¸™½È€ðýÁ¡À•¡¼¡Ñµ±ÍÁ•¥…±¡…ÉÌ ‘‘•™•ÑI•™•É•¹”¤ì€üøˆ±…ÍÌô‰‘•™•Ðµµ…Á}}¥µ…”ˆø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ‰ÕÑÑ½¸ÑåÁ”ô‰‰ÕÑÑ½¸ˆ±…ÍÌô‰‘•™•Ðµµ…Á}}Ñ„ˆ½¹±¥¬ô‰½Á•¹%µ…•5½‘…° œðýÁ¡À•¡¼¡Ñµ±ÍÁ•¥…±¡…ÉÌ ‘‘•™•ÑlÁ¥¹}¥µ…•}ÕÉ°t°9Q}EU=QL¤ì€üøœ°€A¥¸1½…Ñ¥½¸œ¤ìˆø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ¤±…ÍÌô‰‰à‰àµ™Õ±±ÍÉ••¸µ”´Äˆøð½¤øY¥•Ü™Õ±°4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ð½‰ÕÑÑ½¸ø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ð½‘¥Øø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ðýÁ¡À•¹‘¥˜ì€üø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€ð½Í•Ñ¥½¸ø4(€€€€€€€€€€€€€€€€€€€€€€€€ðýÁ¡À•¹‘¥˜ì€üø4(4(€€€€€€€€€€€€€€€€€€€€€€€€ðýÁ¡À¥˜€ ‘¡…Í±½ÍÕÉ•%µ…”¤è€üø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñÍ•Ñ¥½¸±…ÍÌôˆðýÁ¡À•¡¼€‘¡…Í…±±•Éå%µ…•Ì€ü€µˆ´Ðœ€è€œœì€üøˆø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ Ì±…ÍÌô‰Í•Ñ¥½¸µ¡•…‘¥¹œµˆ´Èˆù½µÁ±•Ñ¥½¸Ù¥‘•¹”ð½ Ìø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ‘¥Ø±…ÍÌô‰‘•™•Ðµµ…Àˆø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ¥µœÍÉŒôˆðýÁ¡À•¡¼¡Ñµ±ÍÁ•¥…±¡…ÉÌ ‘±½ÍÕÉ•%µ…•UÉ°°9Q}EU=QL¤ì€üøˆ…±Ðô‰½µÁ±•Ñ¥½¸•Ù¥‘•¹”™½È€ðýÁ¡À•¡¼¡Ñµ±ÍÁ•¥…±¡…ÉÌ ‘‘•™•ÑI•™•É•¹”¤ì€üøˆ±…ÍÌô‰‘•™•Ðµµ…Á}}¥µ…”ˆø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ‰ÕÑÑ½¸ÑåÁ”ô‰‰ÕÑÑ½¸ˆ±…ÍÌô‰‘•™•Ðµµ…Á}}Ñ„ˆ½¹±¥¬ô‰½Á•¹%µ…•5½‘…° œðýÁ¡À•¡¼¡Ñµ±ÍÁ•¥…±¡…ÉÌ ‘±½ÍÕÉ•%µ…•UÉ°°9Q}EU=QL¤ì€üøœ°€½µÁ±•Ñ¥½¸Ù¥‘•¹”œ¤ìˆø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ¤±…ÍÌô‰‰à‰àµ™Õ±±ÍÉ••¸µ”´Äˆøð½¤øY¥•Ü™Õ±°4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ð½‰ÕÑÑ½¸ø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ð½‘¥Øø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€ð½Í•Ñ¥½¸ø4(€€€€€€€€€€€€€€€€€€€€€€€€ðýÁ¡À•¹‘¥˜ì€üø4(4(€€€€€€€€€€€€€€€€€€€€€€€€ðýÁ¡À¥˜€ ‘¡…Í…±±•Éå%µ…•Ì¤è€üø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñÍ•Ñ¥½¸ø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ Ì±…ÍÌô‰Í•Ñ¥½¸µ¡•…‘¥¹œµˆ´ÈˆùMÕÁÁ½ÉÑ¥¹œ%µ…•Ìð½ Ìø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ‘¥Ø±…ÍÌô‰‘•™•Ðµµ•‘¥„µÉ¥ˆø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ðýÁ¡À™½É•… € ‘¥µ…•Ì…Ì€‘¥¹‘•à€ôø€‘¥µ…”¤è€üø4(€€€€€€€€€€€€€€€€€€€€ñ‰ÕÑÑ½¸ÑåÁ”ô‰‰ÕÑÑ½¸ˆ4(€€€€€€€€€€€€€€€€€€€€€€€±…ÍÌô‰‘•™•Ðµµ•‘¥„µÑ¥±”ˆ4(€€€€€€€€€€€€€€€€€€€€€€€½¹±¥¬ô‰½Á•¹%µ…•5½‘…° œðýÁ¡À•¡¼¡Ñµ±ÍÁ•¥…±¡…ÉÌ ‘¥µ…”°9Q}EU=QL¤ì€üøœ°€•™•Ð%µ…”€ðýÁ¡À•¡¼€¡¥¹Ð¤ ‘¥¹‘•à€¬€Ä¤ì€üøœ¤ìˆ4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€…É¥„µ±…‰•°ô‰=Á•¸‘•™•Ð¥µ…”€ðýÁ¡À•¡¼€¡¥¹Ð¤ ‘¥¹‘•à€¬€Ä¤ì€üø¥¸™Õ±°ÍÉ••¸ˆø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ¥µœÍÉŒôˆðýÁ¡À•¡¼¡Ñµ±ÍÁ•¥…±¡…ÉÌ ‘¥µ…”¤ì€üøˆ…±Ðô‰•™•Ð¥µ…”€ðýÁ¡À•¡¼€¡¥¹Ð¤ ‘¥¹‘•à€¬€Ä¤ì€üøˆø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñÍÁ…¸±…ÍÌô‰‘•™•Ðµµ•‘¥„µÑ¥±•}}‰…‘”ˆøŒðýÁ¡À•¡¼€¡¥¹Ð¤ ‘¥¹‘•à€¬€Ä¤ì€üøð½ÍÁ…¸ø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ð½‰ÕÑÑ½¸ø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ðýÁ¡À•¹‘™½É•… ì€üø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ð½‘¥Øø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€ð½Í•Ñ¥½¸ø4(€€€€€€€€€€€€€€€€€€€€€€€€ðýÁ¡À•¹‘¥˜ì€üø4(€€€€€€€€€€€€€€€€€€€€ð½‘¥Øø4(€€€€€€€€€€€€€€€€ð½‘¥Øø4(4(€€€€€€€€€€€€€€€€ðýÁ¡À¥˜€ ‘¡…Í!¥ÍÑ½Éä¤è€üø4(€€€€€€€€€€€€€€€€€€€€ñ‘¥Ø±…ÍÌô‰…É±…ÍÌµÁ…¹•°Í¡…‘½Üµ¹½¹”µÐ´Ðˆø4(€€€€€€€€€€€€€€€€€€€€€€€€ñ‘¥Ø±…ÍÌô‰…Éµ¡•…‘•Èˆø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ È±…ÍÌô‰…ÉµÑ¥Ñ±” Ôµˆ´ÀˆùÑ¥Ù¥Ñä!¥ÍÑ½Éäð½ Èø4(€€€€€€€€€€€€€€€€€€€€€€€€ð½‘¥Øø4(€€€€€€€€€€€€€€€€€€€€€€€€ñ‘¥Ø±…ÍÌô‰…Éµ‰½‘äˆø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ‘¥Ø±…ÍÌô‰‘•™•ÐµÑ¥µ•±¥¹”ˆø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ðýÁ¡À™½É•… € ‘¡¥ÍÑ½Éä…Ì€‘É•½É¤è€üø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ…ÉÑ¥±”±…ÍÌô‰‘•™•ÐµÑ¥µ•±¥¹•}}¥Ñ•´ˆø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñÍÁ…¸±…ÍÌô‰‘•™•ÐµÑ¥µ•±¥¹•}}Ñ¥µ•ÍÑ…µÀˆøðýÁ¡À•¡¼€‘É•½É‘lÉ•…Ñ•‘}…Ðt¥¹ÍÑ…¹•½˜…Ñ•Q¥µ”€ü€‘É•½É‘lÉ•…Ñ•‘}…Ðt´ù™½Éµ…Ð ½´½d é¤œ¤€è€œœì€üøð½ÍÁ…¸ø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñÀ±…ÍÌô‰µˆ´ÄˆøðýÁ¡À•¡¼¹°É‰È¡¡Ñµ±ÍÁ•¥…±¡…ÉÌ ‘É•½É‘l‘•ÍÉ¥ÁÑ¥½¸t€üü€œœ¤¤ì€üøð½Àø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñÍÁ…¸±…ÍÌô‰‘•™•ÐµÑ¥µ•±¥¹•}}µ•Ñ„ˆù‰ä€ðýÁ¡À•¡¼¡Ñµ±ÍÁ•¥…±¡…ÉÌ ‘É•½É‘lÕÁ‘…Ñ•‘}‰å}ÕÍ•Èt€üü€MåÍÑ•´œ¤ì€üøð½ÍÁ…¸ø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ð½…ÉÑ¥±”ø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ðýÁ¡À•¹‘™½É•… ì€üø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€ð½‘¥Øø4(€€€€€€€€€€€€€€€€€€€€€€€€ð½‘¥Øø4(€€€€€€€€€€€€€€€€€€€€ð½‘¥Øø4(€€€€€€€€€€€€€€€€ðýÁ¡À•¹‘¥˜ì€üø4(€€€€€€€€€€€€ð½‘¥Øø4(4(€€€€€€€€€€€€ñ‘¥Ø±…ÍÌô‰½°´ÄÈ½°µá°´Ðˆø4(€€€€€€€€€€€€€€€€ñ‘¥Ø±…ÍÌô‰…É±…ÍÌµÁ…¹•°Í¡…‘½Üµ¹½¹”µˆ´Ðˆø4(€€€€€€€€€€€€€€€€€€€€ñ‘¥Ø±…ÍÌô‰…Éµ¡•…‘•Èˆø4(€€€€€€€€€€€€€€€€€€€€€€€€ñ È±…ÍÌô‰…ÉµÑ¥Ñ±” Øµˆ´ÀˆùEÕ¥¬…ÑÌð½ Èø4(€€€€€€€€€€€€€€€€€€€€ð½‘¥Øø4(€€€€€€€€€€€€€€€€€€€€ñ‘¥Ø±…ÍÌô‰…Éµ‰½‘äˆø4(€€€€€€€€€€€€€€€€€€€€€€€€ñ‘°±…ÍÌô‰‘•™•Ðµ™…Ðµ±¥ÍÐˆø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ‘¥Ø±…ÍÌô‰‘•™•Ðµ™…Ðˆø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ‘Ðøñ¤±…ÍÌô‰‰à‰àµ¡…Í Ñ•áÐµÍ•½¹‘…Éäˆøð½¤ù•™•ÐI•™•É•¹”ð½‘Ðø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ‘øðýÁ¡À•¡¼¡Ñµ±ÍÁ•¥…±¡…ÉÌ ‘‘•™•ÑI•™•É•¹”¤ì€üøð½‘ø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€ð½‘¥Øø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ‘¥Ø±…ÍÌô‰‘•™•Ðµ™…Ðˆø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ‘Ðøñ¤±…ÍÌô‰‰à‰àµ‰É¥•™…Í”Ñ•áÐµÍ•½¹‘…Éäˆøð½¤ùAÉ½©•Ðð½‘Ðø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ‘øðýÁ¡À•¡¼¡Ñµ±ÍÁ•¥…±¡…ÉÌ ‘ÁÉ½©•Ñ1…‰•°¤ì€üøð½‘ø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€ð½‘¥Øø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ‘¥Ø±…ÍÌô‰‘•™•Ðµ™…Ðˆø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ‘Ðøñ¤±…ÍÌô‰‰à‰àµÕÉÉ•¹Ðµ±½…Ñ¥½¸Ñ•áÐµÍ•½¹‘…Éäˆøð½¤ù1½…Ñ¥½¸ð½‘Ðø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ‘øðýÁ¡À•¡¼¡Ñµ±ÍÁ•¥…±¡…ÉÌ ‘™±½½É¥ÍÁ±…ä¤ì€üøð½‘ø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€ð½‘¥Øø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ‘¥Ø±…ÍÌô‰‘•™•Ðµ™…Ðˆø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ‘Ðøñ¤±…ÍÌô‰‰à‰àµÕÍ•Èµ¡•¬Ñ•áÐµÍ•½¹‘…Éäˆøð½¤ùÍÍ¥¹•½¹ÑÉ…Ñ½Èð½‘Ðø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ‘øðýÁ¡À•¡¼¡Ñµ±ÍÁ•¥…±¡…ÉÌ ‘½¹ÑÉ…Ñ½É¥ÍÁ±…ä¤ì€üøð½‘ø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€ð½‘¥Øø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ‘¥Ø±…ÍÌô‰‘•™•Ðµ™…Ðˆø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ‘Ðøñ¤±…ÍÌô‰‰à‰àµÕÍ•ÈµÙ½¥”Ñ•áÐµÍ•½¹‘…Éäˆøð½¤ùÍÍ¥¹•	äð½‘Ðø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ‘øðýÁ¡À•¡¼¡Ñµ±ÍÁ•¥…±¡…ÉÌ ‘…ÍÍ¥¹•‘	ä¤ì€üøð½‘ø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€ð½‘¥Øø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ‘¥Ø±…ÍÌô‰‘•™•Ðµ™…Ðˆø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ‘Ðøñ¤±…ÍÌô‰‰à‰àµÕÍ•ÈµÁ¥¸Ñ•áÐµÍ•½¹‘…Éäˆøð½¤ùI•Á½ÉÑ•	äð½‘Ðø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ‘øðýÁ¡À•¡¼¡Ñµ±ÍÁ•¥…±¡…ÉÌ ‘É•Á½ÉÑ•‘	ä¤ì€üøð½‘ø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€ð½‘¥Øø4(€€€€€€€€€€€€€€€€€€€€€€€€ð½‘°ø4(€€€€€€€€€€€€€€€€€€€€ð½‘¥Øø4(€€€€€€€€€€€€€€€€ð½‘¥Øø4(4(€€€€€€€€€€€€€€€€ðýÁ¡À¥˜€ ‘ÕÍ•ÉI½±”€ôôô€…‘µ¥¸œñð€‘ÕÍ•ÉI½±”€ôôô€µ…¹…•Èœ¤è€üø4(€€€€€€€€€€€€€€€€€€€€ñ‘¥Ø±…ÍÌô‰…É±…ÍÌµÁ…¹•°Í¡…‘½Üµ¹½¹”‘•™•Ðµ…Ñ¥½¹Ìµˆ´Ðˆø4(€€€€€€€€€€€€€€€€€€€€€€€€ñ‘¥Ø±…ÍÌô‰…Éµ¡•…‘•Èˆø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ È±…ÍÌô‰…ÉµÑ¥Ñ±” Øµˆ´ÀˆùÑ¥½¹Ìð½ Èø4(€€€€€€€€€€€€€€€€€€€€€€€€ð½‘¥Øø4(€€€€€€€€€€€€€€€€€€€€€€€€ñ‘¥Ø±…ÍÌô‰…Éµ‰½‘äˆø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ‘¥Ø±…ÍÌô‰µÉ¥…À´Èˆø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ‰ÕÑÑ½¸ÑåÁ”ô‰‰ÕÑÑ½¸ˆ±…ÍÌô‰‰Ñ¸‰Ñ¸µÁÉ¥µ…Éäˆ‘…Ñ„µ‰ÌµÑ½±”ô‰µ½‘…°ˆ‘…Ñ„µ‰ÌµÑ…É•Ðôˆ•‘¥Ñ•™•Ñ5½‘…°ˆø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ¤±…ÍÌô‰™…Ì™„µ•‘¥Ðµ”´Èˆøð½¤øUÁ‘…Ñ”MÑ…ÑÕÌ4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ð½‰ÕÑÑ½¸ø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ‰ÕÑÑ½¸ÑåÁ”ô‰‰ÕÑÑ½¸ˆ±…ÍÌô‰‰Ñ¸‰Ñ¸µ½ÕÑ±¥¹”µ‘…¹•Èˆ½¹±¥¬ô‰½¹™¥Éµ•±•Ñ” ðýÁ¡À•¡¼€¡¥¹Ð¤‘‘•™•Ñ%ì€üø¤ìˆø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ¤±…ÍÌô‰™…Ì™„µÑÉ…Í µ”´Èˆøð½¤ø•±•Ñ”•™•Ð4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ð½‰ÕÑÑ½¸ø4(€€€€€€€€€€€€€€€€€€€€€€€€€€€€ð½‘¥Øø4(€€€€€€€€€€€€€€€€€€€€€€€€ð½‘¥Øø4(€€€€€€€€€€€€€€€€€€€€ð½‘¥Øø4(€€€€€€€€€€€€€€€€ðýÁ¡À•¹‘¥˜ì€üø4(4(€€€€€€€€€€€€€€€€ñ‘¥Ø±…ÍÌô‰…É±…ÍÌµÁ…¹•°Í¡…‘½Üµ¹½¹”ÅÕ¥¬µ…•ÍÌµ…Éˆø4(€€€€€€€€€€€€€€€€€€€€ñ‘¥Ø±…ÍÌô‰…Éµ¡•…‘•Èˆø4(€€€€€€€€€€€€€€€€€€€€€€€€ñ È±…ÍÌô‰…ÉµÑ¥Ñ±” Øµˆ´ÀˆùEÕ¥¬•ÍÌð½ Èø4(€€€€€€€€€€€€€€€€€€€€ð½‘¥Øø4(€€€€€€€€€€€€€€€€€€€€ñ‘¥Ø±…ÍÌô‰…Éµ‰½‘äˆø4(€€€€€€€€€€€€€€€€€€€€€€€€ñ¥µœÍÉŒô‰¡ÑÑÁÌè¼½…Á¤¹ÅÉÍ•ÉÙ•È¹½´½ØÄ½É•…Ñ”µÅÈµ½‘”¼ýÍ¥é”ôÄàÁàÄàÀ™…µÀí‘…Ñ„ôðýÁ¡À•¡¼ÕÉ±•¹½‘”¡M%Q}UI0€¸€œ½Ù¥•Ý}‘•™•Ð¹Á¡Àý¥ôœ€¸€‘‘•™•Ñ%¤ì€üøˆ…±Ðô‰EH½‘”±¥¹­¥¹œÑ¼Ñ¡¥Ì‘•™•Ðˆ±…ÍÌô‰¥µœµ™±Õ¥µˆ´Ìˆø4(€€€€€€€€€€€€€€€€€€€€€€€€ñÀùM…¸Ñ¼½Á•¸Ñ¡¥ÌÉ•½É½¸„µ½‰¥±”‘•Ù¥”¸ð½Àø4(€€€€€€€€€€€€€€€€€€€€ð½‘¥Øø4(€€€€€€€€€€€€€€€€ð½‘¥Øø4(€€€€€€€€€€€€ð½‘¥Øø4(€€€€€€€€ð½‘¥Øø4(€€€€ð½µ…¥¸ø4(4(€€€€ð„´´%µ…”5½‘…°€´´ø4(€€€€ñ‘¥Ø±…ÍÌô‰µ½‘…°™…‘”ˆ¥ô‰¥µ…•5½‘…°ˆÑ…‰¥¹‘•àôˆ´Äˆ…É¥„µ¡¥‘‘•¸ô‰ÑÉÕ”ˆø4(€€€€€€€€ñ‘¥Ø±…ÍÌô‰µ½‘…°µ‘¥…±½œµ½‘…°µá°µ½‘…°µ‘¥…±½œµ•¹Ñ•É•ˆø4(€€€€€€€€€€€€ñ‘¥Ø±…ÍÌô‰µ½‘…°µ½¹Ñ•¹Ðˆø4(€€€€€€€€€€€€€€€€ñ‘¥Ø±…ÍÌô‰µ½‘…°µ¡•…‘•Èˆø4(€€€€€€€€€€€€€€€€€€€€ñ Ô±…ÍÌô‰µ½‘…°µÑ¥Ñ±”ˆ¥ô‰¥µ…•5½‘…±1…‰•°ˆøð½ Ôø4(€€€€€€€€€€€€€€€€€€€€ñ‰ÕÑÑ½¸ÑåÁ”ô‰‰ÕÑÑ½¸ˆ±…ÍÌô‰‰Ñ¸µ±½Í”ˆ‘…Ñ„µ‰Ìµ‘¥Íµ¥ÍÌô‰µ½‘…°ˆ…É¥„µ±…‰•°ô‰±½Í”ˆøð½‰ÕÑÑ½¸ø4(€€€€€€€€€€€€€€€€ð½‘¥Øø4(€€€€€€€€€€€€€€€€ñ‘¥Ø±…ÍÌô‰µ½‘…°µ‰½‘äÑ•áÐµ•¹Ñ•Èˆø4(€€€€€€€€€€€€€€€€€€€€ñ¥µœ¥ô‰µ½‘…±%µ…”ˆÍÉŒôˆˆ…±Ðôˆˆ±…ÍÌô‰¥µœµ™±Õ¥ˆø4(€€€€€€€€€€€€€€€€ð½‘¥Øø4(€€€€€€€€€€€€ð½‘¥Øø4(€€€€€€€€ð½‘¥Øø4(€€€€ð½‘¥Øø4(4(€€€€ðýÁ¡À¥˜€ ‘ÕÍ•ÉI½±”€ôôô€…‘µ¥¸œñð€‘ÕÍ•ÉI½±”€ôôô€µ…¹…•Èœ¤è€üø4(€€€€€€€€ð„´´‘¥ÐMÑ…ÑÕÌ5½‘…°€´´ø4(€€€€€€€€ñ‘¥Ø±…ÍÌô‰µ½‘…°™…‘”ˆ¥ô‰•‘¥Ñ•™•Ñ5½‘…°ˆÑ…‰¥¹‘•àôˆ´Äˆ…É¥„µ¡¥‘‘•¸ô‰ÑÉÕ”ˆø4(€€€€€€€€€€€€ð„´´€¸¸¸€¡‘¥Ðµ½‘…°½¹Ñ•¹Ð¤€¸¸¸€´´ø4(€€€€€€€€ð½‘¥Øø4(€€€€ðýÁ¡À•¹‘¥˜ì€üø4(4(€€€€ñÍÉ¥ÁÐÍÉŒô‰¡ÑÑÁÌè¼½‘¸¹©Í‘•±¥ÙÈ¹¹•Ð½¹Á´½‰½½ÑÍÑÉ…Á Ô¸Ì¸È½‘¥ÍÐ½©Ì½‰½½ÑÍÑÉ…À¹‰Õ¹‘±”¹µ¥¸¹©Ìˆøð½ÍÉ¥ÁÐø4(€€€€ñÍÉ¥ÁÐÍÉŒô‰¡ÑÑÁÌè¼½‘¸¹©Í‘•±¥ÙÈ¹¹•Ð½¹Á´½ÍÝ••Ñ…±•ÉÐÉ ÄÄ¸Ð¸ÄØ½‘¥ÍÐ½ÍÝ••Ñ…±•ÉÐÈ¹µ¥¸¹©Ìˆøð½ÍÉ¥ÁÐø4(€€€€ñÍÉ¥ÁÐø4(€€€€€€€€¼¨¨4(€€€€€€€€€¨Ù¥•Ý}‘•™•Ð¹©Ì4(€€€€€€€€€¨)…Ù…MÉ¥ÁÐ™½ÈY¥•Ü•™•ÐA…”4(€€€€€€€€€¨ÕÉÉ•¹Ð…Ñ”…¹Q¥µ”€¡UQ¤è€ÈÀÈÔ´ÀÄ´ÌÀ€ÄÔèÄÐèÈÈ4(€€€€€€€€€¨ÕÉÉ•¹ÐUÍ•ÈÌ1½¥¸è¥É±…´4(€€€€€€€€€¨¼4(4(€€€€€€€€¼¼•‰Õœµ½‘”…¹½¹ÍÑ…¹ÑÌ4(€€€€€€€½¹ÍÐ	U€ô€ðýÁ¡À•¡¼€‘‘•‰Õ¹…‰±•€ü€ÑÉÕ”œ€è€™…±Í”œì€üøì4(€€€€€€€½¹ÍÐUII9Q}UMH€ô€œðýÁ¡À•¡¼¡Ñµ±ÍÁ•¥…±¡…ÉÌ ‘}MMM%=9lÕÍ•É¹…µ”t€üü€œœ¤ì€üøœì(€€€€€€€½¹ÍÐUII9Q}Q%5MQ5@€ô€œðýÁ¡À•¡¼‘…Ñ” dµ´µ é¤éÌœ¤ì€üøœì(€€€€€€€½¹ÍÐM%Q}UI0€ô€œðýÁ¡À•¡¼M%Q}UI0ì€üøœì(€€€€€€€½¹ÍÐMI}Q=-8€ô€ðýÁ¡À•¡¼©Í½¹}•¹½‘” ¡ÍÑÉ¥¹œ¤ ‘}MMM%=9lÍÉ™}Ñ½­•¸t€üü€œœ¤¤ì€üøì(4(€€€€€€€€¼¼%µ…”µ½‘…°¡…¹‘±¥¹œ4(€€€€€€€™Õ¹Ñ¥½¸½Á•¹%µ…•5½‘…°¡ÍÉŒ°Ñ¥Ñ±”¤ì4(€€€€€€€€€€€½¹ÍÐµ½‘…°€ô‘½Õµ•¹Ð¹•Ñ±•µ•¹Ñ	å% ¥µ…•5½‘…°œ¤ì4(€€€€€€€€€€€½¹ÍÐµ½‘…±%µ…”€ô‘½Õµ•¹Ð¹•Ñ±•µ•¹Ñ	å% µ½‘…±%µ…”œ¤ì4(€€€€€€€€€€€½¹ÍÐµ½‘…±Q¥Ñ±”€ô‘½Õµ•¹Ð¹•Ñ±•µ•¹Ñ	å% ¥µ…•5½‘…±1…‰•°œ¤ì4(4(€€€€€€€€€€€µ½‘…±%µ…”¹ÍÉŒ€ôÍÉŒì4(€€€€€€€€€€€µ½‘…±Q¥Ñ±”¹Ñ•áÑ½¹Ñ•¹Ð€ôÑ¥Ñ±”ì4(4(€€€€€€€€€€€¹•Ü‰½½ÑÍÑÉ…À¹5½‘…°¡µ½‘…°¤¹Í¡½Ü ¤ì4(€€€€€€€ô4(4(€€€€€€€™Õ¹Ñ¥½¸ÁÉ¥¹Ñ•™•Ð ¤ì4(€€€€€€€€€€€Ý¥¹‘½Ü¹ÁÉ¥¹Ð ¤ì4(€€€€€€€ô4(4(€€€€€€€€¼¼•±•Ñ”½¹™¥Éµ…Ñ¥½¸4(€€€€€€€™Õ¹Ñ¥½¸½¹™¥Éµ•±•Ñ”¡‘•™•Ñ%¤ì4(€€€€€€€€€€€MÝ…°¹™¥É”¡ì4(€€€€€€€€€€€€€€€Ñ¥Ñ±”è€É”å½ÔÍÕÉ”üœ°4(€€€€€€€€€€€€€€€Ñ•áÐè€‰Q¡¥ÌÝ¥±°Á•Éµ…¹•¹Ñ±ä‘•±•Ñ”Ñ¡¥Ì‘•™•Ð…¹…±°…ÍÍ½¥…Ñ•‘…Ñ„¸ˆ°4(€€€€€€€€€€€€€€€¥½¸è€Ý…É¹¥¹œœ°4(€€€€€€€€€€€€€€€Í¡½Ý…¹•±	ÕÑÑ½¸èÑÉÕ”°4(€€€€€€€€€€€€€€€½¹™¥Éµ	ÕÑÑ½¹½±½Èè€œ‘ŒÌÔÐÔœ°4(€€€€€€€€€€€€€€€…¹•±	ÕÑÑ½¹½±½Èè€œŒÙŒÜÔÝœ°4(€€€€€€€€€€€€€€€½¹™¥Éµ	ÕÑÑ½¹Q•áÐè€e•Ì°‘•±•Ñ”¥Ð„œ°4(€€€€€€€€€€€€€€€…¹•±	ÕÑÑ½¹Q•áÐè€…¹•°œ4(€€€€€€€€€€€ô¤¹Ñ¡•¸ ¡É•ÍÕ±Ð¤€ôøì4(€€€€€€€€€€€€€€€¥˜€¡É•ÍÕ±Ð¹¥Í½¹™¥Éµ•¤ì4(€€€€€€€€€€€€€€€€€€€‘•±•Ñ••™•Ð¡‘•™•Ñ%¤ì4(€€€€€€€€€€€€€€€ô4(€€€€€€€€€€€ô¤ì4(€€€€€€€ô4(4(€€€€€€€‘½Õµ•¹Ð¹…‘‘Ù•¹Ñ1¥ÍÑ•¹•È =5½¹Ñ•¹Ñ1½…‘•œ°™Õ¹Ñ¥½¸ ¤ì(€€€€€€€€€€€½¹ÍÐÍÑ…ÑÕÍM•±•Ð€ô‘½Õµ•¹Ð¹•Ñ±•µ•¹Ñ	å% ÍÑ…ÑÕÌœ¤ì4(4(€€€€€€€€€€€¥˜€¡ÍÑ…ÑÕÍM•±•Ð¤ì4(€€€€€€€€€€€€€€€ÍÑ…ÑÕÍM•±•Ð¹…‘‘Ù•¹Ñ1¥ÍÑ•¹•È ¡…¹”œ°™Õ¹Ñ¥½¸ ¤ì4(€€€€€€€€€€€€€€€€€€€ÕÁ‘…Ñ•½Éµ¥•±‘Ì¡Ñ¡¥Ì¹Ù…±Õ”¤ì4(€€€€€€€€€€€€€€€ô¤ì4(€€€€€€€€€€€ô4(4(€€€€€€€€€€€¥˜€¡ÍÑ…ÑÕÍM•±•Ð€˜˜ÍÑ…ÑÕÍM•±•Ð¹Ù…±Õ”¤ì4(€€€€€€€€€€€€€€€ÕÁ‘…Ñ•½Éµ¥•±‘Ì¡ÍÑ…ÑÕÍM•±•Ð¹Ù…±Õ”¤ì4(€€€€€€€€€€€ô4(4(€€€€€€€ô¤ì(4(€€€€€€€™Õ¹Ñ¥½¸ÕÁ‘…Ñ•½Éµ¥•±‘Ì¡ÍÑ…ÑÕÌ¤ì4(€€€€€€€€€€€½¹ÍÐ±½ÍÕÉ•%µ…•¥Ø€ô‘½Õµ•¹Ð¹•Ñ±•µ•¹Ñ	å% ±½ÍÕÉ•%µ…•¥Øœ¤ì4(€€€€€€€€€€€½¹ÍÐÉ•©•Ñ¥½¹½µµ•¹Ñ¥Ø€ô‘½Õµ•¹Ð¹•Ñ±•µ•¹Ñ	å% É•©•Ñ¥½¹½µµ•¹Ñ¥Øœ¤ì4(€€€€€€€€€€€½¹ÍÐ±½ÍÕÉ•%µ…”€ô‘½Õµ•¹Ð¹•Ñ±•µ•¹Ñ	å% ±½ÍÕÉ•%µ…”œ¤ì4(€€€€€€€€€€€½¹ÍÐÉ•©•Ñ¥½¹½µµ•¹Ð€ô‘½Õµ•¹Ð¹•Ñ±•µ•¹Ñ	å% É•©•Ñ¥½¹½µµ•¹Ðœ¤ì4(4(€€€€€€€€€€€±½ÍÕÉ•%µ…•¥Ø¹ÍÑå±”¹‘¥ÍÁ±…ä€ô€¹½¹”œì4(€€€€€€€€€€€É•©•Ñ¥½¹½µµ•¹Ñ¥Ø¹ÍÑå±”¹‘¥ÍÁ±…ä€ô€¹½¹”œì4(€€€€€€€€€€€±½ÍÕÉ•%µ…”¹É•ÅÕ¥É•€ô™…±Í”ì4(€€€€€€€€€€€É•©•Ñ¥½¹½µµ•¹Ð¹É•ÅÕ¥É•€ô™…±Í”ì4(4(€€€€€€€€€€€¥˜€¡ÍÑ…ÑÕÌ€ôôô€±½Í•œ¤ì4(€€€€€€€€€€€€€€€±½ÍÕÉ•%µ…•¥Ø¹ÍÑå±”¹‘¥ÍÁ±…ä€ô€‰±½¬œì4(€€€€€€€€€€€€€€€±½ÍÕÉ•%µ…”¹É•ÅÕ¥É•€ôÑÉÕ”ì4(€€€€€€€€€€€ô•±Í”¥˜€¡ÍÑ…ÑÕÌ€ôôô€É•©•Ñ•œ¤ì4(€€€€€€€€€€€€€€€É•©•Ñ¥½¹½µµ•¹Ñ¥Ø¹ÍÑå±”¹‘¥ÍÁ±…ä€ô€‰±½¬œì4(€€€€€€€€€€€€€€€É•©•Ñ¥½¹½µµ•¹Ð¹É•ÅÕ¥É•€ôÑÉÕ”ì4(€€€€€€€€€€€ô4(€€€€€€€ô4(4(€€€€€€€™Õ¹Ñ¥½¸‘•±•Ñ••™•Ð¡‘•™•Ñ%¤ì(€€€€€€€€€€€½¹ÍÐ™½Éµ…Ñ„€ô¹•Ü½Éµ…Ñ„ ¤ì(€€€€€€€€€€€™½Éµ…Ñ„¹…ÁÁ•¹ ‘•™•Ñ}¥œ°‘•™•Ñ%¤ì(€€€€€€€€€€€™½Éµ…Ñ„¹…ÁÁ•¹ ÍÉ™}Ñ½­•¸œ°MI}Q=-8¤ì((€€€€€€€€€€€™•Ñ  œ½…Á¤½‘•±•Ñ”µ‘•™•Ð¹Á¡Àœ°ì(€€€€€€€€€€€€€€€µ•Ñ¡½è€A=MPœ°4(€€€€€€€€€€€€€€€‰½‘äè™½Éµ…Ñ„4(€€€€€€€€€€€ô¤4(€€€€€€€€€€€€¹Ñ¡•¸¡É•ÍÁ½¹Í”€ôøÉ•ÍÁ½¹Í”¹©Í½¸ ¤¤4(€€€€€€€€€€€€¹Ñ¡•¸¡‘…Ñ„€ôøì4(€€€€€€€€€€€€€€€¥˜€¡‘…Ñ„¹ÍÕ•ÍÌ¤ì4(€€€€€€€€€€€€€€€€€€€MÝ…°¹™¥É”¡ì4(€€€€€€€€€€€€€€€€€€€€€€€¥½¸è€ÍÕ•ÍÌœ°4(€€€€€€€€€€€€€€€€€€€€€€€Ñ¥Ñ±”è€•±•Ñ•„œ°4(€€€€€€€€€€€€€€€€€€€€€€€Ñ•áÐè€Q¡”‘•™•Ð¡…Ì‰••¸‘•±•Ñ•¸œ°4(€€€€€€€€€€€€€€€€€€€€€€€Í¡½Ý½¹™¥Éµ	ÕÑÑ½¸è™…±Í”°4(€€€€€€€€€€€€€€€€€€€€€€€Ñ¥µ•Èè€ÄÔÀÀ4(€€€€€€€€€€€€€€€€€€€ô¤¹Ñ¡•¸  ¤€ôøì4(€€€€€€€€€€€€€€€€€€€€€€€Ý¥¹‘½Ü¹±½…Ñ¥½¸¹¡É•˜€ô€‘•™•ÑÌ¹Á¡Àœì4(€€€€€€€€€€€€€€€€€€€ô¤ì4(€€€€€€€€€€€€€€€ô•±Í”ì4(€€€€€€€€€€€€€€€€€€€Ñ¡É½Ü¹•ÜÉÉ½È¡‘…Ñ„¹µ•ÍÍ…”ñð€…¥±•Ñ¼‘•±•Ñ”‘•™•Ð¸œ¤ì4(€€€€€€€€€€€€€€€ô4(€€€€€€€€€€€ô¤4(€€€€€€€€€€€€¹…Ñ ¡•ÉÉ½È€ôøì4(€€€€€€€€€€€€€€€MÝ…°¹™¥É”¡ì4(€€€€€€€€€€€€€€€€€€€¥½¸è€•ÉÉ½Èœ°4(€€€€€€€€€€€€€€€€€€€Ñ¥Ñ±”è€ÉÉ½Èœ°4(€€€€€€€€€€€€€€€€€€€Ñ•áÐè•ÉÉ½È¹µ•ÍÍ…”ñð€¸•ÉÉ½È½ÕÉÉ•Ý¡¥±”‘•±•Ñ¥¹œÑ¡”‘•™•Ð¸œ°4(€€€€€€€€€€€€€€€€€€€Í¡½Ý½¹™¥Éµ	ÕÑÑ½¸è™…±Í”4(€€€€€€€€€€€€€€€ô¤ì4(€€€€€€€€€€€ô¤ì4(€€€€€€€ô4(€€€€ð½ÍÉ¥ÁÐø4(ð½‰½‘äø4(ð½¡Ñµ°ø(
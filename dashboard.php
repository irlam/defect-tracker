<?php
/**
 * dashboard.php - Enhanced Responsive Version
 * Current Date and Time (UTC): 2025-03-18 20:58:28
 * Current User's Login: irlam
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
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

        <header class="dashboard-hero system-tool-card mb-4">
            <div class="dashboard-hero__icon" aria-hidden="true">
                <i class='bx bx-command'></i>
            </div>
            <div class="dashboard-hero__content">
                <span class="dashboard-hero__tag"><i class='bx bx-pulse'></i>Ops Console</span>
                <h1 class="dashboard-hero__title">Operational Defect Command</h1>
                <p class="dashboard-hero__lead">Real-time insight into contractor workloads and outstanding issues.</p>
                <ul class="dashboard-hero__meta">
                    <li><i class='bx bx-user-voice'></i><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></li>
                    <li><i class='bx bx-label'></i><?php echo htmlspeciï½ý¶‰žËkºwµç}¸ˆ±…ÍÌô‰‰Ñ¸‰Ñ¸µÁÉ¥µ…Éäˆ¥ô‰…ÁÁ±å½¹ÑÉ…Ñ½É¥±Ñ•ÈˆùÁÁ±ä¥±Ñ•ÉÌð½‰ÕÑÑ½¸ø(€€€€€€€€€€€€€€€€ð½‘¥Øø(€€€€€€€€€€€€ð½‘¥Øø(€€€€€€€€ð½‘¥Øø(€€€€ð½‘¥Øø((€€€€ð„´´•™•ÑÌ¥±Ñ•È5½‘…°€´´ø(€€€€ñ‘¥Ø±…ÍÌô‰µ½‘…°™…‘”ˆ¥ô‰‘•™•ÑÍ¥±Ñ•É5½‘…°ˆÑ…‰¥¹‘•àôˆ´Äˆ…É¥„µ±…‰•±±•‘‰äô‰‘•™•ÑÍ¥±Ñ•É5½‘…±1…‰•°ˆ…É¥„µ¡¥‘‘•¸ô‰ÑÉÕ”ˆø(€€€€€€€€ñ‘¥Ø±…ÍÌô‰µ½‘…°µ‘¥…±½œˆø(€€€€€€€€€€€€ñ‘¥Ø±…ÍÌô‰µ½‘…°µ½¹Ñ•¹Ðˆø(€€€€€€€€€€€€€€€€ñ‘¥Ø±…ÍÌô‰µ½‘…°µ¡•…‘•Èˆø(€€€€€€€€€€€€€€€€€€€€ñ Ô±…ÍÌô‰µ½‘…°µÑ¥Ñ±”ˆ¥ô‰‘•™•ÑÍ¥±Ñ•É5½‘…±1…‰•°ˆù¥±Ñ•È•™•ÑÌð½ Ôø(€€€€€€€€€€€€€€€€€€€€ñ‰ÕÑÑ½¸ÑåÁ”ô‰‰ÕÑÑ½¸ˆ±…ÍÌô‰‰Ñ¸µ±½Í”ˆ‘…Ñ„µ‰Ìµ‘¥Íµ¥ÍÌô‰µ½‘…°ˆ…É¥„µ±…‰•°ô‰±½Í”ˆøð½‰ÕÑÑ½¸ø(€€€€€€€€€€€€€€€€ð½‘¥Øø(€€€€€€€€€€€€€€€€ñ‘¥Ø±…ÍÌô‰µ½‘…°µ‰½‘äˆø(€€€€€€€€€€€€€€€€€€€€ñ™½É´¥ô‰‘•™•ÑÍ¥±Ñ•É½É´ˆø(€€€€€€€€€€€€€€€€€€€€€€€€ñ‘¥Ø±…ÍÌô‰µˆ´Ìˆø(€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ±…‰•°±…ÍÌô‰™½É´µ±…‰•°ˆùMÑ…ÑÕÌð½±…‰•°ø(€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ‘¥Ø±…ÍÌô‰µ™±•à™±•àµÝÉ…À…À´Èˆø(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ‘¥Ø±…ÍÌô‰™½É´µ¡•¬™½É´µ¡•¬µ¥¹±¥¹”ˆø(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ¥¹ÁÕÐ±…ÍÌô‰™½É´µ¡•¬µ¥¹ÁÕÐˆÑåÁ”ô‰¡•­‰½àˆ¥ô‰™¥±Ñ•ÉMÑ…ÑÕÍ=Á•¸ˆÙ…±Õ”ô‰½Á•¸ˆ¡•­•ø(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ±…‰•°±…ÍÌô‰™½É´µ¡•¬µ±…‰•°ˆ™½Èô‰™¥±Ñ•ÉMÑ…ÑÕÍ=Á•¸ˆù=Á•¸ð½±…‰•°ø(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ð½‘¥Øø(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ‘¥Ø±…ÍÌô‰™½É´µ¡•¬™½É´µ¡•¬µ¥¹±¥¹”ˆø(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ¥¹ÁÕÐ±…ÍÌô‰™½É´µ¡•¬µ¥¹ÁÕÐˆÑåÁ”ô‰¡•­‰½àˆ¥ô‰™¥±Ñ•ÉMÑ…ÑÕÍA•¹‘¥¹œˆÙ…±Õ”ô‰Á•¹‘¥¹œˆ¡•­•ø(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ±…‰•°±…ÍÌô‰™½É´µ¡•¬µ±…‰•°ˆ™½Èô‰™¥±Ñ•ÉMÑ…ÑÕÍA•¹‘¥¹œˆùA•¹‘¥¹œð½±…‰•°ø(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ð½‘¥Øø(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ‘¥Ø±…ÍÌô‰™½É´µ¡•¬™½É´µ¡•¬µ¥¹±¥¹”ˆø(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ¥¹ÁÕÐ±…ÍÌô‰™½É´µ¡•¬µ¥¹ÁÕÐˆÑåÁ”ô‰¡•­‰½àˆ¥ô‰™¥±Ñ•ÉMÑ…ÑÕÍ•ÁÑ•ˆÙ…±Õ”ô‰…•ÁÑ•ˆ¡•­•ø(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ±…‰•°±…ÍÌô‰™½É´µ¡•¬µ±…‰•°ˆ™½Èô‰™¥±Ñ•ÉMÑ…ÑÕÍ•ÁÑ•ˆù•ÁÑ•ð½±…‰•°ø(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ð½‘¥Øø(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ‘¥Ø±…ÍÌô‰™½É´µ¡•¬™½É´µ¡•¬µ¥¹±¥¹”ˆø(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ¥¹ÁÕÐ±…ÍÌô‰™½É´µ¡•¬µ¥¹ÁÕÐˆÑåÁ”ô‰¡•­‰½àˆ¥ô‰™¥±Ñ•ÉMÑ…ÑÕÍI•©•Ñ•ˆÙ…±Õ”ô‰É•©•Ñ•ˆ¡•­•ø(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ±…‰•°±…ÍÌô‰™½É´µ¡•¬µ±…‰•°ˆ™½Èô‰™¥±Ñ•ÉMÑ…ÑÕÍI•©•Ñ•ˆùI•©•Ñ•ð½±…‰•°ø(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ð½‘¥Øø(€€€€€€€€€€€€€€€€€€€€€€€€€€€€ð½‘¥Øø(€€€€€€€€€€€€€€€€€€€€€€€€ð½‘¥Øø(€€€€€€€€€€€€€€€€€€€€€€€€ñ‘¥Ø±…ÍÌô‰µˆ´Ìˆø(€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ±…‰•°±…ÍÌô‰™½É´µ±…‰•°ˆùAÉ¥½É¥Ñäð½±…‰•°ø(€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ‘¥Ø±…ÍÌô‰µ™±•à™±•àµÝÉ…À…À´Èˆø(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ‘¥Ø±…ÍÌô‰™½É´µ¡•¬™½É´µ¡•¬µ¥¹±¥¹”ˆø(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ¥¹ÁÕÐ±…ÍÌô‰™½É´µ¡•¬µ¥¹ÁÕÐˆÑåÁ”ô‰¡•­‰½àˆ¥ô‰™¥±Ñ•ÉAÉ¥½É¥Ñå!¥ ˆÙ…±Õ”ô‰¡¥ ˆ¡•­•ø(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ±…‰•°±…ÍÌô‰™½É´µ¡•¬µ±…‰•°ˆ™½Èô‰™¥±Ñ•ÉAÉ¥½É¥Ñå!¥ ˆù!¥ ð½±…‰•°ø(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ð½‘¥Øø(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ‘¥Ø±…ÍÌô‰™½É´µ¡•¬™½É´µ¡•¬µ¥¹±¥¹”ˆø(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ¥¹ÁÕÐ±…ÍÌô‰™½É´µ¡•¬µ¥¹ÁÕÐˆÑåÁ”ô‰¡•­‰½àˆ¥ô‰™¥±Ñ•ÉAÉ¥½É¥Ñå5•‘¥Õ´ˆÙ…±Õ”ô‰µ•‘¥Õ´ˆ¡•­•ø(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ±…‰•°±…ÍÌô‰™½É´µ¡•¬µ±…‰•°ˆ™½Èô‰™¥±Ñ•ÉAÉ¥½É¥Ñå5•‘¥Õ´ˆù5•‘¥Õ´ð½±…‰•°ø(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ð½‘¥Øø(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ‘¥Ø±…ÍÌô‰™½É´µ¡•¬™½É´µ¡•¬µ¥¹±¥¹”ˆø(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ¥¹ÁÕÐ±…ÍÌô‰™½É´µ¡•¬µ¥¹ÁÕÐˆÑåÁ”ô‰¡•­‰½àˆ¥ô‰™¥±Ñ•ÉAÉ¥½É¥Ñå1½ÜˆÙ…±Õ”ô‰±½Üˆ¡•­•ø(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ±…‰•°±…ÍÌô‰™½É´µ¡•¬µ±…‰•°ˆ™½Èô‰™¥±Ñ•ÉAÉ¥½É¥Ñå1½Üˆù1½Üð½±…‰•°ø(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ð½‘¥Øø(€€€€€€€€€€€€€€€€€€€€€€€€€€€€ð½‘¥Øø(€€€€€€€€€€€€€€€€€€€€€€€€ð½‘¥Øø(€€€€€€€€€€€€€€€€€€€€€€€€ñ‘¥Ø±…ÍÌô‰µˆ´Ìˆø(€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ±…‰•°™½Èô‰™¥±Ñ•É•™•Ñ½¹ÑÉ…Ñ½Èˆ±…ÍÌô‰™½É´µ±…‰•°ˆù½¹ÑÉ…Ñ½Èð½±…‰•°ø(€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñÍ•±•Ð±…ÍÌô‰™½É´µÍ•±•Ðˆ¥ô‰™¥±Ñ•É•™•Ñ½¹ÑÉ…Ñ½Èˆø(€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ½ÁÑ¥½¸Ù…±Õ”ôˆˆù±°½¹ÑÉ…Ñ½ÉÌð½½ÁÑ¥½¸ø(€€€€€€€€€€€€€€€€€€€€€€€€€€€€ð½Í•±•Ðø(€€€€€€€€€€€€€€€€€€€€€€€€ð½‘¥Øø(€€€€€€€€€€€€€€€€€€€€€€€€ñ‘¥Ø±…ÍÌô‰µˆ´Ìˆø(€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ±…‰•°™½Èô‰™¥±Ñ•É•™•ÑQ¥Ñ±”ˆ±…ÍÌô‰™½É´µ±…‰•°ˆùQ¥Ñ±”M•…É ð½±…‰•°ø(€€€€€€€€€€€€€€€€€€€€€€€€€€€€ñ¥¹ÁÕÐÑåÁ”ô‰Ñ•áÐˆ±…ÍÌô‰™½É´µ½¹ÑÉ½°ˆ¥ô‰™¥±Ñ•É•™•ÑQ¥Ñ±”ˆÁ±…•¡½±‘•Èô‰M•…É ¥¸‘•™•ÐÑ¥Ñ±•Ìˆø(€€€€€€€€€€€€€€€€€€€€€€€€ð½‘¥Øø(€€€€€€€€€€€€€€€€€€€€ð½™½É´ø(€€€€€€€€€€€€€€€€ð½‘¥Øø(€€€€€€€€€€€€€€€€ñ‘¥Ø±…ÍÌô‰µ½‘…°µ™½½Ñ•Èˆø(€€€€€€€€€€€€€€€€€€€€ñ‰ÕÑÑ½¸ÑåÁ”ô‰‰ÕÑÑ½¸ˆ±…ÍÌô‰‰Ñ¸‰Ñ¸µÍ•½¹‘…Éäˆ¥ô‰É•Í•Ñ•™•ÑÍ¥±Ñ•ÈˆùI•Í•Ð¥±Ñ•ÉÌð½‰ÕÑÑ½¸ø(€€€€€€€€€€€€€€€€€€€€ñ‰ÕÑÑ½¸ÑåÁ”ô‰‰ÕÑÑ½¸ˆ±…ÍÌô‰‰Ñ¸‰Ñ¸µÁÉ¥µ…Éäˆ¥ô‰…ÁÁ±å•™•ÑÍ¥±Ñ•ÈˆùÁÁ±ä¥±Ñ•ÉÌð½‰ÕÑÑ½¸ø(€€€€€€€€€€€€€€€€ð½‘¥Øø(€€€€€€€€€€€€ð½‘¥Øø(€€€€€€€€ð½‘¥Øø(€€€€ð½‘¥Øø((€€€€ñÍÉ¥ÁÐÍÉŒô‰¡ÑÑÁÌè¼½‘¸¹©Í‘•±¥ÙÈ¹¹•Ð½¹Á´½©ÅÕ•Éå Ì¸Ø¸Ð½‘¥ÍÐ½©ÅÕ•Éä¹µ¥¸¹©Ìˆøð½ÍÉ¥ÁÐø(€€€€ñÍÉ¥ÁÐÍÉŒô‰¡ÑÑÁÌè¼½‘¸¹©Í‘•±¥ÙÈ¹¹•Ð½¹Á´½‰½½ÑÍÑÉ…Á Ô¸Ì¸È½‘¥ÍÐ½©Ì½‰½½ÑÍÑÉ…À¹‰Õ¹‘±”¹µ¥¸¹©Ìˆøð½ÍÉ¥ÁÐø((€€€€ñÍÉ¥ÁÐø(€€€€¡‘½Õµ•¹Ð¤¹É•…‘ä¡™Õ¹Ñ¥½¸ ¤ì(€€€€€€€€ œ¹Ñ½±”µ‘•Ñ…¥±Ìœ¤¹½¸ ±¥¬œ°™Õ¹Ñ¥½¸ ¤ì(€€€€€€€€€€€½¹ÍÐ‘•™•Ñ%€ô€¡Ñ¡¥Ì¤¹‘…Ñ„ ‘•™•Ðµ¥œ¤ì(€€€€€€€€€€€½¹ÍÐ‘•Ñ…¥±ÍI½Ü€ô€¡€‘•Ñ…¥±Ì´‘í‘•™•Ñ%‘õ€¤ì(€€€€€€€€€€€½¹ÍÐ½¹Ñ•¹Ð€ô‘•Ñ…¥±ÍI½Ü¹™¥¹ œ¹É½Üµ‘•Ñ…¥±Ìœ¤ì(€€€€€€€€€€€½¹ÍÐ¥½¸€ô€¡Ñ¡¥Ì¤¹™¥¹ ¤œ¤ì((€€€€€€€€€€€¥˜€¡‘•Ñ…¥±ÍI½Ü¹¥Ì œéÙ¥Í¥‰±”œ¤¤ì(€€€€€€€€€€€€€€€½¹Ñ•¹Ð¹Í±¥‘•UÀ ÄØÀ°™Õ¹Ñ¥½¸ ¤ì(€€€€€€€€€€€€€€€€€€€‘•Ñ…¥±ÍI½Ü¹¡¥‘” ¤ì(€€€€€€€€€€€€€€€ô¤ì(€€€€€€€€€€€€€€€¥½¸¹É•µ½Ù•±…ÍÌ ‰àµ¡•ÙÉ½¸µÕÀœ¤¹…‘‘±…ÍÌ ‰àµ¡•ÙÉ½¸µ‘½Ý¸œ¤ì(€€€€€€€€€€€ô•±Í”ì(€€€€€€€€€€€€€€€‘•Ñ…¥±ÍI½Ü¹Í¡½Ü ¤ì(€€€€€€€€€€€€€€€½¹Ñ•¹Ð¹¡¥‘” ¤¹Í±¥‘•½Ý¸ ÄØÀ¤ì(€€€€€€€€€€€€€€€¥½¸¹É•µ½Ù•±…ÍÌ ‰àµ¡•ÙÉ½¸µ‘½Ý¸œ¤¹…‘‘±…ÍÌ ‰àµ¡•ÙÉ½¸µÕÀœ¤ì(€€€€€€€€€€€ô(€€€€€€€ô¤ì((€€€€€€€€ œ¹é½½µ…‰±”µ¥µ…”œ¤¹½¸ ±¥¬œ°™Õ¹Ñ¥½¸ ¤ì(€€€€€€€€€€€½¹ÍÐ™Õ±±%µ…•MÉŒ€ô€¡Ñ¡¥Ì¤¹‘…Ñ„ ™Õ±°µ¥µ…”œ¤ì(€€€€€€€€€€€€ œµ½‘…±%µ…”œ¤¹…ÑÑÈ ÍÉŒœ°™Õ±±%µ…•MÉŒ¤ì(€€€€€€€€€€€€ œ¥µ…•5½‘…°œ¤¹ÍÌ ‘¥ÍÁ±…äœ°€™±•àœ¤ì(€€€€€€€ô¤ì((€€€€€€€€ œ¹±½Í”µ¥µ…”µµ½‘…°œ¤¹½¸ ±¥¬œ°™Õ¹Ñ¥½¸¡”¤ì(€€€€€€€€€€€”¹ÍÑ½ÁAÉ½Á……Ñ¥½¸ ¤ì(€€€€€€€€€€€€ œ¥µ…•5½‘…°œ¤¹ÍÌ ‘¥ÍÁ±…äœ°€¹½¹”œ¤ì(€€€€€€€ô¤ì(€€€ô¤ì(€€€€ð½ÍÉ¥ÁÐø((€€€€ñÍÉ¥ÁÐø(€€€€¡‘½Õµ•¹Ð¤¹É•…‘ä¡™Õ¹Ñ¥½¸ ¤ì(€€€€€€€½¹ÍÐÑ½…ÍÑ!½ÍÐ€ô€ œ¹Ñ½…ÍÐµ½¹Ñ…¥¹•Èœ¤ì((€€€€€€€™Õ¹Ñ¥½¸Í¡½ÝQ½…ÍÐ¡µ•ÍÍ…”°ÑåÁ”€ô€¥¹™¼œ¤ì(€€€€€€€€€€€½¹ÍÐÑ½…ÍÑ%€ôÑ½…ÍÐ´‘í…Ñ”¹¹½Ü ¥õ€ì(€€€€€€€€€€€½¹ÍÐÑ½¹”€ôÑåÁ”€ôôô€ÍÕ•ÍÌœ€ü€ÍÕ•ÍÌœ€èÑåÁ”€ôôô€•ÉÉ½Èœ€ü€‘…¹•Èœ€è€¥¹™¼œì(€€€€€€€€€€€½¹ÍÐÑ½…ÍÑ5…É­ÕÀ€ô€¡€(€€€€€€€€€€€€€€€€ñ‘¥Ø¥ôˆ‘íÑ½…ÍÑ%‘ôˆ±…ÍÌô‰Ñ½…ÍÐ…±¥¸µ¥Ñ•µÌµ•¹Ñ•ÈÑ•áÐµ‰œµ‘…É¬‰½É‘•È´ÀˆÉ½±”ô‰…±•ÉÐˆ…É¥„µ±¥Ù”ô‰…ÍÍ•ÉÑ¥Ù”ˆ…É¥„µ…Ñ½µ¥Œô‰ÑÉÕ”ˆ‘…Ñ„µ‰Ìµ…ÕÑ½¡¥‘”ô‰ÑÉÕ”ˆ‘…Ñ„µ‰Ìµ‘•±…äôˆÌÈÀÀˆø(€€€€€€€€€€€€€€€€€€€€ñ‘¥Ø±…ÍÌô‰µ™±•à…±¥¸µ¥Ñ•µÌµ•¹Ñ•È…À´ÌÁà´ÌÁä´Èˆø(€€€€€€€€€€€€€€€€€€€€€€€€ñ¤±…ÍÌô‰à‰àµ¥¹™¼µ¥É±”Ñ•áÐ´‘íÑ½¹•ôœøð½¤ø(€€€€€€€€€€€€€€€€€€€€€€€€ñÍÁ…¸ø‘íµ•ÍÍ…•ôð½ÍÁ…¸ø(€€€€€€€€€€€€€€€€€€€€€€€€ñ‰ÕÑÑ½¸ÑåÁ”ô‰‰ÕÑÑ½¸ˆ±…ÍÌô‰‰Ñ¸µ±½Í”‰Ñ¸µ±½Í”µÝ¡¥Ñ”µÌµ…ÕÑ¼ˆ‘…Ñ„µ‰Ìµ‘¥Íµ¥ÍÌô‰Ñ½…ÍÐˆ…É¥„µ±…‰•°ô‰±½Í”ˆøð½‰ÕÑÑ½¸ø(€€€€€€€€€€€€€€€€€€€€ð½‘¥Øø(€€€€€€€€€€€€€€€€ð½‘¥Øø(€€€€€€€€€€€€¤ì(€€€€€€€€€€€Ñ½…ÍÑ!½ÍÐ¹…ÁÁ•¹¡Ñ½…ÍÑ5…É­ÕÀ¤ì(€€€€€€€€€€€½¹ÍÐÑ½…ÍÑ%¹ÍÑ…¹”€ô¹•Ü‰½½ÑÍÑÉ…À¹Q½…ÍÐ¡‘½Õµ•¹Ð¹•Ñ±•µ•¹Ñ	å%¡Ñ½…ÍÑ%¤¤ì(€€€€€€€€€€€Ñ½…ÍÑ%¹ÍÑ…¹”¹Í¡½Ü ¤ì(€€€€€€€ô((€€€€€€€½¹ÍÐ½¹ÑÉ…Ñ½ÉM•±•Ð€ô€ œ™¥±Ñ•É•™•Ñ½¹ÑÉ…Ñ½Èœ¤ì(€€€€€€€½¹ÑÉ…Ñ½É…Ñ„¹™½É… ¡½¹ÑÉ…Ñ½È€ôøì(€€€€€€€€€€€¥˜€ …½¹ÑÉ…Ñ½Èñð€…½¹ÑÉ…Ñ½È¹¹…µ”¤ì(€€€€€€€€€€€€€€€É•ÑÕÉ¸ì(€€€€€€€€€€€ô(€€€€€€€€€€€½¹ÍÐ½ÁÑ¥½¸€ô€ œñ½ÁÑ¥½¸¼øœ°ì(€€€€€€€€€€€€€€€Ù…±Õ”è½¹ÑÉ…Ñ½È¹¹…µ”¹Ñ½1½Ý•É…Í” ¤°(€€€€€€€€€€€€€€€Ñ•áÐè½¹ÑÉ…Ñ½È¹¹…µ”(€€€€€€€€€€€ô¤ì(€€€€€€€€€€€½¹ÑÉ…Ñ½ÉM•±•Ð¹…ÁÁ•¹¡½ÁÑ¥½¸¤ì(€€€€€€€ô¤ì((€€€€€€€€ œÉ•Í•Ñ½¹ÑÉ…Ñ½É¥±Ñ•Èœ¤¹½¸ ±¥¬œ°™Õ¹Ñ¥½¸ ¤ì(€€€€€€€€€€€€ œ™¥±Ñ•É½¹ÑÉ…Ñ½É9…µ”œ¤¹Ù…° œœ¤ì(€€€€€€€€€€€€ œ™¥±Ñ•É!…Í=Á•¹•™•ÑÌœ¤¹ÁÉ½À ¡•­•œ°ÑÉÕ”¤ì(€€€€€€€€€€€€ œ™¥±Ñ•É!…ÍA•¹‘¥¹•™•ÑÌœ¤¹ÁÉ½À ¡•­•œ°ÑÉÕ”¤ì(€€€€€€€ô¤ì((€€€€€€€€ œÉ•Í•Ñ•™•ÑÍ¥±Ñ•Èœ¤¹½¸ ±¥¬œ°™Õ¹Ñ¥½¸ ¤ì(€€€€€€€€€€€€ œ™¥±Ñ•É•™•ÑQ¥Ñ±”œ¤¹Ù…° œœ¤ì(€€€€€€€€€€€€ œ™¥±Ñ•É•™•Ñ½¹ÑÉ…Ñ½Èœ¤¹Ù…° œœ¤ì(€€€€€€€€€€€€ œ™¥±Ñ•ÉMÑ…ÑÕÍ=Á•¸°€™¥±Ñ•ÉMÑ…ÑÕÍA•¹‘¥¹œ°€™¥±Ñ•ÉMÑ…ÑÕÍ•ÁÑ•°€™¥±Ñ•ÉMÑ…ÑÕÍI•©•Ñ•œ¤¹ÁÉ½À ¡•­•œ°ÑÉÕ”¤ì(€€€€€€€€€€€€ œ™¥±Ñ•ÉAÉ¥½É¥Ñå!¥ °€™¥±Ñ•ÉAÉ¥½É¥Ñå5•‘¥Õ´°€™¥±Ñ•ÉAÉ¥½É¥Ñå1½Üœ¤¹ÁÉ½À ¡•­•œ°ÑÉÕ”¤ì(€€€€€€€ô¤ì((€€€€€€€€ œ…ÁÁ±å½¹ÑÉ…Ñ½É¥±Ñ•Èœ¤¹½¸ ±¥¬œ°™Õ¹Ñ¥½¸ ¤ì(€€€€€€€€€€€½¹ÍÐ¹…µ•¥±Ñ•È€ô€ œ™¥±Ñ•É½¹ÑÉ…Ñ½É9…µ”œ¤¹Ù…° ¤¹Ñ½1½Ý•É…Í” ¤¹ÑÉ¥´ ¤ì(€€€€€€€€€€€½¹ÍÐÉ•ÅÕ¥É•=Á•¸€ô€ œ™¥±Ñ•É!…Í=Á•¹•™•ÑÌœ¤¹¥Ì œé¡•­•œ¤ì(€€€€€€€€€€€½¹ÍÐÉ•ÅÕ¥É•A•¹‘¥¹œ€ô€ œ™¥±Ñ•É!…ÍA•¹‘¥¹•™•ÑÌœ¤¹¥Ì œé¡•­•œ¤ì(€€€€€€€€€€€±•ÐÙ¥Í¥‰±•½Õ¹Ð€ô€Àì((€€€€€€€€€€€€ œ½¹ÑÉ…Ñ½ÉÍQ…‰±”Ñ‰½‘äÑÈœ¤¹•… ¡™Õ¹Ñ¥½¸ ¤ì(€€€€€€€€€€€€€€€½¹ÍÐ€‘É½Ü€ô€¡Ñ¡¥Ì¤ì(€€€€€€€€€€€€€€€¥˜€ ‘É½Ü¹™¥¹ Ñœ¤¹±•¹Ñ €ðô€Ä¤ì(€€€€€€€€€€€€€€€€€€€É•ÑÕÉ¸ì(€€€€€€€€€€€€€€€ô((€€€€€€€€€€€€€€€½¹ÍÐ½µÁ…¹å-•ä€ôMÑÉ¥¹œ ‘É½Ü¹‘…Ñ„ ½µÁ…¹äœ¤ñð€œœ¤ì(€€€€€€€€€€€€€€€½¹ÍÐ½Á•¹½Õ¹Ð€ô9Õµ‰•È ‘É½Ü¹‘…Ñ„ ½Á•¸œ¤ñð€À¤ì(€€€€€€€€€€€€€€€½¹ÍÐÁ•¹‘¥¹½Õ¹Ð€ô9Õµ‰•È ‘É½Ü¹‘…Ñ„ Á•¹‘¥¹œœ¤ñð€À¤ì((€€€€€€€€€€€€€€€½¹ÍÐµ…Ñ¡•Í9…µ”€ô€…¹…µ•¥±Ñ•Èñð½µÁ…¹å-•ä¹¥¹±Õ‘•Ì¡¹…µ•¥±Ñ•È¤ì(€€€€€€€€€€€€€€€½¹ÍÐµ…Ñ¡•Í=Á•¸€ô€…É•ÅÕ¥É•=Á•¸ñð½Á•¹½Õ¹Ð€ø€Àì(€€€€€€€€€€€€€€€½¹ÍÐµ…Ñ¡•ÍA•¹‘¥¹œ€ô€…É•ÅÕ¥É•A•¹‘¥¹œñðÁ•¹‘¥¹½Õ¹Ð€ø€Àì((€€€€€€€€€€€€€€€¥˜€¡µ…Ñ¡•Í9…µ”€˜˜µ…Ñ¡•Í=Á•¸€˜˜µ…Ñ¡•ÍA•¹‘¥¹œ¤ì(€€€€€€€€€€€€€€€€€€€€‘É½Ü¹Í¡½Ü ¤ì(€€€€€€€€€€€€€€€€€€€Ù¥Í¥‰±•½Õ¹Ð¬¬ì(€€€€€€€€€€€€€€€ô•±Í”ì(€€€€€€€€€€€€€€€€€€€€‘É½Ü¹¡¥‘” ¤ì(€€€€€€€€€€€€€€€ô(€€€€€€€€€€€ô¤ì((€€€€€€€€€€€½¹ÍÐ½¹ÑÉ…Ñ½É5½‘…°€ô‰½½ÑÍÑÉ…À¹5½‘…°¹•Ñ%¹ÍÑ…¹”¡‘½Õµ•¹Ð¹•Ñ±•µ•¹Ñ	å% ½¹ÑÉ…Ñ½É¥±Ñ•É5½‘…°œ¤¤(€€€€€€€€€€€€€€€ñð¹•Ü‰½½ÑÍÑÉ…À¹5½‘…°¡‘½Õµ•¹Ð¹•Ñ±•µ•¹Ñ	å% ½¹ÑÉ…Ñ½É¥±Ñ•É5½‘…°œ¤¤ì(€€€€€€€€€€€½¹ÑÉ…Ñ½É5½‘…°¹¡¥‘” ¤ì((€€€€€€€€€€€Í¡½ÝQ½…ÍÐ¡¥±Ñ•ÉÌ…ÁÁ±¥•è€‘íÙ¥Í¥‰±•½Õ¹Ñô½¹ÑÉ…Ñ½È‘íÙ¥Í¥‰±•½Õ¹Ð€ôôô€Ä€ü€œœ€è€ÌôÍ¡½Ý¹€°€ÍÕ•ÍÌœ¤ì(€€€€€€€ô¤ì((€€€€€€€€ œ…ÁÁ±å•™•ÑÍ¥±Ñ•Èœ¤¹½¸ ±¥¬œ°™Õ¹Ñ¥½¸ ¤ì(€€€€€€€€€€€½¹ÍÐÑ¥Ñ±•¥±Ñ•È€ô€ œ™¥±Ñ•É•™•ÑQ¥Ñ±”œ¤¹Ù…° ¤¹Ñ½1½Ý•É…Í” ¤¹ÑÉ¥´ ¤ì(€€€€€€€€€€€½¹ÍÐ½¹ÑÉ…Ñ½É¥±Ñ•È€ô€  œ™¥±Ñ•É•™•Ñ½¹ÑÉ…Ñ½Èœ¤¹Ù…° ¤ñð€œœ¤¹Ñ½1½Ý•É…Í” ¤ì((€€€€€€€€€€€½¹ÍÐÍ•±•Ñ•‘MÑ…ÑÕÍ•Ì€ômtì(€€€€€€€€€€€¥˜€  œ™¥±Ñ•ÉMÑ…ÑÕÍ=Á•¸œ¤¹¥Ì œé¡•­•œ¤¤Í•±•Ñ•‘MÑ…ÑÕÍ•Ì¹ÁÕÍ  ½Á•¸œ¤ì(€€€€€€€€€€€¥˜€  œ™¥±Ñ•ÉMÑ…ÑÕÍA•¹‘¥¹œœ¤¹¥Ì œé¡•­•œ¤¤Í•±•Ñ•‘MÑ…ÑÕÍ•Ì¹ÁÕÍ  Á•¹‘¥¹œœ¤ì(€€€€€€€€€€€¥˜€  œ™¥±Ñ•ÉMÑ…ÑÕÍ•ÁÑ•œ¤¹¥Ì œé¡•­•œ¤¤Í•±•Ñ•‘MÑ…ÑÕÍ•Ì¹ÁÕÍ  …•ÁÑ•œ¤ì(€€€€€€€€€€€¥˜€  œ™¥±Ñ•ÉMÑ…ÑÕÍI•©•Ñ•œ¤¹¥Ì œé¡•­•œ¤¤Í•±•Ñ•‘MÑ…ÑÕÍ•Ì¹ÁÕÍ  É•©•Ñ•œ¤ì((€€€€€€€€€€€½¹ÍÐÍ•±•Ñ•‘AÉ¥½É¥Ñ¥•Ì€ômtì(€€€€€€€€€€€¥˜€  œ™¥±Ñ•ÉAÉ¥½É¥Ñå!¥ œ¤¹¥Ì œé¡•­•œ¤¤Í•±•Ñ•‘AÉ¥½É¥Ñ¥•Ì¹ÁÕÍ  ¡¥ œ¤ì(€€€€€€€€€€€¥˜€  œ™¥±Ñ•ÉAÉ¥½É¥Ñå5•‘¥Õ´œ¤¹¥Ì œé¡•­•œ¤¤Í•±•Ñ•‘AÉ¥½É¥Ñ¥•Ì¹ÁÕÍ  µ•‘¥Õ´œ¤ì(€€€€€€€€€€€¥˜€  œ™¥±Ñ•ÉAÉ¥½É¥Ñå1½Üœ¤¹¥Ì œé¡•­•œ¤¤Í•±•Ñ•‘AÉ¥½É¥Ñ¥•Ì¹ÁÕÍ  ±½Üœ¤ì((€€€€€€€€€€€±•ÐÙ¥Í¥‰±•½Õ¹Ð€ô€Àì((€€€€€€€€€€€€ œÉ••¹Ñ•™•ÑÍQ…‰±”Ñ‰½‘äÑÈ¹•áÁ…¹‘…‰±”µÉ½Üœ¤¹•… ¡™Õ¹Ñ¥½¸ ¤ì(€€€€€€€€€€€€€€€½¹ÍÐ€‘É½Ü€ô€¡Ñ¡¥Ì¤ì(€€€€€€€€€€€€€€€½¹ÍÐ‘•™•Ñ%€ô€‘É½Ü¹‘…Ñ„ ‘•™•Ðµ¥œ¤ì(€€€€€€€€€€€€€€€½¹ÍÐÑ¥Ñ±•-•ä€ôMÑÉ¥¹œ ‘É½Ü¹‘…Ñ„ Ñ¥Ñ±”œ¤ñð€œœ¤ì(€€€€€€€€€€€€€€€½¹ÍÐÍÑ…ÑÕÍ-•ä€ôMÑÉ¥¹œ ‘É½Ü¹‘…Ñ„ ÍÑ…ÑÕÌœ¤ñð€œœ¤ì(€€€€€€€€€€€€€€€½¹ÍÐÁÉ¥½É¥Ñå-•ä€ôMÑÉ¥¹œ ‘É½Ü¹‘…Ñ„ ÁÉ¥½É¥Ñäœ¤ñð€œœ¤ì(€€€€€€€€€€€€€€€½¹ÍÐ½¹ÑÉ…Ñ½É-•ä€ôMÑÉ¥¹œ ‘É½Ü¹‘…Ñ„ ½¹ÑÉ…Ñ½Èœ¤ñð€œœ¤ì(€€€€€€€€€€€€€€€½¹ÍÐ‘•Ñ…¥±ÍI½Ü€ô€¡€‘•Ñ…¥±Ì´‘í‘•™•Ñ%‘õ€¤ì(€€€€€€€€€€€€€€€½¹ÍÐ¥½¸€ô€‘É½Ü¹™¥¹ œ¹Ñ½±”µ‘•Ñ…¥±Ì¤œ¤ì((€€€€€€€€€€€€€€€½¹ÍÐµ…Ñ¡•ÍQ¥Ñ±”€ô€…Ñ¥Ñ±•¥±Ñ•ÈñðÑ¥Ñ±•-•ä¹¥¹±Õ‘•Ì¡Ñ¥Ñ±•¥±Ñ•È¤ì(€€€€€€€€€€€€€€€½¹ÍÐµ…Ñ¡•ÍMÑ…ÑÕÌ€ôÍ•±•Ñ•‘MÑ…ÑÕÍ•Ì¹±•¹Ñ €ôôô€ÀñðÍ•±•Ñ•‘MÑ…ÑÕÍ•Ì¹¥¹±Õ‘•Ì¡ÍÑ…ÑÕÍ-•ä¤ì(€€€€€€€€€€€€€€€½¹ÍÐµ…Ñ¡•ÍAÉ¥½É¥Ñä€ôÍ•±•Ñ•‘AÉ¥½É¥Ñ¥•Ì¹±•¹Ñ €ôôô€ÀñðÍ•±•Ñ•‘AÉ¥½É¥Ñ¥•Ì¹¥¹±Õ‘•Ì¡ÁÉ¥½É¥Ñå-•ä¤ì(€€€€€€€€€€€€€€€½¹ÍÐµ…Ñ¡•Í½¹ÑÉ…Ñ½È€ô€…½¹ÑÉ…Ñ½É¥±Ñ•Èñð½¹ÑÉ…Ñ½É-•ä€ôôô½¹ÑÉ…Ñ½É¥±Ñ•Èì((€€€€€€€€€€€€€€€¥˜€¡µ…Ñ¡•ÍQ¥Ñ±”€˜˜µ…Ñ¡•ÍMÑ…ÑÕÌ€˜˜µ…Ñ¡•ÍAÉ¥½É¥Ñä€˜˜µ…Ñ¡•Í½¹ÑÉ…Ñ½È¤ì(€€€€€€€€€€€€€€€€€€€€‘É½Ü¹Í¡½Ü ¤ì(€€€€€€€€€€€€€€€€€€€Ù¥Í¥‰±•½Õ¹Ð¬¬ì(€€€€€€€€€€€€€€€ô•±Í”ì(€€€€€€€€€€€€€€€€€€€€‘É½Ü¹¡¥‘” ¤ì(€€€€€€€€€€€€€€€€€€€‘•Ñ…¥±ÍI½Ü¹¡¥‘” ¤ì(€€€€€€€€€€€€€€€€€€€‘•Ñ…¥±ÍI½Ü¹™¥¹ œ¹É½Üµ‘•Ñ…¥±Ìœ¤¹¡¥‘” ¤ì(€€€€€€€€€€€€€€€€€€€¥½¸¹É•µ½Ù•±…ÍÌ ‰àµ¡•ÙÉ½¸µÕÀœ¤¹…‘‘±…ÍÌ ‰àµ¡•ÙÉ½¸µ‘½Ý¸œ¤ì(€€€€€€€€€€€€€€€ô(€€€€€€€€€€€ô¤ì((€€€€€€€€€€€½¹ÍÐ‘•™•ÑÍ5½‘…°€ô‰½½ÑÍÑÉ…À¹5½‘…°¹•Ñ%¹ÍÑ…¹”¡‘½Õµ•¹Ð¹•Ñ±•µ•¹Ñ	å% ‘•™•ÑÍ¥±Ñ•É5½‘…°œ¤¤(€€€€€€€€€€€€€€€ñð¹•Ü‰½½ÑÍÑÉ…À¹5½‘…°¡‘½Õµ•¹Ð¹•Ñ±•µ•¹Ñ	å% ‘•™•ÑÍ¥±Ñ•É5½‘…°œ¤¤ì(€€€€€€€€€€€‘•™•ÑÍ5½‘…°¹¡¥‘” ¤ì((€€€€€€€€€€€Í¡½ÝQ½…ÍÐ¡¥±Ñ•ÉÌ…ÁÁ±¥•è€‘íÙ¥Í¥‰±•½Õ¹Ñô‘•™•Ð‘íÙ¥Í¥‰±•½Õ¹Ð€ôôô€Ä€ü€œœ€è€ÌôÍ¡½Ý¹€°€ÍÕ•ÍÌœ¤ì(€€€€€€€ô¤ì((€€€€€€€€ œ•áÁ½ÉÑ½¹ÑÉ…Ñ½ÉÌœ¤¹½¸ ±¥¬œ°™Õ¹Ñ¥½¸ ¤ì(€€€€€€€€€€€±•ÐÍÙ½¹Ñ•¹Ð€ô€½µÁ…¹ä±QÉ…‘”±Q½Ñ…°±=Á•¸±A•¹‘¥¹œ±•ÁÑ•±I•©•Ñ•±1…ÍÐUÁ‘…Ñ•q¸œì((€€€€€€€€€€€€ œ½¹ÑÉ…Ñ½ÉÍQ…‰±”Ñ‰½‘äÑÈéÙ¥Í¥‰±”œ¤¹•… ¡™Õ¹Ñ¥½¸ ¤ì(€€€€€€€€€€€€€€€½¹ÍÐ€‘É½Ü€ô€¡Ñ¡¥Ì¤ì(€€€€€€€€€€€€€€€½¹ÍÐ•±±Ì€ô€‘É½Ü¹™¥¹ Ñœ¤ì(€€€€€€€€€€€€€€€¥˜€¡•±±Ì¹±•¹Ñ €„ôô€à¤ì(€€€€€€€€€€€€€€€€€€€É•ÑÕÉ¸ì(€€€€€€€€€€€€€€€ô((€€€€€€€€€€€€€€€½¹ÍÐ±•…¸€ôÙ…±Õ”€ôøMÑÉ¥¹œ¡Ù…±Õ”¤¹ÑÉ¥´ ¤¹É•Á±…” ½qÌ¬½œ°€œ€œ¤ì(€€€€€€€€€€€€€€€½¹ÍÐ½µÁ…¹ä€ô±•…¸ ¡•±±ÍlÁt¤¹Ñ•áÐ ¤¤¹É•Á±…” ¼°½œ°€œ€œ¤ì(€€€€€€€€€€€€€€€½¹ÍÐÑÉ…‘”€ô±•…¸ ¡•±±ÍlÅt¤¹Ñ•áÐ ¤¤¹É•Á±…” ¼°½œ°€œ€œ¤ì(€€€€€€€€€€€€€€€½¹ÍÐÑ½Ñ…°€ô€‘É½Ü¹‘…Ñ„ Ñ½Ñ…°œ¤€üü€¡9Õµ‰•È¡±•…¸ ¡•±±ÍlÉt¤¹Ñ•áÐ ¤¤¤ñð€À¤ì(€€€€€€€€€€€€€€€½¹ÍÐ½Á•¸€ô€‘É½Ü¹‘…Ñ„ ½Á•¸œ¤€üü€¡9Õµ‰•È¡±•…¸ ¡•±±ÍlÍt¤¹Ñ•áÐ ¤¤¤ñð€À¤ì(€€€€€€€€€€€€€€€½¹ÍÐÁ•¹‘¥¹œ€ô€‘É½Ü¹‘…Ñ„ Á•¹‘¥¹œœ¤€üü€¡9Õµ‰•È¡±•…¸ ¡•±±ÍlÑt¤¹Ñ•áÐ ¤¤¤ñð€À¤ì(€€€€€€€€€€€€€€€½¹ÍÐ…•ÁÑ•€ô€‘É½Ü¹‘…Ñ„ …•ÁÑ•œ¤€üü€¡9Õµ‰•È¡±•…¸ ¡•±±ÍlÕt¤¹Ñ•áÐ ¤¤¤ñð€À¤ì(€€€€€€€€€€€€€€€½¹ÍÐÉ•©•Ñ•€ô€‘É½Ü¹‘…Ñ„ É•©•Ñ•œ¤€üü€¡9Õµ‰•È¡±•…¸ ¡•±±ÍlÙt¤¹Ñ•áÐ ¤¤¤ñð€À¤ì(€€€€€€€€€€€€€€€½¹ÍÐ±…ÍÑUÁ‘…Ñ”€ô±•…¸ ¡•±±ÍlÝt¤¹Ñ•áÐ ¤¤¹É•Á±…” ¼°½œ°€œ€œ¤ì((€€€€€€€€€€€€€€€½¹ÍÐ±¥¹”€ôm½µÁ…¹ä°ÑÉ…‘”°Ñ½Ñ…°°½Á•¸°Á•¹‘¥¹œ°…•ÁÑ•°É•©•Ñ•°±…ÍÑUÁ‘…Ñ•t(€€€€€€€€€€€€€€€€€€€€¹µ…À¡Ù…±Õ”€ôø€ˆ‘íMÑÉ¥¹œ¡Ù…±Õ”¤¹É•Á±…” ¼ˆ½œ°€œˆˆœ¥ô‰€¤(€€€€€€€€€€€€€€€€€€€€¹©½¥¸ œ°œ¤ì(€€€€€€€€€€€€€€€ÍÙ½¹Ñ•¹Ð€¬ô€‘í±¥¹•õq¹€ì(€€€€€€€€€€€ô¤ì((€€€€€€€€€€€½¹ÍÐ‰±½ˆ€ô¹•Ü	±½ˆ¡mÍÙ½¹Ñ•¹Ñt°ìÑåÁ”è€Ñ•áÐ½ÍØí¡…ÉÍ•ÐõÕÑ˜´àìœô¤ì(€€€€€€€€€€€½¹ÍÐ‘½Ý¹±½…‘UÉ°€ôUI0¹É•…Ñ•=‰©•ÑUI0¡‰±½ˆ¤ì(€€€€€€€€€€€½¹ÍÐÑ•µÁ1¥¹¬€ô‘½Õµ•¹Ð¹É•…Ñ•±•µ•¹Ð „œ¤ì(€€€€€€€€€€€Ñ•µÁ1¥¹¬¹¡É•˜€ô‘½Ý¹±½…‘UÉ°ì(€€€€€€€€€€€Ñ•µÁ1¥¹¬¹Í•ÑÑÑÉ¥‰ÕÑ” ‘½Ý¹±½…œ°½¹ÑÉ…Ñ½É}ÍÑ…ÑÍ|‘í¹•Ü…Ñ” ¤¹Ñ½%M=MÑÉ¥¹œ ¤¹Í±¥” À°€ÄÀ¥ô¹ÍÙ€¤ì(€€€€€€€€€€€‘½Õµ•¹Ð¹‰½‘ä¹…ÁÁ•¹‘¡¥±¡Ñ•µÁ1¥¹¬¤ì(€€€€€€€€€€€Ñ•µÁ1¥¹¬¹±¥¬ ¤ì(€€€€€€€€€€€‘½Õµ•¹Ð¹‰½‘ä¹É•µ½Ù•¡¥±¡Ñ•µÁ1¥¹¬¤ì(€€€€€€€€€€€UI0¹É•Ù½­•=‰©•ÑUI0¡‘½Ý¹±½…‘UÉ°¤ì((€€€€€€€€€€€Í¡½ÝQ½…ÍÐ ½¹ÑÉ…Ñ½È‘…Ñ„•áÁ½ÉÑ•ÍÕ•ÍÍ™Õ±±äœ°€ÍÕ•ÍÌœ¤ì(€€€€€€€ô¤ì((€€€€€€€€ œÉ•™É•Í¡½¹ÑÉ…Ñ½ÉÌ°€É•™É•Í¡•™•ÑÌœ¤¹½¸ ±¥¬œ°™Õ¹Ñ¥½¸ ¤ì(€€€€€€€€€€€€¡Ñ¡¥Ì¤¹ÁÉ½À ‘¥Í…‰±•œ°ÑÉÕ”¤ì(€€€€€€€€€€€Í•ÑQ¥µ•½ÕÐ  ¤€ôøÝ¥¹‘½Ü¹±½…Ñ¥½¸¹É•±½… ¤°€ÌÀÀ¤ì(€€€€€€€ô¤ì(€€€ô¤ì(€€€€ð½ÍÉ¥ÁÐø(ð½‰½‘äø(ð½¡Ñµ°ø(
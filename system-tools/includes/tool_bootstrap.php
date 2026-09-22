<?php
/**
 * system-tools/includes/tool_bootstrap.php
 * Shared bootstrap and layout helpers for system tool pages
 */

declare(strict_types=1);

if (defined('SYSTEM_TOOLS_BOOTSTRAP_LOADED')) {
    return;
}

define('SYSTEM_TOOLS_BOOTSTRAP_LOADED', true);

// Enable verbose error reporting for diagnostic tools
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../../logs/system-tools.log');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header('Location: ../login.php');
    exit();
}

require_once __DIR__ . '/../../config/database.php';

global $db, $dbErrorMessage, $toolCurrentUser, $toolCurrentTime;

$db = null;
$dbErrorMessage = null;

try {
    $database = new Database();
    $db = $database->getConnection();
} catch (Throwable $e) {
    $dbErrorMessage = $e->getMessage();
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$username = (string)($_SESSION['username'] ?? '');
$fullName = trim((string)($_SESSION['full_name'] ?? ''));
$displayName = $fullName !== '' ? $fullName : $username;
$sessionUserType = (string)($_SESSION['user_type'] ?? 'viewer');

$roleDefinitions = [
    1 => ['name' => 'Administrator', 'description' => 'Full system access with all administrative capabilities'],
    2 => ['name' => 'Manager', 'description' => 'Project management and oversight capabilities'],
    3 => ['name' => 'Contractor', 'description' => 'Contractor access for defect updates and responses'],
    4 => ['name' => 'Viewer', 'description' => 'Read-only access to view defects and reports'],
    5 => ['name' => 'Client', 'description' => 'Client access to view and comment on defects'],
];

$userRoles = [];

if ($db instanceof PDO) {
    try {
        $stmt = $db->prepare('SELECT role_id FROM user_roles WHERE user_id = :user_id AND deleted_at IS NULL');
        $stmt->execute(['user_id' => $userId]);
        $userRoles = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (PDOException $roleException) {
        $dbErrorMessage = $dbErrorMessage ?: $roleException->getMessage();
    }
}

$isAdmin = in_array(1, $userRoles, true) || $sessionUserType === 'admin';
$isManager = in_array(2, $userRoles, true) || $sessionUserType === 'manager';

if (!($isAdmin || $isManager)) {
    header('Location: ../dashboard.php');
    exit();
}

$toolCurrentUser = [
    'id' => $userId,
    'username' => $username,
    'full_name' => $fullName,
    'display' => $displayName,
    'roles' => $userRoles,
    'role_definitions' => $roleDefinitions,
    'is_admin' => $isAdmin,
    'is_manager' => $isManager,
];

$toolCurrentTime = new DateTimeImmutable('now');

if (!function_exists('tool_render_header')) {
    function tool_render_header(string $title, string $subtitle = '', array $breadcrumbs = []): void
    {
        global $toolCurrentUser, $toolCurrentTime;

        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $safeSubtitle = $subtitle !== '' ? htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8') : '';
        $displayName = htmlspecialchars($toolCurrentUser['display'] ?? '', ENT_QUOTES, 'UTF-8');
        $timestamp = $toolCurrentTime->format('d-m-Y H:i:s');

        echo "<!DOCTYPE html>\n";
        echo '<html lang="en">';
        echo '<head>';
        echo '    <meta charset="UTF-8">';
        echo '    <meta name="viewport" content="width=device-width, initial-scale=1.0">';
        echo '    <title>' . $safeTitle . ' | System Tools</title>';
        echo '    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">';
        echo '    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">';
        echo '    <link href="../css/app.css" rel="stylesheet">';
        echo '</head>';
        echo '<body class="tool-body" data-bs-theme="dark">';
        echo '<nav class="navbar navbar-expand-lg navbar-dark sticky-top">';
        echo '  <div class="container-fluid">';
        echo '    <a class="navbar-brand" href="../admin.php">';
        echo "      <i class='bx bx-cog me-1'></i>System Tools";
        echo '    </a>';
        echo '    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#toolNavbar">';
        echo '      <span class="navbar-toggler-icon"></span>';
        echo '    </button>';
        echo '    <div class="collapse navbar-collapse" id="toolNavbar">';
        echo '      <ul class="navbar-nav me-auto mb-2 mb-lg-0">';
        echo '        <li class="nav-item"><a class="nav-link" href="../admin.php"><i class="bx bx-grid-alt me-1"></i>Admin Dashboard</a></li>';
        echo '        <li class="nav-item"><a class="nav-link" href="../dashboard.php"><i class="bx bx-line-chart me-1"></i>Overview Dashboard</a></li>';
        echo '      </ul>';
        echo '      <ul class="navbar-nav mb-2 mb-lg-0">';
        echo '        <li class="nav-item d-flex align-items-center text-muted me-lg-3">';
        echo "          <i class='bx bx-time-five me-1'></i><span class='small'>{$timestamp} UTC</span>";
        echo '        </li>';
        echo '        <li class="nav-item dropdown">';
        echo '          <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">';
        echo "            <i class='bx bx-user-circle'></i> {$displayName}";
        echo '          </a>';
        echo '          <ul class="dropdown-menu dropdown-menu-end">';
        echo '            <li><a class="dropdown-item" href="../profile.php"><i class="bx bx-user"></i> Profile</a></li>';
        echo '            <li><a class="dropdown-item" href="../settings.php"><i class="bx bx-cog"></i> Settings</a></li>';
        echo '            <li><hr class="dropdown-divider"></li>';
        echo '            <li><a class="dropdown-item" href="../logout.php"><i class="bx bx-log-out"></i> Logout</a></li>';
        echo '          </ul>';
        echo '        </li>';
        echo '      </ul>';
        echo '    </div>';
        echo '  </div>';
        echo '</nav>';

        echo '<main class="tool-page container-xl py-4">';
        echo '  <div class="tool-header mb-4">';
        echo '    <div>';
        echo '      <h1 class="h3 mb-1">' . $safeTitle . '</h1>';
        if ($safeSubtitle !== '') {
            echo '      <p class="text-muted mb-0">' . $safeSubtitle . '</p>';
        }
        echo '    </div>';

        if (!empty($breadcrumbs)) {
            echo '    <nav aria-label="breadcrumb" class="tool-breadcrumb mt-3">';
            echo '      <ol class="breadcrumb mb-0">';
            $lastIndex = count($breadcrumbs) - 1;
            foreach ($breadcrumbs as $index => $crumb) {
                $label = htmlspecialchars($crumb['label'] ?? '', ENT_QUOTES, 'UTF-8');
                $href = $crumb['href'] ?? '';
                $isLast = $index === $lastIndex;
                if ($href !== '' && !$isLast) {
                    $safeHref = htmlspecialchars($href, ENT_QUOTES, 'UTF-8');
                    echo '        <li class="breadcrumb-item"><a href="' . $safeHref . '">' . $label . '</a></li>';
                } else {
                    echo '        <li class="breadcrumb-item active" aria-current="page">' . $label . '</li>';
                }
            }
            echo '      </ol>';
            echo '    </nav>';
        }

        echo '  </div>';
    }
}

if (!function_exists('tool_render_footer')) {
    function tool_render_footer(): void
    {
        echo '  </main>';
        echo '  <footer class="tool-footer py-3 mt-auto">';
        echo '    <div class="container-xl text-center text-muted small">';
        echo '      <span>McGoff Construction Defect Tracker &mdash; System Tools Suite</span>';
        echo '    </div>';
        echo '  </footer>';
        echo '  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>';
        echo '</body>';
        echo '</html>';
    }
}

if (!function_exists('tool_format_bytes')) {
    function tool_format_bytes(?int $bytes, int $precision = 2): string
    {
        if ($bytes === null || $bytes < 0) {
            return 'N/A';
        }

        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $pow = (int)floor(log($bytes, 1024));
        $pow = max(0, min($pow, count($units) - 1));

        $bytes /= (1024 ** $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}

if (!function_exists('tool_status_variant')) {
    function tool_status_variant(string $status): string
    {
        return match (strtolower($status)) {
            'healthy', 'success' => 'success',
            'warning' => 'warning',
            'critical', 'error', 'failed' => 'danger',
            default => 'secondary',
        };
    }
}

if (!function_exists('tool_status_label')) {
    function tool_status_label(string $status): string
    {
        return match (strtolower($status)) {
            'healthy', 'success' => 'Healthy',
            'warning' => 'Warning',
            'critical', 'error', 'failed' => 'Critical',
            default => ucfirst($status),
        };
    }
}

if (!function_exists('tool_status_icon')) {
    function tool_status_icon(string $status): string
    {
        return match (strtolower($status)) {
            'healthy', 'success' => 'bx-check-circle',
            'warning' => 'bx-error',
            'critical', 'error', 'failed' => 'bx-x-circle',
            default => 'bx-info-circle',
        };
    }
}

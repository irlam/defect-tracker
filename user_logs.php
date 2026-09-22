<?php
// user_logs.php

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication check
if (!isset($_SESSION['username']) || !isset($_SESSION['user_type'])) {
    header("Location: login.php");
    exit();
}

// Authorization check - only admin users can view user logs
if ($_SESSION['user_type'] !== 'admin') {
    error_log("Unauthorized access attempt to user_logs.php by user: " . $_SESSION['username'] . " (User Type: " . $_SESSION['user_type'] . ")");
    header("Location: dashboard.php?error=unauthorized");
    exit();
}

// Initialize variables
$pageTitle = 'User Logs';
$currentUser = $_SESSION['username'];
$currentDateTime = date('Y-m-d H:i:s');

// Define INCLUDED constant for navbar security
define('INCLUDED', true);

require_once 'includes/functions.php';
require_once 'config/database.php';
require_once 'includes/navbar.php';

// Set timezone to UK
date_default_timezone_set('Europe/London');

// Pagination settings
$logsPerPage = 10;
$currentPage = max(1, (int)($_GET['page'] ?? 1));
$offset = ($currentPage - 1) * $logsPerPage;

// Search keyword
$searchKeyword = trim((string)($_GET['search'] ?? ''));

// Date range filter (in days) - default to 30
$rangeOptions = [
    '7' => '7 Days',
    '30' => '30 Days',
    '90' => '90 Days',
    '180' => '180 Days',
    '365' => '365 Days',
    'all' => 'All Time'
];

$selectedRange = $_GET['range'] ?? '30';
if (!array_key_exists($selectedRange, $rangeOptions)) {
    $selectedRange = '30';
}

$fromDate = null;
if ($selectedRange !== 'all') {
    $daysBack = (int)$selectedRange;
    $fromDate = date('Y-m-d H:i:s', strtotime(sprintf('-%d days', $daysBack)));
}

// Build search conditions for each log source
$userLogsSearchCondition = '';
if ($searchKeyword !== '') {
    $userLogsSearchCondition = " AND (
        u.username LIKE :search
        OR ul.action LIKE :search
        OR ul.details LIKE :search
        OR ul.ip_address LIKE :search
    )";
}

$activityLogsSearchCondition = '';
if ($searchKeyword !== '') {
    $activityLogsSearchCondition = " AND (
        u.username LIKE :search
        OR al.action LIKE :search
        OR al.action_type LIKE :search
        OR al.details LIKE :search
        OR CONCAT('Defect #', al.defect_id) LIKE :search
    )";
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Initialize the Navbar class
    $navbar = new Navbar($db, $_SESSION['user_id'], $_SESSION['username']);

    $userDateCondition = $fromDate ? ' AND ul.action_at >= :fromDate' : '';
    $activityDateCondition = $fromDate ? ' AND al.created_at >= :fromDate' : '';

    // Build comprehensive query combining user_logs and activity_logs
    // First, get total count for pagination
    $countQuery = "
        SELECT COUNT(*) FROM (
            SELECT ul.action_at as log_time
            FROM user_logs ul 
            LEFT JOIN users u ON ul.user_id = u.id 
            WHERE 1=1 {$userDateCondition}{$userLogsSearchCondition}
            
            UNION ALL
            
            SELECT al.created_at as log_time
            FROM activity_logs al
            LEFT JOIN users u ON al.user_id = u.id
            WHERE 1=1 {$activityDateCondition}{$activityLogsSearchCondition}
        ) as combined_logs
    ";
    
    $countStmt = $db->prepare($countQuery);
    if ($fromDate) {
        $countStmt->bindValue(':fromDate', $fromDate);
    }
    if (!empty($searchKeyword)) {
        $countStmt->bindValue(':search', '%' . $searchKeyword . '%');
    }
    $countStmt->execute();
    $totalLogs = (int)($countStmt->fetchColumn() ?: 0);
    $totalPages = $totalLogs > 0 ? (int)ceil($totalLogs / $logsPerPage) : 0;

    // Get comprehensive logs combining user_logs and activity_logs
    $query = "
        SELECT 
            'user_log' as log_type,
            ul.id,
            ul.action_at as log_time,
            u.username,
            ua.username AS action_by_username,
            ul.action,
            NULL as action_type,
            ul.ip_address,
            ul.details,
            NULL as defect_id
        FROM user_logs ul
        LEFT JOIN users u ON ul.user_id = u.id
        LEFT JOIN users ua ON ul.action_by = ua.id
        WHERE 1=1 {$userDateCondition}{$userLogsSearchCondition}
        
        UNION ALL
        
        SELECT 
            'activity_log' as log_type,
            al.id,
            al.created_at as log_time,
            u.username,
            NULL AS action_by_username,
            al.action,
            al.action_type,
            NULL as ip_address,
            al.details,
            al.defect_id
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        WHERE 1=1 {$activityDateCondition}{$activityLogsSearchCondition}
        
        ORDER BY log_time DESC
        LIMIT :offset, :logsPerPage
    ";

    $stmt = $db->prepare($query);
    if ($fromDate) {
        $stmt->bindValue(':fromDate', $fromDate);
    }
    if (!empty($searchKeyword)) {
        $stmt->bindValue(':search', '%' . $searchKeyword . '%');
    }
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindParam(':logsPerPage', $logsPerPage, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $paginationParams = [];
    if ($searchKeyword !== '') {
        $paginationParams['search'] = $searchKeyword;
    }
    $paginationParams['range'] = $selectedRange;
    $paginationQueryString = http_build_query($paginationParams);
    $paginationQuerySuffix = $paginationQueryString ? '&' . $paginationQueryString : '';

} catch (Exception $e) {
    error_log("User Logs Error: " . $e->getMessage());
    $error_message = "An error occurred while loading user logs: " . $e->getMessage();
}

// Helper function to format date to UK format
function formatUKDateTime($date) {
    return date('d/m/Y H:i', strtotime($date));
}

// Helper function to get action badge class
function getActionBadgeClass($action, $action_type = null) {
    // Handle activity_logs action types
    if ($action_type) {
        switch (strtoupper($action_type)) {
            case 'ASSIGN':
                return 'bg-info';
            case 'CREATE':
                return 'bg-success';
            case 'UPDATE':
                return 'bg-primary';
            case 'DELETE':
                return 'bg-danger';
            case 'ACCEPT':
                return 'bg-success';
            case 'REJECT':
                return 'bg-warning';
            default:
                return 'bg-secondary';
        }
    }
    
    // Handle user_logs actions
    switch (strtolower($action)) {
        case 'create_contractor':
        case 'create_user':
        case 'create_defect':
            return 'bg-success';
        case 'update_contractor':
        case 'update_user':
        case 'update_defect':
        case 'status_changed':
        case 'type_changed':
            return 'bg-primary';
        case 'delete_contractor':
        case 'delete_user':
        case 'delete_defect':
            return 'bg-danger';
        case 'login':
        case 'logout':
            return 'bg-info';
        default:
            return 'bg-secondary';
    }
}

// Helper function to format action text
function formatActionText($action, $action_type = null) {
    if ($action_type) {
        return ucwords(strtolower(str_replace('_', ' ', $action_type)));
    }
    return ucwords(str_replace('_', ' ', $action));
}

// Function to format the details
function formatLogDetails($details, $action_by_username) {
    $data = json_decode($details, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        return htmlspecialchars($details);
    }

    $output = "<ul>";
    foreach ($data as $key => $value) {
        $formattedValue = is_string($value) ? htmlspecialchars($value) : json_encode($value);

        // Format the timestamp into UK format if it's a timestamp
        if ($key === 'timestamp') {
            $formattedValue = formatUKDateTime($value);
        }

        // Check if the key is 'changed_by' or 'user_id' and replace the value with the username
        if ($key === 'changed_by' || $key === 'user_id') {
            $formattedValue = htmlspecialchars($action_by_username);
        }

        $output .= "<li><strong>" . htmlspecialchars(ucwords(str_replace('_', ' ', $key))) . ":</strong> " . $formattedValue . "</li>";
    }
    $output .= "</ul>";
    return $output;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="User Logs - Defect Tracker System">
    <meta name="author" content="<?php echo htmlspecialchars($currentUser); ?>">
    <meta name="last-modified" content="<?php echo date('Y-m-d H:i:s'); ?>">
    <title><?php echo $pageTitle; ?> - Defect Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="css/app.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="/favicons/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicons/favicon.svg" />
    <link rel="shortcut icon" href="/favicons/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png" />
    <link rel="manifest" href="/favicons/site.webmanifest" />
</head>
<body class="tool-body has-app-navbar" data-bs-theme="dark">
    <?php $navbar->render(); ?>

    <main class="tool-page container-xl py-4">
        <header class="tool-header mb-5">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <h1 class="h3 mb-2">System Activity Logs</h1>
                    <p class="text-muted mb-0">Comprehensive view of all user actions and system updates from the last 30 days.</p>
                </div>
            </div>
            <nav aria-label="breadcrumb" class="mt-3">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                    <li class="breadcrumb-item active">Activity Logs</li>
                </ol>
            </nav>
        </header>

        <section class="mb-4">
            <div class="card border-0">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <h2 class="h5 mb-0">Filter and Search Logs</h2>
                    </div>
                </div>
                <div class="card-body">
                    <form class="row g-3" method="GET">
                        <div class="col-lg-6">
                            <input class="form-control" type="search" name="search" placeholder="Search by user, action, IP address, defect ID, or details..." value="<?php echo htmlspecialchars($searchKeyword); ?>">
                        </div>
                        <div class="col-sm-6 col-lg-4">
                            <select class="form-select" name="range" aria-label="Select time range">
                                <?php foreach ($rangeOptions as $value => $label): ?>
                                    <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $value === $selectedRange ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-6 col-lg-2">
                            <button class="btn btn-primary w-100" type="submit">
                                <i class='bx bx-search'></i> Search
                            </button>
                        </div>
                        <input type="hidden" name="page" value="1">
                    </form>
                </div>
            </div>
        </section>

        <section>
            <div class="card border-0">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <h2 class="h5 mb-1">Activity Timeline</h2>
                        <p class="text-muted small mb-0">
                            Showing <?php echo number_format($totalLogs); ?> log entries from <?php echo htmlspecialchars($rangeOptions[$selectedRange] ?? $rangeOptions['30']); ?>
                            <?php if ($searchKeyword !== ''): ?>matching “<?php echo htmlspecialchars($searchKeyword); ?>”<?php endif; ?>
                        </p>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($logs)): ?>
                        <div class="alert alert-info mb-0">
                            <i class='bx bx-info-circle me-2'></i>
                            No activity logs found for the selected range<?php echo $searchKeyword !== '' ? ' and search terms' : ''; ?>.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-dark table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th scope="col">Date/Time</th>
                                        <th scope="col">User</th>
                                        <th scope="col">Action</th>
                                        <th scope="col">Type</th>
                                        <th scope="col">Context</th>
                                        <th scope="col">Details</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($logs as $log): ?>
                                        <tr>
                                            <td>
                                                <small><?php echo formatUKDateTime($log['log_time']); ?></small>
                                            </td>
                                            <td>
                                                <i class='bx bx-user-circle me-1'></i>
                                                <?php echo htmlspecialchars($log['username'] ?? 'System'); ?>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo getActionBadgeClass($log['action'], $log['action_type']); ?>">
                                                    <?php echo formatActionText($log['action'], $log['action_type']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($log['log_type'] === 'activity_log'): ?>
                                                    <span class="badge bg-info-subtle text-info-emphasis">
                                                        <i class='bx bx-error-circle'></i> Defect
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning-subtle text-warning-emphasis">
                                                        <i class='bx bx-user'></i> User
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($log['defect_id']): ?>
                                                    <a href="view_defect.php?id=<?php echo (int)$log['defect_id']; ?>" class="text-decoration-none">
                                                        Defect #<?php echo (int)$log['defect_id']; ?>
                                                    </a>
                                                <?php elseif ($log['ip_address']): ?>
                                                    <small class="text-muted"><?php echo htmlspecialchars($log['ip_address']); ?></small>
                                                <?php else: ?>
                                                    <small class="text-muted">-</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small>
                                                    <?php 
                                                    if ($log['log_type'] === 'user_log') {
                                                        echo formatLogDetails($log['details'], $log['action_by_username']); 
                                                    } else {
                                                        echo nl2br(htmlspecialchars($log['details']));
                                                    }
                                                    ?>
                                                </small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if ($totalPages > 1): ?>
                        <nav aria-label="Page navigation" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?php if ($currentPage <= 1) echo 'disabled'; ?>">
                                    <a class="page-link" href="?page=<?php echo max(1, $currentPage - 1); ?><?php echo $paginationQuerySuffix; ?>">
                                        <i class='bx bx-chevron-left'></i> Previous
                                    </a>
                                </li>
                                <?php 
                                $startPage = max(1, $currentPage - 2);
                                $endPage = min($totalPages, $currentPage + 2);
                                
                                if ($startPage > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=1<?php echo $paginationQuerySuffix; ?>">1</a>
                                    </li>
                                    <?php if ($startPage > 2): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                    <li class="page-item <?php if ($i == $currentPage) echo 'active'; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo $paginationQuerySuffix; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($endPage < $totalPages): ?>
                                    <?php if ($endPage < $totalPages - 1): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php endif; ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $totalPages; ?><?php echo $paginationQuerySuffix; ?>"><?php echo $totalPages; ?></a>
                                    </li>
                                <?php endif; ?>
                                
                                <li class="page-item <?php if ($currentPage >= $totalPages) echo 'disabled'; ?>">
                                    <a class="page-link" href="?page=<?php echo min($totalPages, $currentPage + 1); ?><?php echo $paginationQuerySuffix; ?>">
                                        Next <i class='bx bx-chevron-right'></i>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
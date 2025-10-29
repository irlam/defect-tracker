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
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
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
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($currentPage - 1) * $logsPerPage;

// Search functionality
$searchKeyword = isset($_GET['search']) ? $_GET['search'] : '';
$searchCondition = '';
if (!empty($searchKeyword)) {
    $searchCondition = " AND (u.username LIKE :search OR ul.action LIKE :search OR ul.ip_address LIKE :search OR ul.details LIKE :search)";
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Initialize the Navbar class
    $navbar = new Navbar($db, $_SESSION['user_id'], $_SESSION['username']);

    // Calculate the date 90 days ago
    $ninetyDaysAgo = date('Y-m-d H:i:s', strtotime('-90 days'));

    // Get total number of logs (for pagination)
    $countQuery = "SELECT COUNT(*) FROM user_logs ul LEFT JOIN users u ON ul.user_id = u.id WHERE ul.action_at >= :ninetyDaysAgo $searchCondition";
    $countStmt = $db->prepare($countQuery);
    $countStmt->bindParam(':ninetyDaysAgo', $ninetyDaysAgo);
    if (!empty($searchKeyword)) {
        $countStmt->bindValue(':search', '%' . $searchKeyword . '%');
    }
    $countStmt->execute();
    $totalLogs = $countStmt->fetchColumn();
    $totalPages = ceil($totalLogs / $logsPerPage);

    // Get user logs with usernames, with pagination and search
    $query = "SELECT
                ul.*,
                u.username,
                ua.username AS action_by_username
              FROM user_logs ul
              LEFT JOIN users u ON ul.user_id = u.id
              LEFT JOIN users ua ON ul.action_by = ua.id
              WHERE ul.action_at >= :ninetyDaysAgo $searchCondition
              ORDER BY ul.action_at DESC
              LIMIT :offset, :logsPerPage";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':ninetyDaysAgo', $ninetyDaysAgo);
    if (!empty($searchKeyword)) {
        $stmt->bindValue(':search', '%' . $searchKeyword . '%');
    }
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindParam(':logsPerPage', $logsPerPage, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("User Logs Error: " . $e->getMessage());
    $error_message = "An error occurred while loading user logs: " . $e->getMessage();
}

// Helper function to format date to UK format
function formatUKDateTime($date) {
    return date('d/m/Y H:i', strtotime($date));
}

// Helper function to get action badge class
function getActionBadgeClass($action) {
    switch (strtolower($action)) {
        case 'create_contractor':
            return 'bg-success';
        case 'update_contractor':
            return 'bg-primary';
        case 'delete_contractor':
            return 'bg-danger';
        default:
            return 'bg-secondary';
    }
}

// Helper function to format action text
function formatActionText($action) {
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
    <meta name="last-modified" content="2025-02-08 14:10:30">
    <title><?php echo $pageTitle; ?> - Defect Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="/favicons/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicons/favicon.svg" />
    <link rel="shortcut icon" href="/favicons/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png" />
    <link rel="manifest" href="/favicons/site.webmanifest" />
    <style>
        /* Styles for the main content area */
.main-content {
    padding: 20px;
    min-height: 100vh;
    background: linear-gradient(135deg, #f5f7fa 0%, #e4e9f2 100%);
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;  /* Centers the content horizontally */
}

        .table-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            padding: 20px;
            width: 100%;
            max-width: 1200px;
        }

        .table thead th {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            border: none;
            padding: 15px;
        }

        .table tbody tr:hover {
            background-color: rgba(52, 152, 219, 0.1);
        }

        .table td {
            vertical-align: middle;
            padding: 12px;
        }

        .d-flex {
            justify-content: center;
        }

        .pagination {
            justify-content: center;
        }

        @media (max-width: 991.98px) {
            .main-content {
                margin-left: 0;
                padding-top: 60px;
            }
        }
    </style>
</head>
<body>
    <?php $navbar->render(); ?> <!-- Ensure this is rendering the navbar -->

    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">User Activity Logs</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                            <li class="breadcrumb-item active">User Logs</li>
                        </ol>
                    </nav>
                </div>
            </div>

            <!-- Search and Pagination -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <form class="d-flex" method="GET">
                    <input class="form-control me-2" type="search" name="search" placeholder="Search logs" value="<?php echo htmlspecialchars($searchKeyword); ?>">
                    <input type="hidden" name="page" value="1"> <!-- Reset page to 1 when searching -->
                    <button class="btn btn-outline-primary" type="submit">Search</button>
                </form>

                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-end">
                        <li class="page-item <?php if ($currentPage <= 1) echo 'disabled'; ?>">
                            <a class="page-link" href="?page=<?php echo $currentPage - 1; if(!empty($searchKeyword)) echo '&search=' . htmlspecialchars($searchKeyword); ?>">Previous</a>
                        </li>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?php if ($i == $currentPage) echo 'active'; ?>">
                                <a class="page-link" href="?page=<?php echo $i; if(!empty($searchKeyword)) echo '&search=' . htmlspecialchars($searchKeyword); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php if ($currentPage >= $totalPages) echo 'disabled'; ?>">
                            <a class="page-link" href="?page=<?php echo $currentPage + 1; if(!empty($searchKeyword)) echo '&search=' . htmlspecialchars($searchKeyword); ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            </div>

            <div class="table-container">
                <?php if (empty($logs)): ?>
                    <div class="alert alert-info">
                        <i class='bx bx-info-circle me-2'></i>
                        No user activity logs found in the last 90 days.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th scope="col">Date/Time</th>
                                    <th scope="col">User</th>
                                    <th scope="col">Action</th>
                                    <th scope="col">IP Address</th>
                                    <th scope="col">Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td><?php echo formatUKDateTime($log['action_at']); ?></td>
                                        <td>
                                            <i class='bx bx-user-circle me-1'></i>
                                            <?php echo htmlspecialchars($log['username'] ?? 'System'); ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo getActionBadgeClass($log['action']); ?>">
                                                <?php echo formatActionText($log['action']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small><?php echo htmlspecialchars($log['ip_address']); ?></small>
                                        </td>
                                        <td><?php echo formatLogDetails($log['details'], $log['action_by_username']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>


        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
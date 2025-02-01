<?php
// user_logs.php
// Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): 2025-01-25 12:08:22
// Current User's Login: irlam

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

try {
    $database = new Database();
    $db = $database->getConnection();

    // Initialize the Navbar class
    $navbar = new Navbar($db, $_SESSION['user_id']);

    // Get user logs with usernames
    $query = "SELECT 
                ul.*,
                u.username
              FROM user_logs ul
              LEFT JOIN users u ON ul.user_id = u.id
              ORDER BY ul.action_at DESC";

    $stmt = $db->prepare($query);
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="User Logs - Defect Tracker System">
    <meta name="author" content="<?php echo htmlspecialchars($currentUser); ?>">
    <meta name="last-modified" content="2025-01-25 12:08:22">
    <title><?php echo $pageTitle; ?> - Defect Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <style>
        .main-content {
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e9f2 100%);
        }

        .table-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            padding: 20px;
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

        @media (max-width: 991.98px) {
            .main-content {
                margin-left: 0;
                padding-top: 60px;
            }
        }
    </style>
</head>
<body>
    <?php echo $navbar->render(); ?> <!-- Ensure this is rendering the navbar -->

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

            <div class="table-container">
                <?php if (empty($logs)): ?>
                    <div class="alert alert-info">
                        <i class='bx bx-info-circle me-2'></i>
                        No user activity logs found.
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
                                        <td><?php echo htmlspecialchars($log['details']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Pagination - if needed -->
            <?php if (!empty($logs) && count($logs) > 30): ?>
                <div class="d-flex justify-content-center mt-4">
                    <nav aria-label="User logs pagination">
                        <ul class="pagination">
                            <li class="page-item disabled">
                                <a class="page-link" href="#" tabindex="-1" aria-disabled="true">Previous</a>
                            </li>
                            <li class="page-item active" aria-current="page">
                                <a class="page-link" href="#">1</a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="#">2</a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="#">3</a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="#">Next</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
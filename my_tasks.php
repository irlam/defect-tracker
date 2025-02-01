<?php
// my_tasks.php
// Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): 2025-01-17 07:33:49
// Current User's Login: irlam
// Error reporting and logging setup
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debug session
echo "<!-- Session debug: " . print_r($_SESSION, true) . " -->";

// Authentication check
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Define INCLUDED constant for navbar security
define('INCLUDED', true);

require_once 'includes/functions.php';
require_once 'config/database.php';
require_once 'includes/navbar.php'; // Include the navbar file

$pageTitle = 'My Assigned Tasks';
$currentUser = $_SESSION['username'];
$currentDateTime = date('Y-m-d H:i:s');

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Debug connection
    echo "<!-- Database connected -->";
    
    // Initialize the Navbar class
    $navbar = new Navbar($db, $_SESSION['user_id']);

    // Initialize variables
    $tasks = [];
    $taskStats = [
        'total' => 0,
        'open_tasks' => 0,
        'in_progress' => 0,
        'completed' => 0,
        'overdue' => 0
    ];

    // Get tasks assigned to current user
    $query = "SELECT 
                d.id,
                d.title,
                d.description,
                d.status,
                d.priority,
                d.due_date,
                d.created_at,
                p.name as project_name,
                c.company_name as contractor_name
              FROM defects d
              LEFT JOIN projects p ON d.project_id = p.id
              LEFT JOIN contractors c ON d.contractor_id = c.id
              WHERE d.assigned_to = :username
              ORDER BY 
                CASE 
                    WHEN d.status = 'open' THEN 1
                    WHEN d.status = 'in_progress' THEN 2
                    ELSE 3
                END,
                CASE 
                    WHEN d.priority = 'critical' THEN 1
                    WHEN d.priority = 'high' THEN 2
                    WHEN d.priority = 'medium' THEN 3
                    ELSE 4
                END,
                d.due_date ASC";

    echo "<!-- Query: " . $query . " -->";
    echo "<!-- Username: " . $currentUser . " -->";

    $stmt = $db->prepare($query);
    $stmt->bindParam(":username", $currentUser);
    $stmt->execute();
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<!-- Number of tasks found: " . count($tasks) . " -->";

    // Get tasks statistics with error checking
    $statsQuery = "SELECT 
                    COALESCE(COUNT(*), 0) as total,
                    COALESCE(SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END), 0) as open_tasks,
                    COALESCE(SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END), 0) as in_progress,
                    COALESCE(SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END), 0) as completed,
                    COALESCE(SUM(CASE WHEN due_date < CURRENT_DATE AND status != 'closed' THEN 1 ELSE 0 END), 0) as overdue
                  FROM defects 
                  WHERE assigned_to = :username";

    $statsStmt = $db->prepare($statsQuery);
    $statsStmt->bindParam(":username", $currentUser);
    $statsStmt->execute();
    $taskStats = $statsStmt->fetch(PDO::FETCH_ASSOC);

    echo "<!-- Stats: " . print_r($taskStats, true) . " -->";

} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    error_log("My Tasks Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>
        /* Main Layout */
        :root {
            --sidebar-width: 250px;
            --navbar-height: 60px;
            --primary-color: #2c3e50;
            --secondary-color: #34495e;
        }

        body {
            min-height: 100vh;
            background-color: #f8f9fa;
        }

        /* Main Content Area */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            min-height: 100vh;
            background-color: #f8f9fa;
        }

        /* Cards */
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }

        /* Statistics Cards */
        .card h2 {
            font-size: 2rem;
            font-weight: 600;
        }

        .card-title {
            font-size: 0.875rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Table Styles */
        .table {
            font-size: 0.875rem;
        }

        .table thead th {
            background-color: #f8f9fa;
            border-top: none;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding-top: 1rem;
            padding-bottom: 1rem;
        }

        .table tbody tr:hover {
            background-color: rgba(0, 0, 0, 0.02);
        }

        /* Badges */
        .badge {
            font-weight: 500;
            letter-spacing: 0.3px;
            padding: 0.5em 0.75em;
        }

        /* Breadcrumb */
        .breadcrumb {
            font-size: 0.875rem;
            margin-bottom: 0;
        }

        .breadcrumb-item a {
            color: var(--primary-color);
            text-decoration: none;
        }

        .breadcrumb-item.active {
            color: var(--secondary-color);
        }

        /* Action Buttons */
        .btn-group .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }

        /* Status and Priority Badges */
        .badge.bg-danger {
            background-color: #dc3545 !important;
        }

        .badge.bg-warning {
            background-color: #ffc107 !important;
            color: #000 !important;
        }

        .badge.bg-info {
            background-color: #17a2b8 !important;
        }

        .badge.bg-success {
            background-color: #28a745 !important;
        }

        /* Empty State */
        .text-center.py-5 {
            color: #6c757d;
        }

        .text-center.py-5 i {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .card h2 {
                font-size: 1.5rem;
            }

            .table-responsive {
                border: none;
            }
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        ::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        /* Additional Utility Classes */
        .fw-medium {
            font-weight: 500;
        }

        .text-truncate-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
    </style>
</head>
<body>
    <!-- Include Navbar -->
    <?php echo $navbar->render(); ?>

    <main class="main-content">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0"><?php echo $pageTitle; ?></h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">My Tasks</li>
                    </ol>
                </nav>
            </div>
        </div>

        <!-- Task Statistics -->
        <div class="row g-4 mb-4">
            <div class="col-sm-6 col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h6 class="card-title">Total Tasks</h6>
                        <h2 class="mb-0"><?php echo $taskStats['total'] ?? 0; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-md-3">
                <div class="card bg-warning text-dark">
                    <div class="card-body">
                        <h6 class="card-title">Open Tasks</h6>
                        <h2 class="mb-0"><?php echo $taskStats['open_tasks'] ?? 0; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h6 class="card-title">In Progress</h6>
                        <h2 class="mb-0"><?php echo $taskStats['in_progress'] ?? 0; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-md-3">
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <h6 class="card-title">Overdue</h6>
                        <h2 class="mb-0"><?php echo $taskStats['overdue'] ?? 0; ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tasks List -->
        <div class="card">
            <div class="card-body">
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <?php if (empty($tasks)): ?>
                    <div class="text-center py-5">
                        <i class='bx bx-task bx-lg text-muted'></i>
                        <p class="text-muted mt-3">No tasks assigned to you.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Project</th>
                                    <th>Contractor</th>
                                    <th>Priority</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tasks as $task): ?>
                                    <?php 
                                    $priorityClass = [
                                        'critical' => 'danger',
                                        'high' => 'warning',
                                        'medium' => 'info',
                                        'low' => 'secondary'
                                    ][$task['priority']] ?? 'secondary';

                                    $statusClass = [
                                        'open' => 'warning',
                                        'in_progress' => 'info',
                                        'closed' => 'success'
                                    ][$task['status']] ?? 'secondary';
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($task['title']); ?></div>
                                            <small class="text-muted">Created: <?php echo date('M d, Y', strtotime($task['created_at'])); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($task['project_name']); ?></td>
                                        <td><?php echo htmlspecialchars($task['contractor_name']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $priorityClass; ?>">
                                                <?php echo ucfirst($task['priority']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            $dueDate = new DateTime($task['due_date']);
                                            $today = new DateTime();
                                            $isOverdue = $dueDate < $today && $task['status'] != 'closed';
                                            ?>
                                            <span class="<?php echo $isOverdue ? 'text-danger fw-bold' : ''; ?>">
                                                <?php echo $dueDate->format('M d, Y'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $statusClass; ?>">
                                                <?php echo ucfirst($task['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="view_defect.php?id=<?php echo $task['id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    View
                                                </a>
                                                <a href="edit_defect.php?id=<?php echo $task['id']; ?>" 
                                                   class="btn btn-sm btn-outline-secondary">
                                                    Edit
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
/**
 * my_tasks.php
 * Display the current user's assigned tasks.
 * Defect links now point to view_defect_mytasks.php and editing functionality is removed.
 * Current Date and Time (UTC): 2025-03-01 11:37:27
 * Current User's Login: irlam
 */

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

define('INCLUDED', true);

require_once 'includes/functions.php';
require_once 'config/database.php';
require_once 'includes/navbar.php';

$pageTitle       = 'My Assigned Tasks';
$currentUser     = $_SESSION['username'];
$currentUserId   = (int)$_SESSION['user_id'];
$currentDateTime = date('Y-m-d H:i:s');

try {
    $database = new Database();
    $db       = $database->getConnection();

    // Initialize variables for tasks and statistics
    $tasks = [];
    $taskStats = [
        'total'       => 0,
        'open_tasks'  => 0,
        'in_progress' => 0,
        'completed'   => 0,
        'overdue'     => 0
    ];

    // Determine user role (editing is not allowed regardless)
    $userRole = $_SESSION['user_type'] ?? 'unknown';
    $userLogo = '';

    if ($userRole === 'contractor') {
        // Fetch contractor logo if the user is a contractor.
        $contractorQuery = "SELECT c.logo 
                            FROM contractors c
                            JOIN users u ON c.id = u.contractor_id
                            WHERE u.username = :username";
        $contractorStmt = $db->prepare($contractorQuery);
        $contractorStmt->bindParam(":username", $currentUser);
        $contractorStmt->execute();
        $contractor = $contractorStmt->fetch(PDO::FETCH_ASSOC);

        if ($contractor && $contractor['logo']) {
            $userLogo = "https://mcgoff.defecttracker.uk/uploads/logos/" . $contractor['logo'];
        }
    } else {
        // For admin/manager, use generic logo (however, editing options will not be provided)
        $userLogo = getBaseUrl() . '/uploads/logos/admin_logo.png';
    }

    /*
      Updated query using defect_assignments:
      Retrieves defects assigned to the current user based on active assignment.
    */
    $query = "SELECT 
                d.id,
                d.title,
                d.description,
                d.status,
                d.priority,
                d.due_date,
                d.created_at,
                p.name AS project_name,
                c.company_name AS contractor_name
              FROM defects d
              LEFT JOIN defect_assignments da ON d.id = da.defect_id
              LEFT JOIN projects p ON d.project_id = p.id
              LEFT JOIN contractors c ON d.assigned_to = c.id
              WHERE da.user_id = :user_id
                AND da.status = 'active'
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

    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $currentUserId, PDO::PARAM_INT);
    $stmt->execute();
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Task statistics using defect_assignments
    $statsQuery = "SELECT 
                    COALESCE(COUNT(*), 0) AS total,
                    COALESCE(SUM(CASE WHEN d.status = 'open' THEN 1 ELSE 0 END), 0) AS open_tasks,
                    COALESCE(SUM(CASE WHEN d.status = 'in_progress' THEN 1 ELSE 0 END), 0) AS in_progress,
                    COALESCE(SUM(CASE WHEN d.status = 'closed' THEN 1 ELSE 0 END), 0) AS completed,
                    COALESCE(SUM(CASE WHEN d.due_date < CURRENT_DATE AND d.status != 'closed' THEN 1 ELSE 0 END), 0) AS overdue
                  FROM defects d
                  LEFT JOIN defect_assignments da ON d.id = da.defect_id
                  WHERE da.user_id = :user_id
                    AND da.status = 'active'";
    $statsStmt = $db->prepare($statsQuery);
    $statsStmt->bindParam(":user_id", $currentUserId, PDO::PARAM_INT);
    $statsStmt->execute();
    $taskStats = $statsStmt->fetch(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    error_log("My Tasks Error: " . $e->getMessage());
}

// Helper function to get the base URL (adjust as necessary)
function getBaseUrl() {
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $subdir = ''; 
    return $protocol . "://" . $host . $subdir;
}

/**
 * computeExpectedResolutionDate()
 * Calculates the expected resolution date using the defect's creation date and its priority.
 * If a due date is provided, that date is used.
 *
 * @param string $createdAt The defect's creation date.
 * @param string $priority  The defect's priority.
 * @param string|null $dueDate Optional user-defined due date.
 * @return DateTime The computed expected resolution date.
 */
function computeExpectedResolutionDate($createdAt, $priority, $dueDate = null) {
    if ($dueDate) {
        return new DateTime($dueDate);
    }
    $expected = new DateTime($createdAt);
    switch (strtolower($priority)) {
        case 'critical':
            $expected->modify('+1 day');
            break;
        case 'high':
            $expected->modify('+2 days');
            break;
        case 'medium':
            $expected->modify('+5 days');
            break;
        case 'low':
        default:
            $expected->modify('+7 days');
            break;
    }
    return $expected;
}

/**
 * isOverdue()
 * Checks whether the current time has passed the expected resolution date
 * (computed from the defect's creation date and priority) and the defect isn't closed.
 *
 * @param string|null $dueDate The user-defined due date, if any.
 * @param string $priority The defect's priority.
 * @param string $createdAt The defect's creation date.
 * @param string $status The current status of the defect.
 * @return bool True if overdue, false otherwise.
 */
function isOverdue($dueDate, $priority, $createdAt, $status) {
    if (strtolower($status) === 'closed') {
        return false; // Closed defects are not overdue.
    }
    $expected = computeExpectedResolutionDate($createdAt, $priority, $dueDate);
    $now = new DateTime();
    return $now > $expected;
}
$navbar = new Navbar($db, $_SESSION['user_id'], $_SESSION['username']);
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
        :root {
            --sidebar-width: 250px;
            --navbar-height: 60px;
            --primary-color: #2c3e50;
            --secondary-color: #34495e;
        }
        body {
            min-height: 100vh;
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .main-content {
            width: 100%;
            max-width: 1200px;
            padding: 2rem;
            background-color: #f8f9fa;
        }
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15);
        }
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
            background-color: rgba(0,0,0,0.02);
        }
        .badge {
            font-weight: 500;
            letter-spacing: 0.3px;
            padding: 0.5em 0.75em;
        }
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
        .btn-group .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
<?php
$navbar->render();
?>	
    <main class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0"><?php echo $pageTitle; ?></h1>
            </div>
        </div>

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
                                    <th>Expected Resolution</th>
                                    <th>Status</th>
                                    <th>Overdue?</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tasks as $task): ?>
                                    <?php 
                                        // Compute the expected resolution date using the helper function.
                                        $expectedDate = computeExpectedResolutionDate($task['created_at'], $task['priority'], $task['due_date']);
                                        // Determine if the task is overdue.
                                        $isTaskOverdue = isOverdue($task['due_date'], $task['priority'], $task['created_at'], $task['status']);
                                        
                                        // Set badge classes based on priority and status.
                                        $priorityClass = [
                                            'critical' => 'danger',
                                            'high'     => 'warning',
                                            'medium'   => 'info',
                                            'low'      => 'secondary'
                                        ][$task['priority']] ?? 'secondary';

                                        $statusClass = [
                                            'open'        => 'warning',
                                            'in_progress' => 'info',
                                            'closed'      => 'success'
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
                                            <?php echo $expectedDate->format('M d, Y'); ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $statusClass; ?>">
                                                <?php echo ucfirst($task['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($isTaskOverdue): ?>
                                                <span class="badge bg-danger">Overdue</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">On Time</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <!-- Link defect view to view_defect_mytasks.php -->
                                                <a href="view_defect_mytasks.php?id=<?php echo $task['id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    View
                                                </a>
                                                <!-- No edit button provided -->
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($tasks)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center">No tasks assigned to you.</td>
                                    </tr>
                                <?php endif; ?>
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
<?php
// my_tasks_readme.php
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/navbar.php';

// Check if the user is logged in
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Create a database connection
$database = new Database();
$db = $database->getConnection();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Assigned Tasks - Construction Defect Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>
        body {
            padding-top: 76px;
        }
        .main-content {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        .card {
            box-shadow: 0 2px 4px rgba(0,0,0,.05);
            border: none;
            margin-bottom: 1rem;
        }
        .card-header {
            background-color: var(--bs-secondary-bg);
            border-bottom: 1px solid var(--bs-border-color);
            padding: 1rem;
        }
        .card-body {
            padding: 1.25rem;
        }
        .breadcrumb {
            font-size: 0.875rem;
        }
        .table {
            font-size: 0.875rem;
        }
    </style>
</head>
<body class="tool-body" data-bs-theme="dark">

    <?php
    $navbar = new Navbar($db, $_SESSION['user_id'], $_SESSION['username']);
    $navbar->render();
    ?>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">My Assigned Tasks - Construction Defect Tracker</h1>
            <a href="../help_index.php" class="btn btn-outline-secondary">
                <i class='bx bx-arrow-back'></i> Back to Help
            </a>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Instructions</h5>
            </div>
            <div class="card-body">
                <p>
                    The "My Assigned Tasks" page displays all defects currently assigned to the logged in user. This page is a central hub for users to view details about their assigned tasks, including key metrics and individual defect information.
                </p>

                <h5>Page Access</h5>
                <ul>
                    <li><strong>Authentication:</strong> Only logged in users can access this page. Unauthenticated users are redirected to the login page.</li>
                    <li><strong>User Role:</strong> The data displayed adapts based on the user's role. Contractors see their own tasks along with their respective logos, while administrators or managers see a generic admin logo.</li>
                </ul>

                <h5>Functionality Overview</h5>
                <ul>
                    <li><strong>Task List Display:</strong> A table lists the defects assigned to the current user. Each row shows:
                        <ul>
                            <li>Defect Title (with creation date)</li>
                            <li>Project Name and Contractor Name</li>
                            <li>Priority and Expected Resolution Date</li>
                            <li>Current Status (open, in progress, closed)</li>
                            <li>An overdue indicator displaying whether the defect is overdue or on time</li>
                        </ul>
                    </li>
                    <li><strong>Task Statistics:</strong> Summary cards at the top of the page display key statistics—
                        total tasks, open tasks, tasks in progress, and overdue tasks.</li>
                    <li><strong>Defect View:</strong> Clicking the "View" button in a task row leads to a detailed view (via the <code>view_defect_mytasks.php</code> page) for that specific defect.</li>
                </ul>

                <h5>Task Computation and Overdue Checks</h5>
                <ul>
                    <li>
                        The expected resolution date is computed based on the defect’s creation date and its priority. If a custom due date is provided, that date is used instead.
                    </li>
                    <li>
                        The function <code>isOverdue()</code> compares the current date to the expected resolution date (unless the defect is closed) to flag overdue tasks.
                    </li>
                </ul>

                <h5>Error Handling and Logging</h5>
                <ul>
                    <li>
                        Any errors that occur during data retrieval (e.g., fetching task lists or statistics) are logged in the <code>logs/error.log</code> file.
                    </li>
                    <li>
                        The page displays error messages if exceptions occur during the execution of the PHP code.
                    </li>
                </ul>

                <h5>Navigation and User Interaction</h5>
                <ul>
                    <li>
                        Breadcrumb navigation at the top provides a link back to the dashboard.
                    </li>
                    <li>
                        Users can quickly view task statistics via the summary cards and use the "View" button to inspect details for any defect in which they are involved.
                    </li>
                </ul>

                <h5>Security Considerations</h5>
                <ul>
                    <li>
                        Session validation ensures only authenticated users can access the page.
                    </li>
                    <li>
                        Prepared statements and data sanitization are used extensively to prevent SQL injection and cross-site scripting (XSS) attacks.
                    </li>
                </ul>

                <h5>Additional Notes</h5>
                <p>
                    The editing functionality has been removed from this page. Users can only view their tasks and associated details. This design choice helps to maintain data integrity and simplifies task management.
                </p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
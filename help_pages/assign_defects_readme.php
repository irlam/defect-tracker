<?php
// assign_to_user_readme.php
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
    <title>Assign Defects to a User - Construction Defect Tracker</title>
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
        .btn {
            margin-right: 0.5rem;
        }
    </style>
</head>
<body class="tool-body" data-bs-theme="dark">

    <?php
    $navbar = new Navbar($db, $_SESSION['user_id'], $_SESSION['username']);
    $navbar->render();
    ?>

    <div class="main-content">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">Assign Defects to a User - Construction Defect Tracker</h1>
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
                    This page is designed to assign defects to a specific contractor user in the Construction Defect Tracker system. 
                    It allows administrators or authorized users to reassign defect issues to the appropriate contractor.
                </p>
                
                <h5>Page Access</h5>
                <ul>
                    <li><strong>Authentication:</strong> Users must be logged in to access this page. If a user is not logged in, they will be redirected to the login page.</li>
                    <li><strong>User Role:</strong> Typically, only certain roles (e.g., administrators or users with proper permissions) can assign defects.</li>
                </ul>

                <h5>Functionality Overview</h5>
                <ul>
                    <li><strong>Listing Defects:</strong> All defects, along with related details such as project name, contractor, priority, due date, and current status, are listed in a table.</li>
                    <li><strong>Assignment Form:</strong> Each defect row includes a form where you can select an active contractor user from a dropdown list available on the system.</li>
                    <li><strong>Assignment Process:</strong> Upon submitting the form:
                        <ul>
                            <li>The system will remove any previous assignment for the defect.</li>
                            <li>A new assignment is inserted, storing the defect ID, the new user ID, the assigning user’s ID, and a timestamp.</li>
                            <li>An activity log entry is created to track the assignment details.</li>
                        </ul>
                    </li>
                    <li><strong>Defect Details:</strong> Users can click the "View Defect" button to toggle a form that shows additional defect details including title, project, contractor, priority, due date, status, and any attached images.</li>
                </ul>

                <h5>Error Handling</h5>
                <ul>
                    <li>If an error occurs during the assignment process (for example during database transactions), the page will roll back the transaction and display an error message.</li>
                    <li>Errors are logged in a dedicated error log file located at <code>logs/error.log</code> to help with debugging.</li>
                </ul>

                <h5>Navigation and User Interaction</h5>
                <ul>
                    <li>To assign a defect, select a contractor from the dropdown and click the "Assign" button.</li>
                    <li>The "View Defect" button expands a hidden section that displays further details of the defect, including images which can be zoomed by clicking on the thumbnails.</li>
                    <li>After a successful operation, a success message is displayed, indicating the defect was successfully reassigned.</li>
                </ul>

                <h5>Security Measures</h5>
                <ul>
                    <li><strong>Input Sanitization:</strong> All output and input fields are passed through proper sanitization functions to prevent XSS and other injection vulnerabilities.</li>
                    <li><strong>Session Management:</strong> Only users with an active session can access the page.</li>
                </ul>

                <h5>Notes</h5>
                <p>
                    The defect assignment functionality uses prepared statements and transactions to reduce potential database errors. 
                    Ensure that any changes or modifications made to the defect assignment process maintain security best practices.
                </p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
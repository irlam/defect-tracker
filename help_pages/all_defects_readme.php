<?php
// all_defects_readme.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Defects - Construction Defect Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <style>
        .main-content {
            padding: 20px;
            margin-left: 250px;
            transition: margin-left 0.3s;
        }
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
        }
        .card {
            box-shadow: 0 2px 4px rgba(0,0,0,.05);
            border: none;
            margin-bottom: 1rem;
        }

        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid rgba(0,0,0,.05);
            padding: 1rem;
        }

        .card-body {
            padding: 1.25rem;
        }
    </style>
</head>
<body>

    <!-- Main Content -->
    <div class="main-content">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">All Defects - Construction Defect Tracker</h1>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Instructions</h5>
            </div>
            <div class="card-body">
                <p>This webpage allows the user to view all defects in the Construction Defect Tracker system. Users must be logged in to access this page. The page displays defects for a selected project and floor plan, and allows users to view detailed information about each defect.</p>

                <h5>Accessing the Page</h5>
                <ul>
                    <li><strong>Login</strong>: Ensure you are logged in. If not, you will be redirected to the login page.</li>
                    <li><strong>Navigate</strong>: Once logged in, navigate to the <code>all_defects.php</code> page to view all defects.</li>
                </ul>

                <h5>Selecting a Project and Floor Plan</h5>
                <ul>
                    <li><strong>Select Project</strong>: Use the dropdown menu to select a project. The floor plans for the selected project will be displayed.</li>
                    <li><strong>Select Floor Plan</strong>: Click on a floor plan to view the defects associated with it. The floor plan image and defect pins will be displayed.</li>
                </ul>

                <h5>Viewing Defects</h5>
                <ul>
                    <li><strong>Defect Pins</strong>: Defect pins are displayed on the floor plan image. Hover over a pin to view a tooltip with basic defect information.</li>
                    <li><strong>Defect Table</strong>: A table of defects is displayed below the floor plan image. Click on a defect in the table to highlight it on the floor plan.</li>
                    <li><strong>Defect Details</strong>: Click on a defect pin or table row to view detailed information about the defect.</li>
                </ul>

                <h5>Navigation</h5>
                <p>Use the navigation bar to access other sections of the system. Click the <strong>Back to Projects</strong> button to return to the project selection page.</p>

                <h5>Additional Features</h5>
                <ul>
                    <li><strong>Navbar</strong>: The page includes a navigation bar for easy access to other sections of the system.</li>
                    <li><strong>Form Styling</strong>: The page is styled with Bootstrap for a consistent and responsive design.</li>
                    <li><strong>Error Logging</strong>: Errors are logged to a file for debugging purposes.</li>
                </ul>

                <h5>Notes</h5>
                <p>Ensure that all required fields are filled out correctly before submitting any forms. Use the provided validation messages to correct any errors before submission. Contact your system administrator if you encounter any issues while using this page.</p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/main.js"></script>
</body>
</html>
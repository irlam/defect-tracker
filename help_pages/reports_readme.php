<?php
// reports_readme.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports Dashboard - Construction Defect Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <style>
        .main-content {
            padding: 20px;
            margin-left: 250px;
            transition: margin-left 0.3s;
            background-color: #f8f9fa;
            min-height: 100vh;
        }
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
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
            <h1 class="h2">Reports Dashboard - Construction Defect Tracker</h1>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Instructions</h5>
            </div>
            <div class="card-body">
                <p>This webpage allows the user to view and analyze reports in the Construction Defect Tracker system. Users must be logged in to access this page. The page displays various statistics related to defects and contractors, and provides tools for date filtering and exporting data.</p>

                <h5>Accessing the Page</h5>
                <ul>
                    <li><strong>Login</strong>: Ensure you are logged in. If not, you will be redirected to the login page.</li>
                    <li><strong>Navigate</strong>: Once logged in, navigate to the <code>reports.php</code> page to view the reports dashboard.</li>
                </ul>

                <h5>Using the Date Filter</h5>
                <ul>
                    <li><strong>Select Date Range</strong>: Use the date inputs to select a start and end date for the report. Click the "Apply Filter" button to update the report with the selected date range.</li>
                </ul>

                <h5>Viewing Statistics</h5>
                <ul>
                    <li><strong>Statistics Cards</strong>: The top section of the page displays cards with key statistics such as total defects, open defects, pending defects, overdue defects, rejected defects, closed defects, and active contractors.</li>
                    <li><strong>Defect Trend Analysis</strong>: A chart displaying the trend of defect counts over the selected date range.</li>
                    <li><strong>Contractor Performance</strong>: A table displaying performance metrics for contractors, including total defects, open defects, pending defects, overdue defects, rejected defects, closed defects, and more.</li>
                    <li><strong>User Performance</strong>: A table and chart displaying the performance of users in reporting defects.</li>
                </ul>

                <h5>Exporting Data</h5>
                <ul>
                    <li><strong>CSV Export</strong>: Use the "Export to CSV" button to download the contractor performance data as a CSV file.</li>
                    <li><strong>PDF Export</strong>: Use the "Export to PDF" button to download the entire report as a PDF file.</li>
                    <li><strong>Print Report</strong>: Use the "Print Report" button to print the report.</li>
                </ul>

                <h5>Navigation</h5>
                <p>Use the navigation bar to access other sections of the system. Click the "Dashboard" link in the breadcrumb to return to the main dashboard.</p>

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
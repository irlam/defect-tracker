<?php
// help_index.php
// This file creates a help section that links to various help pages in the help_pages folder.

session_start();

require_once 'config/database.php';
require_once 'includes/navbar.php';

// Check if the user is logged in
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Create a database connection
$database = new Database();
$db = $database->getConnection();

$helpPages = [
    'Add Contractor'   => [
        'url' => 'help_pages/add_contractor_readme.php',
        'icon' => 'bi-person-plus-fill',
        'description' => 'Learn how to add new contractors to the system'
    ],
    'All Defects'      => [
        'url' => 'help_pages/all_defects_readme.php',
        'icon' => 'bi-exclamation-triangle-fill',
        'description' => 'View and manage all reported defects'
    ],
    'Assign Defects'   => [
        'url' => 'help_pages/assign_defects_readme.php',
        'icon' => 'bi-clipboard-check',
        'description' => 'Learn how to assign defects to contractors'
    ],
    'My Tasks'         => [
        'url' => 'help_pages/my_tasks_readme.php',
        'icon' => 'bi-list-task',
        'description' => 'View and manage your assigned tasks'
    ],
    'Reports'          => [
        'url' => 'help_pages/reports_readme.php',
        'icon' => 'bi-graph-up',
        'description' => 'Generate and view various system reports'
    ],
    'Backup guide'     => [
        'url' => 'help_pages/backupguide.html',
        'icon' => 'bi-cloud-arrow-up-fill',
        'description' => 'Learn how to backup your data'
    ],
    'Upload to Google Drive' => [
        'url' => 'help_pages/Guide_Upload_to_Google_Drive.html',
        'icon' => 'bi-google',
        'description' => 'Guide for uploading files to Google Drive'
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help Section</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            padding-top: 76px; /* Ensure content is not hidden behind the navbar */
        }
        .help-section {
            padding: 3rem 1rem;
        }
        .help-header {
            margin-bottom: 2.5rem;
        }
        .help-title {
            color: #212529;
            position: relative;
            display: inline-block;
            margin-bottom: 1rem;
        }
        .help-title:after {
            content: '';
            position: absolute;
            left: 0;
            bottom: -10px;
            width: 50px;
            height: 3px;
            background-color: #0d6efd;
        }
        .help-description {
            color: #6c757d;
            max-width: 700px;
        }
        .help-card {
            height: 100%;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: none;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .help-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .card-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: #0d6efd;
        }
        .card-title {
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }
        .card-text {
            font-size: 0.9rem;
            color: #6c757d;
        }
        .card-link {
            text-decoration: none;
            color: inherit;
        }
        .card-link:hover {
            color: inherit;
        }
        .card-footer {
            background-color: #fff;
            border-top: 1px solid rgba(0,0,0,0.05);
            font-size: 0.85rem;
        }
        .card-footer i {
            transition: transform 0.3s ease;
        }
        .help-card:hover .card-footer i {
            transform: translateX(5px);
        }
        @media (max-width: 576px) {
            .help-section {
                padding: 2rem 0.5rem;
            }
            .help-title {
                font-size: 1.75rem;
            }
            .card-icon {
                font-size: 1.75rem;
            }
        }
    </style>
</head>
<body class="tool-body" data-bs-theme="dark">
    <?php
    $navbar = new Navbar($db, $_SESSION['user_id'], $_SESSION['username']);
    $navbar->render();
    ?>
    
    <div class="container help-section">
        <div class="help-header text-center text-md-start">
            <h1 class="help-title">Help Center</h1>
            <p class="help-description">Welcome to our help center. Here you'll find guides and documentation to help you navigate through the system and perform various tasks efficiently.</p>
        </div>
        
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            <?php foreach ($helpPages as $title => $details): ?>
                <div class="col">
                    <a href="<?php echo htmlspecialchars($details['url']); ?>" class="card-link">
                        <div class="help-card card h-100">
                            <div class="card-body text-center">
                                <i class="bi <?php echo htmlspecialchars($details['icon']); ?> card-icon"></i>
                                <h5 class="card-title"><?php echo htmlspecialchars($title); ?></h5>
                                <p class="card-text"><?php echo htmlspecialchars($details['description']); ?></p>
                            </div>
                            <div class="card-footer text-center text-muted">
                                View Guide <i class="bi bi-arrow-right ms-2"></i>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
// project_details.php

// Error reporting and logging setup
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');


session_start();
require_once 'config/database.php';
date_default_timezone_set('UTC');

// Set specific values as requested
$currentUser = 'irlam';
$currentDateTime = '2025-01-14 13:54:12';

// Check authentication
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Get project ID from URL
$projectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$projectId) {
    header("Location: projects.php");
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Fetch project details
    $sql = "SELECT * FROM projects WHERE id = :id";
    $stmt = $db->prepare($sql);
    $stmt->execute([':id' => $projectId]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$project) {
        throw new Exception("Project not found");
    }

} catch (Exception $e) {
    error_log("Project Details Error: " . $e->getMessage());
    $error_message = "An error occurred while loading the project details.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Details - Construction Defect Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
    <style>
        .status-badge {
            font-size: 1rem;
            padding: 0.5rem 1rem;
        }
        .detail-card {
            background-color: #f8f9fa;
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Include your sidebar here -->
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger mt-3"><?php echo htmlspecialchars($error_message); ?></div>
                <?php else: ?>
                    <!-- Project Header -->
                    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                        <div>
                            <h1><?php echo htmlspecialchars($project['name']); ?></h1>
                            <span class="badge bg-<?php echo getStatusColor($project['status']); ?> status-badge">
                                <?php echo ucfirst(htmlspecialchars($project['status'])); ?>
                            </span>
                        </div>
                        <div class="text-muted">
                            <small>UTC: <?php echo $currentDateTime; ?></small><br>
                            <small>User: <?php echo htmlspecialchars($currentUser); ?></small>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="mb-4">
                        <a href="projects.php" class="btn btn-secondary">
                            <i class='bx bx-arrow-back'></i> Back to Projects
                        </a>
                    </div>

                    <!-- Project Details -->
                    <div class="row">
                        <div class="col-md-8">
                            <div class="detail-card">
                                <h3>Project Information</h3>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <strong>Start Date:</strong>
                                        <p><?php echo htmlspecialchars($project['start_date']); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>End Date:</strong>
                                        <p><?php echo $project['end_date'] ? htmlspecialchars($project['end_date']) : 'Not set'; ?></p>
                                    </div>
                                    <div class="col-12">
                                        <strong>Description:</strong>
                                        <p><?php echo nl2br(htmlspecialchars($project['description'])); ?></p>
                                    </div>
                                    <div class="col-12">
                                        <strong>Notes:</strong>
                                        <p><?php echo nl2br(htmlspecialchars($project['notes'] ?? 'No notes available')); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Project Metadata -->
                        <div class="col-md-4">
                            <div class="detail-card">
                                <h3>Project Details</h3>
                                <div class="list-group">
                                    <div class="list-group-item">
                                        <strong>Status:</strong>
                                        <span class="badge bg-<?php echo getStatusColor($project['status']); ?>">
                                            <?php echo ucfirst($project['status']); ?>
                                        </span>
                                    </div>
                                    <div class="list-group-item">
                                        <strong>Created By:</strong>
                                        <span><?php echo htmlspecialchars($project['created_by']); ?></span>
                                    </div>
                                    <div class="list-group-item">
                                        <strong>Last Updated By:</strong>
                                        <span><?php echo htmlspecialchars($project['updated_by']); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
// Helper function to get the appropriate Bootstrap color class for status
function getStatusColor($status) {
    $colors = [
        'active' => 'success',
        'completed' => 'info',
        'on_hold' => 'warning'
    ];
    return $colors[$status] ?? 'secondary';
}
?>
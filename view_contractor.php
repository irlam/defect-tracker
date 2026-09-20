<?php
// view_contractor.php
// Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): 2025-01-17 13:07:55
// Current User's Login: irlam

// Error reporting and logging setup
error_reporting(E_ALL);
ini_set('display_errors', 0);
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

require_once 'includes/functions.php';
require_once 'config/database.php';
require_once 'includes/navbar.php';

$pageTitle = 'View Contractor';
$currentUser = $_SESSION['username'];
$navbar = null;
$contractor = ['company_name' => 'Contractor'];
$recentDefects = [];

// Validate contractor ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: contractors.php");
    exit();
}

$contractorId = (int)$_GET['id'];

try {
    $database = new Database();
    $db = $database->getConnection();
    if (!$db) {
        throw new RuntimeException('Database connection unavailable');
    }

    $navbar = new Navbar($db, (int) ($_SESSION['user_id'] ?? 0), $currentUser);

    // Get contractor details with statistics
    $query = "SELECT 
                c.*,
                COUNT(DISTINCT d.id) as total_defects,
                SUM(CASE WHEN d.status = 'open' THEN 1 ELSE 0 END) as open_defects,
                SUM(CASE WHEN d.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_defects,
                SUM(CASE WHEN d.status = 'closed' THEN 1 ELSE 0 END) as closed_defects
              FROM contractors c
              LEFT JOIN defects d ON c.id = d.contractor_id
              WHERE c.id = :id
              GROUP BY c.id";

    $stmt = $db->prepare($query);
    $stmt->bindParam(":id", $contractorId);
    $stmt->execute();
    
    $contractor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$contractor) {
        header("Location: contractors.php");
        exit();
    }

    // Get recent defects for this contractor
    $defectsQuery = "SELECT 
                        d.*,
                        p.name as project_name
                     FROM defects d
                     LEFT JOIN projects p ON d.project_id = p.id
                     WHERE d.contractor_id = :contractor_id
                     ORDER BY d.created_at DESC
                     LIMIT 5";

    $defectsStmt = $db->prepare($defectsQuery);
    $defectsStmt->bindParam(":contractor_id", $contractorId);
    $defectsStmt->execute();
    $recentDefects = $defectsStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    error_log("View Contractor Error: " . $e->getMessage());
    $error_message = "An error occurred while loading contractor details: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo htmlspecialchars($contractor['company_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="/css/app.css" rel="stylesheet">
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
        }

        .main-content {
            margin: 0 auto;
            max-width: 1440px;
            padding: 2rem;
            min-height: 100vh;
        }

        .stats-card {
            transition: transform 0.2s ease-in-out;
        }

        .stats-card:hover {
            transform: translateY(-5px);
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
        }
    </style>
</head>
<body class="tool-body" data-bs-theme="dark">
    <?php if ($navbar instanceof Navbar) { $navbar->render(); } ?>
    <div class="app-content-offset"></div>

    <main class="main-content">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0"><?php echo htmlspecialchars($contractor['company_name']); ?></h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="contractors.php">Contractors</a></li>
                        <li class="breadcrumb-item active" aria-current="page">View Contractor</li>
                    </ol>
                </nav>
            </div>
            <?php if (($_SESSION['user_type'] ?? '') === 'admin'): ?>
            <div>
                <a href="contractors.php" class="btn btn-primary">
                    <i class='bx bx-edit'></i> Manage Contractors
                </a>
            </div>
            <?php endif; ?>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Contractor Details -->
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Contact Information</h5>
                        <hr>
                        <p class="mb-1">
                            <i class='bx bx-user'></i>
                            <strong>Contact:</strong> <?php echo htmlspecialchars($contractor['contact_name']); ?>
                        </p>
                        <p class="mb-1">
                            <i class='bx bx-envelope'></i>
                            <strong>Email:</strong> 
                            <a href="mailto:<?php echo htmlspecialchars($contractor['email']); ?>">
                                <?php echo htmlspecialchars($contractor['email']); ?>
                            </a>
                        </p>
                        <p class="mb-1">
                            <i class='bx bx-phone'></i>
                            <strong>Phone:</strong>
                            <a href="tel:<?php echo htmlspecialchars($contractor['phone']); ?>">
                                <?php echo htmlspecialchars($contractor['phone']); ?>
                            </a>
                        </p>
                        <p class="mb-1">
                            <i class='bx bx-calendar'></i>
                            <strong>Added:</strong> 
                            <?php echo date('M d, Y', strtotime($contractor['created_at'])); ?>
                        </p>
                        <p class="mb-0">
                            <i class='bx bx-check-circle'></i>
                            <strong>Status:</strong>
                            <span class="badge bg-<?php echo $contractor['status'] === 'active' ? 'success' : 'warning'; ?>">
                                <?php echo ucfirst($contractor['status']); ?>
                            </span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Statistics -->
            <div class="col-md-8">
                <div class="row">
                    <div class="col-sm-6 col-lg-3 mb-4">
                        <div class="card stats-card bg-primary text-white">
                            <div class="card-body">
                                <h6 class="card-title">Total Defects</h6>
                                <h2 class="mb-0"><?php echo $contractor['total_defects']; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3 mb-4">
                        <div class="card stats-card bg-warning">
                            <div class="card-body">
                                <h6 class="card-title">Open</h6>
                                <h2 class="mb-0"><?php echo $contractor['open_defects']; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3 mb-4">
                        <div class="card stats-card bg-info text-white">
                            <div class="card-body">
                                <h6 class="card-title">In Progress</h6>
                                <h2 class="mb-0"><?php echo $contractor['in_progress_defects']; ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3 mb-4">
                        <div class="card stats-card bg-success text-white">
                            <div class="card-body">
                                <h6 class="card-title">Closed</h6>
                                <h2 class="mb-0"><?php echo $contractor['closed_defects']; ?></h2>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Defects -->
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title mb-0">Recent Defects</h5>
                            <a href="defects.php?contractor_id=<?php echo $contractorId; ?>" class="btn btn-sm btn-outline-primary">
                                View All
                            </a>
                        </div>
                        <?php if (empty($recentDefects)): ?>
                            <p class="text-muted mb-0">No defects found for this contractor.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Project</th>
                                            <th>Title</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentDefects as $defect): ?>
                                            <tr>
                                                <td><?php echo $defect['id']; ?></td>
                                                <td><?php echo htmlspecialchars($defect['project_name']); ?></td>
                                                <td>
                                                    <a href="view_defect.php?id=<?php echo $defect['id']; ?>">
                                                        <?php echo htmlspecialchars($defect['title']); ?>
                                                    </a>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $defect['status'] === 'open' ? 'warning' : 
                                                            ($defect['status'] === 'in_progress' ? 'info' : 'success'); 
                                                    ?>">
                                                        <?php echo ucfirst($defect['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($defect['created_at'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

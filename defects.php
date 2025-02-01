<?php
// defects.php
// Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): 2025-01-17 14:10:27
// Current User's Login: irlam
// Error reporting and logging setup
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication check
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Define INCLUDED constant for navbar security
define('INCLUDED', true);

require_once 'includes/functions.php';
require_once 'config/database.php';
require_once 'includes/navbar.php'; // Include the navbar file

$pageTitle = 'Defects';
$currentUser = $_SESSION['username'];
$currentUserId = (int)$_SESSION['user_id']; // Retrieve the user ID from the session

try {
    $database = new Database();
    $db = $database->getConnection();

    // Initialize the Navbar class
    $navbar = new Navbar($db, $currentUserId);

    // Build the query based on filters
    $query = "SELECT 
                d.*,
                c.company_name as contractor_name
              FROM defects d
              LEFT JOIN contractors c ON d.contractor_id = c.id";

    // Apply filters if they exist
    $whereConditions = [];
    $params = [];

    if (isset($_GET['project_id']) && !empty($_GET['project_id'])) {
        $whereConditions[] = "d.project_id = :project_id";
        $params[':project_id'] = $_GET['project_id'];
    }

    if (isset($_GET['contractor_id']) && !empty($_GET['contractor_id'])) {
        $whereConditions[] = "d.contractor_id = :contractor_id";
        $params[':contractor_id'] = $_GET['contractor_id'];
    }

    if (isset($_GET['status']) && !empty($_GET['status'])) {
        $whereConditions[] = "d.status = :status";
        $params[':status'] = $_GET['status'];
    }

    // Add where clause if conditions exist
    if (!empty($whereConditions)) {
        $query .= " WHERE " . implode(" AND ", $whereConditions);
    }

    $query .= " ORDER BY d.created_at DESC";

    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $defects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get projects for filter
    $projectsQuery = "SELECT id, name FROM projects ORDER BY name";
    $projectsStmt = $db->query($projectsQuery);
    $projects = $projectsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get contractors for filter
    $contractorsQuery = "SELECT id, company_name FROM contractors WHERE status = 'active' ORDER BY company_name";
    $contractorsStmt = $db->query($contractorsQuery);
    $contractors = $contractorsStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Defects Error: " . $e->getMessage());
    $error_message = "An error occurred while loading defects: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Defect Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <style>
        /* Additional styles specific to defects page */
        .filters-card {
            background: #fff;
            border-radius: 0.5rem;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }

        .main-content {
            margin-left: 250px;
            padding: 1.5rem;
            min-height: 100vh;
            background-color: #f8f9fa;
        }

        .defect-status {
            width: 100px;
        }

        .card {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: none;
        }

        @media (max-width: 991.98px) {
            .main-content {
                margin-left: 0;
                padding-top: 60px;
            }
        }

        .table-responsive {
            margin-bottom: 0;
        }

        .breadcrumb {
            margin-bottom: 0;
        }

        .btn-sm {
            padding: .25rem .5rem;
        }
    </style>
</head>
<body>
    <!-- Include Navbar -->
    <?php echo $navbar->render(); ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0"><?php echo $pageTitle; ?></h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Defects</li>
                    </ol>
                </nav>
            </div>
            <div>
                <a href="create_defect.php" class="btn btn-primary">
                    <i class='bx bx-plus'></i> Add Defect
                </a>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-card p-3 mb-4">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Project</label>
                    <select name="project_id" class="form-select">
                        <option value="">All Projects</option>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?php echo $project['id']; ?>"
                                <?php echo (isset($_GET['project_id']) && $_GET['project_id'] == $project['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($project['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Contractor</label>
                    <select name="contractor_id" class="form-select">
                        <option value="">All Contractors</option>
                        <?php foreach ($contractors as $contractor): ?>
                            <option value="<?php echo $contractor['id']; ?>"
                                <?php echo (isset($_GET['contractor_id']) && $_GET['contractor_id'] == $contractor['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($contractor['company_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="open" <?php echo (isset($_GET['status']) && $_GET['status'] == 'open') ? 'selected' : ''; ?>>Open</option>
                        <option value="in_progress" <?php echo (isset($_GET['status']) && $_GET['status'] == 'in_progress') ? 'selected' : ''; ?>>In Progress</option>
                        <option value="closed" <?php echo (isset($_GET['status']) && $_GET['status'] == 'closed') ? 'selected' : ''; ?>>Closed</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class='bx bx-filter'></i> Filter
                    </button>
                    <a href="defects.php" class="btn btn-outline-secondary">
                        <i class='bx bx-reset'></i> Reset
                    </a>
                </div>
            </form>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <!-- Defects List -->
        <?php if (empty($defects)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class='bx bx-bug bx-lg text-muted mb-3'></i>
                    <p class="text-muted">No defects found.</p>
                    <a href="create_defect.php" class="btn btn-primary">
                        <i class='bx bx-plus'></i> Add Defect
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Contractor</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($defects as $defect): ?>
                                <tr>
                                    <td>#<?php echo $defect['id']; ?></td>
                                    <td><?php echo htmlspecialchars($defect['title']); ?></td>
                                    <td><?php echo htmlspecialchars($defect['contractor_name']); ?></td>
                                    <td>
                                        <?php
                                            $statusClass = [
                                                'open' => 'warning',
                                                'in_progress' => 'info',
                                                'closed' => 'success'
                                            ][$defect['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $statusClass; ?> defect-status">
                                            <?php echo ucfirst(str_replace('_', ' ', $defect['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($defect['created_at'])); ?></td>
                                    <td>
                                        <a href="view_defect.php?id=<?php echo $defect['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- JavaScript includes -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/main.js"></script>
</body>
</html>
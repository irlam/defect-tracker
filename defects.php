<?php
// defects.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Define INCLUDED constant for navbar security
define('INCLUDED', true);

require_once 'includes/functions.php';
require_once 'config/database.php';
require_once 'config/constants.php';
require_once 'includes/navbar.php';

// Authentication check
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "login.php");
    exit();
}

$pageTitle = 'Defects';
$currentUserId = (int)$_SESSION['user_id'];

// Function to check user role permissions
function checkUserPermissions($db, $userId) {
    $query = "SELECT r.name as role_name
              FROM users u
              JOIN user_roles ur ON u.id = ur.user_id
              JOIN roles r ON ur.role_id = r.id
              WHERE u.id = :user_id";
    
    $stmt = $db->prepare($query);
    $stmt->execute([':user_id' => $userId]);
    $roles = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    return [
        'canEdit' => in_array('admin', $roles) || in_array('manager', $roles),
        'canDelete' => in_array('admin', $roles),
        'roles' => $roles
    ];
}

// Function to check if a due date is overdue
function isDueDateOverdue($dueDate) {
    if (empty($dueDate)) {
        return false;
    }
    
    $currentDate = new DateTime();
    $dueDateObj = new DateTime($dueDate);
    
    return $currentDate > $dueDateObj;
}
try {
    $database = new Database();
    $db = $database->getConnection();

    // Get user permissions
    $permissions = checkUserPermissions($db, $currentUserId);
    $canEdit = $permissions['canEdit'];
    $canDelete = $permissions['canDelete'];
    $userRoles = $permissions['roles'];

    // Initialize the Navbar class
    $navbar = new Navbar($db, $currentUserId, $_SESSION['username']);
    
    // Handle filtering
    $statusFilter = $_GET['status'] ?? 'all';
    $priorityFilter = $_GET['priority'] ?? 'all';
    $contractorFilter = $_GET['contractor'] ?? 'all';
    $projectFilter = $_GET['project'] ?? 'all';
    $dateAddedFilter = $_GET['date_added'] ?? '';
    $searchTerm = $_GET['search'] ?? '';
    
    // Handle pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $perPage = 10;
    $offset = ($page - 1) * $perPage;

    // Base query with correct column names
    $query = "SELECT 
                d.*,
                c.company_name as contractor_name,
                c.logo as contractor_logo,
                p.name as project_name,
                u.username as created_by_user,
                CONCAT(u.first_name, ' ', u.last_name) as created_by_full_name,
                GROUP_CONCAT(DISTINCT di.file_path) as image_paths,
                GROUP_CONCAT(DISTINCT di.pin_path) as pin_paths,
                (SELECT COUNT(*) FROM defect_comments dc WHERE dc.defect_id = d.id) as comment_count,
                rej_user.username as rejected_by_user,
                reo_user.username as reopened_by_user,
                d.rejection_comment,
                d.rejection_status,
                d.reopened_at,
                d.assigned_to,
                d.reported_by,
                d.acceptance_comment,
                d.accepted_at,
                acc_user.username as accepted_by_user
              FROM defects d
              LEFT JOIN contractors c ON d.assigned_to = c.id
              LEFT JOIN projects p ON d.project_id = p.id
              LEFT JOIN users u ON d.created_by = u.id
              LEFT JOIN defect_images di ON d.id = di.defect_id
              LEFT JOIN users rej_user ON d.rejected_by = rej_user.id
              LEFT JOIN users reo_user ON d.reopened_by = reo_user.id
              LEFT JOIN users acc_user ON d.accepted_by = acc_user.id
              WHERE d.deleted_at IS NULL";

    $params = [];

    // Add filters to query
    if ($statusFilter !== 'all') {
        $query .= " AND d.status = :status";
        $params[':status'] = $statusFilter;
    }

    if ($priorityFilter !== 'all') {
        if ($priorityFilter === 'overdue') {
            // Handle overdue filter - check for defects with due_date before current date
            $query .= " AND d.due_date IS NOT NULL AND d.due_date < CURRENT_DATE()";
        } else {
            // Regular priority filter
            $query .= " AND d.priority = :priority";
            $params[':priority'] = $priorityFilter;
        }
    }

    if ($contractorFilter !== 'all') {
        $query .= " AND d.assigned_to = :contractor_id";
        $params[':contractor_id'] = $contractorFilter;
    }

    if ($projectFilter !== 'all') {
        $query .= " AND d.project_id = :project_id";
        $params[':project_id'] = $projectFilter;
    }

    if (!empty($dateAddedFilter)) {
        $query .= " AND DATE(d.created_at) = :date_added";
        $params[':date_added'] = $dateAddedFilter;
    }

    if (!empty($searchTerm)) {
        $query .= " AND (d.title LIKE :search OR d.description LIKE :search OR c.company_name LIKE :search)";
        $params[':search'] = "%$searchTerm%";
    }

    // Add group by and order by
    $query .= " GROUP BY d.id ORDER BY d.updated_at DESC LIMIT {$perPage} OFFSET {$offset}";

    // Prepare and execute query
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $defects = $stmt->fetchAll(PDO::FETCH_ASSOC) ?? [];
	    // Count total records for pagination
    $countQuery = "SELECT COUNT(DISTINCT d.id) as total FROM defects d 
                   LEFT JOIN contractors c ON d.assigned_to = c.id
                   LEFT JOIN projects p ON d.project_id = p.id
                   LEFT JOIN users u ON d.created_by = u.id
                   LEFT JOIN defect_images di ON d.id = di.defect_id
                   LEFT JOIN users rej_user ON d.rejected_by = rej_user.id
                   LEFT JOIN users reo_user ON d.reopened_by = reo_user.id
                   LEFT JOIN users acc_user ON d.accepted_by = acc_user.id
                   WHERE d.deleted_at IS NULL";

    // Add the same filter conditions as the main query
    if ($statusFilter !== 'all') {
        $countQuery .= " AND d.status = :status";
    }
    if ($priorityFilter !== 'all') {
        if ($priorityFilter === 'overdue') {
            $countQuery .= " AND d.due_date IS NOT NULL AND d.due_date < CURRENT_DATE()";
        } else {
            $countQuery .= " AND d.priority = :priority";
        }
    }
    if ($contractorFilter !== 'all') {
        $countQuery .= " AND d.assigned_to = :contractor_id";
    }
    if ($projectFilter !== 'all') {
        $countQuery .= " AND d.project_id = :project_id";
    }
    if (!empty($dateAddedFilter)) {
        $countQuery .= " AND DATE(d.created_at) = :date_added";
    }
    if (!empty($searchTerm)) {
        $countQuery .= " AND (d.title LIKE :search OR d.description LIKE :search OR c.company_name LIKE :search)";
    }

    $countStmt = $db->prepare($countQuery);
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetchColumn();
    $totalPages = ceil($totalRecords / $perPage);

    // Ensure $defects is an array
    if (!is_array($defects)) {
        $defects = [];
    }

    // Get filter options
    $statusQuery = "SELECT DISTINCT status FROM defects WHERE deleted_at IS NULL ORDER BY status";
    $priorityQuery = "SELECT DISTINCT priority FROM defects WHERE deleted_at IS NULL ORDER BY priority";
    $contractorQuery = "SELECT id, company_name FROM contractors WHERE status = 'active' ORDER BY company_name";
    $projectQuery = "SELECT id, name FROM projects WHERE status = 'active' ORDER BY name";

    $statuses = $db->query($statusQuery)->fetchAll(PDO::FETCH_COLUMN);
    $priorities = $db->query($priorityQuery)->fetchAll(PDO::FETCH_COLUMN);
    $contractors = $db->query($contractorQuery)->fetchAll(PDO::FETCH_ASSOC);
    $projects = $db->query($projectQuery)->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Defects Error: " . $e->getMessage());
    error_log("Session Debug for user $currentUser:\n" . print_r($_SESSION, true));
    $_SESSION['error_message'] = "Error: " . $e->getMessage();
    header("Location: " . BASE_URL . "dashboard.php");
    exit();
}

// Function to correct defect image paths
function correctDefectImagePath($path) {
    // Paths stored like this: "uploads/defects/104/img_67a4dedb9c7d4_17388581853507281592734635484040.jpg"
    if (strpos($path, 'uploads/defects/') === 0) {
        return BASE_URL . $path;
    } else {
        return BASE_URL . 'uploads/defects/' . $path; // This should not happen, but included for safety
    }
}

// Function to correct pin image paths
function correctPinImagePath($path) {
    // Paths stored like this: "uploads/defects/221/floorplan_with_pin_defect.png"
    if (strpos($path, 'uploads/defects/') === 0) {
        return BASE_URL . $path;
    } else {
        return BASE_URL . 'uploads/defects/' . $path; // This should not happen, but included for safety
    }
}

// Function to correct contractor logo paths
function correctContractorLogoPath($path) {
    return BASE_URL . 'uploads/logos/' . $path;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Defects Management - Defect Tracker">
    <meta name="author" content="<?php echo htmlspecialchars($currentUser ?? 'Unknown'); ?>">
    <meta name="last-modified" content="<?php echo htmlspecialchars($currentDateTime ?? 'Unknown'); ?>">
    <title><?php echo $pageTitle; ?> - Defect Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="/favicons/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicons/favicon.svg" />
    <link rel="shortcut icon" href="/favicons/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png" />
    <link rel="manifest" href="/favicons/site.webmanifest" />
    <style>
    .main-content {
        margin: 0 auto; /* Center the content horizontally */
        padding: 1.5rem;
        min-height: 100vh;
        background-color: #f8f9fa;
        max-width: 1400px; /* Set a max width for better readability */
        margin-top: 56px; /* Add margin-top to ensure content is not hidden behind the navbar */
    }
    .card {
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        border: none;
        margin-bottom: 1.5rem;
    }
    .defect-status {
        padding: 0.5em 0.75em;
        font-weight: 500;
    }
    .filter-section {
        background-color: #fff;
        border-radius: 0.5rem;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 0.125rem rgba(0, 0, 0, 0.075);
    }
    /* Action Buttons Styles */
    .action-buttons-container {
        background-color: #f8f9fa;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
        border: 1px solid #dee2e6;
    }
    .btn-action {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        padding: 15px 25px;
        font-size: 1.1rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-radius: 8px;
        transition: all 0.3s ease;
        width: 100%;
        margin-bottom: 10px;
    }
    .btn-action i {
        font-size: 1.5rem;
    }
    .btn-accept {
        background-color: #28a745;
        border-color: #28a745;
        color: white;
    }
    .btn-accept:hover {
        background-color: #218838;
        border-color: #1e7e34;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    .btn-reject {
        background-color: #dc3545;
        border-color: #dc3545;
        color: white;
    }
    .btn-reject:hover {
        background-color: #c82333;
        border-color: #bd2130;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    .btn-reopen {
        background-color: #17a2b8;
        border-color: #17a2b8;
        color: white;
    }
    .btn-reopen:hover {
        background-color: #138496;
        border-color: #117a8b;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    .table th {
        background-color: #f8f9fa;
        font-weight: 600;
        border-bottom: 2px solid #dee2e6;
    }
	/* Improved sticky table header */
.table-responsive {
    overflow-y: auto;
    max-height: 70vh; /* Set a max height to ensure scrolling */
    position: relative;
}

.table-responsive thead th {
    position: sticky;
    top: 0;
    z-index: 20;
    background-color: #f8f9fa;
    border-top: none;
    box-shadow: 0 1px 0 rgba(0, 0, 0, 0.1); /* Add subtle shadow for separation */
}

/* Ensure the header has proper styling when sticky */
.table-responsive table {
    border-collapse: separate;
    border-spacing: 0;
}

/* Add a subtle shadow to the bottom of the header row */
.table-responsive thead::after {
    content: '';
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    height: 2px;
    background-color: #dee2e6;
}	
    .btn-group {
        gap: 0.25rem;
    }
    .defect-badge {
        font-size: 0.75rem;
        padding: 0.25em 0.75em;
        border-radius: 1rem;
    }
    .rejection-details, .acceptance-details {
        font-size: 0.875rem;
        color: #6c757d;
        margin-top: 0.5rem;
        padding: 10px;
        background-color: #f8f9fa;
        border-radius: 4px;
    }
    .modal-header.bg-danger,
    .modal-header.bg-success {
        color: white;
    }
    .required::after {
        content: '*';
        color: red;
        margin-left: 4px;
    }
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    .priority-badge {
        padding: 0.4em 0.8em;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
    }
    .actions-column {
        min-width: 160px;
    }
    .description-cell {
        max-width: 300px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .modal-body img {
        max-width: 100%;
        height: auto;
        margin-bottom: 1rem;
        border-radius: 4px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .modal-body .img-container {
        background-color: #f8f9fa;
        padding: 10px;
        border-radius: 4px;
        margin-bottom: 1rem;
    }
    .modal-body .img-caption {
        font-size: 0.875rem;
        color: #6c757d;
        margin-top: 0.5rem;
        text-align: center;
    }
    /* CSS for zoomable images */
    .zoomable-image {
        cursor: zoom-in;
        transition: transform 0.3s ease;
    }
    .zoomable-image.zoomed {
        transform: scale(2);
        cursor: zoom-out;
    }
    @media (max-width: 991.98px) {
        .main-content {
            margin: 56px auto 0; /* Center and add top margin */
            padding: 1rem;
        }
        .filter-section {
            padding: 1rem;
        }
        .btn-action {
            padding: 12px 20px;
            font-size: 1rem;
        }
    }
    .contractor-cell {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .contractor-logo {
        max-height: 30px; /* Keep only max-height, remove max-width constraint */
        margin-left: 5px;
        vertical-align: middle;
    }
    .table td, .table th {
        vertical-align: middle;
    }
    .text-muted {
        color: #6c757d !important;
    }
    .reopened-details {
        font-size: 0.875rem;
        color: #0dcaf0; /* Info color matching the reopen button */
        margin-top: 0.5rem;
        padding: 10px;
        background-color: #f0fdff;
        border-radius: 4px;
        border-left: 3px solid #17a2b8;
    }	
    </style>
</head>
<body>
    <!-- Include Navbar -->
    <?php $navbar->render(); ?>
    <!-- Main Content -->
    <br><br>
    <div class="main-content">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0"><?php echo $pageTitle; ?></h1>
            </div>
            <?php if ($canEdit): ?>
            <div>
                <a href="<?php echo BASE_URL; ?>create_defect.php" class="btn btn-primary">
                    <i class='bx bx-plus'></i> Create New Defect
                </a>
            </div>
            <?php endif; ?>
        </div>
        <!-- Filters Section -->
        <div class="filter-section">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label">Project</label>
                    <select name="project" class="form-select">
                        <option value="all">All Projects</option>
                        <?php foreach ($projects as $project): ?>
                            <option value="<?php echo $project['id']; ?>"
                                <?php echo ($projectFilter == $project['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($project['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Contractor</label>
                    <select name="contractor" class="form-select">
                        <option value="all">All Contractors</option>
                        <?php foreach ($contractors as $contractor): ?>
                            <option value="<?php echo $contractor['id']; ?>"
                                <?php echo ($contractorFilter == $contractor['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($contractor['company_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="all">All Status</option>
                        <?php foreach ($statuses as $status): ?>
                            <option value="<?php echo $status; ?>"
                                <?php echo ($statusFilter === $status) ? 'selected' : ''; ?>>
                                <?php echo ucfirst(str_replace('_', ' ', $status)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Priority</label>
                    <select name="priority" class="form-select">
                        <option value="all">All Priorities</option>
                        <option value="overdue" <?php echo ($priorityFilter === 'overdue') ? 'selected' : ''; ?>>Overdue</option>
                        <?php foreach ($priorities as $priority): ?>
                            <option value="<?php echo $priority; ?>"
                                <?php echo ($priorityFilter === $priority) ? 'selected' : ''; ?>>
                                <?php echo ucfirst($priority); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Date Added</label>
                    <input type="date" name="date_added" class="form-control" value="<?php echo htmlspecialchars($dateAddedFilter); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Search</label>
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($searchTerm); ?>" placeholder="Search defects...">
                        <button type="submit" class="btn btn-outline-secondary">
                            <i class='bx bx-search'></i>
                        </button>
                        <div class="mt-3 text-end">
                            <a href="<?php echo BASE_URL; ?>pdf_exports/export-pdf-defects-report-filtered.php?<?php echo http_build_query($_GET); ?>" 
                               class="btn btn-primary" target="_blank">
                                <i class='bx bxs-file-pdf'></i> Generate PDF Report
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
		        <!-- Card with table pagination info -->
        <div class="card">
            <div class="card-body">
                <div class="text-muted mb-2">
                    Showing the 10 most recent defects.
                </div> <!-- End of filter-section -->
                
                <?php if ($totalPages > 1): ?>
                <div class="mb-3">
                    <nav aria-label="Defects pagination">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page-1; ?>&status=<?php echo urlencode($statusFilter); ?>&priority=<?php echo urlencode($priorityFilter); ?>&contractor=<?php echo urlencode($contractorFilter); ?>&project=<?php echo urlencode($projectFilter); ?>&date_added=<?php echo urlencode($dateAddedFilter); ?>&search=<?php echo urlencode($searchTerm); ?>" tabindex="-1">Previous</a>
                            </li>
                            
                            <?php
                            // Show limited page numbers with ellipsis for many pages
                            $startPage = max(1, min($page - 2, $totalPages - 4));
                            $endPage = min($totalPages, max(5, $page + 2));
                            
                            // Always show first page
                            if ($startPage > 1) {
                                echo '<li class="page-item"><a class="page-link" href="?page=1&status=' . urlencode($statusFilter) . '&priority=' . urlencode($priorityFilter) . '&contractor=' . urlencode($contractorFilter) . '&project=' . urlencode($projectFilter) . '&date_added=' . urlencode($dateAddedFilter) . '&search=' . urlencode($searchTerm) . '">1</a></li>';
                                
                                if ($startPage > 2) {
                                    echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                                }
                            }
                            
                            // Show page numbers
                            for ($i = $startPage; $i <= $endPage; $i++) {
                                echo '<li class="page-item ' . ($page == $i ? 'active' : '') . '">
                                    <a class="page-link" href="?page=' . $i . '&status=' . urlencode($statusFilter) . '&priority=' . urlencode($priorityFilter) . '&contractor=' . urlencode($contractorFilter) . '&project=' . urlencode($projectFilter) . '&date_added=' . urlencode($dateAddedFilter) . '&search=' . urlencode($searchTerm) . '">' . $i . '</a>
                                </li>';
                            }
                            
                            // Always show last page
                            if ($endPage < $totalPages) {
                                if ($endPage < $totalPages - 1) {
                                    echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                                }
                                echo '<li class="page-item"><a class="page-link" href="?page=' . $totalPages . '&status=' . urlencode($statusFilter) . '&priority=' . urlencode($priorityFilter) . '&contractor=' . urlencode($contractorFilter) . '&project=' . urlencode($projectFilter) . '&date_added=' . urlencode($dateAddedFilter) . '&search=' . urlencode($searchTerm) . '">' . $totalPages . '</a></li>';
                            }
                            ?>
                            
                            <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page+1; ?>&status=<?php echo urlencode($statusFilter); ?>&priority=<?php echo urlencode($priorityFilter); ?>&contractor=<?php echo urlencode($contractorFilter); ?>&project=<?php echo urlencode($projectFilter); ?>&date_added=<?php echo urlencode($dateAddedFilter); ?>&search=<?php echo urlencode($searchTerm); ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
                
                <!-- Defects Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Defect No</th>
                                        <th>Project</th>
                                        <th>Contractor</th>
                                        <th>Status</th>
                                        <th>Priority</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($defects)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4">
                                                <i class='bx bx-info-circle fs-4 text-muted'></i>
                                                <p class="text-muted mb-0">No defects found</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php if (is_array($defects)): ?>
                                            <?php foreach ($defects as $defect): ?>
                                                <tr>
                                                    <td><?php echo $defect['id']; ?></td>
                                                    <td><?php echo htmlspecialchars($defect['project_name']); ?></td>
                                                    <td>
                                                        <?php echo htmlspecialchars($defect['contractor_name'] ?? 'Unassigned'); ?>
                                                        <?php if (!empty($defect['contractor_logo'])): ?>
                                                            <img src="<?php echo correctContractorLogoPath($defect['contractor_logo']); ?>" alt="<?php echo htmlspecialchars($defect['contractor_name']); ?> Logo" class="contractor-logo">
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                            $statusClass = [
                                                                'open'        => 'warning',
                                                                'in_progress' => 'info',
                                                                'completed'   => 'success',
                                                                'verified'    => 'primary',
                                                                'rejected'    => 'danger',
                                                                'accepted'    => 'success',
                                                                'closed'      => 'success'
                                                            ][$defect['status']] ?? 'secondary';
                                                        ?>
                                                        <span class="badge bg-<?php echo $statusClass; ?>">
                                                            <?php echo ucfirst(str_replace('_', ' ', $defect['status'])); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php
                                                            $priorityClass = [
                                                                'low' => 'success',
                                                                'medium' => 'warning',
                                                                'high' => 'danger',
                                                                'critical' => 'dark'
                                                            ][$defect['priority']] ?? 'secondary';
                                                        ?>
                                                        <span class="badge bg-<?php echo $priorityClass; ?>">
                                                            <?php echo ucfirst($defect['priority']); ?>
                                                        </span>
                                                        
                                                        <?php if (!empty($defect['due_date'])): ?>
                                                            <div class="mt-1">
                                                                <?php 
                                                                    $isOverdue = isDueDateOverdue($defect['due_date']);
                                                                    $dueDateClass = $isOverdue ? 'text-danger fw-bold' : 'text-success';
                                                                    $dueDate = date('d M Y', strtotime($defect['due_date']));
                                                                ?>
                                                                <small class="<?php echo $dueDateClass; ?>">
                                                                    <i class='bx bx-calendar'></i> 
                                                                    <?php echo $isOverdue ? 'Overdue: ' : 'Due: '; ?>
                                                                    <?php echo $dueDate; ?>
                                                                </small>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div><?php echo date('d M Y, H:i:s', strtotime($defect['created_at'])); ?></div>
                                                        <small class="text-muted">
                                                            by <?php echo htmlspecialchars($defect['created_by_user'] ?? 'Unknown'); ?>
                                                        </small>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group">
                                                            <button type="button" class="btn btn-sm btn-primary"
                                                                    data-bs-toggle="modal" 
                                                                    data-bs-target="#viewDefectModal<?php echo $defect['id']; ?>">
                                                                <i class='bx bx-show'></i> View
                                                            </button>
                                                            <?php if ($canEdit): ?>
                                                                <a href="<?php echo BASE_URL . 'edit_defect.php?id=' . $defect['id']; ?>" class="btn btn-sm btn-secondary">
                                                                    <i class='bx bx-edit'></i> Edit
                                                                </a>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="7" class="text-center py-4">
                                                    <i class='bx bx-error-circle fs-4 text-danger'></i>
                                                    <p class="text-danger mb-0">Error: Defects data is not in the expected format.</p>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
						                        <?php if ($totalPages > 1): ?>
                        <nav aria-label="Defects pagination" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page-1; ?>&status=<?php echo urlencode($statusFilter); ?>&priority=<?php echo urlencode($priorityFilter); ?>&contractor=<?php echo urlencode($contractorFilter); ?>&project=<?php echo urlencode($projectFilter); ?>&date_added=<?php echo urlencode($dateAddedFilter); ?>&search=<?php echo urlencode($searchTerm); ?>" tabindex="-1">Previous</a>
                                </li>
                                
                                <?php
                                // Show limited page numbers with ellipsis for many pages
                                $startPage = max(1, min($page - 2, $totalPages - 4));
                                $endPage = min($totalPages, max(5, $page + 2));
                                
                                // Always show first page
                                if ($startPage > 1) {
                                    echo '<li class="page-item"><a class="page-link" href="?page=1&status=' . urlencode($statusFilter) . '&priority=' . urlencode($priorityFilter) . '&contractor=' . urlencode($contractorFilter) . '&project=' . urlencode($projectFilter) . '&date_added=' . urlencode($dateAddedFilter) . '&search=' . urlencode($searchTerm) . '">1</a></li>';
                                    
                                    if ($startPage > 2) {
                                        echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                                    }
                                }
                                
                                // Show page numbers
                                for ($i = $startPage; $i <= $endPage; $i++) {
                                    echo '<li class="page-item ' . ($page == $i ? 'active' : '') . '">
                                        <a class="page-link" href="?page=' . $i . '&status=' . urlencode($statusFilter) . '&priority=' . urlencode($priorityFilter) . '&contractor=' . urlencode($contractorFilter) . '&project=' . urlencode($projectFilter) . '&date_added=' . urlencode($dateAddedFilter) . '&search=' . urlencode($searchTerm) . '">' . $i . '</a>
                                    </li>';
                                }
                                
                                // Always show last page
                                if ($endPage < $totalPages) {
                                    if ($endPage < $totalPages - 1) {
                                        echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                                    }
                                    echo '<li class="page-item"><a class="page-link" href="?page=' . $totalPages . '&status=' . urlencode($statusFilter) . '&priority=' . urlencode($priorityFilter) . '&contractor=' . urlencode($contractorFilter) . '&project=' . urlencode($projectFilter) . '&date_added=' . urlencode($dateAddedFilter) . '&search=' . urlencode($searchTerm) . '">' . $totalPages . '</a></li>';
                                }
                                ?>
                                
                                <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page+1; ?>&status=<?php echo urlencode($statusFilter); ?>&priority=<?php echo urlencode($priorityFilter); ?>&contractor=<?php echo urlencode($contractorFilter); ?>&project=<?php echo urlencode($projectFilter); ?>&date_added=<?php echo urlencode($dateAddedFilter); ?>&search=<?php echo urlencode($searchTerm); ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <!-- Modals -->
        <?php if (is_array($defects)): ?>
            <?php foreach ($defects as $defect): ?>
                <!-- View Details Modal -->
                <!-- Updated modal-dialog class to modal-xl for a wider window -->
                <div class="modal fade" id="viewDefectModal<?php echo $defect['id']; ?>" tabindex="-1">
                    <div class="modal-dialog modal-xl">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Defect #<?php echo $defect['id']; ?> Details</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <?php if ($canEdit && in_array($defect['status'], ['open', 'pending', 'rejected', 'accepted'])): ?>
                                    <div class="action-buttons-container">
                                        <div class="row">
                                            <div class="col-12 col-md-4">
                                                <button type="button" class="btn btn-action btn-accept" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#acceptDefectModal<?php echo $defect['id']; ?>">
                                                    <i class='bx bx-check-circle'></i>
                                                    Accept
                                                </button>
                                            </div>
                                            <div class="col-12 col-md-4">
                                                <button type="button" class="btn btn-action btn-reject" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#rejectDefectModal<?php echo $defect['id']; ?>">
                                                    <i class='bx bx-x-circle'></i>
                                                    Reject
                                                </button>
                                            </div>
                                            <div class="col-12 col-md-4">
                                                <button type="button" class="btn btn-action btn-reopen" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#reopenDefectModal<?php echo $defect['id']; ?>">
                                                    <i class='bx bx-refresh'></i>
                                                    Reopen
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <div class="row">
                                    <div class="col-md-8">
                                        <h5><?php echo htmlspecialchars($defect['title']); ?></h5>
                                        <p class="text-muted"><?php echo nl2br(htmlspecialchars($defect['description'])); ?></p>
                                        <?php if ($defect['status'] === 'rejected'): ?>
                                            <div class="rejection-details">
                                                <strong>Rejection Reason:</strong><br>
                                                <?php echo nl2br(htmlspecialchars($defect['rejection_comment'] ?? '')); ?>
                                                <br>
                                                <small>
    Rejected by <?php echo htmlspecialchars($defect['rejected_by_user'] ?? 'Unknown'); ?>
    on <?php echo date('d M Y, H:i:s', strtotime($defect['updated_at'])); ?>
</small>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($defect['status'] === 'accepted'): ?>
                                            <div class="acceptance-details">
                                                <strong>Acceptance Comment:</strong><br>
                                                <?php echo nl2br(htmlspecialchars($defect['acceptance_comment'] ?? '')); ?>
                                                <br>
                                                <small>
                                                    Accepted by <?php echo htmlspecialchars($defect['accepted_by_user'] ?? 'Unknown'); ?>
                                                    on <?php echo date('d M Y, H:i:s', strtotime($defect['accepted_at'])); ?>
                                                </small>
                                            </div>										
                                        <?php endif; ?>
										<?php if (!empty($defect['reopened_reason'])): ?>
                                            <div class="reopened-details">
                                                <strong>Reason for Reopening:</strong><br>
                                                <?php echo nl2br(htmlspecialchars($defect['reopened_reason'] ?? '')); ?>
                                                <br>
                                                <small>
                                                    Reopened by <?php echo htmlspecialchars($defect['reopened_by_user'] ?? 'Unknown'); ?>
                                                    on <?php echo date('d M Y, H:i:s', strtotime($defect['reopened_at'])); ?>
                                                </small>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($defect['pin_paths'])):
                                            $pin_paths = explode(',', $defect['pin_paths']);
                                            foreach ($pin_paths as $pin_path):
                                                if (stripos($pin_path, 'floorplan_with_pin_defect.png') !== false) {
                                                    continue;
                                                }
                                        ?>
                                            <div class="mb-3">
                                                <label class="form-label">Pin Image:</label>
                                                <div class="img-container">
                                                    <?php echo '<img src="' . correctPinImagePath(trim($pin_path)) . '" class="img-fluid zoomable-image" alt="Pin Image">'; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; endif; ?>
                                        <?php if (!empty($defect['image_paths'])):
                                            $image_paths = explode(',', $defect['image_paths']);
                                            foreach ($image_paths as $image_path): ?>
                                                <div class="mb-3">
                                                    <label class="form-label">Attachment:</label>
                                                    <div class="img-container">
                                                        <?php echo '<img src="' . correctDefectImagePath(trim($image_path)) . '" class="img-fluid zoomable-image" alt="Attachment">'; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach;
                                        endif; ?>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card bg-light">
                                            <div class="card-body">
                                                <dl class="row mb-0">
                                                    <dt class="col-sm-5">Status:</dt>
                                                    <dd class="col-sm-7">
                                                        <span class="badge bg-<?php echo $statusClass; ?>">
                                                            <?php echo ucfirst(str_replace('_', ' ', $defect['status'])); ?>
                                                        </span>
                                                    </dd>
                                                    <dt class="col-sm-5">Priority:</dt>
                                                    <dd class="col-sm-7">
                                                        <span class="badge bg-<?php echo $priorityClass; ?>">
                                                            <?php echo ucfirst($defect['priority']); ?>
                                                        </span>
                                                    </dd>
                                                    <dt class="col-sm-5">Due Date:</dt>
                                                    <dd class="col-sm-7">
                                                        <?php if (!empty($defect['due_date'])): ?>
                                                            <?php 
                                                                $isOverdue = isDueDateOverdue($defect['due_date']);
                                                                $dueDateClass = $isOverdue ? 'text-danger fw-bold' : 'text-success';
                                                                $dueDate = date('d M Y', strtotime($defect['due_date']));
                                                            ?>
                                                            <span class="<?php echo $dueDateClass; ?>">
                                                                <i class='bx bx-calendar'></i> 
                                                                <?php echo $dueDate; ?>
                                                                <?php if ($isOverdue): ?>
                                                                    <span class="badge bg-danger ms-1">Overdue</span>
                                                                <?php endif; ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="text-muted">Not set</span>
                                                        <?php endif; ?>
                                                    </dd>
                                                    <dt class="col-sm-5">Project:</dt>
                                                    <dd class="col-sm-7">
                                                        <?php echo htmlspecialchars($defect['project_name']); ?>
                                                    </dd>
                                                    <dt class="col-sm-5">Contractor:</dt>
                                                    <dd class="col-sm-7 contractor-cell">
                                                        <?php if (!empty($defect['contractor_logo'])): ?>
                                                            <img src="<?php echo correctContractorLogoPath($defect['contractor_logo']); ?>" alt="<?php echo htmlspecialchars($defect['contractor_name']); ?> Logo" class="contractor-logo">
                                                        <?php endif; ?>
                                                        <span><?php echo htmlspecialchars($defect['contractor_name'] ?? 'Unassigned'); ?></span>
                                                    </dd>
                                                    <dt class="col-sm-5">Created:</dt>
                                                    <dd class="col-sm-7">
                                                        <?php echo date('d M Y, H:i:s', strtotime($defect['created_at'])); ?>
                                                        <br>
                                                        <small class="text-muted">
                                                            by <?php echo htmlspecialchars($defect['created_by_user'] ?? 'Unknown'); ?>
                                                        </small>
                                                    </dd>
                                                </dl>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <a href="<?php echo BASE_URL; ?>pdf_exports/pdf-defect.php?defect_id=<?php echo $defect['id']; ?>" class="btn btn-secondary" target="_blank">
                                    <i class='bx bx-download'></i> Download PDF
                                </a>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <?php if ($canEdit): ?>
                                    <a href="<?php echo BASE_URL . 'edit_defect.php?id=' . $defect['id']; ?>" class="btn btn-primary">Edit Defect</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Accept Defect Modal -->
                <?php if ($canEdit && $defect['status'] !== 'accepted'): ?>
                    <div class="modal fade" id="acceptDefectModal<?php echo $defect['id']; ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form method="POST" action="<?php echo BASE_URL; ?>accept_defect.php" class="needs-validation" novalidate>
                                    <input type="hidden" name="defect_id" value="<?php echo $defect['id']; ?>">
                                    <input type="hidden" name="action" value="accept_defect">
                                    <div class="modal-header bg-success text-white">
                                        <h5 class="modal-title">Accept Defect #<?php echo $defect['id']; ?></h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="form-label required">Acceptance Comment</label>
                                            <textarea name="acceptance_comment" class="form-control" rows="4" placeholder="Please provide any additional comments about accepting this defect..." required></textarea>
                                            <div class="invalid-feedback">
                                                Please provide an acceptance comment
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-success">
                                            <i class='bx bx-check-circle'></i> Confirm Acceptance
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
		                <!-- Reject Defect Modal -->
                <?php if ($canEdit && $defect['status'] !== 'rejected'): ?>
                    <div class="modal fade" id="rejectDefectModal<?php echo $defect['id']; ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form method="POST" action="<?php echo BASE_URL; ?>reject_defect.php" class="needs-validation" novalidate>
                                    <input type="hidden" name="defect_id" value="<?php echo $defect['id']; ?>">
                                    <input type="hidden" name="action" value="reject_defect">
                                    <div class="modal-header bg-danger text-white">
                                        <h5 class="modal-title">Reject Defect #<?php echo $defect['id']; ?></h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="form-label required">Reason for Rejection</label>
                                            <textarea name="rejection_comment" class="form-control" rows="4" placeholder="Please provide a reason for rejecting this defect..." required></textarea>
                                            <div class="invalid-feedback">
                                                Please provide a reason for rejection
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-danger">
                                            <i class='bx bx-x-circle'></i> Confirm Rejection
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Reopen Defect Modal -->
                <?php if ($canEdit && ($defect['status'] === 'rejected' || $defect['status'] === 'accepted')): ?>
                    <div class="modal fade" id="reopenDefectModal<?php echo $defect['id']; ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form method="POST" action="<?php echo BASE_URL; ?>reopen_defect.php" class="needs-validation" novalidate>
                                    <input type="hidden" name="defect_id" value="<?php echo $defect['id']; ?>">
                                    <input type="hidden" name="action" value="reopen_defect">
                                    <div class="modal-header bg-info text-white">
                                        <h5 class="modal-title">Reopen Defect #<?php echo $defect['id']; ?></h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="form-label required">Reason for Reopening</label>
                                            <textarea name="reopen_comment" class="form-control" rows="4" placeholder="Please provide the reason for reopening this defect..." required></textarea>
                                            <div class="invalid-feedback">
                                                Please provide a reason for reopening
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-info">
                                            <i class='bx bx-refresh'></i> Confirm Reopen
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle modal cleanup
            document.querySelectorAll('.modal').forEach(modalElement => {
                modalElement.addEventListener('hidden.bs.modal', function () {
                    document.body.classList.remove('modal-open');
                    const modalBackdrops = document.getElementsByClassName('modal-backdrop');
                    while(modalBackdrops.length > 0) {
                        modalBackdrops[0].parentNode.removeChild(modalBackdrops[0]);
                    }
                });
    
                // Handle nested modals
                modalElement.addEventListener('show.bs.modal', function (event) {
                    const openModals = document.querySelectorAll('.modal.show');
                    openModals.forEach(modal => {
                        if (modal !== this) {
                            const bsModal = bootstrap.Modal.getInstance(modal);
                            if (bsModal) {
                                bsModal.hide();
                            }
                        }
                    });
                });
            });
    
            // Form validation
            const forms = document.querySelectorAll('.needs-validation');
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', event => {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                });
            });
    
            // Auto-submit filter form on change
            document.querySelectorAll('.filter-section select, .filter-section input[type="date"]').forEach(element => {
                element.addEventListener('change', function() {
                    this.closest('form').submit();
                });
            });
    
            // Handle search input with debounce
            const searchInput = document.querySelector('input[name="search"]');
            let searchTimeout;
    
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        this.closest('form').submit();
                    }, 500);
                });
            }
    
            // Show session messages
            <?php if (isset($_SESSION['success_message'])): ?>
                showNotification("<?php echo addslashes($_SESSION['success_message']); ?>", 'success');
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>
            <?php if (isset($_SESSION['error_message'])): ?>
                showNotification("<?php echo addslashes($_SESSION['error_message']); ?>", 'danger');
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>
            
            // Add timestamp to page
            const timestampElement = document.createElement('div');
            timestampElement.className = 'text-muted small mt-3 text-end';
            timestampElement.innerHTML = 'Last updated: 2025-03-22 15:59:01 UTC • User: irlam';
            document.querySelector('.main-content').appendChild(timestampElement);
        });
    
        // Function to show notifications
        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
            notification.style.zIndex = "1050";
            notification.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 5000);
        }
    
        // Add zoom functionality to images for mobile devices
        document.querySelectorAll('.zoomable-image').forEach(img => {
            img.addEventListener('click', function() {
                this.classList.toggle('zoomed'); // Toggle zoom effect on click
            });
        });
    </script>
</body>
</html>
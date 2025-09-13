<?php
// projects.php
// Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): 2025-01-24 17:36:21
// Current User's Login: irlam
// Error reporting and logging setup
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Define INCLUDED constant for navbar security
define('INCLUDED', true);
require_once 'config/database.php';
// Removed navbar include
require_once 'includes/functions.php';

$pageTitle = 'Projects Management';
$success_message = '';
$error_message = '';
$currentDateTime = '2025-01-24 17:36:21';
$currentUser = $_SESSION['user_id'];

try {
    $database = new Database();
    $db = $database->getConnection();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Removed navbar initialization

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'create_project':
                    $project_name = trim($_POST['project_name']);
                    $description = trim($_POST['description']);
                    $start_date = $_POST['start_date'];
                    $end_date = $_POST['end_date'];
                    $status = $_POST['status'];

                    $stmt = $db->prepare("
                        INSERT INTO projects (
                            name, 
                            description, 
                            start_date, 
                            end_date, 
                            status, 
                            created_by,
                            updated_by,
                            created_at, 
                            updated_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    if ($stmt->execute([
                        $project_name,
                        $description,
                        $start_date,
                        $end_date,
                        $status,
                        $currentUser,
                        $currentUser,
                        $currentDateTime,
                        $currentDateTime
                    ])) {
                        $success_message = "Project created successfully";
                    } else {
                        $error_message = "Error creating project";
                    }
                    break;
					                case 'update_project':
                    $project_id = (int)$_POST['project_id'];
                    $project_name = trim($_POST['project_name']);
                    $description = trim($_POST['description']);
                    $start_date = $_POST['start_date'];
                    $end_date = $_POST['end_date'];
                    $status = $_POST['status'];

                    $stmt = $db->prepare("
                        UPDATE projects 
                        SET 
                            name = ?,
                            description = ?, 
                            start_date = ?, 
                            end_date = ?, 
                            status = ?, 
                            updated_by = ?,
                            updated_at = ? 
                        WHERE id = ?
                    ");
                    
                    if ($stmt->execute([
                        $project_name,
                        $description,
                        $start_date,
                        $end_date,
                        $status,
                        $currentUser,
                        $currentDateTime,
                        $project_id
                    ])) {
                        $success_message = "Project updated successfully";
                    } else {
                        $error_message = "Error updating project";
                    }
                    break;

                case 'delete_project':
                    $project_id = (int)$_POST['project_id'];
                    
                    // Check for associated defects
                    $checkDefects = $db->prepare("SELECT COUNT(*) FROM defects WHERE project_id = ?");
                    $checkDefects->execute([$project_id]);
                    $defectCount = $checkDefects->fetchColumn();

                    if ($defectCount > 0) {
                        $error_message = "Cannot delete project. There are defects associated with this project.";
                    } else {
                        $stmt = $db->prepare("DELETE FROM projects WHERE id = ?");
                        if ($stmt->execute([$project_id])) {
                            $success_message = "Project deleted successfully";
                        } else {
                            $error_message = "Error deleting project";
                        }
                    }
                    break;
            }
        }
    }

    // Get all projects with additional date information
    $query = "
        SELECT 
            p.id,
            p.name AS project_name,
            p.description,
            p.start_date,
            p.end_date,
            p.status,
            p.created_at,
            p.updated_at,
            DATEDIFF(p.end_date, CURRENT_DATE()) as days_remaining,
            DATEDIFF(p.end_date, p.start_date) as total_days,
            DATEDIFF(CURRENT_DATE(), p.start_date) as days_elapsed
        FROM projects AS p 
        ORDER BY p.created_at DESC
    ";
    $projects = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Database error in projects.php: " . $e->getMessage());
    $error_message = "Database error: " . $e->getMessage();
}

// Helper function for status badge classes
function getStatusBadgeClass($status) {
    switch (strtolower($status)) {
        case 'active':
            return 'bg-success';
        case 'pending':
            return 'bg-warning text-dark';
        case 'completed':
            return 'bg-info';
        case 'on-hold':
            return 'bg-secondary';
        default:
            return 'bg-primary';
    }
}

// Helper function to format dates
function formatDate($date) {
    return date('d m, Y', strtotime($date));
}
// Helper function to calculate project progress
function calculateProgress($start_date, $end_date) {
    $start = strtotime($start_date);
    $end = strtotime($end_date);
    $now = time();

    if ($start >= $end) return 0;
    if ($now >= $end) return 100;
    if ($now <= $start) return 0;

    $total = $end - $start;
    $elapsed = $now - $start;
    $progress = ($elapsed / $total) * 100;

    return round(max(0, min(100, $progress)));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Projects Management - Defect Tracker System">
    <meta name="author" content="<?php echo htmlspecialchars($_SESSION['username']); ?>">
    <meta name="last-modified" content="2025-02-27 21:24:23">
    <title>Projects Management - Defect Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
	<link rel="icon" type="image/png" href="/favicons/favicon-96x96.png" sizes="96x96" />
<link rel="icon" type="image/svg+xml" href="/favicons/favicon.svg" />
<link rel="shortcut icon" href="/favicons/favicon.ico" />
<link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png" />
<link rel="manifest" href="/favicons/site.webmanifest" />
    <style>
        .main-content {
            padding: 20px;
            min-height: 100vh;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e9f2 100%);
        }

        .project-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            transition: all 0.3s ease;
            border: none;
        }

        .project-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
        }

        .card {
            border: none;
            background: linear-gradient(to right, #ffffff, #f8f9fa);
        }

        .card-header {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            border-radius: 10px 10px 0 0 !important;
            padding: 1rem;
        }

        .date-info {
            background: linear-gradient(135deg, #f6f9fc 0%, #f1f4f8 100%);
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 10px;
        }

        .date-info i {
            color: #3498db;
            margin-right: 5px;
        }

        .date-label {
            font-size: 0.85rem;
            color: #6c757d;
            font-weight: 500;
            margin-bottom: 2px;
        }

        .date-value {
            font-size: 0.95rem;
            color: #2c3e50;
        }

        .progress {
            height: 8px;
            border-radius: 4px;
            margin-top: 10px;
            background-color: #e9ecef;
        }

        .progress-bar {
            background: linear-gradient(135deg, #3498db 0%, #2ecc71 100%);
        }

        .modal-content {
            border: none;
            border-radius: 15px;
            overflow: hidden;
        }

        .modal-header {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            border-bottom: none;
        }

        .modal-header .btn-close {
            color: white;
            opacity: 0.8;
        }

        .modal-footer {
            background: linear-gradient(to right, #f8f9fa, #ffffff);
            border-top: 1px solid rgba(0,0,0,0.05);
        }

        .btn-primary {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #2980b9 0%, #2c3e50 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.15);
        }

        .btn-danger {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            border: none;
        }

        .btn-danger:hover {
            background: linear-gradient(135deg, #c0392b 0%, #a93226 100%);
        }

        .status-badge {
            font-weight: 500;
            padding: 0.5em 1em;
            border-radius: 20px;
        }

        .time-remaining {
            font-size: 0.9rem;
            color: #6c757d;
            margin-top: 10px;
            padding: 8px;
            border-radius: 6px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }

    </style>
</head>
<body>
    <div class="main-content">
        <div class="page-header d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Projects Management</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                        <li class="breadcrumb-item active">Projects</li>
                    </ol>
                </nav>
            </div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createProjectModal">
                <i class='bx bx-plus-circle'></i> Create Project
            </button>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <?php if (empty($projects)): ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        No projects found. Create your first project using the 'Create Project' button.
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($projects as $project): ?>
                    <div class="col-md-6 col-xl-4">
                        <div class="project-card">
                            <div class="card h-100">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0">
                                        <?php echo htmlspecialchars($project['project_name']); ?>
                                    </h5>
                                    <span class="badge <?php echo getStatusBadgeClass($project['status']); ?> status-badge">
                                        <?php echo ucfirst(htmlspecialchars($project['status'])); ?>
                                    </span>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">
                                        <?php echo htmlspecialchars($project['description'] ?? 'No description available'); ?>
                                    </p>
                                    
                                    <div class="date-info">
                                        <div class="row">
                                            <div class="col-6">
                                                <div class="date-label">
                                                    <i class='bx bx-calendar'></i> Start Date
                                                </div>
                                                <div class="date-value">
                                                    <?php echo formatDate($project['start_date']); ?>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="date-label">
                                                    <i class='bx bx-calendar-check'></i> End Date
                                                </div>
                                                <div class="date-value">
                                                    <?php echo formatDate($project['end_date']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="date-info">
                                        <div class="date-label">
                                            <i class='bx bx-time'></i> Created
                                        </div>
                                        <div class="date-value">
                                            <?php echo date('M d, Y H:i', strtotime($project['created_at'])); ?>
                                        </div>
                                    </div>

                                    <?php 
                                    $progress = calculateProgress($project['start_date'], $project['end_date']);
                                    $daysRemaining = $project['days_remaining'];
                                    ?>
                                    
                                    <div class="progress" title="<?php echo $progress; ?>% Complete">
                                        <div class="progress-bar" role="progressbar" 
                                             style="width: <?php echo $progress; ?>%" 
                                             aria-valuenow="<?php echo $progress; ?>" 
                                             aria-valuemin="0" 
                                             aria-valuemax="100">
                                        </div>
                                    </div>
                                    
                                    <div class="time-remaining">
                                        <i class='bx bx-time-five'></i>
                                        <?php 
                                        if ($daysRemaining > 0) {
                                            echo "$daysRemaining days remaining";
                                        } elseif ($daysRemaining == 0) {
                                            echo "Due today";
                                        } else {
                                            echo abs($daysRemaining) . " days overdue";
                                        }
                                        ?>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-sm btn-primary" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editProjectModal<?php echo $project['id']; ?>">
                                            <i class='bx bx-edit'></i> Edit
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger"
                                                onclick="confirmDeleteProject(<?php echo $project['id']; ?>)">
                                            <i class='bx bx-trash'></i> Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Edit Project Modal -->
                        <div class="modal fade" id="editProjectModal<?php echo $project['id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form method="POST" class="needs-validation" novalidate>
                                        <input type="hidden" name="action" value="update_project">
                                        <input type="hidden" name="project_id" value="<?php echo $project['id']; ?>">

                                        <div class="modal-header">
                                            <h5 class="modal-title">Edit Project</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label class="form-label required">Project Name</label>
                                                <input type="text" name="project_name" class="form-control" 
                                                       value="<?php echo htmlspecialchars($project['project_name']); ?>" required>
                                                <div class="invalid-feedback">Project name is required</div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Description</label>
                                                <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($project['description']); ?></textarea>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Start Date</label>
                                                <input type="date" name="start_date" class="form-control" 
                                                       value="<?php echo date('Y-m-d', strtotime($project['start_date'])); ?>" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">End Date</label>
                                                <input type="date" name="end_date" class="form-control" 
                                                       value="<?php echo date('Y-m-d', strtotime($project['end_date'])); ?>" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Status</label>
                                                <select name="status" class="form-select" required>
                                                    <option value="pending" <?php echo $project['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="active" <?php echo $project['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                                    <option value="completed" <?php echo $project['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                    <option value="on-hold" <?php echo $project['status'] == 'on-hold' ? 'selected' : ''; ?>>On Hold</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-primary">Save Changes</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Create Project Modal -->
        <div class="modal fade" id="createProjectModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST">
                        <input type="hidden" name="action" value="create_project">
                        
                        <div class="modal-header">
                            <h5 class="modal-title">Create New Project</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Project Name</label>
                                <input type="text" name="project_name" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="3"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Start Date</label>
                                <input type="date" name="start_date" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">End Date</label>
                                <input type="date" name="end_date" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select" required>
                                    <option value="pending">Pending</option>
                                    <option value="active">Active</option>
                                    <option value="completed">Completed</option>
                                    <option value="on-hold">On Hold</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Create Project</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Project Form -->
    <form id="deleteProjectForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete_project">
        <input type="hidden" name="project_id" id="deleteProjectId">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
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

            // Delete project confirmation
            window.confirmDeleteProject = function(projectId) {
                if (confirm('Are you sure you want to delete this project? This action cannot be undone.')) {
                    const form = document.getElementById('deleteProjectForm');
                    document.getElementById('deleteProjectId').value = projectId;
                    form.submit();
                }
            };

            // Date validation
            const startDateInputs = document.querySelectorAll('input[name="start_date"]');
            const endDateInputs = document.querySelectorAll('input[name="end_date"]');

            startDateInputs.forEach(input => {
                input.addEventListener('change', function() {
                    const endDateInput = this.closest('form').querySelector('input[name="end_date"]');
                    endDateInput.min = this.value;
                });
            });

            endDateInputs.forEach(input => {
                input.addEventListener('change', function() {
                    const startDateInput = this.closest('form').querySelector('input[name="start_date"]');
                    startDateInput.max = this.value;
                });
            });

            // Initialize tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>
</html>
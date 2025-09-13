<?php
// edit_defect.php
// Current Date and Time (UTC): 2025-02-03 19:49:35
// Current User: irlam

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

require_once 'config/database.php';
require_once 'includes/functions.php';

$currentUser = $_SESSION['username'];
$currentUserId = (int)$_SESSION['user_id'];
$currentDateTime = date('Y-m-d H:i:s');

// Generate CSRF token if not already generated
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get defect ID from URL
$defectId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$defectId) {
    $_SESSION['error_message'] = "Invalid defect ID";
    header("Location: defects.php");
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Get defect details
    $query = "SELECT d.*, 
              p.name as project_name,
              c.company_name as contractor_name
              FROM defects d
              LEFT JOIN projects p ON d.project_id = p.id
              LEFT JOIN contractors c ON d.contractor_id = c.id
              WHERE d.id = :id AND d.deleted_at IS NULL";

    $stmt = $db->prepare($query);
    $stmt->execute([':id' => $defectId]);
    $defect = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$defect) {
        throw new Exception("Defect not found");
    }

    // Get projects list
    $projectQuery = "SELECT id, name FROM projects WHERE status = 'active' ORDER BY name";
    $projectStmt = $db->query($projectQuery);
    $projects = $projectStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get contractors list
    $contractorQuery = "SELECT id, company_name FROM contractors WHERE status = 'active' ORDER BY company_name";
    $contractorStmt = $db->query($contractorQuery);
    $contractors = $contractorStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Edit Defect Error: " . $e->getMessage());
    $_SESSION['error_message'] = "Error: " . $e->getMessage();
    header("Location: defects.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Defect #<?php echo $defectId; ?> - Defect Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Edit Defect #<?php echo $defectId; ?></h5>
                    </div>
                    <div class="card-body">
                        <form action="api/update_defect.php" method="POST" class="needs-validation" enctype="multipart/form-data" novalidate>
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="id" value="<?php echo $defectId; ?>">
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label required">Title</label>
                                    <input type="text" class="form-control" name="title" 
                                           value="<?php echo htmlspecialchars($defect['title'] ?? ''); ?>" required>
                                    <div class="invalid-feedback">Please provide a title</div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label required">Project</label>
                                    <select class="form-select" name="project_id" required>
                                        <option value="">Select Project</option>
                                        <?php foreach ($projects as $project): ?>
                                            <option value="<?php echo $project['id']; ?>" 
                                                <?php echo ($project['id'] == $defect['project_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($project['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Please select a project</div>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Assigned Contractor</label>
                                    <select class="form-select" name="contractor_id">
                                        <option value="">Select Contractor</option>
                                        <?php foreach ($contractors as $contractor): ?>
                                            <option value="<?php echo $contractor['id']; ?>" 
                                                <?php echo ($contractor['id'] == $defect['contractor_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($contractor['company_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label required">Priority</label>
                                    <select class="form-select" name="priority" required>
                                        <?php
                                        $priorities = ['low', 'medium', 'high', 'critical'];
                                        foreach ($priorities as $priority):
                                        ?>
                                            <option value="<?php echo $priority; ?>" 
                                                <?php echo ($priority == $defect['priority']) ? 'selected' : ''; ?>>
                                                <?php echo ucfirst($priority); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Please select a priority level</div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label required">Description</label>
                                <textarea class="form-control" name="description" rows="4" required><?php 
                                    echo htmlspecialchars($defect['description'] ?? ''); 
                                ?></textarea>
                                <div class="invalid-feedback">Please provide a description</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Due Date</label>
                                <input type="date" class="form-control" name="due_date" 
                                       value="<?php echo $defect['due_date']; ?>">
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="status" id="status">
                                        <option value="open" <?php echo $defect['status'] === 'open' ? 'selected' : ''; ?>>Open</option>
                                        <option value="closed" <?php echo $defect['status'] === 'closed' ? 'selected' : ''; ?>>Closed</option>
                                        <option value="rejected" <?php echo $defect['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                    </select>
                                </div>

                                <div class="col-md-6" id="closure_image_field" style="display: none;">
                                    <label class="form-label">Closure Image</label>
                                    <input type="file" class="form-control" name="closure_image" id="closure_image">
                                </div>

                                <div class="col-md-6" id="rejection_comment_field" style="display: none;">
                                    <label class="form-label">Rejection Comment</label>
                                    <textarea class="form-control" name="rejection_comment" id="rejection_comment"><?php 
                                        echo htmlspecialchars($defect['rejection_comment'] ?? ''); 
                                    ?></textarea>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="defects.php" class="btn btn-secondary">Cancel</a>
                                <div>
                                    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteDefectModal">Delete</button>
                                    <button type="submit" class="btn btn-primary">Update Defect</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Defect Modal -->
    <div class="modal fade" id="deleteDefectModal" tabindex="-1" aria-labelledby="deleteDefectModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteDefectModalLabel">Delete Defect</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete this defect? This action cannot be undone.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form action="https://mcgoff.defecttracker.uk/api/delete_defect.php" method="POST" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="id" value="<?php echo $defectId; ?>">
                        <button type="submit" class="btn btn-danger">Delete Defect</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('.needs-validation');
            
            Array.prototype.slice.call(forms).forEach(function(form) {
                form.addEventListener('submit', function(event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                });
            });

            // Show/hide fields based on status
            document.getElementById('status').addEventListener('change', function() {
                var status = this.value;
                document.getElementById('closure_image_field').style.display = status === 'closed' ? '' : 'none';
                document.getElementById('rejection_comment_field').style.display = status === 'rejected' ? '' : 'none';
            });
            // Trigger change event to set initial state
            document.getElementById('status').dispatchEvent(new Event('change'));
        });
    </script>
</body>
</html>
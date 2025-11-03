<?php
/**
 * upload_floor_plan.php
 * Floor Plan Upload and Management 
 * Current Date and Time (UTC): 2025-02-08 18:56:03
 * Current User's Login: irlam
 */

// Error reporting and logging setup
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/navbar.php';

if (!isset($_SESSION['user_id'], $_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

$currentUserRoleId = $_SESSION['role_id'] ?? null;
$currentUserType = $_SESSION['user_type'] ?? null;
$success_message = '';
$error_message = '';
$projects = [];
$floorPlans = [];
$navbar = null;
$isAuthorised = true;
$roleMap = [
    'admin' => 1,
    'manager' => 2,
    'contractor' => 3,
    'inspector' => 4,
    'viewer' => 5,
];

try {
    $database = new Database();
    $db = $database->getConnection();

    if ($currentUserRoleId === null) {
        $fetchedRoleId = null;

        if (isset($_SESSION['user_id'])) {
            $roleStmt = $db->prepare(
                "
                SELECT role_id
                FROM user_roles
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT 1
                "
            );
            $roleStmt->execute([$_SESSION['user_id']]);
            $fetchedRoleId = $roleStmt->fetchColumn();
        }

        if ($fetchedRoleId) {
            $currentUserRoleId = (int) $fetchedRoleId;
            $_SESSION['role_id'] = $currentUserRoleId;
        } elseif ($currentUserType) {
            $normalizedType = strtolower((string) $currentUserType);
            if (isset($roleMap[$normalizedType])) {
                $currentUserRoleId = (int) $roleMap[$normalizedType];
                $_SESSION['role_id'] = $currentUserRoleId;
            }
        }
    }

    $hasPermission = false;
    if ($currentUserRoleId !== null) {
        $hasPermission = in_array((int) $currentUserRoleId, [1, 2], true);
    }

    if (!$hasPermission && $currentUserType) {
        $normalizedType = strtolower((string) $currentUserType);
        $hasPermission = in_array($normalizedType, ['admin', 'manager'], true);
    }

    if (!$hasPermission) {
        $isAuthorised = false;
        throw new RuntimeException('You do not have permission to access this page.');
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    try {
        // Initialize the Navbar class
    $navbar = new Navbar($db, $_SESSION['user_id'], $_SESSION['username']);
    } catch (Exception $e) {
        error_log("Navbar Error: " . $e->getMessage());
        $error_message = "An error occurred while loading the navigation.";
    }

    // Get active projects for dropdown
    $stmt = $db->prepare("
        SELECT id, name 
        FROM projects 
        WHERE is_active = 1
        ORDER BY name ASC
    ");
    
    $stmt->execute();
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get existing floor plans for the listing
    $floorPlansStmt = $db->prepare("
        SELECT fp.*, p.name as project_name
        FROM floor_plans fp
        JOIN projects p ON fp.project_id = p.id
        WHERE fp.status != 'deleted'
        ORDER BY fp.last_modified DESC
    ");
    $floorPlansStmt->execute();
    $floorPlans = $floorPlansStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (RuntimeException $permissionError) {
    http_response_code(403);
    $error_message = $permissionError->getMessage();
    error_log("Upload Floor Plan Permission Error: " . $permissionError->getMessage());
    $isAuthorised = false;
} catch (Exception $e) {
    $error_message = $e->getMessage();
    error_log("Upload Floor Plan Error: " . $e->getMessage());
    $isAuthorised = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Floor Plan - Defect Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
</head>
<body data-bs-theme="dark">
    <?php if ($navbar instanceof Navbar) { $navbar->render(); } ?>

    <div class="content-wrapper">
        <div class="container-fluid py-4">
            <?php if ($error_message !== ''): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class='bx bx-error-circle me-1'></i><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if ($isAuthorised): ?>
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="h3 mb-0">Upload Floor Plan</h1>
                    </div>

                    <div id="alertContainer"></div>

                    <div class="row mb-4">
                        <div class="col-lg-8">
                            <div class="card">
                                <div class="card-body">
                                    <form id="uploadForm" method="POST" enctype="multipart/form-data">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="project_id" class="form-label">Project <span class="text-danger">*</span></label>
                                                <select class="form-select" id="project_id" name="project_id" required>
                                                    <option value="">Select Project</option>
                                                    <?php foreach ($projects as $project): ?>
                                                        <option value="<?php echo htmlspecialchars($project['id']); ?>">
                                                            <?php echo htmlspecialchars($project['name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <div class="invalid-feedback">Please select a project.</div>
                                            </div>

                                            <div class="col-md-6 mb-3">
                                                <label for="floor_name" class="form-label">Floor Name <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="floor_name" name="floor_name" required
                                                       placeholder="e.g., Ground Floor, First Floor, etc.">
                                                <div class="invalid-feedback">Please enter a floor name.</div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-12 mb-3">
                                                <label for="level" class="form-label">Level <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="level" name="level" 
                                                       placeholder="e.g., L1, B1, G" required>
                                                <div class="invalid-feedback">Please enter a level.</div>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label for="description" class="form-label">Description</label>
                                            <textarea class="form-control" id="description" name="description" 
                                                      rows="3" placeholder="Enter floor plan description"></textarea>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label">Floor Plan File <span class="text-danger">*</span></label>
                                            <div class="upload-preview" id="dropZone">
                                                <div class="drop-text">
                                                    <i class="bx bx-cloud-upload"></i>
                                                    <p class="mb-1">Drag and drop your file here or click to browse</p>
                                                    <small class="text-muted">Supported formats: JPG, PNG, GIF, PDF (Max: 10MB)</small>
                                                </div>
                                                <img id="preview" style="display: none; max-height: 300px; margin: 10px auto;">
                                                <div class="file-info" id="fileInfo" style="display: none;"></div>
                                            </div>
                                            <input type="file" class="form-control" id="floor_plan" name="floor_plan" 
                                                   accept=".jpg,.jpeg,.png,.gif,.pdf" required style="display: none;">
                                            <div class="progress" style="display: none;">
                                                <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                                     role="progressbar" style="width: 0%"></div>
                                            </div>
                                        </div>

                                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                            <button type="button" class="btn btn-light me-md-2" onclick="resetForm()">
                                                <i class="bx bx-reset me-1"></i>Reset
                                            </button>
                                            <button type="submit" class="btn btn-primary" id="submitButton">
                                                <i class="bx bx-upload me-1"></i>Upload Floor Plan
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Upload Guidelines</h5>
                                </div>
                                <div class="card-body">
                                    <ul class="list-unstyled">
                                        <li class="mb-2">
                                            <i class="bx bx-check-circle text-success me-2"></i>
                                            CROP THE PDFS (REMOVE THE UNWANTED TEXT, AS IT TAKES UP TO MANY RESORCES ON THE SERVER CAUSING THE UPLOAD TO FAIL)
                                        </li>
                                        <li class="mb-2">
                                            <i class="bx bx-check-circle text-success me-2"></i>
                                            File size should not exceed 10MB
                                        </li>
                                        <li class="mb-2">
                                            <i class="bx bx-check-circle text-success me-2"></i>
                                            Supported formats: JPG, PNG, GIF, PDF
                                        </li>
                                        <li class="mb-2">
                                            <i class="bx bx-check-circle text-success me-2"></i>
                                            Recommended resolution: 1920x1080 or higher
                                        </li>
                                        <li class="mb-2">
                                            <i class="bx bx-check-circle text-success me-2"></i>
                                            Clear and legible text/markings
                                        </li>
                                        <li class="mb-2">
                                            <i class="bx bx-check-circle text-success me-2"></i>
                                            Include scale and orientation markers
                                        </li>
                                        <li class="mb-2">
                                            <i class="bx bx-info-circle text-info me-2"></i>
                                            Ensure the floor plan is properly oriented
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Floor Plans Listing -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Recent Floor Plans</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped" id="floorPlansTable">
                                    <thead>
                                        <tr>
                                            <th>Floor Name</th>
                                            <th>Project</th>
                                            <th>Level</th>
                                            <th>Type</th>
                                            <th>Last Modified</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($floorPlans as $plan): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($plan['floor_name']); ?></td>
                                                <td><?php echo htmlspecialchars($plan['project_name']); ?></td>
                                                <td><?php echo htmlspecialchars($plan['level']); ?></td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?php echo htmlspecialchars($plan['file_type']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('Y-m-d H:i:s', strtotime($plan['last_modified'])); ?></td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-info" 
                                                            onclick="viewFloorPlan(<?php echo htmlspecialchars(json_encode($plan)); ?>)">
                                                        <i class="bx bx-show"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card border-danger-subtle">
                        <div class="card-body text-center py-5">
                            <h2 class="h4 mb-3"><i class='bx bx-block me-2'></i>Access Restricted</h2>
                            <p class="text-muted mb-0">Your account does not have permission to manage floor plans. Please contact a system administrator if you believe this is an error.</p>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- View Floor Plan Modal -->
    <div class="modal fade" id="viewFloorPlanModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">View Floor Plan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="viewFloorPlanContent"></div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

    <script>
        // Initialize DataTable
        $(document).ready(function() {
            $('#floorPlansTable').DataTable({
                order: [[4, 'desc']], // Sort by last modified
                pageLength: 10,
                responsive: true
            });
        });

        // File Upload Handling
const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('floor_plan');
const preview = document.getElementById('preview');
const fileInfo = document.getElementById('fileInfo');

['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
    dropZone.addEventListener(eventName, preventDefaults, false);
});

function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
}

['dragenter', 'dragover'].forEach(eventName => {
    dropZone.addEventListener(eventName, highlight, false);
});

['dragleave', 'drop'].forEach(eventName => {
    dropZone.addEventListener(eventName, unhighlight, false);
});

function highlight(e) {
    dropZone.classList.add('dragover');
}

function unhighlight(e) {
    dropZone.classList.remove('dragover');
}

dropZone.addEventListener('drop', handleDrop, false);
dropZone.addEventListener('click', () => fileInput.click());
fileInput.addEventListener('change', handleFiles);

function handleDrop(e) {
    const dt = e.dataTransfer;
    const files = dt.files;
    handleFiles({ target: { files: files } });
}

function handleFiles(e) {
    const file = e.target.files[0];
    if (file) {
        // Validate file size (10MB)
        if (file.size > 10485760) {
            showAlert('File size exceeds 10MB limit', 'danger');
            resetFileInput();
            return;
        }

        // Validate file type
        const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
        if (!validTypes.includes(file.type)) {
            showAlert('Invalid file type. Please upload JPG, PNG, GIF, or PDF', 'danger');
            resetFileInput();
            return;
        }

        // Show preview for images
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            };
            reader.readAsDataURL(file);
        } else {
            preview.style.display = 'none';
        }

        // Show file info
        fileInfo.innerHTML = `
            <div class="alert alert-info mt-3 mb-0">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong>Selected File:</strong> ${file.name}<br>
                        <strong>Size:</strong> ${(file.size / 1024 / 1024).toFixed(2)} MB<br>
                        <strong>Type:</strong> ${file.type}
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="resetFileInput()">
                        <i class="bx bx-x"></i> Remove
                    </button>
                </div>
            </div>
        `;
        fileInfo.style.display = 'block';
    }
}

function resetFileInput() {
    fileInput.value = '';
    preview.style.display = 'none';
    preview.src = '';
    fileInfo.style.display = 'none';
    fileInfo.innerHTML = '';
}

function resetForm() {
    document.getElementById('uploadForm').reset();
    resetFileInput();
}

// Form submission handling
document.getElementById('uploadForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Validate form
    if (!validateForm()) {
        return;
    }

    // Disable submit button and show loading state
    const submitButton = document.getElementById('submitButton');
    const originalButtonText = submitButton.innerHTML;
    submitButton.disabled = true;
    submitButton.innerHTML = '<i class="bx bx-loader bx-spin me-1"></i>Uploading...';

    // Create FormData object
    const formData = new FormData(this);

    // Send AJAX request
    fetch('api/upload_floor_plan.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Floor plan uploaded successfully!', 'success');
            setTimeout(() => window.location.reload(), 2000);
        } else {
            throw new Error(data.message || 'Upload failed');
        }
    })
    .catch(error => {
        console.error('Upload error:', error);
        showAlert('Upload failed: ' + error.message, 'danger');
    })
    .finally(() => {
        // Reset button state
        submitButton.disabled = false;
        submitButton.innerHTML = originalButtonText;
    });
});

function validateForm() {
    const projectId = document.getElementById('project_id').value;
    const floorName = document.getElementById('floor_name').value;
    const level = document.getElementById('level').value;
    const file = fileInput.files[0];

    if (!projectId) {
        showAlert('Please select a project', 'danger');
        return false;
    }

    if (!floorName) {
        showAlert('Please enter a floor name', 'danger');
        return false;
    }

    if (!level) {
        showAlert('Please enter a level', 'danger');
        return false;
    }

    if (!file) {
        showAlert('Please select a file to upload', 'danger');
        return false;
    }

    return true;
}

function showAlert(message, type) {
    const alertContainer = document.getElementById('alertContainer');
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        <div class="d-flex align-items-center">
            <i class="bx ${type === 'success' ? 'bx-check-circle' : 'bx-x-circle'} me-2"></i>
            <div>${message}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
    
    // Remove existing alerts
    alertContainer.innerHTML = '';
    
    // Add new alert
    alertContainer.appendChild(alertDiv);

    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        alertDiv.classList.remove('show');
        setTimeout(() => alertDiv.remove(), 150);
    }, 5000);
}

// View floor plan function
function viewFloorPlan(plan) {
    const modal = new bootstrap.Modal(document.getElementById('viewFloorPlanModal'));
    const contentDiv = document.getElementById('viewFloorPlanContent');

    let content = `
        <div class="mb-3">
            <strong>Floor Name:</strong> ${plan.floor_name}<br>
            <strong>Project:</strong> ${plan.project_name}<br>
            <strong>Level:</strong> ${plan.level}<br>
            <strong>Last Modified:</strong> ${new Date(plan.last_modified).toLocaleString()}
        </div>
    `;

    if (plan.file_type === 'pdf') {
        content += `
            <div class="ratio ratio-16x9">
                <embed src="${plan.file_path}" type="application/pdf" width="100%" height="100%">
            </div>
        `;
    } else {
        content += `
            <img src="${plan.image_path || plan.file_path}" class="img-fluid" alt="${plan.floor_name}">
        `;
    }

    contentDiv.innerHTML = content;
    modal.show();
}
</script>
</body>
</html>
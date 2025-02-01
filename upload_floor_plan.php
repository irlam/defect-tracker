<?php
/**
 * upload_floor_plan.php
 * Floor Plan Upload and Management
 * Current Date and Time (UTC): 2025-01-28 20:09:55
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

// Define INCLUDED after includes for compatibility
define('INCLUDED', true);

$pageTitle = 'Upload Floor Plan';

// Check if role_id is set in the session
if (!isset($_SESSION['role_id'])) {
    die('Error: role_id is not set in the session.');
}

$currentUserRoleId = $_SESSION['role_id'];

// Check if the user has permission to upload (admin or manager)
if ($currentUserRoleId != 1 && $currentUserRoleId != 2) {
    die('Error: You do not have permission to access this page.');
}

$success_message = '';
$error_message = '';
$projects = [];

try {
    $database = new Database();
    $db = $database->getConnection();

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    // At the top of upload_floor_plan.php, after database connection
try {
    // Initialize the Navbar class
    $navbar = new Navbar($db, $_SESSION['user_id']);

} catch (Exception $e) {
    logError("Navbar Error: " . $e->getMessage());
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

} catch (Exception $e) {
    $error_message = $e->getMessage();
    error_log("Upload Floor Plan Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - Defect Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        /* Main layout styles */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            width: 260px;
            background: #344767;
            z-index: 1000;
            overflow-y: auto;
        }

        .content-wrapper {
            margin-left: 260px;
            padding: 20px;
            min-height: 100vh;
            background-color: #f8f9fa;
        }

        /* Mobile responsiveness */
        @media (max-width: 991.98px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease-in-out;
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .content-wrapper {
                margin-left: 0;
            }
        }

        /* Upload specific styles */
        .upload-preview {
            border: 2px dashed #ccc;
            border-radius: 4px;
            padding: 20px;
            text-align: center;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .upload-preview.dragover {
            border-color: #0d6efd;
            background-color: rgba(13, 110, 253, 0.05);
        }

        /* File preview styles */
        .pdf-preview-container,
        .image-preview-container {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
        }

        .pdf-wrapper {
            background-color: #fff;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .image-wrapper {
            background-color: #fff;
            padding: 10px;
            border-radius: 4px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        #viewFloorPlanModal .modal-dialog {
            max-width: 90%;
            margin: 1.75rem auto;
        }

        @media (min-width: 992px) {
            #viewFloorPlanModal .modal-dialog {
                max-width: 80%;
            }
        }

        #viewFloorPlanModal .modal-content {
            height: calc(100vh - 3.5rem);
        }

        #viewFloorPlanModal .modal-body {
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }

        #viewPreviewContainer {
            flex: 1;
            min-height: 0;
            overflow: auto;
        }

        .pdf-controls,
        .image-controls {
            position: sticky;
            top: 0;
            z-index: 1;
            background-color: #f8f9fa;
            padding: 10px 0;
        }

        /* Table styles */
        .floor-plan-preview {
            max-width: 50px;
            max-height: 50px;
            object-fit: contain;
            border-radius: 4px;
        }

        .floor-plan-preview.pdf-preview {
            width: 40px;
            height: 40px;
            padding: 2px;
        }

        .floor-plan-preview.image-preview {
            border: 1px solid #dee2e6;
            background-color: #fff;
            padding: 2px;
        }

        /* Alert container */
        #alertContainer {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
            max-width: 350px;
        }

        /* Card styles */
        .card {
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
            border: none;
            margin-bottom: 24px;
        }

        .card-header {
            background-color: #fff;
            border-bottom: 1px solid rgba(0, 0, 0, 0.08);
        }

        /* Loading and error states */
        .preview-loading {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 200px;
            background-color: #f8f9fa;
            border-radius: 4px;
        }

        .preview-error {
            padding: 20px;
            text-align: center;
            background-color: #fff3cd;
            border: 1px solid #ffeeba;
            border-radius: 4px;
            color: #856404;
        }

        /* Navigation styles */
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 0.5rem 1rem;
            display: flex;
            align-items: center;
            text-decoration: none;
        }

        .sidebar .nav-link i {
            margin-right: 10px;
        }

        .sidebar .nav-link:hover {
            color: #fff;
            background: rgba(255, 255, 255, 0.1);
        }

        .sidebar .nav-link.active {
            background: rgba(255, 255, 255, 0.2);
            color: #fff;
        }
    </style>
</head>
<body>
    <?php echo $navbar->render(); ?>

    <div class="content-wrapper">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0"><?php echo htmlspecialchars($pageTitle); ?></h1>
    </div>
                    </div>

                    <div id="alertContainer"></div>

                    <!-- Upload Form and Guidelines -->
                    <div class="row mb-4">
                        <div class="col-lg-8">
                            <div class="card">
                                <div class="card-body">
                                    <form id="uploadForm" enctype="multipart/form-data">
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
                                                <i class="bx bx-upload me-1"></i>Upload Floor Plans
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
                                    <ul class="list-unstyled guidelines-list">
                                        <li>
                                            <i class="bx bx-check-circle text-success"></i>
                                            File size should not exceed 10MB
                                        </li>
                                        <li>
                                            <i class="bx bx-check-circle text-success"></i>
                                            Supported formats: JPG, PNG, GIF, PDF
                                        </li>
                                        <li>
                                            <i class="bx bx-check-circle text-success"></i>
                                            Recommended resolution: 1920x1080 or higher
                                        </li>
                                        <li>
                                            <i class="bx bx-check-circle text-success"></i>
                                            Clear and legible text/markings
                                        </li>
                                        <li>
                                            <i class="bx bx-check-circle text-success"></i>
                                            Include scale and orientation markers
                                        </li>
                                        <li>
                                            <i class="bx bx-info-circle text-info"></i>
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
                                <table class="table table-striped table-hover" id="floorPlansTable">
                                    <thead>
                                        <tr>
                                            <th>Preview</th>
                                            <th>Floor Name</th>
                                            <th>Project</th>
                                            <th>Level</th>
                                            <th>File Type</th>
                                            <th>Last Modified</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($floorPlans as $plan): ?>
                                            <tr>
                                                <td>
                                                    <?php
                                                    if (strpos($plan['file_type'], 'application/pdf') === 0) {
                                                        $previewPath = 'assets/images/pdf-icon.png';
                                                        $iconClass = 'pdf-preview';
                                                    } else {
                                                        $previewPath = !empty($plan['thumbnail_path']) ? 
                                                            $plan['thumbnail_path'] : 
                                                            $plan['file_path'];
                                                        $iconClass = 'image-preview';
                                                    }
                                                    ?>
                                                    <img src="<?php echo htmlspecialchars($previewPath); ?>" 
                                                         class="floor-plan-preview <?php echo $iconClass; ?>" 
                                                         alt="<?php echo htmlspecialchars($plan['floor_name']); ?>"
                                                         title="<?php echo htmlspecialchars($plan['original_filename']); ?>">
                                                </td>
                                                <td><?php echo htmlspecialchars($plan['floor_name']); ?></td>
                                                <td><?php echo htmlspecialchars($plan['project_name']); ?></td>
                                                <td><?php echo htmlspecialchars($plan['level']); ?></td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?php echo htmlspecialchars(explode('/', $plan['file_type'])[1] ?? 'Unknown'); ?>
                                                    </span>
                                                </td>
                                                <td data-sort="<?php echo strtotime($plan['last_modified']); ?>">
                                                    <?php echo date('Y-m-d H:i:s', strtotime($plan['last_modified'])); ?>
                                                </td>
                                                <td>
                                                    <button type="button" 
                                                            class="btn btn-sm btn-info" 
                                                            onclick="viewFloorPlan(<?php echo htmlspecialchars(json_encode($plan)); ?>)"
                                                            title="View floor plan">
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
        </div>
    </div>

    <!-- Mobile Sidebar Toggle Button -->
    <button id="mobileSidebarToggle" class="btn btn-dark d-lg-none position-fixed">
        <i class="bx bx-menu"></i>
    </button>

    <!-- View Floor Plan Modal -->
    <div class="modal fade" id="viewFloorPlanModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">View Floor Plan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Floor Name:</strong> <span id="viewFloorName"></span>
                        </div>
                        <div class="col-md-6">
                            <strong>Project:</strong> <span id="viewProjectName"></span>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong>Level:</strong> <span id="viewLevel"></span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <strong>Description:</strong>
                        <p id="viewDescription" class="mt-1"></p>
                    </div>
                    <div class="mb-3">
                        <strong>File Information:</strong>
                        <div id="viewFileInfo" class="mt-2"></div>
                    </div>
                    <div class="preview-section">
                        <div id="viewPreviewContainer" class="bg-light rounded p-3">
                            <!-- Preview content will be dynamically inserted here -->
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        /**
         * Floor Plans Management JavaScript
         * Current Date and Time (UTC): 2025-01-28 20:13:23
         * Current User's Login: irlam
         */

        // View Floor Plan Function
        function viewFloorPlan(plan) {
            // Set basic information
            document.getElementById('viewFloorName').textContent = plan.floor_name;
            document.getElementById('viewProjectName').textContent = plan.project_name;
            document.getElementById('viewLevel').textContent = plan.level;
            document.getElementById('viewDescription').textContent = plan.description || 'No description provided';

            // Update file information
            document.getElementById('viewFileInfo').innerHTML = `
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Original Filename:</strong> ${plan.original_filename}</p>
                                <p><strong>File Type:</strong> ${plan.file_type}</p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>File Size:</strong> ${(plan.file_size / 1024 / 1024).toFixed(2)} MB</p>
                                <p><strong>Version:</strong> ${plan.version}</p>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            const previewContainer = document.getElementById('viewPreviewContainer');
            previewContainer.innerHTML = ''; // Clear previous content

            if (plan.file_type.startsWith('application/pdf')) {
                // Create container for PDF
                previewContainer.innerHTML = `
                    <div class="pdf-preview-container">
                        <div class="pdf-controls mb-3">
                            <div class="btn-group">
                                <a href="${plan.file_path}" class="btn btn-sm btn-outline-primary" target="_blank">
                                    <i class="bx bx-fullscreen me-1"></i>Open in New Tab
                                </a>
                                <a href="${plan.file_path}" class="btn btn-sm btn-outline-secondary" download="${plan.original_filename}">
                                    <i class="bx bx-download me-1"></i>Download PDF
                                </a>
                            </div>
                        </div>
                        <div class="pdf-wrapper" style="height: 70vh; border: 1px solid #dee2e6; border-radius: 4px; overflow: hidden;">
                            <embed 
                                src="${plan.file_path}#toolbar=0&navpanes=0&scrollbar=0"
                                type="application/pdf"
                                width="100%"
                                height="100%"
                                style="border: none;">
                        </div>
                    </div>
                `;
            } else if (plan.file_type.startsWith('image/')) {
                // Create container for image
                previewContainer.innerHTML = `
                    <div class="image-preview-container text-center">
                        <div class="mb-3">
                            <div class="btn-group">
                                <a href="${plan.file_path}" class="btn btn-sm btn-outline-primary" target="_blank">
                                    <i class="bx bx-fullscreen me-1"></i>View Full Size
                                </a>
                                <a href="${plan.file_path}" class="btn btn-sm btn-outline-secondary" download="${plan.original_filename}">
                                    <i class="bx bx-download me-1"></i>Download Image
                                </a>
                            </div>
                        </div>
                        <div class="image-wrapper">
                            <img 
                                src="${plan.file_path}" 
                                class="img-fluid rounded shadow-sm" 
                                alt="${plan.floor_name}"
                                style="max-height: 70vh; width: auto; margin: 0 auto;">
                        </div>
                    </div>
                `;
            }

            // Show the modal
            const modal = new bootstrap.Modal(document.getElementById('viewFloorPlanModal'));
            modal.show();
        }

        // Initialize components when document is ready
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize DataTable
            $('#floorPlansTable').DataTable({
                order: [[5, 'desc']], // Sort by last modified by default
                responsive: true,
                pageLength: 10,
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search floor plans..."
                }
            });

            // Setup file upload handling
            setupFileUpload();
            
            // Setup form submissions
            setupFormSubmissions();
            
            // Setup mobile sidebar
            setupMobileSidebar();
        });

        // Setup file upload handling
        function setupFileUpload() {
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
                dropZone.addEventListener(eventName, () => dropZone.classList.add('dragover'), false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, () => dropZone.classList.remove('dragover'), false);
            });

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
                    if (file.size > 10485760) { // 10MB
                        showAlert('File size exceeds 10MB limit', 'danger');
                        resetFileInput();
                        return;
                    }

                    const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
                    if (!validTypes.includes(file.type)) {
                        showAlert('Invalid file type. Please upload JPG, PNG, GIF, or PDF', 'danger');
                        resetFileInput();
                        return;
                    }

                    if (file.type.startsWith('image/')) {
                        const img = new Image();
                        img.onload = function() {
                            preview.src = this.src;
                            preview.style.display = 'block';
                            URL.revokeObjectURL(this.src);
                        };
                        img.src = URL.createObjectURL(file);
                    } else {
                        preview.style.display = 'none';
                    }

                    fileInfo.innerHTML = `
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>File:</strong> ${file.name}<br>
                                <strong>Size:</strong> ${(file.size / 1024 / 1024).toFixed(2)}MB<br>
                                <strong>Type:</strong> ${file.type}
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="resetFileInput()">
                                <i class="bx bx-x"></i>Remove
                            </button>
                        </div>
                    `;
                    fileInfo.style.display = 'block';
                }
            }
        }

        // Setup form submissions
        function setupFormSubmissions() {
            const uploadForm = document.getElementById('uploadForm');

            uploadForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(uploadForm);
                submitFormData('api/upload_floor_plan.php', formData);
            });
        }

        // Submit form data via AJAX
        function submitFormData(url, formData) {
            fetch(url, {
                method: 'POST',
                body: formData,
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    setTimeout(() => location.reload(), 3000);
                } else {
                    showAlert(data.message, 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('An error occurred. Please try again.', 'danger');
            });
        }

        // Show alert message
        function showAlert(message, type) {
            const alertContainer = document.getElementById('alertContainer');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type} alert-dismissible fade show`;
            alert.innerHTML = `
                <strong>${type.charAt(0).toUpperCase() + type.slice(1)}!</strong> ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            alertContainer.appendChild(alert);
            setTimeout(() => alert.remove(), 5000);
        }

        // Setup mobile sidebar toggle
        function setupMobileSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const toggleButton = document.getElementById('mobileSidebarToggle');

            toggleButton.addEventListener('click', () => {
                sidebar.classList.toggle('show');
            });

            document.addEventListener('click', (event) => {
                if (!sidebar.contains(event.target) && !toggleButton.contains(event.target)) {
                    sidebar.classList.remove('show');
                }
            });
        }

        // Reset form function
        function resetForm() {
            document.getElementById('uploadForm').reset();
            resetFileInput();
        }

        // Reset file input function
        function resetFileInput() {
            const fileInput = document.getElementById('floor_plan');
            const preview = document.getElementById('preview');
            const fileInfo = document.getElementById('fileInfo');
            
            fileInput.value = '';
            preview.style.display = 'none';
            fileInfo.style.display = 'none';
            fileInfo.innerHTML = '';
        }
    </script>
</body>
</html>
<?php
declare(strict_types=1);

/**
 * create_defect.php
 * Create Defect Page
 * Current Date and Time (UTC): 2025-01-28 21:34:23
 * Current User's Login: irlam
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');
// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Verify user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Initialize variables
$userId = (int)$_SESSION['user_id'];
$errors = [];
$success = false;

try {
    $database = new Database();
    $db = $database->getConnection();

    // Fetch active projects
    $projectStmt = $db->prepare("
        SELECT id, name 
        FROM projects 
        WHERE status = 'active' 
        AND is_active = 1
        ORDER BY name ASC
    ");
    $projectStmt->execute();
    $projects = $projectStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch contractors
    $contractorStmt = $db->prepare("
        SELECT 
            c.id,
            c.company_name,
            c.trade
        FROM contractors c
        WHERE c.status = 'active'
        ORDER BY c.company_name ASC
    ");
    $contractorStmt->execute();
    $contractors = $contractorStmt->fetchAll(PDO::FETCH_ASSOC);

    // Process form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validate and sanitize inputs
        $projectId = filter_input(INPUT_POST, 'project_id', FILTER_VALIDATE_INT);
        $floorPlanId = filter_input(INPUT_POST, 'floor_plan_id', FILTER_VALIDATE_INT);
        $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING);
        $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
        $contractorId = filter_input(INPUT_POST, 'assigned_to', FILTER_VALIDATE_INT);
        $priority = filter_input(INPUT_POST, 'priority', FILTER_SANITIZE_STRING);
        
        // Validation
        if (!$projectId) {
            $errors[] = "Project selection is required";
        }
        if (!$floorPlanId) {
            $errors[] = "Floor plan selection is required";
        }
        if (empty($title)) {
            $errors[] = "Title is required";
        }
        if (empty($description)) {
            $errors[] = "Description is required";
        }
        if (!$contractorId) {
            $errors[] = "Contractor assignment is required";
        }
        if (empty($priority)) {
            $errors[] = "Priority selection is required";
        }

        // If no errors, create the defect
        if (empty($errors)) {
            $stmt = $db->prepare("
                INSERT INTO defects (
                    project_id,
                    floor_plan_id,
                    title,
                    description,
                    assigned_to,
                    priority,
                    status,
                    created_by,
                    created_at,
                    updated_at
                ) VALUES (
                    :project_id,
                    :floor_plan_id,
                    :title,
                    :description,
                    :assigned_to,
                    :priority,
                    'open',
                    :created_by,
                    UTC_TIMESTAMP(),
                    UTC_TIMESTAMP()
                )
            ");

            $stmt->execute([
                ':project_id' => $projectId,
                ':floor_plan_id' => $floorPlanId,
                ':title' => $title,
                ':description' => $description,
                ':assigned_to' => $contractorId,
                ':priority' => $priority,
                ':created_by' => $userId
            ]);

            $defectId = $db->lastInsertId();

            // Log the creation
            logAction($db, 'create_defect', $userId, [
                'defect_id' => $defectId,
                'project_id' => $projectId,
                'contractor_id' => $contractorId
            ]);

            $success = true;
            header("Location: view_defect.php?id=" . $defectId);
            exit;
        }
    }

} catch (Exception $e) {
    error_log($e->getMessage());
    $errors[] = "An error occurred while processing your request.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Defect - DVN Track</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/custom.css" rel="stylesheet">
</head>
<!-- After your existing CSS links, add this style block -->
    <style>
        /**
         * Inline styles for create_defect.php
         * Current Date and Time (UTC): 2025-01-28 21:42:55
         * Current User's Login: irlam
         */

        /* Form Container Styles */
        .create-defect-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        /* Form Section Styles */
        .form-section {
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        /* Form Group Spacing */
        .form-group {
            margin-bottom: 1.5rem;
        }

        /* Label Enhancements */
        .form-label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
            display: block;
        }

        /* Select2 Customization */
        .form-select {
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 0.625rem;
            width: 100%;
            transition: all 0.2s ease;
        }

        .form-select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-select:disabled {
            background-color: #f3f4f6;
            cursor: not-allowed;
        }

        /* Floor Plan Preview Styles */
        #floorPlanContainer {
            background-color: #f8fafc;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .card-header {
            background-color: #f8fafc;
            border-bottom: 1px solid #e5e7eb;
            padding: 1rem 1.5rem;
        }

        .card-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1f2937;
            margin: 0;
        }

        #openPdfButton {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
        }

        #openPdfButton:hover {
            background-color: #2563eb;
            color: #ffffff;
        }

        /* PDF Viewer Container */
        .pdf-container {
            background-color: #ffffff;
            padding: 1rem;
            min-height: 600px;
        }

        #pdfViewer {
            width: 100%;
            height: 600px;
            border: none;
            background-color: #ffffff;
        }

        /* Priority Selection Styling */
        #priority option[value="low"] { color: #16a34a; }
        #priority option[value="medium"] { color: #eab308; }
        #priority option[value="high"] { color: #ea580c; }
        #priority option[value="critical"] { color: #dc2626; }

        /* Loading Modal Customization */
        #loadingModal .modal-content {
            background-color: #ffffff;
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 
                       0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        #loadingModal .modal-body {
            padding: 2rem;
        }

        #loadingModal .spinner-border {
            width: 3rem;
            height: 3rem;
            color: #2563eb;
        }

        /* Form Validation Styles */
        .was-validated .form-control:invalid,
        .was-validated .form-select:invalid {
            border-color: #dc2626;
        }

        .was-validated .form-control:valid,
        .was-validated .form-select:valid {
            border-color: #16a34a;
        }

        .invalid-feedback {
            color: #dc2626;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        /* Button Group Styling */
        .btn-group {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .btn {
            padding: 0.625rem 1.25rem;
            font-weight: 500;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background-color: #2563eb;
            border-color: #2563eb;
        }

        .btn-primary:hover {
            background-color: #1d4ed8;
            border-color: #1d4ed8;
        }

        .btn-secondary {
            background-color: #6b7280;
            border-color: #6b7280;
        }

        .btn-secondary:hover {
            background-color: #4b5563;
            border-color: #4b5563;
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .create-defect-container {
                margin: 1rem auto;
            }

            .form-section {
                padding: 1rem;
            }

            #pdfViewer {
                height: 400px;
            }

            .btn-group {
                flex-direction: column;
                gap: 0.5rem;
            }
        }

        /* Dark Mode Support */
        @media (prefers-color-scheme: dark) {
            .form-section {
                background-color: #1f2937;
                border-color: #374151;
            }

            .form-label {
                color: #e5e7eb;
            }

            .form-select {
                background-color: #374151;
                border-color: #4b5563;
                color: #e5e7eb;
            }

            .form-select:disabled {
                background-color: #1f2937;
            }

            .card-header {
                background-color: #1f2937;
                border-color: #374151;
            }

            .card-title {
                color: #e5e7eb;
            }
        }
    </style>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h2>Create New Defect</h2>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form id="createDefectForm" method="POST" action="" class="needs-validation" novalidate>
                    <!-- Project Selection -->
                    <div class="mb-3">
                        <label for="project_id" class="form-label">Project</label>
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

                    <!-- Floor Plan Selection -->
                    <div class="mb-3">
                        <label for="floor_plan_id" class="form-label">Floor Plan</label>
                        <select class="form-select" id="floor_plan_id" name="floor_plan_id" required disabled>
                            <option value="">Select Floor Plan</option>
                        </select>
                        <div class="invalid-feedback">Please select a floor plan.</div>
                    </div>

                    <!-- Floor Plan Preview Container -->
                    <div id="floorPlanContainer" class="mb-3" style="display: none;">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Floor Plan Preview</h5>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="openPdfButton">
                                    <i class="bi bi-file-pdf"></i> Open PDF
                                </button>
                            </div>
                            <div class="card-body">
                                <div class="ratio ratio-16x9">
                                    <embed id="pdfViewer" src="" type="application/pdf" width="100%" height="600px" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Defect Title -->
                    <div class="mb-3">
                        <label for="title" class="form-label">Title</label>
                        <input type="text" class="form-control" id="title" name="title" required
                               placeholder="Enter defect title">
                        <div class="invalid-feedback">Please provide a title.</div>
                    </div>

                    <!-- Defect Description -->
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" required
                                  placeholder="Describe the defect"></textarea>
                        <div class="invalid-feedback">Please provide a description.</div>
                    </div>

                    <!-- Contractor Assignment -->
                    <div class="mb-3">
                        <label for="assigned_to" class="form-label">Assign To</label>
                        <select class="form-select" id="assigned_to" name="assigned_to" required>
                            <option value="">Select Contractor</option>
                            <?php foreach ($contractors as $contractor): ?>
                                <option value="<?php echo htmlspecialchars($contractor['id']); ?>"
                                        data-trade="<?php echo htmlspecialchars($contractor['trade']); ?>">
                                    <?php echo htmlspecialchars($contractor['company_name']); ?> - 
                                    <?php echo htmlspecialchars($contractor['trade']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Please select a contractor.</div>
                    </div>

                    <!-- Priority Selection -->
                    <div class="mb-3">
                        <label for="priority" class="form-label">Priority</label>
                        <select class="form-select" id="priority" name="priority" required>
                            <option value="">Select Priority</option>
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                        <div class="invalid-feedback">Please select a priority level.</div>
                    </div>

                    <!-- Submit Button -->
                    <div class="mb-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i> Create Defect
                        </button>
                        <a href="defects.php" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Loading Spinner Modal -->
    <div class="modal fade" id="loadingModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center py-4">
                    <div class="spinner-border text-primary mb-3" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <h5 class="modal-title">Processing...</h5>
                    <p class="text-muted mb-0">Please wait while we create your defect.</p>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    <!-- JavaScript Dependencies -->
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/sweetalert2.min.js"></script>

    <script>
        // Debug mode for development
        const DEBUG = <?php echo defined('DEBUG') && DEBUG ? 'true' : 'false'; ?>;
        const CURRENT_USER = '<?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>';
        const CURRENT_TIMESTAMP = '<?php echo date('Y-m-d H:i:s'); ?>';

        // Utility function for logging
        function log(message, data = null) {
            if (DEBUG) {
                console.log(
                    `[${new Date().toISOString()}] ${message}`, 
                    data ? data : ''
                );
            }
        }

        // Floor Plans Handling
        function fetchFloorPlans(projectId) {
            const floorPlanSelect = document.getElementById('floor_plan_id');
            const floorPlanContainer = document.getElementById('floorPlanContainer');
            
            // Reset and disable floor plan select
            floorPlanSelect.innerHTML = '<option value="">Select Floor Plan</option>';
            floorPlanSelect.disabled = true;
            floorPlanContainer.style.display = 'none';
            
            if (!projectId) {
                return;
            }

            // Show loading state
            floorPlanSelect.innerHTML = '<option value="">Loading floor plans...</option>';

            // Log the request
            log('Fetching floor plans for project', projectId);

            // Fetch floor plans
            fetch(`api/get_floor_plans.php?project_id=${encodeURIComponent(projectId)}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    log('Floor plans response', data);

                    floorPlanSelect.innerHTML = '<option value="">Select Floor Plan</option>';
                    
                    if (data.status === 'success' && data.data && data.data.length > 0) {
                        data.data.forEach(plan => {
                            const option = document.createElement('option');
                            option.value = plan.id;
                            option.textContent = plan.floor_name;
                            if (plan.level && plan.level !== plan.floor_name) {
                                option.textContent += ` (${plan.level})`;
                            }
                            option.setAttribute('data-file-path', plan.file_path);
                            floorPlanSelect.appendChild(option);
                        });
                        
                        floorPlanSelect.disabled = false;
                        log('Floor plans loaded', {
                            count: data.data.length,
                            projectId: projectId
                        });
                    } else {
                        floorPlanSelect.innerHTML = 
                            '<option value="">No floor plans available for this project</option>';
                        log('No floor plans found', { projectId: projectId });
                    }
                })
                .catch(error => {
                    console.error('Error fetching floor plans:', error);
                    floorPlanSelect.innerHTML = 
                        '<option value="">Error loading floor plans</option>';
                    log('Error fetching floor plans', {
                        projectId: projectId,
                        error: error.message
                    });
                });
        }

        // Floor plan selection handler
        document.getElementById('floor_plan_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const floorPlanContainer = document.getElementById('floorPlanContainer');
            const pdfViewer = document.getElementById('pdfViewer');
            
            if (!selectedOption.value) {
                floorPlanContainer.style.display = 'none';
                return;
            }

            // Set PDF viewer source
            pdfViewer.src = `api/get_pdf_preview.php?id=${selectedOption.value}`;
            floorPlanContainer.style.display = 'block';
            
            log('Floor plan selected', {
                id: selectedOption.value,
                name: selectedOption.text
            });
        });

        // Open PDF in new tab
        document.getElementById('openPdfButton').addEventListener('click', function() {
            const floorPlanSelect = document.getElementById('floor_plan_id');
            const selectedOption = floorPlanSelect.options[floorPlanSelect.selectedIndex];
            
            if (selectedOption.value) {
                window.open(`api/get_pdf_preview.php?id=${selectedOption.value}`, '_blank');
                log('PDF opened in new tab', {
                    id: selectedOption.value,
                    name: selectedOption.text
                });
            }
        });

        // Project selection handler
        document.getElementById('project_id').addEventListener('change', function() {
            log('Project changed', {
                id: this.value,
                name: this.options[this.selectedIndex].text
            });
            fetchFloorPlans(this.value);
        });

        // Form submission handler
        document.getElementById('createDefectForm').addEventListener('submit', function(event) {
            if (!this.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            } else {
                const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
                loadingModal.show();
                
                log('Form submitted', {
                    projectId: document.getElementById('project_id').value,
                    floorPlanId: document.getElementById('floor_plan_id').value,
                    title: document.getElementById('title').value,
                    contractorId: document.getElementById('assigned_to').value,
                    priority: document.getElementById('priority').value,
                    timestamp: new Date().toISOString()
                });
            }
            
            this.classList.add('was-validated');
        });

        // Initialize if project is already selected
        document.addEventListener('DOMContentLoaded', function() {
            const projectSelect = document.getElementById('project_id');
            if (projectSelect.value) {
                log('Initial project load', {
                    id: projectSelect.value,
                    name: projectSelect.options[projectSelect.selectedIndex].text
                });
                fetchFloorPlans(projectSelect.value);
            }

            // Enable Bootstrap tooltips
            const tooltipTriggerList = [].slice.call(
                document.querySelectorAll('[data-bs-toggle="tooltip"]')
            );
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>
</html>
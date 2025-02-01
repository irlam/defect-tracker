<?php
/**
 * create_defect.php
 * Create Defect Page with Pin Location Feature
 * Current Date and Time (UTC): 2025-01-30 18:35:20
 * Current User's Login: irlam
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/database.php';
require_once 'config/constants.php'; // Add constants file
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

class DefectImageGenerator {
    private $defectId;
    private $db;

    public function __construct(int $defectId) {
        $this->defectId = $defectId;
        $database = new Database();
        $this->db = $database->getConnection();
    }

    public function getFloorPlanPath(int $floorPlanId): string {
        $stmt = $this->db->prepare("SELECT file_path FROM floor_plans WHERE id = :floor_plan_id");
        $stmt->execute([':floor_plan_id' => $floorPlanId]);
        $floorPlan = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$floorPlan) {
            throw new Exception("Floor plan not found: " . $floorPlanId);
        }

        return $floorPlan['file_path'];
    }

    public function generate(int $floorPlanId): string {
        // Fetch the floor plan path using the provided ID
        $floorPlanPath = $this->getFloorPlanPath($floorPlanId);

        // Generate the defect image using the floor plan path
        // Method implementation to generate the image
        // For example, generating an image with a pin location
        $imagePath = 'path/to/generated/image.png'; // Replace with actual implementation

        return $imagePath;
    }
}

$userId = (int)$_SESSION['user_id'];
$errors = [];
$success = false;

try {
    $database = new Database();
    $db = $database->getConnection();

    // Get active projects
    $projectStmt = $db->prepare("SELECT id, name FROM projects WHERE status = 'active' AND is_active = 1 ORDER BY name ASC");
    $projectStmt->execute();
    $projects = $projectStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get active contractors
    $contractorStmt = $db->prepare("SELECT id, company_name, trade FROM contractors WHERE status = 'active' ORDER BY company_name ASC");
    $contractorStmt->execute();
    $contractors = $contractorStmt->fetchAll(PDO::FETCH_ASSOC);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validate and sanitize input
        $projectId = filter_input(INPUT_POST, 'project_id', FILTER_VALIDATE_INT);
        $floorPlanId = filter_input(INPUT_POST, 'floor_plan_id', FILTER_VALIDATE_INT);
        $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING);
        $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
        $assignedTo = filter_input(INPUT_POST, 'assigned_to', FILTER_VALIDATE_INT);
        $priority = filter_input(INPUT_POST, 'priority', FILTER_SANITIZE_STRING);
        $pinX = filter_input(INPUT_POST, 'pin_x', FILTER_VALIDATE_FLOAT);
        $pinY = filter_input(INPUT_POST, 'pin_y', FILTER_VALIDATE_FLOAT);
        $reportedBy = $userId;

        // Validate required fields
        if (!$projectId) $errors[] = "Project selection is required";
        if (!$floorPlanId) $errors[] = "Floor plan selection is required";
        if (empty($title)) $errors[] = "Title is required";
        if (empty($description)) $errors[] = "Description is required";
        if (!$assignedTo) $errors[] = "Contractor assignment is required";
        if (empty($priority)) $errors[] = "Priority selection is required";
        if (!isset($pinX) || !isset($pinY)) $errors[] = "Pin location is required";

        // Validate contractor exists and is active
        $assignedToStmt = $db->prepare("SELECT id FROM contractors WHERE id = :id AND status = 'active'");
        $assignedToStmt->execute([':id' => $assignedTo]);
        if ($assignedToStmt->rowCount() == 0) {
            $errors[] = "Invalid contractor assignment";
        }

        if (empty($errors)) {
            try {
                $db->beginTransaction();

                // Insert defect
                $stmt = $db->prepare("
                    INSERT INTO defects (
                        project_id, floor_plan_id, title, description,
                        reported_by, assigned_to, priority, status,
                        pin_x, pin_y, created_by, created_at,
                        updated_at, x_coordinate, y_coordinate
                    ) VALUES (
                        :project_id, :floor_plan_id, :title, :description,
                        :reported_by, :assigned_to, :priority, 'open',
                        :pin_x, :pin_y, :created_by, UTC_TIMESTAMP(),
                        UTC_TIMESTAMP(), :pin_x, :pin_y
                    )
                ");

                $stmt->execute([
                    ':project_id' => $projectId,
                    ':floor_plan_id' => $floorPlanId,
                    ':title' => $title,
                    ':description' => $description,
                    ':reported_by' => $reportedBy,
                    ':assigned_to' => $assignedTo,
                    ':priority' => $priority,
                    ':pin_x' => $pinX,
                    ':pin_y' => $pinY,
                    ':created_by' => $userId
                ]);

                $defectId = (int)$db->lastInsertId();
                error_log("Defect ID after insertion: " . $defectId);

                if ($defectId > 0) {
                    // Commit the transaction before generating the image
                    $db->commit();

                    // Create a folder for this defect
$defectFolder = UPLOAD_BASE_DIR . '/defect_' . $defectId . '/';
if (!file_exists($defectFolder)) {
    mkdir($defectFolder, 0755, true);
}

                    // Handle image uploads
$uploadedImages = [];
if (!empty($_FILES['images']['name'][0])) {
    foreach ($_FILES['images']['name'] as $i => $fileName) {
        $tmpName = $_FILES['images']['tmp_name'][$i];
        $safeFileName = date('YmdHis') . '_' . uniqid() . '_' . basename($fileName);
        $targetFilePath = $defectFolder . $safeFileName;

        if (move_uploaded_file($tmpName, $targetFilePath)) {
            $uploadedImages[] = $targetFilePath;
        } else {
            $errors[] = "Failed to upload image: " . basename($fileName);
        }
    }
}

                    // Generate pin image
                    $imageGenerator = new DefectImageGenerator($defectId);
                    $filename = $imageGenerator->generate($floorPlanId);

                    // Ensure the filename is clean and construct paths
                    $safeFilename = basename($filename);
                    $dbImagePath = $defectFolder . $safeFilename;
                    $fullFilePath = $fullDefectFolderPath . $safeFilename;

                    // Move the generated file if needed
                    if (file_exists($filename) && $filename !== $fullFilePath) {
                        rename($filename, $fullFilePath);
                    }

                    // Update defect with pin image path and attachment paths
                    $stmt = $db->prepare("UPDATE defects SET pin_image_path = :pin_image_path, attachment_paths = :attachment_paths WHERE id = :id");
                    $stmt->execute([
                        ':pin_image_path' => $dbImagePath,
                        ':attachment_paths' => json_encode($uploadedImages),
                        ':id' => $defectId
                    ]);

                    // Log action
                    logAction($db, 'create_defect', $userId, [
                        'defect_id' => $defectId,
                        'project_id' => $projectId,
                        'reported_by' => $reportedBy,
                        'assigned_to' => $assignedTo,
                        'pin_image' => $dbImagePath,
                        'uploaded_images' => $uploadedImages
                    ]);

                    $_SESSION['success_message'] = "Defect created successfully with pin location image.";
                    header("Location: view_defect.php?id=" . $defectId);
                    exit;
                } else {
                    throw new Exception("Defect not found after insertion.");
                }

            } catch (Exception $e) {
                $db->rollBack();
                error_log("Error in defect creation: " . $e->getMessage());
                $errors[] = "An error occurred while saving the defect. Please try again.";
            }
        }
    }

} catch (Exception $e) {
    error_log($e->getMessage());
    $errors[] = "An error occurred while processing your request.";
}

// Include the HTML directly for the form and view instead of using a template

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Defect - DVN Track</title>
    
    <!-- CSS Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.8.1/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.4.16/dist/sweetalert2.min.css" rel="stylesheet">
    
    <!-- PDF.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script>
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    </script>

    <style>
        /* Container Styles */
        .main-content {
            padding: 2rem;
            min-height: calc(100vh - 60px);
        }

        /* PDF Container Styles */
        .pdf-container {
            position: relative;
            width: 100%;
            height: 600px;
            overflow: hidden;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            background-color: #f8f9fa;
            margin-bottom: 1rem;
        }

        /* Canvas Styles */
        .pdf-canvas {
            position: absolute;
            top: 0;
            left: 0;
            touch-action: none;
        }

        /* Pin Overlay Styles */
        .pin-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            cursor: crosshair;
            touch-action: none;
            z-index: 2;
        }

        /* Pin Marker Styles */
        .location-pin {
            position: absolute;
            width: 24px;
            height: 24px;
            background-color: #dc3545;
            border: 2px solid #fff;
            border-radius: 50%;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.5);
            transform: translate(-50%, -50%);
            pointer-events: none;
            z-index: 3;
            transition: all 0.2s ease;
        }

        .location-pin::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 8px;
            height: 8px;
            background-color: #fff;
            border-radius: 50%;
            transform: translate(-50%, -50%);
        }

        /* Image Preview Styles */
        #imagePreview {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }

        #imagePreview img {
            max-width: 100px;
            max-height: 100px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #dee2e6;
            transition: transform 0.2s ease;
        }

        #imagePreview img:hover {
            transform: scale(1.05);
        }

        /* Form Control Styles */
        .form-control:focus, 
        .form-select:focus {
            border-color: #86b7fe;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }

        /* Control Panel Styles */
        .control-panel {
            position: absolute;
            bottom: 1rem;
            right: 1rem;
            z-index: 4;
            background-color: rgba(255, 255, 255, 0.9);
            padding: 0.5rem;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        /* Loading Indicator Styles */
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 5;
        }

        /* Mobile Optimization */
        @media (max-width: 768px) {
            .pdf-container {
                height: 400px;
            }

            .location-pin {
                width: 20px;
                height: 20px;
            }

            .location-pin::after {
                width: 6px;
                height: 6px;
            }

            .control-panel {
                bottom: 0.5rem;
                right: 0.5rem;
            }

            #imagePreview img {
                max-width: 80px;
                max-height: 80px;
            }
        }

        /* Zoom Controls */
        .zoom-controls {
            position: absolute;
            top: 1rem;
            right: 1rem;
            z-index: 4;
            display: flex;
            gap: 0.5rem;
        }

        .zoom-controls button {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            border-radius: 4px;
            background-color: rgba(255, 255, 255, 0.9);
            border: 1px solid #dee2e6;
        }

        /* Toast Notifications */
        .toast-container {
            position: fixed;
            bottom: 1rem;
            right: 1rem;
            z-index: 1050;
        }

        /* Accessibility Improvements */
        .visually-hidden {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            border: 0;
        }

        /* Loading Modal Styles */
        .modal-loading {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 2rem;
        }

        .modal-loading .spinner-border {
            width: 3rem;
            height: 3rem;
            margin-bottom: 1rem;
        }

        /* Form Validation Styles */
        .was-validated .form-control:invalid,
        .was-validated .form-select:invalid {
            border-color: #dc3545;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right calc(0.375em + 0.1875rem) center;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
        }

        .was-validated .form-control:valid,
        .was-validated .form-select:valid {
            border-color: #198754;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%23198754' d='M2.3 6.73L.6 4.53c-.4-1.04.46-1.4 1.1-.8l1.1 1.4 3.4-3.8c.6-.63 1.6-.27 1.2.7l-4 4.6c-.43.5-.8.4-1.1.1z'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right calc(0.375em + 0.1875rem) center;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-md-10">
                <h2 class="mb-4 text-center">Create New Defect</h2>
                
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form id="createDefectForm" method="POST" action="" class="needs-validation" novalidate enctype="multipart/form-data">
                    <!-- Project Selection -->
                    <div class="mb-3">
                        <label for="project_id" class="form-label">Project <span class="text-danger">*</span></label>
                        <select class="form-select" id="project_id" name="project_id" required>
                            <option value="">Select Project</option>
                            <?php foreach ($projects as $project): ?>
                                <option value="<?php echo htmlspecialchars((string)$project['id']); ?>">
                                    <?php echo htmlspecialchars($project['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Please select a project.</div>
                    </div>

                    <!-- Floor Plan Selection -->
                    <div class="mb-3">
                        <label for="floor_plan_id" class="form-label">Floor Plan <span class="text-danger">*</span></label>
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
                                <div class="btn-group">
                                    <button type="button" class="btn btn-sm btn-outline-danger" id="clearPinButton">
                                        <i class="bi bi-x-circle"></i> Clear Pin
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-primary ms-2" id="openPdfButton">
                                        <i class="bi bi-file-pdf"></i> Open PDF
                                    </button>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div class="alert alert-info m-3">
                                    <i class="bi bi-info-circle"></i> Click or tap on the floor plan to place a pin marking the defect location.
                                </div>
                                <div class="pdf-container">
                                    <canvas id="pdfCanvas" class="pdf-canvas"></canvas>
                                    <div id="pinOverlay" class="pin-overlay"></div>
                                    
                                    <!-- Zoom Controls -->
                                    <div class="zoom-controls">
                                        <button type="button" class="btn btn-light btn-sm" id="zoomInButton">
                                            <i class="bi bi-zoom-in"></i>
                                        </button>
                                        <button type="button" class="btn btn-light btn-sm" id="zoomOutButton">
                                            <i class="bi bi-zoom-out"></i>
                                        </button>
                                        <button type="button" class="btn btn-light btn-sm" id="resetZoomButton">
                                            <i class="bi bi-arrows-angle-contract"></i>
                                        </button>
                                    </div>

                                    <!-- Loading Overlay -->
                                    <div id="pdfLoadingOverlay" class="loading-overlay" style="display: none;">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Hidden Pin Coordinates -->
                    <input type="hidden" id="pin_x" name="pin_x" value="">
                    <input type="hidden" id="pin_y" name="pin_y" value="">

                    <!-- Defect Title -->
                    <div class="mb-3">
                        <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="title" name="title" required
                               placeholder="Enter defect title" maxlength="255">
                        <div class="invalid-feedback">Please provide a title.</div>
                    </div>

                    <!-- Defect Description -->
                    <div class="mb-3">
                        <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="description" name="description" rows="3" required
                                  placeholder="Describe the defect in detail" maxlength="1000"></textarea>
                        <div class="invalid-feedback">Please provide a description.</div>
                        <div class="form-text">Maximum 1000 characters</div>
                    </div>

                    <!-- Contractor Assignment -->
                    <div class="mb-3">
                        <label for="assigned_to" class="form-label">Assign To <span class="text-danger">*</span></label>
                        <select class="form-select" id="assigned_to" name="assigned_to" required>
                            <option value="">Select Contractor</option>
                            <?php foreach ($contractors as $contractor): ?>
                                <option value="<?php echo htmlspecialchars((string)$contractor['id']); ?>"
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
                        <label for="priority" class="form-label">Priority <span class="text-danger">*</span></label>
                        <select class="form-select" id="priority" name="priority" required>
                            <option value="">Select Priority</option>
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                        <div class="invalid-feedback">Please select a priority level.</div>
                    </div>

                    <!-- Image Upload -->
                    <div class="mb-3">
                        <label for="images" class="form-label">Upload Images <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="images" name="images[]" accept="image/*" multiple required>
                        <div class="invalid-feedback">Please upload at least one image (max 5 images).</div>
                        <div class="form-text">Maximum 5 images, each up to 5MB (JPG, PNG only)</div>
                        <div id="imagePreview" class="mt-2"></div>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="d-flex justify-content-between mt-4">
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

    <!-- Loading Modal -->
    <div class="modal fade" id="loadingModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-loading">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <h5 class="modal-title">Processing...</h5>
                    <p class="text-muted mb-0">Please wait while we create your defect.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Pin Warning Modal -->
    <div class="modal fade" id="pinWarningModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Pin Location Required</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Please place a pin on the floor plan to mark the defect location before submitting.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    <!-- JavaScript Dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.4.16/dist/sweetalert2.min.js"></script>
    <script>
        /**
         * create_defect.js
         * JavaScript for Create Defect Page
         * Current Date and Time (UTC): 2025-01-30 14:59:07
         * Current User's Login: irlam
         */

        // Debug mode and constants
        const DEBUG = <?php echo defined('DEBUG') && DEBUG ? 'true' : 'false'; ?>;
        const CURRENT_USER = '<?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>';
        const CURRENT_TIMESTAMP = '<?php echo date('Y-m-d H:i:s'); ?>';

        // PDF.js variables
        let pdfDoc = null;
        let currentPage = 1;
        let scale = 1.5;
        let canvas = document.getElementById('pdfCanvas');
        let context = canvas.getContext('2d');
        let currentPin = null;
        let pinPlaced = false;
        let pdfViewport = null;
        let initialScale = null;

        // Utility function for logging
        function log(message, data = null) {
            if (DEBUG) {
                console.log(
                    `[${new Date().toISOString()}] [${CURRENT_USER}] ${message}`, 
                    data ? data : ''
                );
            }
        }

        // PDF handling functions
        async function loadPDF(url) {
            try {
                // Show loading overlay
                document.getElementById('pdfLoadingOverlay').style.display = 'flex';
                
                // Load the PDF
                const loadingTask = pdfjsLib.getDocument(url);
                pdfDoc = await loadingTask.promise;
                
                // Render first page
                await renderPage(currentPage);
                
                // Set initial scale
                initialScale = scale;
                
                log('PDF loaded successfully', { url });
            } catch (error) {
                console.error('Error loading PDF:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to load floor plan PDF. Please try again.',
                });
            } finally {
                // Hide loading overlay
                document.getElementById('pdfLoadingOverlay').style.display = 'none';
            }
        }

        async function renderPage(pageNumber) {
            try {
                // Get the page
                const page = await pdfDoc.getPage(pageNumber);
                
                // Calculate scale to fit container width
                const container = document.querySelector('.pdf-container');
                const containerWidth = container.clientWidth;
                const viewport = page.getViewport({ scale: 1 });
                
                if (!initialScale) {
                    scale = containerWidth / viewport.width;
                }
                
                // Update viewport with current scale
                pdfViewport = page.getViewport({ scale });
                
                // Set canvas dimensions
                canvas.height = pdfViewport.height;
                canvas.width = pdfViewport.width;
                
                // Update container height to match aspect ratio
                container.style.height = `${pdfViewport.height}px`;
                
                // Render PDF page
                const renderContext = {
                    canvasContext: context,
                    viewport: pdfViewport
                };
                
                await page.render(renderContext).promise;
                
                // Update overlay dimensions
                const pinOverlay = document.getElementById('pinOverlay');
                pinOverlay.style.width = `${canvas.width}px`;
                pinOverlay.style.height = `${canvas.height}px`;
                
                // Reposition pin if it exists
                if (currentPin) {
                    updatePinPosition();
                }
                
                log('PDF page rendered', {
                    pageNumber,
                    scale,
                    dimensions: {
                        width: canvas.width,
                        height: canvas.height
                    }
                });
            } catch (error) {
                console.error('Error rendering PDF:', error);
                throw error;
            }
        }

        // Pin handling functions
        function calculatePinCoordinates(event) {
            const overlay = document.getElementById('pinOverlay');
            const rect = overlay.getBoundingClientRect();
            
            // Get touch or mouse coordinates
            const clientX = event.touches ? event.touches[0].clientX : event.clientX;
            const clientY = event.touches ? event.touches[0].clientY : event.clientY;
            
            // Calculate relative coordinates
            const x = (clientX - rect.left) / rect.width;
            const y = (clientY - rect.top) / rect.height;
            
            return { x, y };
        }

        function createPin(x, y) {
            const pin = document.createElement('div');
            pin.className = 'location-pin';
            pin.style.left = (x * 100) + '%';
            pin.style.top = (y * 100) + '%';
            return pin;
        }

        function updatePinPosition() {
            if (currentPin && document.getElementById('pin_x').value && document.getElementById('pin_y').value) {
                const x = parseFloat(document.getElementById('pin_x').value);
                const y = parseFloat(document.getElementById('pin_y').value);
                currentPin.style.left = (x * 100) + '%';
                currentPin.style.top = (y * 100) + '%';
            }
        }

        function handlePinPlacement(event) {
            event.preventDefault();
            
            // Calculate coordinates
            const { x, y } = calculatePinCoordinates(event);
            
            // Remove existing pin
            if (currentPin) {
                currentPin.remove();
            }
            
            // Create and position new pin
            currentPin = createPin(x, y);
            document.getElementById('pinOverlay').appendChild(currentPin);
            pinPlaced = true;
            
            // Store coordinates
            document.getElementById('pin_x').value = x.toFixed(6);
            document.getElementById('pin_y').value = y.toFixed(6);
            
            log('Pin placed', { x, y });
            
            // Show success toast
            Swal.fire({
                icon: 'success',
                title: 'Pin Placed',
                text: 'Location marked successfully',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000
            });
        }

        // Zoom control handlers
        document.getElementById('zoomInButton').addEventListener('click', async () => {
            scale *= 1.2;
            await renderPage(currentPage);
        });

        document.getElementById('zoomOutButton').addEventListener('click', async () => {
            scale *= 0.8;
            await renderPage(currentPage);
        });

        document.getElementById('resetZoomButton').addEventListener('click', async () => {
            scale = initialScale;
            await renderPage(currentPage);
        });

        // Clear pin handler
        document.getElementById('clearPinButton').addEventListener('click', function() {
            if (currentPin) {
                currentPin.remove();
                currentPin = null;
                pinPlaced = false;
                document.getElementById('pin_x').value = '';
                document.getElementById('pin_y').value = '';
                
                log('Pin cleared');
                
                Swal.fire({
                    icon: 'info',
                    title: 'Pin Cleared',
                    text: 'Location marker has been removed',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000
                });
            }
        });

        // Floor plan selection handler
        document.getElementById('floor_plan_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const floorPlanContainer = document.getElementById('floorPlanContainer');
            
            if (!selectedOption.value) {
                floorPlanContainer.style.display = 'none';
                return;
            }
            
            const pdfUrl = selectedOption.getAttribute('data-file-path');
            floorPlanContainer.style.display = 'block';
            
            // Load PDF
            loadPDF(pdfUrl);
            
            // Clear existing pin
            if (currentPin) {
                currentPin.remove();
                currentPin = null;
                pinPlaced = false;
                document.getElementById('pin_x').value = '';
                document.getElementById('pin_y').value = '';
            }
        });

        // Project selection handler
        document.getElementById('project_id').addEventListener('change', async function() {
            log('Project changed', {
                id: this.value,
                name: this.options[this.selectedIndex].text
            });

            const floorPlanSelect = document.getElementById('floor_plan_id');
            const floorPlanContainer = document.getElementById('floorPlanContainer');
            
            try {
                floorPlanSelect.innerHTML = '<option value="">Loading floor plans...</option>';
                floorPlanSelect.disabled = true;
                floorPlanContainer.style.display = 'none';

                if (!this.value) {
                    floorPlanSelect.innerHTML = '<option value="">Select Floor Plan</option>';
                    return;
                }

                const response = await fetch(`api/get_floor_plans.php?project_id=${encodeURIComponent(this.value)}`);
                const data = await response.json();

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
                } else {
                    floorPlanSelect.innerHTML = '<option value="">No floor plans available</option>';
                }
            } catch (error) {
                console.error('Error fetching floor plans:', error);
                floorPlanSelect.innerHTML = '<option value="">Error loading floor plans</option>';
                
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to load floor plans. Please try again.',
                });
            }
        });

        // Image preview handler
        document.getElementById('images').addEventListener('change', function() {
            const imagePreview = document.getElementById('imagePreview');
            imagePreview.innerHTML = '';
            
            if (this.files.length > 5) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Maximum 5 images allowed',
                });
                this.value = '';
                return;
            }

            Array.from(this.files).forEach(file => {
                if (file.size > 5 * 1024 * 1024) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: `Image ${file.name} exceeds 5MB limit`,
                    });
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    imagePreview.appendChild(img);
                };
                reader.readAsDataURL(file);
            });
        });

        // Form submission handler
        document.getElementById('createDefectForm').addEventListener('submit', async function(event) {
            event.preventDefault();
            
            // Check if pin has been placed
            if (!pinPlaced) {
                const pinWarningModal = new bootstrap.Modal(document.getElementById('pinWarningModal'));
                pinWarningModal.show();
                return;
            }

            // Validate form
            if (!this.checkValidity()) {
                event.stopPropagation();
                this.classList.add('was-validated');
                return;
            }

            try {
                // Show loading modal
                const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
                loadingModal.show();

                // Log form submission
                log('Form submitted', {
                    projectId: document.getElementById('project_id').value,
                    floorPlanId: document.getElementById('floor_plan_id').value,
                    title: document.getElementById('title').value,
                    contractorId: document.getElementById('assigned_to').value,
                    priority: document.getElementById('priority').value,
                    pinX: document.getElementById('pin_x').value,
                    pinY: document.getElementById('pin_y').value,
                    timestamp: new Date().toISOString()
                });

                // Submit the form
                this.submit();

            } catch (error) {
                console.error('Error submitting form:', error);
                loadingModal.hide();
                
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'An error occurred while saving the defect. Please try again.',
                });
            }
        });

        // Initialize pin placement
        const pinOverlay = document.getElementById('pinOverlay');
        pinOverlay.addEventListener('click', handlePinPlacement);
        pinOverlay.addEventListener('touchstart', handlePinPlacement, { passive: false });

        // Initialize if project is already selected
        document.addEventListener('DOMContentLoaded', function() {
            const projectSelect = document.getElementById('project_id');
            if (projectSelect.value) {
                log('Initial project load', {
                    id: projectSelect.value,
                    name: projectSelect.options[projectSelect.selectedIndex].text
                });
                projectSelect.dispatchEvent(new Event('change'));
            }
        });

        // Window resize handler
        let resizeTimeout;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(async function() {
                if (pdfDoc) {
                    await renderPage(currentPage);
                }
            }, 250);
        });
    </script>
</body>
</html>
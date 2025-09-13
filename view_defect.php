<?php
/**
 * view_defect.php
 * View and manage individual defect details
 * Current Date and Time (UTC): 2025-01-30 15:12:02
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
require_once 'includes/upload_constants.php'; // Include upload constants

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? '';
$errors = [];
$success = false;

// Get defect ID from URL
$defectId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$defectId) {
    header('Location: defects.php');
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Fetch defect details and related information
    $stmt = $db->prepare("
        SELECT 
            d.*,
            p.name AS project_name,
            fp.floor_name,
            fp.level AS floor_level,
            fp.file_path AS floor_plan_path,
            c.company_name AS contractor_name,
            c.trade AS contractor_trade,
            u1.username AS reported_by_user,
            u2.username AS assigned_by_user,
            u3.username AS updated_by_user
        FROM defects d
        LEFT JOIN projects p ON d.project_id = p.id
        LEFT JOIN floor_plans fp ON d.floor_plan_id = fp.id
        LEFT JOIN contractors c ON d.assigned_to = c.id
        LEFT JOIN users u1 ON d.reported_by = u1.id
        LEFT JOIN users u2 ON d.created_by = u2.id
        LEFT JOIN users u3 ON d.updated_by = u3.id
        WHERE d.id = :id AND d.deleted_at IS NULL
    ");
    $stmt->execute([':id' => $defectId]);
    $defect = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$defect) {
        throw new Exception("Defect not found.");
    }

    // Format dates for display
    $defect['created_at'] = new DateTime($defect['created_at']);
    $defect['updated_at'] = new DateTime($defect['updated_at']);

    // Get defect images
    $attachmentPaths = json_decode($defect['attachment_paths'] ?? '[]', true);
    $images = [];
    foreach ($attachmentPaths as $path) {
        // Ensure the path is properly formatted for display
        $images[] = SITE_URL . '/' . ltrim($path, '/');
    }

    // Format pin image path
    if (!empty($defect['pin_image_path'])) {
        $defect['pin_image_url'] = SITE_URL . '/' . ltrim($defect['pin_image_path'], '/');
    }

    // Get defect history
    $historyStmt = $db->prepare("
        SELECT 
            dh.*,
            u.username AS updated_by_user
        FROM defect_history dh
        LEFT JOIN users u ON dh.updated_by = u.id
        WHERE dh.defect_id = :defect_id
        ORDER BY dh.created_at DESC
    ");
    $historyStmt->execute([':defect_id' => $defectId]);
    $history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

    // Format history dates
    foreach ($history as &$record) {
        $record['created_at'] = new DateTime($record['created_at']);
    }
    unset($record);

} catch (Exception $e) {
    error_log("Error in view_defect.php: " . $e->getMessage());
    $_SESSION['error_message'] = "An error occurred while retrieving the defect details.";
    header('Location: defects.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Defect - DVN Track</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .defect-images img {
            max-width: 200px;
            height: auto;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .defect-images img:hover {
            transform: scale(1.05);
        }
        .pin-image {
            max-width: 100%;
            height: auto;
            cursor: pointer;
        }
        .modal-dialog.modal-xl {
            max-width: 90%;
        }
        .badge {
            font-size: 0.9em;
        }
        .history-timeline {
            position: relative;
            padding-left: 30px;
        }
        .history-timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #dee2e6;
        }
        .history-item {
            position: relative;
            margin-bottom: 1.5rem;
        }
        .history-item::before {
            content: '';
            position: absolute;
            left: -30px;
            top: 0;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #0d6efd;
            border: 2px solid #fff;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
<br><br><br><br>
    <div class="container mt-4">
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php 
                echo htmlspecialchars($_SESSION['success_message']);
                unset($_SESSION['success_message']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Defect Details -->
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Defect #<?php echo htmlspecialchars((string)$defect['id']); ?></h5>
                        <span class="badge bg-<?php echo getStatusColor($defect['status']); ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $defect['status'])); ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <h4><?php echo htmlspecialchars($defect['title']); ?></h4>
                        <p class="text-muted mb-4">
                            <strong>Project:</strong> <?php echo htmlspecialchars($defect['project_name']); ?><br>
                            <strong>Floor:</strong> <?php echo htmlspecialchars($defect['floor_name']); ?> 
                            <?php if ($defect['floor_level']): ?>
                                (Level <?php echo htmlspecialchars($defect['floor_level']); ?>)
                            <?php endif; ?>
                        </p>

                        <h6>Description</h6>
                        <p class="mb-4"><?php echo nl2br(htmlspecialchars($defect['description'])); ?></p>

                        <!-- Pin Location Image -->
                        <?php if (!empty($defect['pin_image_url'])): ?>
                            <h6>Location</h6>
                            <div class="mb-4">
                                <img src="<?php echo htmlspecialchars($defect['pin_image_url']); ?>" 
                                     alt="Pin Location" 
                                     class="pin-image img-thumbnail"
                                     onclick="openImageModal(this.src, 'Pin Location')">
                            </div>
                        <?php endif; ?>

                        <!-- Defect Images -->
                        <?php if (!empty($images)): ?>
                            <h6>Images</h6>
                            <div class="defect-images mb-4">
                                <div class="row g-3">
                                    <?php foreach ($images as $index => $image): ?>
                                        <div class="col-6 col-md-4 col-lg-3">
                                            <img src="<?php echo htmlspecialchars($image); ?>" 
                                                 alt="Defect Image <?php echo $index + 1; ?>"
                                                 class="img-thumbnail"
                                                 onclick="openImageModal(this.src, 'Defect Image <?php echo $index + 1; ?>')">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Additional Details -->
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Status Details</h6>
                                <p class="mb-3">
                                    <strong>Priority:</strong> 
                                    <span class="badge bg-<?php echo getPriorityColor($defect['priority']); ?>">
                                        <?php echo ucfirst($defect['priority']); ?>
                                    </span><br>
                                    <strong>Assigned To:</strong> 
                                    <?php echo htmlspecialchars($defect['contractor_name']); ?> 
                                    (<?php echo htmlspecialchars($defect['contractor_trade']); ?>)
                                </p>
                            </div>
                            <div class="col-md-6">
                                <h6>Timestamps</h6>
                                <p class="mb-3">
                                    <strong>Created:</strong> 
                                    <?php echo $defect['created_at']->format('d/m/Y H:i:s'); ?><br>
                                    <strong>Last Updated:</strong> 
                                    <?php echo $defect['updated_at']->format('d/m/Y H:i:s'); ?>
                                </p>
                            </div>
                        </div>

                        <!-- Rejection Details -->
                        <?php if ($defect['status'] === 'rejected' && !empty($defect['rejection_comment'])): ?>
                            <div class="alert alert-danger mt-3">
                                <h6 class="alert-heading">Rejection Details</h6>
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($defect['rejection_comment'])); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- History Timeline -->
                <?php if (!empty($history)): ?>
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">History</h5>
                        </div>
                        <div class="card-body">
                            <div class="history-timeline">
                                <?php foreach ($history as $record): ?>
                                    <div class="history-item">
                                        <p class="mb-1">
                                            <strong><?php echo $record['created_at']->format('d/m/Y H:i:s'); ?></strong>
                                        </p>
                                        <p class="mb-1">
                                            <?php echo htmlspecialchars($record['description']); ?>
                                        </p>
                                        <small class="text-muted">
                                            By <?php echo htmlspecialchars($record['updated_by_user']); ?>
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Actions Sidebar -->
            <div class="col-md-4">
                <?php if ($userRole === 'admin' || $userRole === 'manager'): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editDefectModal">
                                    <i class="fas fa-edit"></i> Update Status
                                </button>
                                <button type="button" class="btn btn-danger" onclick="confirmDelete(<?php echo $defectId; ?>)">
                                    <i class="fas fa-trash"></i> Delete Defect
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- QR Code -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Quick Access</h5>
                    </div>
                    <div class="card-body text-center">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php 
                            echo urlencode(SITE_URL . '/view_defect.php?id=' . $defectId);
                        ?>" alt="QR Code" class="img-fluid mb-2">
                        <p class="mb-0 small">Scan to view on mobile device</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Image Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="imageModalLabel"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalImage" src="" alt="" class="img-fluid">
                </div>
            </div>
        </div>
    </div>

    <?php if ($userRole === 'admin' || $userRole === 'manager'): ?>
        <!-- Edit Status Modal -->
        <div class="modal fade" id="editDefectModal" tabindex="-1" aria-hidden="true">
            <!-- ... (Edit modal content) ... -->
        </div>
    <?php endif; ?>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.4.16/dist/sweetalert2.min.js"></script>
    <script>
        /**
         * view_defect.js
         * JavaScript for View Defect Page
         * Current Date and Time (UTC): 2025-01-30 15:14:22
         * Current User's Login: irlam
         */

        // Debug mode and constants
        const DEBUG = <?php echo defined('DEBUG') && DEBUG ? 'true' : 'false'; ?>;
        const CURRENT_USER = '<?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>';
        const CURRENT_TIMESTAMP = '<?php echo date('Y-m-d H:i:s'); ?>';
        const SITE_URL = '<?php echo SITE_URL; ?>';

        // Image modal handling
        function openImageModal(src, title) {
            const modal = document.getElementById('imageModal');
            const modalImage = document.getElementById('modalImage');
            const modalTitle = document.getElementById('imageModalLabel');
            
            modalImage.src = src;
            modalTitle.textContent = title;
            
            new bootstrap.Modal(modal).show();
        }

        // Delete confirmation
        function confirmDelete(defectId) {
            Swal.fire({
                title: 'Are you sure?',
                text: "This will permanently delete this defect and all associated data.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    deleteDefect(defectId);
                }
            });
        }

        // Delete defect
        const editForm = document.getElementById('editDefectForm');
if (editForm) {
    editForm.addEventListener('submit', handleFormSubmit);
}

function handleFormSubmit(e) {
    Swal.push(e.target); // Focus on the input field

    try {
        const formData = new FormData(editForm);
        
        if (FormData.isNotEmpty(formData)) {
            // Ensure required fields are filled
            const closureImageDiv = document.getElementById('closureImageDiv');
            const rejectionCommentDiv = document.getElementById('rejectionCommentDiv');

            editForm.reset();
            
            const statusSelect = document.getElementById('status');
            if (statusSelect && statusSelect.value) {
                updateFormFields(statusSelect.value);
            }

            deleteDefect(formData.get('defect_id').toString());
        }
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: `Failed to process form submission. Please try again later.`,
            showConfirmButton: false
        });
    }
}

        // Status update handling
        document.addEventListener('DOMContentLoaded', function() {
            const statusSelect = document.getElementById('status');
            const closureImageDiv = document.getElementById('closureImageDiv');
            const rejectionCommentDiv = document.getElementById('rejectionCommentDiv');
            
            if (statusSelect) {
                statusSelect.addEventListener('change', function() {
                    updateFormFields(this.value);
                });
            }

            // Initialize form fields based on current status
            if (statusSelect && statusSelect.value) {
                updateFormFields(statusSelect.value);
            }

            // Handle form submission
            const editForm = document.getElementById('editDefectForm');
            if (editForm) {
                editForm.addEventListener('submit', handleFormSubmit);
            }
        });

        // Update form fields based on status
        function updateFormFields(status) {
            const closureImageDiv = document.getElementById('closureImageDiv');
            const rejectionCommentDiv = document.getElementById('rejectionCommentDiv');
            const closureImage = document.getElementById('closureImage');
            const rejectionComment = document.getElementById('rejectionComment');

            // Hide all conditional fields
            closureImageDiv.style.display = 'none';
            rejectionCommentDiv.style.display = 'none';
            closureImage.required = false;
            rejectionComment.required = false;

            // Show fields based on selected status
            if (status === 'closed') {
                closureImageDiv.style.display = 'block';
                closureImage.required = true;
            } else if (status === 'rejected') {
                rejectionCommentDiv.style.display = 'block';
                rejectionComment.required = true;
            }
        }

        // Delete defect
        function deleteDefect(defectId) {
            const formData = new FormData();
            formData.append('action', 'delete_defect');
            formData.append('defect_id', defectId);

            fetch('delete_defect.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Deleted!',
                        text: 'The defect has been deleted.',
                        showConfirmButton: false,
                        timer: 1500
                    }).then(() => {
                        window.location.href = 'defects.php';
                    });
                } else {
                    throw new Error(data.message || 'Failed to delete defect.');
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message || 'An error occurred while deleting the defect.',
                    showConfirmButton: false
                });
            });
        }
    </script>
</body>
</html>
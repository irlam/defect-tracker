<?php
declare(strict_types=1);
/**
 * view_defect_mytasks.php
 * View individual defect details for users' assigned tasks.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/database.php';
require_once 'config/constants.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/upload_constants.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId   = (int) $_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? '';
$errors   = [];

$defectId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$defectId) {
    header('Location: defects.php');
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();

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
    
    $defect['created_at'] = new DateTime($defect['created_at']);
    $defect['updated_at'] = new DateTime($defect['updated_at']);

    $imgStmt = $db->prepare("
        SELECT *
        FROM defect_images
        WHERE defect_id = :defect_id
        ORDER BY created_at ASC
    ");
    $imgStmt->execute([':defect_id' => $defectId]);
    $defectImages = $imgStmt->fetchAll(PDO::FETCH_ASSOC);

    // Build an array of image URLs:
    $images = [];
    foreach ($defectImages as $img) {
        // Use pin_path if available, else file_path.
        $path = !empty($img['pin_path']) ? $img['pin_path'] : $img['file_path'];
        $images[] = SITE_URL . '/' . ltrim($path, '/');
    }
    
    // Separate images into categories:
    $completedImages = array_filter($images, function($img) {
        return strpos(basename($img), 'complete_') !== false;
    });
    $defectImagesList = array_filter($images, function($img) {
        return strpos(basename($img), 'complete_') === false && basename($img) !== 'floorplan_with_pin_defect.png';
    });
    // Floorplan image is defined strictly as the image with filename 'floorplan_with_pin_defect.png'
    $floorplanImages = array_filter($images, function($img) {
        return basename($img) === 'floorplan_with_pin_defect.png';
    });

    // Handle pin image URL if available
    if (!empty($defect['pin_image_path'])) {
        $defect['pin_image_url'] = SITE_URL . '/' . ltrim($defect['pin_image_path'], '/');
    }

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

    foreach ($history as &$record) {
        $record['created_at'] = new DateTime($record['created_at']);
    }
    unset($record);
} catch (Exception $e) {
    error_log("Error in view_defect_mytasks.php: " . $e->getMessage());
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
        .back-btn {
            margin-top: 2rem;
        }
        .upload-form {
            margin-top: 2rem;
            padding: 1rem;
            border: 1px dashed #ccc;
            border-radius: 5px;
            background-color: #f8f9fa;
        }
        #imageModal .modal-body {
            overflow: hidden;
            cursor: grab;
        }
        #imageModal .zoomed {
            cursor: grabbing;
        }
        #imageModal img {
            transition: transform 0.3s ease;
            transform-origin: center center;
        }
    </style>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            window.openImageModal = function(imageSrc, imageAlt) {
                var modalImage = document.getElementById('modalImage');
                if (!modalImage) {
                    console.error("modalImage element not found.");
                    return;
                }
                modalImage.src = imageSrc;
                modalImage.alt = imageAlt;
                window.currentImageSrc = imageSrc;
                var imageModalLabel = document.getElementById('imageModalLabel');
                if (imageModalLabel) {
                    imageModalLabel.textContent = imageAlt;
                }
                var imageModalElement = document.getElementById('imageModal');
                var imageModal = new bootstrap.Modal(imageModalElement);
                imageModal.show();
                var checkbox = document.getElementById('selectImageCheckbox');
                if (checkbox) {
                    checkbox.checked = false;
                }
            }
        });
    </script>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="container mt-4">
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php
            echo htmlspecialchars((string)$_SESSION['success_message']);
            unset($_SESSION['success_message']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Defect #<?php echo htmlspecialchars((string)$defect['id']); ?></h5>
                    <span class="badge bg-<?php echo getStatusColor((string)$defect['status']); ?>">
                        <?php echo ucfirst(str_replace('_', ' ', (string)$defect['status'])); ?>
                    </span>
                </div>
                <div class="card-body">
                    <h4><?php echo htmlspecialchars((string)$defect['title']); ?></h4>
                    <p class="text-muted mb-4">
                        <strong>Project:</strong> <?php echo htmlspecialchars((string)$defect['project_name']); ?><br>
                        <strong>Floor:</strong> <?php echo htmlspecialchars((string)$defect['floor_name']); ?>
                        <?php if ($defect['floor_level']): ?>
                            (Level <?php echo htmlspecialchars((string)$defect['floor_level']); ?>)
                        <?php endif; ?>
                    </p>

                    <h6>Description</h6>
                    <p class="mb-4"><?php echo nl2br(htmlspecialchars((string)$defect['description'])); ?></p>

                    <!-- Section for Floorplan Image -->
                    <?php if (!empty($floorplanImages)): ?>
                        <h6>Floorplan Image</h6>
                        <div class="mb-4">
                            <?php foreach ($floorplanImages as $img): ?>
                                <img src="<?php echo htmlspecialchars($img); ?>" 
                                     alt="Floorplan Image"
                                     class="img-thumbnail"
                                     onclick="openImageModal(this.src, 'Floorplan Image')">
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Display Pin Location Image -->
                    <?php if (!empty($defect['pin_image_url'])): ?>
                        <h6>Defect Location (Pin Image)</h6>
                        <div class="mb-4">
                            <img src="<?php echo htmlspecialchars((string)$defect['pin_image_url']); ?>"
                                 alt="Pin Location"
                                 class="pin-image img-thumbnail"
                                 onclick="openImageModal(this.src, 'Pin Location')">
                        </div>
                    <?php endif; ?>

                    <!-- Display Completed Images -->
                    <?php if (!empty($completedImages)): ?>
                        <h6>Completed Images</h6>
                        <div class="defect-images mb-4">
                            <div class="row g-3">
                                <?php foreach ($completedImages as $index => $image): ?>
                                    <div class="col-6 col-md-4 col-lg-3">
                                        <img src="<?php echo htmlspecialchars((string)$image); ?>" 
                                             alt="Completed Image <?php echo (string)($index + 1); ?>" 
                                             class="img-thumbnail" 
                                             onclick="openImageModal(this.src, 'Completed Image <?php echo (string)($index + 1); ?>')">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Display Defect Images -->
                    <?php if (!empty($defectImagesList)): ?>
                        <h6>Defect Images</h6>
                        <div class="defect-images mb-4">
                            <div class="row g-3">
                                <?php foreach ($defectImagesList as $index => $image): ?>
                                    <div class="col-6 col-md-4 col-lg-3">
                                        <img src="<?php echo htmlspecialchars((string)$image); ?>" 
                                             alt="Defect Image <?php echo (string)($index + 1); ?>" 
                                             class="img-thumbnail" 
                                             onclick="openImageModal(this.src, 'Defect Image <?php echo (string)($index + 1); ?>')">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Form to Add Completed Images -->
                    <div class="upload-form">
                        <h6>Add Completed Images</h6>
                        <form action="upload_completed_images.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="defect_id" value="<?php echo htmlspecialchars((string)$defectId); ?>">
                            <div class="mb-3">
                                <label for="completed_images" class="form-label">Select Images (you can use camera or choose files):</label>
                                <input type="file" class="form-control" id="completed_images" name="completed_images[]" accept="image/*" capture="environment" multiple required>
                                <div class="form-text">Each uploaded file will have 'complete_' prepended to its filename.</div>
                            </div>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-upload"></i> Upload Completed Images
                            </button>
                        </form>
                    </div>

                    <!-- Additional Details -->
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <h6>Status Details</h6>
                            <p class="mb-3">
                                <strong>Priority:</strong>
                                <span class="badge bg-<?php echo getPriorityColor((string)$defect['priority']); ?>">
                                    <?php echo ucfirst((string)$defect['priority']); ?>
                                </span><br>
                                <strong>Assigned To:</strong>
                                <?php echo htmlspecialchars((string)$defect['contractor_name']); ?>
                                (<?php echo htmlspecialchars((string)$defect['contractor_trade']); ?>)
                            </p>
                        </div>
                        <div class="col-md-6">
                            <h6>Timestamps</h6>
                            <p class="mb-3">
                                <strong>Created:</strong> <?php echo $defect['created_at']->format('d/m/Y H:i:s'); ?><br>
                                <strong>Last Updated:</strong> <?php echo $defect['updated_at']->format('d/m/Y H:i:s'); ?>
                            </p>
                        </div>
                    </div>

                    <?php if ($defect['status'] === 'rejected' && !empty($defect['rejection_comment'])): ?>
                        <div class="alert alert-danger mt-3">
                            <h6 class="alert-heading">Rejection Details</h6>
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars((string)$defect['rejection_comment'])); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($history)): ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">History</h5>
                    </div>
                    <div class="card-body">
                        <div class="history-timeline">
                            <?php foreach ($history as $record): ?>
                                <div class="history-item">
                                    <p class="mb-1"><strong><?php echo $record['created_at']->format('d/m/Y H:i:s'); ?></strong></p>
                                    <p class="mb-1"><?php echo htmlspecialchars((string)$record['description']); ?></p>
                                    <small class="text-muted">By <?php echo htmlspecialchars((string)$record['updated_by_user']); ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Large Back Button to My Tasks -->
            <div class="back-btn">
                <a href="my_tasks.php" class="btn btn-primary btn-lg w-100">
                    <i class="fas fa-arrow-left"></i> my tasks
                </a>
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
                <img id="modalImage" src="" alt="Full Size Image" style="max-width: 100%; max-height: 80vh;">
                <input type="checkbox" id="selectImageCheckbox" style="margin-top: 10px;">
                <label for="selectImageCheckbox">Select this image</label>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let zoomLevel = 1, isDragging = false, startX = 0, startY = 0;
    const modalImage = document.getElementById('modalImage');
    const modalBody = document.querySelector('#imageModal .modal-body');

    if (modalImage) {
        modalImage.addEventListener('wheel', function (e) {
            e.preventDefault();
            const zoomSpeed = 0.1;
            zoomLevel = Math.max(1, zoomLevel + (e.deltaY > 0 ? -zoomSpeed : zoomSpeed));
            modalImage.style.transform = `scale(${zoomLevel})`;
        });
    }

    if (modalBody) {
        modalBody.addEventListener('mousedown', function (e) {
            isDragging = true;
            startX = e.clientX - this.offsetLeft;
            startY = e.clientY - this.offsetTop;
            modalBody.classList.add('zoomed');
        });
        modalBody.addEventListener('mouseup', () => { isDragging = false; modalBody.classList.remove('zoomed'); });
        modalBody.addEventListener('mouseleave', () => { isDragging = false; modalBody.classList.remove('zoomed'); });
        modalBody.addEventListener('mousemove', function (e) {
            if (!isDragging) return;
            e.preventDefault();
            const x = e.clientX - this.offsetLeft, y = e.clientY - this.offsetTop;
            this.scrollLeft -= (x - startX);
            this.scrollTop -= (y - startY);
        });

        let initialDistance = null;
        modalBody.addEventListener('touchstart', function (e) {
            if (e.touches.length === 2) {
                initialDistance = getDistanceBetweenTouches(e.touches);
            } else if (e.touches.length === 1) {
                isDragging = true;
                startX = e.touches[0].clientX - this.offsetLeft;
                startY = e.touches[0].clientY - this.offsetTop;
                modalBody.classList.add('zoomed');
            }
        });
        modalBody.addEventListener('touchmove', function (e) {
            e.preventDefault();
            if (e.touches.length === 2 && initialDistance !== null) {
                const currentDistance = getDistanceBetweenTouches(e.touches);
                const zoomSpeed = 0.05;
                zoomLevel = Math.max(1, zoomLevel + (currentDistance - initialDistance) * zoomSpeed);
                modalImage.style.transform = `scale(${zoomLevel})`;
                initialDistance = currentDistance;
            } else if (e.touches.length === 1 && isDragging) {
                const x = e.touches[0].clientX - this.offsetLeft, y = e.touches[0].clientY - this.offsetTop;
                this.scrollLeft -= (x - startX);
                this.scrollTop -= (y - startY);
            }
        }, {passive: false});
        modalBody.addEventListener('touchend', function () {
            isDragging = false;
            modalBody.classList.remove('zoomed');
            initialDistance = null;
        });
    }

    function getDistanceBetweenTouches(touches) {
        const touch1 = touches[0], touch2 = touches[1];
        return Math.sqrt(Math.pow(touch2.clientX - touch1.clientX, 2) +
                         Math.pow(touch2.clientY - touch1.clientY, 2));
    }

    const imageModalElement = document.getElementById('imageModal');
    imageModalElement.addEventListener('hidden.bs.modal', function () {
        zoomLevel = 1;
        if (modalImage) modalImage.style.transform = `scale(${zoomLevel})`;
        if (modalBody) {
            modalBody.scrollLeft = 0;
            modalBody.scrollTop = 0;
        }
    });

    document.getElementById('selectImageCheckbox').addEventListener('change', function() {
        console.log(this.checked ? 'Selected image:' + window.currentImageSrc : 'Unselected image:' + window.currentImageSrc);
    });
</script>
</body>
</html>
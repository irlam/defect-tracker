<?php
/**
 * floor_plans.php
 * Floor Plans Management
 * Current Date and Time (UTC): 2025-01-28 19:40:21
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

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/navbar.php';
date_default_timezone_set('UTC');

// Initialize error handling array
$errors = [];
$success_message = '';

// Function to log errors
function logError($message, $context = []) {
    error_log(sprintf(
        "[%s] [User: %s] %s - Context: %s",
        date('Y-m-d H:i:s'),
        $_SESSION['username'] ?? 'unknown',
        $message,
        json_encode($context)
    ));
}

// Function to scan for PDFs recursively
function scanForPDFs($directory, $maxDepth = 6, $currentDepth = 0) {
    error_log("[" . date('Y-m-d H:i:s') . "] Scanning directory: " . $directory . " at depth " . $currentDepth);
    $pdfs = [];
    
    try {
        if ($currentDepth >= $maxDepth) {
            error_log("[" . date('Y-m-d H:i:s') . "] Max depth reached at: " . $directory);
            return $pdfs;
        }

        if (!is_dir($directory)) {
            error_log("[" . date('Y-m-d H:i:s') . "] Not a directory: " . $directory);
            return $pdfs;
        }

        $files = scandir($directory);
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $file;
            
            if (is_dir($path)) {
                // Recursively scan subdirectories
                $subPDFs = scanForPDFs($path, $maxDepth, $currentDepth + 1);
                $pdfs = array_merge($pdfs, $subPDFs);
            } else {
                // Check if file is PDF
                $fileInfo = pathinfo($path);
                if (strtolower($fileInfo['extension'] ?? '') === 'pdf') {
                    $relativePath = str_replace($_SERVER['DOCUMENT_ROOT'] . '/', '', $path);
                    $pdfs[] = [
                        'path' => $relativePath,
                        'name' => $fileInfo['filename'],
                        'directory' => str_replace($_SERVER['DOCUMENT_ROOT'] . '/', '', $directory),
                        'size' => filesize($path),
                        'modified' => filemtime($path)
                    ];
                    error_log("[" . date('Y-m-d H:i:s') . "] Found PDF: " . $relativePath);
                }
            }
        }
    } catch (Exception $e) {
        error_log("[" . date('Y-m-d H:i:s') . "] Error scanning directory: " . $e->getMessage());
    }

    return $pdfs;
}

// Check authentication
if (!isset($_SESSION['username'])) {
    logError('Unauthorized access attempt to floor plans');
    header("Location: login.php?error=unauthorized");
    exit();
}

// Show current user and current time
$currentUser = $_SESSION['username'];
$currentDateTime = date('Y-m-d H:i:s');

try {
    $database = new Database();
    $db = $database->getConnection();

    // Initialize the Navbar class
    $navbar = new Navbar($db, $_SESSION['user_id'], $_SESSION['username']);

    // Define default image path and create if it doesn't exist
$defaultImage = 'assets/images/default-preview.png';
$defaultImageDirectory = dirname($defaultImage);

// Create the assets/images directory if it doesn't exist
if (!is_dir($defaultImageDirectory)) {
    mkdir($defaultImageDirectory, 0755, true);
}

// Create default preview image if it doesn't exist
if (!file_exists($defaultImage)) {
    try {
        // Create a new image
        $image = imagecreatetruecolor(150, 150);
        
        // Define colors
        $bgColor = imagecolorallocate($image, 248, 249, 250); // #f8f9fa
        $textColor = imagecolorallocate($image, 108, 117, 125); // #6c757d
        $borderColor = imagecolorallocate($image, 222, 226, 230); // #dee2e6
        
        // Fill background
        imagefill($image, 0, 0, $bgColor);
        
        // Add border
        imagerectangle($image, 0, 0, 149, 149, $borderColor);
        
        // Add text
        $text = 'No Preview';
        $font = 5; // Built-in font
        $textWidth = imagefontwidth($font) * strlen($text);
        $textHeight = imagefontheight($font);
        
        // Center the text
        $x = (150 - $textWidth) / 2;
        $y = (150 - $textHeight) / 2;
        
        imagestring($image, $font, (int)$x, (int)$y, $text, $textColor);
        
        // Save the image
        imagepng($image, $defaultImage);
        imagedestroy($image);
        
        error_log("[" . date('Y-m-d H:i:s') . "] Default preview image created successfully");
    } catch (Exception $e) {
        error_log("[" . date('Y-m-d H:i:s') . "] Error creating default preview image: " . $e->getMessage());
        // Set a base64 fallback image if file creation fails
        $defaultImage = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAJYAAACWCAYAAAA8AXHiAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAALEgAACxIB0t1+/AAAABx0RVh0U29mdHdhcmUAQWRvYmUgRmlyZXdvcmtzIENTNui8sowAAAGiSURBVHic7dy/SsNQFMfx37lpKK3QoVMHFwfxAcSt4FrcxUfwCRx9AkHo4Cg4uDj4AA4FFyczWBs65J+latPEJPfm3PM5UwoNaeTL99wkVQrA4/V6vb0gCKpa6wYRNZVSm0qpzeVyfcg5p6dpekfM3O12O71erz+K41hHURT/1kS3mbmj89eHnHOPROKcc8+c8/p/0J/qdrtbRVGsaq2rQRBsSCnXtNZbUspNKWWTc14wxqz9GytN0wgA4jhuHB4cdsqyxHK5RJ7n6Pf7KIripSiK53POH40xn1LKD2PMhzEmieP4WCn19TfWeDy+1Vr3rbXjsixhjEGe5xiNRrDWPgG4staeWGvPrbUXaZq+G2OQ5zlms9lJkiRHWZZNtdaTzc3yPB9Ya0/TNEX5y2uRZRnSNIUx5lprvTfP5oM8zwEASql9AN0oipAkCbTWB1pr9xaLRbBarQAAQggopdYBdMIwxGQyQRAEO2EYbltrO2maIo5jlGUZlmUZhmHYBoBKpWLDMES1Wm0EQbCutd4KgqBZqVS2giBoVqvVzWq1Gq5iAX8cGb/QQB2zKQAAAABJRU5ErkJggg==';
    }
}

// Log the default image path
error_log("[" . date('Y-m-d H:i:s') . "] Default image path: " . $defaultImage);

    // Scan for PDFs in uploads/floor_plans
    $uploadDir = __DIR__ . '/uploads/floor_plans';
    $foundPDFs = scanForPDFs($uploadDir);
    error_log("[" . date('Y-m-d H:i:s') . "] Total PDFs found: " . count($foundPDFs));

    // Create a mapping of file paths to their details
    $pdfMapping = array_reduce($foundPDFs, function($acc, $pdf) {
        $acc[$pdf['path']] = $pdf;
        return $acc;
    }, []);
    // Modify the floor plans query to include file existence check and duplicates
    $sql = "SELECT 
                fp.*, 
                p.name as project_name,
                COALESCE(
                    (SELECT 1 FROM floor_plans fp2 
                     WHERE fp2.file_path = fp.file_path 
                     AND fp2.id != fp.id 
                     LIMIT 1), 
                    0
                ) as has_duplicate,
                u.username as created_by_username
            FROM floor_plans fp 
            JOIN projects p ON fp.project_id = p.id 
            LEFT JOIN users u ON fp.created_by = u.id
            WHERE fp.status = 'active'
            ORDER BY fp.created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $floor_plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Update floor plans array with file existence information
    foreach ($floor_plans as $key => $plan) {
        $relativePath = str_replace($_SERVER['DOCUMENT_ROOT'] . '/', '', $plan['file_path']);
        $floor_plans[$key]['file_exists'] = isset($pdfMapping[$relativePath]);
        $floor_plans[$key]['file_details'] = $pdfMapping[$relativePath] ?? null;
        
        if (!$floor_plans[$key]['file_exists']) {
            error_log("[" . date('Y-m-d H:i:s') . "] File not found: " . $relativePath);
            // Try to find the file in the scanned PDFs by filename
            $fileName = basename($relativePath);
            foreach ($foundPDFs as $pdf) {
                if (basename($pdf['path']) === $fileName) {
                    error_log("[" . date('Y-m-d H:i:s') . "] Found matching file: " . $pdf['path']);
                    // Update the database with the new path
                    try {
                        $updateSql = "UPDATE floor_plans SET 
                                    file_path = ?, 
                                    updated_at = ?, 
                                    updated_by = ? 
                                    WHERE id = ?";
                        $updateStmt = $db->prepare($updateSql);
                        $updateStmt->execute([
                            $pdf['path'], 
                            date('Y-m-d H:i:s'),
                            $_SESSION['user_id'],
                            $plan['id']
                        ]);
                        
                        $floor_plans[$key]['file_path'] = $pdf['path'];
                        $floor_plans[$key]['file_exists'] = true;
                        $floor_plans[$key]['file_details'] = $pdf;
                        
                        error_log("[" . date('Y-m-d H:i:s') . "] Updated database with new path for ID " . $plan['id']);
                        break;
                    } catch (PDOException $e) {
                        error_log("[" . date('Y-m-d H:i:s') . "] Error updating file path: " . $e->getMessage());
                    }
                }
            }
        }
    }

    // Log summary of file scanning
    error_log(sprintf(
        "[%s] Floor Plans Summary:\nTotal Records: %d\nFiles Found: %d\nMissing Files: %d",
        date('Y-m-d H:i:s'),
        count($floor_plans),
        count(array_filter($floor_plans, function($plan) { return $plan['file_exists']; })),
        count(array_filter($floor_plans, function($plan) { return !$plan['file_exists']; }))
    ));

    // Fetch projects for the edit modal
    $projectsSql = "SELECT id, name FROM projects WHERE status = 'active' ORDER BY name ASC";
    $projectsStmt = $db->prepare($projectsSql);
    $projectsStmt->execute();
    $projects = $projectsStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    logError("Floor Plans Error: " . $e->getMessage());
    $error_message = "An error occurred while loading floor plans.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Floor Plans - Defect Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
	<link rel="icon" type="image/png" href="/favicons/favicon-96x96.png" sizes="96x96" />
<link rel="icon" type="image/svg+xml" href="/favicons/favicon.svg" />
<link rel="shortcut icon" href="/favicons/favicon.ico" />
<link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png" />
<link rel="manifest" href="/favicons/site.webmanifest" />
    <script>
        // Set worker path for PDF.js
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    </script>
</head>
<body data-bs-theme="dark">
    <?php echo $navbar->render(); ?>

    <div class="content-wrapper">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div id="alertContainer"></div>
                    
                    <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0">Floor Plans</h1>
    </div>
    <div>
        <a href="upload_floor_plan.php" class="btn btn-primary">
            <i class='bx bx-upload me-1'></i> Upload New Floor Plan
        </a>
    </div>
</div>

                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class='bx bx-error-circle me-1'></i>
                            <?php echo htmlspecialchars($error_message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Manage Floor Plans</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover" id="floorPlansTable">
                                    <thead>
                                        <tr>
                                            <th class="preview-cell">Preview</th>
                                            <th>Name</th>
                                            <th>Project</th>
                                            <th>Level</th>
                                            <th>Created By</th>
                                            <th>Created At</th>
                                            <th class="action-buttons">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($floor_plans as $plan): ?>
                                            <tr>
                                                <td class="preview-cell">
    <div class="preview-container <?php echo !$plan['file_exists'] ? 'file-not-found' : ''; ?>" 
         data-file-type="<?php echo htmlspecialchars($plan['file_type']); ?>"
         data-file-path="<?php echo htmlspecialchars($plan['file_path']); ?>"
         data-exists="<?php echo $plan['file_exists'] ? 'true' : 'false'; ?>">
        <?php if ($plan['file_type'] === 'application/pdf'): ?>
            <?php if ($plan['file_exists']): ?>
                <canvas class="pdf-preview" width="150" height="150"></canvas>
                <div class="preview-loading">
                    <i class='bx bx-loader-alt'></i>
                    <span>Loading...</span>
                </div>
            <?php else: ?>
                <div class="file-missing">
                    <i class='bx bx-error-circle'></i>
                    <span>File Not Found</span>
                    <small>Check uploads folder</small>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <img src="<?php echo htmlspecialchars($plan['file_path']); ?>" 
                 class="floor-plan-image" 
                 alt="<?php echo htmlspecialchars($plan['floor_name']); ?>"
                 onerror="this.onerror=null;this.src='<?php echo htmlspecialchars($defaultImage); ?>';">
        <?php endif; ?>
    </div>
</td>
                                                </td>
                                                <td><?php echo htmlspecialchars($plan['floor_name']); ?></td>
                                                <td><?php echo htmlspecialchars($plan['project_name']); ?></td>
                                                <td><?php echo htmlspecialchars($plan['level']); ?></td>
                                                <td><?php echo htmlspecialchars($plan['created_by_username'] ?? 'Unknown'); ?></td>
                                                <td data-sort="<?php echo strtotime($plan['created_at']); ?>">
                                                    <?php echo date('Y-m-d H:i:s', strtotime($plan['created_at'])); ?>
                                                </td>
                                                <td class="action-buttons">
                                                    <button class="btn btn-sm btn-primary" 
                                                            onclick='editFloorPlan(<?php echo json_encode([
                                                                'id' => $plan['id'],
                                                                'project_id' => $plan['project_id'],
                                                                'floor_name' => $plan['floor_name'],
                                                                'level' => $plan['level'],
                                                                'description' => $plan['description'] ?? '',
                                                                'file_exists' => $plan['file_exists']
                                                            ]); ?>)'
                                                            title="Edit floor plan">
                                                        <i class='bx bx-edit'></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-danger" 
                                                            onclick="deleteFloorPlan(<?php echo (int)$plan['id']; ?>)"
                                                            title="Delete floor plan">
                                                        <i class='bx bx-trash'></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Edit Floor Plan Modal -->
                    <div class="modal fade" id="editFloorPlanModal" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Edit Floor Plan</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <form id="editFloorPlanForm">
                                    <div class="modal-body">
                                        <input type="hidden" id="editId" name="id">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                        
                                        <div class="mb-3">
                                            <label for="editProjectId" class="form-label">Project</label>
                                            <select class="form-select" id="editProjectId" name="project_id" required>
                                                <?php foreach ($projects as $project): ?>
                                                    <option value="<?php echo htmlspecialchars($project['id']); ?>">
                                                        <?php echo htmlspecialchars($project['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <label for="editName" class="form-label">Floor Plan Name</label>
                                            <input type="text" class="form-control" id="editName" name="floor_name" required>
                                        </div>

                                        <div class="mb-3">
                                            <label for="editLevel" class="form-label">Floor Level</label>
                                            <input type="text" class="form-control" id="editLevel" name="level" required>
                                        </div>

                                        <div class="mb-3">
                                            <label for="editDescription" class="form-label">Description</label>
                                            <textarea class="form-control" id="editDescription" name="description" rows="3"></textarea>
                                        </div>

                                        <div id="fileStatusAlert" class="alert alert-warning d-none">
                                            <i class='bx bx-error'></i>
                                            <span>File not found in the system. Please check the file location.</span>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-primary">
                                            <i class='bx bx-save me-1'></i>Save Changes
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Sidebar Toggle Button -->
    <button id="mobileSidebarToggle" class="btn btn-dark d-lg-none position-fixed">
        <i class='bx bx-menu'></i>
    </button>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        /**
         * Floor Plans Management JavaScript
         * Current Date and Time (UTC): 2025-01-28 19:43:29
         * Current User's Login: irlam
         */
        document.addEventListener('DOMContentLoaded', function() {
    // Check if PDF.js is properly loaded
    if (typeof pdfjsLib === 'undefined') {
        console.error('PDF.js is not loaded properly');
        showAlert('PDF preview functionality is not available. Please refresh the page.', 'warning');
        return;
    }

    console.log('PDF.js version:', pdfjsLib.version);
    
    // Initialize DataTable and PDF previews
    initializePDFPreviews();
    // ... rest of your initialization code ...
});
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize DataTable with improved configuration
            const table = $('#floorPlansTable').DataTable({
                order: [[5, 'desc']], // Sort by Created At by default
                pageLength: 10,
                responsive: true,
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search floor plans...",
                    info: "Showing _START_ to _END_ of _TOTAL_ floor plans",
                    infoEmpty: "No floor plans available",
                    emptyTable: "No floor plans found"
                },
                drawCallback: function() {
                    // Reinitialize PDF previews after table redraw
                    initializePDFPreviews();
                }
            });

            // Initialize PDF previews for initial load
            initializePDFPreviews();

            // Mobile sidebar toggle
            document.getElementById('mobileSidebarToggle')?.addEventListener('click', function() {
                document.querySelector('.sidebar').classList.toggle('show');
            });

            // Auto-dismiss alerts after 5 seconds
            setTimeout(function() {
                $('.alert-dismissible').alert('close');
            }, 5000);
        });

        async function initializePDFPreviews() {
    console.log('[2025-01-28 19:50:17] Initializing PDF previews...');
    const previewContainers = document.querySelectorAll('.preview-container');
    console.log(`Found ${previewContainers.length} preview containers`);

    for (const container of previewContainers) {
        const fileType = container.dataset.fileType;
        const filePath = container.dataset.filePath;
        const fileExists = container.dataset.exists === 'true';
        
        console.log(`Processing container:`, {
            fileType,
            filePath,
            fileExists
        });

        if (fileType === 'application/pdf' && fileExists) {
            try {
                console.log(`Starting PDF preview for: ${filePath}`);
                const canvas = container.querySelector('canvas.pdf-preview');
                const loadingDiv = container.querySelector('.preview-loading');
                
                if (!canvas) {
                    console.error('Canvas element not found in container');
                    continue;
                }

                // Add error handling for missing file path
                if (!filePath) {
                    throw new Error('File path is missing');
                }

                // Ensure file path starts with proper URL
                const pdfUrl = filePath.startsWith('/') ? filePath : '/' + filePath;
                console.log(`Loading PDF from: ${pdfUrl}`);

                // Show loading state
                if (loadingDiv) {
                    loadingDiv.innerHTML = `
                        <i class='bx bx-loader-alt'></i>
                        <span>Loading PDF...</span>
                    `;
                }

                const startTime = performance.now();

                // Load the PDF file with explicit error handling
                const loadingTask = pdfjsLib.getDocument({
                    url: pdfUrl,
                    verbosity: pdfjsLib.VerbosityLevel.ERRORS
                });

                loadingTask.onProgress = function(progress) {
                    console.log(`Loading PDF: ${progress.loaded} of ${progress.total}`);
                };

                const pdf = await loadingTask.promise;
                console.log(`PDF loaded successfully: ${pdf.numPages} pages`);

                // Get the first page
                const page = await pdf.getPage(1);
                console.log('First page loaded');

                // Calculate proper scaling
                const viewport = page.getViewport({ scale: 1.0 });
                const scale = Math.min(150 / viewport.width, 150 / viewport.height);
                const scaledViewport = page.getViewport({ scale });

                // Set canvas dimensions
                canvas.width = scaledViewport.width;
                canvas.height = scaledViewport.height;
                canvas.style.width = `${scaledViewport.width}px`;
                canvas.style.height = `${scaledViewport.height}px`;

                // Prepare rendering context
                const context = canvas.getContext('2d');
                const renderContext = {
                    canvasContext: context,
                    viewport: scaledViewport,
                    enableWebGL: true
                };

                // Render the page
                const renderTask = page.render(renderContext);
                await renderTask.promise;
                
                console.log('PDF page rendered successfully');

                // Calculate and log performance
                const endTime = performance.now();
                const duration = endTime - startTime;
                console.log(`PDF preview generated in ${duration}ms`);

                // Remove loading indicator
                if (loadingDiv) {
                    loadingDiv.remove();
                }

                // Add click handler to open PDF
                container.addEventListener('click', () => {
                    window.open(pdfUrl, '_blank');
                });

                // Add success indicator
                container.classList.add('preview-success');

            } catch (error) {
                console.error('Error loading PDF preview:', error);
                const loadingDiv = container.querySelector('.preview-loading');
                if (loadingDiv) {
                    loadingDiv.innerHTML = `
                        <i class='bx bx-error-circle' style="color: #dc3545;"></i>
                        <span style="color: #dc3545;">Error loading PDF</span>
                        <small style="color: #dc3545;">${error.message}</small>
                    `;
                }
                
                // Log the error with context
                logError('PDF Preview Error', {
                    filePath,
                    error: error.message,
                    timestamp: new Date().toISOString(),
                    user: 'irlam'
                });
            }
        }
    }
}

// Add this helper function for error logging
function logError(type, details) {
    const errorLog = {
        type,
        details,
        timestamp: '2025-01-28 19:50:17',
        user: 'irlam'
    };
    console.error('Error Log:', errorLog);
    
    // You might want to send this to your server
    try {
        fetch('/api/log-error.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(errorLog)
        });
    } catch (e) {
        console.error('Error sending error log:', e);
    }
}

        function showAlert(message, type = 'success') {
            const alert = document.createElement('div');
            alert.className = `alert alert-${type} alert-dismissible fade show`;
            alert.innerHTML = `
                <i class="bx bx-${type === 'success' ? 'check' : 'x'}-circle me-1"></i>${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.getElementById('alertContainer').appendChild(alert);
            setTimeout(() => alert.remove(), 5000);
        }

        function editFloorPlan(plan) {
            console.log('Edit plan data:', plan);
            
            if (!plan || !plan.id) {
                console.error('Invalid plan data:', plan);
                showAlert('Invalid floor plan data', 'danger');
                return;
            }

            document.getElementById('editId').value = plan.id;
            document.getElementById('editProjectId').value = plan.project_id;
            document.getElementById('editName').value = plan.floor_name;
            document.getElementById('editLevel').value = plan.level;
            document.getElementById('editDescription').value = plan.description || '';
            
            // Show file status warning if file doesn't exist
            const fileStatusAlert = document.getElementById('fileStatusAlert');
            if (!plan.file_exists) {
                fileStatusAlert.classList.remove('d-none');
            } else {
                fileStatusAlert.classList.add('d-none');
            }
            
            const modal = new bootstrap.Modal(document.getElementById('editFloorPlanModal'));
            modal.show();
        }

        document.getElementById('editFloorPlanForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('updated_at', new Date().toISOString());
            formData.append('updated_by', '<?php echo $_SESSION['user_id']; ?>');

            try {
                const response = await fetch('api/update_floor_plan.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                console.log('Update response:', result);
                
                if (result.success) {
                    showAlert('Floor plan updated successfully');
                    location.reload();
                } else {
                    showAlert(result.message || 'Error updating floor plan', 'danger');
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('Error updating floor plan', 'danger');
            }
        });

        function deleteFloorPlan(id) {
            console.log('Deleting floor plan ID:', id);
            
            if (!id || isNaN(parseInt(id))) {
                console.error('Invalid floor plan ID:', id);
                showAlert('Invalid floor plan ID', 'danger');
                return;
            }

            if (confirm('Are you sure you want to delete this floor plan? This action cannot be undone.')) {
                const formData = new FormData();
                formData.append('id', id);
                formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
                
                fetch('api/delete_floor_plan.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(result => {
                    console.log('Delete response:', result);
                    if (result.success) {
                        showAlert('Floor plan deleted successfully');
                        // Fade out the deleted row before reloading
                        const row = $(`#floorPlansTable tr:has(button[onclick*="${id}"])`);
                        row.fadeOut(400, () => {
                            location.reload();
                        });
                    } else {
                        showAlert(result.message || 'Error deleting floor plan', 'danger');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('Error deleting floor plan', 'danger');
                });
            }
        }

        // Add tooltips for file info
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Handle PDF preview errors globally
        window.addEventListener('unhandledrejection', function(event) {
            if (event.reason && event.reason.message && event.reason.message.includes('PDF.js')) {
                console.error('PDF.js Error:', event.reason);
                showAlert('Error loading PDF preview. Please check if the file is valid.', 'warning');
            }
        });

        // Log user activity
        function logUserActivity(action, details) {
            console.log(`[${new Date().toISOString()}] User: irlam - Action: ${action}`, details);
        }

        // Monitor PDF loading performance
        const performanceEntries = [];
        function logPDFLoadingPerformance(filePath, startTime, endTime) {
            const duration = endTime - startTime;
            performanceEntries.push({
                filePath,
                duration,
                timestamp: new Date().toISOString()
            });
            
            if (duration > 2000) { // If loading takes more than 2 seconds
                console.warn(`Slow PDF loading detected for ${filePath}: ${duration}ms`);
            }
        }

        // Cleanup function for page unload
        window.addEventListener('beforeunload', function() {
            // Log performance data if any entries exist
            if (performanceEntries.length > 0) {
                console.log('PDF Loading Performance Summary:', {
                    totalFiles: performanceEntries.length,
                    averageDuration: performanceEntries.reduce((acc, curr) => acc + curr.duration, 0) / performanceEntries.length,
                    slowLoads: performanceEntries.filter(entry => entry.duration > 2000).length
                });
            }
        });
    </script>
</body>
</html>
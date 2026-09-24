<?php
/**
 * floor_plans.php
 * Floor Plans Management
 * Current Date and Time (UTC): 2025-01-28 19:40:21
 * Current User's Login: irlam
 */

// Error reporting and logging setup
error_reporting(E_ALL);
ini_set('display_errors', 0);
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
<body class="tool-body" data-bs-theme="dark">
    <?php echo $navbar->render(); ?>

    <div class="content-wrapper">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div id="alertContainer"></div>
                    
                    <div class="floor-plans-header mb-4">
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

                    <div class="card floor-plans-card">
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
        <?php if (!empty($plan['image_path'])): ?>
            <img src="/<?php echo htmlspecialchars(ltrim((string) $plan['image_path'], '/')); ?>"
                 class="floor-plan-image"
                 alt="<?php echo htmlspecialchars($plan['floor_name']); ?>"
                 loading="lazy"
                 onerror="this.onerror=null;this.src='<?php echo htmlspecialchars($defaultImage); ?>';">
        <?Û¾}¶‰žËkºwµç]•µ•¹Ð)…Ù…MÉ¥ÁÐ4(€€€€€€€€€¨ÕÉÉ•¹Ð…Ñ”…¹Q¥µ”€¡UQ¤è€ÈÀÈÔ´ÀÄ´Èà€ÄäèÐÌèÈä4(€€€€€€€€€¨ÕÉÉ•¹ÐUÍ•ÈÌ1½¥¸è¥É±…´4(€€€€€€€€€¨¼4(€€€€€€€‘½Õµ•¹Ð¹…‘‘Ù•¹Ñ1¥ÍÑ•¹•È =5½¹Ñ•¹Ñ1½…‘•œ°™Õ¹Ñ¥½¸ ¤ì(€€€€€€€€€€€¥˜€¡ÑåÁ•½˜Á‘™©Í1¥ˆ€ôôô€Õ¹‘•™¥¹•œ¤ì(€€€€€€€€€€€€€€€½¹Í½±”¹•ÉÉ½È A¹©Ì¥Ì¹½Ð±½…‘•ÁÉ½Á•É±äœ¤ì(€€€€€€€€€€€€€€€Í¡½Ý±•ÉÐ AÁÉ•Ù¥•Ü™Õ¹Ñ¥½¹…±¥Ñä¥Ì¹½Ð…Ù…¥±…‰±”¸A±•…Í”É•™É•Í Ñ¡”Á…”¸œ°€Ý…É¹¥¹œœ¤ì(€€€€€€€€€€€€€€€É•ÑÕÉ¸ì(€€€€€€€€€€€ô((€€€€€€€€€€€½¹Í½±”¹±½œ A¹©ÌÙ•ÉÍ¥½¸èœ°Á‘™©Í1¥ˆ¹Ù•ÉÍ¥½¸¤ì((€€€€€€€€€€€€¼¼%¹¥Ñ¥…±¥é”…Ñ…Q…‰±”Ý¥Ñ ¥µÁÉ½Ù•½¹™¥ÕÉ…Ñ¥½¸4(€€€€€€€€€€€½¹ÍÐÑ…‰±”€ô€ œ™±½½ÉA±…¹ÍQ…‰±”œ¤¹…Ñ…Q…‰±”¡ì4(€€€€€€€€€€€€€€€½É‘•ÈèmlÔ°€‘•ÍŒut°€¼¼M½ÉÐ‰äÉ•…Ñ•Ð‰ä‘•™…Õ±Ð4(€€€€€€€€€€€€€€€Á…•1•¹Ñ è€ÄÀ°4(€€€€€€€€€€€€€€€É•ÍÁ½¹Í¥Ù”èÑÉÕ”°4(€€€€€€€€€€€€€€€±…¹Õ…”èì4(€€€€€€€€€€€€€€€€€€€Í•…É è€‰}%9AUQ|ˆ°4(€€€€€€€€€€€€€€€€€€€Í•…É¡A±…•¡½±‘•Èè€‰M•…É ™±½½ÈÁ±…¹Ì¸¸¸ˆ°4(€€€€€€€€€€€€€€€€€€€¥¹™¼è€‰M¡½Ý¥¹œ}MQIQ|Ñ¼}9|½˜}Q=Q1|™±½½ÈÁ±…¹Ìˆ°4(€€€€€€€€€€€€€€€€€€€¥¹™½µÁÑäè€‰9¼™±½½ÈÁ±…¹Ì…Ù…¥±…‰±”ˆ°4(€€€€€€€€€€€€€€€€€€€•µÁÑåQ…‰±”è€‰9¼™±½½ÈÁ±…¹Ì™½Õ¹ˆ4(€€€€€€€€€€€€€€€ô°4(€€€€€€€€€€€€€€€‘É…Ý…±±‰…¬è™Õ¹Ñ¥½¸ ¤ì4(€€€€€€€€€€€€€€€€€€€€¼¼I•¥¹¥Ñ¥…±¥é”AÁÉ•Ù¥•ÝÌ…™Ñ•ÈÑ…‰±”É•‘É…Ü4(€€€€€€€€€€€€€€€€€€€¥¹¥Ñ¥…±¥é•AAÉ•Ù¥•ÝÌ ¤ì4(€€€€€€€€€€€€€€€ô4(€€€€€€€€€€€ô¤ì4(4(€€€€€€€€€€€€¼¼5½‰¥±”Í¥‘•‰…ÈÑ½±”4(€€€€€€€€€€€‘½Õµ•¹Ð¹•Ñ±•µ•¹Ñ	å% µ½‰¥±•M¥‘•‰…ÉQ½±”œ¤ü¹…‘‘Ù•¹Ñ1¥ÍÑ•¹•È ±¥¬œ°™Õ¹Ñ¥½¸ ¤ì4(€€€€€€€€€€€€€€€‘½Õµ•¹Ð¹ÅÕ•ÉåM•±•Ñ½È œ¹Í¥‘•‰…Èœ¤¹±…ÍÍ1¥ÍÐ¹Ñ½±” Í¡½Üœ¤ì4(€€€€€€€€€€€ô¤ì4(4(€€€€€€€€€€€€¼¼ÕÑ¼µ‘¥Íµ¥ÍÌ…±•ÉÑÌ…™Ñ•È€ÔÍ•½¹‘Ì4(€€€€€€€€€€€Í•ÑQ¥µ•½ÕÐ¡™Õ¹Ñ¥½¸ ¤ì4(€€€€€€€€€€€€€€€€ œ¹…±•ÉÐµ‘¥Íµ¥ÍÍ¥‰±”œ¤¹…±•ÉÐ ±½Í”œ¤ì4(€€€€€€€€€€€ô°€ÔÀÀÀ¤ì4(€€€€€€€ô¤ì4(4(€€€€€€€…Íå¹Œ™Õ¹Ñ¥½¸¥¹¥Ñ¥…±¥é•AAÉ•Ù¥•ÝÌ ¤ì4(€€€½¹Í½±”¹±½œ lÈÀÈÔ´ÀÄ´Èà€ÄäèÔÀèÄÝt%¹¥Ñ¥…±¥é¥¹œAÁÉ•Ù¥•ÝÌ¸¸¸œ¤ì4(€€€½¹ÍÐÁÉ•Ù¥•Ý½¹Ñ…¥¹•ÉÌ€ô‘½Õµ•¹Ð¹ÅÕ•ÉåM•±•Ñ½É±° œ¹ÁÉ•Ù¥•Üµ½¹Ñ…¥¹•Èœ¤ì4(€€€½¹Í½±”¹±½œ¡½Õ¹€‘íÁÉ•Ù¥•Ý½¹Ñ…¥¹•ÉÌ¹±•¹Ñ¡ôÁÉ•Ù¥•Ü½¹Ñ…¥¹•ÉÍ€¤ì4(4(€€€™½È€¡½¹ÍÐ½¹Ñ…¥¹•È½˜ÁÉ•Ù¥•Ý½¹Ñ…¥¹•ÉÌ¤ì(€€€€€€€¥˜€¡½¹Ñ…¥¹•È¹‘…Ñ…Í•Ð¹ÁÉ•Ù¥•ÝMÑ…Ñ”€ôôô€±½…‘¥¹œœñð½¹Ñ…¥¹•È¹‘…Ñ…Í•Ð¹ÁÉ•Ù¥•ÝMÑ…Ñ”€ôôô€±½…‘•œ¤ì(€€€€€€€€€€€½¹Ñ¥¹Õ”ì(€€€€€€€ô((€€€€€€€€¼¼UÁ±½…‘•AÌ¹½Éµ…±±ä¡…Ù”„•¹•É…Ñ•A9ÁÉ•Ù¥•Ü¸Q¡½Í”¥µ…•Ì…É”(€€€€€€€€¼¼…±É•…‘äÉ•¹‘•É•‰äÑ¡”‰É½ÝÍ•È…¹‘¼¹½Ð¹••Ñ¡”A¹©Ì™…±±‰…¬¸(€€€€€€€¥˜€¡½¹Ñ…¥¹•È¹ÅÕ•ÉåM•±•Ñ½È ¥µœ¹™±½½ÈµÁ±…¸µ¥µ…”œ¤¤ì(€€€€€€€€€€€½¹Ñ…¥¹•È¹‘…Ñ…Í•Ð¹ÁÉ•Ù¥•ÝMÑ…Ñ”€ô€±½…‘•œì(€€€€€€€€€€€½¹Ñ¥¹Õ”ì(€€€€€€€ô((€€€€€€€½¹ÍÐ™¥±•QåÁ”€ô½¹Ñ…¥¹•È¹‘…Ñ…Í•Ð¹™¥±•QåÁ”ì(€€€€€€€½¹ÍÐ™¥±•A…Ñ €ô½¹Ñ…¥¹•È¹‘…Ñ…Í•Ð¹™¥±•A…Ñ ì4(€€€€€€€½¹ÍÐ™¥±•á¥ÍÑÌ€ô½¹Ñ…¥¹•È¹‘…Ñ…Í•Ð¹•á¥ÍÑÌ€ôôô€ÑÉÕ”œì4(€€€€€€€€4(€€€€€€€½¹Í½±”¹±½œ¡AÉ½•ÍÍ¥¹œ½¹Ñ…¥¹•Èé€°ì4(€€€€€€€€€€€™¥±•QåÁ”°4(€€€€€€€€€€€™¥±•A…Ñ °4(€€€€€€€€€€€™¥±•á¥ÍÑÌ4(€€€€€€€ô¤ì4(4(€€€€€€€¥˜€¡™¥±•QåÁ”€ôôô€…ÁÁ±¥…Ñ¥½¸½Á‘˜œ€˜˜™¥±•á¥ÍÑÌ¤ì(€€€€€€€€€€€ÑÉäì(€€€€€€€€€€€€€€€½¹Ñ…¥¹•È¹‘…Ñ…Í•Ð¹ÁÉ•Ù¥•ÝMÑ…Ñ”€ô€±½…‘¥¹œœì(€€€€€€€€€€€€€€€½¹Í½±”¹±½œ¡MÑ…ÉÑ¥¹œAÁÉ•Ù¥•Ü™½Èè€‘í™¥±•A…Ñ¡õ€¤ì4(€€€€€€€€€€€€€€€½¹ÍÐ…¹Ù…Ì€ô½¹Ñ…¥¹•È¹ÅÕ•ÉåM•±•Ñ½È …¹Ù…Ì¹Á‘˜µÁÉ•Ù¥•Üœ¤ì4(€€€€€€€€€€€€€€€½¹ÍÐ±½…‘¥¹¥Ø€ô½¹Ñ…¥¹•È¹ÅÕ•ÉåM•±•Ñ½È œ¹ÁÉ•Ù¥•Üµ±½…‘¥¹œœ¤ì4(€€€€€€€€€€€€€€€€4(€€€€€€€€€€€€€€€¥˜€ ……¹Ù…Ì¤ì4(€€€€€€€€€€€€€€€€€€€½¹Í½±”¹•ÉÉ½È …¹Ù…Ì•±•µ•¹Ð¹½Ð™½Õ¹¥¸½¹Ñ…¥¹•Èœ¤ì4(€€€€€€€€€€€€€€€€€€€½¹Ñ¥¹Õ”ì4(€€€€€€€€€€€€€€€ô4(4(€€€€€€€€€€€€€€€€¼¼‘•ÉÉ½È¡…¹‘±¥¹œ™½Èµ¥ÍÍ¥¹œ™¥±”Á…Ñ 4(€€€€€€€€€€€€€€€¥˜€ …™¥±•A…Ñ ¤ì4(€€€€€€€€€€€€€€€€€€€Ñ¡É½Ü¹•ÜÉÉ½È ¥±”Á…Ñ ¥Ìµ¥ÍÍ¥¹œœ¤ì4(€€€€€€€€€€€€€€€ô4(4(€€€€€€€€€€€€€€€€¼¼¹ÍÕÉ”™¥±”Á…Ñ ÍÑ…ÉÑÌÝ¥Ñ ÁÉ½Á•ÈUI04(€€€€€€€€€€€€€€€½¹ÍÐÁ‘™UÉ°€ô™¥±•A…Ñ ¹ÍÑ…ÉÑÍ]¥Ñ  œ¼œ¤€ü™¥±•A…Ñ €è€œ¼œ€¬™¥±•A…Ñ ì4(€€€€€€€€€€€€€€€½¹Í½±”¹±½œ¡1½…‘¥¹œA™É½´è€‘íÁ‘™UÉ±õ€¤ì4(4(€€€€€€€€€€€€€€€€¼¼M¡½Ü±½…‘¥¹œÍÑ…Ñ”4(€€€€€€€€€€€€€€€¥˜€¡±½…‘¥¹¥Ø¤ì4(€€€€€€€€€€€€€€€€€€€±½…‘¥¹¥Ø¹¥¹¹•É!Q50€ô€4(€€€€€€€€€€€€€€€€€€€€€€€€ñ¤±…ÍÌô‰à‰àµ±½…‘•Èµ…±Ðœøð½¤ø4(€€€€€€€€€€€€€€€€€€€€€€€€ñÍÁ…¸ù1½…‘¥¹œA¸¸¸ð½ÍÁ…¸ø4(€€€€€€€€€€€€€€€€€€€€ì4(€€€€€€€€€€€€€€€ô4(4(€€€€€€€€€€€€€€€½¹ÍÐÍÑ…ÉÑQ¥µ”€ôÁ•É™½Éµ…¹”¹¹½Ü ¤ì4(4(€€€€€€€€€€€€€€€€¼¼1½…Ñ¡”A™¥±”Ý¥Ñ •áÁ±¥¥Ð•ÉÉ½È¡…¹‘±¥¹œ4(€€€€€€€€€€€€€€€½¹ÍÐ±½…‘¥¹Q…Í¬€ôÁ‘™©Í1¥ˆ¹•Ñ½Õµ•¹Ð¡ì4(€€€€€€€€€€€€€€€€€€€ÕÉ°èÁ‘™UÉ°°4(€€€€€€€€€€€€€€€€€€€Ù•É‰½Í¥ÑäèÁ‘™©Í1¥ˆ¹Y•É‰½Í¥Ñå1•Ù•°¹II=IL4(€€€€€€€€€€€€€€€ô¤ì4(4(€€€€€€€€€€€€€€€±½…‘¥¹Q…Í¬¹½¹AÉ½É•ÍÌ€ô™Õ¹Ñ¥½¸¡ÁÉ½É•ÍÌ¤ì4(€€€€€€€€€€€€€€€€€€€½¹Í½±”¹±½œ¡1½…‘¥¹œAè€‘íÁÉ½É•ÍÌ¹±½…‘•‘ô½˜€‘íÁÉ½É•ÍÌ¹Ñ½Ñ…±õ€¤ì4(€€€€€€€€€€€€€€€ôì4(4(€€€€€€€€€€€€€€€½¹ÍÐÁ‘˜€ô…Ý…¥Ð±½…‘¥¹Q…Í¬¹ÁÉ½µ¥Í”ì4(€€€€€€€€€€€€€€€½¹Í½±”¹±½œ¡A±½…‘•ÍÕ•ÍÍ™Õ±±äè€‘íÁ‘˜¹¹ÕµA…•ÍôÁ…•Í€¤ì4(4(€€€€€€€€€€€€€€€€¼¼•ÐÑ¡”™¥ÉÍÐÁ…”4(€€€€€€€€€€€€€€€½¹ÍÐÁ…”€ô…Ý…¥ÐÁ‘˜¹•ÑA…” Ä¤ì4(€€€€€€€€€€€€€€€½¹Í½±”¹±½œ ¥ÉÍÐÁ…”±½…‘•œ¤ì4(4(€€€€€€€€€€€€€€€€¼¼…±Õ±…Ñ”ÁÉ½Á•ÈÍ…±¥¹œ4(€€€€€€€€€€€€€€€½¹ÍÐÙ¥•ÝÁ½ÉÐ€ôÁ…”¹•ÑY¥•ÝÁ½ÉÐ¡ìÍ…±”è€Ä¸Àô¤ì4(€€€€€€€€€€€€€€€½¹ÍÐÍ…±”€ô5…Ñ ¹µ¥¸ ÄÔÀ€¼Ù¥•ÝÁ½ÉÐ¹Ý¥‘Ñ °€ÄÔÀ€¼Ù¥•ÝÁ½ÉÐ¹¡•¥¡Ð¤ì4(€€€€€€€€€€€€€€€½¹ÍÐÍ…±•‘Y¥•ÝÁ½ÉÐ€ôÁ…”¹•ÑY¥•ÝÁ½ÉÐ¡ìÍ…±”ô¤ì4(4(€€€€€€€€€€€€€€€€¼¼M•Ð…¹Ù…Ì‘¥µ•¹Í¥½¹Ì4(€€€€€€€€€€€€€€€…¹Ù…Ì¹Ý¥‘Ñ €ôÍ…±•‘Y¥•ÝÁ½ÉÐ¹Ý¥‘Ñ ì4(€€€€€€€€€€€€€€€…¹Ù…Ì¹¡•¥¡Ð€ôÍ…±•‘Y¥•ÝÁ½ÉÐ¹¡•¥¡Ðì4(€€€€€€€€€€€€€€€…¹Ù…Ì¹ÍÑå±”¹Ý¥‘Ñ €ô€‘íÍ…±•‘Y¥•ÝÁ½ÉÐ¹Ý¥‘Ñ¡õÁá€ì4(€€€€€€€€€€€€€€€…¹Ù…Ì¹ÍÑå±”¹¡•¥¡Ð€ô€‘íÍ…±•‘Y¥•ÝÁ½ÉÐ¹¡•¥¡ÑõÁá€ì4(4(€€€€€€€€€€€€€€€€¼¼AÉ•Á…É”É•¹‘•É¥¹œ½¹Ñ•áÐ4(€€€€€€€€€€€€€€€½¹ÍÐ½¹Ñ•áÐ€ô…¹Ù…Ì¹•Ñ½¹Ñ•áÐ œÉœ¤ì4(€€€€€€€€€€€€€€€½¹ÍÐÉ•¹‘•É½¹Ñ•áÐ€ôì4(€€€€€€€€€€€€€€€€€€€…¹Ù…Í½¹Ñ•áÐè½¹Ñ•áÐ°4(€€€€€€€€€€€€€€€€€€€Ù¥•ÝÁ½ÉÐèÍ…±•‘Y¥•ÝÁ½ÉÐ°4(€€€€€€€€€€€€€€€€€€€•¹…‰±•]•‰0èÑÉÕ”4(€€€€€€€€€€€€€€€ôì4(4(€€€€€€€€€€€€€€€€¼¼I•¹‘•ÈÑ¡”Á…”4(€€€€€€€€€€€€€€€½¹ÍÐÉ•¹‘•ÉQ…Í¬€ôÁ…”¹É•¹‘•È¡É•¹‘•É½¹Ñ•áÐ¤ì4(€€€€€€€€€€€€€€€…Ý…¥ÐÉ•¹‘•ÉQ…Í¬¹ÁÉ½µ¥Í”ì4(€€€€€€€€€€€€€€€€4(€€€€€€€€€€€€€€€½¹Í½±”¹±½œ AÁ…”É•¹‘•É•ÍÕ•ÍÍ™Õ±±äœ¤ì4(4(€€€€€€€€€€€€€€€€¼¼…±Õ±…Ñ”…¹±½œÁ•É™½Éµ…¹”4(€€€€€€€€€€€€€€€½¹ÍÐ•¹‘Q¥µ”€ôÁ•É™½Éµ…¹”¹¹½Ü ¤ì4(€€€€€€€€€€€€€€€½¹ÍÐ‘ÕÉ…Ñ¥½¸€ô•¹‘Q¥µ”€´ÍÑ…ÉÑQ¥µ”ì4(€€€€€€€€€€€€€€€½¹Í½±”¹±½œ¡AÁÉ•Ù¥•Ü•¹•É…Ñ•¥¸€‘í‘ÕÉ…Ñ¥½¹õµÍ€¤ì4(4(€€€€€€€€€€€€€€€€¼¼I•µ½Ù”±½…‘¥¹œ¥¹‘¥…Ñ½È4(€€€€€€€€€€€€€€€¥˜€¡±½…‘¥¹¥Ø¤ì4(€€€€€€€€€€€€€€€€€€€±½…‘¥¹¥Ø¹É•µ½Ù” ¤ì4(€€€€€€€€€€€€€€€ô4(4(€€€€€€€€€€€€€€€€¼¼‘±¥¬¡…¹‘±•ÈÑ¼½Á•¸A4(€€€€€€€€€€€€€€€½¹Ñ…¥¹•È¹…‘‘Ù•¹Ñ1¥ÍÑ•¹•È ±¥¬œ°€ ¤€ôøì4(€€€€€€€€€€€€€€€€€€€Ý¥¹‘½Ü¹½Á•¸¡Á‘™UÉ°°€}‰±…¹¬œ¤ì4(€€€€€€€€€€€€€€€ô¤ì4(4(€€€€€€€€€€€€€€€€¼¼‘ÍÕ•ÍÌ¥¹‘¥…Ñ½È4(€€€€€€€€€€€€€€€½¹Ñ…¥¹•È¹±…ÍÍ1¥ÍÐ¹…‘ ÁÉ•Ù¥•ÜµÍÕ•ÍÌœ¤ì(€€€€€€€€€€€€€€€½¹Ñ…¥¹•È¹‘…Ñ…Í•Ð¹ÁÉ•Ù¥•ÝMÑ…Ñ”€ô€±½…‘•œì((€€€€€€€€€€€ô…Ñ €¡•ÉÉ½È¤ì(€€€€€€€€€€€€€€€½¹Ñ…¥¹•È¹‘…Ñ…Í•Ð¹ÁÉ•Ù¥•ÝMÑ…Ñ”€ô€•ÉÉ½Èœì(€€€€€€€€€€€€€€€½¹Í½±”¹•ÉÉ½È ÉÉ½È±½…‘¥¹œAÁÉ•Ù¥•Üèœ°•ÉÉ½È¤ì4(€€€€€€€€€€€€€€€½¹ÍÐ±½…‘¥¹¥Ø€ô½¹Ñ…¥¹•È¹ÅÕ•ÉåM•±•Ñ½È œ¹ÁÉ•Ù¥•Üµ±½…‘¥¹œœ¤ì4(€€€€€€€€€€€€€€€¥˜€¡±½…‘¥¹¥Ø¤ì4(€€€€€€€€€€€€€€€€€€€±½…‘¥¹¥Ø¹¥¹¹•É!Q50€ô€4(€€€€€€€€€€€€€€€€€€€€€€€€ñ¤±…ÍÌô‰à‰àµ•ÉÉ½Èµ¥É±”œÍÑå±”ô‰½±½Èè€‘ŒÌÔÐÔìˆøð½¤ø4(€€€€€€€€€€€€€€€€€€€€€€€€ñÍÁ…¸ÍÑå±”ô‰½±½Èè€‘ŒÌÔÐÔìˆùÉÉ½È±½…‘¥¹œAð½ÍÁ…¸ø4(€€€€€€€€€€€€€€€€€€€€€€€€ñÍµ…±°ÍÑå±”ô‰½±½Èè€‘ŒÌÔÐÔìˆø‘í•ÉÉ½È¹µ•ÍÍ…•ôð½Íµ…±°ø4(€€€€€€€€€€€€€€€€€€€€ì4(€€€€€€€€€€€€€€€ô4(€€€€€€€€€€€€€€€€4(€€€€€€€€€€€€€€€€¼¼1½œÑ¡”•ÉÉ½ÈÝ¥Ñ ½¹Ñ•áÐ4(€€€€€€€€€€€€€€€±½ÉÉ½È AAÉ•Ù¥•ÜÉÉ½Èœ°ì4(€€€€€€€€€€€€€€€€€€€™¥±•A…Ñ °4(€€€€€€€€€€€€€€€€€€€•ÉÉ½Èè•ÉÉ½È¹µ•ÍÍ…”°4(€€€€€€€€€€€€€€€€€€€Ñ¥µ•ÍÑ…µÀè¹•Ü…Ñ” ¤¹Ñ½%M=MÑÉ¥¹œ ¤°4(€€€€€€€€€€€€€€€€€€€ÕÍ•Èè€¥É±…´œ4(€€€€€€€€€€€€€€€ô¤ì4(€€€€€€€€€€€ô4(€€€€€€€ô4(€€€ô4)ô4(4(¼¼‘Ñ¡¥Ì¡•±Á•È™Õ¹Ñ¥½¸™½È•ÉÉ½È±½¥¹œ4)™Õ¹Ñ¥½¸±½ÉÉ½È¡ÑåÁ”°‘•Ñ…¥±Ì¤ì4(€€€½¹ÍÐ•ÉÉ½É1½œ€ôì4(€€€€€€€ÑåÁ”°4(€€€€€€€‘•Ñ…¥±Ì°4(€€€€€€€Ñ¥µ•ÍÑ…µÀè€œÈÀÈÔ´ÀÄ´Èà€ÄäèÔÀèÄÜœ°4(€€€€€€€ÕÍ•Èè€¥É±…´œ4(€€€ôì4(€€€½¹Í½±”¹•ÉÉ½È ÉÉ½È1½œèœ°•ÉÉ½É1½œ¤ì4(€€€€4(€€€€¼¼e½Ôµ¥¡ÐÝ…¹ÐÑ¼Í•¹Ñ¡¥ÌÑ¼å½ÕÈÍ•ÉÙ•È4(€€€ÑÉäì4(€€€€€€€™•Ñ  œ½…Á¤½±½œµ•ÉÉ½È¹Á¡Àœ°ì4(€€€€€€€€€€€µ•Ñ¡½è€A=MPœ°4(€€€€€€€€€€€¡•…‘•ÉÌèì4(€€€€€€€€€€€€€€€€½¹Ñ•¹ÐµQåÁ”œè€…ÁÁ±¥…Ñ¥½¸½©Í½¸œ°4(€€€€€€€€€€€ô°4(€€€€€€€€€€€‰½‘äè)M=8¹ÍÑÉ¥¹¥™ä¡•ÉÉ½É1½œ¤4(€€€€€€€ô¤ì4(€€€ô…Ñ €¡”¤ì4(€€€€€€€½¹Í½±”¹•ÉÉ½È ÉÉ½ÈÍ•¹‘¥¹œ•ÉÉ½È±½œèœ°”¤ì4(€€€ô4)ô4(4(€€€€€€€™Õ¹Ñ¥½¸Í¡½Ý±•ÉÐ¡µ•ÍÍ…”°ÑåÁ”€ô€ÍÕ•ÍÌœ¤ì4(€€€€€€€€€€€½¹ÍÐ…±•ÉÐ€ô‘½Õµ•¹Ð¹É•…Ñ•±•µ•¹Ð ‘¥Øœ¤ì4(€€€€€€€€€€€…±•ÉÐ¹±…ÍÍ9…µ”€ô…±•ÉÐ…±•ÉÐ´‘íÑåÁ•ô…±•ÉÐµ‘¥Íµ¥ÍÍ¥‰±”™…‘”Í¡½Ý€ì4(€€€€€€€€€€€…±•ÉÐ¹¥¹¹•É!Q50€ô€4(€€€€€€€€€€€€€€€€ñ¤±…ÍÌô‰‰à‰à´‘íÑåÁ”€ôôô€ÍÕ•ÍÌœ€ü€¡•¬œ€è€àôµ¥É±”µ”´Äˆøð½¤ø‘íµ•ÍÍ…•ô4(€€€€€€€€€€€€€€€€ñ‰ÕÑÑ½¸ÑåÁ”ô‰‰ÕÑÑ½¸ˆ±…ÍÌô‰‰Ñ¸µ±½Í”ˆ‘…Ñ„µ‰Ìµ‘¥Íµ¥ÍÌô‰…±•ÉÐˆøð½‰ÕÑÑ½¸ø4(€€€€€€€€€€€€ì4(€€€€€€€€€€€‘½Õµ•¹Ð¹•Ñ±•µ•¹Ñ	å% …±•ÉÑ½¹Ñ…¥¹•Èœ¤¹…ÁÁ•¹‘¡¥±¡…±•ÉÐ¤ì4(€€€€€€€€€€€Í•ÑQ¥µ•½ÕÐ  ¤€ôø…±•ÉÐ¹É•µ½Ù” ¤°€ÔÀÀÀ¤ì4(€€€€€€€ô4(4(€€€€€€€™Õ¹Ñ¥½¸•‘¥Ñ±½½ÉA±…¸¡Á±…¸¤ì4(€€€€€€€€€€€½¹Í½±”¹±½œ ‘¥ÐÁ±…¸‘…Ñ„èœ°Á±…¸¤ì4(€€€€€€€€€€€€4(€€€€€€€€€€€¥˜€ …Á±…¸ñð€…Á±…¸¹¥¤ì4(€€€€€€€€€€€€€€€½¹Í½±”¹•ÉÉ½È %¹Ù…±¥Á±…¸‘…Ñ„èœ°Á±…¸¤ì4(€€€€€€€€€€€€€€€Í¡½Ý±•ÉÐ %¹Ù…±¥™±½½ÈÁ±…¸‘…Ñ„œ°€‘…¹•Èœ¤ì4(€€€€€€€€€€€€€€€É•ÑÕÉ¸ì4(€€€€€€€€€€€ô4(4(€€€€€€€€€€€‘½Õµ•¹Ð¹•Ñ±•µ•¹Ñ	å% •‘¥Ñ%œ¤¹Ù…±Õ”€ôÁ±…¸¹¥ì4(€€€€€€€€€€€‘½Õµ•¹Ð¹•Ñ±•µ•¹Ñ	å% •‘¥ÑAÉ½©•Ñ%œ¤¹Ù…±Õ”€ôÁ±…¸¹ÁÉ½©•Ñ}¥ì4(€€€€€€€€€€€‘½Õµ•¹Ð¹•Ñ±•µ•¹Ñ	å% •‘¥Ñ9…µ”œ¤¹Ù…±Õ”€ôÁ±…¸¹™±½½É}¹…µ”ì4(€€€€€€€€€€€‘½Õµ•¹Ð¹•Ñ±•µ•¹Ñ	å% •‘¥Ñ1•Ù•°œ¤¹Ù…±Õ”€ôÁ±…¸¹±•Ù•°ì4(€€€€€€€€€€€‘½Õµ•¹Ð¹•Ñ±•µ•¹Ñ	å% •‘¥Ñ•ÍÉ¥ÁÑ¥½¸œ¤¹Ù…±Õ”€ôÁ±…¸¹‘•ÍÉ¥ÁÑ¥½¸ñð€œœì4(€€€€€€€€€€€€4(€€€€€€€€€€€€¼¼M¡½Ü™¥±”ÍÑ…ÑÕÌÝ…É¹¥¹œ¥˜™¥±”‘½•Í¸Ð•á¥ÍÐ4(€€€€€€€€€€€½¹ÍÐ™¥±•MÑ…ÑÕÍ±•ÉÐ€ô‘½Õµ•¹Ð¹•Ñ±•µ•¹Ñ	å% ™¥±•MÑ…ÑÕÍ±•ÉÐœ¤ì4(€€€€€€€€€€€¥˜€ …Á±…¸¹™¥±•}•á¥ÍÑÌ¤ì4(€€€€€€€€€€€€€€€™¥±•MÑ…ÑÕÍ±•ÉÐ¹±…ÍÍ1¥ÍÐ¹É•µ½Ù” µ¹½¹”œ¤ì4(€€€€€€€€€€€ô•±Í”ì4(€€€€€€€€€€€€€€€™¥±•MÑ…ÑÕÍ±•ÉÐ¹±…ÍÍ1¥ÍÐ¹…‘ µ¹½¹”œ¤ì4(€€€€€€€€€€€ô4(€€€€€€€€€€€€4(€€€€€€€€€€€½¹ÍÐµ½‘…°€ô¹•Ü‰½½ÑÍÑÉ…À¹5½‘…°¡‘½Õµ•¹Ð¹•Ñ±•µ•¹Ñ	å% •‘¥Ñ±½½ÉA±…¹5½‘…°œ¤¤ì4(€€€€€€€€€€€µ½‘…°¹Í¡½Ü ¤ì4(€€€€€€€ô4(4(€€€€€€€‘½Õµ•¹Ð¹•Ñ±•µ•¹Ñ	å% •‘¥Ñ±½½ÉA±…¹½É´œ¤¹…‘‘Ù•¹Ñ1¥ÍÑ•¹•È ÍÕ‰µ¥Ðœ°…Íå¹Œ™Õ¹Ñ¥½¸¡”¤ì4(€€€€€€€€€€€”¹ÁÉ•Ù•¹Ñ•™…Õ±Ð ¤ì4(€€€€€€€€€€€€4(€€€€€€€€€€€½¹ÍÐ™½Éµ…Ñ„€ô¹•Ü½Éµ…Ñ„¡Ñ¡¥Ì¤ì4(€€€€€€€€€€€™½Éµ…Ñ„¹…ÁÁ•¹ ÕÁ‘…Ñ•‘}…Ðœ°¹•Ü…Ñ” ¤¹Ñ½%M=MÑÉ¥¹œ ¤¤ì4(€€€€€€€€€€€™½Éµ…Ñ„¹…ÁÁ•¹ ÕÁ‘…Ñ•‘}‰äœ°€œðýÁ¡À•¡¼€‘}MMM%=9lÕÍ•É}¥tì€üøœ¤ì4(4(€€€€€€€€€€€ÑÉäì4(€€€€€€€€€€€€€€€½¹ÍÐÉ•ÍÁ½¹Í”€ô…Ý…¥Ð™•Ñ  …Á¤½ÕÁ‘…Ñ•}™±½½É}Á±…¸¹Á¡Àœ°ì4(€€€€€€€€€€€€€€€€€€€µ•Ñ¡½è€A=MPœ°4(€€€€€€€€€€€€€€€€€€€‰½‘äè™½Éµ…Ñ„4(€€€€€€€€€€€€€€€ô¤ì4(€€€€€€€€€€€€€€€€4(€€€€€€€€€€€€€€€½¹ÍÐÉ•ÍÕ±Ð€ô…Ý…¥ÐÉ•ÍÁ½¹Í”¹©Í½¸ ¤ì4(€€€€€€€€€€€€€€€½¹Í½±”¹±½œ UÁ‘…Ñ”É•ÍÁ½¹Í”èœ°É•ÍÕ±Ð¤ì4(€€€€€€€€€€€€€€€€4(€€€€€€€€€€€€€€€¥˜€¡É•ÍÕ±Ð¹ÍÕ•ÍÌ¤ì4(€€€€€€€€€€€€€€€€€€€Í¡½Ý±•ÉÐ ±½½ÈÁ±…¸ÕÁ‘…Ñ•ÍÕ•ÍÍ™Õ±±äœ¤ì4(€€€€€€€€€€€€€€€€€€€±½…Ñ¥½¸¹É•±½… ¤ì4(€€€€€€€€€€€€€€€ô•±Í”ì4(€€€€€€€€€€€€€€€€€€€Í¡½Ý±•ÉÐ¡É•ÍÕ±Ð¹µ•ÍÍ…”ñð€ÉÉ½ÈÕÁ‘…Ñ¥¹œ™±½½ÈÁ±…¸œ°€‘…¹•Èœ¤ì4(€€€€€€€€€€€€€€€ô4(€€€€€€€€€€€ô…Ñ €¡•ÉÉ½È¤ì4(€€€€€€€€€€€€€€€½¹Í½±”¹•ÉÉ½È ÉÉ½Èèœ°•ÉÉ½È¤ì4(€€€€€€€€€€€€€€€Í¡½Ý±•ÉÐ ÉÉ½ÈÕÁ‘…Ñ¥¹œ™±½½ÈÁ±…¸œ°€‘…¹•Èœ¤ì4(€€€€€€€€€€€ô4(€€€€€€€ô¤ì4(4(€€€€€€€™Õ¹Ñ¥½¸‘•±•Ñ•±½½ÉA±…¸¡¥¤ì4(€€€€€€€€€€€½¹Í½±”¹±½œ •±•Ñ¥¹œ™±½½ÈÁ±…¸%èœ°¥¤ì4(€€€€€€€€€€€€4(€€€€€€€€€€€¥˜€ …¥ñð¥Í9…8¡Á…ÉÍ•%¹Ð¡¥¤¤¤ì4(€€€€€€€€€€€€€€€½¹Í½±”¹•ÉÉ½È %¹Ù…±¥™±½½ÈÁ±…¸%èœ°¥¤ì4(€€€€€€€€€€€€€€€Í¡½Ý±•ÉÐ %¹Ù…±¥™±½½ÈÁ±…¸%œ°€‘…¹•Èœ¤ì4(€€€€€€€€€€€€€€€É•ÑÕÉ¸ì4(€€€€€€€€€€€ô4(4(€€€€€€€€€€€¥˜€¡½¹™¥É´ É”å½ÔÍÕÉ”å½ÔÝ…¹ÐÑ¼‘•±•Ñ”Ñ¡¥Ì™±½½ÈÁ±…¸üQ¡¥Ì…Ñ¥½¸…¹¹½Ð‰”Õ¹‘½¹”¸œ¤¤ì4(€€€€€€€€€€€€€€€½¹ÍÐ™½Éµ…Ñ„€ô¹•Ü½Éµ…Ñ„ ¤ì4(€€€€€€€€€€€€€€€™½Éµ…Ñ„¹…ÁÁ•¹ ¥œ°¥¤ì4(€€€€€€€€€€€€€€€™½Éµ…Ñ„¹…ÁÁ•¹ ÍÉ™}Ñ½­•¸œ°‘½Õµ•¹Ð¹ÅÕ•ÉåM•±•Ñ½È ¥¹ÁÕÑm¹…µ”ô‰ÍÉ™}Ñ½­•¸‰tœ¤¹Ù…±Õ”¤ì4(€€€€€€€€€€€€€€€€4(€€€€€€€€€€€€€€€™•Ñ  …Á¤½‘•±•Ñ•}™±½½É}Á±…¸¹Á¡Àœ°ì4(€€€€€€€€€€€€€€€€€€€µ•Ñ¡½è€A=MPœ°4(€€€€€€€€€€€€€€€€€€€‰½‘äè™½Éµ…Ñ„4(€€€€€€€€€€€€€€€ô¤4(€€€€€€€€€€€€€€€€¹Ñ¡•¸¡É•ÍÁ½¹Í”€ôøÉ•ÍÁ½¹Í”¹©Í½¸ ¤¤4(€€€€€€€€€€€€€€€€¹Ñ¡•¸¡É•ÍÕ±Ð€ôøì4(€€€€€€€€€€€€€€€€€€€½¹Í½±”¹±½œ •±•Ñ”É•ÍÁ½¹Í”èœ°É•ÍÕ±Ð¤ì4(€€€€€€€€€€€€€€€€€€€¥˜€¡É•ÍÕ±Ð¹ÍÕ•ÍÌ¤ì4(€€€€€€€€€€€€€€€€€€€€€€€Í¡½Ý±•ÉÐ ±½½ÈÁ±…¸‘•±•Ñ•ÍÕ•ÍÍ™Õ±±äœ¤ì4(€€€€€€€€€€€€€€€€€€€€€€€€¼¼…‘”½ÕÐÑ¡”‘•±•Ñ•É½Ü‰•™½É”É•±½…‘¥¹œ4(€€€€€€€€€€€€€€€€€€€€€€€½¹ÍÐÉ½Ü€ô€¡€™±½½ÉA±…¹ÍQ…‰±”ÑÈé¡…Ì¡‰ÕÑÑ½¹m½¹±¥¬¨ôˆ‘í¥‘ô‰t¥€¤ì4(€€€€€€€€€€€€€€€€€€€€€€€É½Ü¹™…‘•=ÕÐ ÐÀÀ°€ ¤€ôøì4(€€€€€€€€€€€€€€€€€€€€€€€€€€€±½…Ñ¥½¸¹É•±½… ¤ì4(€€€€€€€€€€€€€€€€€€€€€€€ô¤ì4(€€€€€€€€€€€€€€€€€€€ô•±Í”ì4(€€€€€€€€€€€€€€€€€€€€€€€Í¡½Ý±•ÉÐ¡É•ÍÕ±Ð¹µ•ÍÍ…”ñð€ÉÉ½È‘•±•Ñ¥¹œ™±½½ÈÁ±…¸œ°€‘…¹•Èœ¤ì4(€€€€€€€€€€€€€€€€€€€ô4(€€€€€€€€€€€€€€€ô¤4(€€€€€€€€€€€€€€€€¹…Ñ ¡•ÉÉ½È€ôøì4(€€€€€€€€€€€€€€€€€€€½¹Í½±”¹•ÉÉ½È ÉÉ½Èèœ°•ÉÉ½È¤ì4(€€€€€€€€€€€€€€€€€€€Í¡½Ý±•ÉÐ ÉÉ½È‘•±•Ñ¥¹œ™±½½ÈÁ±…¸œ°€‘…¹•Èœ¤ì4(€€€€€€€€€€€€€€€ô¤ì4(€€€€€€€€€€€ô4(€€€€€€€ô4(4(€€€€€€€€¼¼‘Ñ½½±Ñ¥ÁÌ™½È™¥±”¥¹™¼4(€€€€€€€½¹ÍÐÑ½½±Ñ¥ÁQÉ¥•É1¥ÍÐ€ômt¹Í±¥”¹…±°¡‘½Õµ•¹Ð¹ÅÕ•ÉåM•±•Ñ½É±° m‘…Ñ„µ‰ÌµÑ½±”ô‰Ñ½½±Ñ¥À‰tœ¤¤ì4(€€€€€€€½¹ÍÐÑ½½±Ñ¥Á1¥ÍÐ€ôÑ½½±Ñ¥ÁQÉ¥•É1¥ÍÐ¹µ…À¡™Õ¹Ñ¥½¸€¡Ñ½½±Ñ¥ÁQÉ¥•É°¤ì4(€€€€€€€€€€€É•ÑÕÉ¸¹•Ü‰½½ÑÍÑÉ…À¹Q½½±Ñ¥À¡Ñ½½±Ñ¥ÁQÉ¥•É°¤ì4(€€€€€€€ô¤ì4(4(€€€€€€€€¼¼!…¹‘±”AÁÉ•Ù¥•Ü•ÉÉ½ÉÌ±½‰…±±ä4(€€€€€€€Ý¥¹‘½Ü¹…‘‘Ù•¹Ñ1¥ÍÑ•¹•È Õ¹¡…¹‘±•‘É•©•Ñ¥½¸œ°™Õ¹Ñ¥½¸¡•Ù•¹Ð¤ì4(€€€€€€€€€€€¥˜€¡•Ù•¹Ð¹É•…Í½¸€˜˜•Ù•¹Ð¹É•…Í½¸¹µ•ÍÍ…”€˜˜•Ù•¹Ð¹É•…Í½¸¹µ•ÍÍ…”¹¥¹±Õ‘•Ì A¹©Ìœ¤¤ì4(€€€€€€€€€€€€€€€½¹Í½±”¹•ÉÉ½È A¹©ÌÉÉ½Èèœ°•Ù•¹Ð¹É•…Í½¸¤ì4(€€€€€€€€€€€€€€€Í¡½Ý±•ÉÐ ÉÉ½È±½…‘¥¹œAÁÉ•Ù¥•Ü¸A±•…Í”¡•¬¥˜Ñ¡”™¥±”¥ÌÙ…±¥¸œ°€Ý…É¹¥¹œœ¤ì4(€€€€€€€€€€€ô4(€€€€€€€ô¤ì4(4(€€€€€€€€¼¼1½œÕÍ•È…Ñ¥Ù¥Ñä4(€€€€€€€™Õ¹Ñ¥½¸±½UÍ•ÉÑ¥Ù¥Ñä¡…Ñ¥½¸°‘•Ñ…¥±Ì¤ì4(€€€€€€€€€€€½¹Í½±”¹±½œ¡l‘í¹•Ü…Ñ” ¤¹Ñ½%M=MÑÉ¥¹œ ¥õtUÍ•Èè¥É±…´€´Ñ¥½¸è€‘í…Ñ¥½¹õ€°‘•Ñ…¥±Ì¤ì4(€€€€€€€ô4(4(€€€€€€€€¼¼5½¹¥Ñ½ÈA±½…‘¥¹œÁ•É™½Éµ…¹”4(€€€€€€€½¹ÍÐÁ•É™½Éµ…¹•¹ÑÉ¥•Ì€ômtì4(€€€€€€€™Õ¹Ñ¥½¸±½A1½…‘¥¹A•É™½Éµ…¹”¡™¥±•A…Ñ °ÍÑ…ÉÑQ¥µ”°•¹‘Q¥µ”¤ì4(€€€€€€€€€€€½¹ÍÐ‘ÕÉ…Ñ¥½¸€ô•¹‘Q¥µ”€´ÍÑ…ÉÑQ¥µ”ì4(€€€€€€€€€€€Á•É™½Éµ…¹•¹ÑÉ¥•Ì¹ÁÕÍ ¡ì4(€€€€€€€€€€€€€€€™¥±•A…Ñ °4(€€€€€€€€€€€€€€€‘ÕÉ…Ñ¥½¸°4(€€€€€€€€€€€€€€€Ñ¥µ•ÍÑ…µÀè¹•Ü…Ñ” ¤¹Ñ½%M=MÑÉ¥¹œ ¤4(€€€€€€€€€€€ô¤ì4(€€€€€€€€€€€€4(€€€€€€€€€€€¥˜€¡‘ÕÉ…Ñ¥½¸€ø€ÈÀÀÀ¤ì€¼¼%˜±½…‘¥¹œÑ…­•Ìµ½É”Ñ¡…¸€ÈÍ•½¹‘Ì4(€€€€€€€€€€€€€€€½¹Í½±”¹Ý…É¸¡M±½ÜA±½…‘¥¹œ‘•Ñ•Ñ•™½È€‘í™¥±•A…Ñ¡ôè€‘í‘ÕÉ…Ñ¥½¹õµÍ€¤ì4(€€€€€€€€€€€ô4(€€€€€€€ô4(4(€€€€€€€€¼¼±•…¹ÕÀ™Õ¹Ñ¥½¸™½ÈÁ…”Õ¹±½…4(€€€€€€€Ý¥¹‘½Ü¹…‘‘Ù•¹Ñ1¥ÍÑ•¹•È ‰•™½É•Õ¹±½…œ°™Õ¹Ñ¥½¸ ¤ì4(€€€€€€€€€€€€¼¼1½œÁ•É™½Éµ…¹”‘…Ñ„¥˜…¹ä•¹ÑÉ¥•Ì•á¥ÍÐ4(€€€€€€€€€€€¥˜€¡Á•É™½Éµ…¹•¹ÑÉ¥•Ì¹±•¹Ñ €ø€À¤ì4(€€€€€€€€€€€€€€€½¹Í½±”¹±½œ A1½…‘¥¹œA•É™½Éµ…¹”MÕµµ…Éäèœ°ì4(€€€€€€€€€€€€€€€€€€€Ñ½Ñ…±¥±•ÌèÁ•É™½Éµ…¹•¹ÑÉ¥•Ì¹±•¹Ñ °4(€€€€€€€€€€€€€€€€€€€…Ù•É…•ÕÉ…Ñ¥½¸èÁ•É™½Éµ…¹•¹ÑÉ¥•Ì¹É•‘Õ” ¡…Œ°ÕÉÈ¤€ôø…Œ€¬ÕÉÈ¹‘ÕÉ…Ñ¥½¸°€À¤€¼Á•É™½Éµ…¹•¹ÑÉ¥•Ì¹±•¹Ñ °4(€€€€€€€€€€€€€€€€€€€Í±½Ý1½…‘ÌèÁ•É™½Éµ…¹•¹ÑÉ¥•Ì¹™¥±Ñ•È¡•¹ÑÉä€ôø•¹ÑÉä¹‘ÕÉ…Ñ¥½¸€ø€ÈÀÀÀ¤¹±•¹Ñ 4(€€€€€€€€€€€€€€€ô¤ì4(€€€€€€€€€€€ô4(€€€€€€€ô¤ì4(€€€€ð½ÍÉ¥ÁÐø4(ð½‰½‘äø4(ð½¡Ñµ°ø(
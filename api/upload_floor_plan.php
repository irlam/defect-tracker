<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
error_log("API endpoint hit");
if (!isset($_SESSION['user_id'])) {
    error_log("User not authenticated");
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
error_log("User authenticated");

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/PdfConverter.php';
error_log("Database and PdfConverter configs loaded");

/**
 * Function to format bytes into human-readable form.
 *
 * @param int $bytes File size in bytes.
 * @param int $precision Number of decimal places.
 *
 * @return string Formatted file size.
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];

    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);

    $bytes /= (1 << (10 * $pow));

    return round($bytes, $precision) . ' ' . $units[$pow];
}

// Check for file upload
if (!isset($_FILES['floor_plan']) || $_FILES['floor_plan']['error'] !== UPLOAD_ERR_OK) {
    error_log("File upload failed");
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'File upload failed']);
    exit;
}
error_log("File upload detected");

// Required fields check
if (empty($_POST['project_id']) || empty($_POST['floor_name']) || empty($_POST['level'])) {
    error_log("Missing required fields");
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}
error_log("Required fields present");

try {
    // Initialize database
    $database = new Database();
    $db = $database->getConnection();
    error_log("Database connection established");

    // Get project name
    $stmt = $db->prepare("SELECT name FROM projects WHERE id = ?");
    $stmt->execute([$_POST['project_id']]);
    $projectName = $stmt->fetchColumn();
    error_log("Project name retrieved");

    if (!$projectName) {
        error_log("Invalid project ID");
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid project ID']);
        exit;
    }

    // Set up paths
    $projectSlug = preg_replace('/[^a-zA-Z0-9]+/', '-', strtolower($projectName));
    $yearMonth = date('Y/m');
    $timestamp = date('Ymd_His');
    $extension = strtolower(pathinfo($_FILES['floor_plan']['name'], PATHINFO_EXTENSION));
    $safeFloorName = preg_replace('/[^a-z0-9]+/', '-', strtolower($_POST['floor_name']));

    // Create directory paths
    $uploadDir = dirname(__DIR__) . '/uploads/floor_plans/' . $projectSlug . '/' . $yearMonth;
    $imageDir = dirname(__DIR__) . '/uploads/floor_plan_images';

    // Create directories
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
        error_log("Upload directory created: " . $uploadDir);
    }
    if (!file_exists($imageDir)) {
        mkdir($imageDir, 0755, true);
        error_log("Image directory created: " . $imageDir);
    }

    // Generate filenames
    $filename = "{$projectSlug}_{$safeFloorName}_{$timestamp}.{$extension}";
    $uploadPath = $uploadDir . '/' . $filename;
    $relativePath = 'uploads/floor_plans/' . $projectSlug . '/' . $yearMonth . '/' . $filename;

    // Get file size and type BEFORE moving the file
    $fileSizeBytes = $_FILES['floor_plan']['size']; // Get the file size in bytes
    $fileSize = formatBytes($fileSizeBytes); // Format the file size
    $fileType = $_FILES['floor_plan']['type'];
    $originalFilename = $_FILES['floor_plan']['name']; // Get the original filename

    // Move uploaded file - MOVED UP
    if (!move_uploaded_file($_FILES['floor_plan']['tmp_name'], $uploadPath)) {
        error_log("Failed to save file");
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to save file']);
        exit;
    }
    error_log("File saved to: " . $uploadPath);

    // Handle PDF conversion
    $imagePath = null;
    $imageUploadPath = null;

    if ($_FILES['floor_plan']['type'] === 'application/pdf') {
        try {
            $pdfConverter = new PdfConverter();
            $imageFilename = pathinfo($filename, PATHINFO_FILENAME) . '.png';
            $imageUploadPath = $imageDir . '/' . $imageFilename;  // Full server path (but not used directly)
            error_log("imageUploadPath: " . $imageUploadPath); // Log it

            $result = $pdfConverter->convertPdfToImage($uploadPath, $imageDir);
            error_log("PDF conversion attempted");

            if ($result['status'] === 'success') {
                $fullImagePath = $result['path']; // Full server path from PdfConverter
                $imagePath = str_replace(dirname(__DIR__) . '/', '', $fullImagePath); // Convert to relative path

                error_log("Full Image Path: " . $fullImagePath);
                error_log("Relative imagePath: " . $imagePath); // Log it
            } else {
                error_log("PDF Conversion Failed: status is not success");
            }
        } catch (Exception $e) {
            error_log("PDF Conversion Error: " . $e->getMessage());
        }
    } else {
        $imagePath = $relativePath; // if not PDF, the original file is the image
        error_log("No PDF conversion needed");
    }

    // Insert into database
    $projectId = $_POST['project_id'];
    $floorName = $_POST['floor_name'];
    $level = $_POST['level'];
    $uploadedBy = $_SESSION['user_id']; // Get the user ID from the session

    $sql = "INSERT INTO floor_plans (project_id, floor_name, level, file_path, image_path, uploaded_by, file_size, file_type, original_filename) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $db->prepare($sql);
    $stmt->execute([$projectId, $floorName, $level, $relativePath, $imagePath, $uploadedBy, $fileSize, $fileType, $originalFilename]);
    error_log("Database insertion attempted");

    // Check if the insertion was successful
    if ($stmt->rowCount() > 0) {
        error_log("Database insertion successful");
    } else {
        error_log("Database insertion failed");
    }

    // Success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Floor plan uploaded successfully',
        'data' => [
            'file_path' => $relativePath,
            'image_path' => $imagePath, // Include image path in response
        ],
        'timestamp' => date('d-m-Y H:i:s')
    ]);
    error_log("Success response sent");

} catch (Exception $e) {
    error_log("Exception: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    
    // Only delete the uploaded file if there was an error
    if (isset($uploadPath) && file_exists($uploadPath)) {
        unlink($uploadPath);
    }
}
exit;
?>
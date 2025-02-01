<?php
// api/upload_floor_plan.php
// Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): 2025-01-20 19:50:54
// Current User's Login: irlam

// Prevent any output before headers
ob_clean();

// Set error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Include required files
require_once '../config/database.php';
require_once '../includes/upload_constants.php';

// Function to send JSON response and exit
function sendJsonResponse($success, $message, $data = null, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

// Log function
function logError($message, $context = []) {
    $logFile = __DIR__ . '/../logs/upload_errors.log';
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? ' | ' . json_encode($context) : '';
    error_log("[$timestamp] $message$contextStr\n", 3, $logFile);
}

try {
    // Check authentication
    if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
        sendJsonResponse(false, 'Unauthorized access', null, 401);
    }

    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJsonResponse(false, 'Invalid request method', null, 405);
    }

    // Check for file upload
    if (!isset($_FILES['floor_plan']) || $_FILES['floor_plan']['error'] !== UPLOAD_ERR_OK) {
        $error = isset($_FILES['floor_plan']['error']) ? $_FILES['floor_plan']['error'] : 'No file uploaded';
        logError('File upload error', ['error' => $error]);
        sendJsonResponse(false, 'File upload failed', null, 400);
    }

    // Validate required fields
    if (empty($_POST['project_id']) || empty($_POST['floor_name'])) {
        sendJsonResponse(false, 'Missing required fields', null, 400);
    }

    // Initialize database connection
    $database = new Database();
    $db = $database->getConnection();

    // Validate file type
    $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($fileInfo, $_FILES['floor_plan']['tmp_name']);
    finfo_close($fileInfo);

    if (!array_key_exists($mimeType, ALLOWED_MIME_TYPES)) {
        sendJsonResponse(false, 'Invalid file type', null, 400);
    }

    // Check file size
    if ($_FILES['floor_plan']['size'] > MAX_FILE_SIZE) {
        sendJsonResponse(false, 'File size exceeds limit', null, 400);
    }

    // Get project details
    $stmt = $db->prepare("SELECT name FROM projects WHERE id = ?");
    $stmt->execute([$_POST['project_id']]);
    $projectName = $stmt->fetchColumn();

    if (!$projectName) {
        sendJsonResponse(false, 'Invalid project ID', null, 400);
    }

    // Create upload directory structure
    $projectSlug = preg_replace('/[^a-zA-Z0-9]+/', '-', strtolower($projectName));
    $yearMonth = date('Y/m');
    $uploadDir = dirname(__DIR__) . '/uploads/floor_plans/' . $projectSlug . '/' . $yearMonth;

    if (!file_exists($uploadDir) && !mkdir($uploadDir, 0755, true)) {
        logError('Failed to create directory', ['dir' => $uploadDir]);
        sendJsonResponse(false, 'Server configuration error', null, 500);
    }

    // Generate filename
    $extension = ALLOWED_MIME_TYPES[$mimeType];
    $filename = sprintf('%s_%s_%s.%s',
        $projectSlug,
        preg_replace('/[^a-zA-Z0-9]+/', '-', strtolower($_POST['floor_name'])),
        date('Ymd_His'),
        $extension
    );

    $uploadPath = $uploadDir . '/' . $filename;
    $relativePath = 'uploads/floor_plans/' . $projectSlug . '/' . $yearMonth . '/' . $filename;

    // Move uploaded file
    if (!move_uploaded_file($_FILES['floor_plan']['tmp_name'], $uploadPath)) {
        logError('Failed to move uploaded file', ['path' => $uploadPath]);
        sendJsonResponse(false, 'Failed to save file', null, 500);
    }

    // Database transaction
    $db->beginTransaction();

    try {
        $stmt = $db->prepare("
            INSERT INTO floor_plans (
                project_id,
                floor_name,
                level,
                file_path,
                uploaded_by,
                file_size,
                file_type,
                description,
                status,
                original_filename,
                created_by
            ) VALUES (
                :project_id,
                :floor_name,
                :level,
                :file_path,
                :uploaded_by,
                :file_size,
                :file_type,
                :description,
                'active',
                :original_filename,
                :created_by
            )
        ");

        $params = [
            ':project_id' => $_POST['project_id'],
            ':floor_name' => $_POST['floor_name'],
            ':level' => $_POST['floor_name'],
            ':file_path' => $relativePath,
            ':uploaded_by' => $_SESSION['user_id'],
            ':file_size' => $_FILES['floor_plan']['size'],
            ':file_type' => $extension,
            ':description' => $_POST['description'] ?? null,
            ':original_filename' => $_FILES['floor_plan']['name'],
            ':created_by' => $_SESSION['user_id']
        ];

        if (!$stmt->execute($params)) {
            throw new Exception("Database error: " . implode(", ", $stmt->errorInfo()));
        }

        $floorPlanId = $db->lastInsertId();
        $db->commit();

        sendJsonResponse(true, 'Floor plan uploaded successfully', [
            'id' => $floorPlanId,
            'path' => $relativePath
        ]);

    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    logError($e->getMessage(), ['trace' => $e->getTraceAsString()]);
    
    if (isset($uploadPath) && file_exists($uploadPath)) {
        unlink($uploadPath);
    }
    
    sendJsonResponse(false, 'Upload failed: ' . $e->getMessage(), null, 500);
}
?>
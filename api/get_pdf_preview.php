<?php
/**
 * api/get_pdf_preview.php
 * PDF Preview Handler for Floor Plans
 * Current Date and Time (UTC): 2025-01-28 21:55:45
 * Current User's Login: irlam
 */

declare(strict_types=1);

// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';

try {
    // Check authentication
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Authentication required', 401);
    }

    // Validate floor plan ID
    $floorPlanId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$floorPlanId) {
        throw new Exception('Invalid floor plan ID', 400);
    }

    // Get database connection
    $database = new Database();
    $db = $database->getConnection();

    // Get floor plan data with error logging
    $stmt = $db->prepare("
        SELECT 
            fp.id,
            fp.project_id,
            fp.floor_name,
            fp.level,
            fp.file_path,
            fp.file_type,
            fp.original_filename,
            fp.status,
            p.name as project_name,
            p.status as project_status
        FROM floor_plans fp
        JOIN projects p ON fp.project_id = p.id
        WHERE fp.id = :id
        AND fp.status = 'active'
        AND p.status = 'active'
        LIMIT 1
    ");
    
    if (!$stmt) {
        error_log("PDO Error Info: " . print_r($db->errorInfo(), true));
        throw new Exception('Database query preparation failed', 500);
    }

    $stmt->execute([':id' => $floorPlanId]);
    $floorPlan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$floorPlan) {
        throw new Exception('Floor plan not found', 404);
    }

    // Log the file path for debugging
    error_log("Attempting to access file: " . $floorPlan['file_path']);

    // Validate file path
    $filePath = '../' . ltrim($floorPlan['file_path'], '/');
    
    // Log full server path for debugging
    error_log("Full server path: " . realpath($filePath));

    if (!file_exists($filePath)) {
        throw new Exception(sprintf(
            'File not found on server. Path: %s, Exists: %s, Readable: %s',
            $filePath,
            file_exists($filePath) ? 'Yes' : 'No',
            is_readable($filePath) ? 'Yes' : 'No'
        ), 404);
    }

    if (!is_readable($filePath)) {
        error_log("File permissions: " . decoct(fileperms($filePath)));
        throw new Exception('File is not readable', 403);
    }

    // Check file type
    $allowedMimeTypes = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/jpg'
    ];

    $mimeType = mime_content_type($filePath);
    error_log("File mime type: " . $mimeType);
    
    if (!in_array($mimeType, $allowedMimeTypes)) {
        throw new Exception("Invalid file type: {$mimeType}", 415);
    }

    // Get file details
    $fileSize = filesize($filePath);
    $fileName = $floorPlan['original_filename'] ?? basename($floorPlan['file_path']);

    // Set headers for file streaming
    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . $fileSize);
    header('Content-Disposition: inline; filename="' . $fileName . '"');
    header('Cache-Control: public, max-age=3600');
    header('Accept-Ranges: bytes');

    // Stream the file
    if (readfile($filePath) === false) {
        throw new Exception('Failed to read file', 500);
    }
    exit;

} catch (PDOException $e) {
    error_log(sprintf(
        "Database Error [%s]: %s\nSQL State: %s\nTrace:\n%s",
        date('Y-m-d H:i:s'),
        $e->getMessage(),
        $e->getCode(),
        $e->getTraceAsString()
    ));
    
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => true,
        'message' => 'Database error occurred',
        'debug' => DEBUG ? $e->getMessage() : null
    ]);
    exit;

} catch (Exception $e) {
    $code = (int) $e->getCode();
    if ($code < 100 || $code > 599) {
        $code = 500;
    }

    error_log(sprintf(
        "PDF Preview Error [%s]: %s\nCode: %d\nTrace:\n%s",
        date('Y-m-d H:i:s'),
        $e->getMessage(),
        $code,
        $e->getTraceAsString()
    ));

    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage(),
        'code' => $code,
        'debug' => DEBUG ? [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ] : null
    ]);
    exit;
}
?>
<?php
// api/get_floor_plan_image.php
// Current Date and Time (UTC): 2025-01-18 00:37:15
// Current User's Login: irlam

require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../includes/PdfConverter.php';

header('Content-Type: application/json');

// Initialize session and check authentication
session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Validate and get the floor plan ID
    $input = json_decode(file_get_contents('php://input'), true);
    $floorPlanId = filter_var($input['id'], FILTER_VALIDATE_INT);
    if (!$floorPlanId) {
        throw new Exception('Invalid floor plan ID');
    }

    // Get floor plan details
    $stmt = $db->prepare("
        SELECT id, file_path, image_path 
        FROM floor_plans 
        WHERE id = ? AND status = 'active'
    ");
    $stmt->execute([$floorPlanId]);
    $floorPlan = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$floorPlan) {
        throw new Exception('Floor plan not found');
    }

    // Check if we already have a converted image
    if (!empty($floorPlan['image_path']) && file_exists('../' . $floorPlan['image_path'])) {
        echo json_encode([
            'status' => 'success',
            'data' => ['file_path' => $floorPlan['image_path']]
        ]);
        exit();
    }

    // Convert PDF to image if needed
    $pdfConverter = new PdfConverter();
    $outputDir = '../assets/floor_plans/converted';
    
    $result = $pdfConverter->convertPdfToImage(
        '../' . $floorPlan['file_path'],
        $outputDir
    );

    if ($result['status'] === 'success') {
        // Update database with image path
        $stmt = $db->prepare("
            UPDATE floor_plans 
            SET image_path = ? 
            WHERE id = ?
        ");
        $stmt->execute([
            str_replace('../', '', $result['image_path']), // Store relative path
            $floorPlanId
        ]);

        echo json_encode([
            'status' => 'success',
            'data' => ['file_path' => str_replace('../', '', $result['image_path'])]
        ]);
    } else {
        throw new Exception('Failed to convert PDF to image');
    }

} catch (Exception $e) {
    error_log("Error in get_floor_plan_image.php: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
<?php
// api/save_pin_location.php

header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/generate_defect_image.php';
require_once '../includes/upload_constants.php'; // Include upload constants

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$defectId = filter_var($data['defect_id'] ?? null, FILTER_VALIDATE_INT);
$pinX = filter_var($data['x'] ?? null, FILTER_VALIDATE_FLOAT);
$pinY = filter_var($data['y'] ?? null, FILTER_VALIDATE_FLOAT);

if (!$defectId || !isset($pinX) || !isset($pinY)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']);
    exit;
}
try {
    $database = new Database();
    $db = $database->getConnection();
    
    $stmt = $db->prepare("UPDATE defects SET pin_x = :pin_x, pin_y = :pin_y WHERE id = :id");
    $result = $stmt->execute([
        ':pin_x' => $pinX,
        ':pin_y' => $pinY,
        ':id' => $defectId
    ]);

    if ($result) {
        // Generate the updated image with the new pin location
        $imageGenerator = new DefectImageGenerator($defectId);
        $filename = $imageGenerator->generate();

        echo json_encode(['status' => 'success', 'image' => $filename]);
    } else {
        throw new Exception('Failed to update pin location');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
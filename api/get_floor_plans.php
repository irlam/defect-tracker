<?php
declare(strict_types=1);

/**
 * api/get_floor_plans.php
 * Floor Plans Retrieval API
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';

try {
    // Validate authentication
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Authentication required', 401);
    }

    // Get project_id from POST or GET
    $projectId = filter_input(INPUT_POST, 'project_id', FILTER_VALIDATE_INT) 
               ?? filter_input(INPUT_GET, 'project_id', FILTER_VALIDATE_INT);
    
    if (!$projectId) {
        throw new Exception('Project ID is required');
    }

    $database = new Database();
    $db = $database->getConnection();

    // Get floor plans
    $stmt = $db->prepare("
        SELECT 
            fp.id,
            fp.project_id,
            p.name as project_name,
            fp.floor_name,
            fp.level,
            fp.floor_number,
            fp.file_path,
            fp.image_path,
            fp.thumbnail_path,
            fp.status,
            fp.original_filename
        FROM floor_plans fp
        JOIN projects p ON fp.project_id = p.id
        WHERE fp.project_id = :project_id
        AND fp.status = 'active'
        ORDER BY 
            COALESCE(fp.floor_number, 999999),
            fp.floor_name ASC
    ");

    $stmt->execute([':project_id' => $projectId]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'data' => $results
    ]);

} catch (Exception $e) {
    error_log("Floor plans API error: " . $e->getMessage());
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
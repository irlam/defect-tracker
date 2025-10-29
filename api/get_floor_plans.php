<?php
/**
 * api/get_floor_plans.php
 * Current Date and Time (UTC): 2025-02-09 15:58:00
 * Current User's Login: irlam
 */

require_once '../config/database.php';
require_once '../includes/session.php';

header('Content-Type: application/json');

try {
    // Initialize database connection
    $database = new Database();
    $db = $database->getConnection();
    
    // Get input project ID from the request
    $input = json_decode(file_get_contents('php://input'), true);
    $projectId = isset($input['project_id']) ? (int)$input['project_id'] : 0;
    
    error_log("Fetching floor plans for project ID: " . $projectId);

    // Query to get floor plans for the specific project
    $query = "
        SELECT 
            fp.id,
            fp.floor_name,
            fp.level,
            fp.file_path,
            fp.image_path,
            fp.status,
            fp.floor_number,
            fp.original_filename,
            fp.thumbnail_path
        FROM floor_plans fp
        WHERE fp.project_id = :project_id
        AND fp.status = 'active'
        ORDER BY 
            COALESCE(fp.floor_number, 999999),
            fp.level,
            fp.floor_name
    ";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':project_id', $projectId, PDO::PARAM_INT);
    $stmt->execute();
    
    $floorPlans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process the floor plans to ensure correct paths
    $processedPlans = array_map(function($plan) {
        // Get the image path, ensuring it's properly formatted
        $imagePath = $plan['image_path'];
        if (!empty($imagePath)) {
            // Remove any leading slashes
            $imagePath = ltrim($imagePath, '/');
            
            // Ensure the path starts correctly
            if (strpos($imagePath, 'uploads/') !== 0) {
                $imagePath = 'uploads/' . $imagePath;
            }
        }

        // Construct display name with level information
        $displayName = $plan['floor_name'];
        if (!empty($plan['level']) && $plan['level'] !== 'Level?') {
            $displayName .= " ({$plan['level']})";
        }
        if (!empty($plan['floor_number'])) {
            $displayName .= " [Floor {$plan['floor_number']}]";
        }

        return [
            'id' => $plan['id'],
            'floor_name' => $displayName,
            'level' => $plan['level'],
            'floor_number' => $plan['floor_number'],
            'image_path' => $imagePath,
            'thumbnail_path' => $plan['thumbnail_path'],
            'original_filename' => $plan['original_filename'],
            'file_path' => $plan['file_path']
        ];
    }, $floorPlans);

    // Log the processed data for debugging
    error_log("Processed floor plans: " . json_encode([
        'count' => count($processedPlans),
        'first_plan' => !empty($processedPlans) ? $processedPlans[0] : null
    ]));

    if (empty($processedPlans)) {
        echo json_encode([
            'status' => 'success',
            'message' => 'No floor plans found for this project',
            'data' => []
        ]);
    } else {
        echo json_encode([
            'status' => 'success',
            'data' => $processedPlans,
            'debug' => [
                'project_id' => $projectId,
                'count' => count($processedPlans),
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ]);
    }

} catch (Exception $e) {
    error_log("Error in get_floor_plans.php: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to retrieve floor plans',
        'debug' => [
            'error' => $e->getMessage(),
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);
}
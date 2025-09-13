<?php
/**
 * Process Defect Images - Handles saving marked-up images
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/image_processor.log');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication check
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
    exit();
}

require_once 'config/database.php';

$response = ['status' => 'error', 'message' => 'Unknown error'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if the request contains image data
    if (!isset($_POST['imageData']) || !isset($_POST['defectId'])) {
        $response = ['status' => 'error', 'message' => 'Missing required parameters'];
    } else {
        $imageData = $_POST['imageData'];
        $defectId = (int)$_POST['defectId'];
        $currentUser = (int)$_SESSION['user_id'];
        
        // Validate defect ID
        if ($defectId <= 0) {
            $response = ['status' => 'error', 'message' => 'Invalid defect ID'];
            echo json_encode($response);
            exit();
        }
        
        // Create directory for defect images if it doesn't exist
        $defectDir = __DIR__ . "/uploads/defects/{$defectId}";
        if (!is_dir($defectDir)) {
            if (!mkdir($defectDir, 0777, true)) {
                $response = ['status' => 'error', 'message' => 'Failed to create directory'];
                echo json_encode($response);
                exit();
            }
        }
        
        // Process each image
        $savedImages = [];
        foreach ($imageData as $index => $base64Data) {
            // Extract the base64 encoded image data
            if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $matches)) {
                $imageType = $matches[1];
                $base64Data = substr($base64Data, strpos($base64Data, ',') + 1);
                $decodedData = base64_decode($base64Data);
                
                if ($decodedData === false) {
                    continue; // Skip invalid data
                }
                
                // Generate a unique filename
                $filename = 'marked_' . time() . '_' . $index . '.' . $imageType;
                $filePath = "{$defectDir}/{$filename}";
                
                // Save the image
                if (file_put_contents($filePath, $decodedData)) {
                    $relativePath = "uploads/defects/{$defectId}/{$filename}";
                    
                    // Insert into database
                    $db = (new Database())->getConnection();
                    $query = "INSERT INTO defect_images (defect_id, file_path, uploaded_by, created_at, uploaded_at) 
                             VALUES (:defect_id, :file_path, :uploaded_by, NOW(), NOW())";
                    
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':defect_id', $defectId);
                    $stmt->bindParam(':file_path', $relativePath);
                    $stmt->bindParam(':uploaded_by', $currentUser);
                    
                    if ($stmt->execute()) {
                        $savedImages[] = [
                            'path' => $relativePath,
                            'id' => $db->lastInsertId()
                        ];
                    }
                }
            }
        }
        
        if (count($savedImages) > 0) {
            $response = [
                'status' => 'success',
                'message' => 'Images processed successfully',
                'images' => $savedImages
            ];
        } else {
            $response = ['status' => 'error', 'message' => 'Failed to process images'];
        }
    }
}

header('Content-Type: application/json');
echo json_encode($response);
exit();
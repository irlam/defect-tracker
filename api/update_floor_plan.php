<?php
/**
 * Floor Plan Update API
 * Current Date and Time (UTC): 2025-02-08 20:05:37
 * Current User's Login: irlam
 */

// Start output buffering
ob_start();

// Set error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/floor_plan_update.log');

// Function to log with timestamp
function logMessage($message, $data = null) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp} UTC] $message";
    if ($data !== null) {
        $logEntry .= "\n" . print_r($data, true);
    }
    error_log($logEntry . "\n");
}

// Function to send JSON response
function sendJsonResponse($success, $message, $data = null, $statusCode = 200) {
    ob_clean();
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

class FloorPlanUpdateHandler {
    private $db;
    private $userId;
    private $roleId;
    private $postData;
    private $existingData;

    public function __construct($db, $userId, $roleId, $postData) {
        $this->db = $db;
        $this->userId = $userId;
        $this->roleId = $roleId;
        $this->postData = $postData;
    }

    private function normalizeData($data) {
        // Normalize data for comparison
        $normalized = [];
        foreach ($data as $key => $value) {
            if (in_array($key, ['project_id', 'floor_name', 'level', 'description'])) {
                $normalized[$key] = trim((string)$value);
            }
        }
        return $normalized;
    }

    private function validateRequest() {
        logMessage("Validating request...");

        if (!isset($this->postData['csrf_token']) || 
            !isset($_SESSION['csrf_token']) || 
            $this->postData['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception("Invalid CSRF token");
        }

        $requiredFields = ['id', 'project_id', 'floor_name', 'level'];
        foreach ($requiredFields as $field) {
            if (!isset($this->postData[$field]) || trim($this->postData[$field]) === '') {
                throw new Exception("Missing required field: $field");
            }
        }

        return true;
    }

    private function checkPermissions($floorPlanId) {
        logMessage("Checking permissions for floor plan ID: $floorPlanId");

        $stmt = $this->db->prepare("
            SELECT fp.*, p.created_by as project_creator, r.role_name
            FROM floor_plans fp
            JOIN projects p ON fp.project_id = p.id
            LEFT JOIN user_roles r ON r.role_id = ?
            WHERE fp.id = ?
        ");
        
        $stmt->execute([$this->roleId, $floorPlanId]);
        $this->existingData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$this->existingData) {
            throw new Exception("Floor plan not found");
        }

        $isAdmin = ($this->existingData['role_name'] === 'admin');
        $isOwner = ($this->existingData['created_by'] == $this->userId);
        $isProjectCreator = ($this->existingData['project_creator'] == $this->userId);

        if (!$isAdmin && !$isOwner && !$isProjectCreator) {
            throw new Exception("Permission denied");
        }

        return true;
    }

    private function updateLastModified() {
        $currentTime = date('Y-m-d H:i:s');
        $stmt = $this->db->prepare("
            UPDATE floor_plans 
            SET last_modified = :last_modified,
                updated_at = :updated_at,
                updated_by = :updated_by
            WHERE id = :id
        ");

        $params = [
            ':last_modified' => $currentTime,
            ':updated_at' => $currentTime,
            ':updated_by' => $this->userId,
            ':id' => $this->postData['id']
        ];

        if (!$stmt->execute($params)) {
            throw new Exception("Failed to update timestamps");
        }

        return [
            'success' => true,
            'message' => 'Floor plan timestamps updated',
            'data' => [
                'id' => $this->postData['id'],
                'last_modified' => $currentTime,
                'updated_at' => $currentTime,
                'updated_by' => $this->userId
            ]
        ];
    }

    public function updateFloorPlan() {
        try {
            $this->db->beginTransaction();

            // Validate request
            $this->validateRequest();

            // Check permissions
            $this->checkPermissions($this->postData['id']);

            // Normalize existing and new data for comparison
            $existingNormalized = $this->normalizeData($this->existingData);
            $newNormalized = $this->normalizeData($this->postData);

            // Compare normalized data
            $hasChanges = false;
            foreach ($newNormalized as $key => $value) {
                if ($existingNormalized[$key] !== $value) {
                    $hasChanges = true;
                    break;
                }
            }

            // If no content changes, just update timestamps
            if (!$hasChanges) {
                $result = $this->updateLastModified();
                $this->db->commit();
                return $result;
            }

            // Prepare update with actual changes
            $currentTime = date('Y-m-d H:i:s');
            $stmt = $this->db->prepare("
                UPDATE floor_plans 
                SET project_id = :project_id,
                    floor_name = :floor_name,
                    level = :level,
                    description = :description,
                    last_modified = :last_modified,
                    updated_at = :updated_at,
                    updated_by = :updated_by
                WHERE id = :id
            ");

            $params = [
                ':project_id' => $this->postData['project_id'],
                ':floor_name' => trim($this->postData['floor_name']),
                ':level' => $this->postData['level'],
                ':description' => $this->postData['description'] ?? '',
                ':last_modified' => $currentTime,
                ':updated_at' => $currentTime,
                ':updated_by' => $this->userId,
                ':id' => $this->postData['id']
            ];

            if (!$stmt->execute($params)) {
                throw new Exception("Failed to update floor plan");
            }

            $this->db->commit();

            return [
                'success' => true,
                'message' => 'Floor plan updated successfully',
                'data' => array_merge(
                    ['id' => $this->postData['id']],
                    $params
                )
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            logMessage("Error in update: " . $e->getMessage());
            throw $e;
        }
    }
}

try {
    // Start session if not started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Check authentication
    if (!isset($_SESSION['user_id'])) {
        throw new Exception("Unauthorized access", 401);
    }

    // Include database configuration
    require_once __DIR__ . '/../config/database.php';

    // Initialize database connection
    $database = new Database();
    $db = $database->getConnection();

    // Create handler instance
    $handler = new FloorPlanUpdateHandler(
        $db,
        $_SESSION['user_id'],
        $_SESSION['role_id'],
        $_POST
    );

    // Process update
    $result = $handler->updateFloorPlan();
    
    // Send success response
    sendJsonResponse(
        $result['success'],
        $result['message'],
        $result['data']
    );

} catch (Exception $e) {
    logMessage("Update failed: " . $e->getMessage());
    sendJsonResponse(
        false,
        $e->getMessage(),
        null,
        $e->getCode() ?: 500
    );
}

// End output buffer
ob_end_clean();
?>
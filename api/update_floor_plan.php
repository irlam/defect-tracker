<?php
/**
 * api/update_floor_plan.php
 * Floor Plan Update Handler
 * Current Date and Time (UTC): 2025-01-28 18:56:15
 * Current User's Login: irlam
 */

declare(strict_types=1);

// Set error handling
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/update.log');

// Log PHP version for debugging
error_log("PHP Version: " . phpversion());

// Security headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Access-Control-Allow-Origin: https://defects.dvntracker.site');
header('Access-Control-Allow-Credentials: true');

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Debug session information
error_log("=== Session Information ===");
error_log("User ID: " . ($_SESSION['user_id'] ?? 'not set'));
error_log("User Roles: " . print_r($_SESSION['user_roles'] ?? [], true));
error_log("========================");

require_once '../config/database.php';
require_once '../includes/auth.php';

class FloorPlanUpdateHandler {
    private $db;
    private $userId;
    private $userRoleId;
    private $userPermissions;
    private $allowedFields;
    public function __construct(PDO $db, int $userId) {
        $this->db = $db;
        $this->userId = $userId;
        $this->userRoleId = null;
        $this->userPermissions = [];
        $this->initializeAllowedFields();
        $this->loadUserRole();
        $this->loadUserPermissions();
        
        // Debug log
        error_log("Handler initialized for user: {$this->userId}");
    }

    private function initializeAllowedFields(): void {
        $this->allowedFields = [
            'project_id' => ['type' => 'int', 'required' => true],
            'floor_name' => ['type' => 'string', 'required' => true],
            'level' => ['type' => 'string', 'required' => true],
            'floor_number' => ['type' => 'int', 'required' => false],
            'description' => ['type' => 'string', 'required' => false],
            'status' => [
                'type' => 'enum',
                'values' => ['active', 'inactive'],
                'required' => false
            ]
        ];
    }

    private function loadUserRole(): void {
        $stmt = $this->db->prepare("
            SELECT ur.role_id, r.name as role_name
            FROM user_roles ur
            JOIN roles r ON ur.role_id = r.id
            WHERE ur.user_id = ?
            AND ur.deleted_at IS NULL
            ORDER BY ur.created_at DESC
            LIMIT 1
        ");
        
        $stmt->execute([$this->userId]);
        $role = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $this->userRoleId = $role ? $role['role_id'] : null;
        
        // Debug log
        error_log("User ID: {$this->userId}");
        error_log("Loaded role: " . print_r($role, true));
    }

    private function loadUserPermissions(): void {
        $stmt = $this->db->prepare("
            SELECT DISTINCT p.permission_key
            FROM permissions p
            JOIN role_permissions rp ON p.id = rp.permission_id
            JOIN user_roles ur ON rp.role_id = ur.role_id
            WHERE ur.user_id = ?
            AND ur.deleted_at IS NULL
            AND p.deleted_at IS NULL
        ");
        
        $stmt->execute([$this->userId]);
        $this->userPermissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Add admin permissions automatically if user has admin role
        if ($this->userRoleId === 1) { // 1 is admin role ID
            $this->userPermissions = array_merge(
                $this->userPermissions,
                ['edit_projects', 'delete_projects', 'manage_users', 'view_reports']
            );
        }
        
        // Debug log
        error_log("Loaded permissions for user {$this->userId}: " . implode(', ', $this->userPermissions));
    }
    public function validateRequest(): void {
        // Debug logging
        error_log("Validating request...");
        error_log("POST data: " . print_r($_POST, true));
        error_log("Session CSRF: " . ($_SESSION['csrf_token'] ?? 'not set'));
        error_log("Posted CSRF: " . ($_POST['csrf_token'] ?? 'not set'));
        error_log("Current Time (UTC): 2025-01-28 19:02:37");
        error_log("Current User: irlam");

        // Check request method
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new Exception('Invalid request method', 405);
        }

        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || 
            !isset($_SESSION['csrf_token']) || 
            !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            $this->logSecurityEvent('CSRF token validation failed');
            throw new Exception('Invalid security token', 403);
        }

        // Validate floor plan ID
        if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
            throw new Exception('Invalid floor plan ID', 400);
        }

        // Validate required fields with detailed logging
        foreach ($this->allowedFields as $field => $config) {
            if ($config['required'] && empty($_POST[$field])) {
                error_log("Missing required field: {$field}");
                throw new Exception("Field '{$field}' is required", 400);
            }
        }

        // Validate field types
        foreach ($_POST as $field => $value) {
            if (isset($this->allowedFields[$field])) {
                try {
                    $this->validateField($field, $value, $this->allowedFields[$field]);
                } catch (Exception $e) {
                    error_log("Field validation failed for {$field}: " . $e->getMessage());
                    throw $e;
                }
            }
        }

        error_log("Request validation completed successfully");
    }

    private function validateField(string $field, $value, array $config): void {
        if ($value === null || $value === '') {
            if ($config['required']) {
                throw new Exception("Field '{$field}' cannot be empty", 400);
            }
            return;
        }

        switch ($config['type']) {
            case 'string':
                if (!is_string($value)) {
                    throw new Exception("Field '{$field}' must be a string", 400);
                }
                break;
            case 'int':
                if (!is_numeric($value)) {
                    throw new Exception("Field '{$field}' must be a number", 400);
                }
                break;
            case 'enum':
                if (!in_array($value, $config['values'], true)) {
                    throw new Exception(
                        "Field '{$field}' must be one of: " . 
                        implode(', ', $config['values']),
                        400
                    );
                }
                break;
        }
    }

    public function checkPermissions(int $floorPlanId): array {
        error_log("Checking permissions for floor plan ID: {$floorPlanId}");
        error_log("User ID: {$this->userId}");
        error_log("User Role ID: {$this->userRoleId}");

        // For admin role (ID 1), grant immediate access
        if ($this->userRoleId === 1) {
            error_log("Admin access granted automatically");
            
            $stmt = $this->db->prepare("
                SELECT fp.*, p.created_by as project_creator
                FROM floor_plans fp
                JOIN projects p ON fp.project_id = p.id
                WHERE fp.id = ?
                AND fp.status != 'deleted'
                FOR UPDATE
            ");
            
            $stmt->execute([$floorPlanId]);
            $floorPlan = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$floorPlan) {
                throw new Exception('Floor plan not found or deleted', 404);
            }
            
            return $floorPlan;
        }
        // Continue checkPermissions method for non-admin users
        $stmt = $this->db->prepare("
            SELECT 
                fp.*,
                p.created_by as project_creator,
                p.id as project_id,
                r.name as role_name
            FROM floor_plans fp
            JOIN projects p ON fp.project_id = p.id
            JOIN user_roles ur ON ur.user_id = ?
            JOIN roles r ON r.id = ur.role_id
            WHERE fp.id = ?
            AND fp.status != 'deleted'
            AND ur.deleted_at IS NULL
            FOR UPDATE
        ");
        
        $stmt->execute([$this->userId, $floorPlanId]);
        $floorPlan = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$floorPlan) {
            throw new Exception('Floor plan not found or deleted', 404);
        }

        // Debug logging
        error_log("Floor plan data: " . print_r($floorPlan, true));
        error_log("User role name: " . ($floorPlan['role_name'] ?? 'none'));
        error_log("User permissions: " . implode(', ', $this->userPermissions));

        // Check permissions based on role and ownership
        if (
            $floorPlan['role_name'] === 'manager' || 
            $floorPlan['created_by'] == $this->userId || 
            $floorPlan['project_creator'] == $this->userId ||
            in_array('edit_projects', $this->userPermissions)
        ) {
            error_log("Permission granted - Role: {$floorPlan['role_name']}");
            return $floorPlan;
        }

        // Log permission denial details
        error_log("Permission denied - Details:");
        error_log("User ID: {$this->userId}");
        error_log("Role: " . ($floorPlan['role_name'] ?? 'none'));
        error_log("Floor Plan Creator: " . $floorPlan['created_by']);
        error_log("Project Creator: " . $floorPlan['project_creator']);
        error_log("Has edit_projects permission: " . (in_array('edit_projects', $this->userPermissions) ? "Yes" : "No"));
        
        throw new Exception('Insufficient permissions', 403);
    }

    public function updateFloorPlan(int $floorPlanId): array {
        error_log("Starting floor plan update for ID: {$floorPlanId}");
        error_log("Current Time (UTC): 2025-01-28 19:03:35");
        error_log("Current User: irlam");
        
        $this->db->beginTransaction();

        try {
            $currentData = $this->checkPermissions($floorPlanId);
            $updateData = $this->prepareUpdateData($_POST);

            if (!$this->hasChanges($currentData, $updateData)) {
                throw new Exception('No changes detected', 400);
            }

            if ($this->isSignificantChange($currentData, $updateData)) {
                $updateData['version'] = ($currentData['version'] ?? 0) + 1;
            }

            $success = $this->executeUpdate($floorPlanId, $updateData);

            if (!$success) {
                throw new Exception('Failed to update floor plan', 500);
            }

            $this->logUpdate($floorPlanId, $currentData, $updateData);

            $this->db->commit();
            
            $updatedData = $this->getUpdatedFloorPlan($floorPlanId);
            
            error_log("Floor plan updated successfully");

            return [
                'success' => true,
                'message' => 'Floor plan updated successfully',
                'data' => $updatedData,
                'timestamp' => '2025-01-28 19:03:35'
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Update failed: " . $e->getMessage());
            throw $e;
        }
    }
    private function prepareUpdateData(array $postData): array {
        error_log("Preparing update data at: 2025-01-28 19:04:24");
        error_log("Raw POST data: " . print_r($postData, true));
        
        $updateData = [];
        
        foreach ($this->allowedFields as $field => $config) {
            if (isset($postData[$field])) {
                $updateData[$field] = $postData[$field];
            }
        }

        // Add audit fields
        $updateData['updated_at'] = gmdate('Y-m-d H:i:s');
        $updateData['updated_by'] = $this->userId;

        error_log("Prepared update data: " . print_r($updateData, true));
        return $updateData;
    }

    private function hasChanges(array $currentData, array $updateData): bool {
        error_log("Checking for changes at: 2025-01-28 19:04:24");
        
        $significantFields = ['floor_name', 'level', 'floor_number', 'description', 'project_id', 'status'];
        
        foreach ($significantFields as $field) {
            if (isset($updateData[$field]) && 
                isset($currentData[$field]) && 
                $updateData[$field] != $currentData[$field]) {
                error_log("Change detected in field '{$field}': {$currentData[$field]} -> {$updateData[$field]}");
                return true;
            }
        }
        
        error_log("No significant changes detected");
        return false;
    }

    private function isSignificantChange(array $currentData, array $updateData): bool {
        error_log("Checking for significant changes at: 2025-01-28 19:04:24");
        
        $significantFields = ['floor_name', 'level', 'project_id'];
        
        foreach ($significantFields as $field) {
            if (isset($updateData[$field]) && 
                isset($currentData[$field]) && 
                $updateData[$field] != $currentData[$field]) {
                error_log("Significant change detected in field '{$field}'");
                return true;
            }
        }
        
        error_log("No significant changes requiring version update");
        return false;
    }

    private function executeUpdate(int $floorPlanId, array $updateData): bool {
        error_log("Executing update at: 2025-01-28 19:04:24");
        error_log("Update data: " . print_r($updateData, true));

        $fields = array();
        $params = array();
        
        foreach ($updateData as $field => $value) {
            $fields[] = "{$field} = :{$field}";
            $params[$field] = $value;
        }
        
        $params['id'] = $floorPlanId;
        
        $sql = "
            UPDATE floor_plans 
            SET " . implode(', ', $fields) . "
            WHERE id = :id
        ";

        error_log("SQL Query: {$sql}");
        error_log("Parameters: " . print_r($params, true));

        try {
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute($params);
            
            error_log("Update " . ($result ? "successful" : "failed"));
            return $result;
            
        } catch (PDOException $e) {
            error_log("Database error during update: " . $e->getMessage());
            throw new Exception('Database error during update', 500);
        }
    }
    private function getUpdatedFloorPlan(int $floorPlanId): array {
        error_log("Fetching updated floor plan at: 2025-01-28 19:05:08");
        
        $stmt = $this->db->prepare("
            SELECT fp.*, 
                   p.name as project_name,
                   u.username as updated_by_user
            FROM floor_plans fp
            JOIN projects p ON fp.project_id = p.id
            LEFT JOIN users u ON fp.updated_by = u.id
            WHERE fp.id = ?
        ");
        
        $stmt->execute([$floorPlanId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        error_log("Retrieved updated data: " . print_r($result, true));
        return $result;
    }

    private function logUpdate(int $floorPlanId, array $oldData, array $newData): void {
        error_log("Logging update at: 2025-01-28 19:05:08");
        error_log("User: irlam");
        
        try {
            $stmt = $this->db->prepare("
                INSERT INTO audit_logs (
                    entity_type,
                    entity_id,
                    action,
                    old_values,
                    new_values,
                    user_id,
                    created_at
                ) VALUES (
                    'floor_plan',
                    ?,
                    'update',
                    ?,
                    ?,
                    ?,
                    ?
                )
            ");

            $stmt->execute([
                $floorPlanId,
                json_encode($oldData),
                json_encode($newData),
                $this->userId,
                gmdate('Y-m-d H:i:s')
            ]);

            error_log("Audit log created successfully");
            
        } catch (PDOException $e) {
            error_log("Warning: Failed to create audit log: " . $e->getMessage());
            // Don't throw exception as this is not critical
        }
    }

    private function logSecurityEvent(string $message): void {
        error_log("Security Event at: 2025-01-28 19:05:08");
        error_log("User: irlam");
        error_log("Message: " . $message);
        
        try {
            $stmt = $this->db->prepare("
                INSERT INTO security_logs (
                    event_type,
                    user_id,
                    message,
                    ip_address,
                    user_agent,
                    created_at
                ) VALUES (
                    'security_warning',
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )
            ");

            $stmt->execute([
                $this->userId,
                $message,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                gmdate('Y-m-d H:i:s')
            ]);
            
        } catch (PDOException $e) {
            error_log("Warning: Failed to log security event: " . $e->getMessage());
        }
    }
}

// Debug logging for incoming request
error_log("=== Update Floor Plan Request ===");
error_log("Time: 2025-01-28 19:05:08");
error_log("User: irlam");
error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
error_log("POST Data: " . print_r($_POST, true));
error_log("Session Data: " . print_r($_SESSION, true));

try {
    // Check authentication
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Authentication required', 401);
    }

    // Initialize handler
    $database = new Database();
    $handler = new FloorPlanUpdateHandler(
        $database->getConnection(),
        (int)$_SESSION['user_id']
    );

    // Process update
    $handler->validateRequest();
    $response = $handler->updateFloorPlan((int)$_POST['id']);

    // Debug logging
    error_log("Update completed successfully");
    error_log("Response: " . print_r($response, true));

    // Output response
    http_response_code($response['success'] ? 200 : 500);
    echo json_encode($response);

} catch (Exception $e) {
    $code = $e->getCode() ?: 500;
    http_response_code($code);
    
    $errorResponse = [
        'success' => false,
        'message' => $e->getMessage(),
        'code' => $code,
        'timestamp' => '2025-01-28 19:05:08'
    ];
    
    // Debug logging
    error_log("=== Error in Update Floor Plan ===");
    error_log("Time: 2025-01-28 19:05:08");
    error_log("User: irlam");
    error_log("Error Code: {$code}");
    error_log("Error Message: " . $e->getMessage());
    error_log("Stack Trace: " . $e->getTraceAsString());
    error_log("POST Data: " . print_r($_POST, true));
    error_log("================================");
    
    echo json_encode($errorResponse);
}

// Log completion of request
error_log("=== Update Floor Plan Request Complete ===");
error_log("Time: 2025-01-28 19:05:08");
error_log("User: irlam");
error_log("Status: " . (isset($response['success']) ? 'Success' : 'Failed'));
error_log("================================");
?>
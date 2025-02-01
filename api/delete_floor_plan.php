<?php
/**
 * api/delete_floor_plan.php
 * Floor Plan Deletion Handler
 * Current Date and Time (UTC): 2025-01-28 19:16:39
 * Current User's Login: irlam
 */

declare(strict_types=1);

// Set error handling
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/delete.log');

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

class FloorPlanDeleteHandler {
    private $db;
    private $userId;
    private $userRoleId;
    private $userPermissions;

    public function __construct(PDO $db, int $userId) {
        $this->db = $db;
        $this->userId = $userId;
        $this->userRoleId = null;
        $this->userPermissions = [];
        $this->loadUserRole();
        $this->loadUserPermissions();
        
        error_log("Handler initialized for user: {$this->userId}");
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
        
        $this->userRoleId = $role ? (int)$role['role_id'] : null;
        
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
        if ($this->userRoleId === 1) {
            $this->userPermissions = array_merge(
                $this->userPermissions,
                ['delete_projects', 'manage_users', 'view_reports']
            );
        }
        
        error_log("Loaded permissions for user {$this->userId}: " . implode(', ', $this->userPermissions));
    }
    public function validateRequest(): void {
        error_log("Validating delete request at: 2025-01-28 19:17:29");
        error_log("Current User: irlam");
        error_log("POST data: " . print_r($_POST, true));
        error_log("Session CSRF: " . ($_SESSION['csrf_token'] ?? 'not set'));
        error_log("Posted CSRF: " . ($_POST['csrf_token'] ?? 'not set'));

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new Exception('Invalid request method', 405);
        }

        if (!isset($_POST['csrf_token']) || 
            !isset($_SESSION['csrf_token']) || 
            !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            $this->logSecurityEvent('CSRF token validation failed for floor plan deletion');
            throw new Exception('Invalid security token', 403);
        }

        if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
            throw new Exception('Invalid floor plan ID', 400);
        }
    }

    public function checkPermissions(int $floorPlanId): array {
        error_log("Checking delete permissions at: 2025-01-28 19:17:29");
        error_log("Floor Plan ID: {$floorPlanId}");
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
                throw new Exception('Floor plan not found or already deleted', 404);
            }
            
            return $floorPlan;
        }

        // For non-admin users, check specific permissions
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
            throw new Exception('Floor plan not found or already deleted', 404);
        }

        if (
            $floorPlan['role_name'] === 'manager' || 
            $floorPlan['created_by'] == $this->userId || 
            $floorPlan['project_creator'] == $this->userId ||
            in_array('delete_projects', $this->userPermissions)
        ) {
            error_log("Delete permission granted - Role: {$floorPlan['role_name']}");
            return $floorPlan;
        }

        error_log("Delete permission denied - Details:");
        error_log("User ID: {$this->userId}");
        error_log("Role: " . ($floorPlan['role_name'] ?? 'none'));
        error_log("Floor Plan Creator: " . $floorPlan['created_by']);
        error_log("Project Creator: " . $floorPlan['project_creator']);
        
        throw new Exception('Insufficient permissions to delete floor plan', 403);
    }

    public function deleteFloorPlan(int $floorPlanId): array {
        error_log("Starting floor plan deletion at: 2025-01-28 19:17:29");
        error_log("User: irlam");
        
        $this->db->beginTransaction();

        try {
            $floorPlan = $this->checkPermissions($floorPlanId);

            $stmt = $this->db->prepare("
                UPDATE floor_plans 
                SET 
                    status = 'deleted',
                    updated_at = ?,
                    updated_by = ?
                WHERE id = ?
            ");

            $currentTime = gmdate('Y-m-d H:i:s');
            
            $success = $stmt->execute([
                $currentTime,
                $this->userId,
                $floorPlanId
            ]);

            if (!$success) {
                throw new Exception('Failed to delete floor plan', 500);
            }

            $this->logDeletion($floorPlanId, $floorPlan);
            $this->db->commit();
            
            error_log("Floor plan deleted successfully");

            return [
                'success' => true,
                'message' => 'Floor plan deleted successfully',
                'timestamp' => '2025-01-28 19:17:29',
                'deleted_id' => $floorPlanId
            ];

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Database error during deletion: " . $e->getMessage());
            throw new Exception('Database error during deletion', 500);
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Deletion failed: " . $e->getMessage());
            throw $e;
        }
    }

    private function logDeletion(int $floorPlanId, array $floorPlanData): void {
        error_log("Logging deletion at: 2025-01-28 19:17:29");
        error_log("User: irlam");
        
        try {
            $stmt = $this->db->prepare("
                INSERT INTO audit_logs (
                    entity_type,
                    entity_id,
                    action,
                    old_values,
                    user_id,
                    created_at
                ) VALUES (
                    'floor_plan',
                    ?,
                    'delete',
                    ?,
                    ?,
                    ?
                )
            ");

            $stmt->execute([
                $floorPlanId,
                json_encode($floorPlanData),
                $this->userId,
                gmdate('Y-m-d H:i:s')
            ]);

            error_log("Audit log created successfully");
        } catch (PDOException $e) {
            error_log("Warning: Failed to create audit log: " . $e->getMessage());
        }
    }

    private function logSecurityEvent(string $message): void {
        error_log("Security Event at: 2025-01-28 19:17:29");
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

// Main execution
error_log("=== Delete Floor Plan Request ===");
error_log("Time: 2025-01-28 19:17:29");
error_log("User: irlam");
error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
error_log("POST Data: " . print_r($_POST, true));

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Authentication required', 401);
    }

    $database = new Database();
    $handler = new FloorPlanDeleteHandler(
        $database->getConnection(),
        (int)$_SESSION['user_id']
    );

    $handler->validateRequest();
    $response = $handler->deleteFloorPlan((int)$_POST['id']);

    http_response_code(200);
    echo json_encode($response);

} catch (Exception $e) {
    $code = is_int($e->getCode()) && $e->getCode() !== 0 ? $e->getCode() : 500;
    http_response_code($code);
    
    $errorResponse = [
        'success' => false,
        'message' => $e->getMessage(),
        'code' => $code,
        'timestamp' => '2025-01-28 19:17:29'
    ];
    
    error_log("=== Error in Delete Floor Plan ===");
    error_log("Time: 2025-01-28 19:17:29");
    error_log("User: irlam");
    error_log("Error Code: {$code}");
    error_log("Error Message: " . $e->getMessage());
    error_log("Stack Trace: " . $e->getTraceAsString());
    
    echo json_encode($errorResponse);
}

error_log("=== Delete Floor Plan Request Complete ===");
error_log("Time: 2025-01-28 19:17:29");
error_log("User: irlam");
error_log("Status: " . (isset($response['success']) ? 'Success' : 'Failed'));
error_log("================================");
?>
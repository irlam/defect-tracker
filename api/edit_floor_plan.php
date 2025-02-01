<?php
/**
 * api/edit_floor_plan.php
 * Floor Plan Edit Handler
 * Current Date and Time (UTC): 2025-01-28 16:01:38
 * Current User's Login: irlam
 */

// Prevent any output before headers
ob_clean();

// Set error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/edit.log');

// Security headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
require_once '../includes/auth.php';

class FloorPlanEditHandler {
    private $db;
    private $userId;
    private $userRoleId;
    private $userPermissions;

    public function __construct(PDO $db, $userId) {
        $this->db = $db;
        $this->userId = $userId;
        $this->userRoleId = null;
        $this->userPermissions = [];
        $this->loadUserRole();
        $this->loadUserPermissions();
    }

    private function loadUserRole() {
        $stmt = $this->db->prepare("
            SELECT ur.role_id
            FROM user_roles ur
            WHERE ur.user_id = ?
            AND ur.deleted_at IS NULL
            ORDER BY ur.created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$this->userId]);
        $this->userRoleId = $stmt->fetchColumn();
        if ($this->userRoleId === false) {
            $this->userRoleId = null;
        }
    }

    private function loadUserPermissions() {
        // Get permissions from both user_permissions and role_permissions
        $stmt = $this->db->prepare("
            SELECT DISTINCT p.permission_key
            FROM permissions p
            LEFT JOIN role_permissions rp ON p.id = rp.permission_id
            LEFT JOIN user_permissions up ON p.id = up.permission_id
            WHERE (rp.role_id = ? OR up.user_id = ?)
            AND p.deleted_at IS NULL
        ");
        $stmt->execute([$this->userRoleId, $this->userId]);
        $this->userPermissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function validateRequest() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new Exception('Invalid request method', 405);
        }

        if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
            throw new Exception('Invalid floor plan ID', 400);
        }

        if (!isset($_POST['csrf_token']) || 
            !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            $this->logSecurityEvent('CSRF token validation failed');
            throw new Exception('Invalid security token', 403);
        }
    }

    public function checkPermissions($floorPlanId) {
        // Check if user has admin role or edit_projects permission
        if (!in_array('edit_projects', $this->userPermissions) && 
            $this->userRoleId !== 1) { // Admin role ID
            throw new Exception('Insufficient permissions', 403);
        }

        // Get floor plan and project details
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
            throw new Exception('Floor plan not found', 404);
        }

        // Additional permission checks for non-admin users
        if ($this->userRoleId !== 1) {
            if ($floorPlan['uploaded_by'] !== $this->userId && 
                $floorPlan['project_creator'] !== $this->userId) {
                throw new Exception('Insufficient permissions', 403);
            }
        }
    }

    public function editFloorPlan($floorPlanId) {
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare("
                UPDATE floor_plans 
                SET project_id = :project_id,
                    floor_name = :floor_name,
                    level = :level,
                    floor_number = :floor_number,
                    description = :description,
                    last_modified = UTC_TIMESTAMP(),
                    updated_at = UTC_TIMESTAMP(),
                    updated_by = :updated_by
                WHERE id = :id
            ");

            $params = [
                ':id' => $floorPlanId,
                ':project_id' => $_POST['project_id'],
                ':floor_name' => $_POST['floor_name'],
                ':level' => $_POST['level'],
                ':floor_number' => $_POST['floor_number'],
                ':description' => $_POST['description'] ?? null,
                ':updated_by' => $this->userId
            ];

            if (!$stmt->execute($params)) {
                throw new Exception('Failed to update floor plan', 500);
            }

            // Log the update
            $this->logUpdate($floorPlanId);

            $this->db->commit();

            return [
                'success' => true,
                'message' => 'Floor plan updated successfully',
                'data' => [
                    'id' => $floorPlanId
                ]
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function logUpdate($floorPlanId) {
        $stmt = $this->db->prepare("
            INSERT INTO user_logs (
                user_id,
                action,
                action_by,
                action_at,
                ip_address,
                details
            ) VALUES (?, ?, ?, UTC_TIMESTAMP(), ?, ?)
        ");

        $details = json_encode([
            'floor_plan_id' => $floorPlanId,
            'updated_by' => $this->userId
        ]);

        $stmt->execute([
            $this->userId,
            'edit_floor_plan',
            $this->userId,
            $_SERVER['REMOTE_ADDR'],
            $details
        ]);
    }

    private function logSecurityEvent($message) {
        error_log(
            json_encode([
                'timestamp' => gmdate('Y-m-d H:i:s'),
                'user_id' => $this->userId,
                'ip' => $_SERVER['REMOTE_ADDR'],
                'message' => $message
            ]) . "\n",
            3,
            __DIR__ . '/../logs/security.log'
        );
    }
}

// Main execution
try {
    // Check authentication
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Authentication required', 401);
    }

    // Initialize handler
    $database = new Database();
    $handler = new FloorPlanEditHandler(
        $database->getConnection(),
        (int)$_SESSION['user_id']
    );

    // Process edit
    $handler->validateRequest();
    $handler->checkPermissions((int)$_POST['id']);
    $response = $handler->editFloorPlan((int)$_POST['id']);

    http_response_code($response['success'] ? 200 : 500);
    echo json_encode($response);

} catch (Exception $e) {
    $code = $e->getCode() ?: 500;
    http_response_code($code);
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'code' => $code
    ]);

    if ($code >= 500) {
        error_log($e->getMessage() . "\n" . $e->getTraceAsString());
    }
}
?>
<?php
/**
 * api/delete_floor_plan.php
 * Floor Plan Deletion Handler (with Cascade Delete)
 */

declare(strict_types=1);

// Set error handling
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/delete.log');

// Security headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Access-Control-Allow-Origin: https://mcgoff.defecttracker.uk');
header('Access-Control-Allow-Credentials: true');

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

        if ($this->userRoleId === 1) {
            $this->userPermissions = array_merge(
                $this->userPermissions,
                ['delete_projects', 'manage_users', 'view_reports']
            );
        }
    }
    public function validateRequest(): void {
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
        if ($this->userRoleId === 1) {
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
            return $floorPlan;
        }

        throw new Exception('Insufficient permissions to delete floor plan', 403);
    }

    public function deleteFloorPlan(int $floorPlanId): array {
        $this->db->beginTransaction();

        try {
            $floorPlan = $this->checkPermissions($floorPlanId);

            // Delete defect_comments
            $sql = "DELETE FROM defect_comments WHERE defect_id IN (SELECT id FROM defects WHERE floor_plan_id = ?)";
            $stmt = $this->db->prepare($sql);
            if (!$stmt->execute([$floorPlanId])) {
                 throw new Exception("Failed to delete defect_comments: " . print_r($stmt->errorInfo(), true));
            }

            // Delete defect_images
            $sql = "DELETE FROM defect_images WHERE defect_id IN (SELECT id FROM defects WHERE floor_plan_id = ?)";
            $stmt = $this->db->prepare($sql);
            if (!$stmt->execute([$floorPlanId])) {
                throw new Exception("Failed to delete defect_images: " . print_r($stmt->errorInfo(), true));
            }

            // Delete activity_logs
            $sql = "DELETE FROM activity_logs WHERE defect_id IN (SELECT id FROM defects WHERE floor_plan_id = ?)";
            $stmt = $this->db->prepare($sql);
             if (!$stmt->execute([$floorPlanId])) {
                 throw new Exception("Failed to delete activity_logs: " . print_r($stmt->errorInfo(), true));
            }

            // Delete comments
            $sql = "DELETE FROM comments WHERE defect_id IN (SELECT id FROM defects WHERE floor_plan_id = ?)";
            $stmt = $this->db->prepare($sql);
            if (!$stmt->execute([$floorPlanId])) {
                 throw new Exception("Failed to delete comments: " . print_r($stmt->errorInfo(), true));
            }

            // Delete defects
            $sql = "DELETE FROM defects WHERE floor_plan_id = ?";
            $stmt = $this->db->prepare($sql);
            if (!$stmt->execute([$floorPlanId])) {
                throw new Exception("Failed to delete defects: " . print_r($stmt->errorInfo(), true));
            }

            // Delete floor plan
            $sql = "UPDATE floor_plans SET status = 'deleted', updated_at = ?, updated_by = ? WHERE id = ?";
            $stmt = $this->db->prepare($sql);

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

            return [
                'success' => true,
                'message' => 'Floor plan and associated data deleted successfully',
                'timestamp' => gmdate('Y-m-d H:i:s'),
                'deleted_id' => $floorPlanId
            ];

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Database error during cascade deletion: " . $e->getMessage());
            throw new Exception('Database error during cascade deletion', 500);
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Deletion failed: " . $e->getMessage());
            throw $e;
        }
    }

    private function logDeletion(int $floorPlanId, array $floorPlanData): void {
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

            $success = $stmt->execute([
                $floorPlanId,
                json_encode($floorPlanData),
                $this->userId,
                gmdate('Y-m-d H:i:s')
            ]);

            if (!$success) {
                throw new Exception("Failed to create audit log: " . print_r($stmt->errorInfo(), true));
            }

        }  catch (PDOException $e) {
            error_log("Warning: Failed to create audit log: " . $e->getMessage());
            throw $e; //Re-throw the exception to be caught by the main try...catch block
        }
    }

    private function logSecurityEvent(string $message): void {
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
        'timestamp' => gmdate('Y-m-d H:i:s')
    ];

    echo json_encode($errorResponse);
}
?>
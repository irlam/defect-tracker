<?php
/**
 * api/update_defect.php
 * Defect Update Handler
 * Current Date and Time (UTC): 2025-01-30 11:14:01
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

class DefectUpdateHandler {
    private $db;
    private $userId;
    private $userRole;
    private $uploadDir = '../uploads/defect_images/';

    public function __construct(PDO $db, $userId) {
        $this->db = $db;
        $this->userId = $userId;
        $this->loadUserRole();
    }

    private function loadUserRole() {
        $stmt = $this->db->prepare("
            SELECT r.name 
            FROM roles r 
            JOIN user_roles ur ON r.id = ur.role_id 
            WHERE ur.user_id = ? 
            AND r.name IN ('admin', 'manager')
            AND ur.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([$this->userId]);
        $this->userRole = $stmt->fetchColumn();
    }

    public function validateRequest() {
        // Check CSRF token
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            throw new Exception('Invalid security token', 403);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new Exception('Invalid request method', 405);
        }

        if (!$this->userRole) {
            throw new Exception('Insufficient permissions', 403);
        }

        if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
            throw new Exception('Invalid defect ID', 400);
        }

        if (!isset($_POST['status']) || !in_array($_POST['status'], ['open', 'closed', 'rejected'])) {
            throw new Exception('Invalid status', 400);
        }

        // Validate required fields based on status
        if ($_POST['status'] === 'closed' && empty($_FILES['closure_image'])) {
            throw new Exception('Closure image is required when closing a defect', 400);
        }

        if ($_POST['status'] === 'rejected' && empty($_POST['rejection_comment'])) {
            throw new Exception('Rejection comment is required when rejecting a defect', 400);
        }
    }

    public function updateDefect($defectId) {
        $this->db->beginTransaction();

        try {
            // Get current defect status
            $stmt = $this->db->prepare("SELECT status FROM defects WHERE id = ? FOR UPDATE");
            $stmt->execute([$defectId]);
            $currentStatus = $stmt->fetchColumn();

            if ($currentStatus === false) {
                throw new Exception('Defect not found', 404);
            }

            // Handle image upload if closing
            $closureImagePath = null;
            if ($_POST['status'] === 'closed' && !empty($_FILES['closure_image'])) {
                $closureImagePath = $this->handleImageUpload('closure_image');
            }

            // Prepare update query
            $stmt = $this->db->prepare("
                UPDATE defects 
                SET status = :status,
                    closure_image = CASE 
                        WHEN :status = 'closed' THEN :closure_image 
                        ELSE closure_image 
                    END,
                    rejection_comment = CASE 
                        WHEN :status = 'rejected' THEN :rejection_comment 
                        ELSE rejection_comment 
                    END,
                    updated_at = UTC_TIMESTAMP(),
                    updated_by = :updated_by
                WHERE id = :id
            ");

            $params = [
                ':id' => $defectId,
                ':status' => $_POST['status'],
                ':closure_image' => $closureImagePath,
                ':rejection_comment' => $_POST['status'] === 'rejected' ? $_POST['rejection_comment'] : null,
                ':updated_by' => $this->userId
            ];

            if (!$stmt->execute($params)) {
                throw new Exception('Failed to update defect', 500);
            }

            // Log the update
            $this->logUpdate($defectId, $currentStatus, $_POST['status']);

            $this->db->commit();

            return [
                'success' => true,
                'message' => 'Defect updated successfully'
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function handleImageUpload($fileKey) {
        if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Error uploading file', 400);
        }

        $file = $_FILES[$fileKey];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Validate file type
        $allowedTypes = ['jpg', 'jpeg', 'png'];
        if (!in_array($ext, $allowedTypes)) {
            throw new Exception('Invalid file type. Only JPG, JPEG, and PNG files are allowed.', 400);
        }

        // Validate file size (5MB max)
        $maxSize = 5 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            throw new Exception('File size exceeds maximum limit of 5MB', 400);
        }

        // Create upload directory if it doesn't exist
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }

        // Generate unique filename
        $fileName = uniqid('defect_') . '_' . date('Ymd_His') . '.' . $ext;
        $filePath = $this->uploadDir . $fileName;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            throw new Exception('Failed to save uploaded file', 500);
        }

        return 'uploads/defect_images/' . $fileName;
    }

    private function logUpdate($defectId, $oldStatus, $newStatus) {
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
            'defect_id' => $defectId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'updated_by' => $this->userId
        ]);

        $stmt->execute([
            $this->userId,
            'update_defect',
            $this->userId,
            $_SERVER['REMOTE_ADDR'],
            $details
        ]);
    }
}

// Main execution
try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Authentication required', 401);
    }

    $database = new Database();
    $handler = new DefectUpdateHandler(
        $database->getConnection(),
        (int)$_SESSION['user_id']
    );

    $handler->validateRequest();
    $response = $handler->updateDefect((int)$_POST['id']);

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
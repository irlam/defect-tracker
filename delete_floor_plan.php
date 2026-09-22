<?php
/**
 * delete_floor_plan.php
 * Floor Plan Deletion Handler
 * Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): 2025-01-26 14:34:56
 * Current User's Login: irlam
 */

// Error reporting and logging setup
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

// Set security headers
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net https://code.jquery.com; style-src 'self' https://cdn.jsdelivr.net");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

require_once 'config/database.php';
require_once 'includes/auth.php';
//require_once 'includes/audit_logger.php';

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'data' => null
];

// Enhanced logging function
function logDeletionAction($action, $status, $details = []) {
    $logEntry = [
        'timestamp' => gmdate('Y-m-d H:i:s'),
        'user_id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? null,
        'ip' => $_SERVER['REMOTE_ADDR'],
        'action' => $action,
        'status' => $status,
        'details' => $details
    ];
    
    error_log(json_encode($logEntry) . "\n", 3, __DIR__ . '/logs/deletion_actions.log');
}

try {
    // Verify request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        throw new Exception('Invalid security token');
    }

    // Validate floor plan ID
    if (!isset($_POST['floor_plan_id']) || !is_numeric($_POST['floor_plan_id'])) {
        throw new Exception('Invalid floor plan ID');
    }

    $floorPlanId = filter_var($_POST['floor_plan_id'], FILTER_VALIDATE_INT);
    if ($floorPlanId === false) {
        throw new Exception('Invalid floor plan ID format');
    }

    // Check for admin privileges
    if ($_SESSION['user_type'] !== 'admin' && $_SESSION['role_id'] !== 1) {
        throw new Exception('Insufficient permissions to delete floor plans');
    }

    $database = new Database();
    $db = $database->getConnection();

    // Begin transaction
    $db->beginTransaction();

    try {
        // Get floor plan details before deletion
        $stmt = $db->prepare("
            SELECT fp.*, p.name as project_name 
            FROM floor_plans fp
            INNER JOIN projects p ON fp.project_id = p.id
            WHERE fp.id = :id
            FOR UPDATE
        ");
        
        $stmt->execute([':id' => $floorPlanId]);
        $floorPlan = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$floorPlan) {
            throw new Exception('Floor plan not found');
        }

        // Check user's project access
        $accessStmt = $db->prepare("
            SELECT can_delete 
            FROM user_project_access 
            WHERE user_id = :user_id 
            AND project_id = :project_id
        ");
        
        $accessStmt->execute([
            ':user_id' => $_SESSION['user_id'],
            ':project_id' => $floorPlan['project_id']
        ]);
        
        $access = $accessStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$access || !$access['can_delete']) {
            throw new Exception('No deletion permission for this project');
        }
        // Store file paths for deletion
        $filesToDelete = [
            __DIR__ . '/' . $floorPlan['file_path']
        ];

        // Add thumbnail path if exists
        if (!empty($floorPlan['thumbnail_path'])) {
            $filesToDelete[] = __DIR__ . '/' . $floorPlan['thumbnail_path'];
        }

        // Check if files are in use by other records
        $filesInUseStmt = $db->prepare("
            SELECT COUNT(*) as use_count 
            FROM floor_plans 
            WHERE (file_path = :file_path OR thumbnail_path = :thumbnail_path)
            AND id != :floor_plan_id
        ");

        $filesInUseStmt->execute([
            ':file_path' => $floorPlan['file_path'],
            ':thumbnail_path' => $floorPlan['thumbnail_path'],
            ':floor_plan_id' => $floorPlanId
        ]);
        
        $filesInUse = $filesInUseStmt->fetch(PDO::FETCH_ASSOC)['use_count'] > 0;

        // Soft delete the floor plan record
        $deleteStmt = $db->prepare("
            UPDATE floor_plans 
            SET status = 'deleted',
                deleted_at = UTC_TIMESTAMP(),
                deleted_by = :deleted_by,
                last_modified = UTC_TIMESTAMP()
            WHERE id = :id
        ");

        if (!$deleteStmt->execute([
            ':id' => $floorPlanId,
            ':deleted_by' => $_SESSION['user_id']
        ])) {
            throw new Exception('Failed to delete floor plan record');
        }

        // Record deletion in audit log
        $auditStmt = $db->prepare("
            INSERT INTO audit_log (
                action_type, entity_type, entity_id, user_id,
                action_timestamp, ip_address, details
            ) VALUES (
                'delete', 'floor_plan', :entity_id, :user_id,
                UTC_TIMESTAMP(), :ip_address, :details
            )
        ");

        $auditDetails = [
            'floor_plan_id' => $floorPlanId,
            'project_id' => $floorPlan['project_id'],
            'project_name' => $floorPlan['project_name'],
            'file_path' => $floorPlan['file_path'],
            'deletion_type' => 'soft_delete',
            'files_in_use' => $filesInUse
        ];

        $auditStmt->execute([
            ':entity_id' => $floorPlanId,
            ':user_id' => $_SESSION['user_id'],
            ':ip_address' => $_SERVER['REMOTE_ADDR'],
            ':details' => json_encode($auditDetails)
        ]);

        // Physical file deletion if files are not in use
        if (!$filesInUse) {
            foreach ($filesToDelete as $filePath) {
                if (file_exists($filePath)) {
                    if (!unlink($filePath)) {
                        // Log file deletion failure but continue
                        logDeletionAction('file_deletion', 'failed', [
                            'file' => $filePath,
                            'floor_plan_id' => $floorPlanId
                        ]);
                    }
                }
            }
        }

        // Create backup record
        $backupStmt = $db->prepare("
            INSERT INTO floor_plan_backups (
                floor_plan_id, project_id, floor_name, level,
                file_path, thumbnail_path, backup_date, backup_by,
                backup_reason, metadata
            ) VALUES (
                :floor_plan_id, :project_id, :floor_name, :level,
                :file_path, :thumbnail_path, UTC_TIMESTAMP(), :backup_by,
                'pre_deletion', :metadata
            )
        ");

        $backupMetadata = [
            'original_upload_date' => $floorPlan['upload_date'],
            'deletion_date' => gmdate('Y-m-d H:i:s'),
            'deleted_by' => $_SESSION['username'],
            'file_hash' => $floorPlan['file_hash'],
            'file_size' => $floorPlan['file_size']
        ];

        $backupStmt->execute([
            ':floor_plan_id' => $floorPlanId,
            ':project_id' => $floorPlan['project_id'],
            ':floor_name' => $floorPlan['floor_name'],
            ':level' => $floorPlan['level'],
            ':file_path' => $floorPlan['file_path'],
            ':thumbnail_path' => $floorPlan['thumbnail_path'],
            ':backup_by' => $_SESSION['user_id'],
            ':metadata' => json_encode($backupMetadata)
        ]);

        // Commit transaction
        $db->commit();

        // Log successful deletion
        logDeletionAction('delete_floor_plan', 'success', [
            'floor_plan_id' => $floorPlanId,
            'project_id' => $floorPlan['project_id'],
            'files_deleted' => !$filesInUse
        ]);
        // Prepare success response
        $response = [
            'success' => true,
            'message' => 'Floor plan deleted successfully',
            'data' => [
                'floor_plan_id' => $floorPlanId,
                'project_id' => $floorPlan['project_id'],
                'project_name' => $floorPlan['project_name'],
                'deletion_time' => gmdate('Y-m-d\TH:i:s\Z'),
                'files_deleted' => !$filesInUse
            ]
        ];

    } catch (PDOException $e) {
        // Rollback transaction
        $db->rollBack();
        
        // Log database error
        logDeletionAction('database_error', 'failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        throw new Exception('Database error occurred during deletion');
    }

} catch (Exception $e) {
    // Log the error
    logDeletionAction('delete_floor_plan', 'failed', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);

    $response = [
        'success' => false,
        'message' => $e->getMessage(),
        'data' => null
    ];
}

// Return JSON response for AJAX requests
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Redirect with message for regular form submissions
$_SESSION['message'] = $response['message'];
$_SESSION['message_type'] = $response['success'] ? 'success' : 'error';

// Determine redirect location
$redirect_url = 'floor_plans.php';
if (isset($_SERVER['HTTP_REFERER']) && 
    strpos($_SERVER['HTTP_REFERER'], $_SERVER['HTTP_HOST']) !== false) {
    $redirect_url = $_SERVER['HTTP_REFERER'];
}

header("Location: $redirect_url");
exit;

?>

<!-- Error handling template for non-AJAX fallback -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Floor Plan - <?php echo htmlspecialchars($response['success'] ? 'Success' : 'Error'); ?></title>
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/custom.css">
</head>
<body class="tool-body" data-bs-theme="dark">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h4><?php echo htmlspecialchars($response['success'] ? 'Success' : 'Error'); ?></h4>
                    </div>
                    <div class="card-body">
                        <p class="<?php echo $response['success'] ? 'text-success' : 'text-danger'; ?>">
                            <?php echo htmlspecialchars($response['message']); ?>
                        </p>
                        <div class="mt-3">
                            <a href="floor_plans.php" class="btn btn-primary">Return to Floor Plans</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="js/jquery.min.js"></script>
    <script src="js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-redirect after 3 seconds for non-AJAX fallback
        setTimeout(function() {
            window.location.href = '<?php echo $redirect_url; ?>';
        }, 3000);
    </script>
</body>
</html>
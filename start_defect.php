<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/logs/error.log');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/defect_workflow.php';
require_once __DIR__ . '/classes/NotificationHelper.php';

$defectId = filter_input(INPUT_POST, 'defect_id', FILTER_VALIDATE_INT) ?: 0;
$redirect = 'my_tasks.php';

try {
    if (!isset($_SESSION['user_id'])) {
        throw new RuntimeException('Please sign in to update this defect.');
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !defectWorkflowHasValidCsrf($_POST['csrf_token'] ?? null)) {
        throw new RuntimeException('Your session expired. Refresh the page and try again.');
    }
    if ($defectId < 1) {
        throw new RuntimeException('Invalid defect.');
    }

    $redirect = 'view_defect_mytasks.php?id=' . $defectId;
    $db = (new Database())->getConnection();
    if (!$db instanceof PDO) {
        throw new RuntimeException('Database connection unavailable.');
    }
    $userId = (int) $_SESSION['user_id'];
    if (!defectWorkflowCanAccessTask($db, $defectId, $userId)) {
        throw new RuntimeException('This defect is not assigned to you.');
    }

    $db->beginTransaction();
    $defect = defectWorkflowLoadForUpdate($db, $defectId);
    defectWorkflowAssertTransition('start', (string) $defect['status']);

    $stmt = $db->prepare("UPDATE defects SET status = 'in_progress', updated_by = :user_id, updated_at = NOW() WHERE id = :defect_id");
    $stmt->execute([':user_id' => $userId, ':defect_id' => $defectId]);
    defectWorkflowLogHistory($db, $defectId, $userId, 'Work started; status changed to In Progress.');
    $db->commit();

    (new NotificationHelper($db))->notifyDefectStatusChanged($defectId, 'in_progress', $userId);
    $_SESSION['success_message'] = "Defect #{$defectId} is now in progress.";
} catch (Throwable $exception) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Start Defect Error: ' . $exception->getMessage());
    $_SESSION['error_message'] = $exception->getMessage();
}

header('Location: ' . BASE_URL . $redirect);
exit;

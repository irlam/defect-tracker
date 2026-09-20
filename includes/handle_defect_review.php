<?php
declare(strict_types=1);

if (!isset($workflowAction, $workflowCommentField)) {
    throw new LogicException('Review action configuration is missing.');
}

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', dirname(__DIR__) . '/logs/error.log');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/constants.php';
require_once __DIR__ . '/defect_workflow.php';
require_once dirname(__DIR__) . '/classes/NotificationHelper.php';

$defectId = filter_input(INPUT_POST, 'defect_id', FILTER_VALIDATE_INT) ?: 0;

try {
    if (!isset($_SESSION['user_id'])) {
        throw new RuntimeException('Please sign in to review defects.');
    }
    if (!defectWorkflowIsReviewer()) {
        throw new RuntimeException('Manager or administrator access is required.');
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !defectWorkflowHasValidCsrf($_POST['csrf_token'] ?? null)) {
        throw new RuntimeException('Your session expired. Refresh the page and try again.');
    }
    if ($defectId < 1) {
        throw new RuntimeException('Invalid defect.');
    }

    $comment = trim((string) ($_POST[$workflowCommentField] ?? ''));
    if ($comment === '') {
        throw new RuntimeException('Please provide a comment for this action.');
    }

    $db = (new Database())->getConnection();
    if (!$db instanceof PDO) {
        throw new RuntimeException('Database connection unavailable.');
    }

    $userId = (int) $_SESSION['user_id'];
    $db->beginTransaction();
    $defect = defectWorkflowLoadForUpdate($db, $defectId);
    $targetStatus = defectWorkflowAssertTransition($workflowAction, (string) $defect['status']);

    if ($workflowAction === 'accept') {
        $stmt = $db->prepare("
            UPDATE defects
            SET status = 'accepted', acceptance_comment = :comment,
                accepted_by = :user_id, accepted_at = NOW(),
                updated_by = :user_id, updated_at = NOW()
            WHERE id = :defect_id
        ");
    } elseif ($workflowAction === 'reject') {
        $stmt = $db->prepare("
            UPDATE defects
            SET status = 'rejected', rejection_comment = :comment,
                rejected_by = :user_id, rejection_status = 'rejected',
                updated_by = :user_id, updated_at = NOW()
            WHERE id = :defect_id
        ");
    } else {
        $stmt = $db->prepare("
            UPDATE defects
            SET status = 'open', reopened_reason = :comment,
                reopened_by = :user_id, reopened_at = NOW(),
                rejection_status = 'reopened', updated_by = :user_id,
                updated_at = NOW()
            WHERE id = :defect_id
        ");
    }
    $stmt->execute([':comment' => $comment, ':user_id' => $userId, ':defect_id' => $defectId]);

    $label = ucwords(str_replace('_', ' ', $targetStatus));
    defectWorkflowLogHistory($db, $defectId, $userId, "Manager review changed status to {$label}: {$comment}");
    $db->commit();

    $notificationStatus = $workflowAction === 'reopen' ? 'reopened' : $targetStatus;
    (new NotificationHelper($db))->notifyDefectStatusChanged($defectId, $notificationStatus, $userId);
    $_SESSION['success_message'] = "Defect #{$defectId} changed to {$label}.";
} catch (Throwable $exception) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log(ucfirst($workflowAction) . ' Defect Error: ' . $exception->getMessage());
    $_SESSION['error_message'] = $exception->getMessage();
}

header('Location: ' . BASE_URL . 'defects.php');
exit;

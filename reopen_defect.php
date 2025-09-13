<?php
// reopen_defect.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/database.php';
require_once 'config/constants.php';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['defect_id'], $_POST['reopen_comment'])) {
        $defectId = (int)$_POST['defect_id'];
        $reopenComment = trim($_POST['reopen_comment']);
        $userId = (int)$_SESSION['user_id'];
        $currentDateTime = date('Y-m-d H:i:s');

        $database = new Database();
        $db = $database->getConnection();

        // Update the defect with reopen reason and set status to 'open'
        $query = "UPDATE defects 
                  SET reopened_reason = :reopened_reason, 
                      reopened_by = :reopened_by, 
                      reopened_at = :reopened_at, 
                      status = 'open'
                  WHERE id = :defect_id";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':reopened_reason', $reopenComment);
        $stmt->bindParam(':reopened_by', $userId);
        $stmt->bindParam(':reopened_at', $currentDateTime);
        $stmt->bindParam(':defect_id', $defectId);

        if ($stmt->execute() && $stmt->rowCount() > 0) {
            $_SESSION['success_message'] = "Defect #{$defectId} has been reopened successfully.";
        } else {
            throw new Exception("Failed to reopen defect #{$defectId}.");
        }
    } else {
        throw new Exception("Invalid request.");
    }
} catch (Exception $e) {
    error_log("Reopen Defect Error: " . $e->getMessage());
    error_log("User: {$_SESSION['username']}, Defect ID: {$defectId}, Reopen Comment: {$reopenComment}");
    $_SESSION['error_message'] = "Error: " . $e->getMessage();
}

header("Location: " . BASE_URL . "defects.php");
exit();
?>
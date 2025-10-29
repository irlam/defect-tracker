<?php
// reject_defect.php
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
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['defect_id'], $_POST['rejection_comment'])) {
        $defectId = (int)$_POST['defect_id'];
        $rejectionComment = trim($_POST['rejection_comment']);
        $userId = (int)$_SESSION['user_id'];
        $currentDateTime = date('Y-m-d H:i:s');

        $database = new Database();
        $db = $database->getConnection();

        // Update the defect with rejection comment and set status to 'rejected'
        $query = "UPDATE defects 
                  SET rejection_comment = :rejection_comment, 
                      rejected_by = :rejected_by, 
                      updated_at = :updated_at, 
                      status = 'rejected'
                  WHERE id = :defect_id";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':rejection_comment', $rejectionComment);
        $stmt->bindParam(':rejected_by', $userId);
        $stmt->bindParam(':updated_at', $currentDateTime);
        $stmt->bindParam(':defect_id', $defectId);

        if ($stmt->execute() && $stmt->rowCount() > 0) {
            $_SESSION['success_message'] = "Defect #{$defectId} has been rejected successfully.";
        } else {
            throw new Exception("Failed to reject defect #{$defectId}.");
        }
    } else {
        throw new Exception("Invalid request.");
    }
} catch (Exception $e) {
    error_log("Reject Defect Error: " . $e->getMessage());
    error_log("User: {$_SESSION['username']}, Defect ID: {$defectId}, Rejection Comment: {$rejectionComment}");
    $_SESSION['error_message'] = "Error: " . $e->getMessage();
}

header("Location: " . BASE_URL . "defects.php");
exit();
?>
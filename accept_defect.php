<?php
// accept_defect.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/database.php';
require_once 'config/constants.php'; // this file should define BASE_URL, etc.
require_once 'classes/NotificationHelper.php';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['defect_id'], $_POST['acceptance_comment'])) {
        $defectId = (int)$_POST['defect_id'];
        $acceptanceComment = trim($_POST['acceptance_comment']);
        $userId = (int)$_SESSION['user_id'];
        $currentDateTime = date('Y-m-d H:i:s'); // Current UTC timestamp

        // Database connection
        $database = new Database();
        $db = $database->getConnection();

        // Update the defect with acceptance comment and set status to 'accepted'
        $query = "UPDATE defects 
                  SET acceptance_comment = :acceptance_comment, 
                      accepted_by = :accepted_by, 
                      accepted_at = :accepted_at, 
                      status = 'accepted'
                  WHERE id = :defect_id";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':acceptance_comment', $acceptanceComment);
        $stmt->bindParam(':accepted_by', $userId);
        $stmt->bindParam(':accepted_at', $currentDateTime);
        $stmt->bindParam(':defect_id', $defectId);

        if ($stmt->execute() && $stmt->rowCount() > 0) {
            // Send notifications
            $notificationHelper = new NotificationHelper($db);
            $notificationHelper->notifyDefectStatusChanged($defectId, 'accepted', $userId);

            $_SESSION['success_message'] = "Defect #{$defectId} has been accepted successfully.";
        } else {
            throw new Exception("Failed to accept defect #{$defectId}.");
        }
    } else {
        throw new Exception("Invalid request.");
    }
} catch (Exception $e) {
    error_log("Accept Defect Error: " . $e->getMessage());
    error_log("User: {$_SESSION['username']}, Defect ID: {$defectId}, Acceptance Comment: {$acceptanceComment}");
    $_SESSION['error_message'] = "Error: " . $e->getMessage();
}

header("Location: " . BASE_URL . "defects.php");
exit();
?>
<?php
// classes/Logger.php
class Logger {
    private $db;
    private $currentUser;
    private $currentDateTime;

    public function __construct($db, $currentUser = 'irlam', $currentDateTime = '2025-01-14 21:17:03') {
        $this->db = $db;
        $this->currentUser = $currentUser;
        $this->currentDateTime = $currentDateTime;
    }

    public function logActivity($action, $details, $userId = null) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO activity_logs (
                    user_id,
                    action,
                    details,
                    ip_address,
                    user_agent,
                    created_by,
                    created_at
                ) VALUES (
                    :user_id,
                    :action,
                    :details,
                    :ip_address,
                    :user_agent,
                    :created_by,
                    :created_at
                )
            ");

            return $stmt->execute([
                ':user_id' => $userId,
                ':action' => $action,
                ':details' => json_encode($details),
                ':ip_address' => $_SERVER['REMOTE_ADDR'],
                ':user_agent' => $_SERVER['HTTP_USER_AGENT'],
                ':created_by' => $this->currentUser,
                ':created_at' => $this->currentDateTime
            ]);
        } catch (Exception $e) {
            error_log("Logging error: " . $e->getMessage());
            return false;
        }
    }
}

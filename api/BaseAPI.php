<?php
// api/BaseAPI.php
class BaseAPI {
    protected $db;
    protected $currentUser = 'irlam';
    protected $currentDateTime = '2025-01-15 07:52:47';

    public function __construct($db) {
        $this->db = $db;
        header('Content-Type: application/json');
        
        // Check authentication
        session_start();
        if (!isset($_SESSION['user_id'])) {
            $this->sendResponse(false, 'Unauthorized access', 401);
            exit();
        }
    }

    protected function sendResponse($success, $message, $statusCode = 200, $data = null) {
        http_response_code($statusCode);
        echo json_encode([
            'success' => $success,
            'message' => $message,
            'data' => $data
        ]);
    }
}
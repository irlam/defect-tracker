<?php
// api_authentication.php
// Error reporting and logging setup
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/assign_to_user.log');
class APIAuthentication {
    private $db;
    private $currentUser;
    private $currentDateTime;

    public function __construct($db) {
        $this->db = $db;
        $this->currentUser = 'irlam';
        $this->currentDateTime = '2025-01-14 21:31:43';
    }

    public function generateApiKey($userId) {
        $apiKey = bin2hex(random_bytes(32));
        $hashedKey = password_hash($apiKey, PASSWORD_DEFAULT);

        $stmt = $this->db->prepare("
            INSERT INTO api_keys (
                user_id, 
                api_key_hash, 
                created_by, 
                created_at,
                expires_at
            ) VALUES (
                :user_id, 
                :api_key_hash, 
                :created_by, 
                :created_at,
                DATE_ADD(:created_at, INTERVAL 1 YEAR)
            )
        ");

        $stmt->execute([
            ':user_id' => $userId,
            ':api_key_hash' => $hashedKey,
            ':created_by' => $this->currentUser,
            ':created_at' => $this->currentDateTime
        ]);

        return $apiKey;
    }

    public function validateApiKey($apiKey) {
        $stmt = $this->db->prepare("
            SELECT ak.*, u.status 
            FROM api_keys ak
            JOIN users u ON ak.user_id = u.id
            WHERE ak.revoked = 0 
            AND ak.expires_at > NOW()
            AND u.status = 'active'
        ");
        $stmt->execute();
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (password_verify($apiKey, $row['api_key_hash'])) {
                return $row;
            }
        }
        return false;
    }
}
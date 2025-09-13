<?php
// classes/TwoFactorAuth.php
class TwoFactorAuth {
    private $db;
    private $emailService;
    
    public function __construct($db) {
        $this->db = $db;
        $this->emailService = new EmailService();
    }

    public function generateTwoFactorCode($userId) {
        $code = sprintf("%06d", mt_rand(0, 999999));
        $expiry = date('d-m-Y H:i:s', strtotime('+5 minutes'));
        
        $stmt = $this->db->prepare("
            UPDATE users SET 
                two_factor_code = :code,
                two_factor_expiry = :expiry
            WHERE id = :id
        ");
        
        return $stmt->execute([
            ':code' => $code,
            ':expiry' => $expiry,
            ':id' => $userId
        ]) ? $code : false;
    }

    public function verifyTwoFactorCode($userId, $code) {
        $stmt = $this->db->prepare("
            SELECT two_factor_code, two_factor_expiry 
            FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) return false;
        
        return $result['two_factor_code'] === $code && 
               strtotime($result['two_factor_expiry']) > time();
    }
}
<?php
// api/reset_password.php
session_start();
require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../classes/Logger.php';
require_once '../classes/EmailService.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $logger = new Logger($db, 'irlam', '2025-01-14 21:17:03');
    $emailService = new EmailService();

    // Generate reset token
    $resetToken = bin2hex(random_bytes(32));
    $tokenExpiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

    // Store reset token
    $stmt = $db->prepare("
        UPDATE users SET 
            reset_token = :reset_token,
            reset_token_expiry = :reset_token_expiry,
            updated_by = :updated_by,
            updated_at = :updated_at
        WHERE id = :id
    ");

    $stmt->execute([
        ':id' => $_POST['id'],
        ':reset_token' => $resetToken,
        ':reset_token_expiry' => $tokenExpiry,
        ':updated_by' => 'irlam',
        ':updated_at' => '2025-01-14 21:17:03'
    ]);

    // Get user details
    $stmt = $db->prepare("SELECT username, email FROM users WHERE id = ?");
    $stmt->execute([$_POST['id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Send reset email
    if ($emailService->sendPasswordResetEmail($user, $resetToken)) {
        // Log activity
        $logger->logActivity(
            'password_reset_requested',
            ['user_id' => $_POST['id'], 'username' => $user['username']],
            $_SESSION['user_id']
        );

        echo json_encode(['success' => true, 'message' => 'Password reset email sent']);
    } else {
        throw new Exception('Failed to send reset email');
    }

} catch (Exception $e) {
    error_log("Password reset error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error processing password reset']);
}

// Create database tables for logging
$db->exec("
    CREATE TABLE IF NOT EXISTS activity_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        action VARCHAR(255) NOT NULL,
        details TEXT,
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_by VARCHAR(255),
        created_at DATETIME,
        FOREIGN KEY (user_id) REFERENCES users(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// Add audit triggers to users table
$db->exec("
    CREATE TRIGGER users_audit_insert AFTER INSERT ON users
    FOR EACH ROW
    BEGIN
        INSERT INTO activity_logs (
            user_id,
            action,
            details,
            created_by,
            created_at
        ) VALUES (
            NEW.id,
            'user_created',
            JSON_OBJECT(
                'username', NEW.username,
                'email', NEW.email,
                'user_type', NEW.user_type,
                'status', NEW.status
            ),
            NEW.created_by,
            NEW.created_at
        );
    END;
");

$db->exec("
    CREATE TRIGGER users_audit_update AFTER UPDATE ON users
    FOR EACH ROW
    BEGIN
        INSERT INTO activity_logs (
            user_id,
            action,
            details,
            created_by,
            created_at
        ) VALUES (
            NEW.id,
            'user_updated',
            JSON_OBJECT(
                'old_data', JSON_OBJECT(
                    'username', OLD.username,
                    'email', OLD.email,
                    'user_type', OLD.user_type,
                    'status', OLD.status
                ),
                'new_data', JSON_OBJECT(
                    'username', NEW.username,
                    'email', NEW.email,
                    'user_type', NEW.user_type,
                    'status', NEW.status
                )
            ),
            NEW.updated_by,
            NEW.updated_at
        );
    END;
");
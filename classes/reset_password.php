<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Logger.php';
require_once __DIR__ . '/../classes/EmailService.php';

if (strtolower((string)($_SESSION['user_type'] ?? '')) !== 'admin' || empty($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success'=>false,'message'=>'Forbidden']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false,'message'=>'Method not allowed']);
    exit;
}
$csrf=(string)($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (empty($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'],$csrf)) {
    http_response_code(403);
    echo json_encode(['success'=>false,'message'=>'Invalid CSRF token']);
    exit;
}
$userId=filter_var($_POST['id'] ?? null,FILTER_VALIDATE_INT);
if (!$userId) {
    http_response_code(422);
    echo json_encode(['success'=>false,'message'=>'Invalid user']);
    exit;
}

try {
    $db=(new Database())->getConnection();
    if (!$db) throw new RuntimeException('Database unavailable.');

    $stmt=$db->prepare('SELECT username,email FROM users WHERE id=? AND deleted_at IS NULL');
    $stmt->execute([$userId]);
    $user=$stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        http_response_code(404);
        echo json_encode(['success'=>false,'message'=>'User not found']);
        exit;
    }

    $resetToken=bin2hex(random_bytes(32));
    $tokenHash=hash('sha256',$resetToken);
    $stmt=$db->prepare("UPDATE users SET reset_token=:token, reset_token_expiry=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR), updated_by=:updated_by, updated_at=UTC_TIMESTAMP() WHERE id=:id");
    $stmt->execute([
        ':token'=>$tokenHash,
        ':updated_by'=>(string)($_SESSION['username'] ?? $_SESSION['user_id']),
        ':id'=>$userId
    ]);

    $emailService=new EmailService();
    if (!$emailService->sendPasswordResetEmail($user,$resetToken)) {
        throw new RuntimeException('Password reset email could not be sent.');
    }

    try {
        $logger=new Logger($db,(string)($_SESSION['username'] ?? ''),gmdate('Y-m-d H:i:s'));
        $logger->logActivity('password_reset_requested',['user_id'=>$userId,'username'=>$user['username']],(int)$_SESSION['user_id']);
    } catch (Throwable $logError) {
        error_log('Password reset audit log failed: '.$logError->getMessage());
    }

    echo json_encode(['success'=>true,'message'=>'Password reset email sent']);
} catch (Throwable $e) {
    error_log('Password reset error: '.$e->getMessage());
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Unable to process password reset']);
}

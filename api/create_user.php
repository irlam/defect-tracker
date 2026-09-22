<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['success'=>false,'message'=>'Method not allowed']); exit;
}
if (empty($_SESSION['user_id']) || strtolower((string)($_SESSION['user_type'] ?? '')) !== 'admin') {
    http_response_code(403); echo json_encode(['success'=>false,'message'=>'Forbidden']); exit;
}
$csrf=(string)($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (empty($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'],$csrf)) {
    http_response_code(403); echo json_encode(['success'=>false,'message'=>'Invalid CSRF token']); exit;
}

$db=null;
try {
    $db=(new Database())->getConnection();
    if (!$db) throw new RuntimeException('Database unavailable.');
    $username=trim((string)($_POST['username'] ?? ''));
    $email=trim((string)($_POST['email'] ?? ''));
    $password=(string)($_POST['password'] ?? '');
    $userType=strtolower(trim((string)($_POST['user_type'] ?? '')));
    if ($username==='' || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($password)<12 || !in_array($userType,['admin','manager','contractor','client','viewer'],true)) {
        throw new InvalidArgumentException('Enter valid user details and a password of at least 12 characters.');
    }

    $db->beginTransaction();
    $actor=(string)($_SESSION['username'] ?? $_SESSION['user_id']);
    $stmt=$db->prepare("INSERT INTO users (username,email,password,user_type,status,created_by,created_at)
        VALUES (:username,:email,:password,:user_type,'active',:created_by,NOW())");
    $stmt->execute([':username'=>$username,':email'=>$email,':password'=>password_hash($password,PASSWORD_DEFAULT),':user_type'=>$userType,':created_by'=>$actor]);
    $userId=(int)$db->lastInsertId();

    if ($userType==='contractor' && trim((string)($_POST['company_name'] ?? ''))!=='') {
        $stmt=$db->prepare("INSERT INTO contractors (user_id,company_name,created_at) VALUES (:user_id,:company_name,NOW())");
        $stmt->execute([':user_id'=>$userId,':company_name'=>trim((string)$_POST['company_name'])]);
    }
    $db->commit();
    echo json_encode(['success'=>true,'message'=>'User created successfully','user_id'=>$userId]);
} catch (InvalidArgumentException $e) {
    if ($db instanceof PDO && $db->inTransaction()) $db->rollBack();
    http_response_code(422); echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
} catch (Throwable $e) {
    if ($db instanceof PDO && $db->inTransaction()) $db->rollBack();
    error_log('Create user error: '.$e->getMessage());
    http_response_code(500); echo json_encode(['success'=>false,'message'=>'Unable to create user.']);
}

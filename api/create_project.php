<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['success'=>false,'message'=>'Method not allowed']); exit;
}
if (empty($_SESSION['user_id']) || !in_array(strtolower((string)($_SESSION['user_type'] ?? '')), ['admin','manager'], true)) {
    http_response_code(403); echo json_encode(['success'=>false,'message'=>'Forbidden']); exit;
}
$csrf = (string)($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (empty($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], $csrf)) {
    http_response_code(403); echo json_encode(['success'=>false,'message'=>'Invalid CSRF token']); exit;
}

try {
    $db=(new Database())->getConnection();
    if (!$db) throw new RuntimeException('Database unavailable.');
    $name=trim((string)($_POST['name'] ?? ''));
    $description=trim((string)($_POST['description'] ?? ''));
    $status=strtolower(trim((string)($_POST['status'] ?? '')));
    $startDate=trim((string)($_POST['start_date'] ?? ''));
    $endDate=trim((string)($_POST['end_date'] ?? '')) ?: null;
    if ($name==='' || $description==='' || $startDate==='' || !in_array($status,['planning','active','on_hold','completed','archived'],true)) {
        throw new InvalidArgumentException('Invalid project details.');
    }
    $actor=(string)($_SESSION['username'] ?? $_SESSION['user_id']);
    $stmt=$db->prepare("INSERT INTO projects (name,description,status,start_date,end_date,created_at,created_by,updated_at,updated_by)
        VALUES (:name,:description,:status,:start_date,:end_date,NOW(),:created_by,NOW(),:updated_by)");
    $stmt->execute([':name'=>$name,':description'=>$description,':status'=>$status,':start_date'=>$startDate,':end_date'=>$endDate,':created_by'=>$actor,':updated_by'=>$actor]);
    echo json_encode(['success'=>true,'message'=>'Project created successfully','projectId'=>(int)$db->lastInsertId()]);
} catch (InvalidArgumentException $e) {
    http_response_code(422); echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
} catch (Throwable $e) {
    error_log('Create project error: '.$e->getMessage());
    http_response_code(500); echo json_encode(['success'=>false,'message'=>'Unable to create project.']);
}

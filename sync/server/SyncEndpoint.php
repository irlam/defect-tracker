<?php
declare(strict_types=1);
namespace Sync;

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../init.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_id']) && empty($_SESSION['username']) && empty($_SESSION['user'])) {
    http_response_code(401); echo json_encode(['error'=>'Authentication required']); exit;
}
$db = (new \Database())->getConnection();
if (!$db) { http_response_code(503); echo json_encode(['error'=>'Service unavailable']); exit; }

$user = (string)($_SESSION['username'] ?? $_SESSION['user'] ?? $_SESSION['user_id']);
$syncManager = new SyncManager($db);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(['status'=>'online','timestamp'=>gmdate('c')]); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['error'=>'Method not allowed']); exit;
}
$csrf = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!empty($_SESSION['csrf_token']) && !hash_equals((string)$_SESSION['csrf_token'], $csrf)) {
    http_response_code(403); echo json_encode(['error'=>'Invalid CSRF token']); exit;
}
$request = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($request) || !isset($request['queue']) || !is_array($request['queue'])) {
    http_response_code(400); echo json_encode(['error'=>'Invalid sync queue data']); exit;
}
echo json_encode(['status'=>'success','results'=>$syncManager->processSyncQueue($request['queue'],$user)]);

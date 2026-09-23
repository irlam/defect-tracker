<?php
declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false,'message'=>'Method not allowed']);
    exit;
}

$csrf = (string)($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if ($csrf === '' || !hash_equals((string)($_SESSION['csrf_token'] ?? ''), $csrf)) {
    http_response_code(403);
    echo json_encode(['success'=>false,'message'=>'Invalid CSRF token']);
    exit;
}

if (!$training->isReady()) {
    http_response_code(409);
    echo json_encode(['success'=>false,'message'=>'Training schema is not installed yet']);
    exit;
}

$lessonId = (int)($_POST['lesson_id'] ?? 0);
$status = trim((string)($_POST['status'] ?? 'in_progress'));
$percent = (int)($_POST['progress_percent'] ?? ($status === 'completed' ? 100 : 10));
$lastPosition = trim((string)($_POST['last_position'] ?? ''));

if ($lessonId < 1) {
    http_response_code(422);
    echo json_encode(['success'=>false,'message'=>'Invalid lesson']);
    exit;
}

$ok = $training->updateProgress($userId, $lessonId, $status, $percent, $lastPosition !== '' ? $lastPosition : null);

echo json_encode([
    'success'=>$ok,
    'status'=>$status,
    'progress_percent'=>$status === 'completed' ? 100 : max(0, min(100, $percent)),
]);

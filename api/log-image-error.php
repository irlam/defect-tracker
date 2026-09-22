<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors','0');
ini_set('log_errors','1');
ini_set('error_log',__DIR__.'/../logs/error.log');
header('Content-Type: application/json');

if (session_status()===PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_id'])) { http_response_code(401); exit(json_encode(['error'=>'Unauthorized'])); }
if ($_SERVER['REQUEST_METHOD']!=='POST') { http_response_code(405); exit(json_encode(['error'=>'Method not allowed'])); }
$csrf=(string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (empty($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'],$csrf)) {
    http_response_code(403); exit(json_encode(['error'=>'Invalid CSRF token']));
}
$data=json_decode((string)file_get_contents('php://input'),true);
if (!is_array($data) || empty($data['path']) || empty($data['type'])) {
    http_response_code(400); exit(json_encode(['error'=>'Invalid request data']));
}
$path=(string)$data['path'];
if (str_contains($path,'..')) { http_response_code(400); exit(json_encode(['error'=>'Invalid path'])); }
$entry=[
    'timestamp'=>gmdate('c'),
    'user_id'=>(int)$_SESSION['user_id'],
    'username'=>(string)($_SESSION['username'] ?? ''),
    'path'=>$path,
    'type'=>substr((string)$data['type'],0,80),
    'ip_address'=>(string)($_SERVER['REMOTE_ADDR'] ?? '')
];
error_log('[IMAGE_ERROR] '.json_encode($entry,JSON_UNESCAPED_SLASHES));
@file_put_contents(__DIR__.'/../logs/image_errors.log',gmdate('Y-m-d H:i:s').' | '.json_encode($entry,JSON_UNESCAPED_SLASHES)."\n",FILE_APPEND|LOCK_EX);
echo json_encode(['success'=>true,'message'=>'Error logged successfully']);

<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors','0');
ini_set('log_errors','1');
ini_set('error_log',__DIR__.'/../logs/error.log');
header('Content-Type: application/json');
require_once __DIR__.'/../config/database.php';

if (session_status()===PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_id'])) { http_response_code(401); exit(json_encode(['error'=>'Unauthorized'])); }
if ($_SERVER['REQUEST_METHOD']!=='POST') { http_response_code(405); exit(json_encode(['error'=>'Method not allowed'])); }
$csrf=(string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '');
if (empty($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'],$csrf)) {
    http_response_code(403); exit(json_encode(['error'=>'Invalid CSRF token']));
}
$data=json_decode((string)file_get_contents('php://input'),true);
$defectId=filter_var($data['defect_id'] ?? $_POST['defect_id'] ?? null,FILTER_VALIDATE_INT);
if (!$defectId) { http_response_code(400); exit(json_encode(['error'=>'Invalid defect ID'])); }

$db=null;
try {
    $db=(new Database())->getConnection();
    if (!$db) throw new RuntimeException('Database unavailable.');

    $stmt=$db->prepare("SELECT r.name FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=?");
    $stmt->execute([(int)$_SESSION['user_id']]);
    $roles=array_map('strtolower',$stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    $userType=strtolower((string)($_SESSION['user_type'] ?? ''));
    if ($userType!=='admin' && !in_array('admin',$roles,true)) {
        http_response_code(403); exit(json_encode(['error'=>'Forbidden']));
    }

    $db->beginTransaction();
    $stmt=$db->prepare("SELECT title,status FROM defects WHERE id=:id AND deleted_at IS NULL FOR UPDATE");
    $stmt->execute([':id'=>$defectId]);
    $defect=$stmt->fetch(PDO::FETCH_ASSOC);
    if (!$defect) {
        $db->rollBack(); http_response_code(404); exit(json_encode(['error'=>'Defect not found']));
    }

    $stmt=$db->prepare("UPDATE defects SET deleted_at=NOW(),deleted_by=:uid,updated_at=NOW(),updated_by=:uid WHERE id=:id");
    $stmt->execute([':uid'=>(int)$_SESSION['user_id'],':id'=>$defectId]);

    foreach (['audit_logs','audit_log'] as $table) {
        try {
            $log=$db->prepare("INSERT INTO {$table} (action,table_name,record_id,old_values,user_id,created_at) VALUES ('DELETE','defects',:id,:old,:uid,NOW())");
            $log->execute([':id'=>$defectId,':old'=>json_encode($defect),':uid'=>(int)$_SESSION['user_id']]);
            break;
        } catch (PDOException $ignored) {}
    }
    $db->commit();
    echo json_encode(['success'=>true,'message'=>'Defect deleted successfully','defect_id'=>$defectId]);
} catch (Throwable $e) {
    if ($db instanceof PDO && $db->inTransaction()) $db->rollBack();
    error_log('Delete defect error: '.$e->getMessage());
    http_response_code(500); echo json_encode(['success'=>false,'message'=>'Unable to delete defect.']);
}

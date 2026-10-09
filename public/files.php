<?php
declare(strict_types=1);
require dirname(__DIR__).'/staging/bootstrap.php';
if(($_SERVER['REQUEST_METHOD']??'')!=='GET')stageFail(405);
stageCurrent();
try{$file=$register->attachment($_GET['id']??null);}catch(Throwable $e){stageFail(404,'Attachment unavailable.');}
header('Content-Type: '.$file['mime']);header('Content-Length: '.$file['file_size']);header('Content-Security-Policy: sandbox');
header("Content-Disposition: attachment; filename*=UTF-8''".rawurlencode($file['original_name']));readfile($file['path']);

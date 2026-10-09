<?php
declare(strict_types=1);
require dirname(__DIR__).'/staging/bootstrap.php';
if(($_SERVER['REQUEST_METHOD']??'')!=='GET')stageFail(405);
stageCurrent();header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="defects.csv"');
$out=fopen('php://output','w');fputcsv($out,['ID','Title','Description','Status','Priority','Updated']);
foreach($register->all()as$row){$fields=[];foreach(['id','title','description','status','priority','updated_at']as$key){$value=(string)$row[$key];if(preg_match('/^[\s]*[=+@-]/',$value))$value="'".$value;$fields[]=$value;}fputcsv($out,$fields);}fclose($out);

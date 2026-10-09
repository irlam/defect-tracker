<?php
declare(strict_types=1);
require dirname(__DIR__).'/staging/bootstrap.php';
$method=$_SERVER['REQUEST_METHOD']??'';
try{
 if($method==='GET'){if(isset($_GET['action']))stageFail(405);stageCurrent();stageJson(['ok'=>true,'defects'=>$register->all()]);}
 if($method!=='POST')stageFail(405);
 $current=stageWrite();$action=$_GET['action']??'';
 if($action==='upload'){
  $id=$register->attach($_POST['defect_id']??null,$_FILES['file']??[],$current['user']['id']);stageJson(['ok'=>true,'attachment_id'=>$id]);
 }
 $raw=file_get_contents('php://input',false,null,0,20001);if($raw===false||strlen($raw)>20000)stageFail(413);
 $data=json_decode($raw,true,16,JSON_THROW_ON_ERROR);if(!is_array($data))stageFail(400);
 if($action==='create')$id=$register->save($data,$current['user']['id']);
 elseif($action==='update'){$id=\DefectTracker\Suite\Defects::id($data['id']??null);unset($data['id']);$id=$register->save($data,$current['user']['id'],$id);}
 else stageFail(404);
 stageJson(['ok'=>true,'id'=>$id]);
}catch(Throwable $e){stageFail(422,'Check the defect details and try again.');}

<?php
declare(strict_types=1);
// Packaged outside the public document root. No legacy auth/config is loaded.
$root=dirname(__DIR__);
foreach(['Gateway','ProjectScope','Configuration','SessionStore','SignIn','CookiePolicy','UserMap','Defects']as$class)require_once $root.'/includes/Suite/'.$class.'.php';
use DefectTracker\Suite\Configuration;
use DefectTracker\Suite\Gateway;
use DefectTracker\Suite\ProjectScope;
use DefectTracker\Suite\SessionStore;
use DefectTracker\Suite\SignIn;
use DefectTracker\Suite\CookiePolicy;
use DefectTracker\Suite\UserMap;
use DefectTracker\Suite\Defects;
foreach(CookiePolicy::headers()as$key=>$value)header($key.': '.$value);
ini_set('display_errors','0');
function stageFail(int $status,string $message='Access is not available.'):never{http_response_code($status);header('Content-Type: application/json');echo json_encode(['ok'=>false,'error'=>$message]);exit;}
function stageJson(array $value):never{header('Content-Type: application/json');echo json_encode($value,JSON_THROW_ON_ERROR);exit;}
function stageEscape(string $value):string{return htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
$route=parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH);
if(!in_array($route,['/','/index.php','/suite-login.php','/suite-logout.php','/api.php','/files.php','/export.php'],true))stageFail(404);
try{
 $config=Configuration::load($root);
 CookiePolicy::requireOrigin($_SERVER,$config);
 $gateway=new Gateway($config);$store=new SessionStore($config['session_root'],$config);
 if($route==='/suite-logout.php'){$flow=new SignIn($gateway,null,$store);}
 else{
  $db=Configuration::connect($config);$scope=new ProjectScope($gateway,$db,$config);
  $flow=new SignIn($gateway,$scope,$store);$mapping=new UserMap($db,$config);$register=new Defects($db,$config);
 }
}catch(Throwable $e){stageFail(503,'Project tool is not configured.');}
function stageCurrent():array{
 global $flow,$mapping;
 $id=$_COOKIE[CookiePolicy::SESSION]??'';
 if(!is_string($id))stageFail(401);
 try{$current=$flow->current($id);$current['user']=$mapping->resolve($current['identity']);return $current;}
 catch(Throwable $e){setcookie(CookiePolicy::SESSION,'',CookiePolicy::options(1));stageFail(401,'Sign in through Construction Suite.');}
}
function stageWrite():array{
 global $flow,$mapping;
 $id=$_COOKIE[CookiePolicy::SESSION]??'';$csrf=$_SERVER['HTTP_X_CSRF_TOKEN']??($_POST['csrf']??'');
 if(!is_string($id)||!is_string($csrf))stageFail(403);
 try{$identity=$flow->requireWrite($id,$_SERVER['REQUEST_METHOD']??'',$csrf);return ['identity'=>$identity,'user'=>$mapping->resolve($identity)];}
 catch(Throwable $e){stageFail(403,'Changes are not available to this account.');}
}

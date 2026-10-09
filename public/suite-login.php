<?php
declare(strict_types=1);
require dirname(__DIR__).'/staging/bootstrap.php';
use DefectTracker\Suite\CookiePolicy;
$method=$_SERVER['REQUEST_METHOD']??'';
try{
 if($method==='GET'){
  $pending=$flow->begin();setcookie(CookiePolicy::PENDING,$pending['pending_id'],CookiePolicy::pendingOptions(time()+300));
  header('Location: '.$pending['url'],true,303);exit;
 }
 if($method!=='POST')stageFail(405);
 $pending=$_COOKIE[CookiePolicy::PENDING]??'';$code=$_POST['code']??'';$state=$_POST['state']??'';
 setcookie(CookiePolicy::PENDING,'',CookiePolicy::pendingOptions(1));
 if(!is_string($pending)||!is_string($code)||!is_string($state))stageFail(400);
 // Clear a prior local session before accepting an account switch.
 $old=$_COOKIE[CookiePolicy::SESSION]??'';
 if(is_string($old)&&$old!==''){try{$record=$store->consume($old);if($record['kind']==='authenticated')$gateway->revoke($record['token']);}catch(Throwable $ignored){}}
 setcookie(CookiePolicy::SESSION,'',CookiePolicy::options(1));
 $accepted=$flow->accept($pending,$code,$state);$mapping->resolve($accepted['identity']);
 setcookie(CookiePolicy::SESSION,$accepted['session_id'],CookiePolicy::options($accepted['identity']['session_expires_at']));
 header('Location: /',true,303);exit;
}catch(Throwable $e){
 if(isset($accepted)){try{$record=$store->consume($accepted['session_id']);$gateway->revoke($record['token']);}catch(Throwable $ignored){}}
 stageFail(401,'Sign-in could not be verified.');
}

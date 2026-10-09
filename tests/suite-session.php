<?php
declare(strict_types=1);
foreach (['Gateway','ProjectScope','SessionStore','SignIn','CookiePolicy'] as $class) require dirname(__DIR__).'/includes/Suite/'.$class.'.php';
use DefectTracker\Suite\Gateway;
use DefectTracker\Suite\ProjectScope;
use DefectTracker\Suite\SessionStore;
use DefectTracker\Suite\SignIn;
use DefectTracker\Suite\CookiePolicy;
$checks=0;
function verifySession(bool $value,string $label): void {global $checks;if(!$value)throw new RuntimeException($label);$checks++;}
function denySession(callable $callback,string $label): void {try{$callback();}catch(RuntimeException $e){verifySession(true,$label);return;}throw new RuntimeException('Expected denial: '.$label);}
$binding=['staging_only'=>true,'module_key'=>'defects','suite_origin'=>'https://suite.defecttracker.uk','origin'=>'https://alpha.defectnotice.site','instance_id'=>3,'organization_id'=>7,'project_id'=>7,'local_project_id'=>1,'key'=>str_repeat('a',64)];
$identity=['instance_id'=>3,'organization_id'=>7,'project_id'=>7,'module_key'=>'defects','user_id'=>8,'role'=>'manager','name'=>'Fixture manager','email'=>'fixture@example.invalid','session_expires_at'=>time()+600,'session_token'=>str_repeat('b',64)];
$outage=false;$allowed=true;$calls=[];
$gateway=new Gateway($binding,static function($url,$payload,$key)use(&$identity,&$outage,&$allowed,&$calls){
    $calls[]=$payload;
    if($outage)throw new RuntimeException('fixture-secret');
    if(!$allowed)return ['ok'=>false];
    return ($payload['action']??'')==='revoke'?['ok'=>true]:['ok'=>true,'identity'=>$identity];
});
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE projects(id INTEGER PRIMARY KEY); INSERT INTO projects VALUES(1); CREATE TABLE suite_instance_binding(instance_id INTEGER,organization_id INTEGER,suite_project_id INTEGER,local_project_id INTEGER,module_key TEXT); INSERT INTO suite_instance_binding VALUES(3,7,7,1,'defects')");
$tmp=sys_get_temp_dir().'/defects-sessions-'.bin2hex(random_bytes(8));mkdir($tmp,0700);
$now=time();$store=new SessionStore($tmp,$binding,static function()use(&$now){return $now;});
$flow=new SignIn($gateway,new ProjectScope($gateway,$db,$binding),$store);
$code=str_repeat('c',64);
$signin=static function()use($flow,$code){$pending=$flow->begin();parse_str(parse_url($pending['url'],PHP_URL_QUERY),$query);return [$pending,$flow->accept($pending['pending_id'],$code,$query['state'])];};
try {
    [$pending,$session]=$signin();
    verifySession($pending['pending_id']!==$session['session_id'],'session fixation prevented');
    verifySession(!str_contains(json_encode($session),'session_token')&&!str_contains(json_encode($session),str_repeat('b',64)),'Suite token never returned to browser');
    verifySession($flow->current($session['session_id'])['identity']['user_id']===8,'current identity');
    verifySession($flow->requireWrite($session['session_id'],'POST',$session['csrf'])['role']==='manager','manager write allowed');
    foreach(['GET','HEAD','OPTIONS','post'] as $method)denySession(fn()=>$flow->requireWrite($session['session_id'],$method,$session['csrf']),'write method denied');
    denySession(fn()=>$flow->requireWrite($session['session_id'],'POST','invalid'),'CSRF mismatch denied');
    $identity['role']='viewer';
    verifySession($flow->current($session['session_id'])['identity']['role']==='viewer','fresh role downgrade');
    denySession(fn()=>$flow->requireWrite($session['session_id'],'POST',$session['csrf']),'viewer write denied');
    foreach(['user','contractor'] as $role){$identity['role']=$role;denySession(fn()=>$flow->requireWrite($session['session_id'],'POST',$session['csrf']),'unreviewed actions denied');}
    $identity['role']='manager';
    $n=count($calls);denySession(fn()=>$flow->accept($pending['pending_id'],$code,str_repeat('d',64)),'callback replay');verifySession(count($calls)===$n,'replay rejected before Suite');
    $pending=$flow->begin();$n=count($calls);denySession(fn()=>$flow->accept($pending['pending_id'],$code,str_repeat('d',64)),'state mismatch');verifySession(count($calls)===$n,'state mismatch before network');
    denySession(fn()=>$store->read($pending['pending_id']),'failed state consumed');
    $allowed=false;denySession(fn()=>$flow->current($session['session_id']),'revocation denied');$allowed=true;
    denySession(fn()=>$store->read($session['session_id']),'revoked local session removed');
    [$pending,$session]=$signin();$identity['user_id']=9;denySession(fn()=>$flow->current($session['session_id']),'token rebind user denied');$identity['user_id']=8;
    [$pending,$session]=$signin();$identity['session_expires_at']++;denySession(fn()=>$flow->current($session['session_id']),'absolute expiry extension denied');$identity['session_expires_at']--;
    [$pending,$session]=$signin();$now=$identity['session_expires_at'];denySession(fn()=>$flow->current($session['session_id']),'local expiry denied');$now=time();
    [$pending,$session]=$signin();$outage=true;denySession(fn()=>$flow->current($session['session_id']),'Suite outage denied');$outage=false;
    denySession(fn()=>$store->read($session['session_id']),'outage clears local auth');
    [$pending,$session]=$signin();denySession(fn()=>$flow->logout($session['session_id'],'GET',$session['csrf']),'logout GET denied');
    denySession(fn()=>$flow->logout($session['session_id'],'POST','wrong'),'logout CSRF denied');verifySession($store->read($session['session_id'])['kind']==='authenticated','invalid logout preserves session');
    verifySession($flow->logout($session['session_id'],'POST',$session['csrf'])===true,'remote logout');denySession(fn()=>$flow->current($session['session_id']),'logged out denied');
    [$pending,$session]=$signin();$outage=true;verifySession($flow->logout($session['session_id'],'POST',$session['csrf'])===false,'outage logout reports remote failure');$outage=false;
    denySession(fn()=>$store->read($session['session_id']),'outage logout still removes local access');
    $foreign=$binding;$foreign['origin']='https://beta.defectnotice.site';$foreign['instance_id']=4;$foreign['organization_id']=8;$foreign['project_id']=8;
    [$pending,$session]=$signin();$foreignStore=new SessionStore($tmp,$foreign);denySession(fn()=>$foreignStore->read($session['session_id']),'foreign store audience denied');
    $foreignStore->remove($session['session_id']);verifySession($store->read($session['session_id'])['user_id']===8,'foreign cleanup cannot remove another instance record');
    $reordered=array_reverse($binding,true);verifySession((new SessionStore($tmp,$reordered))->read($session['session_id'])['user_id']===8,'configuration key order preserves audience');
    $rotated=$binding;$rotated['key']=str_repeat('e',64);denySession(fn()=>(new SessionStore($tmp,$rotated))->read($session['session_id']),'key rotation denies old records');
    foreach(['','../secret',str_repeat('f',63),str_repeat('F',64)]as$id)denySession(fn()=>$store->read($id),'invalid identifier');
    $path=glob($tmp.'/*-'.hash('sha256',$session['session_id']).'.json')[0];verifySession((fileperms($path)&0777)===0600,'private file permissions');
    chmod($path,0644);clearstatcache();denySession(fn()=>$store->read($session['session_id']),'readable session file denied');chmod($path,0600);clearstatcache();
    file_put_contents($path,'{"invalid":true}');denySession(fn()=>$store->read($session['session_id']),'malformed record denied');
    $one=$store->create(['kind'=>'pending','state'=>str_repeat('f',64),'expires_at'=>time()+300]);$store->consume($one);denySession(fn()=>$store->consume($one),'one-use consume');
    denySession(fn()=>$store->create(['kind'=>'pending','state'=>str_repeat('f',64),'expires_at'=>time()+300,'token'=>str_repeat('b',64)]),'no tokens in pending records');
    CookiePolicy::requireOrigin(['HTTP_HOST'=>'alpha.defectnotice.site','HTTPS'=>'on'],$binding);verifySession(true,'exact HTTPS host accepted');
    foreach([['HTTP_HOST'=>'beta.defectnotice.site','HTTPS'=>'on'],['HTTP_HOST'=>'alpha.defectnotice.site','HTTPS'=>'off','HTTP_X_FORWARDED_PROTO'=>'https'],['HTTP_HOST'=>'alpha.defectnotice.site:443','HTTPS'=>'on']]as$server)denySession(fn()=>CookiePolicy::requireOrigin($server,$binding),'origin spoof denied');
    $options=CookiePolicy::options(time()+300);verifySession(!isset($options['domain'])&&$options['path']==='/'&&$options['secure']&&$options['httponly']&&$options['samesite']==='Lax','host-only secure callback cookie');
    verifySession(CookiePolicy::headers()['Referrer-Policy']==='no-referrer'&&CookiePolicy::headers()['Cache-Control']==='no-store','callback caching and referrer policy');
    verifySession(!isset($_SESSION),'legacy session never touched');
    echo json_encode(['passed'=>true,'checks'=>$checks,'routes_enabled'=>false,'live_requests'=>0])."\n";
}finally{foreach(glob($tmp.'/*')as$file)unlink($file);rmdir($tmp);}

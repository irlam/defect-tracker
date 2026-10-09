<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/Suite/Gateway.php';
require dirname(__DIR__) . '/includes/Suite/ProjectScope.php';
use DefectTracker\Suite\Gateway;
use DefectTracker\Suite\ProjectScope;

$checks = 0;
function check(bool $condition, string $label): void {
    global $checks;
    if (!$condition) throw new RuntimeException($label);
    $checks++;
}
function denied(callable $operation, string $label): void {
    try { $operation(); } catch (RuntimeException $e) { check(true, $label); return; }
    throw new RuntimeException('Expected denial: ' . $label);
}
$binding = ['staging_only'=>true, 'module_key'=>'defects', 'suite_origin'=>'https://suite.defecttracker.uk', 'origin'=>'https://alpha.defectnotice.site', 'instance_id'=>3, 'organization_id'=>7, 'project_id'=>7, 'local_project_id'=>1, 'key'=>str_repeat('a',64)];
$identity = ['instance_id'=>3, 'organization_id'=>7, 'project_id'=>7, 'module_key'=>'defects', 'user_id'=>8, 'role'=>'manager', 'name'=>'Fixture manager', 'email'=>'fixture@example.invalid', 'session_expires_at'=>time()+600, 'session_token'=>str_repeat('b',64)];
$reply=['ok'=>true,'identity'=>$identity];
$calls=[];
$transport=static function(string $url,array $payload,string $key) use (&$reply,&$calls): array {
    $calls[]=[$url,$payload,$key];
    return $reply;
};
$gateway=new Gateway($binding,$transport);
$begin=$gateway->begin();
check(strlen($begin['state'])===64 && str_contains($begin['url'],'instance_id=3&state='),'fresh browser state');
check($begin['state']!==$gateway->begin()['state'],'state is unique');
$code=str_repeat('c',64);
$current=$gateway->redeem($code,$begin['state'],$begin['state']);
check($current['local_project_id']===1 && $current['role']==='manager','identity preserves current Suite role');
check(!isset($current['local_role']),'no legacy admin role assignment');
check($calls[0][0]==='https://suite.defecttracker.uk/api/v1/module-handoff.php' && $calls[0][1]['instance_id']===3 && $calls[0][2]===$binding['key'],'fixed endpoint and instance key');
$n=count($calls);
denied(fn()=>$gateway->redeem($code,$begin['state'],str_repeat('d',64)),'browser state mismatch');
denied(fn()=>$gateway->validate('invalid'),'invalid token');
check(count($calls)===$n,'invalid browser/token rejected before network');
foreach (['instance_id','organization_id','project_id','user_id','session_expires_at'] as $field) {
    $reply=['ok'=>true,'identity'=>$identity]; $reply['identity'][$field]=(string)$identity[$field];
    denied(fn()=>$gateway->validate($identity['session_token']),'strict identity type '.$field);
}
foreach (['instance_id'=>4,'organization_id'=>8,'project_id'=>8,'module_key'=>'programme','user_id'=>0,'session_expires_at'=>time()-1,'role'=>'company_admin','email'=>null] as $field=>$value) {
    $reply=['ok'=>true,'identity'=>$identity]; $reply['identity'][$field]=$value;
    denied(fn()=>$gateway->validate($identity['session_token']),'foreign or invalid identity '.$field);
}
foreach (['staging_only'=>false,'module_key'=>'programme','instance_id'=>'3','key'=>'','suite_origin'=>'http://suite.defecttracker.uk','origin'=>'https://defectnotice.site','local_project_id'=>0] as $field=>$value) {
    $bad=$binding; $bad[$field]=$value;
    denied(fn()=>new Gateway($bad,$transport),'invalid deployment '.$field);
}
foreach (['https://alpha.defectnotice.site/path','https://user@alpha.defectnotice.site','https://alpha.defectnotice.site:443','https://alpha.defectnotice.site?x=1','https://alpha.defectnotice.site#x','https://alpha.defectnotice.site.evil.invalid'] as $origin) {
    $bad=$binding; $bad['origin']=$origin;
    denied(fn()=>new Gateway($bad,$transport),'unsafe origin');
}
$db=new PDO('sqlite::memory:'); $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE projects (id INTEGER PRIMARY KEY); INSERT INTO projects VALUES (1); CREATE TABLE suite_instance_binding (instance_id INTEGER, organization_id INTEGER, suite_project_id INTEGER, local_project_id INTEGER, module_key TEXT); INSERT INTO suite_instance_binding VALUES (3,7,7,1,\'defects\')');
$scope=new ProjectScope($gateway,$db,$binding);
$reply=['ok'=>true,'identity'=>$identity];
$current=$scope->current($identity['session_token']);
check($scope->requireProject($current,'1')===1,'bound project accepted');
foreach ([null,'',0,'0',2,'2','1 OR 1=1',true,1.0,'01'] as $project) denied(fn()=>$scope->requireProject($current,$project),'project override denied');
$reply['identity']['role']='viewer';
check($scope->current($identity['session_token'])['role']==='viewer','role downgrade is fresh');
$reply=['ok'=>false];
denied(fn()=>$scope->current($identity['session_token']),'revoked membership denied despite previous success');
$outage=new Gateway($binding,static function(){ throw new RuntimeException('private transport detail'); });
try {$outage->validate($identity['session_token']); throw new LogicException('Outage accepted');} catch(RuntimeException $e) {check($e->getMessage()==='Suite access could not be verified.','outage denies with no secret detail');}
$reply=['ok'=>true,'identity'=>$identity];
foreach (['organization_id'=>8,'suite_project_id'=>8,'local_project_id'=>2,'instance_id'=>4,'module_key'=>'programme'] as $column=>$value) {
    $stmt=$db->prepare("UPDATE suite_instance_binding SET $column=?"); $stmt->execute([$value]);
    denied(fn()=>$scope->current($identity['session_token']),'database mismatch '.$column);
    $stmt->execute([$column==='suite_project_id'?7:$binding[$column]]);
}
$db->exec('INSERT INTO projects VALUES (2)');
denied(fn()=>$scope->current($identity['session_token']),'additional local project denied');
$db->exec('DELETE FROM projects WHERE id=2; INSERT INTO suite_instance_binding SELECT * FROM suite_instance_binding');
denied(fn()=>$scope->current($identity['session_token']),'duplicate database binding denied');
$db->exec('DELETE FROM suite_instance_binding');
denied(fn()=>$scope->current($identity['session_token']),'missing database binding denied');
$reply=['ok'=>true];
$gateway->revoke($identity['session_token']);
$last=$calls[count($calls)-1];
check($last[1]['action']==='revoke' && $last[1]['instance_id']===3,'logout uses server session revocation');
echo json_encode(['passed'=>true,'checks'=>$checks,'tenant_ready'=>false,'routes_enabled'=>false],JSON_UNESCAPED_SLASHES)."\n";

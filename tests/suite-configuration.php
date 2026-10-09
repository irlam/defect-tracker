<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/Suite/Gateway.php';
require dirname(__DIR__) . '/includes/Suite/ProjectScope.php';
require dirname(__DIR__) . '/includes/Suite/Configuration.php';
use DefectTracker\Suite\Configuration;
use DefectTracker\Suite\Gateway;
use DefectTracker\Suite\ProjectScope;

$checks=0;
function verify(bool $value,string $label): void {global $checks; if(!$value)throw new RuntimeException($label); $checks++;}
function reject(callable $operation,string $label): void {
    ob_start();
    try {$operation();} catch(RuntimeException $e) {
        $output=ob_get_clean(); verify($output==='', $label.' output suppressed');
        verify(!str_contains($e->getMessage(),'fixture-password')&&!str_contains($e->getMessage(),'fixture_key'),'secret-free exception');
        return;
    } catch(Throwable $e) {ob_end_clean(); throw $e;}
    ob_end_clean(); throw new RuntimeException('Expected denial: '.$label);
}
$tmp=sys_get_temp_dir().'/defects-suite-config-'.bin2hex(random_bytes(8));
$root=$tmp.'/app';
foreach ([$tmp,$root,$root.'/config',$tmp.'/private',$tmp.'/private/uploads',$tmp.'/private/sessions'] as $dir)mkdir($dir,0700);
$file=$root.'/config/runtime.suite.private.php';
$config=['staging_only'=>true,'module_key'=>'defects','suite_origin'=>'https://suite.defecttracker.uk','origin'=>'https://alpha.defectnotice.site','instance_id'=>3,'organization_id'=>7,'project_id'=>7,'local_project_id'=>1,'key'=>str_repeat('a',64),'database'=>['host'=>'localhost','port'=>3306,'name'=>'fixture_alpha_stage','username'=>'fixture_alpha_stage','password'=>'fixture-password'],'upload_root'=>$tmp.'/private/uploads','session_root'=>$tmp.'/private/sessions'];
$write=static function(string $path,array $value,bool $guard=true): void {
    file_put_contents($path,"<?php\ndeclare(strict_types=1);\n".($guard?Configuration::guard()."\n":'').'return '.var_export($value,true).";\n"); chmod($path,0600); clearstatcache();
};
try {
    $write($file,$config);
    $childCode = 'require '.var_export(dirname(__DIR__).'/includes/Suite/Gateway.php',true).'; require '.var_export(dirname(__DIR__).'/includes/Suite/Configuration.php',true).';'
        . 'ini_set("open_basedir",'.var_export($tmp,true).');'
        . '$value=\\DefectTracker\\Suite\\Configuration::load('.var_export($root,true).');'
        . 'if($value["instance_id"]!==3)exit(1); echo "restricted-path-pass";';
    $process=proc_open([PHP_BINARY,'-r',$childCode],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
    $childOutput=stream_get_contents($pipes[1]);fclose($pipes[1]);
    $childError=stream_get_contents($pipes[2]);fclose($pipes[2]);
    verify(proc_close($process)===0&&$childOutput==='restricted-path-pass'&&$childError==='','configuration works within open_basedir');
    $copy=Configuration::load($root);
    verify($copy===$config,'protected in-tree configuration');
    $outside=$tmp.'/private/binding.php'; $write($outside,$config,false);
    verify(Configuration::load($root,$outside)===$config,'outside-root configuration');
    reject(fn()=>Configuration::load($root,$root.'/missing.php'),'missing config');
    chmod($file,0644); clearstatcache(); reject(fn()=>Configuration::load($root),'world-readable config');
    $write($file,$config,false); reject(fn()=>Configuration::load($root),'missing HTTP guard');
    $write($root.'/config/other.php',$config); reject(fn()=>Configuration::load($root,$root.'/config/other.php'),'arbitrary in-tree config');
    $write($file,$config); symlink($file,$tmp.'/config-link.php'); reject(fn()=>Configuration::load($root,$tmp.'/config-link.php'),'linked config');
    symlink($tmp.'/private',$tmp.'/private-link'); reject(fn()=>Configuration::load($root,$tmp.'/private-link/binding.php'),'linked config ancestor');
    file_put_contents($outside,"<?php echo 'fixture-password'; return [];"); chmod($outside,0600);
    reject(fn()=>Configuration::load($root,$outside),'config output');
    file_put_contents($outside,"<?php trigger_error('fixture-password'); return [];"); reject(fn()=>Configuration::load($root,$outside),'config warning');
    file_put_contents($outside,"<?php return 'fixture-password';"); reject(fn()=>Configuration::load($root,$outside),'non-array config');
    foreach (['port'=>'3306','name'=>'production','username'=>'production','host'=>'localhost;dbname=production','password'=>''] as $field=>$value) {
        $bad=$config;$bad['database'][$field]=$value;$write($file,$bad);
        reject(fn()=>Configuration::load($root),'database field '.$field);
    }
    foreach (['upload_root'=>$root.'/config','session_root'=>$tmp.'/missing','staging_only'=>false,'local_project_id'=>'1'] as $field=>$value) {
        $bad=$config;$bad[$field]=$value;$write($file,$bad);
        reject(fn()=>Configuration::load($root),'binding/storage field '.$field);
    }
    $bad=$config;$bad['session_root']=$bad['upload_root'];$write($file,$bad);reject(fn()=>Configuration::load($root),'overlapping stores');
    mkdir($tmp.'/private/uploads/nested',0700);$bad['session_root']=$tmp.'/private/uploads/nested';$write($file,$bad);reject(fn()=>Configuration::load($root),'nested stores');
    chmod($config['upload_root'],0755);clearstatcache();$write($file,$config);reject(fn()=>Configuration::load($root),'public-readable uploads');chmod($config['upload_root'],0700);clearstatcache();
    $bad=$config;$bad['upload_root']=$tmp.'/private-link/uploads';$write($file,$bad);reject(fn()=>Configuration::load($root),'linked upload ancestor');
    // A sibling whose name starts with the app root is outside it, not a prefix collision.
    mkdir($root.'-uploads',0700);$bad=$config;$bad['upload_root']=$root.'-uploads';$write($file,$bad);verify(Configuration::load($root)['upload_root']===$root.'-uploads','path-component boundary');
    $write($file,$config);
    $db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    $db->exec("CREATE TABLE projects(id INTEGER PRIMARY KEY); INSERT INTO projects VALUES(1); CREATE TABLE suite_instance_binding(instance_id INTEGER,organization_id INTEGER,suite_project_id INTEGER,local_project_id INTEGER,module_key TEXT); INSERT INTO suite_instance_binding VALUES(3,7,7,1,'defects')");
    $contacted=0;$gateway=new Gateway($config,static function()use(&$contacted){$contacted++;throw new RuntimeException();});
    $scope=new ProjectScope($gateway,$db,$config);$before=$db->query('SELECT total_changes()')->fetchColumn();
    $scope->assertDatabase();verify($contacted===0,'local preflight does not contact Suite');verify($db->query('SELECT total_changes()')->fetchColumn()===$before,'preflight does not write');
    $db->exec('UPDATE suite_instance_binding SET organization_id=8');reject(fn()=>$scope->assertDatabase(),'foreign database');
    verify(!isset($_SESSION) && getenv('DB_NAME')===false,'configuration does not set legacy identity/database');
    echo json_encode(['passed'=>true,'checks'=>$checks,'writes_to_live'=>0,'routes_enabled'=>false])."\n";
} finally {
    $items=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($items as $item) {if($item->isDir()&&!$item->isLink())rmdir($item->getPathname());else unlink($item->getPathname());}
    rmdir($tmp);
}

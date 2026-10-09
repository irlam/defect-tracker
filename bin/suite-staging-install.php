<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');
foreach(['Gateway','Configuration','ProjectScope']as$class)require dirname(__DIR__).'/includes/Suite/'.$class.'.php';
use DefectTracker\Suite\Configuration;
use DefectTracker\Suite\Gateway;
use DefectTracker\Suite\ProjectScope;
try{
 $mode=$argv[1]??'--dry-run';if($argc>2||!in_array($mode,['--dry-run','--apply'],true))throw new RuntimeException();
 $config=Configuration::load(dirname(__DIR__));$db=Configuration::connect($config);
 if($db->query('SHOW TABLES')->fetchAll())throw new RuntimeException();
 if($mode==='--dry-run'){echo '{"plan_valid":true,"database_empty":true,"writes_performed":0,"tenant_ready":false}'."\n";exit;}
 $schema=file_get_contents(dirname(__DIR__).'/staging/schema.mysql.sql');if(!$schema)throw new RuntimeException();
 foreach(explode(';',preg_replace('/^--.*$/m','',$schema))as$sql){if(trim($sql)!=='')$db->exec($sql);}
 $db->beginTransaction();
 $q=$db->prepare("INSERT INTO projects(id,name,status)VALUES(?,?,'active')");$q->execute([$config['local_project_id'],'Suite demo project']);
 $q=$db->prepare("INSERT INTO suite_instance_binding(singleton,instance_id,organization_id,suite_project_id,local_project_id,module_key)VALUES(1,?,?,?,?,'defects')");$q->execute([$config['instance_id'],$config['organization_id'],$config['project_id'],$config['local_project_id']]);$db->commit();
 (new ProjectScope(new Gateway($config),$db,$config))->assertDatabase();
 echo '{"installed":true,"projects":1,"users":0,"defects":0,"tenant_ready":false}'."\n";
}catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();echo '{"ok":false,"error":"empty_staging_database_and_private_configuration_required","tenant_ready":false}'."\n";exit(1);}

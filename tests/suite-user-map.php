<?php
declare(strict_types=1);
require dirname(__DIR__).'/includes/Suite/Gateway.php';require dirname(__DIR__).'/includes/Suite/UserMap.php';
use DefectTracker\Suite\UserMap;
$checks=0;function mappedCheck(bool $value,string $label):void{global$checks;if(!$value)throw new RuntimeException($label);$checks++;}
function mappedDeny(callable $fn,string $label):void{try{$fn();}catch(RuntimeException $e){mappedCheck(true,$label);return;}throw new RuntimeException('Expected denial: '.$label);}
$binding=['staging_only'=>true,'module_key'=>'defects','suite_origin'=>'https://suite.defecttracker.uk','origin'=>'https://alpha.defectnotice.site','instance_id'=>3,'organization_id'=>7,'project_id'=>7,'local_project_id'=>1,'key'=>str_repeat('a',64)];
$identity=['instance_id'=>3,'organization_id'=>7,'project_id'=>7,'local_project_id'=>1,'module_key'=>'defects','user_id'=>8,'role'=>'manager','name'=>'Manager fixture','email'=>'existing@example.invalid','session_expires_at'=>time()+600];
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->exec('PRAGMA foreign_keys=ON');
$db->exec('CREATE TABLE users(id INTEGER PRIMARY KEY AUTOINCREMENT,username TEXT UNIQUE,email TEXT UNIQUE,password TEXT,full_name TEXT,user_type TEXT,role TEXT,status TEXT,is_active INTEGER); CREATE TABLE suite_user_map(instance_id INTEGER,suite_user_id INTEGER,local_user_id INTEGER UNIQUE,PRIMARY KEY(instance_id,suite_user_id),FOREIGN KEY(local_user_id)REFERENCES users(id))');
$q=$db->prepare("INSERT INTO users(username,email,password,full_name,user_type,role,status,is_active)VALUES('existing',?,'fixture','Existing admin','admin','admin','active',1)");$q->execute([$identity['email']]);
$map=new UserMap($db,$binding);$first=$map->resolve($identity);mappedCheck($first['id']!==1,'email match does not adopt admin');mappedCheck($first['user_type']==='manager'&&$first['role']==='project_manager','local manager is not global admin');
$identity['email']='different@example.invalid';$identity['name']='Changed fixture';$again=$map->resolve($identity);mappedCheck($again['id']===$first['id'],'immutable Suite ID mapping');mappedCheck($again['full_name']==='Changed fixture','name refreshed');
$identity['role']='viewer';$viewer=$map->resolve($identity);mappedCheck($viewer['id']===$first['id']&&$viewer['user_type']==='viewer'&&$viewer['role']==='client','role downgrade refreshed');
foreach(['instance_id'=>4,'organization_id'=>8,'project_id'=>8,'local_project_id'=>2,'module_key'=>'programme','user_id'=>'8','session_expires_at'=>time()-1,'role'=>'owner']as$field=>$value){$bad=$identity;$bad[$field]=$value;mappedDeny(fn()=>$map->resolve($bad),'invalid identity '.$field);}
$identity['user_id']=9;$q=$db->prepare("INSERT INTO users(username,email,password,full_name,user_type,role,status,is_active)VALUES('suite_3_9','suite_3_9@example.invalid','fixture','Collision','admin','admin','active',1)");$q->execute();$count=(int)$db->query('SELECT COUNT(*)FROM users')->fetchColumn();mappedDeny(fn()=>$map->resolve($identity),'synthetic username collision fails without adoption');mappedCheck((int)$db->query('SELECT COUNT(*)FROM users')->fetchColumn()===$count,'collision rolls back');
$db->exec("CREATE TRIGGER fail_mapping BEFORE INSERT ON suite_user_map BEGIN SELECT RAISE(ABORT,'fixture failure'); END");$identity['user_id']=10;mappedDeny(fn()=>$map->resolve($identity),'mapping insert failure');mappedCheck((int)$db->query('SELECT COUNT(*)FROM users')->fetchColumn()===$count,'user insert rollback');$db->exec('DROP TRIGGER fail_mapping');
$db->beginTransaction();mappedDeny(fn()=>$map->resolve($identity),'caller transaction denied');$db->rollBack();
$db->exec('PRAGMA foreign_keys=OFF; INSERT INTO suite_user_map VALUES(3,10,999)');mappedDeny(fn()=>$map->resolve($identity),'dangling mapping fails closed');
echo json_encode(['passed'=>true,'checks'=>$checks,'live_writes'=>0])."\n";

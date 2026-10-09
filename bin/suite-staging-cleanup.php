<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');
foreach(['Gateway','Configuration','SessionStore']as$class)require dirname(__DIR__).'/includes/Suite/'.$class.'.php';
use DefectTracker\Suite\Configuration;
use DefectTracker\Suite\SessionStore;
try{
 $mode=$argv[1]??'--dry-run';if($argc>2||!in_array($mode,['--dry-run','--apply'],true))throw new RuntimeException();
 $config=Configuration::load(dirname(__DIR__));$result=(new SessionStore($config['session_root'],$config))->pruneExpired($mode==='--apply');
 echo json_encode(['ok'=>true,'mode'=>$mode]+$result+['suite_contacted'=>false,'tenant_ready'=>false])."\n";
}catch(Throwable $e){echo '{"ok":false,"error":"private_staging_configuration_unavailable"}'."\n";exit(1);}

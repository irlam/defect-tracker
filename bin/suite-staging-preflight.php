<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '0');
require dirname(__DIR__) . '/includes/Suite/Gateway.php';
require dirname(__DIR__) . '/includes/Suite/ProjectScope.php';
require dirname(__DIR__) . '/includes/Suite/Configuration.php';

use DefectTracker\Suite\Configuration;
use DefectTracker\Suite\Gateway;
use DefectTracker\Suite\ProjectScope;

try {
    if ($argc !== 1) throw new RuntimeException('No command arguments accepted.');
    $config = Configuration::load(dirname(__DIR__));
    $db = Configuration::connect($config);
    (new ProjectScope(new Gateway($config), $db, $config))->assertDatabase();
    echo json_encode(['configuration_verified'=>true, 'database_binding_verified'=>true, 'private_storage_verified'=>true, 'writes_performed'=>0, 'suite_contacted'=>false, 'tenant_ready'=>false, 'routes_enabled'=>false]) . "\n";
} catch (Throwable $e) {
    // Do not print exception messages, credentials, database names or private paths.
    echo json_encode(['preflight_passed'=>false, 'error'=>'staging_configuration_or_binding_unavailable', 'writes_performed'=>0, 'suite_contacted'=>false, 'tenant_ready'=>false]) . "\n";
    exit(1);
}

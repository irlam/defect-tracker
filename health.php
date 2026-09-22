<?php
declare(strict_types=1);

header('Content-Type: application/json');
header('Cache-Control: no-store');

require_once __DIR__ . '/config/database.php';

$response = [
    'app' => 'Defect Tracker',
    'version' => '3.0.0-rc1',
    'status' => 'error',
    'database' => 'unavailable',
];

try {
    $db = (new Database())->getConnection();
    if (!$db) {
        throw new RuntimeException('Database unavailable');
    }

    $value = $db->query('SELECT 1')->fetchColumn();
    if ((int) $value !== 1) {
        throw new RuntimeException('Database health check failed');
    }

    $response['status'] = 'ok';
    $response['database'] = 'ok';
    http_response_code(200);
} catch (Throwable $e) {
    http_response_code(503);
}

echo json_encode($response, JSON_UNESCAPED_SLASHES);

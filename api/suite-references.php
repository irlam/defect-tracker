<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__) . '/config/env.php';
require_once dirname(__DIR__) . '/config/database.php';

$expected = trim((string) Environment::get('CONSTRUCTION_SUITE_API_KEY', ''));
$provided = trim((string) ($_SERVER['HTTP_X_CONSTRUCTION_SUITE_KEY'] ?? ''));

if ($expected === '') {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'suite_integration_not_configured']);
    exit;
}
if ($provided === '' || !hash_equals($expected, $provided)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
}

try {
    $db = (new Database())->getConnection();
    if (!$db) {
        throw new RuntimeException('Database unavailable.');
    }

    $stmt = $db->query(
        "SELECT id, name
         FROM projects
         WHERE name IS NOT NULL AND TRIM(name) <> ''
         ORDER BY name ASC, id ASC"
    );
    $items = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $items[] = [
            'value' => (string) ((int) $row['id']),
            'label' => (string) $row['name'],
        ];
    }

    echo json_encode([
        'ok' => true,
        'module' => 'defects',
        'reference_type' => 'project',
        'items' => $items,
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('Construction Suite project reference lookup failed: ' . $e->getMessage());
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'references_unavailable']);
}

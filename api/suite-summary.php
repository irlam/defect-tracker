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

$projectId = null;
if (isset($_GET['project']) && $_GET['project'] !== '') {
    if (!ctype_digit((string) $_GET['project']) || (int) $_GET['project'] < 1) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'invalid_project']);
        exit;
    }
    $projectId = (int) $_GET['project'];
}

try {
    $db = (new Database())->getConnection();
    if (!$db) {
        throw new RuntimeException('Database unavailable.');
    }

    $where = ['d.deleted_at IS NULL'];
    $params = [];
    if ($projectId !== null) {
        $where[] = 'd.project_id = :project_id';
        $params[':project_id'] = $projectId;
    }

    $sql = "SELECT
                COUNT(*) AS total,
                COALESCE(SUM(CASE WHEN d.status IN ('open','pending','in_progress') THEN 1 ELSE 0 END),0) AS active,
                COALESCE(SUM(CASE WHEN d.priority = 'critical' THEN 1 ELSE 0 END),0) AS critical,
                COALESCE(SUM(CASE WHEN d.due_date IS NOT NULL AND d.due_date < CURRENT_DATE() AND d.status IN ('open','in_progress','rejected') THEN 1 ELSE 0 END),0) AS overdue,
                COALESCE(SUM(CASE WHEN d.status = 'rejected' THEN 1 ELSE 0 END),0) AS rejected,
                MAX(d.updated_at) AS last_updated
            FROM defects d
            WHERE " . implode(' AND ', $where);

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    echo json_encode([
        'ok' => true,
        'module' => 'defects',
        'scope' => ['project' => $projectId],
        'metrics' => [
            'total' => (int) ($row['total'] ?? 0),
            'active' => (int) ($row['active'] ?? 0),
            'critical' => (int) ($row['critical'] ?? 0),
            'overdue' => (int) ($row['overdue'] ?? 0),
            'rejected' => (int) ($row['rejected'] ?? 0),
        ],
        'last_updated' => $row['last_updated'] ?: null,
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('Construction Suite summary failed: ' . $e->getMessage());
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'summary_unavailable']);
}

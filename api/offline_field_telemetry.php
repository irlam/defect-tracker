<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['user_id']) || empty($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

$csrf = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (empty($_SESSION['csrf_token']) || !hash_equals((string) $_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid security token.']);
    exit;
}

$payload = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid telemetry payload.']);
    exit;
}

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/FieldSyncTelemetry.php';

try {
    $db = (new Database())->getConnection();
    if (!$db) {
        throw new RuntimeException('Database unavailable.');
    }
    $identity = [
        'device_id' => $payload['deviceId'] ?? null,
        'user_id' => (int) $_SESSION['user_id'],
        'username' => (string) $_SESSION['username'],
    ];
    FieldSyncTelemetry::updateDevice($db, array_merge($identity, [
        'pending_count' => $payload['pendingCount'] ?? 0,
        'failed_count' => $payload['failedCount'] ?? 0,
        'syncing_count' => $payload['syncingCount'] ?? 0,
        'oldest_pending_at' => $payload['oldestPendingAt'] ?? null,
        'last_status' => $payload['status'] ?? 'online',
        'last_error' => $payload['lastError'] ?? null,
    ]));

    if (!empty($payload['event']) && is_array($payload['event'])) {
        FieldSyncTelemetry::recordEvent($db, array_merge($identity, [
            'event_id' => $payload['event']['id'] ?? null,
            'client_submission_id' => $payload['event']['submissionId'] ?? null,
            'event_type' => $payload['event']['type'] ?? 'heartbeat',
            'status' => $payload['event']['status'] ?? 'info',
            'defect_id' => $payload['event']['defectId'] ?? null,
            'message' => $payload['event']['message'] ?? null,
            'details' => $payload['event']['details'] ?? null,
        ]));
    }

    echo json_encode(['success' => true, 'recordedAt' => gmdate('c')]);
} catch (Throwable $error) {
    error_log('Offline field telemetry failed: ' . $error->getMessage());
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Telemetry is temporarily unavailable.']);
}


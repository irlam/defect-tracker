<?php
declare(strict_types=1);

final class FieldSyncTelemetry
{
    private static bool $schemaReady = false;

    public static function ensureSchema(PDO $db): void
    {
        if (self::$schemaReady) {
            return;
        }

        $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS field_sync_devices (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    device_id VARCHAR(100) NOT NULL,
    user_id INT NOT NULL,
    username VARCHAR(100) NOT NULL,
    pending_count INT UNSIGNED NOT NULL DEFAULT 0,
    failed_count INT UNSIGNED NOT NULL DEFAULT 0,
    syncing_count INT UNSIGNED NOT NULL DEFAULT 0,
    oldest_pending_at DATETIME NULL,
    last_seen DATETIME NOT NULL,
    last_sync_at DATETIME NULL,
    last_status VARCHAR(30) NOT NULL DEFAULT 'online',
    last_error TEXT NULL,
    user_agent TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_field_sync_device_user (device_id, user_id),
    KEY idx_field_sync_devices_seen (last_seen),
    KEY idx_field_sync_devices_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS field_sync_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_id VARCHAR(100) NOT NULL,
    client_submission_id VARCHAR(100) NULL,
    device_id VARCHAR(100) NOT NULL,
    user_id INT NOT NULL,
    username VARCHAR(100) NOT NULL,
    event_type VARCHAR(40) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'info',
    defect_id INT NULL,
    message VARCHAR(500) NULL,
    details LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_field_sync_event (event_id),
    KEY idx_field_sync_events_created (created_at),
    KEY idx_field_sync_events_device (device_id, user_id),
    KEY idx_field_sync_events_submission (client_submission_id),
    KEY idx_field_sync_events_type (event_type, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        self::$schemaReady = true;
    }

    public static function normaliseDeviceId(?string $deviceId): string
    {
        $deviceId = trim((string) $deviceId);
        if ($deviceId === '' || !preg_match('/^[A-Za-z0-9._-]{10,100}$/', $deviceId)) {
            return 'unknown-' . substr(hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . ($_SERVER['REMOTE_ADDR'] ?? '')), 0, 32);
        }
        return $deviceId;
    }

    public static function updateDevice(PDO $db, array $data): void
    {
        self::ensureSchema($db);
        $statement = $db->prepare(<<<'SQL'
INSERT INTO field_sync_devices (
    device_id, user_id, username, pending_count, failed_count, syncing_count,
    oldest_pending_at, last_seen, last_sync_at, last_status, last_error, user_agent, ip_address
) VALUES (
    :device_id, :user_id, :username, :pending_count, :failed_count, :syncing_count,
    :oldest_pending_at, NOW(), :last_sync_at, :last_status, :last_error, :user_agent, :ip_address
)
ON DUPLICATE KEY UPDATE
    username = VALUES(username),
    pending_count = VALUES(pending_count),
    failed_count = VALUES(failed_count),
    syncing_count = VALUES(syncing_count),
    oldest_pending_at = VALUES(oldest_pending_at),
    last_seen = NOW(),
    last_sync_at = COALESCE(VALUES(last_sync_at), last_sync_at),
    last_status = VALUES(last_status),
    last_error = VALUES(last_error),
    user_agent = VALUES(user_agent),
    ip_address = VALUES(ip_address)
SQL);
        $statement->execute([
            ':device_id' => self::normaliseDeviceId($data['device_id'] ?? null),
            ':user_id' => (int) ($data['user_id'] ?? 0),
            ':username' => substr((string) ($data['username'] ?? 'unknown'), 0, 100),
            ':pending_count' => max(0, min(10000, (int) ($data['pending_count'] ?? 0))),
            ':failed_count' => max(0, min(10000, (int) ($data['failed_count'] ?? 0))),
            ':syncing_count' => max(0, min(10000, (int) ($data['syncing_count'] ?? 0))),
            ':oldest_pending_at' => self::validDateTime($data['oldest_pending_at'] ?? null),
            ':last_sync_at' => self::validDateTime($data['last_sync_at'] ?? null),
            ':last_status' => self::validStatus($data['last_status'] ?? 'online'),
            ':last_error' => self::limitedText($data['last_error'] ?? null, 2000),
            ':user_agent' => self::limitedText($_SERVER['HTTP_USER_AGENT'] ?? null, 2000),
            ':ip_address' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
        ]);
    }

    public static function recordEvent(PDO $db, array $data): void
    {
        self::ensureSchema($db);
        $eventId = trim((string) ($data['event_id'] ?? ''));
        if ($eventId === '') {
            $eventId = 'server-' . bin2hex(random_bytes(16));
        }
        $statement = $db->prepare(<<<'SQL'
INSERT IGNORE INTO field_sync_events (
    event_id, client_submission_id, device_id, user_id, username,
    event_type, status, defect_id, message, details, created_at
) VALUES (
    :event_id, :client_submission_id, :device_id, :user_id, :username,
    :event_type, :status, :defect_id, :message, :details, NOW()
)
SQL);
        $details = $data['details'] ?? null;
        $statement->execute([
            ':event_id' => substr($eventId, 0, 100),
            ':client_submission_id' => self::limitedText($data['client_submission_id'] ?? null, 100),
            ':device_id' => self::normaliseDeviceId($data['device_id'] ?? null),
            ':user_id' => (int) ($data['user_id'] ?? 0),
            ':username' => substr((string) ($data['username'] ?? 'unknown'), 0, 100),
            ':event_type' => substr((string) ($data['event_type'] ?? 'heartbeat'), 0, 40),
            ':status' => in_array(($data['status'] ?? ''), ['info', 'success', 'failed'], true) ? $data['status'] : 'info',
            ':defect_id' => empty($data['defect_id']) ? null : (int) $data['defect_id'],
            ':message' => self::limitedText($data['message'] ?? null, 500),
            ':details' => $details === null ? null : json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
    }

    public static function markServerCompletion(PDO $db, array $data): void
    {
        $deviceId = self::normaliseDeviceId($data['device_id'] ?? null);
        $submissionId = (string) ($data['client_submission_id'] ?? bin2hex(random_bytes(8)));
        self::recordEvent($db, [
            'event_id' => (!empty($data['duplicate']) ? 'duplicate-' : 'completed-') . hash('sha256', $submissionId),
            'client_submission_id' => $submissionId,
            'device_id' => $deviceId,
            'user_id' => $data['user_id'] ?? 0,
            'username' => $data['username'] ?? 'unknown',
            'event_type' => !empty($data['duplicate']) ? 'duplicate_confirmed' : 'upload_completed',
            'status' => 'success',
            'defect_id' => $data['defect_id'] ?? null,
            'message' => !empty($data['duplicate']) ? 'Duplicate retry matched the existing defect.' : 'Offline field report uploaded successfully.',
        ]);
        self::ensureSchema($db);
        $statement = $db->prepare(<<<'SQL'
INSERT INTO field_sync_devices (
    device_id, user_id, username, pending_count, failed_count, syncing_count,
    last_seen, last_sync_at, last_status, user_agent, ip_address
) VALUES (:device_id, :user_id, :username, 0, 0, 0, NOW(), NOW(), 'online', :user_agent, :ip_address)
ON DUPLICATE KEY UPDATE
    username = VALUES(username),
    last_seen = NOW(),
    last_sync_at = NOW(),
    last_status = 'online',
    user_agent = VALUES(user_agent),
    ip_address = VALUES(ip_address)
SQL);
        $statement->execute([
            ':device_id' => $deviceId,
            ':user_id' => (int) ($data['user_id'] ?? 0),
            ':username' => substr((string) ($data['username'] ?? 'unknown'), 0, 100),
            ':user_agent' => self::limitedText($_SERVER['HTTP_USER_AGENT'] ?? null, 2000),
            ':ip_address' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
        ]);
    }

    private static function validStatus(mixed $status): string
    {
        $status = (string) $status;
        return in_array($status, ['online', 'offline', 'syncing', 'error'], true) ? $status : 'online';
    }

    private static function validDateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $timestamp = strtotime((string) $value);
        return $timestamp === false ? null : date('Y-m-d H:i:s', $timestamp);
    }

    private static function limitedText(mixed $value, int $limit): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        return function_exists('mb_substr')
            ? mb_substr((string) $value, 0, $limit)
            : substr((string) $value, 0, $limit);
    }
}

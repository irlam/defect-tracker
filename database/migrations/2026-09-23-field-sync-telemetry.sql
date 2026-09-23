-- Optional manual migration for hosts that do not allow CREATE TABLE at runtime.
-- The application also creates these tables automatically on first use.

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

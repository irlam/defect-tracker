-- Create sync_queue table to track items pending synchronization
CREATE TABLE IF NOT EXISTS sync_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    action ENUM('create', 'update', 'delete') NOT NULL,
    entity_type ENUM('defect', 'defect_comment', 'defect_image') NOT NULL,
    entity_id INT NOT NULL,
    server_id INT NULL,
    data LONGTEXT NULL,
    base_timestamp DATETIME NULL,
    status ENUM('pending', 'processing', 'completed', 'failed', 'conflict', 'awaiting_user_input') NOT NULL DEFAULT 'pending',
    attempts INT NOT NULL DEFAULT 0,
    force_sync TINYINT(1) NOT NULL DEFAULT 0,   -- Changed from 'force BOOLEAN' to 'force_sync TINYINT(1)'
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    processed_at DATETIME NULL,
    username VARCHAR(50) NOT NULL,
    device_id VARCHAR(100) NULL,
    result LONGTEXT NULL,
    INDEX(status),
    INDEX(entity_type, entity_id),
    INDEX(created_at)
) ENGINE=InnoDB;

-- Create sync_logs table to track sync history
CREATE TABLE IF NOT EXISTS sync_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    device_id VARCHAR(100) NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    items_processed INT NOT NULL DEFAULT 0,
    items_succeeded INT NOT NULL DEFAULT 0,
    items_failed INT NOT NULL DEFAULT 0,
    items_conflicted INT NOT NULL DEFAULT 0,
    sync_direction ENUM('upload', 'download', 'bidirectional') NOT NULL DEFAULT 'bidirectional',
    status ENUM('success', 'partial', 'failed') NOT NULL,
    message TEXT NULL,
    details LONGTEXT NULL,
    INDEX(username),
    INDEX(start_time)
) ENGINE=InnoDB;

-- Create sync_conflicts table to store detailed conflict information
CREATE TABLE IF NOT EXISTS sync_conflicts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sync_queue_id INT NOT NULL,
    entity_type ENUM('defect', 'defect_comment', 'defect_image') NOT NULL,
    entity_id INT NOT NULL,
    server_data LONGTEXT NOT NULL,
    client_data LONGTEXT NOT NULL,
    resolved TINYINT(1) NOT NULL DEFAULT 0,   -- Changed from BOOLEAN to TINYINT(1)
    resolution_type ENUM('server_wins', 'client_wins', 'merge', 'manual') NULL,
    resolved_by VARCHAR(50) NULL,
    resolved_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX(entity_type, entity_id),
    INDEX(resolved),
    FOREIGN KEY (sync_queue_id) REFERENCES sync_queue(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Create sync_devices table to track user devices
CREATE TABLE IF NOT EXISTS sync_devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL,
    device_name VARCHAR(100) NULL,
    device_type VARCHAR(50) NULL,
    last_sync DATETIME NULL,
    last_ip VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY(device_id),
    INDEX(username)
) ENGINE=InnoDB;
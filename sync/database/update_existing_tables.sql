-- Check if sync columns exist in defects table, add them only if they don't
SET @defects_has_sync_status = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                                WHERE TABLE_NAME = 'defects' 
                                AND COLUMN_NAME = 'sync_status'
                                AND TABLE_SCHEMA = DATABASE());

SET @sql_defects = IF(@defects_has_sync_status = 0, 
    'ALTER TABLE defects
     ADD COLUMN sync_status ENUM("synced", "pending", "conflict") NOT NULL DEFAULT "synced" AFTER updated_at,
     ADD COLUMN client_id VARCHAR(100) NULL AFTER sync_status,
     ADD COLUMN sync_timestamp DATETIME NULL AFTER client_id,
     ADD COLUMN device_id VARCHAR(100) NULL AFTER sync_timestamp,
     ADD INDEX(sync_status),
     ADD INDEX(client_id)',
    'SELECT "Sync columns already exist in defects table" AS message');

PREPARE stmt_defects FROM @sql_defects;
EXECUTE stmt_defects;
DEALLOCATE PREPARE stmt_defects;

-- Check if sync columns exist in defect_images table, add them only if they don't
SET @defect_images_has_sync_status = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                                    WHERE TABLE_NAME = 'defect_images' 
                                    AND COLUMN_NAME = 'sync_status'
                                    AND TABLE_SCHEMA = DATABASE());

SET @sql_defect_images = IF(@defect_images_has_sync_status = 0, 
    'ALTER TABLE defect_images
     ADD COLUMN sync_status ENUM("synced", "pending", "conflict") NOT NULL DEFAULT "synced" AFTER uploaded_at,
     ADD COLUMN client_id VARCHAR(100) NULL AFTER sync_status,
     ADD COLUMN sync_timestamp DATETIME NULL AFTER client_id,
     ADD COLUMN device_id VARCHAR(100) NULL AFTER sync_timestamp,
     ADD INDEX(sync_status),
     ADD INDEX(client_id)',
    'SELECT "Sync columns already exist in defect_images table" AS message');

PREPARE stmt_defect_images FROM @sql_defect_images;
EXECUTE stmt_defect_images;
DEALLOCATE PREPARE stmt_defect_images;

-- Check if sync columns exist in defect_comments table, add them only if they don't
SET @defect_comments_has_sync_status = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                                      WHERE TABLE_NAME = 'defect_comments' 
                                      AND COLUMN_NAME = 'sync_status'
                                      AND TABLE_SCHEMA = DATABASE());

SET @sql_defect_comments = IF(@defect_comments_has_sync_status = 0, 
    'ALTER TABLE defect_comments
     ADD COLUMN sync_status ENUM("synced", "pending", "conflict") NOT NULL DEFAULT "synced" AFTER updated_at,
     ADD COLUMN client_id VARCHAR(100) NULL AFTER sync_status,
     ADD COLUMN sync_timestamp DATETIME NULL AFTER client_id,
     ADD COLUMN device_id VARCHAR(100) NULL AFTER sync_timestamp,
     ADD INDEX(sync_status),
     ADD INDEX(client_id)',
    'SELECT "Sync columns already exist in defect_comments table" AS message');

PREPARE stmt_defect_comments FROM @sql_defect_comments;
EXECUTE stmt_defect_comments;
DEALLOCATE PREPARE stmt_defect_comments;
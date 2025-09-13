-- Create trigger for defects table
DELIMITER //
CREATE TRIGGER defects_before_update BEFORE UPDATE ON defects
FOR EACH ROW
BEGIN
    -- Only mark as pending if this is a direct update, not from the sync system
    IF NEW.sync_status = 'synced' AND OLD.updated_at != NEW.updated_at THEN
        SET NEW.sync_status = 'pending';
        SET NEW.sync_timestamp = NOW();
    END IF;
END//

-- Create trigger for defect_images table
CREATE TRIGGER defect_images_before_update BEFORE UPDATE ON defect_images
FOR EACH ROW
BEGIN
    -- Only mark as pending if this is a direct update, not from the sync system
    IF NEW.sync_status = 'synced' AND OLD.uploaded_at != NEW.uploaded_at THEN
        SET NEW.sync_status = 'pending';
        SET NEW.sync_timestamp = NOW();
    END IF;
END//

-- Create trigger for defect_comments table
CREATE TRIGGER defect_comments_before_update BEFORE UPDATE ON defect_comments
FOR EACH ROW
BEGIN
    -- Only mark as pending if this is a direct update, not from the sync system
    IF NEW.sync_status = 'synced' AND OLD.updated_at != NEW.updated_at THEN
        SET NEW.sync_status = 'pending';
        SET NEW.sync_timestamp = NOW();
    END IF;
END//

DELIMITER ;
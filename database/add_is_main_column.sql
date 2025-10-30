-- Add missing is_main column to defect_images table
-- This column indicates which image is the main/primary image for a defect

ALTER TABLE `defect_images` ADD COLUMN `is_main` TINYINT(1) DEFAULT 0 COMMENT 'Indicates if this is the main/primary image for the defect' AFTER `is_edited`;

-- Update existing records to set the first image for each defect as main (if no main image exists)
-- This ensures backward compatibility
UPDATE defect_images di1
INNER JOIN (
    SELECT defect_id, MIN(id) as first_image_id
    FROM defect_images
    GROUP BY defect_id
) di2 ON di1.defect_id = di2.defect_id AND di1.id = di2.first_image_id
SET di1.is_main = 1
WHERE di1.is_main = 0;
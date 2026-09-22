# Push Notification System Enhancements - Database Migration

## Overview
This migration enhances the push notification system to support:
- Contractor targeting
- Delivery confirmation tracking
- Platform-specific support (PWA, iOS, Android)
- Detailed error logging
- Individual recipient tracking

## Running the Migration

### Option 1: Using the Migration Runner (Recommended)
```bash
cd database/migrations
php run_migration.php
```

### Option 2: Manual SQL Execution
1. Connect to your MySQL/MariaDB database
2. Run the SQL file:
```bash
mysql -u [username] -p [database_name] < add_notification_enhancements.sql
```

Or via phpMyAdmin/SQL client:
- Open `add_notification_enhancements.sql`
- Execute all statements

## Changes Made

### 1. notification_log Table Extensions
- `contractor_id` (INT) - Links notification to a specific contractor
- `platform` (VARCHAR) - Device platform (pwa, ios, android, web)
- `failed_count` (INT) - Count of failed deliveries
- `delivery_status` (ENUM) - Overall delivery status: pending, sent, delivered, failed
- `error_message` (TEXT) - Error details for failed notifications
- `delivery_confirmed_at` (DATETIME) - When delivery was confirmed

### 2. users Table Extension
- `device_platform` (VARCHAR) - User's device platform type

### 3. New notification_recipients Table
Tracks individual notification deliveries to each recipient:
- `id` - Primary key
- `notification_log_id` - Links to notification_log
- `user_id` - Recipient user ID
- `contractor_id` - Recipient contractor ID
- `fcm_token` - FCM token used
- `platform` - Device platform
- `delivery_status` - Delivery status for this recipient
- `sent_at` - When notification was sent
- `delivered_at` - When delivery was confirmed
- `failed_at` - When delivery failed
- `error_message` - Error details
- `fcm_response` - FCM API response

## Rollback
If you need to rollback these changes, run:

```sql
-- Remove new table
DROP TABLE IF EXISTS `notification_recipients`;

-- Remove added columns from notification_log
ALTER TABLE `notification_log` 
  DROP COLUMN IF EXISTS `contractor_id`,
  DROP COLUMN IF EXISTS `platform`,
  DROP COLUMN IF EXISTS `failed_count`,
  DROP COLUMN IF EXISTS `delivery_status`,
  DROP COLUMN IF EXISTS `error_message`,
  DROP COLUMN IF EXISTS `delivery_confirmed_at`;

-- Remove added column from users
ALTER TABLE `users` 
  DROP COLUMN IF EXISTS `device_platform`;
```

## Testing After Migration
1. Verify tables exist:
```sql
SHOW TABLES LIKE 'notification_%';
DESCRIBE notification_log;
DESCRIBE notification_recipients;
```

2. Test sending a notification through the UI at `/push_notifications/`
3. Check notification history at `/push_notifications/notification_history.php`
4. Verify delivery confirmation API at `/api/confirm_notification_delivery.php`

## Notes
- The migration is designed to be safe to run multiple times (uses IF NOT EXISTS and checks)
- Existing data in notification_log will remain intact
- Default values are provided for all new columns
- Foreign key constraints ensure data integrity

<?php
/**
 * McGoff Backup Manager - Configuration File
 * 
 * This file contains all configuration settings for the backup system including
 * database credentials, file paths, backup settings, and authentication information.
 * 
 * Created: 2025-02-26 19:27:55
 * Author: irlam
 */

// Authentication
define('USERNAME', 'irlam');
define('PASSWORD', 'Subaru5554346'); // Change this to a secure password

// Backup Settings
define('BACKUP_DIR', __DIR__ . '/backups');
define('MAX_BACKUPS', 10); // Maximum number of backups to keep
define('BACKUP_NAME_PREFIX', 'mcgoff-backup');

// Website Files to Backup
define('WEBSITE_ROOT', '/var/www/vhosts/hosting215226.ae97b.netcup.net/mcgoff.defecttracker.uk/httpdocs');
define('EXCLUDE_PATHS', serialize(array(
    __DIR__, // Exclude the backups system itself
    WEBSITE_ROOT . '/tmp',
    WEBSITE_ROOT . '/cache',
    WEBSITE_ROOT . '/logs'
)));

// Database Settings
define('DB_HOST', '10.35.233.124:3306');  // Your database host
define('DB_USER', 'k87747_defecttracker'); // Change to your actual database username
define('DB_PASS', '7Mr@ww816'); // Change to your actual database password
define('DB_NAME', 'k87747_defecttracker'); // Change to your actual database name

// MySQL Dump Path (from diagnostics)
define('MYSQLDUMP_PATH', '/usr/bin/mysqldump');

// Time zone settings
date_default_timezone_set('UTC'); // Server is in UTC

// Current date/time and user
define('CURRENT_DATETIME', '2025-02-26 19:04:30');
define('CURRENT_USER', 'irlam');

// Create backup directory if it doesn't exist
if (!file_exists(BACKUP_DIR)) {
    mkdir(BACKUP_DIR, 0755, true);
}
?>
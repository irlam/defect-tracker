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

require_once __DIR__ . '/../config/env.php';

// Legacy standalone authentication (the current backup UI uses the main session).
define('USERNAME', Environment::get('BACKUP_USERNAME', ''));
define('PASSWORD', Environment::get('BACKUP_PASSWORD', ''));

// Backup Settings
define('BACKUP_DIR', __DIR__ . '/backups');
define('MAX_BACKUPS', 10); // Maximum number of backups to keep
define('BACKUP_NAME_PREFIX', 'mcgoff-backup');

// Website Files to Backup
define('WEBSITE_ROOT', realpath(dirname(__DIR__)) ?: dirname(__DIR__));
define('EXCLUDE_PATHS', serialize(array(
    __DIR__, // Exclude the backups system itself
    WEBSITE_ROOT . '/tmp',
    WEBSITE_ROOT . '/cache',
    WEBSITE_ROOT . '/logs',
    WEBSITE_ROOT . '/.git',
    WEBSITE_ROOT . '/.github'
)));

// Database Settings
define('DB_HOST', Environment::get('DB_HOST', 'localhost'));
define('DB_USER', Environment::get('DB_USERNAME', ''));
define('DB_PASS', Environment::get('DB_PASSWORD', ''));
define('DB_NAME', Environment::get('DB_NAME', ''));

// MySQL Dump Path (from diagnostics)
define('MYSQLDUMP_PATH', '/usr/bin/mysqldump');

// Time zone settings
date_default_timezone_set('UTC'); // Server is in UTC

// Current date/time and user
define('CURRENT_DATETIME', gmdate('Y-m-d H:i:s'));
define('CURRENT_USER', isset($_SESSION['username']) && $_SESSION['username'] !== '' ? (string) $_SESSION['username'] : 'system');

// Create backup directory if it doesn't exist
if (!file_exists(BACKUP_DIR)) {
    mkdir(BACKUP_DIR, 0755, true);
}
?>

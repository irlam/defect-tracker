<?php
/**
 * config/constants.php
 * Application constants using environment configuration
 * Current Date and Time (UTC): <?php echo gmdate('Y-m-d H:i:s'); ?>
 */

require_once __DIR__ . '/env.php';

// Site URL from environment or fallback
if (!defined('SITE_URL')) {
    define('SITE_URL', Environment::get('SITE_URL', Environment::get('BASE_URL', 'http://localhost')));
}

// Upload directory constants
if (!defined('UPLOAD_BASE_DIR')) {
    define('UPLOAD_BASE_DIR', dirname(__DIR__) . '/uploads/defects');
}

if (!defined('MAX_FILE_SIZE')) {
    define('MAX_FILE_SIZE', (int)Environment::get('MAX_FILE_SIZE', 10 * 1024 * 1024)); // 10MB default
}

if (!defined('ALLOWED_MIME_TYPES')) {
    define('ALLOWED_MIME_TYPES', [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'application/pdf' => 'pdf'
    ]);
}

// Additional application constants
if (!defined('APP_NAME')) {
    define('APP_NAME', 'McGoff Defect Tracker');
}

if (!defined('APP_VERSION')) {
    define('APP_VERSION', '2.0.0');
}

if (!defined('DEFAULT_TIMEZONE')) {
    define('DEFAULT_TIMEZONE', 'Europe/London');
}

if (!defined('PWA_DOWNLOAD_URL')) {
    define('PWA_DOWNLOAD_URL', Environment::get('PWA_DOWNLOAD_URL', SITE_URL . '/downloads/defect-tracker-pwa.apk'));
}

// Company configuration
if (!defined('COMPANY_CONTRACTOR_ID')) {
    define('COMPANY_CONTRACTOR_ID', 1); // ID of the company record in contractors table
}
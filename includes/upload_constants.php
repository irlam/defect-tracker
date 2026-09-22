<?php
// includes/upload_constants.php
// Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): 2025-01-26 11:15:33

// Check if constants are already defined before defining them
if (!defined('UPLOAD_BASE_DIR')) {
    define('UPLOAD_BASE_DIR', dirname(__DIR__) . '/uploads/defects');
}

if (!defined('MAX_FILE_SIZE')) {
    define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
}

if (!defined('ALLOWED_MIME_TYPES')) {
    define('ALLOWED_MIME_TYPES', [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'application/pdf' => 'pdf'
    ]);
}
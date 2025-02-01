<?php
/**
 * autoload.php
 * Current Date and Time (UTC): 2025-01-26 17:04:45
 * Current User's Login: irlam
 */

// Define the base path for vendor libraries
define('VENDOR_PATH', __DIR__ . '/../vendor/');

// Autoloader function
spl_autoload_register(function ($class) {
    // Convert namespace to full file path
    $path = str_replace('\\', '/', $class);
    
    // PhpSpreadsheet classes
    if (strpos($class, 'PhpOffice\\PhpSpreadsheet') === 0) {
        $file = VENDOR_PATH . 'phpoffice/phpspreadsheet/src/' . substr($path, strlen('PhpOffice/PhpSpreadsheet/')) . '.php';
        if (file_exists($file)) {
            require_once $file;
            return true;
        }
    }
    
    // TCPDF classes
    if (strpos($class, 'TCPDF') === 0) {
        $file = VENDOR_PATH . 'tecnickcom/tcpdf/' . $path . '.php';
        if (file_exists($file)) {
            require_once $file;
            return true;
        }
    }
    
    return false;
});

// Load TCPDF configuration
require_once VENDOR_PATH . 'tecnickcom/tcpdf/config/tcpdf_config.php';
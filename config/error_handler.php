<?php
// config/error_handler.php
// Error handling configuration and logging setup


// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Custom error handler
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    $error_message = date('Y-m-d H:i:s') . " - Error: [$errno] $errstr - File: $errfile:$errline\n";
    error_log($error_message, 3, __DIR__ . '/../logs/error.log');
    
    if (ini_get('display_errors')) {
        echo "<div style='color:red; background-color:#fff; padding:10px; margin:10px; border:1px solid red;'>";
        echo "<strong>Error:</strong> " . htmlspecialchars($errstr);
        echo "</div>";
    }
    
    return true;
}

// Set the custom error handler
set_error_handler("customErrorHandler");

// Ensure the logs directory exists
if (!file_exists(__DIR__ . '/../logs')) {
    mkdir(__DIR__ . '/../logs', 0777, true);
}
?>
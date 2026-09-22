<?php
/**
 * includes/error_handler.php
 * Centralized error handling for the defect tracker
 * Current Date and Time (UTC): <?php echo gmdate('Y-m-d H:i:s'); ?>
 */

require_once __DIR__ . '/../config/env.php';

class ErrorHandler {
    private static $logFile;
    
    public static function init() {
        self::$logFile = __DIR__ . '/../logs/error.log';
        
        // Set error handling based on environment
        if (Environment::isDevelopment()) {
            error_reporting(E_ALL);
            ini_set('display_errors', 1);
        } else {
            error_reporting(E_ERROR | E_WARNING | E_PARSE);
            ini_set('display_errors', 0);
        }
        
        // Always log errors
        ini_set('log_errors', 1);
        ini_set('error_log', self::$logFile);
        
        // Set custom error handlers
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleFatalError']);
    }
    
    /**
     * Handle PHP errors
     */
    public static function handleError($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        
        $errorType = self::getErrorType($severity);
        $logMessage = self::formatErrorMessage($errorType, $message, $file, $line);
        
        self::logError($logMessage);
        
        if (Environment::isDevelopment()) {
            echo "<div class='alert alert-danger'><strong>{$errorType}:</strong> {$message} in {$file} on line {$line}</div>";
        }
        
        return true;
    }
    
    /**
     * Handle uncaught exceptions
     */
    public static function handleException($exception) {
        $logMessage = self::formatErrorMessage(
            'EXCEPTION', 
            $exception->getMessage(), 
            $exception->getFile(), 
            $exception->getLine()
        );
        
        $logMessage .= "\nStack trace:\n" . $exception->getTraceAsString();
        
        self::logError($logMessage);
        
        if (Environment::isDevelopment()) {
            echo "<div class='alert alert-danger'>";
            echo "<strong>Exception:</strong> " . htmlspecialchars($exception->getMessage()) . "<br>";
            echo "<strong>File:</strong> " . htmlspecialchars($exception->getFile()) . " (Line " . $exception->getLine() . ")<br>";
            echo "<details><summary>Stack Trace</summary><pre>" . htmlspecialchars($exception->getTraceAsString()) . "</pre></details>";
            echo "</div>";
        } else {
            echo "<div class='alert alert-danger'>An error occurred. Please contact the administrator.</div>";
        }
    }
    
    /**
     * Handle fatal errors
     */
    public static function handleFatalError() {
        $error = error_get_last();
        
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $logMessage = self::formatErrorMessage(
                'FATAL', 
                $error['message'], 
                $error['file'], 
                $error['line']
            );
            
            self::logError($logMessage);
            
            if (!Environment::isProduction()) {
                echo "<div class='alert alert-danger'><strong>Fatal Error:</strong> " . htmlspecialchars($error['message']) . "</div>";
            }
        }
    }
    
    /**
     * Log custom errors
     */
    public static function logError($message, $context = []) {
        $timestamp = gmdate('Y-m-d H:i:s');
        $user = $_SESSION['username'] ?? 'anonymous';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        $logEntry = "[{$timestamp}] [{$user}] [{$ip}] {$message}";
        
        if (!empty($context)) {
            $logEntry .= " | Context: " . json_encode($context);
        }
        
        $logEntry .= "\n";
        
        // Ensure log directory exists
        $logDir = dirname(self::$logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents(self::$logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Log custom info messages
     */
    public static function logInfo($message, $context = []) {
        self::logError("INFO: " . $message, $context);
    }
    
    /**
     * Log security events
     */
    public static function logSecurity($message, $context = []) {
        $securityLogFile = __DIR__ . '/../logs/security.log';
        $timestamp = gmdate('Y-m-d H:i:s');
        $user = $_SESSION['username'] ?? 'anonymous';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        $logEntry = "[{$timestamp}] [SECURITY] [{$user}] [{$ip}] {$message}";
        
        if (!empty($context)) {
            $logEntry .= " | Context: " . json_encode($context);
        }
        
        $logEntry .= "\n";
        
        file_put_contents($securityLogFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Get error type string
     */
    private static function getErrorType($severity) {
        switch ($severity) {
            case E_ERROR:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case E_USER_ERROR:
                return 'ERROR';
            case E_WARNING:
            case E_CORE_WARNING:
            case E_COMPILE_WARNING:
            case E_USER_WARNING:
                return 'WARNING';
            case E_NOTICE:
            case E_USER_NOTICE:
                return 'NOTICE';
            case E_STRICT:
                return 'STRICT';
            case E_DEPRECATED:
            case E_USER_DEPRECATED:
                return 'DEPRECATED';
            default:
                return 'UNKNOWN';
        }
    }
    
    /**
     * Format error message
     */
    private static function formatErrorMessage($type, $message, $file, $line) {
        return "{$type}: {$message} in {$file} on line {$line}";
    }
    
    /**
     * Clean old log files
     */
    public static function cleanupLogs($daysToKeep = 30) {
        $logDirectory = __DIR__ . '/../logs/';
        $files = glob($logDirectory . '*.log');
        $cutoffTime = time() - ($daysToKeep * 24 * 60 * 60);
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoffTime) {
                unlink($file);
            }
        }
    }
}

// Initialize error handler
ErrorHandler::init();
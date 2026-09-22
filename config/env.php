<?php
/**
 * config/env.php
 * Environment variable loader and configuration management
 */

class Environment {
    private static $loaded = false;
    
    /**
     * Load environment variables from .env file
     */
    public static function load($envFile = null) {
        if (self::$loaded) {
            return;
        }
        
        $envFile = $envFile ?: dirname(__DIR__) . '/.env';
        
        if (!file_exists($envFile)) {
            // Fallback to .env.example for development
            $envFile = dirname(__DIR__) . '/.env.example';
        }
        
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            
            foreach ($lines as $line) {
                // Skip comments
                if (strpos(trim($line), '#') === 0) {
                    continue;
                }
                
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Remove quotes if present
                $value = trim($value, '"\'');
                
                // Set environment variable if not already set
                if (!isset($_ENV[$key])) {
                    $_ENV[$key] = $value;
                    putenv("$key=$value");
                }
            }
        }
        
        self::$loaded = true;
    }
    
    /**
     * Get environment variable with optional default
     */
    public static function get($key, $default = null) {
        self::load();
        return $_ENV[$key] ?? getenv($key) ?: $default;
    }
    
    /**
     * Check if running in production environment
     */
    public static function isProduction() {
        return self::get('ENVIRONMENT', 'production') === 'production';
    }
    
    /**
     * Check if running in development environment
     */
    public static function isDevelopment() {
        return self::get('ENVIRONMENT', 'production') === 'development';
    }
}

// Load environment variables
Environment::load();
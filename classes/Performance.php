<?php
/**
 * classes/Performance.php
 * Simple performance monitoring and optimization utilities
 * Current Date and Time (UTC): <?php echo gmdate('Y-m-d H:i:s'); ?>
 */

class Performance {
    private static $startTime;
    private static $checkpoints = [];
    private static $memoryUsage = [];
    private static $queryCount = 0;
    private static $queryTime = 0;
    
    /**
     * Start performance monitoring
     */
    public static function start() {
        self::$startTime = microtime(true);
        self::$memoryUsage['start'] = memory_get_usage(true);
    }
    
    /**
     * Add a checkpoint
     */
    public static function checkpoint($name) {
        if (self::$startTime === null) {
            self::start();
        }
        
        self::$checkpoints[$name] = [
            'time' => microtime(true) - self::$startTime,
            'memory' => memory_get_usage(true)
        ];
    }
    
    /**
     * Track database query performance
     */
    public static function trackQuery($queryTime) {
        self::$queryCount++;
        self::$queryTime += $queryTime;
    }
    
    /**
     * Get performance report
     */
    public static function getReport() {
        if (self::$startTime === null) {
            return ['error' => 'Performance monitoring not started'];
        }
        
        $endTime = microtime(true);
        $totalTime = $endTime - self::$startTime;
        $peakMemory = memory_get_peak_usage(true);
        $currentMemory = memory_get_usage(true);
        
        return [
            'total_time' => round($totalTime * 1000, 2) . 'ms',
            'query_count' => self::$queryCount,
            'query_time' => round(self::$queryTime * 1000, 2) . 'ms',
            'memory_usage' => [
                'current' => self::formatBytes($currentMemory),
                'peak' => self::formatBytes($peakMemory),
                'start' => self::formatBytes(self::$memoryUsage['start'] ?? 0)
            ],
            'checkpoints' => array_map(function($checkpoint) {
                return [
                    'time' => round($checkpoint['time'] * 1000, 2) . 'ms',
                    'memory' => self::formatBytes($checkpoint['memory'])
                ];
            }, self::$checkpoints),
            'server_load' => self::getServerLoad()
        ];
    }
    
    /**
     * Display performance report (for development)
     */
    public static function displayReport() {
        if (!Environment::isDevelopment()) {
            return;
        }
        
        $report = self::getReport();
        
        echo '<div class="alert alert-info" style="position: fixed; bottom: 10px; right: 10px; z-index: 9999; max-width: 300px; font-size: 12px;">';
        echo '<strong>Performance Report</strong><br>';
        echo 'Total Time: ' . $report['total_time'] . '<br>';
        echo 'Queries: ' . $report['query_count'] . ' (' . $report['query_time'] . ')<br>';
        echo 'Memory: ' . $report['memory_usage']['current'] . ' (Peak: ' . $report['memory_usage']['peak'] . ')<br>';
        
        if (!empty($report['checkpoints'])) {
            echo '<strong>Checkpoints:</strong><br>';
            foreach ($report['checkpoints'] as $name => $data) {
                echo $name . ': ' . $data['time'] . '<br>';
            }
        }
        
        echo '</div>';
    }
    
    /**
     * Format bytes to human readable format
     */
    private static function formatBytes($size, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        
        return round($size, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Get server load average
     */
    private static function getServerLoad() {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return [
                '1min' => $load[0],
                '5min' => $load[1], 
                '15min' => $load[2]
            ];
        }
        
        return null;
    }
    
    /**
     * Log performance data for analysis
     */
    public static function logPerformance($pageId) {
        if (Environment::isProduction()) {
            $report = self::getReport();
            
            // Only log if performance is concerning
            $totalTimeMs = floatval(str_replace('ms', '', $report['total_time']));
            $queryCount = $report['query_count'];
            
            if ($totalTimeMs > 1000 || $queryCount > 50) { // More than 1 second or 50 queries
                ErrorHandler::logInfo("Performance concern on {$pageId}", [
                    'total_time' => $report['total_time'],
                    'query_count' => $queryCount,
                    'memory_peak' => $report['memory_usage']['peak']
                ]);
            }
        }
    }
    
    /**
     * Optimize images for web
     */
    public static function optimizeImage($sourcePath, $destinationPath, $quality = 85, $maxWidth = 1920, $maxHeight = 1080) {
        if (!file_exists($sourcePath)) {
            return false;
        }
        
        $imageInfo = getimagesize($sourcePath);
        if (!$imageInfo) {
            return false;
        }
        
        $width = $imageInfo[0];
        $height = $imageInfo[1];
        $mimeType = $imageInfo['mime'];
        
        // Calculate new dimensions
        if ($width > $maxWidth || $height > $maxHeight) {
            $ratio = min($maxWidth / $width, $maxHeight / $height);
            $newWidth = round($width * $ratio);
            $newHeight = round($height * $ratio);
        } else {
            $newWidth = $width;
            $newHeight = $height;
        }
        
        // Create image resource
        switch ($mimeType) {
            case 'image/jpeg':
                $source = imagecreatefromjpeg($sourcePath);
                break;
            case 'image/png':
                $source = imagecreatefrompng($sourcePath);
                break;
            case 'image/gif':
                $source = imagecreatefromgif($sourcePath);
                break;
            default:
                return false;
        }
        
        if (!$source) {
            return false;
        }
        
        // Create new image
        $destination = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preserve transparency for PNG and GIF
        if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
            imagealphablending($destination, false);
            imagesavealpha($destination, true);
            $transparent = imagecolorallocatealpha($destination, 0, 0, 0, 127);
            imagefill($destination, 0, 0, $transparent);
        }
        
        // Resize image
        imagecopyresampled($destination, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        
        // Save optimized image
        $result = false;
        switch ($mimeType) {
            case 'image/jpeg':
                $result = imagejpeg($destination, $destinationPath, $quality);
                break;
            case 'image/png':
                $result = imagepng($destination, $destinationPath, 9); // Max compression
                break;
            case 'image/gif':
                $result = imagegif($destination, $destinationPath);
                break;
        }
        
        // Clean up memory
        imagedestroy($source);
        imagedestroy($destination);
        
        return $result;
    }
    
    /**
     * Clean up old cache files
     */
    public static function cleanupCache($cacheDir, $maxAge = 3600) {
        if (!is_dir($cacheDir)) {
            return false;
        }
        
        $files = glob($cacheDir . '/*');
        $cutoff = time() - $maxAge;
        $cleaned = 0;
        
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoff) {
                if (unlink($file)) {
                    $cleaned++;
                }
            }
        }
        
        return $cleaned;
    }
}
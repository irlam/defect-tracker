<?php
require_once 'config.php';
/**
 * McGoff Backup Manager - Helper Functions
 * 
 * This file contains utility functions used throughout the backup system for operations
 * like date formatting, security tokens, file size formatting, and backup management.
 * 
 * Created: 2025-02-26 19:27:55
 * Author: irlam
 */
	
	
/**
 * Generate a secure token for CSRF protection
 */
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verify_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        die('CSRF token validation failed');
    }
    return true;
}

/**
 * Format file size to human-readable format
 */
function format_size($size) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $i = 0;
    while ($size >= 1024 && $i < count($units) - 1) {
        $size /= 1024;
        $i++;
    }
    return round($size, 2) . ' ' . $units[$i];
}

/**
 * Format date in UK format (DD-MM-YYYY HH:MM:SS)
 */
function format_date_uk($timestamp) {
    return date('d-m-Y H:i:s', $timestamp);
}

/**
 * Convert UTC datetime to UK format
 */
function utc_to_uk_format($utc_datetime) {
    $timestamp = strtotime($utc_datetime);
    return format_date_uk($timestamp);
}

/**
 * Get all available backups
 */
function get_backups() {
    $backups = array();
    if (is_dir(BACKUP_DIR)) {
        $files = scandir(BACKUP_DIR);
        foreach ($files as $file) {
            if ($file != '.' && $file != '..' && pathinfo($file, PATHINFO_EXTENSION) == 'zip') {
                $filepath = BACKUP_DIR . '/' . $file;
                $backups[] = array(
                    'filename' => $file,
                    'size' => format_size(filesize($filepath)),
                    'date' => format_date_uk(filemtime($filepath)), // UK format
                    'path' => $filepath
                );
            }
        }
        
        // Sort by date (newest first)
        usort($backups, function($a, $b) {
            $timeA = filemtime($a['path']);
            $timeB = filemtime($b['path']);
            return $timeB - $timeA;
        });
    }
    return $backups;
}

/**
 * Clean old backups if we exceed MAX_BACKUPS
 */
function clean_old_backups() {
    $backups = get_backups();
    if (count($backups) > MAX_BACKUPS) {
        $to_delete = array_slice($backups, MAX_BACKUPS);
        foreach ($to_delete as $backup) {
            unlink($backup['path']);
        }
    }
}

/**
 * List files in a backup archive
 */
function list_backup_files($backupFile) {
    $files = array();
    $zip = new ZipArchive();
    if ($zip->open($backupFile) === TRUE) {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $files[] = $zip->getNameIndex($i);
        }
        $zip->close();
    }
    return $files;
}

/**
 * Check if user is authenticated
 */
function is_authenticated() {
    return isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
}

/**
 * Get current datetime in specified format
 */
function get_current_datetime($format = 'd-m-Y H:i:s') {
    return date($format);
}
?>
<?php
/**
 * McGoff Backup Manager - Scheduled Backup Manager
 * 
 * This file manages the scheduled backup functionality, allowing users to create,
 * view, edit, and delete scheduled backup jobs. The actual execution of scheduled
 * backups is handled by the run-scheduled-backup.php script via cron.
 * 
 * Created: 2025-02-26 19:48:11
 * Author: irlam
 */

require_once 'config.php';
require_once 'functions.php';

/**
 * Get all scheduled backup jobs
 */
function get_scheduled_backups() {
    $scheduleFile = __DIR__ . '/scheduler/backup_schedule.json';
    
    if (!file_exists($scheduleFile)) {
        return [];
    }
    
    $scheduleJson = file_get_contents($scheduleFile);
    if (empty($scheduleJson)) {
        return [];
    }
    
    $schedules = json_decode($scheduleJson, true);
    if (!is_array($schedules)) {
        return [];
    }
    
    return $schedules;
}

/**
 * Save a scheduled backup job
 */
function save_scheduled_backup($schedule) {
    // Create scheduler directory if it doesn't exist
    $schedulerDir = __DIR__ . '/scheduler';
    if (!file_exists($schedulerDir)) {
        mkdir($schedulerDir, 0755, true);
    }
    
    $scheduleFile = $schedulerDir . '/backup_schedule.json';
    $schedules = get_scheduled_backups();
    
    // Generate a unique ID if not provided
    if (empty($schedule['id'])) {
        $schedule['id'] = uniqid('schedule_');
    }
    
    // Add creation timestamp if new
    if (empty($schedule['created'])) {
        $schedule['created'] = date('Y-m-d H:i:s');
    }
    
    // Update last modified timestamp
    $schedule['modified'] = date('Y-m-d H:i:s');
    
    // Validate schedule
    if (empty($schedule['time']) || empty($schedule['frequency'])) {
        return [
            'success' => false,
            'message' => 'Schedule time and frequency are required'
        ];
    }
    
    // Add or update the schedule
    $schedules[$schedule['id']] = $schedule;
    
    // Save to file
    $result = file_put_contents($scheduleFile, json_encode($schedules, JSON_PRETTY_PRINT));
    
    if ($result === false) {
        return [
            'success' => false,
            'message' => 'Failed to save schedule to file'
        ];
    }
    
    return [
        'success' => true,
        'message' => 'Backup schedule saved successfully',
        'schedule' => $schedule
    ];
}

/**
 * Delete a scheduled backup job
 */
function delete_scheduled_backup($id) {
    $scheduleFile = __DIR__ . '/scheduler/backup_schedule.json';
    $schedules = get_scheduled_backups();
    
    if (!isset($schedules[$id])) {
        return [
            'success' => false,
            'message' => 'Schedule not found'
        ];
    }
    
    unset($schedules[$id]);
    
    $result = file_put_contents($scheduleFile, json_encode($schedules, JSON_PRETTY_PRINT));
    
    if ($result === false) {
        return [
            'success' => false,
            'message' => 'Failed to update schedule file'
        ];
    }
    
    return [
        'success' => true,
        'message' => 'Schedule deleted successfully'
    ];
}

/**
 * Get the next run time for a schedule
 */
function get_next_run_time($schedule) {
    $now = time();
    $frequency = $schedule['frequency'];
    
    // Get the base time for today's schedule
    list($hour, $minute) = explode(':', $schedule['time']);
    $baseTime = mktime((int)$hour, (int)$minute, 0);
    
    // For daily schedules
    if ($frequency === 'daily') {
        $nextRun = $baseTime;
        if ($nextRun < $now) {
            // If today's scheduled time has passed, move to tomorrow
            $nextRun = strtotime('+1 day', $baseTime);
        }
    }
    // For weekly schedules
    elseif ($frequency === 'weekly') {
        $dayOfWeek = $schedule['day_of_week'] ?? 1; // Default to Monday
        $nextRun = strtotime("this week $dayOfWeek", $baseTime);
        if ($nextRun < $now) {
            $nextRun = strtotime("next week $dayOfWeek", $baseTime);
        }
    }
    // For monthly schedules
    elseif ($frequency === 'monthly') {
        $dayOfMonth = $schedule['day_of_month'] ?? 1; // Default to 1st
        
        // Get the current month/year
        $currentMonth = date('n');
        $currentYear = date('Y');
        
        // Calculate the next run time for the specified day of this month
        $nextRun = mktime((int)$hour, (int)$minute, 0, $currentMonth, $dayOfMonth, $currentYear);
        
        // If that time has passed, move to next month
        if ($nextRun < $now) {
            $nextMonth = $currentMonth + 1;
            $nextYear = $currentYear;
            if ($nextMonth > 12) {
                $nextMonth = 1;
                $nextYear++;
            }
            $nextRun = mktime((int)$hour, (int)$minute, 0, $nextMonth, $dayOfMonth, $nextYear);
        }
    }
    
    return $nextRun;
}

/**
 * Generate cron expression for a schedule
 */
function generate_cron_expression($schedule) {
    list($hour, $minute) = explode(':', $schedule['time']);
    $frequency = $schedule['frequency'];
    
    // Base cron pattern: minute hour day-of-month month day-of-week
    switch ($frequency) {
        case 'daily':
            return "$minute $hour * * *";
            
        case 'weekly':
            $dayOfWeek = $schedule['day_of_week'] ?? 1; // Default to Monday
            return "$minute $hour * * $dayOfWeek";
            
        case 'monthly':
            $dayOfMonth = $schedule['day_of_month'] ?? 1; // Default to 1st
            return "$minute $hour $dayOfMonth * *";
            
        default:
            return "# Invalid frequency: $frequency";
    }
}

/**
 * Get human-readable frequency description
 */
function get_frequency_description($schedule) {
    $frequency = $schedule['frequency'];
    $timeStr = $schedule['time'];
    
    switch ($frequency) {
        case 'daily':
            return "Every day at $timeStr";
            
        case 'weekly':
            $days = [
                1 => 'Monday',
                2 => 'Tuesday',
                3 => 'Wednesday',
                4 => 'Thursday',
                5 => 'Friday',
                6 => 'Saturday',
                0 => 'Sunday'
            ];
            $dayOfWeek = $schedule['day_of_week'] ?? 1;
            $dayName = $days[$dayOfWeek] ?? 'Monday';
            return "Every $dayName at $timeStr";
            
        case 'monthly':
            $dayOfMonth = $schedule['day_of_month'] ?? 1;
            
            // Add suffix for day of month
            $suffix = 'th';
            if ($dayOfMonth == 1 || $dayOfMonth == 21 || $dayOfMonth == 31) {
                $suffix = 'st';
            } elseif ($dayOfMonth == 2 || $dayOfMonth == 22) {
                $suffix = 'nd';
            } elseif ($dayOfMonth == 3 || $dayOfMonth == 23) {
                $suffix = 'rd';
            }
            
            return "Monthly on the $dayOfMonth$suffix at $timeStr";
            
        default:
            return "Unknown schedule";
    }
}

/**
 * Check if a scheduled backup should run now
 */
function should_run_schedule($schedule) {
    $now = time();
    $lastRun = isset($schedule['last_run']) ? strtotime($schedule['last_run']) : 0;
    
    // Get the next scheduled run time
    $nextRun = get_next_run_time($schedule);
    
    // Check if it's time to run and it hasn't been run already
    if ($now >= $nextRun && ($now - $lastRun) > 3600) { // Prevent running more than once per hour
        return true;
    }
    
    return false;
}

/**
 * Update the last run time for a schedule
 */
function update_schedule_last_run($scheduleId) {
    $schedules = get_scheduled_backups();
    
    if (!isset($schedules[$scheduleId])) {
        return false;
    }
    
    $schedules[$scheduleId]['last_run'] = date('Y-m-d H:i:s');
    
    $scheduleFile = __DIR__ . '/scheduler/backup_schedule.json';
    return file_put_contents($scheduleFile, json_encode($schedules, JSON_PRETTY_PRINT)) !== false;
}
?>
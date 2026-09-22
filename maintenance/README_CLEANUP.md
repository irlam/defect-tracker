# Log Cleanup Script Documentation

## Overview
The `cleanup_old_logs.php` script automatically removes log entries older than 30 days from the database to prevent excessive storage usage.

## What it cleans
- **user_logs table**: User actions like login, logout, status changes, etc.
- **activity_logs table**: Defect-related activities like assignments, updates, etc.

## Usage

### Manual Execution
Run from the command line:
```bash
php /path/to/defect-tracker/maintenance/cleanup_old_logs.php
```

### Automated Execution (Recommended)
Set up a cron job to run the script daily at 2 AM:

1. Edit your crontab:
```bash
crontab -e
```

2. Add this line:
```bash
0 2 * * * /usr/bin/php /path/to/defect-tracker/maintenance/cleanup_old_logs.php >> /path/to/defect-tracker/logs/cleanup.log 2>&1
```

This will:
- Run every day at 2:00 AM
- Log the output to `logs/cleanup.log`
- Log errors to the same file

## Output
The script outputs:
- Number of entries deleted from `user_logs`
- Number of entries deleted from `activity_logs`
- Total entries deleted
- Any errors encountered

## Safety
- The script can only be run from the command line (not via web browser)
- It only affects logs older than 30 days
- Recent logs (last 30 days) are always preserved
- Database operations are wrapped in try-catch blocks for error handling

## Monitoring
Check the maintenance log file to verify cleanup is running:
```bash
tail -f /path/to/defect-tracker/logs/maintenance.log
```

Or if using cron output:
```bash
tail -f /path/to/defect-tracker/logs/cleanup.log
```

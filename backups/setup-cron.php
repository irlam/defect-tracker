<?php
/**
 * McGoff Backup Manager - Cron Job Setup Helper
 * 
 * This file helps users configure the cron job needed to run scheduled backups.
 * It provides instructions and the exact cron command needed for their server.
 * 
 * Created: 2025-02-26 20:08:47
 * Author: irlam
 */

session_start();
require_once 'auth.php';
require_once 'config.php';
require_once 'scheduled-backups.php';

// Get the full path to PHP and the run-scheduled-backup.php script
$phpPath = PHP_BINARY; // PHP executable path
$scriptPath = __DIR__ . '/run-scheduled-backup.php';
$cronCommand = "*/10 * * * * $phpPath $scriptPath > /dev/null 2>&1";

// For shared hosting environments, sometimes the PHP path needs to be specified differently
$alternativeCronCommand = "*/10 * * * * /usr/bin/php $scriptPath > /dev/null 2>&1";

// Get scheduled backups
$scheduledBackups = get_scheduled_backups();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cron Setup - McGoff Backup Manager</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Scheduled Backup Configuration</h1>
            <p>Current user: <?php echo htmlspecialchars(CURRENT_USER); ?></p>
            <p>Current time: <?php echo date('d-m-Y H:i:s'); ?></p>
            <p><a href="index.php">&laquo; Back to Backup Manager</a></p>
        </header>
        
        <section>
            <h2>Cron Job Setup</h2>
            
            <p>To enable scheduled backups, you need to set up a cron job on your server. This cron job will check for scheduled backups and run them when required.</p>
            
            <div class="info-box">
                <h3>Add this line to your server's crontab:</h3>
                <pre><?php echo htmlspecialchars($cronCommand); ?></pre>
                <p>This will run the scheduler every 10 minutes to check if any backups are due.</p>
                
                <h3>Alternative command (if the above doesn't work):</h3>
                <pre><?php echo htmlspecialchars($alternativeCronCommand); ?></pre>
            </div>
            
            <h3>Instructions for different hosting environments:</h3>
            
            <div class="hosting-instructions">
                <h4>If you have SSH access:</h4>
                <ol>
                    <li>Connect to your server via SSH</li>
                    <li>Run the command: <code>crontab -e</code></li>
                    <li>Add the line shown above</li>
                    <li>Save and exit</li>
                </ol>
                
                <h4>If you use cPanel:</h4>
                <ol>
                    <li>Login to your cPanel account</li>
                    <li>Find and click on "Cron Jobs" or "Scheduled Tasks"</li>
                    <li>Select your preferred interval (e.g., "Every 10 minutes")</li>
                    <li>Copy and paste the command line (without the timing part)</li>
                    <li>Save the cron job</li>
                </ol>
                
                <h4>If you use Plesk:</h4>
                <ol>
                    <li>Login to Plesk Control Panel</li>
                    <li>Go to "Scheduled Tasks"</li>
                    <li>Click "Add Task"</li>
                    <li>Choose "Run a PHP script" or "Run a command"</li>
                    <li>Enter the full command or path to the script</li>
                    <li>Set the schedule to run every 10 minutes</li>
                    <li>Save the task</li>
                </ol>
            </div>
            
            <p><strong>Note:</strong> If you don't have access to set up cron jobs, please contact your hosting provider for assistance.</p>
        </section>
        
        <section>
            <h2>Current Scheduled Backups</h2>
            <?php if (empty($scheduledBackups)): ?>
                <p>No scheduled backups configured yet. <a href="index.php">Go back</a> to create some.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Schedule</th>
                            <th>Next Run</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($scheduledBackups as $id => $schedule): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($schedule['name']); ?></td>
                            <td><?php echo htmlspecialchars(get_frequency_description($schedule)); ?></td>
                            <td>
                                <?php
                                    $nextRun = get_next_run_time($schedule);
                                    echo format_date_uk($nextRun);
                                ?>
                            </td>
                            <td>
                                <?php if ($schedule['enabled'] ?? false): ?>
                                    <span class="badge badge-success">Enabled</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Disabled</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="cron-expressions">
                    <h3>Equivalent Cron Expressions:</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Schedule Name</th>
                                <th>Cron Expression</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($scheduledBackups as $id => $schedule): ?>
                                <?php if ($schedule['enabled'] ?? false): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($schedule['name']); ?></td>
                                    <td><code><?php echo generate_cron_expression($schedule); ?></code></td>
                                </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
        
        <section class="hosting-specific">
            <h2>Netcup Hosting Instructions</h2>
            <p>Since you're using Netcup hosting, here are specific instructions for setting up cron jobs:</p>
            
            <ol>
                <li>Log in to your Netcup Customer Control Panel (CCP)</li>
                <li>Navigate to your hosting package</li>
                <li>Look for "Cron Jobs" or "Scheduled Tasks" in the sidebar</li>
                <li>Create a new cron job with the following settings:
                    <ul>
                        <li>Timing: Every 10 minutes</li>
                        <li>Command: <code>/usr/bin/php <?php echo htmlspecialchars($scriptPath); ?> > /dev/null 2>&1</code></li>
                    </ul>
                </li>
                <li>Save the cron job</li>
            </ol>
            
            <p>If you have any issues, please contact Netcup support for assistance with setting up cron jobs on your specific hosting package.</p>
        </section>
        
        <footer>
            <p><a href="index.php">Back to Backup Manager</a></p>
        </footer>
    </div>
</body>
</html>
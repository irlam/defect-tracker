<?php
/**
 * Backup Manager - Main Interface
 * 
 * This is the main interface for the backup manager, providing options to create
 * backups manually, manage scheduled backups, and view existing backups.
 * 
 * Updated: 2025-11-04
 * Author: irlam
 */

// Start session if not already started
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once 'auth.php';
require_once 'config.php';
require_once 'functions.php';
require_once 'backup-manager.php';
require_once 'scheduled-backups.php';
require_once __DIR__ . '/../includes/navbar.php';

// Initialize variables
$message = '';
$messageType = '';

// Get user information for display
$displayName = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User';
$username = $_SESSION['username'] ?? '';
$userId = $_SESSION['user_id'] ?? 0;

// Initialize navbar
$navbar = null;
try {
    require_once __DIR__ . '/../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    $navbar = new Navbar($db, $userId, $username);
} catch (Throwable $navbarError) {
    error_log('Navbar initialisation error on backups/index.php: ' . $navbarError->getMessage());
}

// Get list of existing backups
$backups = get_backups();

// Get scheduled backups
$scheduledBackups = get_scheduled_backups();

// Handle schedule creation/update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'save_schedule') {
    verify_csrf_token($_POST['csrf_token']);
    
    $schedule = [
        'id' => $_POST['schedule_id'] ?? '',
        'name' => $_POST['schedule_name'] ?? 'Unnamed Schedule',
        'frequency' => $_POST['frequency'] ?? 'daily',
        'time' => $_POST['time'] ?? '00:00',
        'enabled' => isset($_POST['enabled']) ? true : false,
    ];
    
    // Add day of week for weekly schedules
    if ($schedule['frequency'] === 'weekly') {
        $schedule['day_of_week'] = $_POST['day_of_week'] ?? 1;
    }
    
    // Add day of month for monthly schedules
    if ($schedule['frequency'] === 'monthly') {
        $schedule['day_of_month'] = $_POST['day_of_month'] ?? 1;
    }
    
    $result = save_scheduled_backup($schedule);
    $message = $result['message'];
    $messageType = $result['success'] ? 'success' : 'error';
    
    // Refresh the schedules
    $scheduledBackups = get_scheduled_backups();
}

// Handle schedule deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete_schedule') {
    verify_csrf_token($_POST['csrf_token']);
    
    $scheduleId = $_POST['schedule_id'] ?? '';
    if (!empty($scheduleId)) {
        $result = delete_scheduled_backup($scheduleId);
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';
        
        // Refresh the schedules
        $scheduledBackups = get_scheduled_backups();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup Manager | System Tools</title>
    <link rel="icon" type="image/png" href="/favicons/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicons/favicon.svg" />
    <link rel="shortcut icon" href="/favicons/favicon.ico" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="../css/app.css" rel="stylesheet">
    <style>
        .backup-progress-container {
            display: none;
            margin-top: 1.5rem;
            padding: 1.5rem;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(37, 99, 235, 0.16);
            border-radius: var(--border-radius-lg);
        }
        
        .progress-bar-container {
            height: 0.75rem;
            background: rgba(37, 99, 235, 0.18);
            border-radius: 999px;
            overflow: hidden;
            margin-bottom: 0.75rem;
        }
        
        .progress-bar {
            height: 100%;
            width: 0;
            background: linear-gradient(90deg, rgba(14, 165, 233, 0.85), rgba(59, 130, 246, 0.95));
            transition: width 0.3s ease;
        }
        
        .modal {
            background: rgba(2, 6, 23, 0.82);
        }
        
        .modal-content {
            background: rgba(15, 23, 42, 0.95);
            border: 1px solid rgba(37, 99, 235, 0.2);
            border-radius: var(--border-radius-xl);
        }
        
        .schedule-preview {
            padding: 0.75rem 1rem;
            background: rgba(37, 99, 235, 0.12);
            border: 1px solid rgba(37, 99, 235, 0.2);
            border-radius: var(--border-radius-md);
            color: rgba(226, 232, 240, 0.96);
        }
        
        .tool-card {
            background: rgba(15, 23, 42, 0.92);
            border: 1px solid rgba(37, 99, 235, 0.16);
            border-radius: var(--border-radius-xl);
            box-shadow: 0 32px 60px rgba(2, 6, 23, 0.55);
            padding: clamp(1.5rem, 3vw, 2.25rem);
            margin-bottom: 1.5rem;
        }
        
        .badge-success {
            background: rgba(22, 163, 74, 0.18);
            border: 1px solid rgba(34, 197, 94, 0.35);
            color: rgba(187, 247, 208, 0.95);
            padding: 0.25rem 0.6rem;
            border-radius: 999px;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        
        .badge-warning {
            background: rgba(234, 179, 8, 0.18);
            border: 1px solid rgba(250, 204, 21, 0.35);
            color: rgba(254, 240, 138, 0.95);
            padding: 0.25rem 0.6rem;
            border-radius: 999px;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
    </style>
</head>
<body class="tool-body" data-bs-theme="dark">
    <?php if ($navbar instanceof Navbar) { $navbar->render(); } ?>
    <div class="app-content-offset"></div>
    
    <main class="tool-page container-xl py-4">
        <header class="tool-header mb-4">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <h1 class="h3 mb-2">Backup Manager</h1>
                    <p class="text-muted mb-0">Create, schedule, and manage database backups for disaster recovery.</p>
                </div>
                <div class="d-flex flex-column align-items-start text-muted small gap-1">
                    <span><i class='bx bx-user-voice me-1'></i><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></span>
                    <span><i class='bx bx-time-five me-1'></i><?php echo date('d-m-Y H:i:s'); ?> UK</span>
                </div>
            </div>
        </header>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?> mb-4" role="alert">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <section class="tool-card">
            <h2 class="h5 mb-3"><i class='bx bx-shield-quarter me-2'></i>Backup Actions</h2>
            <div class="d-flex flex-wrap gap-2 mb-3">
                <button id="create-backup-btn" class="btn btn-primary"><i class='bx bx-save me-1'></i>Create Full Backup</button>
                <a href="diagnostics.php" class="btn btn-outline-light"><i class='bx bx-pulse me-1'></i>Run System Diagnostics</a>
                <a href="setup-cron.php" class="btn btn-outline-light"><i class='bx bx-time me-1'></i>Set Up Scheduled Backups</a>
            </div>
            
            <!-- Progress bar (hidden by default) -->
            <div id="backup-progress-container" class="backup-progress-container">
                <h3 class="h6 mb-2">Backup Progress</h3>
                <div class="progress-bar-container">
                    <div id="progress-bar" class="progress-bar"></div>
                </div>
                <p id="progress-text" class="mb-1 fw-medium">0%</p>
                <p id="current-activity" class="mb-1 text-muted small">Preparing backup...</p>
                <p id="current-file" class="mb-0 text-muted small"></p>
            </div>
        </section>
        
        <section class="tool-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h5 mb-0"><i class='bx bx-calendar-event me-2'></i>Scheduled Backups</h2>
                <button id="new-schedule-btn" class="btn btn-sm btn-outline-light"><i class='bx bx-plus me-1'></i>New Schedule</button>
            </div>
            
            <?php if (empty($scheduledBackups)): ?>
                <p class="text-muted mb-0">No scheduled backups configured.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Schedule</th>
                                <th>Next Run</th>
                                <th>Status</th>
                                <th>Actions</th>
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
                                        <span class="badge-success">Enabled</span>
                                    <?php else: ?>
                                        <span class="badge-warning">Disabled</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button onclick="editSchedule('<?php echo $id; ?>')" class="btn btn-sm btn-outline-light me-1"><i class='bx bx-edit'></i></button>
                                    <form method="post" action="index.php" style="display: inline-block;">
                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                        <input type="hidden" name="action" value="delete_schedule">
                                        <input type="hidden" name="schedule_id" value="<?php echo $id; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this schedule?')"><i class='bx bx-trash'></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
            <!-- Schedule Form Dialog -->
            <div id="schedule-dialog" class="modal" style="display:none;" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="schedule-form-title">New Scheduled Backup</h5>
                            <button type="button" class="btn-close close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form method="post" action="index.php" id="schedule-form">
                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                <input type="hidden" name="action" value="save_schedule">
                                <input type="hidden" name="schedule_id" id="schedule_id" value="">
                                
                                <div class="mb-3">
                                    <label for="schedule_name" class="form-label">Schedule Name:</label>
                                    <input type="text" class="form-control" id="schedule_name" name="schedule_name" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="frequency" class="form-label">Frequency:</label>
                                    <select class="form-select" id="frequency" name="frequency" onchange="updateScheduleFields()">
                                        <option value="daily">Daily</option>
                                        <option value="weekly">Weekly</option>
                                        <option value="monthly">Monthly</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3" id="day-of-week-group" style="display:none;">
                                    <label for="day_of_week" class="form-label">Day of Week:</label>
                                    <select class="form-select" id="day_of_week" name="day_of_week">
                                        <option value="1">Monday</option>
                                        <option value="2">Tuesday</option>
                                        <option value="3">Wednesday</option>
                                        <option value="4">Thursday</option>
                                        <option value="5">Friday</option>
                                        <option value="6">Saturday</option>
                                        <option value="0">Sunday</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3" id="day-of-month-group" style="display:none;">
                                    <label for="day_of_month" class="form-label">Day of Month:</label>
                                    <select class="form-select" id="day_of_month" name="day_of_month">
                                        <?php for ($i = 1; $i <= 28; $i++): ?>
                                            <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="time" class="form-label">Time (24-hour format):</label>
                                    <input type="time" class="form-control" id="time" name="time" value="00:00" required>
                                </div>
                                
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="enabled" name="enabled" checked>
                                    <label class="form-check-label" for="enabled">
                                        Enabled
                                    </label>
                                </div>
                                
                                <div class="mb-3">
                                    <p class="schedule-preview">This backup will run <span id="schedule-preview-text">every day at 00:00</span></p>
                                </div>
                                
                                <div class="d-flex justify-content-end gap-2">
                                    <button type="button" class="btn btn-outline-light" onclick="closeScheduleDialog()">Cancel</button>
                                    <button type="submit" class="btn btn-primary"><i class='bx bx-save me-1'></i>Save Schedule</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        
        <section class="tool-card">
            <h2 class="h5 mb-3"><i class='bx bx-folder me-2'></i>Available Backups</h2>
            <?php if (empty($backups)): ?>
                <p class="text-muted mb-0">No backups available yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Filename</th>
                                <th>Date</th>
                                <th>Size</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="backups-table-body">
                            <?php foreach ($backups as $backup): ?>
                            <tr>
                                <td class="text-break"><?php echo htmlspecialchars($backup['filename']); ?></td>
                                <td><?php echo htmlspecialchars($backup['date']); ?></td>
                                <td><?php echo htmlspecialchars($backup['size']); ?></td>
                                <td>
                                    <a href="restore.php?file=<?php echo urlencode($backup['filename']); ?>" class="btn btn-sm btn-outline-light me-1"><i class='bx bx-search me-1'></i>Browse Files</a>
                                    <a href="download.php?file=<?php echo urlencode($backup['filename']); ?>" class="btn btn-sm btn-outline-info"><i class='bx bx-download me-1'></i>Download</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <footer class="tool-footer py-3 mt-auto">
        <div class="container-xl text-center text-muted small">
            <span>McGoff Construction Defect Tracker &mdash; Backup Manager</span>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Get elements
        const backupBtn = document.getElementById('create-backup-btn');
        const progressContainer = document.getElementById('backup-progress-container');
        const progressBar = document.getElementById('progress-bar');
        const progressText = document.getElementById('progress-text');
        const currentActivity = document.getElementById('current-activity');
        const currentFile = document.getElementById('current-file');
        const backupsTableBody = document.getElementById('backups-table-body');
        
        // CSRF token
        const csrfToken = '<?php echo generate_csrf_token(); ?>';
        
        // Flag to track if backup is in progress
        let backupInProgress = false;
        let statusCheckInterval = null;
        
        // Handle backup button click
        backupBtn.addEventListener('click', function() {
            if (backupInProgress) {
                alert('A backup is already in progress!');
                return;
            }
            
            // Confirm backup
            if (!confirm('Are you sure you want to create a full backup? This may take some time.')) {
                return;
            }
            
            // Start backup process
            backupInProgress = true;
            startBackup();
        });
        
        // Function to start backup
        function startBackup() {
            // Show progress bar
            progressContainer.style.display = 'block';
            backupBtn.disabled = true;
            
            // Reset progress
            progressBar.style.width = '0%';
            progressText.textContent = '0%';
            currentActivity.textContent = 'Initializing backup...';
            currentFile.textContent = '';
            
            // Make AJAX request to start backup
            fetch('create-backup.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=start&csrf_token=${encodeURIComponent(csrfToken)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Start the actual backup process
                    runBackup();
                } else {
                    handleBackupError(data.message || 'Failed to start backup');
                }
            })
            .catch(error => {
                handleBackupError('Error starting backup: ' + error.message);
            });
        }
        
        // Function to run the actual backup
        function runBackup() {
            // Start status check loop
            statusCheckInterval = setInterval(checkBackupStatus, 500);
            
            // Make AJAX request to run backup
            fetch('create-backup.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=run&csrf_token=${encodeURIComponent(csrfToken)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    handleBackupComplete(data);
                } else {
                    handleBackupError(data.message || 'Backup failed');
                }
            })
            .catch(error => {
                handleBackupError('Error during backup: ' + error.message);
            });
        }
        
        // Function to check backup status
        function checkBackupStatus() {
            fetch('create-backup.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=status&csrf_token=${encodeURIComponent(csrfToken)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.status) {
                    updateProgressUI(data.status);
                    
                    // Check if backup is complete or failed
                    if (data.status.status === 'complete' || data.status.status === 'failed') {
                        clearInterval(statusCheckInterval);
                        
                        if (data.status.status === 'complete') {
                            // Add a delay before refreshing to show 100% for a moment
                            setTimeout(() => {
                                location.reload();
                            }, 2000);
                        } else {
                            backupBtn.disabled = false;
                            backupInProgress = false;
                        }
                    }
                }
            })
            .catch(error => {
                console.error('Error checking backup status:', error);
            });
        }
        
        // Function to update the progress UI
        function updateProgressUI(status) {
            const progress = status.progress || 0;
            progressBar.style.width = progress + '%';
            progressText.textContent = progress + '%';
            
            // Update activity and file if available
            if (status.message) {
                currentActivity.textContent = status.message;
            }
            
            if (status.current_file) {
                currentFile.textContent = status.current_file;
            } else {
                currentFile.textContent = '';
            }
        }
        
        // Function to handle backup completion
        function handleBackupComplete(data) {
            clearInterval(statusCheckInterval);
            
            // Update UI to reflect completion
            progressBar.style.width = '100%';
            progressText.textContent = '100%';
            currentActivity.textContent = data.message || 'Backup completed successfully!';
            currentFile.textContent = '';
            
            // Add a delay before refreshing the page to show the completed progress
            setTimeout(() => {
                location.reload();
            }, 2000);
        }
        
        // Function to handle backup error
        function handleBackupError(errorMessage) {
            clearInterval(statusCheckInterval);
            
            // Update UI to reflect error
            progressBar.style.width = '0%';
            progressBar.classList.add('error');
            progressText.textContent = 'Failed';
            currentActivity.textContent = errorMessage;
            currentFile.textContent = '';
            
            // Re-enable backup button
            backupBtn.disabled = false;
            backupInProgress = false;
        }
        
        // ----------------------------------------------------
        // Schedule Management Functions
        // ----------------------------------------------------
        
        // Schedule dialog elements
        const newScheduleBtn = document.getElementById('new-schedule-btn');
        const scheduleDialog = document.getElementById('schedule-dialog');
        const scheduleForm = document.getElementById('schedule-form');
        const closeBtn = scheduleDialog ? scheduleDialog.querySelector('.close') : null;
        
        // Setup event listeners for schedule dialog
        if (newScheduleBtn) {
            newScheduleBtn.addEventListener('click', openNewScheduleDialog);
        }
        
        if (closeBtn) {
            closeBtn.addEventListener('click', closeScheduleDialog);
        }
        
        // Close modal when clicking outside content
        if (scheduleDialog) {
            scheduleDialog.addEventListener('click', function(event) {
                if (event.target === scheduleDialog) {
                    closeScheduleDialog();
                }
            });
        }
        
        // Update preview when form values change
        document.getElementById('frequency').addEventListener('change', updateSchedulePreview);
        document.getElementById('time').addEventListener('change', updateSchedulePreview);
        document.getElementById('day_of_week').addEventListener('change', updateSchedulePreview);
        document.getElementById('day_of_month').addEventListener('change', updateSchedulePreview);
    });
    
    // Function to open the new schedule dialog
    function openNewScheduleDialog() {
        // Reset form
        document.getElementById('schedule-form-title').textContent = 'New Scheduled Backup';
        document.getElementById('schedule_id').value = '';
        document.getElementById('schedule_name').value = 'Nightly Backup';
        document.getElementById('frequency').value = 'daily';
        document.getElementById('time').value = '01:00';
        document.getElementById('day_of_week').value = '1';
        document.getElementById('day_of_month').value = '1';
        document.getElementById('enabled').checked = true;
        
        // Update fields visibility
        updateScheduleFields();
        
        // Update preview text
        updateSchedulePreview();
        
        // Show dialog
        document.getElementById('schedule-dialog').style.display = 'block';
    }
    
    // Function to edit an existing schedule
    function editSchedule(scheduleId) {
        // Fetch schedule data
        const schedules = <?php echo json_encode($scheduledBackups); ?>;
        const schedule = schedules[scheduleId];
        
        if (!schedule) {
            alert('Schedule not found');
            return;
        }
        
        // Update form title
        document.getElementById('schedule-form-title').textContent = 'Edit Scheduled Backup';
        
        // Fill in form values
        document.getElementById('schedule_id').value = scheduleId;
        document.getElementById('schedule_name').value = schedule.name || '';
        document.getElementById('frequency').value = schedule.frequency || 'daily';
        document.getElementById('time').value = schedule.time || '00:00';
        document.getElementById('enabled').checked = schedule.enabled || false;
        
        // Set frequency-specific fields
        if (schedule.frequency === 'weekly' && schedule.day_of_week !== undefined) {
            document.getElementById('day_of_week').value = schedule.day_of_week;
        }
        
        if (schedule.frequency === 'monthly' && schedule.day_of_month !== undefined) {
            document.getElementById('day_of_month').value = schedule.day_of_month;
        }
        
        // Update fields visibility
        updateScheduleFields();
        
        // Update preview text
        updateSchedulePreview();
        
        // Show dialog
        document.getElementById('schedule-dialog').style.display = 'block';
    }
    
    // Function to close the schedule dialog
    function closeScheduleDialog() {
        document.getElementById('schedule-dialog').style.display = 'none';
    }
    
    // Function to update field visibility based on frequency
    function updateScheduleFields() {
        const frequency = document.getElementById('frequency').value;
        
        // Show/hide day of week field
        const dayOfWeekGroup = document.getElementById('day-of-week-group');
        dayOfWeekGroup.style.display = frequency === 'weekly' ? 'block' : 'none';
        
        // Show/hide day of month field
        const dayOfMonthGroup = document.getElementById('day-of-month-group');
        dayOfMonthGroup.style.display = frequency === 'monthly' ? 'block' : 'none';
        
        // Update the preview text
        updateSchedulePreview();
    }
    
    // Function to update the schedule preview text
    function updateSchedulePreview() {
        const frequency = document.getElementById('frequency').value;
        const time = document.getElementById('time').value;
        let previewText = '';
        
        if (frequency === 'daily') {
            previewText = `every day at ${time}`;
        }
        else if (frequency === 'weekly') {
            const dayOfWeek = document.getElementById('day_of_week');
            const dayName = dayOfWeek.options[dayOfWeek.selectedIndex].text;
            previewText = `every ${dayName} at ${time}`;
        }
        else if (frequency === 'monthly') {
            const dayOfMonth = document.getElementById('day_of_month').value;
            let suffix = 'th';
            if (dayOfMonth == 1 || dayOfMonth == 21) suffix = 'st';
            else if (dayOfMonth == 2 || dayOfMonth == 22) suffix = 'nd';
            else if (dayOfMonth == 3 || dayOfMonth == 23) suffix = 'rd';
            previewText = `on the ${dayOfMonth}${suffix} of each month at ${time}`;
        }
        
        document.getElementById('schedule-preview-text').textContent = previewText;
    }
    </script>
</body>
</html>
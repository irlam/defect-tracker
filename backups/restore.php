<?php
session_start();
require_once 'auth.php';
require_once 'config.php';
require_once 'functions.php';
require_once 'backup-manager.php';

$backupManager = new BackupManager();
$message = '';
$messageType = '';

// Check if backup file is specified
if (!isset($_GET['file']) || empty($_GET['file'])) {
    header('Location: index.php');
    exit;
}

$backupFile = $_GET['file'];
$backupPath = BACKUP_DIR . '/' . $backupFile;

// Validate backup file exists
if (!file_exists($backupPath)) {
    $message = "Backup file not found";
    $messageType = 'error';
    header('Location: index.php');
    exit;
}

// Handle restore file request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'restore_file') {
    verify_csrf_token($_POST['csrf_token']);
    if (isset($_POST['file_to_restore'])) {
        $fileToRestore = $_POST['file_to_restore'];
        $result = $backupManager->restoreFile($backupFile, $fileToRestore);
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';
    }
}

// Handle restore database request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'restore_database') {
    verify_csrf_token($_POST['csrf_token']);
    $result = $backupManager->restoreDatabase($backupFile);
    $message = $result['message'];
    $messageType = $result['success'] ? 'success' : 'error';
}

// Handle restore full backup request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'restore_full') {
    verify_csrf_token($_POST['csrf_token']);
    $result = $backupManager->restoreFullBackup($backupFile);
    $message = $result['message'];
    $messageType = $result['success'] ? 'success' : 'error';
}

// Get list of files in the backup
$files = list_backup_files($backupPath);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restore Backup - <?php echo htmlspecialchars($backupFile); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Restore from Backup: <?php echo htmlspecialchars($backupFile); ?></h1>
            <p>Current user: <?php echo htmlspecialchars(CURRENT_USER); ?></p>
            <p>Current time: <?php echo date('Y-m-d H:i:s'); ?></p>
            <p><a href="index.php">&laquo; Back to Backup Manager</a></p>
        </header>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <section class="actions">
            <h2>Restore Actions</h2>
            <div class="warning">
                <p><strong>Warning:</strong> Restoring files or database will overwrite existing data. Make sure you know what you're doing!</p>
            </div>
            
            <form method="post" action="restore.php?file=<?php echo urlencode($backupFile); ?>" onsubmit="return confirm('Are you sure you want to restore the entire database? This will overwrite all current data!');">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="action" value="restore_database">
                <button type="submit" class="btn btn-danger">Restore Database</button>
            </form>
            
            <form method="post" action="restore.php?file=<?php echo urlencode($backupFile); ?>" onsubmit="return confirm('Are you sure you want to restore the entire backup? This will overwrite your current website!');">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="action" value="restore_full">
                <button type="submit" class="btn btn-danger">Restore Full Backup</button>
            </form>
        </section>
        
        <section class="files">
            <h2>Files in Backup</h2>
            <div class="file-search">
                <input type="text" id="file-search" placeholder="Search files...">
            </div>
            
            <?php if (empty($files)): ?>
                <p>No files found in backup.</p>
            <?php else: ?>
                <table id="files-table">
                    <thead>
                        <tr>
                            <th>Filename</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($files as $file): 
                            // Skip database directory
                            if (strpos($file, 'database/') === 0) continue;
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($file); ?></td>
                            <td>
                                <form method="post" action="restore.php?file=<?php echo urlencode($backupFile); ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                    <input type="hidden" name="action" value="restore_file">
                                    <input type="hidden" name="file_to_restore" value="<?php echo htmlspecialchars($file); ?>">
                                    <button type="submit" class="btn btn-small">Restore This File</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('file-search');
        const table = document.getElementById('files-table');
        
        if (searchInput && table) {
            searchInput.addEventListener('keyup', function() {
                const searchTerm = this.value.toLowerCase();
                const rows = table.getElementsByTagName('tr');
                
                for (let i = 1; i < rows.length; i++) {
                    const fileName = rows[i].getElementsByTagName('td')[0].textContent.toLowerCase();
                    if (fileName.includes(searchTerm)) {
                        rows[i].style.display = '';
                    } else {
                        rows[i].style.display = 'none';
                    }
                }
            });
        }
    });
    </script>
    <div style="margin-top: 20px; padding: 10px; background: #f8f9fa; border: 1px solid #ddd; border-radius: 4px; display: none;" id="debug-info">
        <h3>Debug Information</h3>
        <p><strong>Backup File Path:</strong> <?php echo htmlspecialchars($backupFilePath); ?></p>
        <p><strong>File Exists:</strong> <?php echo file_exists($backupFilePath) ? 'Yes' : 'No'; ?></p>
        <p><strong>Script Directory:</strong> <?php echo htmlspecialchars(__DIR__); ?></p>
        <p><strong>Current User:</strong> <?php echo htmlspecialchars('irlam'); ?></p>
        <p><strong>Date/Time:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
    </div>
    
    <script>
        // Add a debug button to show/hide debug info
        document.addEventListener('DOMContentLoaded', function() {
            const footer = document.querySelector('footer');
            if (footer) {
                const debugBtn = document.createElement('button');
                debugBtn.textContent = 'Show Debug Info';
                debugBtn.style.marginLeft = '10px';
                debugBtn.onclick = function() {
                    const debugInfo = document.getElementById('debug-info');
                    if (debugInfo.style.display === 'none') {
                        debugInfo.style.display = 'block';
                        debugBtn.textContent = 'Hide Debug Info';
                    } else {
                        debugInfo.style.display = 'none';
                        debugBtn.textContent = 'Show Debug Info';
                    }
                };
                footer.appendChild(debugBtn);
            }
        });
    </script>
</body>
</html>
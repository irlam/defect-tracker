<?php
/**
 * McGoff Backup Manager - Restore Path Fix
 * 
 * This file fixes the issue with the restore.php file where the backup file path
 * is not being correctly identified, resulting in "Backup file not found" errors.
 * 
 * Current Date: 2025-02-28 12:54:56
 * Author: irlam
 */

// Define path to the restore.php file
$restorePath = __DIR__ . '/restore.php';

// Check if the file exists
if (!file_exists($restorePath)) {
    die("Error: restore.php file not found!");
}

// Backup the original file
copy($restorePath, $restorePath . '.bak.' . date('Ymd-His'));

// Get the content of the file
$content = file_get_contents($restorePath);

// Find where the backup file path is determined
$problematicCode = <<<'EOD'
// Get the backup file path
$backupFile = $_GET['file'] ?? '';
if (empty($backupFile)) {
    $message = "No backup file specified";
    $messageType = "error";
} else {
    $backupFilePath = $backupFile;
EOD;

// Replace with corrected code
$correctedCode = <<<'EOD'
// Get the backup file path
$backupFile = $_GET['file'] ?? '';
if (empty($backupFile)) {
    $message = "No backup file specified";
    $messageType = "error";
} else {
    // Make sure we have the full path to the backup file
    $backupDir = dirname(__DIR__) . '/backups/files/';
    $backupFilePath = $backupDir . basename($backupFile);
    
    // If the file doesn't exist in the expected location, try other common locations
    if (!file_exists($backupFilePath)) {
        $alternativePaths = [
            __DIR__ . '/files/' . basename($backupFile),
            __DIR__ . '/backups/' . basename($backupFile),
            __DIR__ . '/' . basename($backupFile)
        ];
        
        foreach ($alternativePaths as $altPath) {
            if (file_exists($altPath)) {
                $backupFilePath = $altPath;
                break;
            }
        }
    }
EOD;

// Find code for handling file restore requests
$fileRestoreCode = <<<'EOD'
// Handle file restore request
if (isset($_GET['restore_file']) && !empty($_GET['file'])) {
    $fileToRestore = $_GET['restore_file'];
    
    $result = $backupManager->restoreFile($backupFilePath, $fileToRestore);
    
    if ($result['success']) {
        $message = $result['message'];
        $messageType = "success";
    } else {
        $message = "Failed to restore file: " . $result['message'];
        $messageType = "error";
    }
}
EOD;

// Replace with improved code
$improvedRestoreCode = <<<'EOD'
// Handle file restore request
if (isset($_GET['restore_file']) && !empty($_GET['file'])) {
    $fileToRestore = $_GET['restore_file'];
    
    if (!file_exists($backupFilePath)) {
        $message = "Backup file not found: " . htmlspecialchars($backupFilePath);
        $messageType = "error";
    } else {
        $result = $backupManager->restoreFile($backupFilePath, $fileToRestore);
        
        if ($result['success']) {
            $message = $result['message'];
            $messageType = "success";
        } else {
            $message = "Failed to restore file: " . $result['message'];
            $messageType = "error";
        }
    }
}
EOD;

// Perform the replacements
$content = str_replace($problematicCode, $correctedCode, $content);
$content = str_replace($fileRestoreCode, $improvedRestoreCode, $content);

// Add debug output at the bottom of the HTML to help troubleshoot if needed
$debugCode = <<<'EOD'
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
EOD;

// Find the closing </body></html> tags and replace with the debug code
$content = str_replace('</body>' . PHP_EOL . '</html>', $debugCode, $content);

// Save the updated file
if (file_put_contents($restorePath, $content)) {
    echo "✅ Success! The restore.php file has been fixed.<br>";
    echo "The original file has been backed up as: <code>restore.php.bak." . date('Ymd-His') . "</code><br>";
    echo "You can now try restoring files again.<br><br>";
    echo "If you still encounter issues, click the 'Show Debug Info' button at the bottom of the restore page to see detailed information about the backup file path.";
} else {
    echo "❌ Failed to update the file. Please check write permissions.";
}
?>
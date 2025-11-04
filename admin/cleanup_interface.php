<?php
/**
 * Website Cleanup Interface
 * 
 * Web interface for running the cleanup script with safety measures.
 * This page allows admins to clean the website and create a fresh backup.
 * 
 * Created: 2025-11-04
 */

require_once '../config/database.php';
require_once '../includes/session.php';

// Check if user is admin
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'cleanup') {
        // Verify confirmation code
        if (!isset($_POST['confirmation']) || $_POST['confirmation'] !== 'DELETE ALL DATA') {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid confirmation code'
            ]);
            exit();
        }
        
        // Start output buffering to capture the cleanup script output
        ob_start();
        
        // Execute cleanup
        $_SESSION['executing_cleanup'] = true;
        require __DIR__ . '/cleanup_website.php';
        unset($_SESSION['executing_cleanup']);
        
        $output = ob_get_clean();
        
        echo json_encode([
            'success' => true,
            'message' => 'Cleanup completed successfully',
            'output' => $output
        ]);
        exit();
    }
    
    if ($_POST['action'] === 'create_backup') {
        // Create a backup after cleanup
        try {
            require_once __DIR__ . '/../backups/backup-manager.php';
            $backupManager = new BackupManager();
            $result = $backupManager->createFullBackup();
            
            echo json_encode($result);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Backup failed: ' . $e->getMessage()
            ]);
        }
        exit();
    }
}

// Get page title
$pageTitle = "Website Cleanup";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - McGoff Defect Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        .warning-box {
            background-color: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .danger-box {
            background-color: #f8d7da;
            border: 2px solid #dc3545;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .success-box {
            background-color: #d1e7dd;
            border: 2px solid #198754;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .output-box {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 15px;
            font-family: monospace;
            white-space: pre-wrap;
            max-height: 400px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="../dashboard.php">McGoff Defect Tracker</a>
            <span class="navbar-text text-white">
                <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
            </span>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h1><i class="bi bi-trash3"></i> Website Cleanup</h1>
                <p class="lead">Clean all user-generated data and create a fresh backup template</p>
                
                <div class="danger-box">
                    <h4><i class="bi bi-exclamation-triangle-fill text-danger"></i> DANGER ZONE</h4>
                    <p><strong>This operation will permanently delete:</strong></p>
                    <ul>
                        <li>All defects and related data (images, comments, history)</li>
                        <li>All projects</li>
                        <li>All floor plans and uploaded files</li>
                        <li>All contractors (except those linked to admin)</li>
                        <li>All non-admin users</li>
                        <li>All logs and notifications</li>
                        <li>All activity history</li>
                    </ul>
                    <p class="mb-0"><strong>What will be preserved:</strong></p>
                    <ul class="mb-0">
                        <li>Admin account (username: irlam)</li>
                        <li>System configuration and settings</li>
                        <li>Database structure</li>
                        <li>System roles and permissions</li>
                    </ul>
                </div>

                <div class="warning-box">
                    <h5><i class="bi bi-lightbulb-fill text-warning"></i> Recommended Workflow</h5>
                    <ol>
                        <li><strong>Create a full backup</strong> before running this cleanup (use the Backup Manager)</li>
                        <li><strong>Run the cleanup</strong> to remove all user-generated data</li>
                        <li><strong>Create a fresh backup</strong> that can be used as a template for new installations</li>
                    </ol>
                </div>

                <div class="card mt-4">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0"><i class="bi bi-shield-exclamation"></i> Cleanup Confirmation</h5>
                    </div>
                    <div class="card-body">
                        <form id="cleanupForm">
                            <div class="mb-3">
                                <label for="confirmationCode" class="form-label">
                                    Type <code>DELETE ALL DATA</code> to confirm:
                                </label>
                                <input 
                                    type="text" 
                                    class="form-control" 
                                    id="confirmationCode" 
                                    name="confirmation"
                                    placeholder="DELETE ALL DATA"
                                    required
                                >
                            </div>
                            
                            <div class="form-check mb-3">
                                <input 
                                    class="form-check-input" 
                                    type="checkbox" 
                                    id="backupConfirm"
                                    required
                                >
                                <label class="form-check-label" for="backupConfirm">
                                    I confirm that I have created a full backup of the current website
                                </label>
                            </div>
                            
                            <div class="form-check mb-3">
                                <input 
                                    class="form-check-input" 
                                    type="checkbox" 
                                    id="understandConfirm"
                                    required
                                >
                                <label class="form-check-label" for="understandConfirm">
                                    I understand that this action cannot be undone
                                </label>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-danger btn-lg" id="cleanupBtn">
                                    <i class="bi bi-trash3"></i> Run Cleanup
                                </button>
                                <a href="../dashboard.php" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left"></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Results Section -->
                <div id="resultsSection" class="mt-4" style="display: none;">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-list-check"></i> Cleanup Results</h5>
                        </div>
                        <div class="card-body">
                            <div id="resultsOutput" class="output-box"></div>
                        </div>
                    </div>

                    <div class="success-box mt-3">
                        <h5><i class="bi bi-check-circle-fill text-success"></i> Next Steps</h5>
                        <p>The website has been cleaned successfully. You can now:</p>
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-success" id="createBackupBtn">
                                <i class="bi bi-save"></i> Create Fresh Backup Template
                            </button>
                            <a href="../backups/index.php" class="btn btn-primary">
                                <i class="bi bi-folder"></i> Go to Backup Manager
                            </a>
                            <a href="../dashboard.php" class="btn btn-secondary">
                                <i class="bi bi-house"></i> Return to Dashboard
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Backup Progress Section -->
                <div id="backupSection" class="mt-4" style="display: none;">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="bi bi-save"></i> Creating Backup</h5>
                        </div>
                        <div class="card-body">
                            <div class="progress mb-3">
                                <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                     role="progressbar" 
                                     style="width: 100%" 
                                     id="backupProgress">
                                    Creating backup...
                                </div>
                            </div>
                            <div id="backupOutput" class="output-box"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('cleanupForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const confirmationCode = document.getElementById('confirmationCode').value;
            if (confirmationCode !== 'DELETE ALL DATA') {
                alert('Please type "DELETE ALL DATA" exactly as shown.');
                return;
            }
            
            const btn = document.getElementById('cleanupBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Running cleanup...';
            
            try {
                const formData = new FormData();
                formData.append('action', 'cleanup');
                formData.append('confirmation', confirmationCode);
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    document.getElementById('resultsOutput').textContent = result.output;
                    document.getElementById('resultsSection').style.display = 'block';
                    document.getElementById('cleanupForm').style.display = 'none';
                    
                    // Scroll to results
                    document.getElementById('resultsSection').scrollIntoView({ behavior: 'smooth' });
                } else {
                    alert('Cleanup failed: ' + result.message);
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-trash3"></i> Run Cleanup';
                }
            } catch (error) {
                alert('Error: ' + error.message);
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-trash3"></i> Run Cleanup';
            }
        });
        
        document.getElementById('createBackupBtn').addEventListener('click', async function() {
            const btn = this;
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Creating backup...';
            
            document.getElementById('backupSection').style.display = 'block';
            document.getElementById('backupSection').scrollIntoView({ behavior: 'smooth' });
            
            try {
                const formData = new FormData();
                formData.append('action', 'create_backup');
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                const output = document.getElementById('backupOutput');
                if (result.success) {
                    output.textContent = '✓ Backup created successfully!\n\nFilename: ' + result.file + '\n\nYou can now use this backup as a fresh template.';
                    document.querySelector('#backupProgress').classList.remove('progress-bar-animated');
                    document.querySelector('#backupProgress').style.width = '100%';
                    document.querySelector('#backupProgress').classList.add('bg-success');
                    document.querySelector('#backupProgress').textContent = 'Complete';
                } else {
                    output.textContent = '✗ Backup failed: ' + result.message;
                    document.querySelector('#backupProgress').classList.remove('progress-bar-animated');
                    document.querySelector('#backupProgress').classList.add('bg-danger');
                    document.querySelector('#backupProgress').textContent = 'Failed';
                }
            } catch (error) {
                document.getElementById('backupOutput').textContent = '✗ Error: ' + error.message;
                document.querySelector('#backupProgress').classList.remove('progress-bar-animated');
                document.querySelector('#backupProgress').classList.add('bg-danger');
                document.querySelector('#backupProgress').textContent = 'Failed';
            }
            
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-save"></i> Create Fresh Backup Template';
        });
    </script>
</body>
</html>

<?php
// admin/system_settings.php
require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../classes/RBAC.php';
require_once '../classes/Logger.php';
require_once '../classes/SystemHealth.php';
require_once '../classes/BackupManager.php';

// Initialize required objects
$database = new Database();
$db = $database->getConnection();
$rbac = new RBAC($db, 'irlam', '2025-01-14 21:35:47');
$logger = new Logger($db, 'irlam', '2025-01-14 21:35:47');
$systemHealth = new SystemHealth($db);
$backupManager = new BackupManager($db);

// Check admin privileges
if (!$rbac->hasPermission($_SESSION['user_id'], 'manage_system')) {
    header('Location: ../dashboard.php');
    exit();
}

// Fetch current system configurations
$stmt = $db->query("
    SELECT id, config_key, config_value, description, category, is_encrypted, updated_at 
    FROM system_configurations 
    ORDER BY category, config_key
");
$configurations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch email templates
$stmt = $db->query("
    SELECT id, name, subject, body, variables, is_active, updated_at 
    FROM email_templates 
    ORDER BY name
");
$emailTemplates = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get system health metrics
$healthMetrics = $systemHealth->getMetrics();

// Get recent backups
$backups = glob('../backups/*.sql');
rsort($backups); // Sort by newest first
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - Construction Defect Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/codemirror@5.65.2/lib/codemirror.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/codemirror@5.65.2/theme/monokai.css" rel="stylesheet">
    <style>
        .health-indicator {
            width: 15px;
            height: 15px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }
        .health-good { background-color: #198754; }
        .health-warning { background-color: #ffc107; }
        .health-critical { background-color: #dc3545; }
        .config-card { margin-bottom: 20px; }
        .email-template-editor { height: 400px; }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <nav class="col-md-3 col-lg-2 d-md-block bg-light sidebar">
                <?php include '../includes/sidebar.php'; ?>
            </nav>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">System Settings</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-success me-2" onclick="createBackup()">
                            <i class='bx bx-download'></i> Create Backup
                        </button>
                    </div>
                </div>

                <!-- System Health Dashboard -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">System Health</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="d-flex align-items-center mb-3">
                                            <span class="health-indicator <?php echo $systemHealth->checkDatabaseConnection() ? 'health-good' : 'health-critical'; ?>"></span>
                                            <span>Database Connection</span>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="d-flex align-items-center mb-3">
                                            <span class="health-indicator <?php echo $systemHealth->checkDiskSpace() ? 'health-good' : 'health-warning'; ?>"></span>
                                            <span>Disk Space</span>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="d-flex align-items-center mb-3">
                                            <span class="health-indicator <?php echo $systemHealth->checkSystemLoad() ? 'health-good' : 'health-warning'; ?>"></span>
                                            <span>System Load</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Configuration Sections -->
                <div class="row">
                    <!-- System Configuration -->
                    <div class="col-md-6">
                        <div class="card config-card">
                            <div class="card-header">
                                <h5 class="mb-0">System Configuration</h5>
                            </div>
                            <div class="card-body">
                                <form id="systemConfigForm">
                                    <?php foreach ($configurations as $config): ?>
                                        <div class="mb-3">
                                            <label class="form-label"><?php echo ucwords(str_replace('_', ' ', $config['config_key'])); ?></label>
                                            <?php if (is_json($config['config_value'])): ?>
                                                <textarea class="form-control" name="<?php echo $config['config_key']; ?>" rows="3"><?php echo json_encode(json_decode($config['config_value']), JSON_PRETTY_PRINT); ?></textarea>
                                            <?php else: ?>
                                                <input type="text" class="form-control" name="<?php echo $config['config_key']; ?>" value="<?php echo htmlspecialchars($config['config_value']); ?>">
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                    <button type="submit" class="btn btn-primary">Save Configuration</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Email Templates -->
                    <div class="col-md-6">
                        <div class="card config-card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Email Templates</h5>
                                <button class="btn btn-primary btn-sm" onclick="addEmailTemplate()">
                                    <i class='bx bx-plus'></i> Add Template
                                </button>
                            </div>
                            <div class="card-body">
                                <select class="form-select mb-3" id="templateSelector">
                                    <option value="">Select Template</option>
                                    <?php foreach ($emailTemplates as $template): ?>
                                        <option value="<?php echo $template['id']; ?>"><?php echo htmlspecialchars($template['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div id="templateEditor" style="display: none;">
                                    <form id="emailTemplateForm">
                                        <input type="hidden" name="template_id" id="templateId">
                                        <div class="mb-3">
                                            <label class="form-label">Subject</label>
                                            <input type="text" class="form-control" name="subject" id="templateSubject">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Body</label>
                                            <textarea class="form-control email-template-editor" name="body" id="templateBody"></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Variables</label>
                                            <input type="text" class="form-control" name="variables" id="templateVariables">
                                        </div>
                                        <button type="submit" class="btn btn-primary">Save Template</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Backup Management -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Backup Management</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Backup File</th>
                                                <th>Size</th>
                                                <th>Created At</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($backups as $backup): ?>
                                                <tr>
                                                    <td><?php echo basename($backup); ?></td>
                                                    <td><?php echo formatBytes(filesize($backup)); ?></td>
                                                    <td><?php echo date('d-m-Y H:i:s', filemtime($backup)); ?></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-primary" onclick="downloadBackup('<?php echo basename($backup); ?>')">
                                                            <i class='bx bx-download'></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-warning" onclick="restoreBackup('<?php echo basename($backup); ?>')">
                                                            <i class='bx bx-reset'></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-danger" onclick="deleteBackup('<?php echo basename($backup); ?>')">
                                                            <i class='bx bx-trash'></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
<script>
// Continuing from the deleteBackup function...
                filename: filename,
                deleted_by: 'irlam',
                deleted_at: '2025-01-14 21:42:06'
            })
        });
        
        const result = await response.json();
        if (result.success) {
            showNotification('Backup deleted successfully', 'success');
            location.reload();
        } else {
            showNotification('Error deleting backup: ' + result.message, 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('Error deleting backup', 'error');
    }
}

// System Health Monitoring
function initializeSystemMonitoring() {
    const ctx = document.getElementById('systemMetricsChart').getContext('2d');
    const systemMetricsChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                label: 'CPU Usage',
                borderColor: '#007bff',
                data: []
            }, {
                label: 'Memory Usage',
                borderColor: '#28a745',
                data: []
            }, {
                label: 'Disk Usage',
                borderColor: '#dc3545',
                data: []
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });

    // Update metrics every 30 seconds
    updateSystemMetrics(systemMetricsChart);
    setInterval(() => updateSystemMetrics(systemMetricsChart), 30000);
}

async function updateSystemMetrics(chart) {
    try {
        const response = await fetch('../api/get_system_metrics.php');
        const metrics = await response.json();
        
        if (metrics.success) {
            // Update chart data
            const timestamp = new Date().toLocaleTimeString();
            chart.data.labels.push(timestamp);
            chart.data.datasets[0].data.push(metrics.data.cpu_usage);
            chart.data.datasets[1].data.push(metrics.data.memory_usage);
            chart.data.datasets[2].data.push(metrics.data.disk_usage);
            
            // Keep only last 20 data points
            if (chart.data.labels.length > 20) {
                chart.data.labels.shift();
                chart.data.datasets.forEach(dataset => dataset.data.shift());
            }
            
            chart.update();

            // Update health indicators
            updateHealthIndicators(metrics.data);
        }
    } catch (error) {
        console.error('Error updating metrics:', error);
    }
}

function updateHealthIndicators(metrics) {
    const indicators = {
        database: document.getElementById('dbHealthIndicator'),
        disk: document.getElementById('diskHealthIndicator'),
        system: document.getElementById('systemHealthIndicator')
    };

    // Update database health indicator
    indicators.database.className = `health-indicator health-${metrics.database_status}`;
    
    // Update disk health indicator
    indicators.disk.className = `health-indicator health-${metrics.disk_status}`;
    
    // Update system health indicator
    indicators.system.className = `health-indicator health-${metrics.system_status}`;
}

// Notification System
function showNotification(message, type = 'info') {
    const notificationDiv = document.createElement('div');
    notificationDiv.className = `alert alert-${type} alert-dismissible fade show notification-toast`;
    notificationDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.getElementById('notificationContainer').appendChild(notificationDiv);
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        notificationDiv.remove();
    }, 5000);
}

// Real-time System Status Updates
const systemStatus = {
    wsConnection: null,
    connect: function() {
        this.wsConnection = new WebSocket('ws://your-server/system-status');
        
        this.wsConnection.onmessage = function(event) {
            const status = JSON.parse(event.data);
            updateStatusDisplay(status);
        };

        this.wsConnection.onclose = function() {
            // Attempt to reconnect after 5 seconds
            setTimeout(() => systemStatus.connect(), 5000);
        };
    }
};

function updateStatusDisplay(status) {
    // Update various system status indicators
    document.getElementById('activeUsers').textContent = status.active_users;
    document.getElementById('systemLoad').textContent = status.system_load.toFixed(2);
    document.getElementById('memoryUsage').textContent = `${status.memory_usage}%`;
    document.getElementById('diskUsage').textContent = `${status.disk_usage}%`;
}

// Email Template Preview
function previewTemplate() {
    const templateData = {
        subject: document.getElementById('templateSubject').value,
        body: templateEditor.getValue(),
        variables: JSON.parse(document.getElementById('templateVariables').value)
    };

    const previewWindow = window.open('', 'Template Preview', 'width=600,height=400');
    previewWindow.document.write(`
        <html>
            <head>
                <title>Email Template Preview</title>
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
            </head>
            <body class="p-3">
                <h4>${templateData.subject}</h4>
                <hr>
                ${templateData.body}
            </body>
        </html>
    `);
}

// Initialize everything when the page loads
document.addEventListener('DOMContentLoaded', function() {
    // Initialize all tooltips
    const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltips.forEach(tooltip => new bootstrap.Tooltip(tooltip));

    // Connect to real-time system status
    systemStatus.connect();

    // Initialize system monitoring
    initializeSystemMonitoring();

    // Add event listeners for email template preview
    document.getElementById('previewTemplateBtn')?.addEventListener('click', previewTemplate);
});
</script>
</body>
</html>
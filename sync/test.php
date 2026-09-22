<?php
// Include the sync initialization
require_once __DIR__ . '/init.php';

// Database connection for viewing results
try {
    $db = new PDO('mysql:host=localhost;dbname=your_database', 'username', 'password');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// Current time for reference
$currentTime = date('d-m-Y H:i:s'); // 2025-02-26 07:51:20
$currentUser = 'irlam'; // Current user
?>

<!DOCTYPE html>
<html>
<head>
    <title>Offline Sync Test</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1000px; margin: 0 auto; padding: 20px; }
        .card { border: 1px solid #ddd; border-radius: 4px; padding: 15px; margin-bottom: 20px; }
        .controls { display: flex; gap: 10px; margin-bottom: 20px; }
        button { padding: 8px 16px; cursor: pointer; }
        .status-panel { background: #f5f5f5; padding: 10px; margin-bottom: 20px; }
        textarea { width: 100%; height: 100px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .tabs { display: flex; margin-bottom: 20px; }
        .tab { padding: 10px 15px; cursor: pointer; border: 1px solid #ddd; background: #f5f5f5; }
        .tab.active { background: #007bff; color: white; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
    </style>
    <?php sync_init_client(); ?>
</head>
<body>
    <h1>Offline Sync Testing Tool</h1>
    <p>Current Time: <?php echo $currentTime; ?> | User: <?php echo $currentUser; ?></p>
    
    <div class="status-panel">
        <h3>Network Status</h3>
        <div id="network-status">Checking...</div>
        <div id="sync-status-display">Sync Status: Checking...</div>
    </div>
    
    <div class="controls">
        <button id="toggle-network">Simulate Offline</button>
        <button id="force-sync">Force Sync Now</button>
        <button id="clear-data">Clear Local Data</button>
    </div>
    
    <div class="tabs">
        <div class="tab active" data-tab="create">Add Defect</div>
        <div class="tab" data-tab="view">View Local Data</div>
        <div class="tab" data-tab="server">Server Data</div>
        <div class="tab" data-tab="queue">Sync Queue</div>
        <div class="tab" data-tab="logs">Debug Logs</div>
    </div>
    
    <div class="tab-content active" id="create-tab">
        <div class="card">
            <h2>Create Test Defect</h2>
            <form id="defect-form">
                <div>
                    <label>Title:</label>
                    <input type="text" id="title" required>
                </div>
                <div>
                    <label>Description:</label>
                    <textarea id="description"></textarea>
                </div>
                <div>
                    <label>Status:</label>
                    <select id="status">
                        <option value="new">New</option>
                        <option value="in_progress">In Progress</option>
                        <option value="resolved">Resolved</option>
                    </select>
                </div>
                <div>
                    <label>Screenshot:</label>
                    <input type="file" id="attachment" accept="image/*">
                </div>
                <div style="margin-top: 15px;">
                    <button type="submit">Save Defect</button>
                </div>
            </form>
        </div>
    </div>
    
    <div class="tab-content" id="view-tab">
        <div class="card">
            <h2>Local Database</h2>
            <div id="local-data">Loading...</div>
        </div>
    </div>
    
    <div class="tab-content" id="server-tab">
        <div class="card">
            <h2>Server Database</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Status</th>
                        <th>Created By</th>
                        <th>Created At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $db->query("SELECT * FROM defects ORDER BY id DESC LIMIT 20");
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['title']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['status']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['created_by']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['created_at']) . "</td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="tab-content" id="queue-tab">
        <div class="card">
            <h2>Sync Queue</h2>
            <div id="sync-queue">Loading...</div>
        </div>
    </div>
    
    <div class="tab-content" id="logs-tab">
        <div class="card">
            <h2>Debug Logs</h2>
            <div id="log-entries"></div>
            <button id="clear-logs">Clear Logs</button>
        </div>
    </div>

    <script>
        // Debug logger
        const logger = {
            logs: [],
            maxLogs: 100,
            
            log: function(message, data = null) {
                const entry = {
                    timestamp: new Date().toISOString(),
                    message: message,
                    data: data
                };
                
                this.logs.unshift(entry);
                if (this.logs.length > this.maxLogs) {
                    this.logs.pop();
                }
                
                this.render();
                
                // Also log to console
                console.log(message, data || '');
            },
            
            clear: function() {
                this.logs = [];
                this.render();
            },
            
            render: function() {
                const container = document.getElementById('log-entries');
                container.innerHTML = this.logs.map(entry => {
                    const time = entry.timestamp.split('T')[1].split('.')[0];
                    let dataStr = '';
                    
                    if (entry.data) {
                        if (typeof entry.data === 'string') {
                            dataStr = entry.data;
                        } else {
                            try {
                                dataStr = JSON.stringify(entry.data, null, 2);
                            } catch (e) {
                                dataStr = '[Object]';
                            }
                        }
                    }
                    
                    return `
                        <div class="log-entry">
                            <span class="log-time">[${time}]</span>
                            <span class="log-msg">${entry.message}</span>
                            ${dataStr ? `<pre>${dataStr}</pre>` : ''}
                        </div>
                    `;
                }).join('<hr>');
            }
        };
        
        // Network simulation
        let isOfflineSimulation = false;
        
        function updateNetworkStatus() {
            const status = isOfflineSimulation ? false : navigator.onLine;
            const statusEl = document.getElementById('network-status');
            
            if (status) {
                statusEl.textContent = 'ONLINE';
                statusEl.style.color = 'green';
            } else {
                statusEl.textContent = 'OFFLINE' + (isOfflineSimulation ? ' (Simulated)' : '');
                statusEl.style.color = 'red';
            }
        }
        
        // Load local data
        async function loadLocalData() {
            try {
                const defects = await window.dbManager.getAll('defects');
                const attachments = await window.dbManager.getAll('attachments');
                
                let html = '<h3>Defects (' + defects.length + ')</h3>';
                
                if (defects.length === 0) {
                    html += '<p>No local defects stored.</p>';
                } else {
                    html += '<table><thead><tr><th>ID</th><th>Server ID</th><th>Title</th><th>Status</th><th>Sync Status</th></tr></thead><tbody>';
                    
                    defects.forEach(defect => {
                        html += `<tr>
                            <td>${defect.id}</td>
                            <td>${defect.server_id || 'Not synced'}</td>
                            <td>${defect.title}</td>
                            <td>${defect.status}</td>
                            <td>${defect.sync_status}</td>
                        </tr>`;
                    });
                    
                    html += '</tbody></table>';
                }
                
                html += '<h3>Attachments (' + attachments.length + ')</h3>';
                
                if (attachments.length === 0) {
                    html += '<p>No local attachments stored.</p>';
                } else {
                    html += '<table><thead><tr><th>ID</th><th>Defect ID</th><th>File Name</th><th>Preview</th></tr></thead><tbody>';
                    
                    attachments.forEach(attachment => {
                        const preview = attachment.file_data ? 
                            `<img src="${attachment.file_data}" style="max-width: 100px; max-height: 100px;">` : 
                            'No preview';
                        
                        html += `<tr>
                            <td>${attachment.id}</td>
                            <td>${attachment.defect_id}</td>
                            <td>${attachment.file_name || 'Unnamed'}</td>
                            <td>${preview}</td>
                        </tr>`;
                    });
                    
                    html += '</tbody></table>';
                }
                
                document.getElementById('local-data').innerHTML = html;
                
            } catch (error) {
                logger.log('Error loading local data', error);
                document.getElementById('local-data').innerHTML = 
                    `<div class="error">Error loading data: ${error.message}</div>`;
            }
        }
        
        // Load sync queue
        async function loadSyncQueue() {
            try {
                const queue = await window.dbManager.getAll('sync_queue');
                
                let html = '<h3>Sync Queue Items (' + queue.length + ')</h3>';
                
                if (queue.length === 0) {
                    html += '<p>No pending sync items.</p>';
                } else {
                    html += '<table><thead><tr><th>ID</th><th>Action</th><th>Type</th><th>Status</th><th>Created</th><th>Attempts</th></tr></thead><tbody>';
                    
                    queue.forEach(item => {
                        html += `<tr>
                            <td>${item.id}</td>
                            <td>${item.action}</td>
                            <td>${item.type}</td>
                            <td>${item.status}</td>
                            <td>${new Date(item.timestamp).toLocaleString()}</td>
                            <td>${item.attempts || 0}</td>
                        </tr>`;
                    });
                    
                    html += '</tbody></table>';
                    
                    html += '<pre style="max-height: 200px; overflow: auto;">' + 
                        JSON.stringify(queue[0], null, 2) + '</pre>';
                }
                
                document.getElementById('sync-queue').innerHTML = html;
                
            } catch (error) {
                logger.log('Error loading sync queue', error);
                document.getElementById('sync-queue').innerHTML = 
                    `<div class="error">Error loading queue: ${error.message}</div>`;
            }
        }
        
        // Tab switching
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', () => {
                // Deactivate all tabs
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
                
                // Activate selected tab
                tab.classList.add('active');
                const tabId = tab.getAttribute('data-tab') + '-tab';
                document.getElementById(tabId).classList.add('active');
                
                // Refresh data when switching to certain tabs
                if (tabId === 'view-tab') loadLocalData();
                if (tabId === 'queue-tab') loadSyncQueue();
            });
        });
        
        // Handle form submission
        document.getElementById('defect-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const title = document.getElementById('title').value;
            const description = document.getElementById('description').value;
            const status = document.getElementById('status').value;
            const attachmentField = document.getElementById('attachment');
            let attachmentData = null;
            
            // Process the image file if one is selected
            if (attachmentField.files.length > 0) {
                const file = attachmentField.files[0];
                attachmentData = await new Promise(resolve => {
                    const reader = new FileReader();
                    reader.onload = e => resolve(e.target.result);
                    reader.readAsDataURL(file);
                });
            }
            
            logger.log('Creating new defect', { title, status });
            
            try {
                // Save the defect data
                const defectId = await window.dbManager.add('defects', {
                    title: title,
                    description: description,
                    status: status,
                });
                
                logger.log('Defect created locally', { local_id: defectId });
                
                // If there's an attachment, save it separately
                if (attachmentData) {
                    const attachmentId = await window.dbManager.add('attachments', {
                        defect_id: defectId,
                        file_data: attachmentData,
                        file_name: attachmentField.files[0].name,
                        file_type: attachmentField.files[0].type
                    });
                    
                    logger.log('Attachment created locally', { local_id: attachmentId });
                }
                
                // Try to sync immediately if online
                if (navigator.onLine && !isOfflineSimulation) {
                    logger.log('Attempting immediate sync');
                    window.syncManager.synchronize();
                } else {
                    logger.log('Device offline, sync deferred');
                }
                
                alert("Defect saved" + (navigator.onLine && !isOfflineSimulation ? " and syncing" : " (offline mode)"));
                
                // Reset form
                this.reset();
                
                // Refresh data
                loadLocalData();
                loadSyncQueue();
                
            } catch (error) {
                logger.log('Error saving defect', error);
                alert("Error saving defect: " + error.message);
            }
        });
        
        // Network toggle
        document.getElementById('toggle-network').addEventListener('click', function() {
            isOfflineSimulation = !isOfflineSimulation;
            this.textContent = isOfflineSimulation ? 'Simulate Online' : 'Simulate Offline';
            
            // Override the navigator.onLine property
            Object.defineProperty(navigator, 'onLine', {
                configurable: true,
                get: function() { return !isOfflineSimulation; }
            });
            
            // Dispatch online/offline events
            const event = new Event(isOfflineSimulation ? 'offline' : 'online');
            window.dispatchEvent(event);
            
            updateNetworkStatus();
            logger.log(isOfflineSimulation ? 'Offline mode simulated' : 'Online mode simulated');
        });
        
        // Force sync
        document.getElementById('force-sync').addEventListener('click', function() {
            logger.log('Manual sync triggered');
            window.syncManager.synchronize();
            setTimeout(() => {
                loadLocalData();
                loadSyncQueue();
            }, 1000);
        });
        
        // Clear local data
        document.getElementById('clear-data').addEventListener('click', async function() {
            if (confirm("This will delete all local data. Continue?")) {
                try {
                    // Delete the database
                    const request = indexedDB.deleteDatabase('defect_tracker_offline');
                    
                    request.onsuccess = function() {
                        logger.log('IndexedDB deleted successfully');
                        alert('Local data cleared. Reload the page to re-initialize the database.');
                        location.reload();
                    };
                    
                    request.onerror = function(event) {
                        logger.log('Error deleting IndexedDB', event.target.error);
                        alert('Error clearing data: ' + event.target.error);
                    };
                } catch (error) {
                    logger.log('Error clearing data', error);
                    alert('Error clearing data: ' + error.message);
                }
            }
        });
        
        // Clear logs
        document.getElementById('clear-logs').addEventListener('click', function() {
            logger.clear();
        });
        
        // Update sync status display
        function updateSyncStatusDisplay() {
            const statusEl = document.getElementById('sync-status-display');
            const syncStatus = window.syncManager ? 
                (window.syncManager.isSyncing ? 'Syncing...' : 'Idle') : 
                'Not initialized';
            
            statusEl.textContent = 'Sync Status: ' + syncStatus;
        }
        
        // Initial setup
        window.addEventListener('load', function() {
            updateNetworkStatus();
            loadLocalData();
            loadSyncQueue();
            logger.log('Test page initialized');
            
            setInterval(updateNetworkStatus, 1000);
            setInterval(updateSyncStatusDisplay, 1000);
        });
        
        // Listen for online/offline events
        window.addEventListener('online', function() {
            updateNetworkStatus();
            logger.log('Browser reported online status');
        });
        
        window.addEventListener('offline', function() {
            updateNetworkStatus();
            logger.log('Browser reported offline status');
        });
    </script>
</body>
</html>
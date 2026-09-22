<?php
namespace Sync;

class SyncManager {
    private $config;
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
        $this->config = include __DIR__ . '/../config.php';
    }
    
    public function processSyncQueue($queue, $username) {
        // Get user ID from username for action logging
        $userId = $this->getUserIdFromUsername($username);
        
        // Start a new sync log entry
        $syncLogId = $this->startSyncLog($username);
        $results = [];
        $counters = [
            'processed' => 0,
            'succeeded' => 0,
            'failed' => 0,
            'conflicted' => 0
        ];
        
        foreach ($queue as $item) {
            try {
                $counters['processed']++;
                
                // Update sync queue item status to processing
                $this->updateSyncQueueStatus($item['id'], 'processing');
                
                switch ($item['action']) {
                    case 'create':
                        $result = $this->processCreate($item, $username, $userId);
                        break;
                    case 'update':
                        $result = $this->processUpdate($item, $username, $userId);
                        break;
                    case 'delete':
                        $result = $this->processDelete($item, $username, $userId);
                        break;
                    default:
                        $result = [
                            'id' => $item['id'],
                            'status' => 'error',
                            'message' => 'Unknown action'
                        ];
                }
                
                // Update counters
                if ($result['status'] === 'success') {
                    $counters['succeeded']++;
                } elseif ($result['status'] === 'conflict') {
                    $counters['conflicted']++;
                    $this->recordConflict($item, $result);
                } else {
                    $counters['failed']++;
                }
                
                $results[] = $result;
                
                // Update the sync queue status
                $this->updateSyncQueueItem($item['id'], $result);
                
            } catch (\Exception $e) {
                $counters['failed']++;
                
                $result = [
                    'id' => $item['id'],
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
                
                $this->updateSyncQueueItem($item['id'], $result);
                $results[] = $result;
            }
        }
        
        // Update the sync log with results
        $this->finishSyncLog($syncLogId, $counters);
        
        return $results;
    }
    
    private function getUserIdFromUsername($username) {
        $stmt = $this->db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ? $result['id'] : null;
    }
    
    private function startSyncLog($username) {
        $stmt = $this->db->prepare("INSERT INTO sync_logs 
                                  (username, device_id, start_time, end_time, sync_direction, status) 
                                  VALUES (?, ?, ?, ?, 'bidirectional', 'processing')");
        
        $now = date('Y-m-d H:i:s');
        $deviceId = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $stmt->execute([$username, $deviceId, $now, $now]);
        
        return $this->db->lastInsertId();
    }
    
    private function finishSyncLog($syncLogId, $counters) {
        $status = 'success';
        if ($counters['failed'] > 0 && $counters['succeeded'] === 0) {
            $status = 'failed';
        } elseif ($counters['failed'] > 0) {
            $status = 'partial';
        }
        
        $stmt = $this->db->prepare("UPDATE sync_logs 
                                  SET end_time = ?, 
                                      items_processed = ?, 
                                      items_succeeded = ?, 
                                      items_failed = ?,
                                      items_conflicted = ?,
                                      status = ?
                                  WHERE id = ?");
        
        $stmt->execute([
            date('Y-m-d H:i:s'),
            $counters['processed'],
            $counters['succeeded'],
            $counters['failed'],
            $counters['conflicted'],
            $status,
            $syncLogId
        ]);
    }
    
    private function updateSyncQueueStatus($id, $status) {
        $stmt = $this->db->prepare("UPDATE sync_queue 
                                  SET status = ?, 
                                      updated_at = ?
                                  WHERE id = ?");
        $stmt->execute([$status, date('Y-m-d H:i:s'), $id]);
    }
    
    private function updateSyncQueueItem($id, $result) {
        $stmt = $this->db->prepare("UPDATE sync_queue 
                                  SET status = ?, 
                                      processed_at = ?,
                                      result = ?,
                                      updated_at = ?
                                  WHERE id = ?");
        
        $stmt->execute([
            $result['status'],
            date('Y-m-d H:i:s'),
            json_encode($result),
            date('Y-m-d H:i:s'),
            $id
        ]);
    }
    
    private function processCreate($item, $username, $userId) {
        // Add creator info and timestamps
        $item['data']['created_by'] = $userId;
        $item['data']['updated_by'] = $userId;
        
        // Handle image uploads that were stored as base64
        if ($item['entity_type'] === 'defect_image') {
            $item['data'] = $this->processImageData($item['data']);
        }
        
        // Get table name from entity type
        $table = $this->getTableFromType($item['entity_type']);
        
        // Build INSERT query
        $columns = implode(', ', array_keys($item['data']));
        $placeholders = implode(', ', array_fill(0, count($item['data']), '?'));
        
        $stmt = $this->db->prepare("INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})");
        $stmt->execute(array_values($item['data']));
        
        $serverId = $this->db->lastInsertId();
        
        // Log the action
        $this->logAction('CREATE_' . strtoupper($item['entity_type']), $userId, $serverId);
        
        return [
            'id' => $item['id'],
            'server_id' => $serverId,
            'status' => 'success',
            'action' => 'create',
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    private function processUpdate($item, $username, $userId) {
        // Add updater info
        $item['data']['updated_by'] = $userId;
        $item['data']['updated_at'] = date('Y-m-d H:i:s');
        
        // Handle conflict detection
        $conflict = $this->checkConflict($item);
        if ($conflict) {
            // Different conflict strategies can be implemented here
            // Changed from force to force_sync to match the field name in the database
            if ($this->config['conflict_strategy'] === 'server_wins' && !isset($item['force_sync']) || !$item['force_sync']) {
                return [
                    'id' => $item['id'],
                    'status' => 'conflict',
                    'action' => 'update',
                    'resolution' => 'server_wins',
                    'server_data' => $conflict
                ];
            }
        }
        
        // Handle image uploads
        if ($item['entity_type'] === 'defect_image' && isset($item['data']['file_path']) && strpos($item['data']['file_path'], 'data:') === 0) {
            $item['data'] = $this->processImageData($item['data']);
        }
        
        // Get table name from entity type
        $table = $this->getTableFromType($item['entity_type']);
        
        // Build UPDATE query
        $updateParts = [];
        $values = [];
        
        foreach ($item['data'] as $key => $value) {
            $updateParts[] = "{$key} = ?";
            $values[] = $value;
        }
        
        $setClause = implode(', ', $updateParts);
        $values[] = $item['server_id']; // For the WHERE clause
        
        $stmt = $this->db->prepare("UPDATE {$table} SET {$setClause} WHERE id = ?");
        $stmt->execute($values);
        
        // Log the action
        $this->logAction('UPDATE_' . strtoupper($item['entity_type']), $userId, $item['server_id']);
        
        return [
            'id' => $item['id'],
            'server_id' => $item['server_id'],
            'status' => 'success',
            'action' => 'update',
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    private function processDelete($item, $username, $userId) {
        $table = $this->getTableFromType($item['entity_type']);
        
        // For defects, we might want to use soft deletion
        if ($item['entity_type'] === 'defect') {
            $stmt = $this->db->prepare("UPDATE {$table} SET deleted_at = ?, updated_by = ? WHERE id = ?");
            $stmt->execute([date('Y-m-d H:i:s'), $userId, $item['server_id']]);
        } else {
            $stmt = $this->db->prepare("DELETE FROM {$table} WHERE id = ?");
            $stmt->execute([$item['server_id']]);
        }
        
        // Log the action
        $this->logAction('DELETE_' . strtoupper($item['entity_type']), $userId, $item['server_id']);
        
        return [
            'id' => $item['id'],
            'status' => 'success',
            'action' => 'delete',
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    private function checkConflict($item) {
        $table = $this->getTableFromType($item['entity_type']);
        $stmt = $this->db->prepare("SELECT * FROM {$table} WHERE id = ?");
        $stmt->execute([$item['server_id']]);
        $serverData = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$serverData) {
            return false; // No conflict if record doesn't exist
        }
        
        // Check if server version is newer than what client had
        if (isset($item['base_timestamp']) && isset($serverData['updated_at']) && 
            strtotime($serverData['updated_at']) > strtotime($item['base_timestamp'])) {
            return $serverData;
        }
        
        return false;
    }
    
    private function processImageData($data) {
        // If file_path contains base64 data, process it
        if (isset($data['file_path']) && strpos($data['file_path'], 'data:image/') === 0) {
            $data['file_path'] = $this->saveBase64Image($data['file_path']);
        }
        
        // If pin_path contains base64 data, process it
        if (isset($data['pin_path']) && strpos($data['pin_path'], 'data:image/') === 0) {
            $data['pin_path'] = $this->saveBase64Image($data['pin_path']);
        }
        
        return $data;
    }
    
    private function saveBase64Image($base64String) {
        // Extract image data
        list($type, $data) = explode(';', $base64String);
        list(, $data) = explode(',', $data);
        $imgData = base64_decode($data);
        
        // Generate a unique filename
        $extension = explode('/', $type)[1];
        $filename = uniqid() . '.' . $extension;
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/images/';
        
        // Create directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Save the file
        file_put_contents($uploadDir . $filename, $imgData);
        
        return '/uploads/images/' . $filename;
    }
    
    private function getTableFromType($type) {
        // Map entity types to database table names
        $typeTableMap = [
            'defect' => 'defects',
            'defect_comment' => 'defect_comments',
            'defect_image' => 'defect_images'
        ];
        
        if (!isset($typeTableMap[$type])) {
            throw new \Exception("Unknown entity type: {$type}");
        }
        
        return $typeTableMap[$type];
    }
    
    private function recordConflict($item, $result) {
        $stmt = $this->db->prepare("INSERT INTO sync_conflicts 
                                  (sync_queue_id, entity_type, entity_id, server_data, client_data) 
                                  VALUES (?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $item['id'],
            $item['entity_type'],
            $item['server_id'],
            json_encode($result['server_data']),
            json_encode($item['data'])
        ]);
    }
    
    private function logAction($action, $userId, $entityId) {
        try {
            // Log to activity_logs table
            $stmt = $this->db->prepare("INSERT INTO activity_logs 
                                      (defect_id, action, user_id, action_type, details, created_at) 
                                      VALUES (?, ?, ?, 'SYNC', ?, ?)");
            
            $stmt->execute([
                $entityId,
                $action,
                $userId,
                "Action performed via offline sync system at " . date('Y-m-d H:i:s'),
                date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            // If activity logging fails, we don't want to break the sync process
            // Just log to system_logs as a fallback
            try {
                $stmt = $this->db->prepare("INSERT INTO system_logs 
                                          (user_id, action, action_by, action_at, details) 
                                          VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $userId,
                    $action,
                    $userId,
                    date('Y-m-d H:i:s'),
                    "Sync action on entity type: " . explode('_', $action)[1] . ", ID: $entityId"
                ]);
            } catch (\Exception $innerE) {
                // Silently fail if all logging attempts fail
                // We prioritize the sync operation over logging
            }
        }
    }
    
    // Clean up old sync records
    public function cleanupSyncRecords() {
        $maxAgeDays = $this->config['max_queue_age'] ?? 30;
        $stmt = $this->db->prepare("DELETE FROM sync_queue 
                                   WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->execute([$maxAgeDays]);
        return $stmt->rowCount();
    }
    
    // Resolve conflict with chosen strategy
    public function resolveConflict($conflictId, $resolution) {
        try {
            // First get the conflict details
            $stmt = $this->db->prepare("SELECT * FROM sync_conflicts WHERE id = ?");
            $stmt->execute([$conflictId]);
            $conflict = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$conflict) {
                throw new \Exception('Conflict not found');
            }
            
            // Update the conflict record
            $stmt = $this->db->prepare("UPDATE sync_conflicts 
                                      SET resolved = 1, 
                                          resolution_type = ?, 
                                          resolved_by = ?, 
                                          resolved_at = ? 
                                      WHERE id = ?");
            $stmt->execute([$resolution, 'irlam', date('Y-m-d H:i:s'), $conflictId]);
            
            // Update related sync queue item to retry with force_sync
            $stmt = $this->db->prepare("UPDATE sync_queue 
                                      SET status = 'pending', 
                                          force_sync = 1
                                      WHERE id = ?");
            $stmt->execute([$conflict['sync_queue_id']]);
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
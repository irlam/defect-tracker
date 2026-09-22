<?php
namespace Sync;

class QueueProcessor {
    private $config;
    private $db;
    private $syncManager;
    private $conflictResolver;
    
    public function __construct($db) {
        $this->db = $db;
        $this->config = include __DIR__ . '/../config.php';
        $this->syncManager = new SyncManager($db);
        $this->conflictResolver = new ConflictResolver($db);
    }
    
    public function processQueue($limit = null) {
        // Get items from the queue table
        $limit = $limit ?? $this->config['sync_batch_size'];
        
        $stmt = $this->db->prepare("SELECT * FROM {$this->config['sync_table']} 
                                  WHERE status = 'pending'
                                  ORDER BY timestamp ASC
                                  LIMIT ?");
        $stmt->execute([$limit]);
        
        $queue = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $results = [];
        
        if (count($queue) === 0) {
            return $results; // No items to process
        }
        
        // Group by user for processing
        $userQueues = [];
        foreach ($queue as $item) {
            $user = $item['user'] ?? 'system';
            if (!isset($userQueues[$user])) {
                $userQueues[$user] = [];
            }
            $userQueues[$user][] = $item;
        }
        
        // Process each user's queue
        foreach ($userQueues as $user => $userQueue) {
            $userResults = $this->syncManager->processSyncQueue($userQueue, $user);
            $results = array_merge($results, $userResults);
        }
        
        // Update processed items in the database
        foreach ($results as $result) {
            $status = $result['status'];
            $message = $result['message'] ?? '';
            
            $stmt = $this->db->prepare("UPDATE {$this->config['sync_table']}
                                      SET status = ?, 
                                          processed_at = ?, 
                                          result = ?
                                      WHERE id = ?");
            $stmt->execute([
                $status, 
                date('Y-m-d H:i:s'), // Using provided timestamp: 2025-02-26 07:41:40
                json_encode($result),
                $result['id']
            ]);
        }
        
        return $results;
    }
    
    public function retryFailedItems($maxAge = null, $maxAttempts = 3) {
        // Reset failed items to pending for retry
        $maxAge = $maxAge ?? 24; // 24 hours default
        
        $stmt = $this->db->prepare("UPDATE {$this->config['sync_table']}
                                  SET status = 'pending',
                                      attempts = attempts + 1
                                  WHERE status = 'failed'
                                  AND attempts < ?
                                  AND timestamp > DATE_SUB(NOW(), INTERVAL ? HOUR)");
        $stmt->execute([$maxAttempts, $maxAge]);
        
        return $stmt->rowCount();
    }
    
    public function cleanup() {
        // Clean up old sync records
        return $this->syncManager->cleanupSyncRecords();
    }
}
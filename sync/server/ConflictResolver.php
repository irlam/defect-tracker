<?php
namespace Sync;

class ConflictResolver {
    private $config;
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
        $this->config = include __DIR__ . '/../config.php';
    }
    
    public function resolveConflict($clientData, $serverData, $type, $strategyOverride = null) {
        $strategy = $strategyOverride ?: $this->config['conflict_strategy'];
        
        switch ($strategy) {
            case 'server_wins':
                return $serverData;
                
            case 'client_wins':
                return $clientData;
                
            case 'timestamp_wins':
                // Compare timestamps to determine which is newer
                $clientTimestamp = isset($clientData['updated_at']) ? strtotime($clientData['updated_at']) : 0;
                $serverTimestamp = isset($serverData['updated_at']) ? strtotime($serverData['updated_at']) : 0;
                
                if ($clientTimestamp > $serverTimestamp) {
                    return $clientData;
                } else {
                    return $serverData;
                }
                
            case 'prompt_user':
                // This is handled client-side
                // Return information needed for client-side resolution
                return [
                    'status' => 'conflict',
                    'client_data' => $clientData,
                    'server_data' => $serverData,
                    'needs_resolution' => true
                ];
                
            case 'merge':
                // Create a merged record with values from both
                return $this->mergeData($clientData, $serverData, $type);
                
            default:
                // Default to server wins
                return $serverData;
        }
    }
    
    private function mergeData($clientData, $serverData, $type) {
        $mergedData = $serverData;
        
        // Use field-specific merge rules based on entity type
        switch ($type) {
            case 'defect':
                // For defects, we might have specific merging logic
                // This is a simple example that could be expanded
                $fieldsToPreferClient = ['description', 'steps_to_reproduce'];
                foreach ($fieldsToPreferClient as $field) {
                    if (isset($clientData[$field]) && !empty(trim($clientData[$field]))) {
                        $mergedData[$field] = $clientData[$field];
                    }
                }
                
                // For status, use the more "advanced" status (based on workflow)
                $statusPriority = ['new' => 1, 'in_progress' => 2, 'testing' => 3, 'resolved' => 4, 'closed' => 5];
                $clientStatus = isset($clientData['status']) ? $clientData['status'] : 'new';
                $serverStatus = isset($serverData['status']) ? $serverData['status'] : 'new';
                
                $clientPriority = $statusPriority[$clientStatus] ?? 0;
                $serverPriority = $statusPriority[$serverStatus] ?? 0;
                
                if ($clientPriority > $serverPriority) {
                    $mergedData['status'] = $clientStatus;
                }
                
                break;
                
            case 'comment':
                // For comments, content from client usually takes precedence
                if (isset($clientData['content']) && !empty(trim($clientData['content']))) {
                    $mergedData['content'] = $clientData['content'];
                }
                break;
                
            case 'attachment':
                // For attachments, usually we don't merge - just use client if it has content
                if (isset($clientData['file_data']) && !empty($clientData['file_data'])) {
                    $mergedData['file_data'] = $clientData['file_data'];
                }
                break;
        }
        
        // Add a note about the merge
        $mergedData['merge_note'] = 'Auto-merged from conflict on ' . date('Y-m-d H:i:s') . 
                                   ' between client (' . $clientData['updated_at'] . 
                                   ') and server (' . $serverData['updated_at'] . ')';
        
        // Update timestamp to current
        $mergedData['updated_at'] = date('Y-m-d H:i:s'); // Using current timestamp: 2025-02-26 07:41:40
        $mergedData['updated_by'] = 'irlam'; // Current user
        
        return $mergedData;
    }
}
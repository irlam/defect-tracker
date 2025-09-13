    async handleConflict(pendingItem, result) {
        if (result.resolution === 'server_wins') {
            // Update local data with server data
            const storeName = this.getStoreNameFromType(pendingItem.type);
            const transaction = window.dbManager.db.transaction([storeName], 'readwrite');
            const store = transaction.objectStore(storeName);
            
            // Get the local item and merge with server data
            const getRequest = store.get(pendingItem.id);
            
            return new Promise((resolve, reject) => {
                getRequest.onsuccess = async (event) => {
                    const localItem = event.target.result;
                    // Update with server data
                    const updatedItem = {...localItem, ...result.server_data, sync_status: 'synced'};
                    
                    const putRequest = store.put(updatedItem);
                    putRequest.onsuccess = async () => {
                        // Mark sync item as resolved
                        await window.dbManager.updateSyncItem(pendingItem.id, {
                            status: 'resolved',
                            server_response: result
                        });
                        resolve();
                    };
                    
                    putRequest.onerror = (e) => reject(e.target.error);
                };
                
                getRequest.onerror = (e) => reject(e.target.error);
            });
        } else if (result.resolution === 'prompt_user') {
            // Show conflict resolution UI to the user
            this.showConflictResolutionUI(pendingItem, result);
            
            // Mark as pending user input
            await window.dbManager.updateSyncItem(pendingItem.id, {
                status: 'awaiting_user_input',
                server_response: result
            });
        }
        // Other resolution strategies can be handled here
    }
    
    showConflictResolutionUI(pendingItem, result) {
        // Create modal for conflict resolution
        const modal = document.createElement('div');
        modal.className = 'sync-conflict-modal';
        modal.style.cssText = 'position:fixed; top:0; left:0; width:100%; height:100%; ' +
                             'background:rgba(0,0,0,0.5); z-index:10000; display:flex; ' +
                             'justify-content:center; align-items:center;';
        
        const modalContent = document.createElement('div');
        modalContent.style.cssText = 'background:#fff; padding:20px; border-radius:5px; ' +
                                    'max-width:500px; max-height:80vh; overflow-y:auto;';
        
        const title = document.createElement('h3');
        title.textContent = 'Conflict Detected';
        
        const description = document.createElement('p');
        description.textContent = 'This record has been modified on the server since your last sync. ' +
                                 'Please choose which version to keep:';
        
        const localButton = document.createElement('button');
        localButton.textContent = 'Keep My Changes';
        localButton.onclick = () => this.resolveConflict(pendingItem, 'client_wins', modal);
        
        const serverButton = document.createElement('button');
        serverButton.textContent = 'Use Server Version';
        serverButton.onclick = () => this.resolveConflict(pendingItem, 'server_wins', modal);
        
        const mergeButton = document.createElement('button');
        mergeButton.textContent = 'Merge Changes';
        mergeButton.onclick = () => this.resolveConflict(pendingItem, 'merge', modal);
        
        // Append elements
        modalContent.appendChild(title);
        modalContent.appendChild(description);
        modalContent.appendChild(document.createElement('hr'));
        modalContent.appendChild(localButton);
        modalContent.appendChild(serverButton);
        modalContent.appendChild(mergeButton);
        modal.appendChild(modalContent);
        
        document.body.appendChild(modal);
    }
    
    async resolveConflict(pendingItem, resolution, modal) {
        // Remove modal
        document.body.removeChild(modal);
        
        // Handle resolution based on choice
        if (resolution === 'client_wins') {
            // Resubmit with force flag
            await window.dbManager.updateSyncItem(pendingItem.id, {
                status: 'pending',
                force: true
            });
            this.synchronize();
        } else if (resolution === 'server_wins') {
            // Apply server data
            const result = pendingItem.server_response;
            await this.handleConflict(pendingItem, { ...result, resolution: 'server_wins' });
        } else if (resolution === 'merge') {
            // Implement merge logic (this would be more complex in a real app)
            // For now, just favor client data but mark as resolved
            await window.dbManager.updateSyncItem(pendingItem.id, {
                status: 'pending',
                force: true
            });
            this.synchronize();
        }
    }
    
    getStoreNameFromType(type) {
        // Map entity type to IndexedDB store name
        const typeStoreMap = {
            'defect': 'defects',
            'comment': 'comments',
            'attachment': 'attachments',
            // Add more mappings as needed
        };
        
        if (typeStoreMap[type]) {
            return typeStoreMap[type];
        }
        
        return type + 's'; // Default pluralization
    }
    
    // Utility method to check server connection
    async checkServerConnection() {
        try {
            const response = await fetch(this.config.apiEndpoint + '?check=1', {
                method: 'GET',
                headers: {
                    'Cache-Control': 'no-cache'
                }
            });
            
            if (response.ok) {
                return true;
            }
        } catch (e) {
            console.log('Connection check failed:', e);
        }
        
        return false;
    }
}

// Initialize sync manager when document is ready
document.addEventListener('DOMContentLoaded', () => {
    window.syncManager = new SyncManager();
    
    // Set current user info from PHP variables
    if (window.SYNC_CONFIG) {
        window.SYNC_CONFIG.userIdentifier = 'irlam'; // Use the provided login
        window.SYNC_CONFIG.currentTimestamp = '2025-02-26 07:41:40'; // Use the provided timestamp
    }
});
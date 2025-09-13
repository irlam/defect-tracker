/**
 * IndexedDB Manager for offline data storage
 */
class DBManager {
    constructor() {
        this.DB_NAME = 'defect_tracker_offline';
        this.DB_VERSION = 1;
        this.db = null;
        this.initPromise = this.init();
    }

    async init() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(this.DB_NAME, this.DB_VERSION);
            
            request.onerror = (event) => {
                console.error("IndexedDB error:", event.target.error);
                reject("Could not open IndexedDB");
            };
            
            request.onsuccess = (event) => {
                this.db = event.target.result;
                console.log("IndexedDB connection established");
                resolve();
            };
            
            request.onupgradeneeded = (event) => {
                const db = event.target.result;
                
                // Create object stores for different entity types
                if (!db.objectStoreNames.contains('defects')) {
                    const defectsStore = db.createObjectStore('defects', { keyPath: 'id', autoIncrement: true });
                    defectsStore.createIndex('status', 'status', { unique: false });
                    defectsStore.createIndex('sync_status', 'sync_status', { unique: false });
                }
                
                if (!db.objectStoreNames.contains('attachments')) {
                    const attachmentsStore = db.createObjectStore('attachments', { keyPath: 'id', autoIncrement: true });
                    attachmentsStore.createIndex('defect_id', 'defect_id', { unique: false });
                    attachmentsStore.createIndex('sync_status', 'sync_status', { unique: false });
                }
                
                if (!db.objectStoreNames.contains('sync_queue')) {
                    const syncQueueStore = db.createObjectStore('sync_queue', { keyPath: 'id', autoIncrement: true });
                    syncQueueStore.createIndex('status', 'status', { unique: false });
                    syncQueueStore.createIndex('timestamp', 'timestamp', { unique: false });
                }
            };
        });
    }

    async add(storeName, data) {
        await this.initPromise;
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([storeName], 'readwrite');
            const store = transaction.objectStore(storeName);
            
            // Add sync metadata
            data.sync_status = 'pending';
            data.created_at = new Date().toISOString();
            data.updated_at = new Date().toISOString();
            data.created_by = window.SYNC_CONFIG.userIdentifier;
            data.updated_by = window.SYNC_CONFIG.userIdentifier;
            
            const request = store.add(data);
            
            request.onsuccess = (event) => {
                const id = event.target.result;
                // Add to sync queue
                this.addToSyncQueue('create', storeName, id, null, data);
                resolve(id);
            };
            
            request.onerror = (event) => {
                reject(event.target.error);
            };
        });
    }

    async update(storeName, id, data) {
        await this.initPromise;
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([storeName], 'readwrite');
            const store = transaction.objectStore(storeName);
            
            // Get existing data first
            const getRequest = store.get(id);
            
            getRequest.onsuccess = (event) => {
                const existingData = event.target.result;
                if (existingData) {
                    // Merge data
                    const updatedData = {...existingData, ...data};
                    updatedData.sync_status = 'pending';
                    updatedData.updated_at = new Date().toISOString();
                    updatedData.updated_by = window.SYNC_CONFIG.userIdentifier;
                    
                    const updateRequest = store.put(updatedData);
                    
                    updateRequest.onsuccess = () => {
                        // Add to sync queue
                        this.addToSyncQueue('update', storeName, id, existingData.server_id, data, existingData.updated_at);
                        resolve(id);
                    };
                    
                    updateRequest.onerror = (event) => {
                        reject(event.target.error);
                    };
                } else {
                    reject(new Error(`Record not found with ID: ${id}`));
                }
            };
            
            getRequest.onerror = (event) => {
                reject(event.target.error);
            };
        });
    }

    async delete(storeName, id) {
        await this.initPromise;
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([storeName], 'readwrite');
            const store = transaction.objectStore(storeName);
            
            // Get existing data first to capture server_id
            const getRequest = store.get(id);
            
            getRequest.onsuccess = (event) => {
                const existingData = event.target.result;
                if (existingData) {
                    const serverId = existingData.server_id;
                    
                    const deleteRequest = store.delete(id);
                    
                    deleteRequest.onsuccess = () => {
                        // If has server_id, add to sync queue
                        if (serverId) {
                            this.addToSyncQueue('delete', storeName, id, serverId);
                        }
                        resolve(id);
                    };
                    
                    deleteRequest.onerror = (event) => {
                        reject(event.target.error);
                    };
                } else {
                    reject(new Error(`Record not found with ID: ${id}`));
                }
            };
            
            getRequest.onerror = (event) => {
                reject(event.target.error);
            };
        });
    }

    async get(storeName, id) {
        await this.initPromise;
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([storeName], 'readonly');
            const store = transaction.objectStore(storeName);
            const request = store.get(id);
            
            request.onsuccess = (event) => {
                resolve(event.target.result);
            };
            
            request.onerror = (event) => {
                reject(event.target.error);
            };
        });
    }

    async getAll(storeName, indexName = null, indexValue = null) {
        await this.initPromise;
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([storeName], 'readonly');
            const store = transaction.objectStore(storeName);
            
            let request;
            if (indexName && indexValue !== null) {
                const index = store.index(indexName);
                request = index.getAll(indexValue);
            } else {
                request = store.getAll();
            }
            
            request.onsuccess = (event) => {
                resolve(event.target.result);
            };
            
            request.onerror = (event) => {
                reject(event.target.error);
            };
        });
    }

    async addToSyncQueue(action, type, id, serverId, data = null, baseTimestamp = null) {
        await this.initPromise;
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['sync_queue'], 'readwrite');
            const store = transaction.objectStore('sync_queue');
            
            const queueItem = {
                action,
                type,
                id,
                server_id: serverId,
                data: data,
                base_timestamp: baseTimestamp,
                status: 'pending',
                attempts: 0,
                timestamp: new Date().toISOString(),
                user: window.SYNC_CONFIG.userIdentifier
            };
            
            const request = store.add(queueItem);
            
            request.onsuccess = (event) => {
                resolve(event.target.result);
            };
            
            request.onerror = (event) => {
                reject(event.target.error);
            };
        });
    }

    async getPendingSyncItems() {
        await this.initPromise;
        return this.getAll('sync_queue', 'status', 'pending');
    }

    async updateSyncItem(id, updates) {
        await this.initPromise;
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction(['sync_queue'], 'readwrite');
            const store = transaction.objectStore('sync_queue');
            
            const getRequest = store.get(id);
            
            getRequest.onsuccess = (event) => {
                const item = event.target.result;
                if (item) {
                    const updatedItem = {...item, ...updates};
                    const updateRequest = store.put(updatedItem);
                    
                    updateRequest.onsuccess = () => {
                        resolve(id);
                    };
                    
                    updateRequest.onerror = (event) => {
                        reject(event.target.error);
                    };
                } else {
                    reject(new Error(`Sync item not found with ID: ${id}`));
                }
            };
            
            getRequest.onerror = (event) => {
                reject(event.target.error);
            };
        });
    }
    
    async updateEntitySyncStatus(storeName, id, status, serverId = null) {
        await this.initPromise;
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([storeName], 'readwrite');
            const store = transaction.objectStore(storeName);
            
            const getRequest = store.get(id);
            
            getRequest.onsuccess = (event) => {
                const item = event.target.result;
                if (item) {
                    item.sync_status = status;
                    if (serverId) {
                        item.server_id = serverId;
                    }
                    
                    const updateRequest = store.put(item);
                    
                    updateRequest.onsuccess = () => {
                        resolve(id);
                    };
                    
                    updateRequest.onerror = (event) => {
                        reject(event.target.error);
                    };
                } else {
                    reject(new Error(`Item not found with ID: ${id} in store ${storeName}`));
                }
            };
            
            getRequest.onerror = (event) => {
                reject(event.target.error);
            };
        });
    }
}

// Create global instance
window.dbManager = new DBManager();
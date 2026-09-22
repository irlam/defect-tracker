// Service worker for offline capabilities in the defect tracking system

const CACHE_NAME = 'defect-tracker-cache-v1';

// Assets to cache for offline use
const STATIC_ASSETS = [
    '/',
    '/index.php',
    '/css/main.css',
    '/js/main.js',
    '/sync/client/db-manager.js',
    '/sync/client/sync-manager.js',
    // Add other static assets as needed
];

// Install event - cache static assets
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                console.log('Caching static assets');
                return cache.addAll(STATIC_ASSETS);
            })
            .then(() => self.skipWaiting()) // Activate immediately
    );
});

// Activate event - clean up old caches
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((cacheNames) => {
                return Promise.all(
                    cacheNames
                        .filter((name) => name !== CACHE_NAME)
                        .map((name) => caches.delete(name))
                );
            })
            .then(() => self.clients.claim()) // Take control of clients immediately
    );
});

// Fetch event - handle offline requests
self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);
    
    // Skip caching API calls and sync operations - they're handled by sync-manager
    if (url.pathname.includes('/api/') || url.pathname.includes('/sync/server/')) {
        // For API requests, try network first, but fall back to cache if offline
        event.respondWith(
            fetch(event.request)
                .catch(() => {
                    // Return a specific offline response for API requests
                    return new Response(
                        JSON.stringify({
                            error: 'You are currently offline.',
                            offline: true,
                            timestamp: new Date().toISOString()
                        }),
                        {
                            headers: { 'Content-Type': 'application/json' }
                        }
                    );
                })
        );
        return;
    }
    
    // For other requests, use cache-first strategy
    event.respondWith(
        caches.match(event.request)
            .then((response) => {
                if (response) {
                    // Cache hit - return response from cache
                    return response;
                }
                
                // Not in cache - fetch from network
                return fetch(event.request)
                    .then((networkResponse) => {
                        // Don't cache non-GET requests
                        if (event.request.method !== 'GET') {
                            return networkResponse;
                        }
                        
                        // Clone the response as it's a stream and can only be consumed once
                        const responseToCache = networkResponse.clone();
                        
                        // Open cache and store fetched resource
                        caches.open(CACHE_NAME)
                            .then((cache) => {
                                cache.put(event.request, responseToCache);
                            });
                        
                        return networkResponse;
                    })
                    .catch(() => {
                        // Network request failed - show offline page for HTML requests
                        if (event.request.headers.get('accept').includes('text/html')) {
                            return caches.match('/offline.html')
                                .then(offlineResponse => {
                                    if (offlineResponse) return offlineResponse;
                                    return new Response('You are offline and this page is not cached.', {
                                        headers: { 'Content-Type': 'text/html' }
                                    });
                                });
                        }
                    });
            })
    );
});

// Handle background sync
self.addEventListener('sync', (event) => {
    if (event.tag === 'sync-defects') {
        event.waitUntil(syncData());
    }
});

// Helper function to sync data when back online
async function syncData() {
    try {
        // Fetch from our sync endpoint
        const response = await fetch('/sync/server/SyncEndpoint.php', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'ServiceWorker'
            },
            credentials: 'include'
        });
        
        // If successful, broadcast a message to clients
        if (response.ok) {
            const message = await response.json();
            
            // Notify all clients that sync is complete
            const clients = await self.clients.matchAll();
            clients.forEach(client => {
                client.postMessage({
                    type: 'sync_complete',
                    timestamp: new Date().toISOString()
                });
            });
            
            return message;
        }
        
        throw new Error('Sync failed');
    } catch (error) {
        console.error('Background sync failed:', error);
        // Will automatically retry based on the browser's algorithm
        throw error;
    }
}
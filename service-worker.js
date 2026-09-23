const CACHE_VERSION = 'v1.2.0';
const CACHE_NAME = `defect-tracker-${CACHE_VERSION}`;
const OFFLINE_URL = '/offline.html';
const OFFLINE_FIELD_URL = '/offline-field.html';
const FIELD_DB_NAME = 'defect_tracker_field_queue';
const FIELD_DB_VERSION = 1;
const FIELD_OUTBOX_STORE = 'defect_submissions';
const FIELD_META_STORE = 'field_metadata';

const STATIC_ASSETS = [
  '/css/app.css',
  '/offline.html',
  '/offline-field.html',
  '/js/offline-defect-queue.js',
  '/js/offline-field.js',
  '/favicons/favicon-96x96.png'
];

self.addEventListener('install', event => {
  console.log('Service worker installing...');
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => {
      console.log('Opened cache');
      return cache.addAll(STATIC_ASSETS);
    }).catch(err => {
      console.error('Failed to cache static assets:', err);
      throw err; // Re-throw to indicate installation failure
    })
  );
  self.skipWaiting();
});

self.addEventListener('activate', event => {
  console.log('Service worker activating...');
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheName.startsWith('defect-tracker-') && cacheName !== CACHE_NAME) {
            console.log('Deleting old cache:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
  self.clients.claim();
});

self.addEventListener('fetch', event => {
  // Mutations and authenticated data must never be served from a shared cache.
  if (event.request.method !== 'GET') return;

  // Handle navigation requests
  if (event.request.mode === 'navigate') {
    const navigationUrl = new URL(event.request.url);
    event.respondWith(
      fetch(event.request).then(response => {
        if (navigationUrl.pathname === OFFLINE_FIELD_URL && response.ok) {
          const copy = response.clone();
          caches.open(CACHE_NAME).then(cache => cache.put(OFFLINE_FIELD_URL, copy));
        }
        return response;
      }).catch(() => caches.match(
        navigationUrl.pathname === OFFLINE_FIELD_URL ? OFFLINE_FIELD_URL : OFFLINE_URL
      ))
    );
    return;
  }

  const url = new URL(event.request.url);
  if (url.origin !== self.location.origin) {
    return;
  }

  // Floor-plan previews selected during online preparation are placed in a
  // separate cache by the field queue. Serve only those known cached files;
  // private defect photos and authenticated pages always bypass this path.
  if (event.request.destination === 'image' && /\/uploads\/(?:floor_plans|floor_plan_images)\//i.test(url.pathname)) {
    event.respondWith(caches.match(event.request).then(response => response || fetch(event.request)));
    return;
  }

  if (url.search || !STATIC_ASSETS.includes(url.pathname)) return;

  // Only explicitly public static assets use the cache-first strategy.
  event.respondWith(
    caches.match(event.request).then(response => {
      if (response) {
        return response;
      }
      return fetch(event.request).then(response => {
        // Cache successful responses
        if (response && response.status === 200) {
          const responseToCache = response.clone();
          caches.open(CACHE_NAME).then(cache => {
            cache.put(event.request, responseToCache);
          });
        }
        return response;
      });
    })
  );
});

function openFieldDatabase() {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open(FIELD_DB_NAME, FIELD_DB_VERSION);
    request.onsuccess = () => resolve(request.result);
    request.onerror = () => reject(request.error || new Error('Unable to open the field outbox'));
  });
}

function fieldRequest(request) {
  return new Promise((resolve, reject) => {
    request.onsuccess = () => resolve(request.result);
    request.onerror = () => reject(request.error || new Error('Field outbox request failed'));
  });
}

function fieldTransaction(transaction) {
  return new Promise((resolve, reject) => {
    transaction.oncomplete = resolve;
    transaction.onerror = () => reject(transaction.error || new Error('Field outbox transaction failed'));
    transaction.onabort = () => reject(transaction.error || new Error('Field outbox transaction aborted'));
  });
}

async function readFieldOutbox(db) {
  const transaction = db.transaction(FIELD_OUTBOX_STORE, 'readonly');
  return fieldRequest(transaction.objectStore(FIELD_OUTBOX_STORE).getAll());
}

async function readFieldMetadata(db, key) {
  const transaction = db.transaction(FIELD_META_STORE, 'readonly');
  const result = await fieldRequest(transaction.objectStore(FIELD_META_STORE).get(key));
  return result ? result.value : null;
}

async function saveFieldItem(db, item) {
  const transaction = db.transaction(FIELD_OUTBOX_STORE, 'readwrite');
  transaction.objectStore(FIELD_OUTBOX_STORE).put(item);
  return fieldTransaction(transaction);
}

async function deleteFieldItem(db, id) {
  const transaction = db.transaction(FIELD_OUTBOX_STORE, 'readwrite');
  transaction.objectStore(FIELD_OUTBOX_STORE).delete(id);
  return fieldTransaction(transaction);
}

function restoreFieldFormData(entries, csrfToken) {
  const formData = new FormData();
  entries.forEach(entry => {
    if (entry.name === 'csrf_token') return;
    if (entry.kind === 'file') {
      formData.append(entry.name, entry.blob, entry.fileName);
    } else {
      formData.append(entry.name, entry.value);
    }
  });
  formData.set('csrf_token', csrfToken);
  return formData;
}

async function notifyFieldClients(message) {
  const clientList = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
  clientList.forEach(client => client.postMessage(message));
}

async function reportFieldTelemetry(context, db, deviceId, event) {
  try {
    const items = (await readFieldOutbox(db)).filter(item => Number(item.userId) === Number(context.userId));
    const failed = items.filter(item => item.status === 'failed');
    const syncing = items.filter(item => item.status === 'syncing');
    const pending = items.filter(item => !['failed', 'syncing'].includes(item.status));
    const oldestPendingAt = items.length ? new Date(Math.min(...items.map(item => item.createdAt))).toISOString() : null;
    const response = await fetch('/api/offline_field_telemetry.php', {
      method: 'POST',
      credentials: 'include',
      cache: 'no-store',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-Token': context.csrfToken
      },
      body: JSON.stringify({
        deviceId,
        pendingCount: pending.length,
        failedCount: failed.length,
        syncingCount: syncing.length,
        oldestPendingAt,
        status: failed.length ? 'error' : (syncing.length ? 'syncing' : 'online'),
        lastError: failed[0] ? failed[0].lastError : null,
        event: event || null
      })
    });
    return response.ok;
  } catch (error) {
    console.warn('Field telemetry report deferred:', error);
    return false;
  }
}

async function syncFieldReportsInBackground() {
  const contextResponse = await fetch('/api/offline_field_context.php', {
    credentials: 'include',
    cache: 'no-store',
    headers: { Accept: 'application/json' }
  });
  if (contextResponse.status === 401) {
    await notifyFieldClients({ type: 'FIELD_SYNC_NEEDS_LOGIN' });
    return;
  }
  if (!contextResponse.ok) throw new Error(`Field context unavailable (${contextResponse.status})`);
  const context = await contextResponse.json();
  const db = await openFieldDatabase();
  const storedDeviceId = await readFieldMetadata(db, 'deviceId');
  const items = (await readFieldOutbox(db))
    .filter(item => Number(item.userId) === Number(context.userId) && item.status !== 'failed')
    .sort((a, b) => a.createdAt - b.createdAt);
  const deviceId = storedDeviceId || (items[0] && items[0].deviceId) || `worker-${context.userId}-unknown`;
  await reportFieldTelemetry(context, db, deviceId, null);

  for (const item of items) {
    item.status = 'syncing';
    item.lastAttemptAt = Date.now();
    await saveFieldItem(db, item);
    try {
      const response = await fetch('/create_defect.php', {
        method: 'POST',
        credentials: 'include',
        headers: { Accept: 'application/json', 'X-Offline-Submission': '1', 'X-Field-Device-ID': item.deviceId || deviceId },
        body: restoreFieldFormData(item.payload, context.csrfToken)
      });
      let body = {};
      try { body = await response.json(); } catch (error) { body = {}; }
      if (response.ok && body.success) {
        await deleteFieldItem(db, item.id);
        continue;
      }
      item.lastError = body.message || `Upload failed (${response.status})`;
      if (response.status >= 400 && response.status < 500) {
        item.attempts = Number(item.attempts || 0) + 1;
        item.status = 'failed';
        await saveFieldItem(db, item);
        await reportFieldTelemetry(context, db, deviceId, {
          id: `failed-${item.id}-${item.attempts}`,
          submissionId: item.id,
          type: 'upload_failed',
          status: 'failed',
          message: item.lastError
        });
        continue;
      }
      throw new Error(item.lastError);
    } catch (error) {
      item.attempts = Number(item.attempts || 0) + 1;
      item.lastError = error.message || 'Network unavailable';
      item.status = 'pending';
      await saveFieldItem(db, item);
      throw error;
    }
  }
  await reportFieldTelemetry(context, db, deviceId, null);
  await notifyFieldClients({ type: 'FIELD_SYNC_COMPLETE' });
}

self.addEventListener('sync', event => {
  if (event.tag !== 'sync-field-reports') return;
  event.waitUntil(syncFieldReportsInBackground());
});

// Handle push notifications
self.addEventListener('push', event => {
  console.log('Push notification received:', event);
  
  let notificationData = {
    title: 'Defect Tracker',
    body: 'You have a new notification',
    icon: '/favicons/favicon-96x96.png',
    badge: '/favicons/favicon-96x96.png',
    data: {}
  };
  
  // Parse notification data if available
  if (event.data) {
    try {
      const data = event.data.json();
      notificationData = {
        title: data.title || data.notification?.title || notificationData.title,
        body: data.body || data.message || data.notification?.body || notificationData.body,
        icon: data.icon || notificationData.icon,
        badge: data.badge || notificationData.badge,
        data: {
          // Spread operator first to allow standardized fields to override
          ...data,
          // Standardize the data structure - these take precedence
          defectId: data.defectId || data.data?.defectId,
          log_id: data.log_id || data.data?.log_id,
          user_id: data.user_id || data.data?.user_id
        },
        tag: data.tag || 'defect-notification',
        requireInteraction: data.requireInteraction || false
      };
      
      // Add action buttons if defect ID is present
      if (notificationData.data.defectId) {
        notificationData.actions = [
          { action: 'view', title: 'View Defect' },
          { action: 'close', title: 'Dismiss' }
        ];
      }
    } catch (err) {
      console.error('Error parsing push notification data:', err);
      notificationData.body = event.data.text();
    }
  }
  
  // Show the notification
  event.waitUntil(
    self.registration.showNotification(notificationData.title, {
      body: notificationData.body,
      icon: notificationData.icon,
      badge: notificationData.badge,
      data: notificationData.data,
      tag: notificationData.tag,
      actions: notificationData.actions,
      requireInteraction: notificationData.requireInteraction
    }).then(() => {
      // Confirm delivery to the server
      const defectId = notificationData.data.defectId;
      return fetch('/api/confirm_notification_delivery.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          log_id: notificationData.data.log_id,
          user_id: notificationData.data.user_id,
          defect_id: defectId
        })
      }).catch(err => {
        console.error('Failed to confirm delivery:', err);
      });
    })
  );
});

// Handle notification clicks
self.addEventListener('notificationclick', event => {
  console.log('Notification clicked:', event);
  
  event.notification.close();
  
  // Get the defect ID from notification data (standardized structure)
  const notificationData = event.notification.data || {};
  const defectId = notificationData.defectId || (notificationData.data && notificationData.data.defectId);
  let urlToOpen = '/dashboard.php';
  
  if (event.action === 'view' && defectId) {
    urlToOpen = `/view_defect.php?id=${defectId}`;
  } else if (defectId && event.action !== 'close') {
    urlToOpen = `/view_defect.php?id=${defectId}`;
  }
  
  // Open or focus the app
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(clientList => {
      // Check if there's already a window open
      for (let client of clientList) {
        if (client.url.includes(self.location.origin) && 'focus' in client) {
          // Focus existing window and send navigation message
          return client.focus().then(focusedClient => {
            return focusedClient.postMessage({
              type: 'NAVIGATE',
              url: urlToOpen
            });
          });
        }
      }
      // If no window is open, open a new one
      if (clients.openWindow) {
        return clients.openWindow(urlToOpen);
      }
    })
  );
});

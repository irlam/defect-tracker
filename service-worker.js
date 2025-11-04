const CACHE_VERSION = 'v1.0.0';
const CACHE_NAME = `defect-tracker-${CACHE_VERSION}`;
const OFFLINE_URL = '/offline.html';

const STATIC_ASSETS = [
  '/',
  '/css/app.css',
  '/offline.html',
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
          if (cacheName !== CACHE_NAME) {
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
  // Handle navigation requests
  if (event.request.mode === 'navigate') {
    event.respondWith(
      fetch(event.request).catch(() => {
        return caches.match(OFFLINE_URL);
      })
    );
    return;
  }

  // Handle other requests with cache-first strategy
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
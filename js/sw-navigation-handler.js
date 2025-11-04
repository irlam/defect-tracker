/**
 * Service Worker Message Handler
 * Listens for navigation messages from the service worker
 */

if ('serviceWorker' in navigator) {
  // Listen for messages from the service worker
  navigator.serviceWorker.addEventListener('message', event => {
    if (event.data && event.data.type === 'NAVIGATE') {
      // Navigate to the specified URL
      window.location.href = event.data.url;
    }
  });
}

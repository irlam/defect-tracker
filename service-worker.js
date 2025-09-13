self.addEventListener('install', event => {
  console.log('Service worker installing...');
  // Perform install steps
});

self.addEventListener('activate', event => {
  console.log('Service worker activating...');
  // Perform activate steps
});

self.addEventListener('fetch', event => {
  event.respondWith(
    caches.match(event.request).then(response => {
      return response || fetch(event.request);
    })
  );
});
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const handlers = {};
const deleted = [];
let cachedAssets;
const context = {
  URL,
  console,
  self: {
    location: { origin: 'https://example.test' },
    addEventListener: (type, fn) => { handlers[type] = fn; },
    skipWaiting() {},
    clients: { claim() {} },
  },
  caches: {
    open: async () => ({ addAll: async assets => { cachedAssets = assets; } }),
    match: async () => 'cached-public-asset',
    keys: async () => ['defect-tracker-v1.0.0', 'defect-tracker-v1.0.1', 'unrelated-app'],
    delete: async name => { deleted.push(name); },
  },
  fetch: async () => 'network-response',
};
for (const registrationFile of ['../index.html', '../main.js', '../login.php', '../js/offline-defect-queue.js', '../sync/init.php']) {
  const source = fs.readFileSync(require.resolve(registrationFile), 'utf8');
  assert(source.includes('/service-worker.js?v=1.1.0'), `${registrationFile} must cache-bust the field worker registration`);
}
vm.runInNewContext(fs.readFileSync(require.resolve('../service-worker.js'), 'utf8'), context);
async function fetchHandled(path, method = 'GET', mode = 'cors') {
  let response;
  handlers.fetch({ request: { url: new URL(path, context.self.location.origin).href, method, mode },
    respondWith(value) { response = value; } });
  return response === undefined ? undefined : await response;
}
(async () => {
  let pending;
  handlers.install({ waitUntil(promise) { pending = promise; } });
  await pending;
  assert(!cachedAssets.includes('/'), 'Must not precache authenticated root redirects');
  for (const path of ['/offline-field.html', '/js/offline-defect-queue.js', '/js/offline-field.js']) {
    assert(cachedAssets.includes(path), `${path} must be available for offline field capture`);
  }
  for (const path of ['/api/get_defect.php?id=334', '/uploads/defects/334/photo.jpg', '/login.php', '/css/app.css?v=2', 'https://cdn.example.test/file.js']) {
    assert.equal(await fetchHandled(path), undefined, path);
  }
  assert.equal(await fetchHandled('/css/app.css', 'POST'), undefined);
  assert.equal(await fetchHandled('/css/app.css'), 'cached-public-asset');
  assert.equal(await fetchHandled('/dashboard.php', 'GET', 'navigate'), 'network-response');
  context.fetch = async () => { throw new Error('offline'); };
  assert.equal(await fetchHandled('/dashboard.php', 'GET', 'navigate'), 'cached-public-asset');
  handlers.activate({ waitUntil(promise) { pending = promise; } });
  await pending;
  assert.deepEqual(deleted, ['defect-tracker-v1.0.0', 'defect-tracker-v1.0.1']);
  console.log('PASS: private data/mutations bypass cache; offline field shell and scoped cache cleanup work.');
})().catch(error => { console.error(error); process.exitCode = 1; });

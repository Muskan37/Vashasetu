// Vashasetu service worker (v3)
// Pages are fetched network-first, so a freshly uploaded index.php always shows.
// The cache is only used when you are offline.
const CACHE_NAME = 'vashasetu-shell-v31';
const SHELL_FILES = [
  './index.php',
  './learn.php',
  './assets/js/learn.js',
  './assets/css/learn.css',
  './manifest.json',
  './assets/css/app.css',
  './assets/js/app.js',
  './assets/js/bdmap.js',
  './assets/img/alpona-light.svg',
  './assets/img/alpona-dark.svg',
  './icons/icon-192.png',
  './icons/icon-512.png',
];

self.addEventListener('install', (event) => {
  // add files one by one so a single missing file can never block installation
  event.waitUntil(caches.open(CACHE_NAME).then((cache) =>
    Promise.all(SHELL_FILES.map((f) => cache.add(f).catch(() => {})))));
  self.skipWaiting();
});

// Delete every older cache (this clears stale copies from earlier versions)
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k))))
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);

  if (url.origin !== self.location.origin) return;        // not ours: let the browser handle it
  if (url.pathname.includes('/api/')) return;             // live data is never cached
  if (url.pathname.includes('/assets/ocr/')) return;      // big OCR files: browser caching only

  // Icons, manifest, fonts rarely change: cache-first
  if (/\/(icons\/|assets\/fonts\/|manifest\.json)/.test(url.pathname)) {
    event.respondWith(caches.match(req).then((hit) => hit || fetch(req).then((res) => {
      if (res && res.ok) { const copy = res.clone(); caches.open(CACHE_NAME).then((c) => c.put(req, copy)); }
      return res;
    })));
    return;
  }

  // Pages, CSS and JS: network-first, fall back to cache only when offline
  event.respondWith(
    fetch(req)
      .then((res) => {
        if (res && res.ok) { const copy = res.clone(); caches.open(CACHE_NAME).then((c) => c.put(req, copy)); }
        return res;
      })
      .catch(() => caches.match(req, { ignoreSearch: true }).then((hit) => hit || caches.match('./index.php')))
  );
});

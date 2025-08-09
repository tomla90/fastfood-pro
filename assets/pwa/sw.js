const CACHE = 'ffp-v1';
self.addEventListener('install', e => {
  e.waitUntil(caches.open(CACHE).then(c=>c.addAll([
    '/', // tilpass
  ])));
});
self.addEventListener('fetch', e => {
  e.respondWith(
    caches.match(e.request).then(r => r || fetch(e.request))
  );
});

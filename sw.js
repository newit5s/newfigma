const CACHE_NAME = 'rb-mobile-cache-v1';
const OFFLINE_HTML = '<!doctype html><html><head><meta charset="utf-8"><title>Offline</title><style>body{font-family:system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;background:#f8fafc;color:#0f172a;display:flex;min-height:100vh;align-items:center;justify-content:center;padding:2rem;text-align:center;}section{max-width:360px;}h1{font-size:1.5rem;margin-bottom:1rem;}button{margin-top:1.5rem;padding:0.6rem 1.2rem;border-radius:999px;border:none;background:#2563eb;color:#fff;font-weight:600;}</style></head><body><section><h1>Offline</h1><p>The restaurant manager portal is currently offline. Check your connection and try again.</p><button onclick="location.reload()">Retry</button></section></body></html>';

self.addEventListener('install', (event) => {
  event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((names) => Promise.all(names.filter((name) => name !== CACHE_NAME).map((name) => caches.delete(name)))).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const { request } = event;

  if (request.method !== 'GET') {
    return;
  }

  event.respondWith(
    caches.match(request).then((cached) => {
      if (cached) {
        return cached;
      }

      return fetch(request)
        .then((response) => {
          if (!response || response.status !== 200 || response.type !== 'basic') {
            return response;
          }

          const clone = response.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
          return response;
        })
        .catch(() => {
          if (request.mode === 'navigate') {
            return new Response(OFFLINE_HTML, { headers: { 'Content-Type': 'text/html; charset=utf-8' } });
          }

          return new Response('', { status: 503, statusText: 'Offline' });
        });
    })
  );
});

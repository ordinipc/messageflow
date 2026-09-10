/**
 * Service Worker - Contabilità Pro PWA
 * Cache-first per asset statici, network-first per API/pagine PHP
 */
const CACHE_NAME = 'contabilita-pro-v1';
const STATIC_CACHE = 'contabilita-pro-static-v1';

const PRECACHE_URLS = ['/app/accounting/?page=dashboard'];

self.addEventListener('install', function(event) {
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then(function(cache) {
                return cache.addAll(PRECACHE_URLS).catch(function(err) {
                    console.log('[SW] Pre-cache parziale:', err);
                });
            })
            .then(function() { return self.skipWaiting(); })
    );
});

self.addEventListener('activate', function(event) {
    event.waitUntil(
        caches.keys().then(function(names) {
            return Promise.all(
                names.filter(function(n) { return n !== CACHE_NAME && n !== STATIC_CACHE; })
                     .map(function(n) { return caches.delete(n); })
            );
        }).then(function() { return self.clients.claim(); })
    );
});

self.addEventListener('fetch', function(event) {
    var url = new URL(event.request.url);
    if (event.request.method !== 'GET') return;
    if (url.pathname.indexOf('/api/') !== -1 || url.pathname.indexOf('/eva') !== -1) return;

    if (url.pathname.match(/\.(png|jpg|jpeg|gif|svg|ico|js|css|woff|woff2|ttf)$/)) {
        event.respondWith(
            caches.match(event.request).then(function(cached) {
                if (cached) return cached;
                return fetch(event.request).then(function(response) {
                    if (response.ok) {
                        var clone = response.clone();
                        caches.open(STATIC_CACHE).then(function(cache) { cache.put(event.request, clone); });
                    }
                    return response;
                }).catch(function() {
                    if (event.request.url.match(/\.(png|jpg|jpeg|gif|svg)$/)) {
                        return new Response('<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100"><rect fill="#f0f0f0" width="100" height="100"/><text x="50" y="55" text-anchor="middle" fill="#999" font-size="12">Offline</text></svg>', { headers: { 'Content-Type': 'image/svg+xml' } });
                    }
                });
            })
        );
        return;
    }

    event.respondWith(
        fetch(event.request).then(function(response) {
            if (response.ok) {
                var clone = response.clone();
                caches.open(CACHE_NAME).then(function(cache) { cache.put(event.request, clone); });
            }
            return response;
        }).catch(function() {
            return caches.match(event.request).then(function(cached) {
                if (cached) return cached;
                return new Response('<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Offline</title><style>body{font-family:-apple-system,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#f7fafc;color:#1a202c;text-align:center}.box{padding:40px;background:#fff;border-radius:16px;box-shadow:0 4px 20px rgba(0,0,0,.1);max-width:400px}.icon{font-size:4rem;margin-bottom:15px}h2{margin-bottom:10px}p{color:#718096;margin-bottom:20px}button{padding:12px 30px;background:#667eea;color:#fff;border:none;border-radius:10px;font-size:1rem;cursor:pointer;font-weight:600}</style></head><body><div class="box"><div class="icon">📡</div><h2>Sei offline</h2><p>Controlla la tua connessione internet e riprova.</p><button onclick="location.reload()">🔄 Riprova</button></div></body></html>', { headers: { 'Content-Type': 'text/html; charset=UTF-8' } });
            });
        })
    );
});

/*
 * Service worker: makes the site installable and keeps it usable on a
 * flaky connection. Pages are network-first (fresh prices and stock),
 * static files are served from cache and refreshed in the background.
 * The admin panel and the API are never cached.
 */
var CACHE = 'kolibri-v1';
var OFFLINE = 'offline.html';

self.addEventListener('install', function (event) {
    event.waitUntil(caches.open(CACHE).then(function (cache) {
        return cache.addAll([OFFLINE, 'assets/icons/icon-192.png', 'admin/assets/img/kolibri-mark.svg']);
    }));
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(caches.keys().then(function (keys) {
        return Promise.all(keys.filter(function (k) { return k !== CACHE; }).map(function (k) { return caches.delete(k); }));
    }).then(function () { return self.clients.claim(); }));
});

self.addEventListener('fetch', function (event) {
    var req = event.request;
    var url = new URL(req.url);
    if (req.method !== 'GET' || url.origin !== location.origin || /\/(admin|api)\//.test(url.pathname)) {
        return;
    }

    if (req.mode === 'navigate') {
        event.respondWith(fetch(req).then(function (res) {
            var copy = res.clone();
            caches.open(CACHE).then(function (cache) { cache.put(req, copy); });
            return res;
        }).catch(function () {
            return caches.match(req).then(function (hit) { return hit || caches.match(OFFLINE); });
        }));
        return;
    }

    if (/\/(assets|uploads)\//.test(url.pathname)) {
        event.respondWith(caches.open(CACHE).then(function (cache) {
            return cache.match(req).then(function (hit) {
                var network = fetch(req).then(function (res) {
                    if (res.ok) { cache.put(req, res.clone()); }
                    return res;
                }).catch(function () { return hit; });
                return hit || network;
            });
        }));
    }
});

/*
 * Service worker: makes the site an installable app that opens instantly
 * and keeps working on a weak connection.
 * - pages: network first, but after 3.5 s the saved copy is shown;
 * - site files and photos: served from the cache, refreshed in the background;
 * - Google fonts: cached for good;
 * - the admin panel and the API are never cached.
 */
var CACHE = 'kolibri-v2';
var OFFLINE = 'offline.html';
var SHELL = ['./', OFFLINE, 'manifest.webmanifest', 'assets/icons/icon-192.png', 'admin/assets/img/kolibri-mark.svg'];

self.addEventListener('install', function (event) {
    event.waitUntil(caches.open(CACHE).then(function (cache) {
        return cache.addAll(SHELL).catch(function () { /* first visit offline: skip */ });
    }));
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(caches.keys().then(function (keys) {
        return Promise.all(keys.filter(function (k) { return k !== CACHE; }).map(function (k) { return caches.delete(k); }));
    }).then(function () { return self.clients.claim(); }));
});

function timeout(ms, promise) {
    return new Promise(function (resolve, reject) {
        var timer = setTimeout(function () { reject(new Error('timeout')); }, ms);
        promise.then(function (res) { clearTimeout(timer); resolve(res); }, function (err) { clearTimeout(timer); reject(err); });
    });
}

self.addEventListener('fetch', function (event) {
    var req = event.request;
    if (req.method !== 'GET') {
        return;
    }
    var url = new URL(req.url);

    if (/^fonts\.(googleapis|gstatic)\.com$/.test(url.hostname)) {
        event.respondWith(caches.open(CACHE).then(function (cache) {
            return cache.match(req).then(function (hit) {
                return hit || fetch(req).then(function (res) {
                    if (res.ok || res.type === 'opaque') { cache.put(req, res.clone()); }
                    return res;
                });
            });
        }));
        return;
    }

    if (url.origin !== location.origin || /\/(admin|api)\//.test(url.pathname)) {
        return;
    }

    if (req.mode === 'navigate') {
        var network = fetch(req).then(function (res) {
            if (res.ok) {
                var copy = res.clone();
                caches.open(CACHE).then(function (cache) { cache.put(req, copy); });
            }
            return res;
        });
        event.respondWith(timeout(3500, network).catch(function () {
            return caches.match(req, { ignoreSearch: true }).then(function (hit) {
                return hit || network.catch(function () { return caches.match(OFFLINE); });
            });
        }));
        return;
    }

    if (/\/(assets|uploads)\//.test(url.pathname) || url.pathname.endsWith('.webmanifest')) {
        event.respondWith(caches.open(CACHE).then(function (cache) {
            return cache.match(req).then(function (hit) {
                var fresh = fetch(req).then(function (res) {
                    if (res.ok) { cache.put(req, res.clone()); }
                    return res;
                }).catch(function () {
                    // an older version of the same file is better than nothing
                    return hit || cache.match(req, { ignoreSearch: true });
                });
                return hit || fresh;
            });
        }));
    }
});

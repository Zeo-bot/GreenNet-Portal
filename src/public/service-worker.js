const GREENNET_CACHE = 'greennet-static-v2.6.0';

const STATIC_ASSETS = [
    '/offline.html',
    '/manifest.webmanifest',
    '/img/greennet-icon.svg',
    '/css/app.css',
    '/css/subscriber.css',
    '/css/subscriber-app.css',
    '/js/pwa.js'
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(GREENNET_CACHE)
            .then((cache) => Promise.allSettled(STATIC_ASSETS.map((url) => cache.add(url))))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys
                    .filter((key) => key !== GREENNET_CACHE)
                    .map((key) => caches.delete(key))
            ))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    if (url.pathname.startsWith('/admin')) {
        event.respondWith(networkOnly(request));
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(navigationNoCache(request));
        return;
    }

    if (
        url.pathname.startsWith('/css/')
        || url.pathname.startsWith('/js/')
        || url.pathname.startsWith('/img/')
        || url.pathname === '/manifest.webmanifest'
    ) {
        event.respondWith(staticCache(request));
        return;
    }

    event.respondWith(networkOnly(request));
});

async function networkOnly(request) {
    return fetch(request);
}

async function navigationNoCache(request) {
    try {
        return await fetch(request, {
            cache: 'no-store',
            credentials: 'same-origin'
        });
    } catch (error) {
        return caches.match('/offline.html');
    }
}

async function staticCache(request) {
    const cache = await caches.open(GREENNET_CACHE);
    const cached = await cache.match(request);

    const fresh = fetch(request)
        .then((response) => {
            if (response && response.ok) {
                cache.put(request, response.clone());
            }

            return response;
        })
        .catch(() => cached);

    return cached || fresh;
}
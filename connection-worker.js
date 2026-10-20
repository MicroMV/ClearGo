'use strict';
const offlineCache = 'cleargo-offline-v6';
const offlineUrl = new URL('errors/offline.html', self.registration.scope).href;
const logoUrl = new URL('assets/the-lewis-college-logo.png', self.registration.scope).href;

self.addEventListener('install', event => {
    event.waitUntil(caches.open(offlineCache).then(cache => cache.addAll([offlineUrl, logoUrl])).then(() => self.skipWaiting()));
});
self.addEventListener('activate', event => {
    event.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(key => key.startsWith('cleargo-offline-') && key !== offlineCache)
        .map(key => caches.delete(key)))).then(() => self.clients.claim()));
});
self.addEventListener('fetch', event => {
    if (event.request.method === 'GET' && event.request.url === logoUrl) {
        event.respondWith(caches.open(offlineCache).then(cache => cache.match(logoUrl)).then(cached => cached || fetch(event.request)));
        return;
    }
    if (event.request.mode !== 'navigate') return;
    event.respondWith(fetch(event.request).catch(async () => {
        // Cache only the public screen and college logo. Student records and submitted forms are never cached.
        const fallback = await caches.match(offlineUrl);
        if (!fallback) return Response.error();
        const base = new URL(self.registration.scope).pathname.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
        return new Response((await fallback.text()).replace('__CLEARGO_BASE__', base).replace('__CLEARGO_LOGO__', base + 'assets/the-lewis-college-logo.png'), {
            status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'no-store' }
        });
    }));
});

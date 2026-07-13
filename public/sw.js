/**
 * Favilla — service worker (shell offline + Web Push).
 *
 * Registrato da app.js con l'URL esposto su <body data-sw-url>: lo scope è la
 * directory di public/, quindi tutti i path qui dentro sono RELATIVI allo
 * scope (funziona sia sotto /favilla/public/ in dev sia a root in Docker).
 *
 * Strategie fetch:
 *  - navigazioni  → network-first, fallback offline.html
 *  - GET /assets/ → cache-first con riempimento runtime (URL con ?v= inclusa)
 *  - tutto il resto → rete (nessuna interferenza con HTMX/API)
 */

'use strict';

const CACHE_NAME = 'favilla-shell-v2';
const OFFLINE_URL = 'offline.html';
const PRECACHE = [
    OFFLINE_URL,
    'assets/img/pwa/icon-192.png',
    'assets/images/logo.svg',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            // Per-item invece di addAll(): se un asset dà 404 non deve far
            // fallire l'INTERO install (che disattiverebbe l'offline del tutto).
            .then((cache) => Promise.allSettled(PRECACHE.map((path) => cache.add(path))))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
            ))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match(OFFLINE_URL))
        );
        return;
    }

    const url = new URL(request.url);
    if (url.origin === self.location.origin && url.pathname.includes('/assets/')) {
        event.respondWith(
            caches.match(request).then((cached) => {
                if (cached) {
                    return cached;
                }
                return fetch(request).then((response) => {
                    if (response.ok && response.type === 'basic') {
                        const copy = response.clone();
                        caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
                    }
                    return response;
                });
            })
        );
    }
});

self.addEventListener('push', (event) => {
    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch (err) {
        data = { title: 'Favilla', body: event.data ? event.data.text() : '' };
    }

    const title = data.title || 'Favilla';
    const options = {
        body: data.body || '',
        icon: 'assets/img/pwa/icon-192.png',
        badge: 'assets/img/pwa/icon-192.png',
        tag: data.tag || undefined,
        data: { url: data.url || '' },
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const raw = (event.notification.data && event.notification.data.url) || self.registration.scope;
    // data.url è un path (es. /favilla/public/...): normalizzalo ad assoluto,
    // altrimenti il confronto con client.url (sempre assoluto) non combacia mai
    // e si aprirebbe sempre una nuova finestra.
    const targetUrl = new URL(raw, self.registration.scope).href;

    event.waitUntil((async () => {
        const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });

        // 1) Finestra già sull'URL esatto → focus.
        for (const client of windows) {
            if (client.url === targetUrl && 'focus' in client) {
                return client.focus();
            }
        }
        // 2) Una qualsiasi finestra dell'app → portala in primo piano e naviga.
        for (const client of windows) {
            if ('focus' in client) {
                await client.focus();
                if ('navigate' in client) {
                    return client.navigate(targetUrl).catch(() => undefined);
                }
                return undefined;
            }
        }
        // 3) Nessuna finestra aperta → aprine una nuova.
        return self.clients.openWindow(targetUrl);
    })());
});

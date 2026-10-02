/*
 * Service worker for the installed website.
 *
 * The site is always loaded fresh from the server when there is a connection.
 * Without one (a border post, a stretch of road with no signal) the last copy
 * of each page and of each document opened is shown instead, so a driver can
 * still show his papers. Nothing is ever sent from the cache: forms need the
 * network. Signing out wipes every stored copy, so a shared phone keeps nothing.
 */
const VERSION = 'lms-v1';
const PAGES = VERSION + '-pages';
const STATIC = VERSION + '-static';
const MAX_PAGES = 60;

self.addEventListener('install', (event) => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((key) => !key.startsWith(VERSION)).map((key) => caches.delete(key))))
      .then(() => self.clients.claim())
  );
});

const offlinePage = () => new Response(
  '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">' +
  '<title>Offline</title><body style="font:16px/1.5 sans-serif;padding:24px;color:#17212b;background:#f2f4f7">' +
  '<h2 style="color:#1f4e8c">No connection</h2><p>This page has not been opened on this phone before, so there is no saved copy.</p>' +
  '<p>Pages and documents you opened while online are still available. Try again when you have signal.</p>' +
  '<p><a href="javascript:history.back()">Go back</a></p></body>',
  { headers: { 'Content-Type': 'text/html; charset=utf-8' } }
);

async function trim(cacheName, max) {
  const cache = await caches.open(cacheName);
  const keys = await cache.keys();
  for (let i = 0; i < keys.length - max; i++) {
    await cache.delete(keys[i]);
  }
}

async function networkFirst(request, cacheName) {
  try {
    const response = await fetch(request);
    // Only real pages are kept: never a redirect to the sign-in form or an error.
    if (response.ok && !response.redirected && response.type === 'basic') {
      const cache = await caches.open(cacheName);
      cache.put(request, response.clone());
      trim(cacheName, MAX_PAGES);
    }
    return response;
  } catch (error) {
    const cached = await caches.match(request);
    if (cached) {
      return cached;
    }
    return request.mode === 'navigate' ? offlinePage() : Response.error();
  }
}

async function staleWhileRevalidate(request) {
  const cache = await caches.open(STATIC);
  const cached = await cache.match(request);
  const fresh = fetch(request).then((response) => {
    if (response.ok) {
      cache.put(request, response.clone());
    }
    return response;
  }).catch(() => cached);
  return cached || fresh;
}

self.addEventListener('fetch', (event) => {
  const request = event.request;
  if (request.method !== 'GET') {
    return;
  }

  const url = new URL(request.url);
  if (url.origin !== self.location.origin) {
    return;
  }

  // Signing out: forget everything this phone stored for the account.
  if (/\/logout$/.test(url.pathname)) {
    event.respondWith(
      caches.keys()
        .then((keys) => Promise.all(keys.map((key) => caches.delete(key))))
        .then(() => fetch(request))
    );
    return;
  }

  // Exports and downloads are made fresh every time and are not kept.
  if (/\/export(\/|$)/.test(url.pathname)) {
    return;
  }

  if (url.pathname.includes('/assets/')) {
    event.respondWith(staleWhileRevalidate(request));
    return;
  }

  if (request.mode === 'navigate' || url.pathname.includes('/files/')) {
    event.respondWith(networkFirst(request, PAGES));
  }
});

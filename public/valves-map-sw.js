// The legacy valves map registered a tile-caching service worker at this URL.
// The platform does not use it: this stub replaces it once, clears its cache and unregisters.
self.addEventListener('install', () => self.skipWaiting())

self.addEventListener('activate', event => {
    event.waitUntil((async () => {
        await caches.delete('valves-wms-tiles-v1')
        await self.registration.unregister()
        const clients = await self.clients.matchAll({ type: 'window' })
        clients.forEach(client => client.navigate(client.url))
    })())
})

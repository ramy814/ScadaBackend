const CACHE_NAME = 'valves-wms-tiles-v1'
const CACHE_TTL = 30 * 24 * 60 * 60 * 1000
const MAX_TILES = 800

self.addEventListener('install', () => self.skipWaiting())

self.addEventListener('activate', event => {
    event.waitUntil(self.clients.claim())
})

const isWmsTile = request => {
    const url = new URL(request.url)

    return request.method === 'GET'
        && url.origin === self.location.origin
        && url.pathname === '/wms.php'
        && (url.searchParams.get('request') ?? '').toLowerCase() === 'getmap'
}

const cacheResponse = async (cache, request, response) => {
    const isFallback = response.headers.has('X-WMS-Proxy-Fallback')
    const isImage = (response.headers.get('Content-Type') ?? '').startsWith('image/')

    if (!response.ok || !isImage || isFallback) return response

    const headers = new Headers(response.headers)
    headers.set('X-Local-Cache-Time', String(Date.now()))
    const cachedResponse = new Response(await response.clone().blob(), {
        status: response.status,
        statusText: response.statusText,
        headers,
    })

    await cache.put(request, cachedResponse)

    const keys = await cache.keys()
    await Promise.all(keys.slice(0, Math.max(0, keys.length - MAX_TILES)).map(key => cache.delete(key)))

    return response
}

self.addEventListener('fetch', event => {
    if (!isWmsTile(event.request)) return

    event.respondWith((async () => {
        const cache = await caches.open(CACHE_NAME)
        const cached = await cache.match(event.request)
        const cachedAt = Number(cached?.headers.get('X-Local-Cache-Time') ?? 0)

        if (cached && Date.now() - cachedAt < CACHE_TTL) return cached

        try {
            const response = await fetch(event.request)
            if (cached && response.headers.has('X-WMS-Proxy-Fallback')) return cached

            return await cacheResponse(cache, event.request, response)
        } catch (error) {
            if (cached) return cached
            throw error
        }
    })())
})

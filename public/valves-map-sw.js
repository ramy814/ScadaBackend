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

const normalizeTileResponse = async response => {
    const body = await response.arrayBuffer()
    const bytes = new Uint8Array(body)
    const isPng = bytes.length >= 8
        && bytes[0] === 0x89 && bytes[1] === 0x50 && bytes[2] === 0x4e && bytes[3] === 0x47
        && bytes[4] === 0x0d && bytes[5] === 0x0a && bytes[6] === 0x1a && bytes[7] === 0x0a
    const headers = new Headers(response.headers)

    if (isPng) headers.set('Content-Type', 'image/png')

    return {
        isPng,
        response: new Response(body, {
            status: response.status,
            statusText: response.statusText,
            headers,
        }),
    }
}

self.addEventListener('fetch', event => {
    if (!isWmsTile(event.request)) return

    event.respondWith((async () => {
        const cache = await caches.open(CACHE_NAME)
        const cached = await cache.match(event.request)
        const cachedAt = Number(cached?.headers.get('X-Local-Cache-Time') ?? 0)
        const fallback = cached && Date.now() - cachedAt < CACHE_TTL ? cached : null

        try {
            const upstream = await fetch(event.request)
            const normalized = await normalizeTileResponse(upstream)
            if (fallback && (!upstream.ok || !normalized.isPng || upstream.headers.has('X-WMS-Proxy-Fallback'))) return fallback

            return await cacheResponse(cache, event.request, normalized.response)
        } catch (error) {
            if (fallback) return fallback
            throw error
        }
    })())
})

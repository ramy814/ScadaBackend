<script setup>
import { nextTick, ref, onBeforeUnmount, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/ValvesSystem/Layouts/AppLayout.vue'

const props = defineProps({
    mapValves: {
        type: Array,
        default: () => [],
    },
})

// GeoServer direct URL (browser has network access to GeoServer directly)
const GEOSERVER_BASE = 'http://appdev-01.mog.org:8080/geoserver'
const GEOSERVER_WMS_URL = GEOSERVER_BASE + '/cite/wms'

let map = null
let L = null
let mapResizeObserver = null
let mapResizeTimer = null
let geoServerHealthTimer = null
let lastGeoServerTileLoadedAt = 0
let featureInfoAbortController = null

const mapContainer = ref(null)
const geoServerHealth = ref({
    checking: true,
    ok: true,
    message: '',
})

const geoServerLayers = [
    { layerName: 'cite:V_BUILDING_GEOSRV', name: 'المباني', version: '1.1.0', pane: 'municipalContextPane', zIndex: 330, opacity: 0.9 },
    { layerName: 'cite:V_CENTERLINE_SEGMENTS', name: 'محاور الشوارع', version: '1.1.0', pane: 'municipalContextPane', zIndex: 360, opacity: 0.9 },
    // { layerName: 'cite:V_LANDMARKS_V', name: 'المعالم', version: '1.1.0', pane: 'municipalContextPane', zIndex: 390, opacity: 0.9 },
    { layerName: 'cite:WATER_PIPE', name: 'شبكة الأنابيب', version: '1.1.0', pane: 'networkPane', zIndex: 650, opacity: 0.95 },
    { layerName: 'cite:WATER_VALVES', name: 'محابس المياه', version: '1.1.1', pane: 'valvesPane', zIndex: 720, opacity: 1 },
]

const identifyPriority = [
    'cite:WATER_VALVES',
    'cite:WATER_PIPE',
    'cite:V_LANDMARKS_V',
    'cite:V_CENTERLINE_SEGMENTS',
    'cite:V_BUILDING_GEOSRV',
]

const featureFieldLabels = {
    OID: 'المعرف',
    OBJECTID: 'المعرف',
    ID: 'المعرف',
    FID: 'المعرف',
    NAME: 'الاسم',
    NAME_AR: 'الاسم',
    CODE: 'الكود',
    VALVE_CODE: 'كود المحبس',
    DIAMETER: 'القطر',
    MATERIAL: 'المادة',
    TYPE: 'النوع',
    STATUS: 'الحالة',
    AREA: 'المنطقة',
    STREET: 'الشارع',
    LENGTH: 'الطول',
    ELEVATION: 'المنسوب',
}

const hiddenFeatureFields = new Set([
    'GEOM',
    'GEOMETRY',
    'THE_GEOM',
    'SHAPE',
    'SHAPE_AREA',
    'SHAPE_LENGTH',
    'BOUNDED_BY',
    'BOUNDEDBY',
])

const statusLabel = (s) => ({
    open: 'مفتوح',
    closed: 'مغلق',
    maintenance: 'صيانة',
    replacement: 'استبدال',
}[s] ?? s)

const escapeHtml = (value) => String(value ?? '-')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;')

const normalizeFeatureKey = (key) => String(key).toUpperCase().replace(/^.*:/, '')

const featureTitle = (properties, fallback) => {
    const titleKeys = ['NAME_AR', 'NAME', 'VALVE_CODE', 'CODE', 'OBJECTID', 'OID', 'ID']
    const key = titleKeys.find(item => properties?.[item] || properties?.[item.toLowerCase()])
    return key ? (properties[key] ?? properties[key.toLowerCase()]) : fallback
}

const featureEntries = (properties = {}) => Object.entries(properties)
    .filter(([key, value]) => {
        const normalized = normalizeFeatureKey(key)
        return !hiddenFeatureFields.has(normalized) && value !== null && value !== undefined && value !== ''
    })
    .slice(0, 10)
    .map(([key, value]) => ({
        label: featureFieldLabels[normalizeFeatureKey(key)] ?? key.replaceAll('_', ' '),
        value,
    }))

const buildMapPopup = ({ kicker, title, entries, accent = '#38bdf8' }) => {
    const rows = entries.length
        ? entries.map(entry => `
            <div class="v-map-popup-row">
                <span>${escapeHtml(entry.label)}</span>
                <b>${escapeHtml(entry.value)}</b>
            </div>
        `).join('')
        : '<div class="v-map-popup-empty">لا توجد تفاصيل إضافية لهذا العنصر</div>'

    return `
        <div class="v-map-popup" style="--popup-accent: ${escapeHtml(accent)};">
            <div class="v-map-popup-header">
                <div class="v-map-popup-kicker">${escapeHtml(kicker)}</div>
                <div class="v-map-popup-title">${escapeHtml(title)}</div>
            </div>
            <div class="v-map-popup-grid">${rows}</div>
        </div>
    `
}

const mapBoundsBbox = () => {
    const bounds = map.getBounds()
    const sw = map.options.crs.project(bounds.getSouthWest())
    const ne = map.options.crs.project(bounds.getNorthEast())
    return [sw.x, sw.y, ne.x, ne.y].join(',')
}

const htmlToText = (html) => {
    const wrapper = document.createElement('div')
    wrapper.innerHTML = html
    return wrapper.textContent?.replace(/\s+/g, ' ').trim() ?? ''
}

const htmlFeatureEntries = (html) => {
    const wrapper = document.createElement('div')
    wrapper.innerHTML = html
    const rows = Array.from(wrapper.querySelectorAll('tr'))

    return rows.flatMap((row) => {
        const cells = Array.from(row.querySelectorAll('th,td'))
            .map(cell => cell.textContent?.replace(/\s+/g, ' ').trim())
            .filter(Boolean)

        if (cells.length < 2) return []

        const [label, value] = cells
        if (/feature|fid|bounded|geom|shape/i.test(label)) return []

        return [{ label: featureFieldLabels[normalizeFeatureKey(label)] ?? label, value }]
    }).slice(0, 10)
}

const textFeatureEntries = (text) => text
    .split(/[\n\r]+| {2,}/)
    .map(value => value.trim())
    .filter(Boolean)
    .slice(0, 8)
    .map((value, index) => ({ label: index === 0 ? 'البيانات' : `تفصيل ${index + 1}`, value }))

const getFeatureInfoUrl = (layerConfig, point, infoFormat = 'application/json') => {
    const size = map.getSize()
    const params = new URLSearchParams({
        service: 'WMS',
        request: 'GetFeatureInfo',
        version: layerConfig.version,
        layers: layerConfig.layerName,
        query_layers: layerConfig.layerName,
        styles: '',
        bbox: mapBoundsBbox(),
        width: size.x,
        height: size.y,
        srs: 'EPSG:3857',
        x: Math.round(point.x),
        y: Math.round(point.y),
        info_format: infoFormat,
        feature_count: '5',
    })

    // GetFeatureInfo must go through PHP proxy (CORS: fetch() is blocked directly to GeoServer)
    // WMS tiles use direct URL because <img> tags bypass CORS restrictions
    return `/wms.php?${params.toString()}`
}

const readFeatureInfo = async (layerConfig, point, signal = null) => {
    const fetchOptions = signal ? { signal, headers: { Accept: 'application/json' } } : { headers: { Accept: 'application/json' } }
    const jsonResponse = await fetch(getFeatureInfoUrl(layerConfig, point), fetchOptions)

    if (jsonResponse.ok) {
        const contentType = jsonResponse.headers.get('content-type') ?? ''
        if (contentType.includes('json')) {
            const data = await jsonResponse.json()
            const feature = data?.features?.[0]
            if (feature?.properties) {
                return {
                    title: featureTitle(feature.properties, layerConfig.name),
                    entries: featureEntries(feature.properties),
                }
            }
        }
    }

    const htmlFetchOptions = signal ? { signal, headers: { Accept: 'text/html,text/plain' } } : { headers: { Accept: 'text/html,text/plain' } }
    const htmlResponse = await fetch(getFeatureInfoUrl(layerConfig, point, 'text/html'), htmlFetchOptions)

    if (!htmlResponse.ok) return null

    const html = await htmlResponse.text()
    const text = htmlToText(html)
    if (/failed to run|getfeatureinfo|exception|propertydescriptor/i.test(text)) return null

    const entries = htmlFeatureEntries(html).length ? htmlFeatureEntries(html) : textFeatureEntries(text)
    if (!entries.length) return null

    return {
        title: layerConfig.name,
        entries,
    }
}

const resizeMap = () => {
    if (!map) return

    window.requestAnimationFrame(() => {
        map?.invalidateSize({ animate: false, pan: false })
    })
}

const scheduleMapResize = () => {
    if (mapResizeTimer) {
        window.clearTimeout(mapResizeTimer)
    }

    resizeMap()
    mapResizeTimer = window.setTimeout(resizeMap, 160)
}

const checkGeoServerHealth = async () => {
    try {
        const response = await fetch('/wms.php?health=1', {
            headers: { Accept: 'application/json' },
            cache: 'no-store',
        })
        const data = await response.json().catch(() => ({}))
        const recentTileLoaded = Date.now() - lastGeoServerTileLoadedAt < 60000

        geoServerHealth.value = {
            checking: false,
            ok: (response.ok && data?.ok === true) || recentTileLoaded,
            message: data?.message || 'تعذر الاتصال بخادم الخرائط GeoServer.',
        }
    } catch (error) {
        const recentTileLoaded = Date.now() - lastGeoServerTileLoadedAt < 60000

        geoServerHealth.value = {
            checking: false,
            ok: recentTileLoaded,
            message: 'تعذر الاتصال بخادم الخرائط GeoServer.',
        }
    }
}

const initMap = async () => {
    if (map) return
    await nextTick()
    if (!mapContainer.value) return

    L = (await import('leaflet')).default
    await import('leaflet/dist/leaflet.css')

    map = L.map(mapContainer.value, {
        center: [31.5, 34.47],
        zoom: 13,
        zoomControl: true,
        preferCanvas: true,
    })

    const createPane = (name, zIndex) => {
        const pane = map.createPane(name)
        pane.style.zIndex = zIndex
        pane.style.pointerEvents = 'auto'
    }

    createPane('municipalContextPane', 350)
    createPane('networkPane', 650)
    createPane('valvesPane', 720)
    map.getPane('popupPane').style.zIndex = 1100

    const noBaseLayer = L.layerGroup().addTo(map)
    const geoLayerRefs = new Map()

    const osmLayer = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19,
    })

    const overlayLayers = {}

    geoServerLayers.forEach((layerConfig) => {
        const layer = L.tileLayer.wms(GEOSERVER_WMS_URL, {
            layers: layerConfig.layerName,
            version: layerConfig.version,
            format: 'image/png',
            transparent: true,
            opacity: layerConfig.opacity,
            pane: layerConfig.pane,
            maxZoom: 19,
            zIndex: layerConfig.zIndex,
            tileSize: 256,
            tiled: true,
            updateWhenIdle: true,
            updateWhenZooming: false,
            keepBuffer: 2,
            attribution: '',
            maxNativeZoom: 19,
            errorTileUrl: 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=',
        })

        layer.on('tileload', () => {
            lastGeoServerTileLoadedAt = Date.now()
            geoServerHealth.value = { ...geoServerHealth.value, checking: false, ok: true }
        })

        layer.on('tileerror', (e) => {
            console.warn('[WMS] Tile error for', layerConfig.layerName, e)
        })

        if (layerConfig.visible !== false) {
            layer.addTo(map)
        }
        geoLayerRefs.set(layerConfig.layerName, layer)
        overlayLayers[layerConfig.name] = layer
    })
    L.control.layers(
        {
            'OpenStreetMap': osmLayer,
            'بدون OpenStreetMap': noBaseLayer
        },
        overlayLayers,
        { collapsed: false }
    ).addTo(map)



    map.on('click', async (event) => {
        // Cancel any ongoing feature info request
        if (featureInfoAbortController) {
            featureInfoAbortController.abort()
        }
        featureInfoAbortController = new AbortController()
        const signal = featureInfoAbortController.signal

        const point = map.latLngToContainerPoint(event.latlng)
        const orderedLayers = identifyPriority
            .map(layerName => geoServerLayers.find(item => item.layerName === layerName))
            .filter(layerConfig => layerConfig && map.hasLayer(geoLayerRefs.get(layerConfig.layerName)))

        for (const layerConfig of orderedLayers) {
            if (signal.aborted) break
            try {
                const featureInfo = await readFeatureInfo(layerConfig, point, signal)
                if (signal.aborted) break
                if (!featureInfo) continue

                L.popup({ maxWidth: 340, className: 'v-leaflet-popup' })
                    .setLatLng(event.latlng)
                    .setContent(buildMapPopup({
                        kicker: layerConfig.name,
                        title: featureInfo.title,
                        entries: featureInfo.entries,
                        accent: layerConfig.layerName === 'cite:WATER_VALVES' ? '#06b6d4' : '#38bdf8',
                    }))
                    .openOn(map)
                return
            } catch (error) {
                if (error.name === 'AbortError') break
                // Some GeoServer layers may not support JSON GetFeatureInfo; try the next visible layer.
            }
        }
    })

    if (props.mapValves.length) {
        const bounds = props.mapValves.map(v => [v.latitude, v.longitude])
        map.fitBounds(bounds, { padding: [40, 40], maxZoom: 16 })
    }

    mapResizeObserver = new ResizeObserver(scheduleMapResize)
    mapResizeObserver.observe(mapContainer.value)
    window.addEventListener('resize', scheduleMapResize)

    ;[0, 120, 350, 800].forEach(delay => {
        window.setTimeout(scheduleMapResize, delay)
    })

    checkGeoServerHealth()
    geoServerHealthTimer = window.setInterval(checkGeoServerHealth, 30000)
}

onMounted(initMap)

onBeforeUnmount(() => {
    window.removeEventListener('resize', scheduleMapResize)
    mapResizeObserver?.disconnect()
    mapResizeObserver = null
    if (mapResizeTimer) {
        window.clearTimeout(mapResizeTimer)
        mapResizeTimer = null
    }
    if (geoServerHealthTimer) {
        window.clearInterval(geoServerHealthTimer)
        geoServerHealthTimer = null
    }
    if (featureInfoAbortController) {
        featureInfoAbortController.abort()
        featureInfoAbortController = null
    }

    if (map) {
        map.remove()
        map = null
    }
})
</script>

<template>
    <Head title="خريطة المحابس" />
    <AppLayout>
        <div class="v-page-header">
            <div>
                <h2 class="v-page-title">خريطة المحابس البلدية</h2>
                <p class="v-page-subtitle">طبقات GeoServer ومواقع المحابس في شاشة مستقلة للقراءة والتشغيل الميداني</p>
            </div>
        </div>

        <div class="v-card">
            <div class="v-card-header">
                <h3 class="v-card-title">خريطة المحابس</h3>
                <span style="font-size: 0.75rem; color: var(--color-text-muted);">انقر على أي عنصر لعرض بياناته</span>
            </div>
            <div class="v-card-body" style="padding: 0;">
                <div class="v-map-container v-map-container-large">
                    <div ref="mapContainer" id="valves-map" dir="ltr" style="width: 100%; height: 100%;"></div>

                    <div v-if="!geoServerHealth.checking && !geoServerHealth.ok" class="v-map-outage" role="status">
                        <strong>تعذر تحميل طبقات الخريطة</strong>
                        <span>{{ geoServerHealth.message }}</span>
                    </div>

                    <div class="v-map-legend">
                        <div class="v-legend-item">
                            <div class="v-legend-dot" style="background: #22c55e;"></div>
                            <span>مفتوح</span>
                        </div>
                        <div class="v-legend-item">
                            <div class="v-legend-dot" style="background: #94a3b8;"></div>
                            <span>مغلق</span>
                        </div>
                        <div class="v-legend-item">
                            <div class="v-legend-dot" style="background: #f59e0b;"></div>
                            <span>صيانة</span>
                        </div>
                        <div class="v-legend-item">
                            <div class="v-legend-dot" style="background: #ef4444;"></div>
                            <span>استبدال</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

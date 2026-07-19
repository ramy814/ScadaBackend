<script setup>
import { nextTick, ref, onBeforeUnmount, onMounted } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/ValvesSystem/Layouts/AppLayout.vue'

const props = defineProps({
    mapValves: {
        type: Array,
        default: () => [],
    },
    mapFeaturesUrl: {
        type: String,
        required: true,
    },
})

const GEOSERVER_WMS_URL = window.location.protocol === 'https:'
    ? '/wms.php?g_page=/cite/wms'
    : 'http://appdev-01.mog.org:8080/geoserver/cite/wms'

const geoServerLayers = [
    { layerName: 'cite:V_BUILDING_GEOSRV', name: 'المباني', version: '1.1.0', pane: 'municipalContextPane', opacity: 0.9, visible: false },
    { layerName: 'cite:V_CENTERLINE_SEGMENTS', name: 'محاور الشوارع', version: '1.1.0', pane: 'municipalContextPane', opacity: 0.9 },
    { layerName: 'cite:V_LANDMARKS_V2', name: 'المعالم', version: '1.1.0', pane: 'municipalContextPane', opacity: 0.9 },
    { layerName: 'cite:WATER_PIPE', name: 'شبكة الأنابيب', version: '1.1.0', pane: 'networkPane', opacity: 0.95 },
    { layerName: 'cite:WATER_VALVES', name: 'محابس المياه', version: '1.1.1', pane: 'valvesPane', opacity: 1 },
]

let map = null
let mapResizeObserver = null
let mapResizeTimer = null
let featureRequestController = null

const mapContainer = ref(null)
const geoServerMode = ref(false)
const geoServerStatus = ref('انقر على أي عنصر ظاهر لعرض بياناته')

const geoServerLayerDetails = {
    V_BUILDING_GEOSRV: { name: 'مبنى', accent: '#64748b' },
    V_CENTERLINE_SEGMENTS: { name: 'محور شارع', accent: '#0ea5e9' },
    V_LANDMARKS_V2: { name: 'مَعْلم', accent: '#8b5cf6' },
    WATER_PIPE: { name: 'أنبوب مياه', accent: '#2563eb' },
    WATER_VALVES: { name: 'محبس مياه', accent: '#f97316' },
}

const geoServerPropertyLabels = {
    W4_GENERAL_ID: 'المعرّف العام',
    W4_STREET_NO: 'رقم الشارع',
    W4_VALVE_TYPE: 'نوع المحبس',
    W4_VALVE_DIAMETER: 'قطر المحبس',
    W4_MANUFACTURER: 'الشركة المصنّعة',
    W4_OPERATION: 'التشغيل',
    W4_STATUS: 'الحالة',
    W4_PROJECT_NO: 'رقم المشروع',
    W4_SOURCE: 'المصدر',
    W4_QUALITY: 'الجودة',
    W4_VALVE_NO: 'رقم المحبس',
    W4_VALVE_WIDTH: 'عرض المحبس',
    W4_VALVE_POSITION: 'موضع المحبس',
    W4_FLANGE_BOLTS: 'مسامير الفلنجة',
    W4_BONNET_BOLTS: 'مسامير الغطاء',
    W4_VALVE_OPEN: 'الفتح والإغلاق',
    W4_MANHOLE_NO: 'رقم المنهل',
    W4_VALVE_FITTINGS_1: 'الوصلة الأولى',
    W4_VALVE_FITTINGS_2: 'الوصلة الثانية',
    W4_PART_NO: 'رقم القطعة',
    NAME: 'الاسم',
    ARABIC_NAME: 'الاسم العربي',
    STREET_NAME: 'اسم الشارع',
    STREET_NUM: 'رقم الشارع',
    ST_NAME: 'اسم الشارع',
    ST_CLASS: 'تصنيف الشارع',
    SEGMENT_NUM: 'رقم المقطع',
    SEG_WIDTH: 'عرض المقطع',
    REAL_SEG_WIDTH: 'العرض الفعلي',
    ORG_LINE_STATUS: 'حالة المحور',
    DIAMETER: 'القطر',
    PIPE_DIAMETER: 'قطر الأنبوب',
    PIPE_TYPE: 'نوع الأنبوب',
    PIPE_MATERIAL: 'مادة الأنبوب',
    MATERIAL: 'المادة',
    STATUS: 'الحالة',
    TYPE: 'النوع',
    LENGTH: 'الطول',
    OBJECTID: 'المعرّف',
    OID: 'المعرّف',
}

const escapeHtml = (value) => String(value ?? '-')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;')

const valvePopupEntries = (valve) => [
    { label: 'كود المحبس', value: valve.code },
    { label: 'المعرّف العام', value: valve.oid },
    { label: 'رقم المحبس', value: valve.valve_no },
    { label: 'النوع', value: valve.valve_type },
    { label: 'القطر', value: valve.valve_diameter },
    { label: 'حالة المصدر', value: valve.status },
    { label: 'الفتح والإغلاق', value: valve.valve_open },
    { label: 'التشغيل', value: valve.operation },
    { label: 'الشركة المصنّعة', value: valve.manufacturer },
    { label: 'رقم الشارع', value: valve.street_no },
    { label: 'رقم المشروع', value: valve.project_no },
    { label: 'المصدر', value: valve.source },
    { label: 'الجودة', value: valve.quality },
].filter(entry => entry.value !== null && entry.value !== undefined && entry.value !== '')

const buildValvePopup = (valve) => {
    const rows = valvePopupEntries(valve).map(entry => `
        <div class="v-map-popup-row">
            <span>${escapeHtml(entry.label)}</span>
            <b>${escapeHtml(entry.value)}</b>
        </div>
    `).join('')

    return `
        <div class="v-map-popup" style="--popup-accent: #f97316;">
            <div class="v-map-popup-header">
                <div class="v-map-popup-kicker">بيانات EME.WATER_VALVES</div>
                <div class="v-map-popup-title">${escapeHtml(valve.name || valve.code || `محبس ${valve.oid}`)}</div>
            </div>
            <div class="v-map-popup-grid">${rows}</div>
        </div>
    `
}

const geoServerFeatureEntries = (feature) => Object.entries(feature)
    .filter(([key, value]) => !key.startsWith('_')
        && value !== null
        && value !== ''
        && ['string', 'number', 'boolean'].includes(typeof value))
    .slice(0, 18)

const geoServerPropertyLabel = (key) => geoServerPropertyLabels[key]
    ?? key.replaceAll('_', ' ')

const geoServerFeatureTitle = (feature) => {
    const layer = geoServerLayerDetails[feature._feature_layer] ?? {
        name: feature._feature_layer || 'عنصر جغرافي',
        accent: '#0284c7',
    }
    const identifier = feature.W4_GENERAL_ID
        ?? feature.OBJECTID
        ?? feature.OID
        ?? feature.ID

    return {
        ...layer,
        title: identifier === null || identifier === undefined || identifier === ''
            ? layer.name
            : `${layer.name} #${identifier}`,
    }
}

const buildGeoServerPopup = (features) => {
    if (!features.length) {
        return `
            <div class="v-map-popup v-map-popup-geo">
                <div class="v-map-popup-header" style="--popup-accent: #f59e0b;">
                    <div class="v-map-popup-kicker">بيانات GeoServer</div>
                    <div class="v-map-popup-title">لا توجد بيانات في موضع النقر</div>
                </div>
                <div class="v-map-popup-empty">قرّب الخريطة أو فعّل الطبقة المطلوبة ثم حاول مرة أخرى.</div>
            </div>
        `
    }

    const visibleFeatures = features.slice(0, 12)
    const cards = visibleFeatures.map((feature) => {
        const details = geoServerFeatureTitle(feature)
        const rows = geoServerFeatureEntries(feature).map(([key, value]) => `
            <div class="v-map-popup-row">
                <span>${escapeHtml(geoServerPropertyLabel(key))}</span>
                <b>${escapeHtml(value)}</b>
            </div>
        `).join('')

        return `
            <section class="v-map-popup-feature">
                <div class="v-map-popup-feature-title" style="--feature-accent: ${details.accent};">
                    <span>${escapeHtml(details.name)}</span>
                    <strong>${escapeHtml(details.title)}</strong>
                </div>
                <div class="v-map-popup-grid">${rows || '<div class="v-map-popup-empty">لا توجد خصائص متاحة.</div>'}</div>
            </section>
        `
    }).join('')
    const remaining = features.length - visibleFeatures.length

    return `
        <div class="v-map-popup v-map-popup-geo">
            <div class="v-map-popup-header" style="--popup-accent: #0284c7;">
                <div class="v-map-popup-kicker">بيانات الطبقات النشطة</div>
                <div class="v-map-popup-title">${features.length.toLocaleString('ar-EG')} عنصر في موضع النقر</div>
            </div>
            <div class="v-map-popup-features">${cards}</div>
            ${remaining > 0 ? `<div class="v-map-popup-more">قرّب الخريطة لعرض ${remaining.toLocaleString('ar-EG')} نتيجة إضافية بدقة أكبر.</div>` : ''}
        </div>
    `
}

const buildGeoServerFeatureUrl = (latLng, layerNames) => {
    const params = new URLSearchParams({
        layers: layerNames.join(','),
        lat: latLng.lat,
        lng: latLng.lng,
        zoom: map?.getZoom() ?? 12,
    })

    return `${props.mapFeaturesUrl}?${params.toString()}`
}

const buildGeoServerStatePopup = (title, accent = '#0284c7', message = '') => `
    <div class="v-map-popup v-map-popup-geo">
        <div class="v-map-popup-header" style="--popup-accent: ${accent};">
            <div class="v-map-popup-kicker">بيانات GeoServer</div>
            <div class="v-map-popup-title">${escapeHtml(title)}</div>
        </div>
        ${message ? `<div class="v-map-popup-empty">${escapeHtml(message)}</div>` : ''}
    </div>
`

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

const initMap = async () => {
    if (map) return
    await nextTick()
    if (!mapContainer.value) return

    const L = (await import('leaflet')).default
    await import('leaflet/dist/leaflet.css')

    const gazaBounds = L.latLngBounds([31.15, 34.15], [31.65, 34.65])
    map = L.map(mapContainer.value, {
        center: [31.45, 34.4],
        zoom: 12,
        minZoom: 10,
        maxBounds: gazaBounds,
        maxBoundsViscosity: 1,
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

    const osmTiles = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19,
    })
    const databaseValveMarkers = L.layerGroup()
    const valveBounds = []

    props.mapValves.forEach((valve) => {
        const latitude = Number(valve.latitude)
        const longitude = Number(valve.longitude)
        if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) return

        const latLng = [latitude, longitude]
        L.circleMarker(latLng, {
            radius: 6,
            color: '#fff7ed',
            weight: 2,
            fillColor: '#f97316',
            fillOpacity: 0.95,
            bubblingMouseEvents: false,
        })
            .bindPopup(buildValvePopup(valve), {
                maxWidth: 380,
                className: 'v-leaflet-popup',
            })
            .addTo(databaseValveMarkers)

        valveBounds.push(latLng)
    })

    const openStreetMap = L.layerGroup([osmTiles, databaseValveMarkers]).addTo(map)
    const geoServerMap = L.layerGroup()
    const geoServerOverlays = {}
    const geoServerOverlaysByLayerName = {}
    const defaultGeoServerLayers = []

    geoServerLayers.forEach((layerConfig) => {
        const layer = L.tileLayer.wms(GEOSERVER_WMS_URL, {
            layers: layerConfig.layerName,
            version: layerConfig.version,
            format: 'image/png',
            transparent: true,
            opacity: layerConfig.opacity,
            pane: layerConfig.pane,
            maxZoom: 19,
            tileSize: 256,
            tiled: true,
            updateWhenIdle: true,
            updateWhenZooming: false,
            keepBuffer: 2,
            attribution: '',
            errorTileUrl: 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=',
        })

        geoServerOverlays[layerConfig.name] = layer
        geoServerOverlaysByLayerName[layerConfig.layerName] = layer
        if (layerConfig.visible !== false) defaultGeoServerLayers.push(layer)
    })

    const showGeoServerPopup = (latLng, content) => {
        L.popup({
            maxWidth: 440,
            className: 'v-leaflet-popup v-leaflet-popup-geo',
        })
            .setLatLng(latLng)
            .setContent(content)
            .openOn(map)
    }

    const loadGeoServerFeatures = async (event) => {
        if (!geoServerMode.value) return

        const activeLayerNames = geoServerLayers
            .filter(layerConfig => map.hasLayer(geoServerOverlaysByLayerName[layerConfig.layerName]))
            .map(layerConfig => layerConfig.layerName)

        if (!activeLayerNames.length) {
            geoServerStatus.value = 'فعّل طبقة واحدة على الأقل لعرض البيانات'
            showGeoServerPopup(
                event.latlng,
                buildGeoServerStatePopup(
                    'لا توجد طبقات نشطة',
                    '#f59e0b',
                    'فعّل الطبقة المطلوبة من قائمة GeoServer ثم حاول مرة أخرى.',
                ),
            )
            return
        }

        featureRequestController?.abort()
        featureRequestController = new AbortController()
        geoServerStatus.value = 'جارٍ تحميل بيانات موضع النقر...'
        showGeoServerPopup(
            event.latlng,
            buildGeoServerStatePopup('جارٍ تحميل البيانات...'),
        )

        try {
            const response = await fetch(buildGeoServerFeatureUrl(event.latlng, activeLayerNames), {
                signal: featureRequestController.signal,
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
            const features = response.ok
                ? await response.json().catch(() => null)
                : null

            if (!Array.isArray(features)) {
                throw new Error('Invalid GeoServer feature response (' + response.status + ')')
            }

            geoServerStatus.value = features.length
                ? 'تم العثور على ' + features.length.toLocaleString('ar-EG') + ' عنصر'
                : 'لا توجد بيانات في موضع النقر'
            showGeoServerPopup(event.latlng, buildGeoServerPopup(features))
        } catch (error) {
            if (error.name === 'AbortError') return

            console.error('GeoServer feature lookup failed', error)
            geoServerStatus.value = 'تعذر تحميل بيانات GeoServer'
            showGeoServerPopup(
                event.latlng,
                buildGeoServerStatePopup(
                    'تعذر تحميل البيانات',
                    '#dc2626',
                    'حاول مرة أخرى بعد لحظات.',
                ),
            )
        }
    }

    L.control.layers(
        {
            OpenStreetMap: openStreetMap,
            GeoServer: geoServerMap,
        },
        null,
        { collapsed: false }
    ).addTo(map)

    const geoServerLayerControl = L.control.layers(
        null,
        geoServerOverlays,
        { collapsed: false }
    ).addTo(map)
    geoServerLayerControl.getContainer().style.display = 'none'

    map.on('baselayerchange', (event) => {
        geoServerMode.value = event.layer === geoServerMap
        geoServerLayerControl.getContainer().style.display = geoServerMode.value ? '' : 'none'

        if (geoServerMode.value) {
            geoServerStatus.value = 'انقر على أي عنصر ظاهر لعرض بياناته'
            defaultGeoServerLayers.forEach(layer => layer.addTo(map))
            return
        }

        featureRequestController?.abort()
        featureRequestController = null
        geoServerStatus.value = 'انقر على علامة المحبس لعرض بياناته'
        map.closePopup()
        Object.values(geoServerOverlays).forEach(layer => layer.removeFrom(map))
    })

    map.on('click', loadGeoServerFeatures)

    if (valveBounds.length) {
        map.fitBounds(valveBounds, { padding: [40, 40], maxZoom: 16 })
    }

    mapResizeObserver = new ResizeObserver(scheduleMapResize)
    mapResizeObserver.observe(mapContainer.value)
    window.addEventListener('resize', scheduleMapResize)

    ;[0, 120, 350, 800].forEach(delay => {
        window.setTimeout(scheduleMapResize, delay)
    })
}

onMounted(initMap)

onBeforeUnmount(() => {
    featureRequestController?.abort()
    featureRequestController = null
    window.removeEventListener('resize', scheduleMapResize)
    mapResizeObserver?.disconnect()
    mapResizeObserver = null

    if (mapResizeTimer) {
        window.clearTimeout(mapResizeTimer)
        mapResizeTimer = null
    }

    map?.remove()
    map = null
})
</script>

<template>
    <Head title="خريطة المحابس" />
    <AppLayout>
        <div class="v-page-header">
            <div>
                <h2 class="v-page-title">خريطة المحابس البلدية</h2>
                <p class="v-page-subtitle">خريطتان مستقلتان داخل نطاق قطاع غزة</p>
            </div>
            <div v-if="!geoServerMode" class="v-map-count" aria-label="عدد المحابس المرسومة">
                <strong>{{ mapValves.length.toLocaleString('ar-EG') }}</strong>
                <span>محبس على OpenStreetMap</span>
            </div>
            <div v-else class="v-map-count">
                <span>طبقات GeoServer</span>
            </div>
        </div>

        <div class="v-card">
            <div class="v-card-header">
                <h3 class="v-card-title">{{ geoServerMode ? 'خريطة GeoServer' : 'خريطة OpenStreetMap' }}</h3>
                <span class="v-map-help">
                    {{ geoServerMode ? geoServerStatus : 'انقر على علامة المحبس لعرض بياناته' }}
                </span>
            </div>
            <div class="v-card-body" style="padding: 0;">
                <div class="v-map-container v-map-container-large">
                    <div ref="mapContainer" id="valves-map" dir="ltr" style="width: 100%; height: 100%;"></div>

                    <div v-if="!geoServerMode && mapValves.length === 0" class="v-map-outage" role="status">
                        <strong>لا توجد نقاط محابس</strong>
                        <span>لم تصل إحداثيات صالحة داخل قطاع غزة.</span>
                    </div>

                    <div v-if="!geoServerMode" class="v-map-legend">
                        <div class="v-legend-item">
                            <div class="v-legend-dot v-legend-dot-valve"></div>
                            <span>محبس من EME.WATER_VALVES</span>
                        </div>
                        <strong class="v-map-legend-count">{{ mapValves.length.toLocaleString('ar-EG') }} محبس</strong>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

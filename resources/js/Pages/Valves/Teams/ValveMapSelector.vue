<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import Modal from '@/ValvesSystem/Components/Shared/Modal.vue'

const props = defineProps({
    show: { type: Boolean, default: false },
    valves: { type: Array, default: () => [] },
    selectedValveIds: { type: Array, default: () => [] },
    mapFeaturesUrl: { type: String, required: true },
})

const emit = defineEmits(['close', 'toggle'])

const mapContainer = ref(null)
const message = ref('انقر على أي محبس في الخريطة لإضافته إلى الفريق أو إزالته منه.')
const candidateValves = ref([])
const candidateLatLng = ref(null)
const selectedCount = computed(() => props.selectedValveIds.length)
const valvesById = computed(() => new Map(props.valves.map(valve => [Number(valve.OID), valve])))
const wmsUrl = window.location.protocol === 'https:'
    ? '/wms.php?g_page=/cite/wms'
    : 'http://appdev-01.mog.org:8080/geoserver/cite/wms'

let map = null
let L = null
let requestController = null
const selectionMarkers = new Map()

const featureInfoUrl = (latLng) => {
    const params = new URLSearchParams({
        layers: 'cite:WATER_VALVES',
        lat: latLng.lat,
        lng: latLng.lng,
    })

    return `${props.mapFeaturesUrl}?${params.toString()}`
}

const valveIdFromProperties = (properties) => Number(
    properties?.W4_GENERAL_ID ?? properties?.w4_general_id,
)

const showPopup = (latlng, text) => {
    const content = document.createElement('div')
    content.dir = 'rtl'
    content.textContent = text
    L.popup().setLatLng(latlng).setContent(content).openOn(map)
}

const toggleLocatedValve = (valve) => {
    const valveId = Number(valve.OID)
    const wasSelected = props.selectedValveIds.includes(valveId)
    emit('toggle', valveId)

    if (wasSelected) {
        selectionMarkers.get(valveId)?.remove()
        selectionMarkers.delete(valveId)
    } else if (candidateLatLng.value) {
        const marker = L.circleMarker(candidateLatLng.value, {
            radius: 9,
            color: '#ffffff',
            weight: 2,
            fillColor: '#f97316',
            fillOpacity: 0.95,
        }).addTo(map)
        selectionMarkers.set(valveId, marker)
    }

    const action = wasSelected ? 'تمت إزالة' : 'تمت إضافة'
    message.value = `${action} ${valve.CODE} من إسناد الفريق.`
    showPopup(candidateLatLng.value, `${action} ${valve.CODE} - ${valve.NAME}`)
}

const selectValveAt = async (event) => {
    requestController?.abort()
    requestController = new AbortController()

    try {
        const response = await fetch(featureInfoUrl(event.latlng), {
            signal: requestController.signal,
            headers: { Accept: 'application/json' },
        })
        const data = response.ok ? await response.json().catch(() => null) : null
        const valves = [...new Set((data ?? []).map(valveIdFromProperties))]
            .map(valveId => valvesById.value.get(valveId))
            .filter(Boolean)

        if (!valves.length) {
            candidateValves.value = []
            message.value = 'لم يتم العثور على محبس في موضع النقر. قرّب الخريطة وحاول مرة أخرى.'
            return
        }

        candidateValves.value = valves
        candidateLatLng.value = event.latlng

        if (valves.length === 1) {
            toggleLocatedValve(valves[0])
        } else {
            message.value = `يوجد ${valves.length} محابس في هذا الموقع؛ اختر المحبس المطلوب.`
        }
    } catch (error) {
        if (error.name !== 'AbortError') {
            message.value = 'تعذر قراءة المحبس من الخريطة. حاول مرة أخرى.'
        }
    }
}

const addWmsLayer = (layerName, pane, zIndex) => L.tileLayer.wms(wmsUrl, {
    layers: layerName,
    version: layerName === 'cite:WATER_VALVES' ? '1.1.1' : '1.1.0',
    format: 'image/png',
    transparent: true,
    pane,
    zIndex,
    tileSize: 256,
    tiled: true,
    updateWhenIdle: true,
    updateWhenZooming: false,
    keepBuffer: 2,
    errorTileUrl: 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=',
})

const initMap = async () => {
    await nextTick()
    if (map || !mapContainer.value) return

    L = (await import('leaflet')).default
    await import('leaflet/dist/leaflet.css')

    map = L.map(mapContainer.value, {
        center: [31.5, 34.47],
        zoom: 13,
        preferCanvas: true,
    })

    map.createPane('teamContextPane').style.zIndex = 350
    map.createPane('teamNetworkPane').style.zIndex = 650
    map.createPane('teamValvesPane').style.zIndex = 720

    const centerlines = addWmsLayer('cite:V_CENTERLINE_SEGMENTS', 'teamContextPane', 360).addTo(map)
    const landmarks = addWmsLayer('cite:V_LANDMARKS_V2', 'teamContextPane', 390).addTo(map)
    const pipes = addWmsLayer('cite:WATER_PIPE', 'teamNetworkPane', 650).addTo(map)
    const valves = addWmsLayer('cite:WATER_VALVES', 'teamValvesPane', 720).addTo(map)

    L.control.layers({}, {
        'محاور الشوارع': centerlines,
        'المعالم': landmarks,
        'شبكة الأنابيب': pipes,
        'محابس المياه': valves,
    }, { collapsed: false }).addTo(map)

    map.on('click', selectValveAt)
    window.setTimeout(() => map?.invalidateSize(), 100)
}

const destroyMap = () => {
    requestController?.abort()
    requestController = null
    candidateValves.value = []
    candidateLatLng.value = null
    selectionMarkers.clear()
    map?.remove()
    map = null
}

const close = () => {
    destroyMap()
    emit('close')
}

watch(() => props.show, (show) => {
    if (show) {
        message.value = 'انقر على أي محبس في الخريطة لإضافته إلى الفريق أو إزالته منه.'
        initMap()
    } else {
        destroyMap()
    }
})

onBeforeUnmount(destroyMap)
</script>

<template>
    <Modal :show="show" title="اختيار المحابس من الخريطة" max-width="1040px" @close="close">
        <div class="v-map-selection-status">
            <span>{{ message }}</span>
            <strong>المحدد: {{ selectedCount }}</strong>
        </div>
        <div v-if="candidateValves.length > 1" class="v-map-valve-candidates">
            <button
                v-for="valve in candidateValves"
                :key="valve.OID"
                :class="['v-map-candidate-btn', { selected: selectedValveIds.includes(Number(valve.OID)) }]"
                type="button"
                @click="toggleLocatedValve(valve)"
            >
                {{ valve.CODE }} — {{ valve.NAME }}
            </button>
        </div>
        <div ref="mapContainer" class="v-map-container v-team-valve-map" dir="ltr"></div>
        <template #footer>
            <button class="v-btn v-btn-primary" type="button" @click="close">تم</button>
        </template>
    </Modal>
</template>

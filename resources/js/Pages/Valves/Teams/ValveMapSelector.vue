<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import Modal from '@/ValvesSystem/Components/Shared/Modal.vue'

const props = defineProps({
    show: { type: Boolean, default: false },
    valves: { type: Array, default: () => [] },
    selectedValveIds: { type: Array, default: () => [] },
})

const emit = defineEmits(['close', 'toggle'])

const mapContainer = ref(null)
const message = ref('انقر على أي محبس في الخريطة لإضافته إلى الفريق أو إزالته منه.')
const selectedCount = computed(() => props.selectedValveIds.length)

let map = null
let L = null
const selectionMarkers = new Map()

const showPopup = (latlng, text) => {
    const content = document.createElement('div')
    content.dir = 'rtl'
    content.textContent = text
    L.popup().setLatLng(latlng).setContent(content).openOn(map)
}

const markerStyle = (selected) => ({
    radius: selected ? 9 : 6,
    color: '#ffffff',
    weight: 2,
    fillColor: selected ? '#16a34a' : '#f97316',
    fillOpacity: 0.95,
})

const toggleLocatedValve = (valve, marker) => {
    const valveId = Number(valve.oid)
    const wasSelected = props.selectedValveIds.includes(valveId)
    emit('toggle', valveId)
    marker.setStyle(markerStyle(!wasSelected))

    const action = wasSelected ? 'تمت إزالة' : 'تمت إضافة'
    message.value = `${action} ${valve.code} من إسناد الفريق.`
    showPopup(marker.getLatLng(), `${action} ${valve.code} - ${valve.name}`)
}

const initMap = async () => {
    await nextTick()
    if (map || !mapContainer.value) return

    L = (await import('leaflet')).default
    await import('leaflet/dist/leaflet.css')

    map = L.map(mapContainer.value, {
        center: [31.5, 34.47],
        zoom: 13,
        minZoom: 10,
        maxBounds: [[31.15, 34.15], [31.65, 34.65]],
        maxBoundsViscosity: 1,
        preferCanvas: true,
    })

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19,
    }).addTo(map)

    const bounds = []
    props.valves.forEach((valve) => {
        const latitude = Number(valve.latitude)
        const longitude = Number(valve.longitude)
        const valveId = Number(valve.oid)
        if (!Number.isFinite(latitude) || !Number.isFinite(longitude) || !Number.isFinite(valveId)) return

        const marker = L.circleMarker(
            [latitude, longitude],
            markerStyle(props.selectedValveIds.includes(valveId)),
        ).addTo(map)
        marker.on('click', () => toggleLocatedValve(valve, marker))
        marker.bindTooltip(`${valve.code} - ${valve.name}`, { direction: 'top' })
        selectionMarkers.set(valveId, marker)
        bounds.push([latitude, longitude])
    })

    if (bounds.length) {
        map.fitBounds(bounds, { padding: [30, 30], maxZoom: 16 })
    }
    window.setTimeout(() => map?.invalidateSize(), 100)
}

const destroyMap = () => {
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

watch(() => props.selectedValveIds, (selectedValveIds) => {
    selectionMarkers.forEach((marker, valveId) => {
        marker.setStyle(markerStyle(selectedValveIds.includes(valveId)))
    })
}, { deep: true })

onBeforeUnmount(destroyMap)
</script>

<template>
    <Modal :show="show" title="اختيار المحابس من الخريطة" max-width="1040px" @close="close">
        <div class="v-map-selection-status">
            <span>{{ message }}</span>
            <strong>المحدد: {{ selectedCount }}</strong>
        </div>
        <div ref="mapContainer" class="v-map-container v-team-valve-map" dir="ltr"></div>
        <template #footer>
            <button class="v-btn v-btn-primary" type="button" @click="close">تم</button>
        </template>
    </Modal>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '../../../WellsSystem/Layouts/AppLayout.vue'

const props = defineProps({
    wells: { type: Array, default: () => [] },
})

const unknownStatus = '__unknown__'
const operationalLayers = [
    { value: 'يعمل', label: 'يعمل', className: 'is-working' },
    { value: 'متوقف', label: 'متوقف', className: 'is-stopped' },
    { value: 'تحت الصيانة', label: 'تحت الصيانة', className: 'is-maintenance' },
    { value: unknownStatus, label: 'غير محدد', className: 'is-unknown' },
]
const damageLayers = [
    { value: 'سليمة', label: 'سليمة', className: 'is-safe' },
    { value: 'متضررة', label: 'متضررة', className: 'is-damaged' },
    { value: 'أعيد تأهيلها', label: 'أعيد تأهيلها', className: 'is-rehabilitated' },
    { value: 'مدمرة', label: 'مدمرة', className: 'is-destroyed' },
    { value: unknownStatus, label: 'غير محدد', className: 'is-unknown' },
]
const pumpTypeLabels = {
    centrifugal: 'رأسي',
    submersible: 'غاطس',
    vertical: 'رأسي',
}

const selectedOperationalLayers = ref(operationalLayers.map(layer => layer.value))
const selectedDamageLayers = ref(damageLayers.map(layer => layer.value))
const includeInactiveRecords = ref(true)

let leaflet = null
let map = null
let markerLayer = null
let markerRecords = []
let selectedRecord = null

function isActive(value) {
    return value === true || value === 1 || value === '1'
}

function hasCoordinates(well) {
    const latitude = Number(well.LATITUDE)
    const longitude = Number(well.LONGITUDE)

    return Number.isFinite(latitude)
        && Number.isFinite(longitude)
        && latitude >= -90
        && latitude <= 90
        && longitude >= -180
        && longitude <= 180
}

function normalizeLayerValue(value, layers) {
    return layers.some(layer => layer.value === value) ? value : unknownStatus
}

function operationalValue(well) {
    return normalizeLayerValue(well.OPERATIONAL_STATUS, operationalLayers)
}

function damageValue(well) {
    return normalizeLayerValue(well.DAMAGE_STATUS, damageLayers)
}

function wellMatchesFilters(well) {
    return hasCoordinates(well)
        && (includeInactiveRecords.value || isActive(well.IS_ACTIVE))
        && selectedOperationalLayers.value.includes(operationalValue(well))
        && selectedDamageLayers.value.includes(damageValue(well))
}

const mappedWellsCount = computed(() => props.wells.filter(hasCoordinates).length)
const visibleWellsCount = computed(() => props.wells.filter(wellMatchesFilters).length)
const inactiveWellsCount = computed(() => props.wells.filter(well => hasCoordinates(well) && !isActive(well.IS_ACTIVE)).length)

function layerCount(type, value) {
    const layers = type === 'operational' ? operationalLayers : damageLayers
    const field = type === 'operational' ? 'OPERATIONAL_STATUS' : 'DAMAGE_STATUS'

    return props.wells.filter(well => (
        hasCoordinates(well)
        && normalizeLayerValue(well[field], layers) === value
    )).length
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;')
}

function wellNumber(well) {
    return well.SOURCE_WELL_CODE || well.WELL_NUM || 'غير متوفر'
}

function generatorNumbers(well) {
    return (well.MAP_GENERATORS || well.map_generators || [])
        .map(generator => generator.NUMBER ?? generator.number)
        .filter(number => number !== null && number !== undefined && number !== '')
}

function operationalClass(well) {
    return operationalLayers.find(layer => layer.value === operationalValue(well))?.className || 'is-unknown'
}

function damageClass(well) {
    return damageLayers.find(layer => layer.value === damageValue(well))?.className || 'is-unknown'
}

function markerIcon(well, selected = false) {
    const name = escapeHtml(well.NAME || 'بئر بدون اسم')
    const inactiveClass = isActive(well.IS_ACTIVE) ? '' : ' is-record-inactive'
    const selectedClass = selected ? ' is-selected' : ''

    return leaflet.divIcon({
        className: 'well-map-div-icon',
        html: `
            <div class="well-map-marker ${operationalClass(well)}${inactiveClass}${selectedClass}">
                <span class="well-map-marker-symbol" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                        <path d="M12 2.5C9.6 6.2 5.5 10.3 5.5 15a6.5 6.5 0 0 0 13 0c0-4.7-4.1-8.8-6.5-12.5Z"></path>
                        <path class="well-map-marker-wave" d="M8.4 15.2c1.3-.9 2.4-.9 3.6 0 1.2.9 2.3.9 3.6 0"></path>
                    </svg>
                    <i class="well-map-damage-dot ${damageClass(well)}"></i>
                </span>
                <span class="well-map-marker-label">${name}</span>
            </div>
        `,
        iconSize: [180, 48],
        iconAnchor: [24, 42],
        popupAnchor: [0, -42],
    })
}

function popupContent(well) {
    const generators = generatorNumbers(well)
    const generatorText = generators.length ? generators.join('، ') : 'غير مرتبط بمولد'
    const generatorDirection = generators.length ? 'ltr' : 'rtl'
    const operation = well.OPERATIONAL_STATUS || 'غير محدد'
    const damage = well.DAMAGE_STATUS || 'غير محدد'
    const pump = pumpTypeLabels[well.PUMP_TYPE] || well.PUMP_TYPE || 'غير محدد'
    const capacity = well.CAPACITY ? `${well.CAPACITY} م³` : 'غير متوفر'
    const editUrl = `/wells/wells/${encodeURIComponent(well.OID)}/edit`

    return `
        <article class="well-map-popup-card">
            <header class="well-map-popup-header">
                <div>
                    <span class="well-map-popup-number" dir="ltr">${escapeHtml(wellNumber(well))}</span>
                    <h3>${escapeHtml(well.NAME || 'بئر بدون اسم')}</h3>
                </div>
                <span class="well-map-popup-record-status ${isActive(well.IS_ACTIVE) ? 'is-active' : 'is-inactive'}">
                    ${isActive(well.IS_ACTIVE) ? 'مفعّل' : 'غير مفعّل'}
                </span>
            </header>

            <div class="well-map-popup-statuses">
                <span class="${operationalClass(well)}">${escapeHtml(operation)}</span>
                <span class="${damageClass(well)}">${escapeHtml(damage)}</span>
            </div>

            <dl class="well-map-popup-details">
                <div>
                    <dt>المنطقة</dt>
                    <dd>${escapeHtml(well.QUARTER_NAME || 'غير محددة')}</dd>
                </div>
                <div>
                    <dt>نوع المضخة</dt>
                    <dd>${escapeHtml(pump)}</dd>
                </div>
                <div class="is-wide">
                    <dt>رقم المولد</dt>
                    <dd class="well-map-popup-generator" dir="${generatorDirection}">${escapeHtml(generatorText)}</dd>
                </div>
                <div>
                    <dt>الإنتاجية</dt>
                    <dd>${escapeHtml(capacity)}</dd>
                </div>
            </dl>

            <a class="well-map-popup-action" href="${editUrl}">
                عرض وتعديل بيانات البئر
            </a>
        </article>
    `
}

function clearSelectedRecord() {
    if (!selectedRecord) return

    selectedRecord.marker.setIcon(markerIcon(selectedRecord.well))
    selectedRecord.marker.setZIndexOffset(0)
    selectedRecord = null
}

function selectRecord(record) {
    if (selectedRecord && selectedRecord !== record) {
        selectedRecord.marker.setIcon(markerIcon(selectedRecord.well))
        selectedRecord.marker.setZIndexOffset(0)
    }

    selectedRecord = record
    record.marker.setIcon(markerIcon(record.well, true))
    record.marker.setZIndexOffset(1000)
}

function renderVisibleMarkers() {
    if (!markerLayer) return

    markerLayer.clearLayers()

    markerRecords.forEach(record => {
        if (wellMatchesFilters(record.well)) {
            record.marker.addTo(markerLayer)
        } else if (selectedRecord === record) {
            selectedRecord = null
        }
    })
}

function initializeMarkers() {
    markerRecords = props.wells
        .filter(hasCoordinates)
        .map(well => {
            const marker = leaflet.marker(
                [Number(well.LATITUDE), Number(well.LONGITUDE)],
                { icon: markerIcon(well), keyboard: true },
            )
            const record = { well, marker }

            marker.bindPopup(popupContent(well), {
                className: 'well-map-popup',
                minWidth: 300,
                maxWidth: 360,
                closeButton: true,
            })
            marker.bindTooltip(escapeHtml(well.NAME || 'بئر بدون اسم'), {
                direction: 'top',
                offset: [0, -34],
                className: 'well-map-tooltip',
            })
            marker.on('click', () => selectRecord(record))
            marker.on('popupclose', () => {
                if (selectedRecord === record) clearSelectedRecord()
            })

            return record
        })

    renderVisibleMarkers()
}

async function initMap() {
    const leafletModule = await import('leaflet')
    await import('leaflet/dist/leaflet.css')
    leaflet = leafletModule.default

    map = leaflet.map('wells-map', {
        zoomControl: true,
        minZoom: 10,
        maxZoom: 19,
    }).setView([31.5, 34.47], 12)

    leaflet.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap',
        maxZoom: 19,
    }).addTo(map)

    markerLayer = leaflet.layerGroup().addTo(map)
    initializeMarkers()
}

function selectAllLayers() {
    selectedOperationalLayers.value = operationalLayers.map(layer => layer.value)
    selectedDamageLayers.value = damageLayers.map(layer => layer.value)
    includeInactiveRecords.value = true
}

function showOperationalWellsOnly() {
    selectedOperationalLayers.value = ['يعمل']
    selectedDamageLayers.value = damageLayers.map(layer => layer.value)
    includeInactiveRecords.value = false
}

watch(
    [selectedOperationalLayers, selectedDamageLayers, includeInactiveRecords],
    renderVisibleMarkers,
    { deep: true },
)

onMounted(initMap)

onBeforeUnmount(() => {
    if (map) {
        map.remove()
        map = null
    }
})
</script>

<template>
    <AppLayout title="خريطة الآبار">
        <div class="content-wrapper wells-map-page">
            <div class="page-header">
                <div>
                    <h1 class="page-title">خريطة الآبار</h1>
                    <p class="page-subtitle">استعراض الآبار حسب الحالة التشغيلية وحالة الضرر</p>
                </div>
                <a href="/wells/wells" class="btn btn-secondary" @click.prevent="router.visit('/wells/wells')">
                    → قائمة الآبار
                </a>
            </div>

            <section class="well-map-layers card" aria-label="طبقات خريطة الآبار">
                <div class="well-map-layers-header">
                    <div>
                        <span class="well-map-layers-eyebrow">طبقات الخريطة</span>
                        <strong>{{ visibleWellsCount }} من {{ mappedWellsCount }} بئر ظاهر</strong>
                    </div>
                    <div class="well-map-layer-actions">
                        <button type="button" class="btn btn-secondary btn-sm" @click="showOperationalWellsOnly">
                            العاملة فقط
                        </button>
                        <button type="button" class="btn btn-primary btn-sm" @click="selectAllLayers">
                            عرض الكل
                        </button>
                    </div>
                </div>

                <div class="well-map-layer-groups">
                    <fieldset class="well-map-layer-group">
                        <legend>الحالة التشغيلية</legend>
                        <label
                            v-for="layer in operationalLayers"
                            :key="layer.value"
                            class="well-map-layer-option"
                        >
                            <input v-model="selectedOperationalLayers" type="checkbox" :value="layer.value">
                            <span :class="['well-map-layer-dot', layer.className]"></span>
                            <span>{{ layer.label }}</span>
                            <small>{{ layerCount('operational', layer.value) }}</small>
                        </label>
                    </fieldset>

                    <fieldset class="well-map-layer-group">
                        <legend>حالة الضرر</legend>
                        <label
                            v-for="layer in damageLayers"
                            :key="layer.value"
                            class="well-map-layer-option"
                        >
                            <input v-model="selectedDamageLayers" type="checkbox" :value="layer.value">
                            <span :class="['well-map-layer-dot', layer.className]"></span>
                            <span>{{ layer.label }}</span>
                            <small>{{ layerCount('damage', layer.value) }}</small>
                        </label>
                    </fieldset>

                    <label class="well-map-inactive-toggle">
                        <input v-model="includeInactiveRecords" type="checkbox">
                        <span>
                            <strong>إظهار السجلات غير المفعّلة</strong>
                            <small>{{ inactiveWellsCount }} بئر</small>
                        </span>
                    </label>
                </div>
            </section>

            <div class="card well-map-card">
                <div id="wells-map" class="wells-map-canvas"></div>
            </div>
        </div>
    </AppLayout>
</template>

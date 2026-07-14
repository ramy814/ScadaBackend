<script setup>
import { ref, onMounted } from 'vue'
import AppLayout from '../../../WellsSystem/Layouts/AppLayout.vue'

const props = defineProps({
    wells: { type: Array, default: () => [] },
})

let map = null

onMounted(() => {
    const link = document.createElement('link')
    link.rel = 'stylesheet'
    link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'
    document.head.appendChild(link)

    const script = document.createElement('script')
    script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js'
    script.onload = () => initMap()
    document.head.appendChild(script)
})

function initMap() {
    map = L.map('wells-map').setView([31.5, 34.47], 12)
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap'
    }).addTo(map)

    const pumpTypeLabels = { centrifugal: 'طرد مركزي', submersible: 'غاطس' }
    const powerSourceLabels = { electricity: 'كهرباء', solar: 'سولار' }

    props.wells.forEach(well => {
        if (well.LATITUDE && well.LONGITUDE) {
            const wellNumber = well.SOURCE_WELL_CODE || well.WELL_NUM || '-'
            const marker = L.marker([well.LATITUDE, well.LONGITUDE]).addTo(map)
            marker.bindPopup(`
                <div style="direction: rtl; font-family: Cairo, sans-serif; min-width: 200px;">
                    <h3 style="margin: 0 0 8px; color: #00d4aa; font-size: 16px;">${well.NAME}</h3>
                    <p style="margin: 4px 0; font-size: 13px;"># ${wellNumber}</p>
                    <p style="margin: 4px 0; font-size: 13px;">📍 ${well.quarter_name || '-'}</p>
                    <p style="margin: 4px 0; font-size: 13px;">⚙️ ${pumpTypeLabels[well.PUMP_TYPE] || well.PUMP_TYPE}</p>
                    <p style="margin: 4px 0; font-size: 13px;">⚡ ${powerSourceLabels[well.POWER_SOURCE] || well.POWER_SOURCE}</p>
                    <p style="margin: 4px 0; font-size: 13px;">📏 ${well.CAPACITY} م³</p>
                    <a href="/wells/wells/${well.OID}/edit" style="display: inline-block; margin-top: 8px; padding: 4px 12px; background: #00d4aa; color: #0a0e1a; border-radius: 6px; font-size: 12px; font-weight: 700; text-decoration: none;">✏️ تعديل</a>
                </div>
            `)
        }
    })
}
</script>

<template>
    <AppLayout title="خريطة الآبار">
        <div class="content-wrapper">
            <div class="page-header">
                <div>
                    <h1 class="page-title">خريطة الآبار</h1>
                    <p class="page-subtitle">عرض جميع الآبار على الخريطة</p>
                </div>
                <a href="/wells/wells" class="btn btn-secondary" @click.prevent="$inertia.visit('/wells/wells')">
                    → قائمة الآبار
                </a>
            </div>

            <div class="card">
                <div class="card-body" style="padding: 0;">
                    <div id="wells-map" style="height: 600px; border-radius: 12px;"></div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import { useI18n } from '../../lib/i18n.js';
import 'leaflet/dist/leaflet.css';

const props = defineProps({ wells: Array });
const { t } = useI18n();
const el = ref(null);
const operational = ref(['يعمل', 'متوقف', 'تحت الصيانة', '']);
const damage = ref(['سليمة', 'متضررة', 'أعيد تأهيلها', 'مدمرة', '']);
const showInactive = ref(true);
const colors = { 'يعمل': '#1f8f4e', 'متوقف': '#c9353b', 'تحت الصيانة': '#d97706' };
const active = (v) => v === true || v === 1 || v === '1';
const visible = computed(() => props.wells.filter((w) => w.LATITUDE && w.LONGITUDE
    && operational.value.includes(w.OPERATIONAL_STATUS ?? '') && damage.value.includes(w.DAMAGE_STATUS ?? '') && (showInactive.value || active(w.IS_ACTIVE))));
let map; let layer; let L;
function esc(value) { const d = document.createElement('div'); d.textContent = value ?? '—'; return d.innerHTML; }
function draw() {
    if (!map) return;
    layer?.remove();
    layer = L.layerGroup().addTo(map);
    for (const w of visible.value) {
        const marker = L.circleMarker([Number(w.LATITUDE), Number(w.LONGITUDE)], { radius: 8, weight: 2, color: '#fff', fillColor: colors[w.OPERATIONAL_STATUS] ?? '#5b5e66', fillOpacity: active(w.IS_ACTIVE) ? 0.95 : 0.4 });
        const gens = (w.MAP_GENERATORS ?? []).map((g) => esc(g.number)).join('، ') || '—';
        marker.bindPopup(`<strong>${esc(w.NAME)}</strong><br>${esc(w.QUARTER_NAME)}<br>${esc(w.OPERATIONAL_STATUS)} · ${esc(w.DAMAGE_STATUS)}<br>${t('wells.assets.generator')}: ${gens}`);
        marker.addTo(layer);
    }
}
onMounted(async () => {
    L = (await import('leaflet')).default;
    map = L.map(el.value).setView([31.5, 34.46], 12);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap' }).addTo(map);
    draw();
});
watch(visible, draw);
onBeforeUnmount(() => map?.remove());
</script>

<template>
    <Head :title="t('nav.wells.map')" />
    <PageHeader :title="t('wells.map.title')" :description="t('wells.map.subtitle', { n: visible.length, total: wells.length })" />
    <div class="grid" style="grid-template-columns: 240px minmax(0, 1fr); align-items: start">
        <aside class="panel panel-body stack" style="gap: var(--s-3)">
            <div class="label">{{ t('wells.assets.operation') }}</div>
            <label v-for="s in ['يعمل', 'متوقف', 'تحت الصيانة']" :key="s" class="check"><input v-model="operational" type="checkbox" :value="s" /><span class="sev" :style="{ background: colors[s], width: '8px', height: '8px', borderRadius: '50%' }" />{{ s }}</label>
            <div class="label">{{ t('wells.assets.damage') }}</div>
            <label v-for="s in ['سليمة', 'متضررة', 'أعيد تأهيلها', 'مدمرة']" :key="s" class="check"><input v-model="damage" type="checkbox" :value="s" />{{ s }}</label>
            <label class="check"><input v-model="showInactive" type="checkbox" />{{ t('wells.map.inactive') }}</label>
        </aside>
        <div ref="el" class="map" style="height: calc(100vh - 220px); min-height: 420px" />
    </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import { useI18n } from '../../lib/i18n.js';
import 'leaflet/dist/leaflet.css';

/**
 * Two mutually exclusive modes (legacy web-map contract): OpenStreetMap with Oracle
 * valve markers, or GeoServer (general map WMTS + optional WMS overlays) through the
 * authenticated same-origin tile route. GeoServer credentials never reach the browser.
 */
const props = defineProps({ valves: Array });
const { t } = useI18n();
const el = ref(null);
const mode = ref('osm');
const overlays = ref([]);
const OVERLAYS = ['mog:V_BUILDING_GEOSRV', 'mog:V_CENTERLINE_SEGMENTS', 'mog:V_LANDMARKS_V2'];
const BLANK = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';
const WMTS = '/valves/map/tiles/wmts?SERVICE=WMTS&REQUEST=GetTile&VERSION=1.0.0&LAYER=mog%3Agaza%20general%20map&STYLE=&TILEMATRIXSET=EPSG%3A900913&TILEMATRIX=EPSG%3A900913%3A{z}&FORMAT=image%2Fpng&TILECOL={x}&TILEROW={y}';
let L; let map; let osm; let markers; let general; const wms = {}; let lookup;

function esc(value) { const d = document.createElement('div'); d.textContent = value ?? '—'; return d.innerHTML; }
const tileOptions = { tileSize: 256, updateWhenZooming: false, keepBuffer: 2, errorTileUrl: BLANK };

function apply() {
    lookup?.abort();
    for (const layer of [osm, markers, general, ...Object.values(wms)]) layer && map.removeLayer(layer);
    if (mode.value === 'osm') { osm.addTo(map); markers.addTo(map); return; }
    general.addTo(map);
    for (const name of overlays.value) wms[name].addTo(map);
}

async function onClick(event) {
    if (mode.value !== 'geoserver') return;
    lookup?.abort();
    lookup = new AbortController();
    const layers = ['mog:gaza general map', ...overlays.value].join(',');
    const popup = L.popup().setLatLng(event.latlng).setContent(t('valves.map.loading')).openOn(map);
    try {
        const query = new URLSearchParams({ layers, lat: event.latlng.lat, lng: event.latlng.lng, zoom: map.getZoom() });
        const response = await fetch(`/valves/map/features?${query}`, { headers: { Accept: 'application/json' }, signal: lookup.signal });
        const { features } = response.ok ? await response.json() : { features: null };
        if (!features) { popup.setContent(t('valves.map.failed')); return; }
        popup.setContent(features.length ? features.slice(0, 5).map((f) => `<strong>${esc(f._feature_layer)}</strong><br>${Object.entries(f).filter(([k]) => !k.startsWith('_')).slice(0, 6).map(([k, v]) => `${esc(k)}: ${esc(v)}`).join('<br>')}`).join('<hr>') : t('valves.map.empty'));
    } catch (error) {
        if (error.name !== 'AbortError') popup.setContent(t('valves.map.failed'));
    }
}

onMounted(async () => {
    L = (await import('leaflet')).default;
    map = L.map(el.value, { maxBounds: [[31.15, 34.15], [31.65, 34.65]] }).setView([31.5, 34.46], 12);
    osm = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap' });
    markers = L.layerGroup(props.valves.map((v) => L.circleMarker([v.latitude, v.longitude], { radius: 5, weight: 1, color: '#fff', fillColor: '#dc6a1f', fillOpacity: 0.9 })
        .bindPopup(`<strong>${esc(v.code)}</strong><br>${esc(v.name)}<br>${esc(v.valve_type)} · ${esc(v.valve_diameter)}<br>${esc(v.status)}`)));
    general = L.tileLayer(WMTS, { ...tileOptions, maxZoom: 21 });
    for (const name of OVERLAYS) wms[name] = L.tileLayer.wms('/valves/map/tiles/wms', { ...tileOptions, layers: name, version: '1.1.1', format: 'image/png', transparent: true, opacity: 0.9 });
    map.on('click', onClick);
    apply();
});
onBeforeUnmount(() => { lookup?.abort(); map?.remove(); });
</script>

<template>
    <Head :title="t('nav.valves.map')" />
    <PageHeader :title="t('valves.map.title')" :description="t('valves.map.subtitle', { n: valves.length })">
        <template #actions>
            <div class="segmented">
                <button type="button" :aria-current="mode === 'osm'" @click="mode = 'osm'; apply()">OpenStreetMap</button>
                <button type="button" :aria-current="mode === 'geoserver'" @click="mode = 'geoserver'; apply()">GeoServer</button>
            </div>
        </template>
    </PageHeader>
    <div class="grid" :style="mode === 'geoserver' ? 'grid-template-columns: 220px minmax(0, 1fr); align-items: start' : ''">
        <aside v-if="mode === 'geoserver'" class="panel panel-body stack" style="gap: var(--s-2)">
            <div class="label">{{ t('valves.map.layers') }}</div>
            <label v-for="name in OVERLAYS" :key="name" class="check"><input v-model="overlays" type="checkbox" :value="name" @change="apply" />{{ t(`valves.map.overlay.${name}`) }}</label>
            <p class="hint">{{ t('valves.map.click_hint') }}</p>
        </aside>
        <div ref="el" class="map" style="height: calc(100vh - 220px); min-height: 420px" />
    </div>
</template>

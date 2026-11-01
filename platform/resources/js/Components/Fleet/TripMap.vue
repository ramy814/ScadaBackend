<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import 'leaflet/dist/leaflet.css';

// Recorded GPS only: no road matching, snapping or smoothing (project rule).
const props = defineProps({ segments: { type: Array, default: () => [] }, gaps: { type: Array, default: () => [] }, stops: { type: Array, default: () => [] }, height: { type: Number, default: 320 } });
const el = ref(null);
let map = null;

async function draw() {
    const L = (await import('leaflet')).default;
    if (!el.value) return;
    map?.remove();
    map = L.map(el.value, { zoomControl: true, attributionControl: true });
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap' }).addTo(map);

    const accent = getComputedStyle(el.value).getPropertyValue('--app-fleet').trim() || '#2f6fed';
    const all = [];
    for (const segment of props.segments) {
        const latlngs = segment.map((p) => [p.lat, p.lng]);
        all.push(...latlngs);
        L.polyline(latlngs, { color: '#fff', weight: 7, opacity: 0.9 }).addTo(map);
        L.polyline(latlngs, { color: accent, weight: 4 }).addTo(map);
    }
    for (const gap of props.gaps) {
        const from = gap.from ?? gap[0];
        const to = gap.to ?? gap[1];
        if (from && to) L.polyline([[from.lat, from.lng], [to.lat, to.lng]], { color: '#d97706', weight: 3, dashArray: '6 6' }).addTo(map);
    }
    for (const stop of props.stops.filter((s) => s.latitude && s.longitude)) {
        L.circleMarker([stop.latitude, stop.longitude], { radius: 6, color: '#d97706', fillColor: '#f59e0b', fillOpacity: 0.9, weight: 2 }).addTo(map);
    }
    if (all.length) {
        L.circleMarker(all[0], { radius: 7, color: '#fff', fillColor: '#1f8f4e', fillOpacity: 1, weight: 2 }).addTo(map);
        L.circleMarker(all[all.length - 1], { radius: 7, color: '#fff', fillColor: '#c9353b', fillOpacity: 1, weight: 2 }).addTo(map);
        map.fitBounds(all, { padding: [24, 24] });
    } else {
        map.setView([31.5, 34.46], 12);
    }
}

onMounted(draw);
watch(() => props.segments, draw);
onBeforeUnmount(() => map?.remove());
</script>

<template>
    <div ref="el" class="map" :style="{ height: `${height}px` }" role="img" aria-label="Trip route" />
</template>

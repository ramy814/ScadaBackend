<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import 'leaflet/dist/leaflet.css';
import { useI18n } from '../../lib/i18n.js';

// Recorded GPS only: no road matching, snapping or smoothing (project rule).
// Same drawing rules as the legacy TripShow map: outbound/return colours, direction
// arrows, dashed gap connectors (display only), recorded start/end and stop markers.
const props = defineProps({
    segments: { type: Array, default: () => [] },
    gaps: { type: Array, default: () => [] },
    stops: { type: Array, default: () => [] },
    start: { type: Object, default: null },
    end: { type: Object, default: null },
    height: { type: Number, default: 320 },
});
const emit = defineEmits(['direction']);
const { t, num } = useI18n();
const el = ref(null);
let map = null;
let bounds = null;

function splitDirections(L, segments) {
    const points = segments.flat();
    const outbound = segments.map((points) => ({ points, leg: 'outbound' }));
    if (points.length < 4) return { parts: outbound, hasReturn: false };

    const start = L.latLng(points[0]);
    let furthestIndex = 0;
    let furthestDistance = 0;
    points.forEach((point, index) => {
        const distance = start.distanceTo(L.latLng(point));
        if (distance > furthestDistance) { furthestDistance = distance; furthestIndex = index; }
    });
    const endDistance = start.distanceTo(L.latLng(points[points.length - 1]));
    const hasReturn = furthestDistance >= 100 && endDistance <= furthestDistance * 0.65
        && furthestIndex >= points.length * 0.15 && furthestIndex <= points.length * 0.85;
    if (!hasReturn) return { parts: outbound, hasReturn: false };

    const parts = [];
    let globalIndex = 0;
    segments.forEach((segment) => {
        const first = globalIndex;
        const last = globalIndex + segment.length - 1;
        if (last <= furthestIndex) parts.push({ points: segment, leg: 'outbound' });
        else if (first >= furthestIndex) parts.push({ points: segment, leg: 'return' });
        else {
            const split = furthestIndex - first;
            parts.push({ points: segment.slice(0, split + 1), leg: 'outbound' });
            parts.push({ points: segment.slice(split), leg: 'return' });
        }
        globalIndex += segment.length;
    });

    return { parts: parts.filter((part) => part.points.length >= 2), hasReturn: true };
}

const stopMinutes = (stop) => stop.minutes ?? (stop.started_at && stop.ended_at
    ? Math.max(0, Math.round((new Date(stop.ended_at) - new Date(stop.started_at)) / 60000)) : 0);

function fit() {
    if (!map || !bounds?.isValid()) return;
    if (bounds.getNorthEast().equals(bounds.getSouthWest())) map.setView(bounds.getCenter(), 17);
    else map.fitBounds(bounds, { paddingTopLeft: [40, 56], paddingBottomRight: [40, 40], maxZoom: 18 });
}

async function draw() {
    const L = (await import('leaflet')).default;
    window.L = L; // leaflet-polylinedecorator attaches itself to the global L.
    await import('leaflet-polylinedecorator');
    if (!el.value) return;
    map?.remove();
    map = L.map(el.value, { preferCanvas: true, zoomControl: false });
    map.setView([31.5017, 34.4668], 12);
    L.control.zoom({ position: 'bottomleft' }).addTo(map);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap' }).addTo(map);

    bounds = L.latLngBounds([]);
    const style = { lineCap: 'round', lineJoin: 'round', smoothFactor: 1.25 };
    const segments = props.segments.map((segment) => segment.map((p) => [p.lat, p.lng])).filter((segment) => segment.length);
    const route = splitDirections(L, segments);
    emit('direction', route.hasReturn);

    route.parts.forEach(({ points, leg }) => {
        bounds.extend(points);
        if (points.length < 2) return;
        L.polyline(points, { ...style, color: '#ffffff', weight: 11, opacity: 0.96, interactive: false }).addTo(map);
        L.polyline(points, { ...style, color: leg === 'return' ? '#ea580c' : '#2563eb', weight: 6, opacity: 1 }).addTo(map);
        L.polylineDecorator(points, {
            patterns: [{ offset: 45, repeat: 90, symbol: L.Symbol.arrowHead({ pixelSize: 9, polygon: true, pathOptions: { color: '#fff', fillColor: '#fff', fillOpacity: 1, opacity: 1, weight: 1, interactive: false } }) }],
        }).addTo(map);
    });

    props.gaps.forEach((gap) => {
        if (!gap?.from || !gap?.to) return;
        const points = [[gap.from.lat, gap.from.lng], [gap.to.lat, gap.to.lng]];
        bounds.extend(points);
        const minutes = Math.max(1, Math.round((gap.seconds ?? 0) / 60));
        const estimate = gap.estimate_usable ? t('fleet.map.gap_estimate', { km: num(gap.estimated_distance_km ?? 0, 2) }) : t('fleet.map.gap_unusable');
        L.polyline(points, { ...style, color: '#d97706', weight: 4, opacity: 0.9, dashArray: '10 12' })
            .bindTooltip(t('fleet.map.gap_tooltip', { m: minutes, estimate }))
            .addTo(map);
    });

    const first = props.start ? [props.start.lat, props.start.lng] : segments[0]?.[0];
    const lastSegment = segments[segments.length - 1];
    const last = props.end ? [props.end.lat, props.end.lng] : lastSegment?.[lastSegment.length - 1];
    const marker = { radius: 10, color: '#ffffff', weight: 4, opacity: 1, fillOpacity: 1 };
    if (first && last) {
        bounds.extend([first, last]);
        L.circleMarker(first, { ...marker, fillColor: '#059669' })
            .bindTooltip(t('fleet.map.start'), { permanent: true, direction: 'bottom', offset: [0, 14], className: 'trip-tip' })
            .bindPopup(t('fleet.map.start_popup')).addTo(map);
        L.circleMarker(last, { ...marker, fillColor: '#dc2626' })
            .bindTooltip(t('fleet.map.end'), { permanent: true, direction: 'top', offset: [0, -14], className: 'trip-tip' })
            .bindPopup(t('fleet.map.end_popup')).addTo(map);
    }

    props.stops.filter((s) => s.latitude !== null && s.latitude !== undefined && s.longitude !== null && s.longitude !== undefined).forEach((stop) => {
        const point = [Number(stop.latitude), Number(stop.longitude)];
        bounds.extend(point);
        L.circleMarker(point, { radius: 8, color: '#ffffff', weight: 3, fillColor: '#f59e0b', fillOpacity: 1 })
            .bindTooltip(t('fleet.map.stop_tooltip', { m: stopMinutes(stop) })).addTo(map);
    });

    fit();
}

defineExpose({ fit });
onMounted(draw);
watch(() => [props.segments, props.gaps], draw);
onBeforeUnmount(() => { map?.remove(); map = null; });
</script>

<template>
    <div ref="el" class="map" :style="{ height: `${height}px` }" role="img" :aria-label="t('fleet.map.label')" />
</template>

<style>
.trip-tip { font-family: inherit; font-weight: 600; font-size: 12px; }
</style>

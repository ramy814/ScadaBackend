<script setup>
import { computed, ref } from 'vue';
import Icon from '../Icon.vue';
import StatusBadge from '../StatusBadge.vue';
import TripMap from './TripMap.vue';
import DecisionForm from './DecisionForm.vue';
import { useI18n } from '../../lib/i18n.js';

const props = defineProps({ data: { type: Object, required: true }, base: { type: String, required: true }, layout: { type: String, default: 'drawer' } });
const emit = defineEmits(['decided']);
const { t, num, date, duration } = useI18n();
const trip = computed(() => props.data.trip);
const m = computed(() => props.data.metrics);
const manual = computed(() => trip.value.source === 'manual');
const v2 = computed(() => (m.value?.quality_version ?? 0) >= 2);
const map = ref(null);
const hasReturn = ref(false);

// Same semantics as the legacy TripShow page.
const okEvents = ['gps_enabled', 'airplane_mode_off', 'device_boot', 'location_permission_restored'];
const eventTone = (type) => (okEvents.includes(type) ? 'success' : ['battery_low', 'tracking_error'].includes(type) ? 'warning' : 'danger');
const pointsTone = computed(() => {
    const { received, expected } = trip.value.points;
    if (!expected) return '';
    const pct = (received / expected) * 100;
    return pct >= 80 ? 'tone-success' : pct >= 50 ? 'tone-warning' : 'tone-danger';
});
const fuelLabel = computed(() => (m.value?.fuel_provisional ? 'fleet.detail.fuel_provisional' : v2.value ? 'fleet.detail.fuel_filtered' : 'fleet.detail.fuel'));
const gapCount = computed(() => (m.value ? m.value.gap_estimate.estimated + m.value.gap_estimate.unestimated : 0));
const rejectionSummary = computed(() => Object.entries(m.value?.rejection_counts ?? {})
    .filter(([, count]) => Number(count) > 0)
    .map(([reason, count]) => `${t(`fleet.rejections.${reason}`)}: ${count}`));
const processingText = computed(() => {
    const parts = [];
    if (m.value?.rejected_points > 0) parts.push(t('fleet.detail.rejected_points', { n: m.value.rejected_points }));
    if (m.value?.collapsed_points > 0) parts.push(t('fleet.detail.collapsed_points', { n: m.value.collapsed_points }));
    return parts.length ? t('fleet.detail.processing_kept', { parts: parts.join('، ') }) : t('fleet.detail.processing_none');
});
const showQuality = computed(() => m.value && (m.value.quality_flags.length || m.value.rejected_points || m.value.collapsed_points));
const coords = (item) => (item.latitude === null || item.latitude === undefined || item.longitude === null || item.longitude === undefined
    ? '—' : `${Number(item.latitude).toFixed(5)}, ${Number(item.longitude).toFixed(5)}`);
function eventDetail(event) {
    const d = event.details;
    if (!d) return '—';
    if (event.type === 'tracking_gap' && d.reason === 'provider_silent') {
        return t('fleet.event_details.provider_silent', { after: Number.isFinite(Number(d.silence_seconds)) ? t('fleet.event_details.after', { s: Number(d.silence_seconds) }) : '' });
    }
    if (event.type === 'airplane_mode_on' && d.detected_during === 'trip_start') return t('fleet.event_details.airplane_start');
    if (event.type === 'airplane_mode_on' && d.detected_during === 'active_trip_restore') return t('fleet.event_details.airplane_restore');
    return '—';
}
const passengerTone = (source) => (source === 'employee_list' ? 'success' : source === 'manual' ? 'warning' : '');
</script>

<template>
    <div class="trip-panel" :class="`layout-${layout}`">
        <div class="trip-main stack">
            <div class="row wrap">
                <StatusBadge kind="review" :value="trip.review_status" />
                <StatusBadge kind="trip" :value="trip.status" />
                <span class="tag">{{ t(manual ? 'fleet.source.manual' : 'fleet.detail.source_app') }}</span>
            </div>

            <!-- Figures (legacy stats row) -->
            <div class="grid metrics">
                <div class="kpi panel"><span class="kpi-label">{{ t('fleet.detail.points') }}</span><span class="kpi-value" :class="pointsTone">{{ manual ? t('fleet.detail.not_available') : `${num(trip.points.received)} / ${num(trip.points.expected)}` }}</span></div>
                <div class="kpi panel"><span class="kpi-label">{{ t(manual ? 'fleet.detail.estimated_distance' : 'fleet.detail.distance') }}</span><span class="kpi-value">{{ num(m ? m.recorded_distance_km : trip.stored_distance_km, 2) }}</span><span class="kpi-hint">{{ t('common.km') }}</span></div>
                <div v-if="m?.filtered" class="kpi panel filtered"><span class="kpi-label">{{ t('fleet.detail.filtered_distance') }}</span><span class="kpi-value">{{ num(m.distance_km, 2) }}</span><span class="kpi-hint">{{ t('common.km') }}</span></div>
                <div class="kpi panel"><span class="kpi-label">{{ t(v2 ? 'fleet.detail.approved_duration' : 'fleet.detail.duration') }}</span><span class="kpi-value">{{ num(m?.duration_minutes) }}</span><span class="kpi-hint">{{ t('fleet.detail.minutes') }}</span></div>
                <div class="kpi panel"><span class="kpi-label">{{ t('fleet.detail.wait_time') }}</span><span class="kpi-value" :class="{ 'tone-warning': trip.total_wait_minutes > 0 }">{{ trip.total_wait_minutes > 0 ? num(trip.total_wait_minutes) : t('fleet.detail.none') }}</span><span v-if="trip.total_wait_minutes > 0" class="kpi-hint">{{ t('fleet.detail.minutes') }}</span></div>
                <div class="kpi panel"><span class="kpi-label">{{ t(fuelLabel) }}</span><span class="kpi-value">{{ num(m ? m.fuel_liters : trip.stored_fuel_liters, 3) }}</span><span class="kpi-hint">{{ m?.fuel_provisional ? t('fleet.detail.provisional_hint') : t('common.liters') }}</span></div>
            </div>

            <div v-if="manual" class="notice"><Icon name="info" /><span><strong>{{ t('fleet.detail.manual_title') }}</strong> {{ t('fleet.detail.manual_body', { name: trip.entered_by_name || t('fleet.detail.assistant') }) }}</span></div>
            <template v-if="m">
                <div v-if="m.fuel_provisional" class="notice"><Icon name="info" /><span><strong>{{ t('fleet.detail.provisional_title') }}</strong> {{ t('fleet.detail.provisional_body', { km: num(m.distance_km, 2), rate: m.fuel_rate_per_km, fuel: num(m.fuel_liters, 3) }) }}</span></div>
                <div v-if="!v2 && m.filtered" class="notice"><Icon name="info" /><span>{{ t('fleet.detail.filtered_old_trip') }}</span></div>
                <div v-else-if="!m.filtered" class="notice"><Icon name="info" /><span>{{ t('fleet.detail.recorded_only') }}</span></div>

                <div v-if="showQuality" class="notice warning">
                    <Icon name="alert-triangle" />
                    <div class="stack" style="gap: var(--s-1)">
                        <strong>{{ t('fleet.detail.quality_title') }}</strong>
                        <span v-if="m.quality_flags.length">{{ m.quality_flags.map((f) => t(`fleet.flags.${f}`)).join('، ') }}</span>
                        <span class="muted" style="font-size: var(--fs-xs)">{{ t('fleet.detail.raw_values', { km: num(m.recorded_distance_km, 2), min: m.reported_duration_minutes ?? '—' }) }} {{ processingText }}</span>
                        <span v-if="rejectionSummary.length" class="muted" style="font-size: var(--fs-xs)">{{ t('fleet.detail.rejection_reasons', { list: rejectionSummary.join('، ') }) }}</span>
                    </div>
                </div>

                <!-- Conservative tracking-gap estimate (review only, never stored) -->
                <section v-if="gapCount" class="panel gap-panel">
                    <div class="gap-head">
                        <div>
                            <h3 class="section-title">{{ t('fleet.detail.gaps_title') }}</h3>
                            <span class="muted" style="font-size: var(--fs-sm)">{{ t('fleet.detail.gaps_count', { n: m.gap_estimate.estimated }) }}<template v-if="m.gap_estimate.unestimated">{{ t('fleet.detail.gaps_unestimated', { n: m.gap_estimate.unestimated }) }}</template></span>
                        </div>
                        <span class="badge warning">{{ t('fleet.detail.review_only') }}</span>
                    </div>
                    <div v-if="m.gap_estimate.estimated" class="gap-grid">
                        <div class="panel"><span>{{ t('fleet.detail.gap_distance') }}</span><strong>{{ num(m.gap_estimate.distance_km, 2) }} {{ t('common.km') }}</strong></div>
                        <div class="panel"><span>{{ t('fleet.detail.distance_with_gaps') }}</span><strong>{{ num(m.gap_estimate.distance_with_gaps_km, 2) }} {{ t('common.km') }}</strong></div>
                        <div class="panel"><span>{{ t('fleet.detail.fuel_with_gaps') }}</span><strong>{{ num(m.gap_estimate.fuel_with_gaps_liters, 3) }} {{ t('common.liters') }}</strong></div>
                    </div>
                    <p class="muted" style="font-size: var(--fs-sm)">{{ t('fleet.detail.gaps_method') }}</p>
                    <p v-if="!m.gap_estimate.estimated" class="muted" style="font-size: var(--fs-sm)">{{ t('fleet.detail.gaps_none') }}</p>
                </section>
            </template>

            <dl class="dl">
                <dt>{{ t('fleet.detail.route') }}</dt><dd>{{ trip.origin }} <Icon name="arrow-right" :size="12" class="flip subtle" /> {{ trip.destination }}</dd>
                <dt>{{ t('fleet.detail.mission') }}</dt><dd>{{ trip.mission_type ?? '—' }}</dd>
                <dt>{{ t('fleet.detail.started') }}</dt><dd class="num">{{ date(trip.started_at) }}</dd>
                <dt>{{ t('fleet.detail.ended') }}</dt><dd class="num">{{ date(trip.ended_at) }}</dd>
                <dt>{{ t('fleet.detail.driver') }}</dt><dd>{{ trip.driver.name ?? '—' }} <span class="subtle num" dir="ltr">{{ trip.driver.phone }}</span><span v-if="trip.driver.employee_number" class="subtle num"> · {{ t('fleet.detail.employee', { n: trip.driver.employee_number }) }}</span></dd>
                <dt>{{ t('fleet.detail.vehicle') }}</dt><dd><bdi class="mono">{{ trip.vehicle.plate }}</bdi> · {{ trip.vehicle.name }}<span v-if="trip.vehicle.type" class="subtle"> · {{ trip.vehicle.type }}</span></dd>
                <dt v-if="manual">{{ t('fleet.detail.entered_by') }}</dt><dd v-if="manual">{{ trip.entered_by_name }}</dd>
                <dt v-if="trip.notes">{{ t('fleet.detail.driver_notes') }}</dt><dd v-if="trip.notes">{{ trip.notes }}</dd>
            </dl>

            <!-- Route map -->
            <section v-if="m" class="panel map-panel">
                <div class="panel-head">
                    <div>
                        <h3 class="section-title">{{ t('fleet.map.title') }}</h3>
                        <span class="muted" style="font-size: var(--fs-xs)">{{ t(m.filtered ? 'fleet.map.subtitle_filtered' : 'fleet.map.subtitle_recorded') }}</span>
                    </div>
                    <span class="spacer" />
                    <span class="tag">{{ t('fleet.map.points', { n: num(m.points_count) }) }}</span>
                    <button v-if="m.segments.length" type="button" class="btn btn-sm" @click="map?.fit()">{{ t('fleet.map.fit') }}</button>
                </div>
                <div class="legend">
                    <span><i class="dot start" />{{ t('fleet.map.start') }}: <strong>{{ trip.origin || t('fleet.map.first_point') }}</strong></span>
                    <span><i class="dot end" />{{ t('fleet.map.end') }}: <strong>{{ trip.destination || t('fleet.map.last_point') }}</strong></span>
                    <span><i class="line" />{{ t(hasReturn ? 'fleet.map.outbound' : 'fleet.map.direction') }}</span>
                    <span v-if="hasReturn"><i class="line return" />{{ t('fleet.map.return') }}</span>
                    <span v-if="m.gaps.length"><i class="line gap" />{{ t('fleet.map.gap') }}</span>
                </div>
                <p v-if="m.filtered" class="hint" style="padding: 0 var(--s-4) var(--s-2)">{{ t('fleet.map.integrity') }}</p>
                <TripMap v-if="m.segments.length" ref="map" :segments="m.segments" :gaps="m.gaps" :stops="trip.stops" :start="m.recorded_start" :end="m.recorded_end" :height="layout === 'page' ? 460 : 300" @direction="hasReturn = $event" />
                <div v-else class="notice" style="margin: var(--s-3)"><Icon name="info" />{{ t(m.filtered ? 'fleet.map.empty_filtered' : 'fleet.map.empty_recorded') }}</div>
            </section>

            <!-- Stops -->
            <section v-if="!manual" class="panel">
                <div class="panel-head">
                    <h3 class="section-title">{{ t('fleet.detail.stops_title') }}</h3><span class="spacer" />
                    <span class="badge" :class="trip.stops.length ? 'warning' : 'plain'">{{ t('fleet.detail.stops_count', { n: trip.stops.length }) }}</span>
                    <span class="muted num" style="font-size: var(--fs-sm)"><Icon name="clock" :size="14" /> {{ t('fleet.detail.stops_total', { n: num(trip.total_wait_minutes) }) }}</span>
                </div>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>#</th><th>{{ t('fleet.detail.stop_started') }}</th><th>{{ t('fleet.detail.stop_resumed') }}</th><th>{{ t('fleet.detail.stop_duration') }}</th><th>{{ t('fleet.detail.location') }}</th><th>{{ t('fleet.detail.source') }}</th><th>{{ t('fleet.detail.reason') }}</th><th>{{ t('fleet.detail.notes') }}</th></tr></thead>
                        <tbody>
                            <tr v-if="!trip.stops.length"><td colspan="8" class="muted" style="text-align: center">{{ trip.wait_minutes > 0 ? t('fleet.detail.wait_no_stops', { n: trip.wait_minutes }) : t('fleet.detail.no_stops') }}</td></tr>
                            <tr v-for="(stop, i) in trip.stops" :key="i">
                                <td class="num subtle">{{ i + 1 }}</td>
                                <td class="num">{{ date(stop.started_at) }}</td>
                                <td class="num">{{ date(stop.ended_at) }}</td>
                                <td><span v-if="stop.minutes !== null" class="badge warning">{{ t('fleet.detail.minutes_n', { n: stop.minutes }) }}</span><span v-else class="subtle">—</span></td>
                                <td class="num" dir="ltr">{{ coords(stop) }}</td>
                                <td><span class="badge" :class="stop.source === 'automatic' ? 'success' : 'plain'">{{ t(stop.source === 'automatic' ? 'fleet.detail.stop_auto' : 'fleet.detail.stop_manual') }}</span></td>
                                <td>{{ stop.reason ?? '—' }}</td>
                                <td class="muted">{{ stop.notes ?? '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Tracking integrity events -->
            <section v-if="!manual" class="panel">
                <div class="panel-head">
                    <h3 class="section-title">{{ t('fleet.detail.evidence') }}</h3><span class="spacer" />
                    <span class="badge" :class="trip.events.length ? 'warning' : 'success'">{{ t('fleet.detail.events_count', { n: trip.events.length }) }}</span>
                </div>
                <p class="hint" style="padding: 0 var(--s-4)">{{ t('fleet.detail.evidence_hint') }}</p>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>#</th><th>{{ t('fleet.detail.time') }}</th><th>{{ t('fleet.detail.event') }}</th><th>{{ t('fleet.detail.details') }}</th><th>{{ t('fleet.detail.location') }}</th></tr></thead>
                        <tbody>
                            <tr v-if="!trip.events.length"><td colspan="5" class="muted" style="text-align: center">{{ t('fleet.detail.no_events') }}</td></tr>
                            <tr v-for="(event, i) in trip.events" :key="i">
                                <td class="num subtle">{{ i + 1 }}</td>
                                <td class="num">{{ date(event.detected_at) }}</td>
                                <td><span class="badge" :class="eventTone(event.type)">{{ t(`fleet.events.${event.type}`) }}</span></td>
                                <td>{{ eventDetail(event) }}</td>
                                <td class="num" dir="ltr">{{ coords(event) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Passengers -->
            <section class="panel">
                <div class="panel-head">
                    <h3 class="section-title">{{ t('fleet.detail.passengers') }}</h3><span class="spacer" />
                    <span class="badge" :class="trip.passengers.length ? 'success' : 'plain'">{{ t('fleet.detail.passengers_count', { n: trip.passengers.length }) }}</span>
                </div>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>#</th><th>{{ t('fleet.detail.passenger') }}</th><th>{{ t('fleet.detail.employee_id') }}</th><th>{{ t('fleet.detail.added_by') }}</th></tr></thead>
                        <tbody>
                            <tr v-if="!trip.passengers.length"><td colspan="4" class="muted" style="text-align: center">{{ t('fleet.detail.no_passengers') }}</td></tr>
                            <tr v-for="(p, i) in trip.passengers" :key="i">
                                <td class="num subtle">{{ i + 1 }}</td>
                                <td class="cell-main">{{ p.name }}</td>
                                <td class="num">{{ p.employee_id ?? '—' }}</td>
                                <td><span class="badge" :class="passengerTone(p.source) || 'plain'">{{ t(`fleet.passenger_source.${p.source}`) }}</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <aside class="trip-side stack">
            <section v-if="trip.review_note || trip.clarification_response" class="panel panel-body stack" style="gap: var(--s-2)">
                <h3 class="section-title"><Icon name="message-square" />{{ t('fleet.detail.conversation') }}</h3>
                <div v-if="trip.review_note"><div class="label">{{ t('fleet.detail.supervisor_note') }}</div><p class="muted" style="white-space: pre-line">{{ trip.review_note }}</p></div>
                <div v-if="trip.clarification_response"><div class="label">{{ t('fleet.detail.driver_answer') }}</div><p style="white-space: pre-line">{{ trip.clarification_response }}</p><span class="hint num">{{ date(trip.clarification_responded_at) }}</span></div>
            </section>

            <section class="panel panel-body stack" style="gap: var(--s-3)">
                <h3 class="section-title">{{ t('fleet.decision.title') }}</h3>
                <DecisionForm v-if="data.can.review" :trip-id="trip.id" :base="base" :can-clarify="data.can.clarify" :has-response="!!trip.clarification_response" @decided="emit('decided', $event)" />
                <div v-else class="notice"><Icon name="lock" />{{ t(['approved', 'rejected'].includes(trip.review_status) ? 'fleet.decision.final' : trip.status !== 'confirmed' ? 'fleet.decision.not_ready' : 'fleet.decision.no_scope') }}</div>
            </section>

            <section v-if="data.audit.length" class="panel panel-body stack" style="gap: var(--s-2)">
                <h3 class="section-title"><Icon name="history" />{{ t('fleet.detail.audit') }}</h3>
                <ul class="timeline">
                    <li v-for="entry in data.audit" :key="entry.id" :class="{ success: entry.review_status === 'approved', danger: entry.review_status === 'rejected', warning: entry.review_status === 'needs_clarification' }">
                        <div class="cell-main">{{ entry.review_status ? t(`status.review.${entry.review_status}`) : entry.event }}</div>
                        <div class="cell-sub">{{ entry.actor }} · <span class="num">{{ date(entry.at) }}</span></div>
                        <div v-if="entry.note" class="muted" style="font-size: var(--fs-sm); white-space: pre-line">{{ entry.note }}</div>
                    </li>
                </ul>
            </section>
        </aside>
    </div>
</template>

<style scoped>
.trip-panel.layout-drawer { display: flex; flex-direction: column; gap: var(--s-4); }
.trip-panel.layout-page { display: grid; grid-template-columns: minmax(0, 1fr) 360px; gap: var(--s-5); align-items: start; }
.trip-panel.layout-page .trip-side { position: sticky; top: calc(var(--topbar-h) + var(--s-4)); }
.metrics { grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: var(--s-2); }
.metrics .kpi { padding: var(--s-3); }
.metrics .kpi-value { font-size: var(--fs-xl); }
.metrics .kpi.filtered { border-color: color-mix(in srgb, var(--accent) 40%, var(--border)); }
.tone-success { color: var(--success); }
.tone-warning { color: var(--warning); }
.tone-danger { color: var(--danger); }
.gap-panel { padding: var(--s-4); display: flex; flex-direction: column; gap: var(--s-3); border-color: color-mix(in srgb, var(--warning) 45%, var(--border)); background: color-mix(in srgb, var(--warning-bg) 55%, var(--surface)); }
.gap-head { display: flex; justify-content: space-between; align-items: flex-start; gap: var(--s-3); }
.gap-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: var(--s-2); }
.gap-grid .panel { padding: var(--s-3); display: flex; flex-direction: column; gap: var(--s-1); }
.gap-grid span { font-size: var(--fs-sm); color: var(--text-2); }
.gap-grid strong { font-size: var(--fs-xl); color: var(--warning); }
.gap-panel p { margin: 0; }
.panel-head { display: flex; align-items: center; gap: var(--s-2); padding: var(--s-3) var(--s-4); flex-wrap: wrap; }
.table-wrap { overflow-x: auto; }
.legend { display: flex; flex-wrap: wrap; gap: var(--s-2) var(--s-4); padding: 0 var(--s-4) var(--s-2); font-size: var(--fs-sm); color: var(--text-2); }
.legend span { display: inline-flex; align-items: center; gap: 6px; }
.legend .dot { width: 12px; height: 12px; border-radius: 50%; border: 2px solid #fff; box-shadow: 0 0 0 1px var(--border-strong); }
.legend .dot.start { background: #059669; }
.legend .dot.end { background: #dc2626; }
.legend .line { width: 22px; height: 4px; border-radius: 2px; background: #2563eb; }
.legend .line.return { background: #ea580c; }
.legend .line.gap { background: repeating-linear-gradient(90deg, #d97706 0 6px, transparent 6px 10px); }
.map-panel :deep(.map) { border-radius: 0 0 var(--r-lg) var(--r-lg); }
@media (max-width: 1100px) { .trip-panel.layout-page { grid-template-columns: minmax(0, 1fr); } .trip-panel.layout-page .trip-side { position: static; } }
</style>

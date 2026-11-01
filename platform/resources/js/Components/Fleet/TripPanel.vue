<script setup>
import { computed } from 'vue';
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
const severe = ['gps_disabled', 'airplane_mode_on', 'device_shutdown', 'location_permission_missing', 'tracking_gap', 'clock_anomaly'];
</script>

<template>
    <div class="trip-panel" :class="`layout-${layout}`">
        <div class="trip-main stack">
            <div class="row wrap">
                <StatusBadge kind="review" :value="trip.review_status" />
                <StatusBadge kind="trip" :value="trip.status" />
                <span v-if="manual" class="tag">{{ t('fleet.source.manual') }}</span>
                <span v-if="m && !m.filtered" class="tag">{{ t('fleet.detail.legacy_policy') }}</span>
            </div>

            <dl class="dl">
                <dt>{{ t('fleet.detail.driver') }}</dt><dd>{{ trip.driver.name ?? '—' }} <span class="subtle num" dir="ltr">{{ trip.driver.phone }}</span></dd>
                <dt>{{ t('fleet.detail.vehicle') }}</dt><dd><bdi class="mono">{{ trip.vehicle.plate }}</bdi> · {{ trip.vehicle.name }}</dd>
                <dt>{{ t('fleet.detail.route') }}</dt><dd>{{ trip.origin }} <Icon name="arrow-right" :size="12" class="flip subtle" /> {{ trip.destination }}</dd>
                <dt>{{ t('fleet.detail.mission') }}</dt><dd>{{ trip.mission_type ?? '—' }}</dd>
                <dt>{{ t('fleet.detail.time') }}</dt><dd class="num">{{ date(trip.started_at) }} — {{ date(trip.ended_at, 'time') }}</dd>
                <dt v-if="manual">{{ t('fleet.detail.entered_by') }}</dt><dd v-if="manual">{{ trip.entered_by_name }}</dd>
                <dt v-if="trip.notes">{{ t('fleet.detail.notes') }}</dt><dd v-if="trip.notes">{{ trip.notes }}</dd>
            </dl>

            <div class="grid grid-4 metrics">
                <div class="kpi panel"><span class="kpi-label">{{ t(m?.filtered ? 'fleet.detail.filtered_distance' : 'fleet.detail.distance') }}</span><span class="kpi-value">{{ num(m ? m.distance_km : trip.stored_distance_km, 2) }}</span><span class="kpi-hint">{{ t('common.km') }}</span></div>
                <div class="kpi panel"><span class="kpi-label">{{ t('fleet.detail.fuel') }}</span><span class="kpi-value">{{ num(m ? m.fuel_liters : trip.stored_fuel_liters, 2) }}</span><span class="kpi-hint">{{ m?.fuel_provisional ? t('fleet.detail.provisional') : t('common.liters') }}</span></div>
                <div class="kpi panel"><span class="kpi-label">{{ t('fleet.detail.duration') }}</span><span class="kpi-value" style="font-size: var(--fs-xl)">{{ duration(m?.duration_minutes) }}</span><span class="kpi-hint">{{ t('fleet.detail.wait', { m: num(trip.wait_minutes) }) }}</span></div>
                <div class="kpi panel"><span class="kpi-label">{{ t('fleet.detail.points') }}</span><span class="kpi-value">{{ num(trip.points.received) }}</span><span class="kpi-hint">{{ t('fleet.detail.of_expected', { n: num(trip.points.expected) }) }}</span></div>
            </div>

            <template v-if="m">
                <TripMap :segments="m.segments" :gaps="m.gaps" :stops="trip.stops" :height="layout === 'page' ? 420 : 280" />
                <div v-if="m.gap_estimate.estimated || m.gap_estimate.unestimated" class="notice warning">
                    <Icon name="alert-triangle" />
                    <div>
                        <strong>{{ t('fleet.detail.gaps_title') }}</strong>
                        <div class="muted">{{ t('fleet.detail.gaps_body', { km: num(m.gap_estimate.distance_km, 2), total: num(m.gap_estimate.distance_with_gaps_km, 2), fuel: num(m.gap_estimate.fuel_with_gaps_liters, 2), n: m.gap_estimate.estimated, u: m.gap_estimate.unestimated }) }}</div>
                    </div>
                </div>
                <div v-if="m.quality_flags.length" class="row wrap">
                    <span class="subtle" style="font-size: var(--fs-xs)">{{ t('fleet.detail.quality') }}</span>
                    <span v-for="flag in m.quality_flags" :key="flag" class="tag">{{ t(`fleet.flags.${flag}`) }}</span>
                </div>
            </template>
            <div v-else class="notice"><Icon name="info" />{{ t('fleet.detail.manual_no_gps') }}</div>

            <section v-if="trip.events.length" class="stack" style="gap: var(--s-2)">
                <h3 class="section-title">{{ t('fleet.detail.evidence') }}</h3>
                <p class="hint">{{ t('fleet.detail.evidence_hint') }}</p>
                <ul class="timeline">
                    <li v-for="(event, i) in trip.events" :key="i" :class="severe.includes(event.type) ? 'warning' : ''">
                        <div class="cell-main">{{ t(`fleet.events.${event.type}`) }}</div>
                        <div class="cell-sub num">{{ date(event.detected_at) }}</div>
                    </li>
                </ul>
            </section>

            <section v-if="trip.stops.length" class="stack" style="gap: var(--s-2)">
                <h3 class="section-title">{{ t('fleet.detail.stops', { n: trip.stops.length }) }}</h3>
                <div v-for="(stop, i) in trip.stops" :key="i" class="row muted" style="font-size: var(--fs-sm)">
                    <Icon name="clock" :size="14" /><span class="num">{{ date(stop.started_at, 'time') }}</span> · {{ duration(stop.minutes) }} · {{ t(`fleet.stop_source.${stop.source ?? 'manual'}`) }}
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
.trip-panel.layout-drawer .metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.metrics { gap: var(--s-2); }
.metrics .kpi { padding: var(--s-3); }
.metrics .kpi-value { font-size: var(--fs-xl); }
@media (max-width: 1100px) { .trip-panel.layout-page { grid-template-columns: minmax(0, 1fr); } .trip-panel.layout-page .trip-side { position: static; } }
</style>

<script setup>
import { onBeforeUnmount, onMounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Icon from '../../Components/Icon.vue';
import PageHeader from '../../Components/PageHeader.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import EmptyState from '../../Components/EmptyState.vue';
import { useI18n } from '../../lib/i18n.js';

const props = defineProps({ trips: Array, summary: Object, refreshSeconds: Number });
const { t, num, relative, date } = useI18n();
let timer;
onMounted(() => { timer = setInterval(() => router.reload({ only: ['trips', 'summary'] }), props.refreshSeconds * 1000); });
onBeforeUnmount(() => clearInterval(timer));
</script>

<template>
    <Head :title="t('nav.fleet.live')" />
    <PageHeader :title="t('fleet.live.title')" :description="t('fleet.live.subtitle', { s: refreshSeconds })" />
    <div class="grid grid-4" style="margin-bottom: var(--s-4)">
        <div class="panel kpi"><span class="kpi-label">{{ t('fleet.live.total') }}</span><span class="kpi-value">{{ num(summary.total) }}</span></div>
        <div class="panel kpi"><span class="kpi-label"><span class="sev" style="width:8px;height:8px;border-radius:50%;background:var(--success)" />{{ t('status.health.live') }}</span><span class="kpi-value">{{ num(summary.live) }}</span></div>
        <div class="panel kpi"><span class="kpi-label">{{ t('fleet.live.stale') }}</span><span class="kpi-value">{{ num(summary.stale) }}</span></div>
        <div class="panel kpi"><span class="kpi-label">{{ t('status.health.incident') }}</span><span class="kpi-value" :style="summary.incident ? 'color: var(--danger)' : ''">{{ num(summary.incident) }}</span></div>
    </div>
    <section class="panel">
        <table v-if="trips.length" class="table stackable">
            <thead><tr><th>#</th><th>{{ t('fleet.cols.driver') }}</th><th>{{ t('fleet.cols.route') }}</th><th>{{ t('fleet.live.started') }}</th><th>{{ t('fleet.live.last_point') }}</th><th class="num">{{ t('fleet.detail.points') }}</th><th>{{ t('fleet.live.health') }}</th></tr></thead>
            <tbody>
                <tr v-for="trip in trips" :key="trip.id" class="clickable" @click="router.visit(`/fleet/trips/${trip.id}`)">
                    <td class="num subtle">{{ trip.id }}</td>
                    <td><div class="cell-main">{{ trip.driver_name }}</div><div class="cell-sub mono">{{ trip.vehicle_plate }}</div></td>
                    <td>{{ trip.origin }} <Icon name="arrow-right" :size="12" class="flip subtle" /> {{ trip.destination }}</td>
                    <td class="num">{{ date(trip.started_at, 'time') }}</td>
                    <td><span class="num">{{ relative(trip.last_point_at) }}</span></td>
                    <td class="num">{{ num(trip.points_received) }}</td>
                    <td><StatusBadge kind="health" :value="trip.health" /><div v-if="trip.latest_event" class="cell-sub">{{ t(`fleet.events.${trip.latest_event}`) }}</div></td>
                </tr>
            </tbody>
        </table>
        <EmptyState v-else icon="radio" :title="t('fleet.live.empty_title')" :body="t('fleet.live.empty_body')" />
    </section>
    <p class="hint" style="margin-top: var(--s-2)">{{ t('fleet.detail.evidence_hint') }}</p>
</template>

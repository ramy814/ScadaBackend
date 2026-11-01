<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import Icon from '../../Components/Icon.vue';
import PageHeader from '../../Components/PageHeader.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import EmptyState from '../../Components/EmptyState.vue';
import Pagination from '../../Components/Pagination.vue';
import SearchBox from '../../Components/SearchBox.vue';
import { useI18n } from '../../lib/i18n.js';
import { visit } from '../../lib/query.js';

const props = defineProps({ usage: Object, summary: Object, filters: Object, latestRelease: Object });
const { t, num, relative } = useI18n();
const share = (n) => (props.summary.drivers ? Math.round((n / props.summary.drivers) * 100) : 0);
const bars = computed(() => ['current', 'outdated', 'not_reported'].map((key) => ({ key, value: props.summary[key] ?? 0 })));
const tones = { current: 'var(--success)', outdated: 'var(--warning)', not_reported: 'var(--neutral)' };
</script>

<template>
    <Head :title="t('nav.fleet.app')" />
    <PageHeader :title="t('fleet.app.title')" :description="t('fleet.app.subtitle')">
        <template #actions><span class="tag num" dir="ltr">v{{ latestRelease.version }} · build {{ latestRelease.build }}</span></template>
    </PageHeader>

    <div class="grid" style="grid-template-columns: minmax(0, 1fr) minmax(0, 2fr); margin-bottom: var(--s-4); align-items: start">
        <section class="panel panel-body stack" style="gap: var(--s-3)">
            <h2 class="section-title">{{ t('fleet.app.adoption') }}</h2>
            <div v-for="bar in bars" :key="bar.key" class="bar-row">
                <span>{{ t(`status.version.${bar.key}`) }}</span>
                <span class="bar-track"><span class="bar-fill" :style="{ width: `${share(bar.value)}%`, background: tones[bar.key] }" /></span>
                <span class="num" style="text-align: end">{{ num(bar.value) }}</span>
            </div>
            <p class="hint">{{ t('fleet.app.not_reported_hint') }}</p>
        </section>
        <div class="grid grid-3">
            <div class="panel kpi"><span class="kpi-label">{{ t('fleet.app.drivers') }}</span><span class="kpi-value">{{ num(summary.drivers) }}</span><span class="kpi-hint">{{ t('fleet.app.drivers_hint') }}</span></div>
            <div class="panel kpi"><span class="kpi-label">{{ t('fleet.app.store') }}</span><span class="kpi-value">{{ num(summary.store) }}</span><span class="kpi-hint">{{ t('fleet.app.store_hint') }}</span></div>
            <div class="panel kpi"><span class="kpi-label">{{ t('fleet.app.recent') }}</span><span class="kpi-value">{{ num(summary.recent) }}</span><span class="kpi-hint">{{ t('fleet.app.recent_hint', { d: latestRelease.recent_days }) }}</span></div>
        </div>
    </div>

    <section class="panel">
        <div class="toolbar">
            <SearchBox :model-value="filters.search" :placeholder="t('fleet.app.search')" />
            <select class="select select-sm" style="width: auto" :value="filters.version_status" :aria-label="t('fleet.app.version')" @change="visit({ version_status: $event.target.value })">
                <option value="">{{ t('fleet.app.all_versions') }}</option>
                <option v-for="v in ['current', 'outdated', 'not_reported']" :key="v" :value="v">{{ t(`status.version.${v}`) }}</option>
            </select>
            <select class="select select-sm" style="width: auto" :value="filters.activity" :aria-label="t('fleet.app.activity')" @change="visit({ activity: $event.target.value })">
                <option value="">{{ t('fleet.app.any_activity') }}</option>
                <option value="recent">{{ t('fleet.app.recent') }}</option>
                <option value="inactive">{{ t('fleet.app.inactive') }}</option>
            </select>
        </div>
        <table v-if="usage.data.length" class="table stackable">
            <thead><tr><th>{{ t('fleet.cols.driver') }}</th><th>{{ t('fleet.app.vehicles') }}</th><th>{{ t('fleet.app.version') }}</th><th>{{ t('fleet.app.source') }}</th><th>{{ t('fleet.app.last_seen') }}</th><th>{{ t('fleet.app.last_trip') }}</th></tr></thead>
            <tbody>
                <tr v-for="row in usage.data" :key="row.oracle_driver_id">
                    <td><div class="cell-main">{{ row.driver_name ?? '—' }}</div><div class="cell-sub num" dir="ltr" style="text-align: start">{{ row.driver_phone }}</div></td>
                    <td><span v-for="v in row.assigned_vehicles" :key="v.oracle_id" class="tag mono" style="margin-inline-end: 4px">{{ v.plate_number }}</span><span v-if="!row.assigned_vehicles.length" class="subtle">—</span></td>
                    <td><StatusBadge kind="version" :value="row.version_status" /><div v-if="row.app_version" class="cell-sub num" dir="ltr" style="text-align: start">v{{ row.app_version }} ({{ row.build_number }})<template v-if="row.device_count > 1"> · {{ t('fleet.app.devices', { n: row.device_count }) }}</template></div></td>
                    <td>{{ t(`fleet.app.sources.${row.install_source}`) }}</td>
                    <td class="num">{{ relative(row.last_seen_at) }}</td>
                    <td class="num">{{ relative(row.last_trip_at) }}</td>
                </tr>
            </tbody>
        </table>
        <EmptyState v-else icon="smartphone" :title="t('common.no_results_title')" :body="t('common.no_results_body')" />
        <Pagination :page="usage" />
    </section>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3';
import Icon from '../../Components/Icon.vue';
import PageHeader from '../../Components/PageHeader.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import EmptyState from '../../Components/EmptyState.vue';
import Pagination from '../../Components/Pagination.vue';
import SearchBox from '../../Components/SearchBox.vue';
import { useI18n } from '../../lib/i18n.js';
import { visit } from '../../lib/query.js';

defineProps({ trips: Object, summary: Object, filters: Object, csvUrl: String });
const { t, num, date } = useI18n();
</script>

<template>
    <Head :title="t('nav.fleet.reports')" />
    <PageHeader :title="t('fleet.reports.title')" :description="t('fleet.reports.subtitle')">
        <template #actions><a :href="csvUrl" class="btn"><Icon name="download" />{{ t('common.export_csv') }}</a></template>
    </PageHeader>
    <div class="grid grid-3" style="margin-bottom: var(--s-4)">
        <div class="panel kpi"><span class="kpi-label">{{ t('fleet.reports.trips') }}</span><span class="kpi-value">{{ num(summary.trips) }}</span></div>
        <div class="panel kpi"><span class="kpi-label">{{ t('fleet.reports.distance') }}</span><span class="kpi-value">{{ num(summary.distance_km, 1) }}</span><span class="kpi-hint">{{ t('common.km') }}</span></div>
        <div class="panel kpi"><span class="kpi-label">{{ t('fleet.reports.fuel') }}</span><span class="kpi-value">{{ num(summary.fuel_liters, 1) }}</span><span class="kpi-hint">{{ t('fleet.reports.fuel_hint') }}</span></div>
    </div>
    <section class="panel">
        <div class="toolbar">
            <SearchBox param="driver" :model-value="filters.driver" :placeholder="t('fleet.reports.driver')" />
            <SearchBox param="vehicle" :model-value="filters.vehicle" :placeholder="t('fleet.reports.vehicle')" />
            <div class="segmented">
                <button v-for="p in ['day', 'week', 'month']" :key="p" type="button" :aria-current="(filters.period ?? 'month') === p" @click="visit({ period: p })">{{ t(`period.${p}`) }}</button>
            </div>
        </div>
        <table v-if="trips.data.length" class="table stackable">
            <thead><tr><th>#</th><th>{{ t('fleet.cols.driver') }}</th><th>{{ t('fleet.cols.route') }}</th><th>{{ t('fleet.cols.started') }}</th><th class="num">{{ t('fleet.cols.distance') }}</th><th class="num">{{ t('fleet.detail.fuel') }}</th><th>{{ t('fleet.cols.status') }}</th></tr></thead>
            <tbody>
                <tr v-for="trip in trips.data" :key="trip.id">
                    <td class="num"><Link :href="`/fleet/trips/${trip.id}`" class="subtle">{{ trip.id }}</Link></td>
                    <td><div class="cell-main">{{ trip.driver_name }}</div><div class="cell-sub mono">{{ trip.vehicle_plate }}</div></td>
                    <td>{{ trip.origin_text }} <Icon name="arrow-right" :size="12" class="flip subtle" /> {{ trip.destination_text }}</td>
                    <td class="num">{{ date(trip.started_at) }}</td>
                    <td class="num">{{ num(trip.distance_km, 1) }}</td>
                    <td class="num">{{ num(trip.virtual_fuel_liters, 2) }}</td>
                    <td><StatusBadge kind="review" :value="trip.review_status" /></td>
                </tr>
            </tbody>
        </table>
        <EmptyState v-else icon="file-text" :title="t('common.no_results_title')" :body="t('common.no_results_body')" />
        <Pagination :page="trips" />
    </section>
</template>

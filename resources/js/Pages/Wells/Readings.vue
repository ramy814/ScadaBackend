<script setup>
import { computed, onMounted, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import Icon from '../../Components/Icon.vue';
import PageHeader from '../../Components/PageHeader.vue';
import EmptyState from '../../Components/EmptyState.vue';
import Pagination from '../../Components/Pagination.vue';
import SearchBox from '../../Components/SearchBox.vue';
import ReportTable from '../../Components/ReportTable.vue';
import DeleteRecordDialog from '../../Components/DeleteRecordDialog.vue';
import { useI18n } from '../../lib/i18n.js';
import { visit } from '../../lib/query.js';

const props = defineProps({ readings: Object, filters: Object, reportColumns: Array, report: Object, options: Object, canDelete: Boolean });
const { t, num, date } = useI18n();
const tab = ref(new URLSearchParams(window.location.search).get('tab') === 'report' ? 'report' : 'register');
const loading = ref(false);
const deleter = ref(null);

function openReport() {
    tab.value = 'report';
    if (!props.report) { loading.value = true; router.reload({ only: ['report'], onFinish: () => { loading.value = false; } }); }
}
onMounted(() => { if (tab.value === 'report') openReport(); });
const reportQuery = computed(() => ({ well_oid: props.filters.well_oid, inspector_oid: props.filters.inspector_oid, quality: props.filters.quality, date_from: props.filters.date_from, date_to: props.filters.date_to }));
const applyReport = (patch) => visit({ ...patch, tab: 'report' }, { only: ['report', 'filters', 'readings'] });
</script>

<template>
    <Head :title="t('nav.wells.readings')" />
    <PageHeader :title="t('wells.readings.title')" :description="t('wells.readings.subtitle')" />
    <section class="panel">
        <nav class="tabs" style="padding: 0 var(--s-3)">
            <button type="button" :aria-current="tab === 'register'" @click="tab = 'register'">{{ t('wells.readings.register') }}</button>
            <button type="button" :aria-current="tab === 'report'" @click="openReport">{{ t('wells.readings.production') }}</button>
        </nav>

        <template v-if="tab === 'register'">
            <div class="toolbar">
                <SearchBox :model-value="filters.search ?? ''" :placeholder="t('wells.readings.search')" />
                <label class="row" style="font-size: var(--fs-sm)">{{ t('common.from') }}<input type="date" class="input input-sm num" style="width: auto" :value="filters.date_from" @change="visit({ date_from: $event.target.value })" /></label>
                <label class="row" style="font-size: var(--fs-sm)">{{ t('common.to') }}<input type="date" class="input input-sm num" style="width: auto" :value="filters.date_to" @change="visit({ date_to: $event.target.value })" /></label>
                <span class="spacer" /><span class="hint">{{ t('wells.readings.date_hint') }}</span>
            </div>
            <table v-if="readings.data.length" class="table stackable">
                <thead><tr><th>{{ t('wells.cols.well') }}</th><th>{{ t('wells.cols.inspector') }}</th><th class="num">{{ t('wells.readings.generator') }}</th><th class="num">{{ t('wells.readings.pump') }}</th><th class="num">{{ t('wells.readings.chlorine') }}</th><th>{{ t('wells.readings.reading_date') }}</th><th>{{ t('wells.readings.created') }}</th><th v-if="canDelete" /></tr></thead>
                <tbody>
                    <tr v-for="r in readings.data" :key="r.oid">
                        <td><div class="cell-main">{{ r.well }}</div><div class="cell-sub mono">{{ r.well_number }}</div></td>
                        <td :data-label="t('wells.cols.inspector')">{{ r.inspector }}</td>
                        <td class="num" :data-label="t('wells.readings.generator')">{{ num(r.generator_hours, 1) }}</td>
                        <td class="num" :data-label="t('wells.readings.pump')">{{ num(r.pump_hours, 1) }}</td>
                        <td class="num" :data-label="t('wells.readings.chlorine')">{{ num(r.chlorine, 2) }}</td>
                        <td class="num" :data-label="t('wells.readings.reading_date')">{{ date(r.reading_date) }}</td>
                        <td class="num subtle" :data-label="t('wells.readings.created')">{{ date(r.created_at) }}</td>
                        <td v-if="canDelete" class="num"><button type="button" class="btn btn-ghost btn-sm" @click="deleter.open(`/wells/readings/${r.oid}`)"><Icon name="x" :size="14" />{{ t('common.delete') }}</button></td>
                    </tr>
                </tbody>
            </table>
            <EmptyState v-else icon="activity" :title="t('common.no_results_title')" :body="t('common.no_results_body')" />
            <Pagination :page="readings" />
        </template>

        <template v-else>
            <div class="toolbar">
                <select class="select select-sm" style="width: auto" :value="filters.well_oid ?? ''" @change="applyReport({ well_oid: $event.target.value })">
                    <option value="">{{ t('wells.filters.all_wells') }}</option><option v-for="w in options.wells" :key="w.oid" :value="w.oid">{{ w.name }}</option>
                </select>
                <select class="select select-sm" style="width: auto" :value="filters.inspector_oid ?? ''" @change="applyReport({ inspector_oid: $event.target.value })">
                    <option value="">{{ t('wells.filters.all_inspectors') }}</option><option v-for="i in options.inspectors" :key="i.oid" :value="i.oid">{{ i.name }}</option>
                </select>
                <label class="row" style="font-size: var(--fs-sm)">{{ t('common.from') }}<input type="date" class="input input-sm num" style="width: auto" :value="filters.date_from" @change="applyReport({ date_from: $event.target.value })" /></label>
                <label class="row" style="font-size: var(--fs-sm)">{{ t('common.to') }}<input type="date" class="input input-sm num" style="width: auto" :value="filters.date_to" @change="applyReport({ date_to: $event.target.value })" /></label>
            </div>
            <div v-if="loading || !report" class="panel-body stack"><div class="skeleton" style="height: 64px" /><div class="skeleton" style="height: 240px" /></div>
            <template v-else>
                <div class="grid grid-4" style="padding: var(--s-4); gap: var(--s-3)">
                    <div class="panel kpi"><span class="kpi-label">{{ t('wells.report.completion') }}</span><span class="kpi-value">{{ num(report.summary.completion_percentage, 1) }}%</span><span class="kpi-hint">{{ t('wells.report.reported', { n: report.summary.reported_wells, total: report.summary.total_active_wells }) }}</span></div>
                    <div class="panel kpi"><span class="kpi-label">{{ t('wells.report.missing') }}</span><span class="kpi-value" :style="report.summary.missing_wells ? 'color: var(--warning)' : ''">{{ num(report.summary.missing_wells) }}</span></div>
                    <div class="panel kpi"><span class="kpi-label">{{ t('wells.report.review') }}</span><span class="kpi-value" :style="report.summary.review_wells ? 'color: var(--danger)' : ''">{{ num(report.summary.review_wells) }}</span></div>
                    <div class="panel kpi"><span class="kpi-label">{{ t('wells.report.production') }}</span><span class="kpi-value">{{ num(report.totals.production, 1) }}</span><span class="kpi-hint">{{ t('wells.report.hours', { n: num(report.totals.operating_hours, 1) }) }}</span></div>
                </div>
                <ReportTable :columns="reportColumns" :rows="report.rows" storage-key="wells.readings.columns" export-base="/wells/readings/export" :export-query="reportQuery" />
                <p class="hint" style="padding: var(--s-3) var(--s-4)">{{ t('wells.report.generator_only') }}</p>
            </template>
        </template>
    </section>
    <DeleteRecordDialog ref="deleter" :title="t('wells.readings.delete_title')" />
</template>

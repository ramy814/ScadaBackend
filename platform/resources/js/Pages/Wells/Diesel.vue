<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import Icon from '../../Components/Icon.vue';
import PageHeader from '../../Components/PageHeader.vue';
import ReportTable from '../../Components/ReportTable.vue';
import DeleteRecordDialog from '../../Components/DeleteRecordDialog.vue';
import { useI18n } from '../../lib/i18n.js';
import { visit } from '../../lib/query.js';

const props = defineProps({ internalReport: Object, externalReport: Object, columnSets: Object, unclassifiedWellCount: Number, filters: Object, options: Object, canDelete: Boolean });
const { t, num } = useI18n();
const deleter = ref(null);
const tab = ref(props.filters.tank_group === 'external' ? (props.filters.external_view === 'inventory' ? 'external_inventory' : 'external_results') : 'internal');
const report = computed(() => (tab.value === 'internal' ? props.internalReport : props.externalReport));
const query = computed(() => ({ ...props.filters, report_type: tab.value }));
const highCount = computed(() => report.value.high_consumption_count ?? 0);
</script>

<template>
    <Head :title="t('nav.wells.diesel')" />
    <PageHeader :title="t('wells.diesel.title')" :description="t('wells.diesel.subtitle')">
        <template #actions><Link href="/wells/diesel/monthly" class="btn"><Icon name="file-text" />{{ t('wells.diesel.monthly') }}</Link></template>
    </PageHeader>
    <div v-if="unclassifiedWellCount" class="notice warning" style="margin-bottom: var(--s-4)"><Icon name="alert-triangle" /><span>{{ t('wells.diesel.unclassified', { n: unclassifiedWellCount }) }}</span><span class="spacer" /><Link href="/wells/assets" class="btn btn-sm">{{ t('nav.wells.assets') }}</Link></div>
    <section class="panel">
        <nav class="tabs" style="padding: 0 var(--s-3)">
            <button v-for="key in ['internal', 'external_inventory', 'external_results']" :key="key" type="button" :aria-current="tab === key" @click="tab = key">
                {{ t(`wells.diesel.tabs.${key}`) }}<span class="count">{{ num((key === 'internal' ? internalReport : externalReport).rows.length) }}</span>
            </button>
        </nav>
        <div class="toolbar">
            <select class="select select-sm" style="width: auto" :value="filters.well_oid ?? ''" @change="visit({ well_oid: $event.target.value })">
                <option value="">{{ t('wells.filters.all_wells') }}</option><option v-for="w in options.wells" :key="w.oid" :value="w.oid">{{ w.name }}</option>
            </select>
            <select v-if="tab !== 'internal'" class="select select-sm" style="width: auto" :value="filters.inspector_oid ?? ''" @change="visit({ inspector_oid: $event.target.value })">
                <option value="">{{ t('wells.filters.all_inspectors') }}</option><option v-for="i in options.inspectors" :key="i.oid" :value="i.oid">{{ i.name }}</option>
            </select>
            <label class="row" style="font-size: var(--fs-sm)">{{ t('common.from') }}<input type="date" class="input input-sm num" style="width: auto" :value="filters.date_from" @change="visit({ date_from: $event.target.value })" /></label>
            <label class="row" style="font-size: var(--fs-sm)">{{ t('common.to') }}<input type="date" class="input input-sm num" style="width: auto" :value="filters.date_to" @change="visit({ date_to: $event.target.value })" /></label>
            <span class="spacer" />
            <span v-if="highCount" class="badge danger">{{ t('wells.diesel.high', { n: highCount }) }}</span>
        </div>
        <div class="grid grid-3" style="padding: var(--s-4); gap: var(--s-3)">
            <div class="panel kpi"><span class="kpi-label">{{ t('wells.diesel.hours') }}</span><span class="kpi-value">{{ num(report.totals?.operating_hours, 1) }}</span></div>
            <div class="panel kpi"><span class="kpi-label">{{ t('wells.diesel.supplied') }}</span><span class="kpi-value">{{ num(report.totals?.supplied_fuel_liters, 1) }}</span><span class="kpi-hint">{{ t('common.liters') }}</span></div>
            <div v-if="tab !== 'internal'" class="panel kpi"><span class="kpi-label">{{ t('wells.diesel.consumed') }}</span><span class="kpi-value">{{ num(report.totals?.consumed_fuel_liters, 1) }}</span><span class="kpi-hint">{{ t('common.liters') }}</span></div>
        </div>
        <ReportTable :key="tab" :columns="columnSets[tab]" :rows="report.rows" :storage-key="`wells.diesel.${tab}`" export-base="/wells/diesel/export" :export-query="query"
            :can-delete="canDelete && tab !== 'internal'" @delete="(row) => deleter.open(`/wells/diesel/${row.row_id}`)" />
        <p class="hint" style="padding: var(--s-3) var(--s-4)">{{ t(tab === 'internal' ? 'wells.diesel.internal_hint' : 'wells.diesel.external_hint') }}</p>
    </section>
    <DeleteRecordDialog ref="deleter" :title="t('wells.diesel.delete_title')" />
</template>

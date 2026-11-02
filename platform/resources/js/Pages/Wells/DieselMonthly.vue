<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import Icon from '../../Components/Icon.vue';
import PageHeader from '../../Components/PageHeader.vue';
import ReportTable from '../../Components/ReportTable.vue';
import { useI18n } from '../../lib/i18n.js';
import { visit } from '../../lib/query.js';
import { useCrumb } from '../../lib/shell.js';

const props = defineProps({ report: Object, columnSets: Object, filters: Object, options: Object });
const { t, num } = useI18n();
useCrumb(t('wells.diesel.monthly'));
const query = computed(() => ({ month: props.filters.month, tank_group: props.filters.tank_group, well_oid: props.filters.well_oid }));
</script>

<template>
    <Head :title="t('wells.diesel.monthly')" />
    <PageHeader :title="t('wells.diesel.monthly')" :description="t('wells.diesel.monthly_subtitle')">
        <template #actions><Link href="/wells/diesel" class="btn"><Icon name="arrow-left" class="flip" />{{ t('nav.wells.diesel') }}</Link></template>
    </PageHeader>
    <section class="panel">
        <nav class="tabs" style="padding: 0 var(--s-3)">
            <button v-for="group in ['internal', 'external']" :key="group" type="button" :aria-current="filters.tank_group === group" @click="visit({ tank_group: group })">{{ t(`wells.diesel.groups.${group}`) }}</button>
        </nav>
        <div class="toolbar">
            <label class="row" style="font-size: var(--fs-sm)">{{ t('wells.diesel.month') }}<input type="month" class="input input-sm num" style="width: auto" :value="filters.month" @change="visit({ month: $event.target.value })" /></label>
            <select class="select select-sm" style="width: auto" :value="filters.well_oid ?? ''" @change="visit({ well_oid: $event.target.value })">
                <option value="">{{ t('wells.filters.all_wells') }}</option><option v-for="w in options.wells" :key="w.oid" :value="w.oid">{{ w.name }}</option>
            </select>
        </div>
        <div class="grid grid-3" style="padding: var(--s-4); gap: var(--s-3)">
            <div class="panel kpi"><span class="kpi-label">{{ t('wells.diesel.hours') }}</span><span class="kpi-value">{{ num(report.totals?.operating_hours, 1) }}</span></div>
            <div class="panel kpi"><span class="kpi-label">{{ t('wells.diesel.supplied') }}</span><span class="kpi-value">{{ num(report.totals?.supplied_fuel_liters ?? report.totals?.monthly_supplied_fuel_liters, 1) }}</span></div>
            <div class="panel kpi"><span class="kpi-label">{{ t('wells.diesel.consumed') }}</span><span class="kpi-value">{{ num(report.totals?.consumed_fuel_liters, 1) }}</span></div>
        </div>
        <ReportTable :key="filters.tank_group" :columns="columnSets[filters.tank_group]" :rows="report.rows" :storage-key="`wells.monthly.${filters.tank_group}`" export-base="/wells/diesel/monthly/export" :export-query="query" />
        <p class="hint" style="padding: var(--s-3) var(--s-4)">{{ t(`wells.diesel.monthly_hint_${filters.tank_group}`) }}</p>
    </section>
</template>

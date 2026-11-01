<script setup>
import { Head } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import EmptyState from '../../Components/EmptyState.vue';
import Pagination from '../../Components/Pagination.vue';
import SearchBox from '../../Components/SearchBox.vue';
import { useI18n } from '../../lib/i18n.js';
import { visit } from '../../lib/query.js';

defineProps({ readings: Object, filters: Object });
const { t, num, date } = useI18n();
</script>

<template>
    <Head :title="t('nav.wells.readings')" />
    <PageHeader :title="t('wells.readings.title')" :description="t('wells.readings.subtitle')" />
    <section class="panel">
        <div class="toolbar">
            <SearchBox :model-value="filters.search" :placeholder="t('wells.readings.search')" />
            <label class="row" style="font-size: var(--fs-sm)">{{ t('common.from') }}<input type="date" class="input input-sm num" style="width: auto" :value="filters.date_from" @change="visit({ date_from: $event.target.value })" /></label>
            <label class="row" style="font-size: var(--fs-sm)">{{ t('common.to') }}<input type="date" class="input input-sm num" style="width: auto" :value="filters.date_to" @change="visit({ date_to: $event.target.value })" /></label>
            <span class="spacer" />
            <span class="hint">{{ t('wells.readings.date_hint') }}</span>
        </div>
        <table v-if="readings.data.length" class="table stackable">
            <thead><tr><th>{{ t('wells.cols.well') }}</th><th>{{ t('wells.cols.inspector') }}</th><th class="num">{{ t('wells.readings.generator') }}</th><th class="num">{{ t('wells.readings.pump') }}</th><th class="num">{{ t('wells.readings.chlorine') }}</th><th>{{ t('wells.readings.reading_date') }}</th><th>{{ t('wells.readings.created') }}</th></tr></thead>
            <tbody>
                <tr v-for="r in readings.data" :key="r.oid">
                    <td><div class="cell-main">{{ r.well }}</div><div class="cell-sub mono">{{ r.well_number }}</div></td>
                    <td :data-label="t('wells.cols.inspector')">{{ r.inspector }}</td>
                    <td class="num" :data-label="t('wells.readings.generator')">{{ num(r.generator_hours, 1) }}</td>
                    <td class="num" :data-label="t('wells.readings.pump')">{{ num(r.pump_hours, 1) }}</td>
                    <td class="num" :data-label="t('wells.readings.chlorine')">{{ num(r.chlorine, 2) }}</td>
                    <td class="num" :data-label="t('wells.readings.reading_date')">{{ date(r.reading_date) }}</td>
                    <td class="num subtle" :data-label="t('wells.readings.created')">{{ date(r.created_at) }}</td>
                </tr>
            </tbody>
        </table>
        <EmptyState v-else icon="activity" :title="t('common.no_results_title')" :body="t('common.no_results_body')" />
        <Pagination :page="readings" />
    </section>
</template>

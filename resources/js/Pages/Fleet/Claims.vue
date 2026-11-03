<script setup>
import { reactive, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import Icon from '../../Components/Icon.vue';
import PageHeader from '../../Components/PageHeader.vue';
import EmptyState from '../../Components/EmptyState.vue';
import SearchBox from '../../Components/SearchBox.vue';
import { useI18n } from '../../lib/i18n.js';
import { visit } from '../../lib/query.js';

const props = defineProps({ filters: Object, rows: Array, totals: Object, from: String, to: String, csvUrl: String, pdfUrl: String });
const { t, num } = useI18n();

// A month or a custom range: choosing one clears the other (legacy behaviour).
const month = ref(props.filters.month ?? '');
const dateFrom = ref(props.filters.month ? '' : props.filters.date_from);
const dateTo = ref(props.filters.month ? '' : props.filters.date_to);
const expanded = reactive({});

function onMonth() {
    dateFrom.value = '';
    dateTo.value = '';
    visit({ month: month.value, date_from: null, date_to: null });
}
function onRange() {
    if (!dateFrom.value || !dateTo.value) return;
    month.value = '';
    visit({ month: null, date_from: dateFrom.value, date_to: dateTo.value });
}
// Always two decimals with Latin digits, as in the legacy claim.
const moneyFormat = new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const money = (value) => moneyFormat.format(Number(value ?? 0));
</script>

<template>
    <Head :title="t('nav.fleet.claims')" />
    <PageHeader :title="t('fleet.claims.title')" :description="t('fleet.claims.subtitle')">
        <template #actions>
            <a :href="csvUrl" class="btn"><Icon name="download" />{{ t('common.export_csv') }}</a>
            <a :href="pdfUrl" class="btn"><Icon name="file-text" />PDF</a>
        </template>
    </PageHeader>

    <div class="grid grid-4" style="margin-bottom: var(--s-4)">
        <div class="panel kpi"><span class="kpi-label">{{ t('fleet.claims.vehicles') }}</span><span class="kpi-value">{{ num(totals.vehicles) }}</span></div>
        <div class="panel kpi"><span class="kpi-label">{{ t('fleet.claims.trips') }}</span><span class="kpi-value">{{ num(totals.trips) }}</span></div>
        <div class="panel kpi"><span class="kpi-label">{{ t('fleet.claims.hours') }}</span><span class="kpi-value" dir="ltr">{{ totals.work_time }}</span><span class="kpi-hint">{{ t('fleet.claims.hours_hint', { n: num(totals.work_hours, 2) }) }}</span></div>
        <div class="panel kpi"><span class="kpi-label">{{ t('fleet.claims.amount') }}</span><span class="kpi-value">{{ money(totals.amount) }}</span><span class="kpi-hint">{{ t('fleet.claims.currency') }}</span></div>
    </div>

    <div v-if="totals.current_price_trips || totals.unpriced_trips || totals.hidden_unpriced_vehicles" class="notice warning stack" style="gap: var(--s-1); margin-bottom: var(--s-4)">
        <span v-if="totals.current_price_trips"><Icon name="alert-triangle" :size="14" /> {{ t('fleet.claims.notice_current', { n: totals.current_price_trips }) }}</span>
        <span v-if="totals.unpriced_trips"><Icon name="alert-triangle" :size="14" /> {{ t('fleet.claims.notice_unpriced', { n: totals.unpriced_trips }) }}</span>
        <span v-if="totals.hidden_unpriced_vehicles"><Icon name="info" :size="14" /> {{ t('fleet.claims.notice_hidden', { n: totals.hidden_unpriced_vehicles }) }}</span>
    </div>

    <section class="panel">
        <div class="toolbar" style="flex-wrap: wrap; align-items: end">
            <label class="field"><span class="label">{{ t('fleet.claims.month') }}</span><input v-model="month" class="input input-sm" type="month" @change="onMonth" /></label>
            <label class="field"><span class="label">{{ t('fleet.claims.from') }}</span><input v-model="dateFrom" class="input input-sm" type="date" @change="onRange" /></label>
            <label class="field"><span class="label">{{ t('fleet.claims.to') }}</span><input v-model="dateTo" class="input input-sm" type="date" @change="onRange" /></label>
            <SearchBox param="vehicle_search" :model-value="filters.vehicle_search" :placeholder="t('fleet.claims.search')" />
            <label class="check"><input type="checkbox" :checked="filters.include_unpriced" @change="visit({ include_unpriced: $event.target.checked })" />{{ t('fleet.claims.include_unpriced') }}</label>
        </div>

        <div v-if="rows.length" class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th><th>{{ t('fleet.claims.cols.vehicle') }}</th><th>{{ t('fleet.claims.cols.driver') }}</th>
                        <th class="num">{{ t('fleet.claims.cols.trips') }}</th><th class="num">{{ t('fleet.claims.cols.hours') }}</th>
                        <th>{{ t('fleet.claims.cols.price') }}</th><th class="num">{{ t('fleet.claims.cols.amount') }}</th><th />
                    </tr>
                </thead>
                <tbody>
                    <template v-for="(row, index) in rows" :key="row.oracle_vehicle_id">
                        <tr>
                            <td class="num subtle">{{ index + 1 }}</td>
                            <td><div class="cell-main"><bdi dir="ltr">{{ row.plate_number ?? '—' }}</bdi></div><div class="cell-sub">{{ row.vehicle_name ?? '—' }}</div></td>
                            <td>{{ row.driver_name ?? '—' }}</td>
                            <td class="num">{{ num(row.trips_count) }}</td>
                            <td class="num" dir="ltr">{{ row.work_time }}</td>
                            <td>
                                <span class="num" dir="ltr">{{ row.price_label }}</span>
                                <span v-if="row.current_price_trips" class="badge warning" style="margin-inline-start: 6px" :title="t('fleet.claims.current_price_hint')">{{ t('fleet.claims.current_price') }}</span>
                                <span v-if="row.unpriced_trips" class="badge danger" style="margin-inline-start: 6px">{{ t('fleet.claims.unpriced', { n: row.unpriced_trips }) }}</span>
                            </td>
                            <td class="num" style="font-weight: 600">{{ money(row.amount) }}</td>
                            <td class="num">
                                <button type="button" class="btn btn-sm btn-ghost" :aria-expanded="!!expanded[row.oracle_vehicle_id]" @click="expanded[row.oracle_vehicle_id] = !expanded[row.oracle_vehicle_id]">
                                    {{ t(expanded[row.oracle_vehicle_id] ? 'fleet.claims.hide_days' : 'fleet.claims.show_days') }}
                                    <Icon :name="expanded[row.oracle_vehicle_id] ? 'chevron-up' : 'chevron-down'" :size="14" />
                                </button>
                            </td>
                        </tr>
                        <tr v-if="expanded[row.oracle_vehicle_id]" class="days-row">
                            <td colspan="8">
                                <table class="table days">
                                    <thead><tr><th>{{ t('fleet.claims.cols.date') }}</th><th class="num">{{ t('fleet.claims.cols.trips') }}</th><th class="num">{{ t('fleet.claims.cols.hours') }}</th><th class="num">{{ t('fleet.claims.cols.amount') }}</th></tr></thead>
                                    <tbody>
                                        <tr v-for="day in row.days" :key="day.date">
                                            <td class="num">{{ day.date }}</td><td class="num">{{ num(day.trips_count) }}</td>
                                            <td class="num" dir="ltr">{{ day.work_time }}</td><td class="num">{{ money(day.amount) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                    </template>
                </tbody>
                <tfoot>
                    <tr class="total">
                        <td colspan="3">{{ t('fleet.claims.total') }}</td>
                        <td class="num">{{ num(totals.trips) }}</td>
                        <td class="num" dir="ltr">{{ totals.work_time }}</td>
                        <td />
                        <td class="num">{{ money(totals.amount) }}</td>
                        <td />
                    </tr>
                </tfoot>
            </table>
        </div>
        <EmptyState v-else icon="banknote" :title="t('fleet.claims.empty')" :body="t('fleet.claims.empty_body')" />
    </section>
</template>

<style scoped>
.table-wrap { overflow-x: auto; }
.days-row > td { background: var(--surface-2); padding: var(--s-2) var(--s-4) var(--s-4); }
.table.days { max-width: 640px; background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-md); }
.total td { font-weight: 600; border-top: 2px solid var(--border-strong); }
.notice > span { display: inline-flex; gap: var(--s-2); align-items: center; }
</style>

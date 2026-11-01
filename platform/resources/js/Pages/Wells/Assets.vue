<script setup>
import { Head } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import EmptyState from '../../Components/EmptyState.vue';
import Pagination from '../../Components/Pagination.vue';
import SearchBox from '../../Components/SearchBox.vue';
import { useI18n } from '../../lib/i18n.js';
import { visit } from '../../lib/query.js';

defineProps({ wells: Object, filters: Object });
const { t, num, relative } = useI18n();
const damageTone = { 'سليمة': 'success', 'متضررة': 'warning', 'أعيد تأهيلها': 'info', 'مدمرة': 'danger' };
</script>

<template>
    <Head :title="t('nav.wells.assets')" />
    <PageHeader :title="t('wells.assets.title')" :description="t('wells.assets.subtitle')" />
    <section class="panel">
        <div class="toolbar">
            <SearchBox :model-value="filters.search" :placeholder="t('wells.assets.search')" />
            <select class="select select-sm" style="width: auto" :value="filters.damage_status ?? ''" :aria-label="t('wells.assets.damage')" @change="visit({ damage_status: $event.target.value })">
                <option value="">{{ t('wells.assets.any_damage') }}</option>
                <option v-for="d in ['سليمة', 'متضررة', 'أعيد تأهيلها', 'مدمرة']" :key="d" :value="d">{{ d }}</option>
            </select>
            <div class="segmented">
                <button type="button" :aria-current="(filters.is_active ?? '') === ''" @click="visit({ is_active: null })">{{ t('common.all') }}</button>
                <button type="button" :aria-current="filters.is_active === '1'" @click="visit({ is_active: '1' })">{{ t('common.active') }}</button>
                <button type="button" :aria-current="filters.is_active === '0'" @click="visit({ is_active: '0' })">{{ t('common.inactive') }}</button>
            </div>
        </div>
        <table v-if="wells.data.length" class="table stackable">
            <thead><tr><th>{{ t('wells.cols.well') }}</th><th>{{ t('wells.assets.operation') }}</th><th>{{ t('wells.assets.damage') }}</th><th>{{ t('wells.assets.pump') }}</th><th class="num">{{ t('wells.assets.capacity') }}</th><th>{{ t('wells.assets.last_reading') }}</th><th>{{ t('common.state') }}</th></tr></thead>
            <tbody>
                <tr v-for="w in wells.data" :key="w.oid">
                    <td><div class="cell-main">{{ w.name }}</div><div class="cell-sub mono">{{ w.number }}</div></td>
                    <td>{{ w.operational_status ?? '—' }}</td>
                    <td><span class="badge" :class="damageTone[w.damage_status]">{{ w.damage_status ?? '—' }}</span></td>
                    <td>{{ w.pump_type ? t(`wells.pump.${w.pump_type}`) : '—' }}</td>
                    <td class="num">{{ num(w.capacity) }}</td>
                    <td class="num">{{ w.last_reading_at ? relative(w.last_reading_at) : t('wells.today.never') }}</td>
                    <td><StatusBadge kind="active" :value="w.is_active" /></td>
                </tr>
            </tbody>
        </table>
        <EmptyState v-else icon="droplets" :title="t('common.no_results_title')" :body="t('common.no_results_body')" />
        <Pagination :page="wells" />
    </section>
</template>

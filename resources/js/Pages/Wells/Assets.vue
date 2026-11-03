<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Icon from '../../Components/Icon.vue';
import PageHeader from '../../Components/PageHeader.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import EmptyState from '../../Components/EmptyState.vue';
import Pagination from '../../Components/Pagination.vue';
import SearchBox from '../../Components/SearchBox.vue';
import ConfirmDialog from '../../Components/ConfirmDialog.vue';
import { useI18n } from '../../lib/i18n.js';
import { visit } from '../../lib/query.js';

defineProps({ wells: Object, generators: [Object, Array], filters: Object });
const { t, num } = useI18n();
const damageTone = { 'سليمة': 'success', 'متضررة': 'warning', 'أعيد تأهيلها': 'info', 'مدمرة': 'danger' };
const active = (v) => v === true || v === 1 || v === '1';
const target = ref(null);
const dialog = ref(null);
const busy = ref(false);
function askDelete(well) { target.value = well; dialog.value.open(); }
function destroy() {
    busy.value = true;
    router.delete(`/wells/assets/${target.value.OID}`, { preserveScroll: true, onFinish: () => { busy.value = false; dialog.value.close(); } });
}
</script>

<template>
    <Head :title="t('nav.wells.assets')" />
    <PageHeader :title="t('wells.assets.title')" :description="t('wells.assets.subtitle')">
        <template #actions>
            <Link href="/wells/map" class="btn"><Icon name="map-pin" />{{ t('nav.wells.map') }}</Link>
            <Link href="/wells/assets/create" class="btn btn-primary"><Icon name="plus" />{{ t('wells.assets.add') }}</Link>
        </template>
    </PageHeader>
    <section class="panel">
        <div class="toolbar">
            <SearchBox :model-value="filters.search ?? ''" :placeholder="t('wells.assets.search')" />
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
            <thead><tr><th>{{ t('wells.cols.well') }}</th><th>{{ t('wells.assets.quarter') }}</th><th>{{ t('wells.assets.operation') }}</th><th>{{ t('wells.assets.damage') }}</th><th>{{ t('wells.assets.pump') }}</th><th>{{ t('wells.assets.generator') }}</th><th class="num">{{ t('wells.assets.capacity') }}</th><th>{{ t('common.state') }}</th><th /></tr></thead>
            <tbody>
                <tr v-for="w in wells.data" :key="w.OID">
                    <td><div class="cell-main">{{ w.NAME }}</div><div class="cell-sub mono">{{ w.SOURCE_WELL_CODE ?? w.WELL_NUM }}</div></td>
                    <td>{{ w.QUARTER_NAME ?? '—' }}</td>
                    <td>{{ w.OPERATIONAL_STATUS ?? '—' }}</td>
                    <td><span class="badge" :class="damageTone[w.DAMAGE_STATUS]">{{ w.DAMAGE_STATUS ?? '—' }}</span></td>
                    <td>{{ w.PUMP_TYPE ? t(`wells.pump.${w.PUMP_TYPE}`) : '—' }}</td>
                    <td>
                        <div v-for="g in w.GENERATORS ?? []" :key="g.GENERATOR_OID"><bdi class="mono">{{ generators[g.GENERATOR_OID]?.GENERATOR_NUM ?? g.GENERATOR_OID }}</bdi> <span class="cell-sub">{{ generators[g.GENERATOR_OID]?.NAME }}</span></div>
                        <span v-if="!(w.GENERATORS ?? []).length" class="subtle">—</span>
                    </td>
                    <td class="num">{{ num(w.CAPACITY) }}</td>
                    <td><StatusBadge kind="active" :value="active(w.IS_ACTIVE)" /></td>
                    <td class="num" style="white-space: nowrap">
                        <Link :href="`/wells/assets/${w.OID}/edit`" class="btn btn-ghost btn-sm"><Icon name="pencil" :size="14" />{{ t('common.edit') }}</Link>
                        <button v-if="w.CAN_DELETE" type="button" class="btn btn-ghost btn-sm" @click="askDelete(w)"><Icon name="x" :size="14" /></button>
                        <span v-else class="btn btn-ghost btn-sm" :title="t('wells.assets.locked', { reasons: (w.DELETE_BLOCKERS ?? []).join('، ') })" aria-disabled="true"><Icon name="lock" :size="14" /></span>
                    </td>
                </tr>
            </tbody>
        </table>
        <EmptyState v-else icon="droplets" :title="t('common.no_results_title')" :body="t('common.no_results_body')" />
        <Pagination :page="wells" />
    </section>
    <p class="hint" style="margin-top: var(--s-2)">{{ t('wells.assets.delete_hint') }}</p>
    <ConfirmDialog ref="dialog" :title="t('wells.assets.delete_title', { name: target?.NAME ?? '' })" :confirm-label="t('common.delete')" tone="danger" :busy="busy" @confirm="destroy">
        <p class="muted">{{ t('wells.assets.delete_body') }}</p>
    </ConfirmDialog>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import Icon from './Icon.vue';
import EmptyState from './EmptyState.vue';
import { useI18n } from '../lib/i18n.js';

/**
 * Renders any server report: columns come from the service's allowlisted registry
 * (key, label, type, default). The same selection drives PDF/Excel exports.
 */
const props = defineProps({
    columns: { type: Array, required: true },
    rows: { type: Array, default: () => [] },
    storageKey: { type: String, required: true },
    exportBase: { type: String, default: '' },
    exportQuery: { type: Object, default: () => ({}) },
    canDelete: Boolean,
    perPage: { type: Number, default: 15 },
});
const emit = defineEmits(['delete']);
const { t, num, date } = useI18n();

function loadSelection() {
    try {
        const saved = JSON.parse(localStorage.getItem(props.storageKey) ?? 'null');
        if (Array.isArray(saved)) return saved.filter((key) => props.columns.some((c) => c.key === key));
    } catch {}
    return props.columns.filter((c) => c.default).map((c) => c.key);
}
const selected = ref(loadSelection());
watch(selected, (value) => { try { localStorage.setItem(props.storageKey, JSON.stringify(value)); } catch {} }, { deep: true });
const visible = computed(() => props.columns.filter((c) => selected.value.includes(c.key)));
const picker = ref(false);

const page = ref(1);
watch(() => props.rows, () => { page.value = 1; });
const pages = computed(() => Math.max(1, Math.ceil(props.rows.length / props.perPage)));
const slice = computed(() => props.rows.slice((page.value - 1) * props.perPage, page.value * props.perPage));

const tones = { normal: 'success', high: 'danger', invalid: 'danger', unavailable: '', first: 'info', missing: 'warning', review: 'warning', abnormal: 'danger' };
function exportUrl(format) {
    const query = new URLSearchParams();
    for (const [key, value] of Object.entries(props.exportQuery)) if (value !== null && value !== undefined && value !== '') query.set(key, value);
    for (const key of selected.value) query.append('columns[]', key);
    return `${props.exportBase}/${format}?${query}`;
}
function cell(row, column) {
    const value = row[column.key];
    if (column.type === 'number') return num(value, 2);
    if (column.type === 'date') return value ? date(value) : (row[`${column.key}_unavailable_reason`] ?? '—');
    return value ?? (row[`${column.key}_unavailable_reason`] ?? '—');
}
</script>

<template>
    <div>
        <div class="toolbar" style="border-top: 1px solid var(--border)">
            <span class="subtle num" style="font-size: var(--fs-xs)">{{ t('reports.rows', { n: num(rows.length) }) }}</span>
            <span class="spacer" />
            <div style="position: relative">
                <button type="button" class="btn btn-sm" :aria-expanded="picker" @click="picker = !picker"><Icon name="list" :size="14" />{{ t('reports.columns', { n: visible.length }) }}</button>
                <div v-if="picker" class="panel" style="position: absolute; inset-inline-end: 0; top: 34px; z-index: 30; width: 260px; max-height: 340px; overflow: auto; box-shadow: var(--shadow-pop)">
                    <div class="panel-body stack" style="gap: var(--s-2)">
                        <label v-for="column in columns" :key="column.key" class="check"><input v-model="selected" type="checkbox" :value="column.key" />{{ column.label }}</label>
                    </div>
                </div>
            </div>
            <template v-if="exportBase">
                <a :href="exportUrl('excel')" class="btn btn-sm"><Icon name="download" :size="14" />Excel</a>
                <a :href="exportUrl('pdf')" class="btn btn-sm"><Icon name="file-text" :size="14" />PDF</a>
            </template>
        </div>
        <div v-if="rows.length" class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th class="num">#</th><th v-for="column in visible" :key="column.key" :class="{ num: column.type === 'number' }">{{ column.label }}</th><th v-if="canDelete" /></tr>
                </thead>
                <tbody>
                    <tr v-for="(row, i) in slice" :key="row.row_id ?? row.well_oid ?? i">
                        <td class="num subtle">{{ row.index ?? (page - 1) * perPage + i + 1 }}</td>
                        <td v-for="column in visible" :key="column.key" :class="{ num: column.type === 'number' }" :title="column.type === 'status' ? row[column.key]?.reason : undefined">
                            <span v-if="column.type === 'status' && row[column.key]" class="badge" :class="tones[row[column.key].key]">{{ row[column.key].label }}</span>
                            <span v-else>{{ cell(row, column) }}</span>
                        </td>
                        <td v-if="canDelete" class="num">
                            <button v-if="row.row_id" type="button" class="btn btn-ghost btn-sm" :aria-label="t('common.delete')" @click="emit('delete', row)"><Icon name="x" :size="14" /></button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <EmptyState v-else icon="file-text" :title="t('common.no_results_title')" :body="t('common.no_results_body')" />
        <nav v-if="pages > 1" class="pager">
            <span class="num">{{ t('reports.page', { page, pages }) }}</span>
            <span class="spacer" />
            <button type="button" class="btn btn-sm" :disabled="page === 1" @click="page--">{{ t('common.previous') }}</button>
            <button type="button" class="btn btn-sm" :disabled="page === pages" @click="page++">{{ t('common.next') }}</button>
        </nav>
    </div>
</template>

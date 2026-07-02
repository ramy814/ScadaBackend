<script setup>
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    columns: { type: Array, required: true },
    rows: { type: Array, required: true },
    pagination: { type: Object, default: null },
    emptyMessage: { type: String, default: 'لا توجد بيانات' },
});
</script>

<template>
    <div class="card">
        <div class="data-table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th v-for="col in columns" :key="col.key">{{ col.label }}</th>
                        <th v-if="$slots.actions">إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="rows.length === 0">
                        <td :colspan="columns.length + ($slots.actions ? 1 : 0)">
                            <div class="empty-state">
                                <div class="empty-state-icon">📭</div>
                                <div class="empty-state-title">{{ emptyMessage }}</div>
                            </div>
                        </td>
                    </tr>
                    <tr v-for="row in rows" :key="row.OID" class="animate-fade-in">
                        <td v-for="col in columns" :key="col.key">
                            <slot :name="`cell-${col.key}`" :row="row" :value="row[col.key]">
                                {{ row[col.key] }}
                            </slot>
                        </td>
                        <td v-if="$slots.actions">
                            <slot name="actions" :row="row" />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="pagination && pagination.last_page > 1" class="pagination">
            <Link
                v-for="link in pagination.links"
                :key="link.label"
                :href="link.url || '#'"
                class="pagination-btn"
                :class="{ active: link.active }"
                :disabled="!link.url"
                v-html="link.label"
                preserve-scroll
            />
        </div>
    </div>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import EmptyState from '../../Components/EmptyState.vue';
import Pagination from '../../Components/Pagination.vue';
import SearchBox from '../../Components/SearchBox.vue';
import { useI18n } from '../../lib/i18n.js';
import { visit } from '../../lib/query.js';

defineProps({ logs: Object, filters: Object, events: Array, tripBase: String });
const { t, date } = useI18n();
</script>

<template>
    <Head :title="t('nav.platform.audit')" />
    <PageHeader :title="t('audit.title')" :description="t('audit.subtitle')" />
    <section class="panel">
        <div class="toolbar">
            <SearchBox param="entity" :model-value="filters.entity" :placeholder="t('audit.search')" />
            <select class="select select-sm" style="width: auto" :value="filters.event ?? ''" :aria-label="t('audit.event')" @change="visit({ event: $event.target.value })">
                <option value="">{{ t('audit.any_event') }}</option>
                <option v-for="e in events" :key="e" :value="e">{{ t(`audit.events.${e}`) }}</option>
            </select>
        </div>
        <table v-if="logs.data.length" class="table stackable">
            <thead><tr><th>{{ t('audit.when') }}</th><th>{{ t('audit.event') }}</th><th>{{ t('audit.record') }}</th><th>{{ t('audit.actor') }}</th><th>{{ t('audit.details') }}</th></tr></thead>
            <tbody>
                <tr v-for="log in logs.data" :key="log.id">
                    <td class="num">{{ date(log.at) }}</td>
                    <td>{{ t(`audit.events.${log.event}`) }}</td>
                    <td><Link v-if="log.entity_type === 'trip'" :href="`${tripBase}/${log.entity_id}`" class="num">{{ t('fleet.detail.trip', { id: log.entity_id }) }}</Link><span v-else>{{ log.entity_type }} #{{ log.entity_id }}</span></td>
                    <td>{{ log.actor }}</td>
                    <td>
                        <StatusBadge v-if="log.payload?.review_status" kind="review" :value="log.payload.review_status" />
                        <span v-if="log.payload?.note" class="muted" style="margin-inline-start: 6px">{{ log.payload.note }}</span>
                    </td>
                </tr>
            </tbody>
        </table>
        <EmptyState v-else icon="history" :title="t('common.no_results_title')" />
        <Pagination :page="logs" />
    </section>
    <p class="hint" style="margin-top: var(--s-2)">{{ t('audit.append_only') }}</p>
</template>

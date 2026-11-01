<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import EmptyState from '../../Components/EmptyState.vue';
import Pagination from '../../Components/Pagination.vue';
import { useI18n } from '../../lib/i18n.js';

defineProps({ reports: Object, status: String, counts: Object });
const { t, num, date, relative } = useI18n();
</script>

<template>
    <Head :title="t('nav.valves.maintenance')" />
    <PageHeader :title="t('valves.maintenance.title')" :description="t('valves.maintenance.subtitle')" />
    <section class="panel">
        <nav class="tabs" style="padding: 0 var(--s-3)">
            <Link v-for="s in ['all', 'pending', 'in_progress', 'resolved']" :key="s" :href="s === 'all' ? '/valves/maintenance' : `/valves/maintenance?status=${s}`" :aria-current="status === s">
                {{ s === 'all' ? t('common.all') : t(`status.maintenance.${s}`) }}<span v-if="s !== 'all' && counts[s]" class="count">{{ num(counts[s]) }}</span>
            </Link>
        </nav>
        <table v-if="reports.data.length" class="table stackable">
            <thead><tr><th>{{ t('valves.cols.valve') }}</th><th>{{ t('valves.maintenance.issue') }}</th><th>{{ t('valves.maintenance.priority') }}</th><th>{{ t('valves.maintenance.reporter') }}</th><th>{{ t('valves.maintenance.reported') }}</th><th>{{ t('common.state') }}</th></tr></thead>
            <tbody>
                <tr v-for="r in reports.data" :key="r.oid">
                    <td><div class="cell-main mono">{{ r.valve.code }}</div><div class="cell-sub">{{ r.valve.location }}</div></td>
                    <td style="max-width: 360px">{{ r.description }}</td>
                    <td><StatusBadge kind="priority" :value="r.priority" /></td>
                    <td>{{ r.reporter ?? '—' }}</td>
                    <td class="num" :title="date(r.created_at)">{{ relative(r.created_at) }}</td>
                    <td><StatusBadge kind="maintenance" :value="r.status" /><div v-if="r.resolved_at" class="cell-sub num">{{ date(r.resolved_at) }}</div></td>
                </tr>
            </tbody>
        </table>
        <EmptyState v-else icon="wrench" :title="t('valves.maintenance.empty')" />
        <Pagination :page="reports" />
    </section>
    <p class="hint" style="margin-top: var(--s-2)">{{ t('valves.maintenance.resolve_hint') }}</p>
</template>

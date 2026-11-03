<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import EmptyState from '../../Components/EmptyState.vue';
import { useI18n } from '../../lib/i18n.js';

const props = defineProps({ stats: Object, distribution: Array, openReports: Array });
const { t, num, relative } = useI18n();
const total = computed(() => props.distribution.reduce((sum, row) => sum + row.valves, 0));
const tones = { open: 'var(--success)', close: 'var(--neutral)', maintenance: 'var(--warning)', replacement: 'var(--info)' };
</script>

<template>
    <Head :title="t('nav.valves.today')" />
    <PageHeader :title="t('valves.today.title')" :description="t('valves.today.subtitle')" />
    <div class="grid grid-4" style="margin-bottom: var(--s-4)">
        <Link href="/valves/maintenance?status=pending" class="panel kpi"><span class="kpi-label">{{ t('valves.today.pending') }}</span><span class="kpi-value" :style="stats.pending ? 'color: var(--warning)' : ''">{{ num(stats.pending) }}</span><span class="kpi-hint">{{ t('valves.today.pending_hint') }}</span></Link>
        <Link href="/valves/maintenance?status=in_progress" class="panel kpi"><span class="kpi-label">{{ t('valves.today.in_progress') }}</span><span class="kpi-value">{{ num(stats.in_progress) }}</span></Link>
        <Link href="/valves/actions" class="panel kpi"><span class="kpi-label">{{ t('valves.today.actions') }}</span><span class="kpi-value">{{ num(stats.actions_today) }}</span><span class="kpi-hint">{{ t('valves.today.actions_hint') }}</span></Link>
        <div class="panel kpi"><span class="kpi-label">{{ t('valves.today.network') }}</span><span class="kpi-value">{{ num(stats.valves) }}</span><span class="kpi-hint">EME.WATER_VALVES</span></div>
    </div>
    <div class="grid" style="grid-template-columns: minmax(0, 1fr) minmax(0, 1.4fr); align-items: start">
        <section class="panel panel-body stack" style="gap: var(--s-3)">
            <h2 class="section-title">{{ t('valves.today.latest_state') }}</h2>
            <div v-for="row in distribution" :key="row.action" class="bar-row">
                <span>{{ t(`status.action.${row.action}`) }}</span>
                <span class="bar-track"><span class="bar-fill" :style="{ width: total ? `${(row.valves / total) * 100}%` : '0', background: tones[row.action] }" /></span>
                <span class="num" style="text-align: end">{{ num(row.valves) }}</span>
            </div>
            <p class="hint">{{ t('valves.today.latest_state_hint', { n: num(total) }) }}</p>
        </section>
        <section class="panel">
            <div class="panel-head"><h2 class="section-title">{{ t('valves.today.open_reports') }}</h2><span class="spacer" /><Link href="/valves/maintenance" class="btn btn-ghost btn-sm">{{ t('common.view_all') }}</Link></div>
            <table v-if="openReports.length" class="table">
                <tbody>
                    <tr v-for="r in openReports" :key="r.oid">
                        <td><div class="cell-main mono">{{ r.valve.code }}</div><div class="cell-sub">{{ r.valve.location }}</div></td>
                        <td>{{ r.description }}</td>
                        <td><StatusBadge kind="priority" :value="r.priority" /></td>
                        <td class="num subtle">{{ relative(r.created_at) }}</td>
                    </tr>
                </tbody>
            </table>
            <EmptyState v-else icon="circle-check" :title="t('valves.maintenance.empty_pending')" />
        </section>
    </div>
</template>

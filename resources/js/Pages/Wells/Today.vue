<script setup>
import { onBeforeUnmount, onMounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Icon from '../../Components/Icon.vue';
import PageHeader from '../../Components/PageHeader.vue';
import EmptyState from '../../Components/EmptyState.vue';
import { useI18n } from '../../lib/i18n.js';

const props = defineProps({ stats: Object, missing: Array, recent: Array, refreshSeconds: Number });
const { t, num, date, relative } = useI18n();
let timer;
onMounted(() => { timer = setInterval(() => router.reload({ only: ['stats', 'missing', 'recent'] }), props.refreshSeconds * 1000); });
onBeforeUnmount(() => clearInterval(timer));
const coverage = () => (props.stats.active_wells ? Math.round((props.stats.readings_today / props.stats.active_wells) * 100) : 0);
</script>

<template>
    <Head :title="t('nav.wells.today')" />
    <PageHeader :title="t('wells.today.title')" :description="t('wells.today.subtitle')" />
    <div class="grid grid-4" style="margin-bottom: var(--s-4)">
        <div class="panel kpi"><span class="kpi-label">{{ t('wells.today.readings') }}</span><span class="kpi-value">{{ num(stats.readings_today) }}</span><span class="kpi-hint">{{ t('wells.today.coverage', { p: coverage(), n: num(stats.active_wells) }) }}</span></div>
        <div class="panel kpi"><span class="kpi-label">{{ t('wells.today.missing') }}</span><span class="kpi-value" :style="stats.missing_today ? 'color: var(--warning)' : ''">{{ num(stats.missing_today) }}</span><span class="kpi-hint">{{ t('wells.today.missing_hint') }}</span></div>
        <Link href="/wells/signals?state=unread" class="panel kpi"><span class="kpi-label">{{ t('wells.today.unread') }}</span><span class="kpi-value">{{ num(stats.unread_signals) }}</span><span class="kpi-hint">{{ t('wells.today.unread_hint') }}</span></Link>
        <div class="panel kpi"><span class="kpi-label">{{ t('wells.today.active') }}</span><span class="kpi-value">{{ num(stats.active_wells) }}</span><span class="kpi-hint">{{ t('wells.today.active_hint') }}</span></div>
    </div>
    <div class="grid grid-2" style="align-items: start">
        <section class="panel">
            <div class="panel-head"><h2 class="section-title">{{ t('wells.today.missing_list') }}</h2><span class="count">{{ num(missing.length) }}</span></div>
            <table v-if="missing.length" class="table">
                <tbody>
                    <tr v-for="well in missing" :key="well.oid">
                        <td><div class="cell-main">{{ well.name }}</div><div class="cell-sub mono">{{ well.number }}</div></td>
                        <td class="num subtle">{{ well.last_reading_at ? t('wells.today.last', { when: relative(well.last_reading_at) }) : t('wells.today.never') }}</td>
                    </tr>
                </tbody>
            </table>
            <EmptyState v-else icon="circle-check" :title="t('wells.today.all_in_title')" :body="t('wells.today.all_in_body')" />
        </section>
        <section class="panel">
            <div class="panel-head"><h2 class="section-title">{{ t('wells.today.recent') }}</h2><span class="spacer" /><Link href="/wells/readings" class="btn btn-ghost btn-sm">{{ t('common.view_all') }}</Link></div>
            <table v-if="recent.length" class="table">
                <tbody>
                    <tr v-for="r in recent" :key="r.oid">
                        <td><div class="cell-main">{{ r.well }}</div><div class="cell-sub">{{ r.inspector }}</div></td>
                        <td class="num"><div>{{ num(r.generator_hours, 1) }} <span class="subtle">{{ t('wells.hours') }}</span></div><div class="cell-sub">{{ t('wells.readings.generator') }}</div></td>
                        <td class="num"><div>{{ date(r.reading_date, 'date') }}</div><div class="cell-sub">{{ t('wells.readings.submitted', { when: relative(r.created_at) }) }}</div></td>
                    </tr>
                </tbody>
            </table>
            <EmptyState v-else icon="activity" :title="t('wells.readings.empty')" />
        </section>
    </div>
</template>

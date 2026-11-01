<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import Icon from '../Components/Icon.vue';
import PageHeader from '../Components/PageHeader.vue';
import EmptyState from '../Components/EmptyState.vue';
import { useI18n } from '../lib/i18n.js';

const props = defineProps({ applications: Array, operations: Object, generatedAt: String });
const page = usePage();
const { t, num, relative } = useI18n();

const rank = { danger: 0, warning: 1, info: 2, neutral: 3 };
const attention = computed(() => props.applications
    .flatMap((app) => app.attention.map((item) => ({ ...item, app })))
    .sort((a, b) => rank[a.severity] - rank[b.severity]));
const urgent = computed(() => attention.value.filter((item) => ['danger', 'warning'].includes(item.severity)).reduce((sum, item) => sum + item.count, 0));
</script>

<template>
    <Head :title="t('nav.overview')" />
    <PageHeader :title="t('overview.title', { name: page.props.auth?.name ?? '' })" :description="t('overview.subtitle')">
        <template #actions><span class="subtle" style="font-size: var(--fs-xs)">{{ t('overview.updated', { when: relative(generatedAt) }) }}</span></template>
    </PageHeader>

    <div v-if="operations.shutdown_enabled" class="notice danger" style="margin-bottom: var(--s-4)">
        <Icon name="power" />
        <div>
            <strong>{{ t('overview.suspended') }}</strong>
            <div class="muted">{{ operations.reason }}</div>
        </div>
        <span class="spacer" />
        <Link v-if="page.props.shell.urls.operations" :href="page.props.shell.urls.operations" class="btn btn-sm">{{ t('overview.manage_operations') }}</Link>
    </div>

    <div class="grid" style="grid-template-columns: minmax(0, 1.6fr) minmax(0, 1fr); align-items: start">
        <section class="panel" :aria-label="t('overview.attention')">
            <div class="panel-head">
                <h2 class="section-title">{{ t('overview.attention') }}</h2>
                <span v-if="urgent" class="count accent">{{ num(urgent) }}</span>
                <span class="spacer" />
                <span class="subtle" style="font-size: var(--fs-xs)">{{ t('overview.attention_hint') }}</span>
            </div>
            <ul v-if="attention.length" class="attention">
                <li v-for="item in attention" :key="item.app.key + item.key">
                    <Link :href="item.url" :data-app="item.app.key">
                        <span class="sev" :class="item.severity" />
                        <span class="app-mark" style="width: 24px; height: 24px"><Icon :name="item.app.icon" :size="14" /></span>
                        <span style="min-width: 0">
                            <span class="cell-main">{{ t(`attention.${item.key}.label`) }}</span>
                            <span class="cell-sub" style="display: block">{{ item.app.domain }} · {{ t(`attention.${item.key}.hint`) }}</span>
                        </span>
                        <span class="spacer" />
                        <span class="big">{{ num(item.count) }}</span>
                        <Icon name="chevron-right" class="flip subtle" />
                    </Link>
                </li>
            </ul>
            <EmptyState v-else icon="circle-check" :title="t('overview.clear_title')" :body="t('overview.clear_body')" />
        </section>

        <section class="stack" :aria-label="t('overview.pulse')">
            <article v-for="app in applications" :key="app.key" class="panel" :data-app="app.key">
                <div class="panel-head">
                    <span class="app-mark"><Icon :name="app.icon" /></span>
                    <div style="min-width: 0">
                        <div class="cell-main">{{ app.domain }}</div>
                        <div class="cell-sub">{{ app.name }}</div>
                    </div>
                    <span class="spacer" />
                    <Link :href="app.home" class="btn btn-ghost btn-sm">{{ t('common.open') }}<Icon name="chevron-right" :size="14" class="flip" /></Link>
                </div>
                <div v-if="!app.available" class="panel-body"><div class="notice warning"><Icon name="alert-triangle" />{{ t('overview.unavailable') }}</div></div>
                <div v-else class="grid" :class="app.pulse.length > 1 ? 'grid-3' : ''" style="gap: 0">
                    <Link v-for="metric in app.pulse" :key="metric.key" :href="metric.url" class="kpi">
                        <span class="kpi-label">{{ t(`pulse.${metric.key}.label`) }}</span>
                        <span class="kpi-value">{{ num(metric.value, 1) }}</span>
                        <span class="kpi-hint">{{ t(`pulse.${metric.key}.hint`, metric.hint ?? {}) }}</span>
                    </Link>
                </div>
            </article>
            <EmptyState v-if="!applications.length" icon="lock" :title="t('overview.no_apps_title')" :body="t('overview.no_apps_body')" />
        </section>
    </div>
</template>

<style scoped>
@media (max-width: 1100px) { .grid[style*='1.6fr'] { grid-template-columns: minmax(0, 1fr) !important; } }
@media (max-width: 1280px) { .grid-3 { grid-template-columns: minmax(0, 1fr); } }
</style>

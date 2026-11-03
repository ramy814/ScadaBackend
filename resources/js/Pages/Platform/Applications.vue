<script setup>
import { Head, Link } from '@inertiajs/vue3';
import Icon from '../../Components/Icon.vue';
import PageHeader from '../../Components/PageHeader.vue';
import { useI18n } from '../../lib/i18n.js';

defineProps({ applications: Array });
const { t } = useI18n();
</script>

<template>
    <Head :title="t('nav.platform.applications')" />
    <PageHeader :title="t('applications.title')" :description="t('applications.subtitle')" />
    <div class="grid grid-3">
        <article v-for="app in applications" :key="app.key" class="panel" :data-app="app.key">
            <div class="panel-head">
                <span class="app-mark"><Icon :name="app.icon" /></span>
                <div><div class="cell-main">{{ app.name }}</div><div class="cell-sub">{{ app.domain }}</div></div>
            </div>
            <div class="panel-body stack" style="gap: var(--s-3)">
                <dl class="dl">
                    <dt>{{ t('applications.audience') }}</dt><dd>{{ app.audience }}</dd>
                    <dt>{{ t('applications.platforms') }}</dt><dd>{{ app.platforms.join(' · ') }}</dd>
                    <dt>{{ t('applications.auth') }}</dt><dd>{{ app.auth }}</dd>
                    <dt>{{ t('applications.api') }}</dt><dd><bdi class="mono">{{ app.api_prefix }}/*</bdi></dd>
                    <dt>{{ t('applications.offline') }}</dt><dd>{{ t(app.offline_first ? 'applications.offline_yes' : 'common.no') }}</dd>
                    <dt>{{ t('applications.release') }}</dt>
                    <dd><bdi v-if="app.release" class="tag num">v{{ app.release.version }} · build {{ app.release.build }}</bdi><span v-else class="subtle">{{ t('applications.no_release') }}</span></dd>
                </dl>
            </div>
            <div class="panel-foot row">
                <Link v-if="app.accessible" :href="app.home" class="btn btn-sm">{{ t('applications.open_workspace') }}<Icon name="chevron-right" :size="14" class="flip" /></Link>
                <span v-else class="hint"><Icon name="lock" :size="12" /> {{ t('applications.no_access') }}</span>
            </div>
        </article>
    </div>
    <div class="notice" style="margin-top: var(--s-4)"><Icon name="info" /><span>{{ t('applications.contract_hint') }}</span></div>
</template>

<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import Icon from '../../Components/Icon.vue';
import { useI18n } from '../../lib/i18n.js';

defineProps({ reason: String, application: String });
const page = usePage();
const { t } = useI18n();
</script>

<template>
    <Head :title="t('errors.no_access')" />
    <main class="center-page">
        <div class="center-card">
            <div class="empty-icon" style="width: 48px; height: 48px"><Icon name="lock" :size="22" /></div>
            <h1 style="font-size: var(--fs-xl)">{{ t('errors.no_access') }}</h1>
            <p class="muted">{{ t(`errors.reasons.${reason}`, { app: application ?? '' }) }}</p>
            <div class="row">
                <Link v-if="page.props.shell && reason !== 'no_application'" :href="page.props.shell.urls.overview" class="btn btn-primary">{{ t('errors.back_overview') }}</Link>
                <Link v-if="page.props.auth" href="/logout" method="post" as="button" class="btn">{{ t('common.sign_out') }}</Link>
            </div>
        </div>
    </main>
</template>

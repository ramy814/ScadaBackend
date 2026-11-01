<script setup>
import { Head } from '@inertiajs/vue3';
import Icon from '../../Components/Icon.vue';
import { useI18n } from '../../lib/i18n.js';

defineProps({ ssoError: Boolean, ssoAvailable: Boolean, devLoginUrl: String });
const { t } = useI18n();
</script>

<template>
    <Head :title="t('auth.title')" />
    <main class="center-page" data-app="platform">
        <div class="center-card panel panel-body" style="padding: var(--s-8)">
            <span class="rail-logo" style="width: 44px; height: 44px; margin: 0">MOG</span>
            <h1 style="font-size: var(--fs-xl)">{{ t('app.name') }}</h1>
            <p class="muted">{{ t('auth.subtitle') }}</p>
            <div v-if="ssoError" class="notice danger" style="text-align: start"><Icon name="alert-triangle" />{{ t('auth.sso_error') }}</div>
            <a v-if="ssoAvailable" href="/login" class="btn btn-primary" style="width: 100%">{{ t('auth.sso') }}</a>
            <div v-else class="notice warning" style="text-align: start"><Icon name="info" />{{ t('auth.sso_missing') }}</div>
            <a v-if="devLoginUrl" :href="devLoginUrl" class="btn" style="width: 100%">{{ t('auth.dev') }}</a>
        </div>
    </main>
</template>

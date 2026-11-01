<script setup>
import { Head, Link } from '@inertiajs/vue3';
import Icon from '../../Components/Icon.vue';
import { useI18n } from '../../lib/i18n.js';

defineProps({ reason: String, changedAt: String, canReopen: Boolean });
const { t, date } = useI18n();
</script>

<template>
    <Head :title="t('errors.suspended')" />
    <main class="center-page">
        <div class="center-card">
            <div class="empty-icon" style="width: 48px; height: 48px; background: var(--danger-bg); color: var(--danger)"><Icon name="power" :size="22" /></div>
            <h1 style="font-size: var(--fs-xl)">{{ t('errors.suspended') }}</h1>
            <p class="muted">{{ reason || t('errors.suspended_default') }}</p>
            <p v-if="changedAt" class="hint num">{{ date(changedAt) }}</p>
            <Link v-if="canReopen" href="/platform/operations" class="btn btn-primary">{{ t('errors.manage_operations') }}</Link>
        </div>
    </main>
</template>

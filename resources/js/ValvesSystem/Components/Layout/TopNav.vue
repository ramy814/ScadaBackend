<script setup>
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineEmits(['toggle-sidebar']);

const page = usePage();
const userName = computed(() => page.props.auth?.user?.name ?? '');
</script>

<template>
    <header class="v-topnav">
        <div style="display: flex; align-items: center; gap: 12px;">
            <h1 class="v-topnav-title">نظام متابعة المحابس البلدية</h1>
        </div>

        <div class="v-topnav-actions">
            <span style="font-size: 0.8125rem; color: var(--color-text-muted);">
                {{ new Date().toLocaleDateString('ar-PS', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) }}
            </span>
            <a href="/auth/sso/logout" style="font-size: 0.8125rem; color: var(--color-text-muted); text-decoration: none; padding: 6px 12px; border: 1px solid var(--color-border); border-radius: 8px; transition: color 0.15s;">
                تسجيل خروج
            </a>
        </div>
    </header>
</template>

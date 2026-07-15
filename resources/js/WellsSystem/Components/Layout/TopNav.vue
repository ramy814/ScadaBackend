<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Icon from '@/WellsSystem/Components/Shared/Icon.vue';

defineEmits(['toggle-sidebar']);

const page = usePage();
const unreadCount = computed(() => page.props.unread_notifications_count || 0);
</script>

<template>
    <header class="topnav">
        <div class="flex items-center gap-3">
            <button class="btn btn-icon btn-secondary" @click="$emit('toggle-sidebar')" style="display: none;">
                ☰
            </button>
            <h1 class="topnav-title">نظام ادارة آبار بلدية غزة</h1>
        </div>

        <div class="topnav-actions">
            <span class="text-muted" style="font-size: 0.875rem;">
                {{ new Date().toLocaleDateString('ar-PS') }}
            </span>

            <!-- زر الإشعارات مع badge -->
            <button
                class="btn btn-icon btn-secondary"
                style="position: relative; margin-right: 8px;"
                title="الإشعارات"
                @click="router.visit('/wells/notifications')"
            >
                <Icon name="bell" size="20" />
                <span
                    v-if="unreadCount > 0"
                    style="
                        position: absolute;
                        top: -4px;
                        left: -4px;
                        background: var(--color-danger);
                        color: white;
                        font-size: 0.65rem;
                        font-weight: 700;
                        min-width: 18px;
                        height: 18px;
                        border-radius: 50%;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        padding: 0 3px;
                        line-height: 1;
                        animation: pulse 2s infinite;
                    "
                >
                    {{ unreadCount > 99 ? '99+' : unreadCount }}
                </span>
            </button>
        </div>
    </header>
</template>

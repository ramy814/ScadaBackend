<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Icon from '@/WellsSystem/Components/Shared/Icon.vue';
import { router } from '@inertiajs/vue3';

defineProps({
    open: Boolean,
});

defineEmits(['close']);

const page = usePage();
const currentUrl = computed(() => page.url);
const unreadCount = computed(() => page.props.unread_notifications_count || 0);

import { ref, onMounted } from 'vue';

const isDark = ref(false);

onMounted(() => {
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme) {
        isDark.value = savedTheme === 'dark';
        document.documentElement.setAttribute('data-theme', savedTheme);
    } else {
        isDark.value = window.matchMedia('(prefers-color-scheme: dark)').matches;
    }
});

function toggleTheme() {
    isDark.value = !isDark.value;
    const theme = isDark.value ? 'dark' : 'light';
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('theme', theme);
}

const isActive = (path) => {
    if (path === '/') return currentUrl.value === '/';
    return currentUrl.value.startsWith(path);
};

const navItems = [
    { label: 'البوابة', icon: 'home', href: '/' },
    { label: 'لوحة التحكم', icon: 'dashboard', href: '/wells' },
    { label: 'الآبار', icon: 'water-drop', href: '/wells/wells' },
    { label: 'خريطة الآبار', icon: 'map', href: '/wells/wells-map' },
    { label: 'الفرق', icon: 'users', href: '/wells/teams' },
    { label: 'المفتشين', icon: 'search', href: '/wells/inspectors' },
    { label: 'القراءات', icon: 'clipboard', href: '/wells/readings' },
    { label: 'جرد السولار', icon: 'fuel', href: '/wells/diesel-inventories' },
];

const notificationNavItems = [
    { label: 'أنواع الإشعارات', icon: 'notification', href: '/wells/notification-types' },
];
</script>

<template>
    <aside class="sidebar" :class="{ open }">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <Icon name="water-drop" size="24" style="color: var(--color-text-primary)" />
            </div>
            <div>
                <div class="sidebar-title">إدارة الآبار</div>
                <div class="sidebar-subtitle">بلدية غزة</div>
            </div>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section-title">القائمة الرئيسية</div>

            <template v-for="item in navItems" :key="item.href">
                <a
                    v-if="item.href === '/'"
                    :href="item.href"
                    class="nav-item"
                    :class="{ active: isActive(item.href) }"
                    @click="$emit('close')"
                >
                    <span class="nav-item-icon">
                        <Icon :name="item.icon" size="20" />
                    </span>
                    <span class="nav-item-label">{{ item.label }}</span>
                </a>
                <Link
                    v-else
                    :href="item.href"
                    class="nav-item"
                    :class="{ active: isActive(item.href) }"
                    @click="$emit('close')"
                >
                    <span class="nav-item-icon">
                        <Icon :name="item.icon" size="20" />
                    </span>
                    <span class="nav-item-label">{{ item.label }}</span>
                </Link>
            </template>

            <div class="nav-section-title" style="margin-top: 16px;">الإشعارات</div>

            <!-- الإشعارات مع badge -->
            <Link
                href="/wells/notifications"
                class="nav-item"
                :class="{ active: isActive('/wells/notifications') }"
                @click="$emit('close')"
            >
                <span class="nav-item-icon" style="position: relative;">
                    <Icon name="bell" size="20" />
                    <span
                        v-if="unreadCount > 0"
                        style="
                            position: absolute;
                            top: -6px;
                            left: -6px;
                            background: var(--color-danger);
                            color: var(--color-text-primary);
                            font-size: 0.6rem;
                            font-weight: 700;
                            min-width: 16px;
                            height: 16px;
                            border-radius: 50%;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                        "
                    >{{ unreadCount > 99 ? '99+' : unreadCount }}</span>
                </span>
                <span class="nav-item-label">الإشعارات</span>
            </Link>

            <Link
                v-for="item in notificationNavItems"
                :key="item.href"
                :href="item.href"
                class="nav-item"
                :class="{ active: isActive(item.href) }"
                @click="$emit('close')"
            >
                <span class="nav-item-icon">
                    <Icon :name="item.icon" size="20" />
                </span>
                <span class="nav-item-label">{{ item.label }}</span>
            </Link>

            <div style="margin-top: auto; padding-top: 16px;">
                <button @click="toggleTheme" class="nav-item" style="width: 100%; border: none; background: transparent; cursor: pointer;" :title="isDark ? 'التبديل للوضع النهاري' : 'التبديل للوضع الليلي'">
                    <span class="nav-item-icon">
                        <svg v-if="isDark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5" style="width: 20px; height: 20px;"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="4.22" x2="19.78" y2="5.64"/></svg>
                        <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-5 h-5" style="width: 20px; height: 20px;"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                    </span>
                    <span class="nav-item-label">{{ isDark ? 'الوضع النهاري' : 'الوضع الليلي' }}</span>
                </button>
            </div>
        </nav>
    </aside>
</template>

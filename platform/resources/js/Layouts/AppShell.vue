<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import Icon from '../Components/Icon.vue';
import CommandPalette from '../Components/CommandPalette.vue';
import Toasts from '../Components/Toasts.vue';
import { useI18n } from '../lib/i18n.js';
import { crumb } from '../lib/shell.js';

const page = usePage();
const { t } = useI18n();
const shell = computed(() => page.props.shell);
const workspace = computed(() => shell.value?.workspace ?? 'overview');
const apps = computed(() => shell.value?.applications ?? []);
const currentApp = computed(() => apps.value.find((app) => app.key === workspace.value));
const navigation = computed(() => shell.value?.navigation ?? []);
const path = computed(() => new URL(page.url, window.location.origin).pathname);

// Longest route prefix wins, so /fleet/trips/12 keeps "Reviews" active.
const activeItem = computed(() => {
    let best = null;
    for (const group of navigation.value) for (const item of group.items) {
        const itemPath = new URL(item.route).pathname;
        if ((path.value === itemPath || path.value.startsWith(itemPath + '/') || (itemPath !== '/' && path.value.startsWith(itemPath)))
            && (!best || itemPath.length > new URL(best.route).pathname.length)) best = item;
    }
    if (!best && path.value.startsWith('/fleet/trips')) best = navigation.value.flatMap((g) => g.items).find((i) => i.key === 'reviews');
    return best;
});

const workspaceLabel = computed(() => currentApp.value?.domain
    ?? (workspace.value === 'platform' ? t('nav.platform.title') : t('nav.overview')));
const workspaceHome = computed(() => currentApp.value?.home ?? (workspace.value === 'platform' ? shell.value?.urls.platform : shell.value?.urls.overview));

// Return to the last page visited inside each application (context preserved on switch).
const open = ref(false);
const lastVisited = ref({});
const appHref = (app) => lastVisited.value[app.key] ?? app.home;
watch(() => page.url, () => {
    if (currentApp.value) {
        try { sessionStorage.setItem(`last:${workspace.value}`, page.url); } catch {}
        lastVisited.value = { ...lastVisited.value, [workspace.value]: page.url };
    }
    open.value = false;
}, { immediate: true });
onMounted(() => {
    try {
        lastVisited.value = Object.fromEntries(apps.value.map((a) => [a.key, sessionStorage.getItem(`last:${a.key}`)]).filter(([, v]) => v));
    } catch {}
});

const collapsed = ref({});
const palette = ref(false);
const onKey = (event) => {
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') { event.preventDefault(); palette.value = !palette.value; }
};
onMounted(() => window.addEventListener('keydown', onKey));
onBeforeUnmount(() => window.removeEventListener('keydown', onKey));

const paletteEntries = computed(() => [
    { group: t('palette.workspaces'), label: t('nav.overview'), icon: 'layout-grid', url: shell.value.urls.overview },
    ...apps.value.map((app) => ({ group: t('palette.workspaces'), label: `${app.domain} — ${app.name}`, icon: app.icon, url: app.home })),
    { group: t('palette.workspaces'), label: t('nav.platform.title'), icon: 'settings', url: shell.value.urls.platform },
    ...navigation.value.flatMap((group) => group.items.map((item) => ({ group: workspaceLabel.value, label: t(item.label), icon: item.icon, url: item.route }))),
]);

const theme = ref(document.documentElement.dataset.theme ?? 'system');
function cycleTheme() {
    const next = { system: 'light', light: 'dark', dark: 'system' }[theme.value];
    theme.value = next;
    try { next === 'system' ? localStorage.removeItem('theme') : localStorage.setItem('theme', next); } catch {}
    if (next === 'system') delete document.documentElement.dataset.theme; else document.documentElement.dataset.theme = next;
}
const themeIcon = computed(() => ({ system: 'sun', light: 'sun', dark: 'moon' }[theme.value]));

function switchLocale() {
    router.post(shell.value.urls.locale, { locale: page.props.locale === 'ar' ? 'en' : 'ar' }, {
        preserveScroll: true,
        onSuccess: () => window.location.reload(), // direction and fonts change: full reload is simplest and correct
    });
}
</script>

<template>
    <div class="shell" :class="{ 'no-sidebar': !navigation.length, open }" :data-app="workspace">
        <div v-if="open" class="scrim" @click="open = false" />

        <nav class="rail" :aria-label="t('nav.applications')">
            <Link :href="shell.urls.overview" class="rail-logo" :aria-label="t('app.name')">MOG</Link>
            <Link :href="shell.urls.overview" class="rail-btn" style="--rail-accent: var(--app-platform)" :aria-current="workspace === 'overview'">
                <Icon name="layout-grid" :size="18" /><span class="rail-tip">{{ t('nav.overview') }}</span>
            </Link>
            <div class="rail-sep" />
            <Link v-for="app in apps" :key="app.key" :href="appHref(app)" class="rail-btn" :style="`--rail-accent: var(--app-${app.accent})`" :aria-current="workspace === app.key">
                <Icon :name="app.icon" :size="18" /><span class="rail-tip">{{ app.domain }} · {{ app.name }}</span>
            </Link>
            <div class="rail-sep" />
            <Link :href="shell.urls.platform" class="rail-btn" style="--rail-accent: var(--app-platform)" :aria-current="workspace === 'platform'">
                <Icon name="settings" :size="18" /><span class="rail-tip">{{ t('nav.platform.title') }}</span>
            </Link>
            <span class="spacer" />
            <button class="rail-btn" type="button" @click="cycleTheme"><Icon :name="themeIcon" :size="18" /><span class="rail-tip">{{ t(`theme.${theme}`) }}</span></button>
            <button class="rail-btn" type="button" @click="switchLocale"><Icon name="languages" :size="18" /><span class="rail-tip">{{ t('common.switch_language') }}</span></button>
            <Link :href="shell.urls.logout" method="post" as="button" class="rail-btn"><Icon name="log-out" :size="18" class="flip" /><span class="rail-tip">{{ t('common.sign_out') }}</span></Link>
        </nav>

        <aside v-if="navigation.length" class="sidebar">
            <div class="sidebar-head">
                <span class="app-mark"><Icon :name="currentApp?.icon ?? 'settings'" /></span>
                <div>
                    <div class="sidebar-title">{{ workspaceLabel }}</div>
                    <div class="sidebar-sub">{{ currentApp?.name ?? t('nav.platform.subtitle') }}</div>
                </div>
            </div>
            <nav :aria-label="workspaceLabel">
                <div v-for="group in navigation" :key="group.label" class="nav-group">
                    <button class="nav-group-label" type="button" :aria-expanded="!collapsed[group.label]" @click="collapsed[group.label] = !collapsed[group.label]">
                        {{ t(group.label) }}<Icon :name="collapsed[group.label] ? 'chevron-right' : 'chevron-down'" :size="12" class="flip" />
                    </button>
                    <template v-if="!collapsed[group.label]">
                        <Link v-for="item in group.items" :key="item.key" :href="item.route" class="nav-item" :aria-current="activeItem?.key === item.key ? 'page' : undefined">
                            <Icon :name="item.icon" />
                            <span>{{ t(item.label) }}</span>
                            <span v-if="item.badge" class="count" :class="{ accent: activeItem?.key !== item.key }">{{ item.badge }}</span>
                        </Link>
                    </template>
                </div>
            </nav>
        </aside>

        <div class="main">
            <header class="topbar">
                <button class="btn btn-ghost btn-icon menu-btn" type="button" :aria-label="t('common.menu')" @click="open = true"><Icon name="menu" /></button>
                <nav class="crumbs" :aria-label="t('common.breadcrumb')">
                    <Link :href="workspaceHome">{{ workspaceLabel }}</Link>
                    <template v-if="activeItem">
                        <Icon name="chevron-right" :size="14" class="flip" />
                        <Link v-if="crumb" :href="activeItem.route">{{ t(activeItem.label) }}</Link>
                        <span v-else class="current">{{ t(activeItem.label) }}</span>
                    </template>
                    <template v-if="crumb">
                        <Icon name="chevron-right" :size="14" class="flip" />
                        <span class="current">{{ crumb }}</span>
                    </template>
                </nav>
                <span class="spacer" />
                <button class="search-trigger" type="button" @click="palette = true">
                    <Icon name="search" :size="15" /><span class="label">{{ t('palette.trigger') }}</span><span class="spacer" /><kbd>Ctrl K</kbd>
                </button>
                <span class="row muted" style="font-size: var(--fs-sm)"><Icon name="user" :size="15" /><span class="hide-sm">{{ page.props.auth?.name }}</span></span>
            </header>
            <main class="content"><slot /></main>
        </div>

        <CommandPalette v-if="palette" :entries="paletteEntries" :trip-lookup="shell.urls.tripLookup" @close="palette = false" />
        <Toasts />
    </div>
</template>

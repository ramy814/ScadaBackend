<script setup>
import { computed, ref, watch } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import Icon from '../../Components/Icon.vue';
import PageHeader from '../../Components/PageHeader.vue';
import EmptyState from '../../Components/EmptyState.vue';
import Drawer from '../../Components/Drawer.vue';
import { useI18n } from '../../lib/i18n.js';
import { debounce } from '../../lib/query.js';

const props = defineProps({ users: Array, catalog: Array, requirements: Object, searchUrl: String, updateBase: String });
const { t } = useI18n();

const filter = ref('');
const listed = computed(() => props.users.filter((u) => !filter.value || `${u.name} ${u.user_id}`.includes(filter.value)));

// SSO directory search (active users only, enforced server-side).
const query = ref('');
const results = ref([]);
const searching = ref(false);
const search = debounce(async (value) => {
    if (value.trim().length < 2) { results.value = []; return; }
    searching.value = true;
    try {
        const response = await fetch(`${props.searchUrl}?q=${encodeURIComponent(value)}`, { headers: { Accept: 'application/json' } });
        results.value = response.ok ? (await response.json()).users : [];
    } finally { searching.value = false; }
}, 300);
watch(query, search);

const editing = ref(null);
const form = useForm({ keys: [] });
function edit(user) {
    editing.value = user;
    form.clearErrors();
    form.keys = [...(user.systems ?? [])];
}
function toggle(key, checked) {
    form.keys = checked ? [...new Set([...form.keys, key])] : form.keys.filter((k) => k !== key && props.requirements[k] !== key);
}
const disabled = (key) => props.requirements[key] && !form.keys.includes(props.requirements[key]);
function save() {
    form.put(`${props.updateBase}/${editing.value.user_id ?? editing.value.id}`, { preserveScroll: true, onSuccess: () => { editing.value = null; query.value = ''; } });
}
</script>

<template>
    <Head :title="t('nav.platform.access')" />
    <PageHeader :title="t('access.title')" :description="t('access.subtitle')" />

    <div class="grid" style="grid-template-columns: minmax(0, 1.7fr) minmax(0, 1fr); align-items: start">
        <section class="panel">
            <div class="toolbar">
                <label class="search-box"><Icon name="search" :size="15" /><input v-model="filter" class="input" type="search" :placeholder="t('access.filter')" /></label>
                <span class="spacer" /><span class="subtle" style="font-size: var(--fs-xs)">{{ t('access.count', { n: users.length }) }}</span>
            </div>
            <table v-if="listed.length" class="table stackable">
                <thead><tr><th>{{ t('access.user') }}</th><th>{{ t('access.applications') }}</th><th>{{ t('access.fleet_scope') }}</th><th /></tr></thead>
                <tbody>
                    <tr v-for="user in listed" :key="user.user_id" class="clickable" @click="edit(user)">
                        <td><div class="cell-main">{{ user.name ?? '—' }}</div><div class="cell-sub num">SSO #{{ user.user_id }}</div></td>
                        <td><span v-for="key in user.systems" :key="key" class="tag" style="margin-inline-end: 4px">{{ t(`access.keys.${key}`) }}</span></td>
                        <td>
                            <span v-if="user.fleet_access?.can_enter" class="tag">{{ t('access.scope_entry') }}</span>
                            <span v-if="user.fleet_access?.can_review" class="tag" style="margin-inline-start: 4px">{{ t('access.scope_review') }}</span>
                            <span v-if="!user.fleet_access?.can_enter && !user.fleet_access?.can_review" class="subtle">—</span>
                        </td>
                        <td class="num"><Icon name="chevron-right" class="flip subtle" /></td>
                    </tr>
                </tbody>
            </table>
            <EmptyState v-else icon="users" :title="t('access.empty')" />
        </section>

        <section class="panel">
            <div class="panel-head"><h2 class="section-title"><Icon name="plus" />{{ t('access.grant_title') }}</h2></div>
            <div class="panel-body stack" style="gap: var(--s-3)">
                <label class="search-box" style="max-width: none"><Icon name="search" :size="15" /><input v-model="query" class="input" type="search" :placeholder="t('access.search')" /></label>
                <p class="hint">{{ t('access.search_hint') }}</p>
                <div v-if="searching" class="skeleton" style="height: 40px" />
                <button v-for="user in results" :key="user.id" type="button" class="nav-item" style="height: auto; padding: var(--s-2); width: 100%; border: 0; background: none; cursor: pointer; text-align: start" @click="edit(user)">
                    <Icon name="user" />
                    <span style="flex: 1"><span class="cell-main" style="display: block">{{ user.full_name }}</span><span class="cell-sub num">SSO #{{ user.id }} · {{ t('access.emp', { n: user.emp_id ?? '—' }) }}</span></span>
                    <span v-if="user.systems.length" class="tag">{{ t('access.has_access') }}</span>
                </button>
            </div>
        </section>
    </div>
    <div class="notice" style="margin-top: var(--s-4)"><Icon name="info" /><span>{{ t('access.fleet_scope_hint') }}</span></div>

    <Drawer v-if="editing" :title="editing.name ?? editing.full_name" @close="editing = null">
        <form id="access-form" class="stack" @submit.prevent="save">
            <p class="muted num" style="font-size: var(--fs-sm)">SSO #{{ editing.user_id ?? editing.id }}</p>
            <fieldset v-for="group in catalog" :key="group.application" class="panel panel-body stack" style="gap: var(--s-2); margin: 0" :data-app="group.application">
                <legend class="section-title" style="padding: 0 var(--s-1)"><span class="app-mark" style="width: 22px; height: 22px" />{{ t(`access.apps.${group.application}`) }}</legend>
                <label v-for="key in group.keys" :key="key" class="check" :style="requirements[key] ? 'padding-inline-start: 24px' : ''">
                    <input type="checkbox" :checked="form.keys.includes(key)" :disabled="disabled(key)" @change="toggle(key, $event.target.checked)" />
                    <span><span>{{ t(`access.keys.${key}`) }}</span><span class="hint" style="display: block">{{ t(`access.key_hints.${key}`) }}</span></span>
                </label>
            </fieldset>
            <span v-if="form.errors.keys" class="error-text">{{ form.errors.keys }}</span>
            <div v-if="editing.fleet_access" class="notice"><Icon name="shield-check" /><span>{{ t('access.derived', { entry: t(editing.fleet_access.can_enter ? 'common.yes' : 'common.no'), review: t(editing.fleet_access.can_review ? 'common.yes' : 'common.no') }) }}</span></div>
        </form>
        <template #footer>
            <div class="row"><button type="submit" form="access-form" class="btn btn-primary" :disabled="form.processing">{{ t('common.save') }}</button><button type="button" class="btn" @click="editing = null">{{ t('common.cancel') }}</button></div>
        </template>
    </Drawer>
</template>

<style scoped>
@media (max-width: 1100px) { .grid[style*='1.7fr'] { grid-template-columns: minmax(0, 1fr) !important; } }
</style>

<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import Icon from '../../Components/Icon.vue';
import PageHeader from '../../Components/PageHeader.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import EmptyState from '../../Components/EmptyState.vue';
import FormDrawer from '../../Components/FormDrawer.vue';
import { useI18n } from '../../lib/i18n.js';

// Icon codes are the allowlist of StoreNotificationTypeRequest (raw emoji are not stored: Oracle charset).
const icons = ['danger', 'warning', 'success', 'info', 'bell', 'maint', 'fuel', 'water', 'power', 'repair'];
defineProps({ types: Object });
const { t, num } = useI18n();
const truthy = (v) => v === true || v === 1 || v === '1';
const editing = ref(null);
const form = useForm({ NAME: '', ICON: 'info', COLOR: '#3e63dd', PRIORITY: 5, IS_ACTIVE: true });
function edit(type = null) {
    editing.value = type ?? {};
    form.clearErrors();
    Object.assign(form, { NAME: type?.NAME ?? '', ICON: type?.ICON ?? 'info', COLOR: type?.COLOR ?? '#3e63dd', PRIORITY: Number(type?.PRIORITY ?? 5), IS_ACTIVE: type ? truthy(type.IS_ACTIVE) : true });
}
function save() {
    form.transform((d) => ({ ...d, EMOJI: d.ICON, IS_ACTIVE: d.IS_ACTIVE ? 1 : 0 }))[editing.value.OID ? 'put' : 'post'](
        `/wells/settings/signal-types${editing.value.OID ? `/${editing.value.OID}` : ''}`, { preserveScroll: true, onSuccess: () => { editing.value = null; } });
}
const destroy = (type) => router.delete(`/wells/settings/signal-types/${type.OID}`, { preserveScroll: true, onSuccess: () => { editing.value = null; } });
</script>

<template>
    <Head :title="t('nav.wells.signal_types')" />
    <PageHeader :title="t('wells.types.title')" :description="t('wells.types.subtitle')">
        <template #actions><button type="button" class="btn btn-primary" @click="edit()"><Icon name="plus" />{{ t('wells.types.add') }}</button></template>
    </PageHeader>
    <section class="panel">
        <table v-if="types.data.length" class="table stackable">
            <thead><tr><th>{{ t('common.name') }}</th><th>{{ t('wells.types.icon') }}</th><th class="num">{{ t('wells.types.priority') }}</th><th>{{ t('common.state') }}</th><th /></tr></thead>
            <tbody>
                <tr v-for="type in types.data" :key="type.OID" class="clickable" @click="edit(type)">
                    <td><span class="row"><span class="sev" :style="{ background: type.COLOR, width: '10px', height: '10px', borderRadius: '50%' }" /><span class="cell-main">{{ type.NAME }}</span></span></td>
                    <td><span class="tag">{{ t(`wells.types.icons.${type.ICON}`) }}</span></td>
                    <td class="num">{{ num(type.PRIORITY) }}</td>
                    <td><StatusBadge kind="active" :value="truthy(type.IS_ACTIVE)" /></td>
                    <td class="num"><span v-if="!type.CAN_DELETE" class="subtle" :title="t('wells.types.in_use')"><Icon name="lock" :size="14" /></span></td>
                </tr>
            </tbody>
        </table>
        <EmptyState v-else icon="list" :title="t('wells.types.empty')" />
    </section>
    <p class="hint" style="margin-top: var(--s-2)">{{ t('wells.types.hint') }}</p>

    <FormDrawer v-if="editing" :title="t(editing.OID ? 'common.edit' : 'wells.types.add')" :busy="form.processing" @close="editing = null" @submit="save">
        <div class="field"><label for="st-name">{{ t('common.name') }}</label><input id="st-name" v-model="form.NAME" class="input" required maxlength="255" /><span v-if="form.errors.NAME" class="error-text">{{ form.errors.NAME }}</span></div>
        <div class="field"><label for="st-icon">{{ t('wells.types.icon') }}</label><select id="st-icon" v-model="form.ICON" class="select"><option v-for="i in icons" :key="i" :value="i">{{ t(`wells.types.icons.${i}`) }}</option></select></div>
        <div class="grid grid-2">
            <div class="field"><label for="st-color">{{ t('wells.types.color') }}</label><input id="st-color" v-model="form.COLOR" type="color" class="input" style="padding: 2px" /></div>
            <div class="field"><label for="st-prio">{{ t('wells.types.priority') }}</label><input id="st-prio" v-model.number="form.PRIORITY" type="number" min="1" max="100" class="input num" /><span class="hint">{{ t('wells.types.priority_hint') }}</span></div>
        </div>
        <label class="check"><input v-model="form.IS_ACTIVE" type="checkbox" />{{ t('wells.types.active_hint') }}</label>
        <template #danger><button v-if="editing.OID && editing.CAN_DELETE" type="button" class="btn btn-ghost" style="color: var(--danger)" @click="destroy(editing)"><Icon name="x" />{{ t('common.delete') }}</button></template>
    </FormDrawer>
</template>

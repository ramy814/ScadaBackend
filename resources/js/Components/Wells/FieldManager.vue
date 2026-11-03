<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import Icon from '../Icon.vue';
import StatusBadge from '../StatusBadge.vue';
import EmptyState from '../EmptyState.vue';
import FormDrawer from '../FormDrawer.vue';
import ConfirmDialog from '../ConfirmDialog.vue';
import { useI18n } from '../../lib/i18n.js';

/**
 * Teams + inspectors CRUD shared by Wells and Valves. Their data stays separate
 * (business rule); each app passes its own endpoints and assignable items.
 */
const props = defineProps({
    teams: Array, inspectors: Array, items: Array,
    base: { type: String, required: true }, itemKey: { type: String, required: true }, itemLabel: { type: Function, required: true },
    unit: String, activity: String, assignAll: Boolean,
});
const { t, num } = useI18n();
const tab = ref('teams');
const truthy = (v) => v === true || v === 1 || v === '1';
const idsOf = (list) => (list ?? []).map((x) => Number(x.OID ?? x.oid ?? x.W4_GENERAL_ID));

const editing = ref(null);
const filter = ref('');
const teamForm = useForm({ NAME: '', IS_ACTIVE: true, ASSIGN_ALL_VALVES: false, [props.itemKey]: [] });
const inspectorForm = useForm({ NAME: '', PHONE: '', PASSWORD: '', IS_ACTIVE: true, team_ids: [] });
const shownItems = computed(() => props.items.filter((item) => !filter.value || props.itemLabel(item).toLowerCase().includes(filter.value.toLowerCase())));

function editTeam(team = null) {
    editing.value = { kind: 'team', record: team };
    teamForm.clearErrors();
    teamForm.NAME = team?.NAME ?? '';
    teamForm.IS_ACTIVE = team ? truthy(team.IS_ACTIVE) : true;
    teamForm.ASSIGN_ALL_VALVES = team ? truthy(team.ASSIGN_ALL_VALVES) : false;
    teamForm[props.itemKey] = idsOf(team?.WELLS ?? team?.VALVES);
}
function editInspector(person = null) {
    editing.value = { kind: 'inspector', record: person };
    inspectorForm.clearErrors();
    inspectorForm.NAME = person?.NAME ?? '';
    inspectorForm.PHONE = person?.PHONE ?? '';
    inspectorForm.PASSWORD = '';
    inspectorForm.IS_ACTIVE = person ? truthy(person.IS_ACTIVE) : true;
    inspectorForm.team_ids = idsOf(person?.TEAMS);
}
const close = () => { editing.value = null; };
function save() {
    const { kind, record } = editing.value;
    const form = kind === 'team' ? teamForm : inspectorForm;
    const path = `${props.base}/${kind === 'team' ? 'teams' : 'inspectors'}${record ? `/${record.OID}` : ''}`;
    form.transform((data) => {
        const out = { ...data, IS_ACTIVE: data.IS_ACTIVE ? 1 : 0 };
        if (kind === 'team' && !props.assignAll) delete out.ASSIGN_ALL_VALVES;
        if (kind === 'team' && props.assignAll) out.ASSIGN_ALL_VALVES = data.ASSIGN_ALL_VALVES ? 1 : 0;
        if (kind === 'inspector' && record && !data.PASSWORD) delete out.PASSWORD;
        return out;
    })[record ? 'put' : 'post'](path, { preserveScroll: true, onSuccess: close });
}

const dialog = ref(null);
const doomed = ref(null);
function askDelete(kind, record) { doomed.value = { kind, record }; dialog.value.open(); }
function destroy() {
    router.delete(`${props.base}/${doomed.value.kind === 'team' ? 'teams' : 'inspectors'}/${doomed.value.record.OID}`, { preserveScroll: true, onFinish: () => dialog.value.close() });
}
</script>

<template>
    <section class="panel">
        <nav class="tabs" style="padding: 0 var(--s-3)">
            <button type="button" :aria-current="tab === 'teams'" @click="tab = 'teams'">{{ t('field.teams') }}<span class="count">{{ num(teams.length) }}</span></button>
            <button type="button" :aria-current="tab === 'inspectors'" @click="tab = 'inspectors'">{{ t('field.inspectors') }}<span class="count">{{ num(inspectors.length) }}</span></button>
            <span class="spacer" />
            <button type="button" class="btn btn-primary btn-sm" style="align-self: center" @click="tab === 'teams' ? editTeam() : editInspector()"><Icon name="plus" :size="14" />{{ t(tab === 'teams' ? 'field.add_team' : 'field.add_inspector') }}</button>
        </nav>
        <template v-if="tab === 'teams'">
            <table v-if="teams.length" class="table stackable">
                <thead><tr><th>{{ t('common.name') }}</th><th class="num">{{ t(`field.${unit}`) }}</th><th class="num">{{ t('field.inspectors') }}</th><th>{{ t('common.state') }}</th><th /></tr></thead>
                <tbody>
                    <tr v-for="team in teams" :key="team.OID" class="clickable" @click="editTeam(team)">
                        <td class="cell-main">{{ team.NAME }}</td>
                        <td class="num"><span v-if="truthy(team.ASSIGN_ALL_VALVES)" class="tag">{{ t('field.all_valves') }}</span><span v-else>{{ num(team.WELLS_COUNT ?? team.VALVES_COUNT ?? idsOf(team.WELLS ?? team.VALVES).length) }}</span></td>
                        <td class="num">{{ num(team.INSPECTORS_COUNT ?? (team.INSPECTORS ?? []).length) }}</td>
                        <td><StatusBadge kind="active" :value="truthy(team.IS_ACTIVE)" /></td>
                        <td class="num" @click.stop>
                            <button v-if="team.CAN_DELETE !== false" type="button" class="btn btn-ghost btn-sm" @click="askDelete('team', team)"><Icon name="x" :size="14" /></button>
                            <span v-else class="btn btn-ghost btn-sm" :title="(team.DELETE_BLOCKERS ?? []).join('، ')" aria-disabled="true"><Icon name="lock" :size="14" /></span>
                        </td>
                    </tr>
                </tbody>
            </table>
            <EmptyState v-else icon="users" :title="t('field.no_teams')" />
        </template>
        <template v-else>
            <table v-if="inspectors.length" class="table stackable">
                <thead><tr><th>{{ t('common.name') }}</th><th>{{ t('field.phone') }}</th><th>{{ t('field.teams') }}</th><th>{{ t('field.app_login') }}</th><th /></tr></thead>
                <tbody>
                    <tr v-for="person in inspectors" :key="person.OID" class="clickable" @click="editInspector(person)">
                        <td class="cell-main">{{ person.NAME }}</td>
                        <td class="num" dir="ltr" style="text-align: start">{{ person.PHONE }}</td>
                        <td><span v-for="team in person.TEAMS ?? []" :key="team.OID" class="tag" style="margin-inline-end: 4px">{{ team.NAME }}</span></td>
                        <td><span class="badge" :class="truthy(person.IS_ACTIVE) ? 'success' : ''">{{ t(truthy(person.IS_ACTIVE) ? 'field.can_login' : 'field.blocked') }}</span></td>
                        <td class="num" @click.stop>
                            <button v-if="person.CAN_DELETE !== false" type="button" class="btn btn-ghost btn-sm" @click="askDelete('inspector', person)"><Icon name="x" :size="14" /></button>
                            <span v-else class="btn btn-ghost btn-sm" :title="(person.DELETE_BLOCKERS ?? []).join('، ')" aria-disabled="true"><Icon name="lock" :size="14" /></span>
                        </td>
                    </tr>
                </tbody>
            </table>
            <EmptyState v-else icon="user" :title="t('field.no_inspectors')" />
        </template>
        <p class="hint" style="padding: var(--s-3) var(--s-4); border-top: 1px solid var(--border)">{{ t('field.delete_hint') }}</p>
    </section>

    <FormDrawer v-if="editing?.kind === 'team'" :title="t(editing.record ? 'field.edit_team' : 'field.add_team')" :busy="teamForm.processing" @close="close" @submit="save">
        <div class="field"><label for="t-name">{{ t('common.name') }}</label><input id="t-name" v-model="teamForm.NAME" class="input" required maxlength="255" /><span v-if="teamForm.errors.NAME" class="error-text">{{ teamForm.errors.NAME }}</span></div>
        <label class="check"><input v-model="teamForm.IS_ACTIVE" type="checkbox" />{{ t('common.active') }}</label>
        <label v-if="assignAll" class="check"><input v-model="teamForm.ASSIGN_ALL_VALVES" type="checkbox" />{{ t('field.assign_all') }}</label>
        <div v-if="!teamForm.ASSIGN_ALL_VALVES" class="field">
            <span class="label">{{ t(`field.assign_${unit}`) }} <span class="subtle num">({{ teamForm[itemKey].length }})</span></span>
            <input v-model="filter" class="input input-sm" type="search" :placeholder="t('field.filter_items')" />
            <div class="panel" style="max-height: 320px; overflow: auto; padding: var(--s-2)">
                <label v-for="item in shownItems" :key="item.OID ?? item.W4_GENERAL_ID" class="check" style="display: flex; padding: 4px">
                    <input v-model="teamForm[itemKey]" type="checkbox" :value="Number(item.OID ?? item.W4_GENERAL_ID)" />{{ itemLabel(item) }}
                </label>
            </div>
            <span v-if="teamForm.errors[itemKey]" class="error-text">{{ teamForm.errors[itemKey] }}</span>
        </div>
    </FormDrawer>

    <FormDrawer v-if="editing?.kind === 'inspector'" :title="t(editing.record ? 'field.edit_inspector' : 'field.add_inspector')" :busy="inspectorForm.processing" @close="close" @submit="save">
        <div class="field"><label for="i-name">{{ t('common.name') }}</label><input id="i-name" v-model="inspectorForm.NAME" class="input" required maxlength="255" /><span v-if="inspectorForm.errors.NAME" class="error-text">{{ inspectorForm.errors.NAME }}</span></div>
        <div class="field"><label for="i-phone">{{ t('field.phone') }}</label><input id="i-phone" v-model="inspectorForm.PHONE" class="input num" dir="ltr" required maxlength="20" /><span v-if="inspectorForm.errors.PHONE" class="error-text">{{ inspectorForm.errors.PHONE }}</span></div>
        <div class="field">
            <label for="i-pass">{{ t('field.password') }}</label>
            <input id="i-pass" v-model="inspectorForm.PASSWORD" class="input" type="password" autocomplete="new-password" :required="!editing.record" minlength="6" />
            <span class="hint">{{ t(editing.record ? 'field.password_keep' : 'field.password_hint') }}</span>
            <span v-if="inspectorForm.errors.PASSWORD" class="error-text">{{ inspectorForm.errors.PASSWORD }}</span>
        </div>
        <label class="check"><input v-model="inspectorForm.IS_ACTIVE" type="checkbox" />{{ t('field.can_login_hint') }}</label>
        <div class="field">
            <span class="label">{{ t('field.teams') }}</span>
            <label v-for="team in teams" :key="team.OID" class="check"><input v-model="inspectorForm.team_ids" type="checkbox" :value="Number(team.OID)" />{{ team.NAME }}</label>
        </div>
    </FormDrawer>

    <ConfirmDialog ref="dialog" :title="t('field.delete_title', { name: doomed?.record?.NAME ?? '' })" :confirm-label="t('common.delete')" tone="danger" @confirm="destroy">
        <p class="muted">{{ t('field.delete_body') }}</p>
    </ConfirmDialog>
</template>

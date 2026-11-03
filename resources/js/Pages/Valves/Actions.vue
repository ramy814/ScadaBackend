<script setup>
import { Head } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import EmptyState from '../../Components/EmptyState.vue';
import Pagination from '../../Components/Pagination.vue';
import SearchBox from '../../Components/SearchBox.vue';
import { useI18n } from '../../lib/i18n.js';
import { visit } from '../../lib/query.js';

defineProps({ actions: Object, filters: Object, teams: Array, inspectors: Array });
const { t, date } = useI18n();
</script>

<template>
    <Head :title="t('nav.valves.actions')" />
    <PageHeader :title="t('valves.actions.title')" :description="t('valves.actions.subtitle')" />
    <section class="panel">
        <div class="toolbar">
            <SearchBox param="valve" :model-value="filters.valve" :placeholder="t('valves.actions.search')" />
            <select class="select select-sm" style="width: auto" :value="filters.action ?? ''" :aria-label="t('valves.actions.action')" @change="visit({ action: $event.target.value })">
                <option value="">{{ t('valves.actions.any_action') }}</option>
                <option v-for="a in ['open', 'close', 'maintenance', 'replacement']" :key="a" :value="a">{{ t(`status.action.${a}`) }}</option>
            </select>
            <select class="select select-sm" style="width: auto" :value="filters.team ?? ''" :aria-label="t('field.teams')" @change="visit({ team: $event.target.value })">
                <option value="">{{ t('valves.actions.any_team') }}</option>
                <option v-for="team in teams" :key="team.oid" :value="team.oid">{{ team.name }}</option>
            </select>
            <select class="select select-sm" style="width: auto" :value="filters.inspector ?? ''" :aria-label="t('field.inspectors')" @change="visit({ inspector: $event.target.value })">
                <option value="">{{ t('valves.actions.any_inspector') }}</option>
                <option v-for="person in inspectors" :key="person.oid" :value="person.oid">{{ person.name }}</option>
            </select>
        </div>
        <table v-if="actions.data.length" class="table stackable">
            <thead><tr><th>{{ t('valves.cols.valve') }}</th><th>{{ t('valves.actions.action') }}</th><th>{{ t('field.inspector') }}</th><th>{{ t('valves.actions.note') }}</th><th>{{ t('valves.actions.at') }}</th></tr></thead>
            <tbody>
                <tr v-for="a in actions.data" :key="a.oid">
                    <td><div class="cell-main mono">{{ a.valve.code }}</div><div class="cell-sub">{{ a.valve.location }}</div></td>
                    <td><StatusBadge kind="action" :value="a.action" /></td>
                    <td>{{ a.inspector ?? '—' }}</td>
                    <td class="muted">{{ a.note ?? '—' }}</td>
                    <td class="num">{{ date(a.action_at) }}</td>
                </tr>
            </tbody>
        </table>
        <EmptyState v-else icon="history" :title="t('common.no_results_title')" :body="t('common.no_results_body')" />
        <Pagination :page="actions" />
    </section>
</template>

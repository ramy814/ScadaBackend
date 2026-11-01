<script setup>
import { ref } from 'vue';
import StatusBadge from './StatusBadge.vue';
import EmptyState from './EmptyState.vue';
import { useI18n } from '../lib/i18n.js';

// Shared by Wells and Valves: their teams/inspectors stay separate data (business rule),
// only the presentation pattern is shared.
defineProps({ teams: Array, inspectors: Array, unit: String, activity: String });
const { t, num } = useI18n();
const tab = ref('teams');
</script>

<template>
    <section class="panel">
        <nav class="tabs" style="padding: 0 var(--s-3)">
            <button type="button" :aria-current="tab === 'teams'" @click="tab = 'teams'">{{ t('field.teams') }}<span class="count">{{ num(teams.length) }}</span></button>
            <button type="button" :aria-current="tab === 'inspectors'" @click="tab = 'inspectors'">{{ t('field.inspectors') }}<span class="count">{{ num(inspectors.length) }}</span></button>
        </nav>
        <template v-if="tab === 'teams'">
            <table v-if="teams.length" class="table stackable">
                <thead><tr><th>{{ t('common.name') }}</th><th class="num">{{ t(`field.${unit}`) }}</th><th class="num">{{ t('field.inspectors') }}</th><th>{{ t('common.state') }}</th></tr></thead>
                <tbody>
                    <tr v-for="team in teams" :key="team.oid">
                        <td class="cell-main">{{ team.name }}</td>
                        <td class="num"><span v-if="team.assign_all" class="tag">{{ t('field.all_valves') }}</span><span v-else>{{ num(team[unit]) }}</span></td>
                        <td class="num">{{ num(team.inspectors) }}</td>
                        <td><StatusBadge kind="active" :value="team.is_active" /></td>
                    </tr>
                </tbody>
            </table>
            <EmptyState v-else icon="users" :title="t('field.no_teams')" />
        </template>
        <template v-else>
            <table v-if="inspectors.length" class="table stackable">
                <thead><tr><th>{{ t('common.name') }}</th><th>{{ t('field.phone') }}</th><th>{{ t('field.teams') }}</th><th class="num">{{ t(`field.${activity}`) }}</th><th>{{ t('field.app_login') }}</th></tr></thead>
                <tbody>
                    <tr v-for="person in inspectors" :key="person.oid">
                        <td class="cell-main">{{ person.name }}</td>
                        <td class="num" dir="ltr" style="text-align: start">{{ person.phone }}</td>
                        <td><span v-for="name in person.teams" :key="name" class="tag" style="margin-inline-end: 4px">{{ name }}</span></td>
                        <td class="num">{{ num(person[activity]) }}</td>
                        <td><span class="badge" :class="person.is_active ? 'success' : ''">{{ t(person.is_active ? 'field.can_login' : 'field.blocked') }}</span></td>
                    </tr>
                </tbody>
            </table>
            <EmptyState v-else icon="user" :title="t('field.no_inspectors')" />
        </template>
        <p class="hint" style="padding: var(--s-3) var(--s-4); border-top: 1px solid var(--border)">{{ t('field.manage_hint') }}</p>
    </section>
</template>

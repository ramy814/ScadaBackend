<script setup>
import { Head } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import FieldTeams from '../../Components/FieldTeams.vue';
import { useI18n } from '../../lib/i18n.js';

defineProps({ teams: Array, inspectors: Array });
const { t } = useI18n();
</script>

<template>
    <Head :title="t('nav.valves.field')" />
    <PageHeader :title="t('field.title')" :description="t('field.valves_subtitle')" />
    <FieldTeams :teams="teams" :inspectors="inspectors" unit="valves" activity="actions" />
</template>

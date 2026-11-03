<script setup>
import { Head } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import FieldManager from '../../Components/Wells/FieldManager.vue';
import { useI18n } from '../../lib/i18n.js';

defineProps({ teams: Array, inspectors: Array, valves: Array });
const { t } = useI18n();
// Legacy valve code convention: WV-{W4_GENERAL_ID}.
const label = (valve) => `WV-${valve.W4_GENERAL_ID ?? valve.OID}${valve.NAME ? ` · ${valve.NAME}` : ''}`;
</script>

<template>
    <Head :title="t('nav.valves.field')" />
    <PageHeader :title="t('field.title')" :description="t('field.valves_subtitle')" />
    <FieldManager :teams="teams" :inspectors="inspectors" :items="valves" base="/valves" item-key="valve_ids" :item-label="label" unit="valves" activity="actions" assign-all />
</template>

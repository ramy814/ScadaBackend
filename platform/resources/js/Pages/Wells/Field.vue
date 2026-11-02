<script setup>
import { Head } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import FieldManager from '../../Components/Wells/FieldManager.vue';
import { useI18n } from '../../lib/i18n.js';

defineProps({ teams: Array, inspectors: Array, wells: Array });
const { t } = useI18n();
const label = (well) => `${well.NAME}${well.WELL_NUM ? ` · ${well.WELL_NUM}` : ''}`;
</script>

<template>
    <Head :title="t('nav.wells.field')" />
    <PageHeader :title="t('field.title')" :description="t('field.wells_subtitle')" />
    <FieldManager :teams="teams" :inspectors="inspectors" :items="wells" base="/wells" item-key="well_ids" :item-label="label" unit="wells" activity="readings" />
</template>

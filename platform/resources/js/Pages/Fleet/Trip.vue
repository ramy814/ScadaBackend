<script setup>
import { Head, Link } from '@inertiajs/vue3';
import Icon from '../../Components/Icon.vue';
import PageHeader from '../../Components/PageHeader.vue';
import TripPanel from '../../Components/Fleet/TripPanel.vue';
import { useI18n } from '../../lib/i18n.js';
import { useCrumb } from '../../lib/shell.js';

const props = defineProps({ trip: Object, metrics: Object, audit: Array, can: Object });
const { t } = useI18n();
useCrumb(`#${props.trip.id}`);
const base = window.location.pathname.replace(/\/\d+$/, '');
</script>

<template>
    <Head :title="t('fleet.detail.trip', { id: trip.id })" />
    <PageHeader :title="t('fleet.detail.trip', { id: trip.id })" :description="`${trip.driver.name ?? ''} · ${trip.vehicle.plate ?? ''}`">
        <template #actions>
            <Link href="/fleet/reviews" class="btn"><Icon name="arrow-left" class="flip" />{{ t('fleet.detail.back') }}</Link>
        </template>
    </PageHeader>
    <TripPanel :data="{ trip, metrics, audit, can }" :base="base" layout="page" />
</template>

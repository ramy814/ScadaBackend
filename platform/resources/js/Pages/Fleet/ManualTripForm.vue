<script setup>
import { computed, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Icon from '../../Components/Icon.vue';
import PageHeader from '../../Components/PageHeader.vue';
import { useI18n } from '../../lib/i18n.js';
import { useCrumb } from '../../lib/shell.js';

// Rules mirror StoreManualTripRequest: same calendar day, end after start, list-only places,
// distance = end − start odometer when both are entered (server recalculates too).
const props = defineProps({ trip: Object, vehicles: Array, locations: Array, mission_types: Array });
const { t } = useI18n();
const editing = !!props.trip;
useCrumb(editing ? `#${props.trip.id}` : t('fleet.manual.add'));

const today = new Date().toLocaleDateString('en-CA');
const split = (iso) => (iso ? [iso.slice(0, 10), iso.slice(11, 19)] : [today, '']);
const [startDate, startTime] = split(props.trip?.started_at);
const [, endTime] = split(props.trip?.ended_at);

const form = useForm({
    oracle_vehicle_id: props.trip?.oracle_vehicle_id ?? '',
    day: startDate, start_time: startTime, end_time: endTime,
    origin_text: props.trip?.origin_text ?? '', destination_text: props.trip?.destination_text ?? '', mission_type: props.trip?.mission_type ?? '',
    start_odometer: props.trip?.start_odometer ?? '', end_odometer: props.trip?.end_odometer ?? '',
    estimated_distance_km: props.trip?.estimated_distance_km ?? '', wait_minutes: props.trip?.wait_minutes ?? 0, notes: props.trip?.notes ?? '',
});
const fromOdometer = computed(() => form.start_odometer !== '' && form.end_odometer !== '' && form.start_odometer !== null && form.end_odometer !== null);
watch(() => [form.start_odometer, form.end_odometer], () => {
    if (fromOdometer.value) form.estimated_distance_km = Math.max(0, Number(form.end_odometer) - Number(form.start_odometer));
});
const vehicle = computed(() => props.vehicles.find((v) => String(v.oracle_id) === String(form.oracle_vehicle_id)));
const seconds = (time) => (time && time.length === 5 ? `${time}:00` : time);

function submit() {
    form.transform((d) => ({
        oracle_vehicle_id: d.oracle_vehicle_id,
        started_at: `${d.day}T${seconds(d.start_time)}`, ended_at: `${d.day}T${seconds(d.end_time)}`,
        origin_text: d.origin_text, destination_text: d.destination_text, mission_type: d.mission_type,
        start_odometer: d.start_odometer === '' ? null : d.start_odometer, end_odometer: d.end_odometer === '' ? null : d.end_odometer,
        estimated_distance_km: d.estimated_distance_km, wait_minutes: d.wait_minutes || 0, notes: d.notes,
    }))[editing ? 'put' : 'post'](editing ? `/fleet/manual/${props.trip.id}` : '/fleet/manual');
}
</script>

<template>
    <Head :title="editing ? t('fleet.manual.edit') : t('fleet.manual.add')" />
    <PageHeader :title="editing ? t('fleet.manual.edit_title', { id: trip.id }) : t('fleet.manual.add')" :description="t('fleet.manual.form_subtitle')">
        <template #actions><Link href="/fleet/manual" class="btn"><Icon name="arrow-left" class="flip" />{{ t('nav.fleet.manual') }}</Link></template>
    </PageHeader>
    <form class="stack" style="max-width: 860px" @submit.prevent="submit">
        <section class="panel">
            <div class="panel-head"><h2 class="section-title">{{ t('fleet.manual.vehicle_section') }}</h2></div>
            <div class="panel-body stack">
                <div class="field">
                    <label for="m-vehicle">{{ t('fleet.detail.vehicle') }}</label>
                    <select id="m-vehicle" v-model="form.oracle_vehicle_id" class="select" required>
                        <option value="">{{ t('fleet.manual.pick_vehicle') }}</option>
                        <option v-for="v in vehicles" :key="v.oracle_id" :value="v.oracle_id">{{ v.plate_number }} · {{ v.name }}</option>
                    </select>
                    <span v-if="form.errors.oracle_vehicle_id" class="error-text">{{ form.errors.oracle_vehicle_id }}</span>
                    <span v-else-if="!vehicles.length" class="hint">{{ t('fleet.manual.no_vehicles') }}</span>
                </div>
                <dl v-if="vehicle" class="dl"><dt>{{ t('fleet.detail.driver') }}</dt><dd>{{ vehicle.driver_name ?? '—' }} <span class="subtle num" dir="ltr">{{ vehicle.driver_phone }}</span></dd></dl>
            </div>
        </section>
        <section class="panel">
            <div class="panel-head"><h2 class="section-title">{{ t('fleet.manual.time_section') }}</h2></div>
            <div class="panel-body grid grid-3">
                <div class="field"><label for="m-day">{{ t('fleet.manual.day') }}</label><input id="m-day" v-model="form.day" type="date" class="input num" required /></div>
                <div class="field"><label for="m-start">{{ t('fleet.manual.start') }}</label><input id="m-start" v-model="form.start_time" type="time" step="1" class="input num" required /><span v-if="form.errors.started_at" class="error-text">{{ form.errors.started_at }}</span></div>
                <div class="field"><label for="m-end">{{ t('fleet.manual.end') }}</label><input id="m-end" v-model="form.end_time" type="time" step="1" class="input num" required /><span v-if="form.errors.ended_at" class="error-text">{{ form.errors.ended_at }}</span></div>
            </div>
            <p class="hint" style="padding: 0 var(--s-4) var(--s-4)">{{ t('fleet.manual.same_day') }}</p>
        </section>
        <section class="panel">
            <div class="panel-head"><h2 class="section-title">{{ t('fleet.manual.route_section') }}</h2></div>
            <div class="panel-body grid grid-3">
                <div v-for="[key, list] in [['origin_text', locations], ['destination_text', locations], ['mission_type', mission_types]]" :key="key" class="field">
                    <label :for="`m-${key}`">{{ t(`fleet.manual.fields.${key}`) }}</label>
                    <select :id="`m-${key}`" v-model="form[key]" class="select" required><option value="">—</option><option v-for="item in list" :key="item" :value="item">{{ item }}</option></select>
                    <span v-if="form.errors[key]" class="error-text">{{ form.errors[key] }}</span>
                </div>
            </div>
        </section>
        <section class="panel">
            <div class="panel-head"><h2 class="section-title">{{ t('fleet.manual.distance_section') }}</h2></div>
            <div class="panel-body grid grid-4">
                <div class="field"><label for="m-so">{{ t('fleet.manual.start_odometer') }}</label><input id="m-so" v-model="form.start_odometer" type="number" min="0" step="1" class="input num" /></div>
                <div class="field"><label for="m-eo">{{ t('fleet.manual.end_odometer') }}</label><input id="m-eo" v-model="form.end_odometer" type="number" min="0" step="1" class="input num" /><span v-if="form.errors.end_odometer" class="error-text">{{ form.errors.end_odometer }}</span></div>
                <div class="field"><label for="m-km">{{ t('fleet.manual.distance') }}</label><input id="m-km" v-model="form.estimated_distance_km" type="number" min="0" step="0.001" class="input num" required :readonly="fromOdometer" /><span v-if="form.errors.estimated_distance_km" class="error-text">{{ form.errors.estimated_distance_km }}</span><span v-else-if="fromOdometer" class="hint">{{ t('fleet.manual.from_odometer') }}</span></div>
                <div class="field"><label for="m-wait">{{ t('fleet.manual.wait') }}</label><input id="m-wait" v-model="form.wait_minutes" type="number" min="0" max="720" step="1" class="input num" /></div>
            </div>
            <div class="panel-body" style="padding-top: 0"><div class="field"><label for="m-notes">{{ t('fleet.detail.notes') }}</label><textarea id="m-notes" v-model="form.notes" class="textarea" maxlength="2000" /></div></div>
        </section>
        <div class="row">
            <button type="submit" class="btn btn-primary" :disabled="form.processing">{{ t('fleet.manual.save_draft') }}</button>
            <Link href="/fleet/manual" class="btn">{{ t('common.cancel') }}</Link>
            <span class="hint">{{ t('fleet.manual.draft_hint') }}</span>
        </div>
    </form>
</template>

<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Icon from '../../Components/Icon.vue';
import PageHeader from '../../Components/PageHeader.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import EmptyState from '../../Components/EmptyState.vue';
import Pagination from '../../Components/Pagination.vue';
import SearchBox from '../../Components/SearchBox.vue';
import ConfirmDialog from '../../Components/ConfirmDialog.vue';
import { useI18n } from '../../lib/i18n.js';
import { visit } from '../../lib/query.js';

const props = defineProps({ trips: Object, vehicles: Array, filters: Object, mode: String, can: Object });
const { t, num, date } = useI18n();
const approval = props.mode === 'approval';
const statuses = approval ? ['pending', 'approved', 'rejected', 'all'] : ['all', 'draft', 'pending', 'approved', 'rejected'];

const target = ref(null);
const dialog = ref(null);
const form = useForm({ note: '' });
function ask(trip) { target.value = trip; form.reset(); form.clearErrors(); dialog.value.open(); }
function confirm() {
    const url = approval ? target.value.approve_url : target.value.submit_url;
    form.post(url, { preserveScroll: true, onSuccess: () => dialog.value.close() });
}
</script>

<template>
    <Head :title="t(approval ? 'nav.fleet.manual_requests' : 'nav.fleet.manual')" />
    <PageHeader :title="t(approval ? 'fleet.manual.requests_title' : 'fleet.manual.title')" :description="t(approval ? 'fleet.manual.requests_subtitle' : 'fleet.manual.subtitle')">
        <template #actions>
            <Link v-if="!approval && can.approve" href="/fleet/manual/requests" class="btn"><Icon name="check" />{{ t('nav.fleet.manual_requests') }}</Link>
            <Link v-if="approval" href="/fleet/manual" class="btn"><Icon name="arrow-left" class="flip" />{{ t('nav.fleet.manual') }}</Link>
            <Link v-if="!approval && can.enter" href="/fleet/manual/create" class="btn btn-primary"><Icon name="plus" />{{ t('fleet.manual.add') }}</Link>
        </template>
    </PageHeader>
    <div v-if="!approval && !can.enter" class="notice warning" style="margin-bottom: var(--s-4)"><Icon name="lock" /><span>{{ t('fleet.manual.no_entry_scope') }}</span></div>
    <section class="panel">
        <nav class="tabs" style="padding: 0 var(--s-3)">
            <button v-for="s in statuses" :key="s" type="button" :aria-current="(filters.review_status ?? (approval ? 'pending' : 'all')) === s" @click="visit({ review_status: s })">{{ s === 'all' ? t('common.all') : t(`status.review.${s}`) }}</button>
        </nav>
        <div class="toolbar">
            <SearchBox :model-value="filters.search ?? ''" :placeholder="t('fleet.manual.search')" />
            <select class="select select-sm" style="width: auto" :value="filters.oracle_vehicle_id ?? ''" @change="visit({ oracle_vehicle_id: $event.target.value })">
                <option value="">{{ t('fleet.manual.all_vehicles') }}</option>
                <option v-for="v in vehicles" :key="v.oracle_id" :value="v.oracle_id">{{ v.plate_number }} · {{ v.name }}</option>
            </select>
            <label class="row" style="font-size: var(--fs-sm)">{{ t('common.from') }}<input type="date" class="input input-sm num" style="width: auto" :value="filters.date_from" @change="visit({ date_from: $event.target.value })" /></label>
            <label class="row" style="font-size: var(--fs-sm)">{{ t('common.to') }}<input type="date" class="input input-sm num" style="width: auto" :value="filters.date_to" @change="visit({ date_to: $event.target.value })" /></label>
        </div>
        <table v-if="trips.data.length" class="table stackable">
            <thead><tr><th>#</th><th>{{ t('fleet.cols.driver') }}</th><th>{{ t('fleet.cols.route') }}</th><th>{{ t('fleet.cols.started') }}</th><th class="num">{{ t('fleet.cols.distance') }}</th><th v-if="approval">{{ t('fleet.manual.entered_by') }}</th><th>{{ t('fleet.cols.status') }}</th><th /></tr></thead>
            <tbody>
                <tr v-for="trip in trips.data" :key="trip.id">
                    <td class="num subtle">{{ trip.id }}</td>
                    <td><div class="cell-main">{{ trip.driver_name }}</div><div class="cell-sub"><bdi class="mono">{{ trip.vehicle_plate }}</bdi> · {{ trip.vehicle_name }}</div></td>
                    <td><div>{{ trip.origin_text }} <Icon name="arrow-right" :size="12" class="flip subtle" /> {{ trip.destination_text }}</div><div class="cell-sub">{{ trip.mission_type }}</div></td>
                    <td class="num"><div>{{ date(trip.started_at, 'date') }}</div><div class="cell-sub">{{ date(trip.started_at, 'time') }} — {{ date(trip.ended_at, 'time') }}</div></td>
                    <td class="num">{{ num(trip.distance_km, 1) }} <span class="subtle">{{ t('common.km') }}</span></td>
                    <td v-if="approval">{{ trip.entered_by_name }}</td>
                    <td><StatusBadge kind="review" :value="trip.review_status" /></td>
                    <td class="num" style="white-space: nowrap">
                        <template v-if="!approval && trip.review_status === 'draft' && can.enter">
                            <Link :href="`/fleet/manual/${trip.id}/edit`" class="btn btn-ghost btn-sm"><Icon name="pencil" :size="14" />{{ t('common.edit') }}</Link>
                            <button type="button" class="btn btn-sm" @click="ask(trip)">{{ t('fleet.manual.submit') }}</button>
                        </template>
                        <button v-if="approval && trip.review_status === 'pending'" type="button" class="btn btn-primary btn-sm" @click="ask(trip)"><Icon name="check" :size="14" />{{ t('fleet.decision.approve') }}</button>
                    </td>
                </tr>
            </tbody>
        </table>
        <EmptyState v-else icon="inbox" :title="t(approval ? 'fleet.manual.requests_empty' : 'fleet.manual.empty')" />
        <Pagination :page="trips" />
    </section>
    <p class="hint" style="margin-top: var(--s-2)">{{ t(approval ? 'fleet.manual.approval_hint' : 'fleet.manual.register_hint') }}</p>

    <ConfirmDialog ref="dialog" :title="t(approval ? 'fleet.manual.approve_title' : 'fleet.manual.submit_title', { id: target?.id ?? '' })" :confirm-label="t(approval ? 'fleet.decision.approve' : 'fleet.manual.submit')" :busy="form.processing" @confirm="confirm">
        <dl v-if="target" class="dl">
            <dt>{{ t('fleet.detail.driver') }}</dt><dd>{{ target.driver_name }}</dd>
            <dt>{{ t('fleet.detail.vehicle') }}</dt><dd><bdi class="mono">{{ target.vehicle_plate }}</bdi></dd>
            <dt>{{ t('fleet.detail.route') }}</dt><dd>{{ target.origin_text }} → {{ target.destination_text }}</dd>
            <dt>{{ t('fleet.detail.time') }}</dt><dd class="num">{{ date(target.started_at) }} — {{ date(target.ended_at, 'time') }}</dd>
            <dt>{{ t('fleet.cols.distance') }}</dt><dd class="num">{{ num(target.distance_km, 3) }} {{ t('common.km') }}</dd>
            <dt v-if="target.notes">{{ t('fleet.detail.notes') }}</dt><dd v-if="target.notes">{{ target.notes }}</dd>
        </dl>
        <p v-if="!approval" class="muted">{{ t('fleet.manual.submit_body') }}</p>
        <div v-if="approval" class="field"><label for="approve-note">{{ t('fleet.decision.note') }}</label><textarea id="approve-note" v-model="form.note" class="textarea" maxlength="1000" /></div>
        <span v-if="Object.keys(form.errors).length" class="error-text">{{ Object.values(form.errors)[0] }}</span>
    </ConfirmDialog>
</template>

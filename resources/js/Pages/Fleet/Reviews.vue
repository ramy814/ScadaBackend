<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import Icon from '../../Components/Icon.vue';
import PageHeader from '../../Components/PageHeader.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import EmptyState from '../../Components/EmptyState.vue';
import Pagination from '../../Components/Pagination.vue';
import SearchBox from '../../Components/SearchBox.vue';
import Drawer from '../../Components/Drawer.vue';
import TripPanel from '../../Components/Fleet/TripPanel.vue';
import { useI18n } from '../../lib/i18n.js';
import { visit } from '../../lib/query.js';

const props = defineProps({ trips: Object, counts: Object, filters: Object, tripBase: String });
const { t, num, date, relative } = useI18n();

const mobile = computed(() => props.filters.source !== 'manual');
const counts = computed(() => props.counts[props.filters.source] ?? {});
const queues = computed(() => [
    { key: 'awaiting', count: counts.value.awaiting },
    ...(mobile.value ? [{ key: 'responded', count: counts.value.responded }, { key: 'clarification', count: counts.value.clarification }] : []),
    { key: 'decided' },
    { key: 'all' },
]);
const periodApplies = computed(() => ['decided', 'all'].includes(props.filters.queue));

// Review drawer: decide without leaving the list, then continue with the next row.
const selected = ref(null);
const selectedIndex = ref(0);
const detail = ref(null);
const loading = ref(false);
const failed = ref(false);
const rows = computed(() => props.trips.data);

async function openTrip(row) {
    selected.value = row.id;
    selectedIndex.value = rows.value.findIndex((r) => r.id === row.id);
    loading.value = true;
    failed.value = false;
    try {
        const response = await fetch(`${props.tripBase}/${row.id}`, { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error(String(response.status));
        detail.value = await response.json();
    } catch {
        failed.value = true;
    } finally {
        loading.value = false;
    }
}

function close() { selected.value = null; detail.value = null; }

// The decision POST redirects back here with fresh props; continue with the next row.
function afterDecision() {
    const stillListed = props.trips.data.some((r) => r.id === selected.value);
    const next = props.trips.data[stillListed ? selectedIndex.value + 1 : selectedIndex.value];
    next && props.filters.queue !== 'all' ? openTrip(next) : close();
}

function onKey(event) {
    if (event.target.closest('input, textarea, select, dialog, [contenteditable]')) return;
    const index = rows.value.findIndex((r) => r.id === selected.value);
    if (event.key === 'j' && rows.value[index + 1]) openTrip(rows.value[index + 1]);
    if (event.key === 'k' && index > 0) openTrip(rows.value[index - 1]);
}
onMounted(() => window.addEventListener('keydown', onKey));
onBeforeUnmount(() => window.removeEventListener('keydown', onKey));

const evidence = (row) => row.evidence.gps_disabled + row.evidence.airplane_mode + row.evidence.tracking_gap;
</script>

<template>
    <Head :title="t('nav.fleet.reviews')" />
    <PageHeader :title="t('fleet.reviews.title')" :description="t('fleet.reviews.subtitle')">
        <template #actions>
            <div class="segmented" role="tablist" :aria-label="t('fleet.source.label')">
                <button v-for="source in ['mobile', 'manual']" :key="source" type="button" role="tab" :aria-current="filters.source === source" @click="visit({ source: source === 'mobile' ? null : source, queue: null })">
                    {{ t(`fleet.source.${source}`) }}
                    <span v-if="props.counts[source]?.awaiting" class="count">{{ num(props.counts[source].awaiting) }}</span>
                </button>
            </div>
        </template>
    </PageHeader>

    <section class="panel">
        <nav class="tabs" style="padding: 0 var(--s-3)" :aria-label="t('fleet.queue.label')">
            <button v-for="queue in queues" :key="queue.key" type="button" :aria-current="filters.queue === queue.key" @click="visit({ queue: queue.key === 'awaiting' ? null : queue.key })">
                {{ t(`fleet.queue.${queue.key}`) }}<span v-if="queue.count" class="count">{{ num(queue.count) }}</span>
            </button>
        </nav>
        <div class="toolbar">
            <SearchBox :model-value="filters.search" :placeholder="t('fleet.reviews.search')" />
            <select v-if="periodApplies" class="select select-sm" style="width: auto" :value="filters.period" :aria-label="t('common.period')" @change="visit({ period: $event.target.value })">
                <option v-for="p in ['day', 'week', 'month']" :key="p" :value="p">{{ t(`period.${p}`) }}</option>
            </select>
            <span v-else class="hint"><Icon name="info" :size="13" /> {{ t('fleet.reviews.no_period') }}</span>
            <span class="spacer" />
            <label v-if="mobile" class="check" :title="t('fleet.reviews.experimental_hint')">
                <input type="checkbox" :checked="filters.experimental" @change="visit({ experimental: $event.target.checked })" />{{ t('fleet.reviews.experimental') }}
            </label>
        </div>

        <div v-if="rows.length" class="table-wrap">
            <table class="table stackable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>{{ t('fleet.cols.driver') }}</th>
                        <th>{{ t('fleet.cols.route') }}</th>
                        <th>{{ t('fleet.cols.started') }}</th>
                        <th class="num">{{ t('fleet.cols.distance') }}</th>
                        <th>{{ t('fleet.cols.evidence') }}</th>
                        <th>{{ t('fleet.cols.status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows" :key="row.id" class="clickable" :class="{ selected: selected === row.id }" tabindex="0" @click="openTrip(row)" @keydown.enter="openTrip(row)">
                        <td class="num subtle">{{ row.id }}</td>
                        <td :data-label="t('fleet.cols.driver')">
                            <div class="cell-main">{{ row.driver_name ?? '—' }}</div>
                            <div class="cell-sub"><bdi class="mono">{{ row.vehicle_plate }}</bdi> · {{ row.vehicle_name }}</div>
                        </td>
                        <td :data-label="t('fleet.cols.route')">
                            <div>{{ row.origin }} <Icon name="arrow-right" :size="12" class="flip subtle" /> {{ row.destination }}</div>
                            <div class="cell-sub">{{ row.mission_type }}<template v-if="row.entered_by_name"> · {{ t('fleet.reviews.entered_by', { name: row.entered_by_name }) }}</template></div>
                        </td>
                        <td :data-label="t('fleet.cols.started')">
                            <div class="num">{{ date(row.started_at) }}</div>
                            <div class="cell-sub">{{ relative(row.started_at) }}</div>
                        </td>
                        <td class="num" :data-label="t('fleet.cols.distance')">
                            <div>{{ num(row.distance_km, 1) }} <span class="subtle">{{ t('common.km') }}</span></div>
                            <div class="cell-sub">{{ num(row.fuel_liters, 2) }} {{ t('common.liters') }}</div>
                        </td>
                        <td :data-label="t('fleet.cols.evidence')">
                            <span v-if="evidence(row)" class="badge warning" :title="t('fleet.reviews.evidence_title', row.evidence)">{{ t('fleet.reviews.evidence_count', { n: evidence(row) }) }}</span>
                            <span v-else class="subtle">—</span>
                            <span v-if="row.experimental" class="tag" style="margin-inline-start: 4px">{{ t('fleet.reviews.experimental_tag') }}</span>
                        </td>
                        <td :data-label="t('fleet.cols.status')">
                            <StatusBadge kind="review" :value="row.review_status" />
                            <div v-if="row.has_response" class="cell-sub">{{ t('fleet.reviews.driver_replied') }}</div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <EmptyState v-else-if="filters.search" icon="search" :title="t('common.no_results_title')" :body="t('common.no_results_body')" />
        <EmptyState v-else icon="circle-check" :title="t(`fleet.empty.${filters.queue}.title`)" :body="t(`fleet.empty.${filters.queue}.body`)" />
        <Pagination :page="trips" />
    </section>
    <p class="hint" style="margin-top: var(--s-2)"><kbd>j</kbd> / <kbd>k</kbd> {{ t('fleet.reviews.keys') }}</p>

    <Drawer v-if="selected" :title="`#${selected}`" @close="close">
        <template #title><strong class="num">{{ t('fleet.detail.trip', { id: selected }) }}</strong></template>
        <template #head-actions>
            <Link :href="`${tripBase}/${selected}`" class="btn btn-ghost btn-sm"><Icon name="external-link" :size="14" />{{ t('common.open_full') }}</Link>
        </template>
        <div v-if="loading" class="stack"><div class="skeleton" style="height: 24px; width: 40%" /><div class="skeleton" style="height: 120px" /><div class="skeleton" style="height: 280px" /></div>
        <EmptyState v-else-if="failed" icon="alert-triangle" :title="t('common.load_failed')" :body="t('common.try_again')" />
        <TripPanel v-else-if="detail" :data="detail" :base="tripBase" @decided="afterDecision" />
    </Drawer>
</template>

<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import Icon from './Icon.vue';
import { useI18n } from '../lib/i18n.js';

const props = defineProps({ entries: { type: Array, required: true }, tripLookup: { type: String, default: null } });
const emit = defineEmits(['close']);
const { t } = useI18n();
const query = ref('');
const active = ref(0);
const input = ref(null);

const results = computed(() => {
    const q = query.value.trim().toLowerCase();
    const list = [];
    const tripId = q.replace(/^#/, '');
    if (props.tripLookup && /^\d+$/.test(tripId)) {
        list.push({ group: t('palette.records'), label: t('palette.open_trip', { id: tripId }), icon: 'route', url: `${props.tripLookup}/${tripId}` });
    }
    for (const entry of props.entries) {
        if (!q || entry.label.toLowerCase().includes(q) || entry.group.toLowerCase().includes(q)) list.push(entry);
    }
    return list.slice(0, 30);
});

watch(query, () => { active.value = 0; });
onMounted(() => nextTick(() => input.value?.focus()));

function go(item) {
    if (!item) return;
    emit('close');
    router.visit(item.url);
}

function onKey(event) {
    if (event.key === 'ArrowDown') { event.preventDefault(); active.value = Math.min(active.value + 1, results.value.length - 1); }
    if (event.key === 'ArrowUp') { event.preventDefault(); active.value = Math.max(active.value - 1, 0); }
    if (event.key === 'Enter') { event.preventDefault(); go(results.value[active.value]); }
    if (event.key === 'Escape') emit('close');
}
</script>

<template>
    <Teleport to="body">
        <div class="palette" @mousedown.self="emit('close')">
            <div class="palette-box" role="dialog" aria-modal="true" :aria-label="t('palette.title')">
                <input ref="input" v-model="query" class="palette-input" :placeholder="t('palette.placeholder')" role="combobox" aria-expanded="true" aria-controls="palette-list" @keydown="onKey" />
                <ul id="palette-list" class="palette-list" role="listbox">
                    <template v-for="(item, index) in results" :key="item.url + index">
                        <li v-if="index === 0 || results[index - 1].group !== item.group" class="palette-group" role="presentation">{{ item.group }}</li>
                        <li class="palette-item" role="option" :aria-selected="index === active" @mouseenter="active = index" @click="go(item)">
                            <Icon :name="item.icon" />
                            <span>{{ item.label }}</span>
                        </li>
                    </template>
                    <li v-if="!results.length" class="palette-group">{{ t('palette.no_match') }}</li>
                </ul>
            </div>
        </div>
    </Teleport>
</template>

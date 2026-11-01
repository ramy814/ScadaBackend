<script setup>
import { computed } from 'vue';
import { useI18n } from '../lib/i18n.js';

// One mapping from domain status to tone, so every screen speaks the same visual language.
const tones = {
    review: { pending: 'warning', needs_clarification: 'info', approved: 'success', rejected: 'danger', draft: 'neutral' },
    trip: { uploaded: 'info', confirmed: 'success' },
    version: { current: 'success', outdated: 'warning', not_reported: 'neutral' },
    maintenance: { pending: 'warning', in_progress: 'info', resolved: 'success' },
    priority: { high: 'danger', medium: 'warning', low: 'neutral' },
    health: { live: 'success', stale: 'warning', waiting: 'neutral', incident: 'danger' },
    action: { open: 'success', close: 'neutral', maintenance: 'warning', replacement: 'info' },
    active: { 1: 'success', 0: 'neutral', true: 'success', false: 'neutral' },
};

const props = defineProps({ kind: { type: String, required: true }, value: { type: [String, Number, Boolean], default: null } });
const { t } = useI18n();
const tone = computed(() => tones[props.kind]?.[String(props.value)] ?? 'neutral');
const label = computed(() => t(`status.${props.kind}.${String(props.value)}`));
</script>

<template>
    <span class="badge" :class="tone">{{ label }}</span>
</template>

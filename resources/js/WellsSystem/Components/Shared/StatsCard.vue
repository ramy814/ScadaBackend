<script setup>
import Icon from '@/WellsSystem/Components/Shared/Icon.vue';

defineProps({
    value: { type: [Number, String], required: true },
    label: { type: String, required: true },
    icon: { type: String, default: 'bar-chart' },
    iconClass: { type: String, default: 'accent' },
    change: { type: String, default: null },
    changeType: { type: String, default: 'positive' },
});
</script>

<template>
    <div class="stat-card glass-card">
        <div class="stat-icon-wrapper" :class="iconClass">
            <Icon :name="icon" size="28" />
        </div>
        <div class="stat-content">
            <div class="stat-value">{{ value }}</div>
            <div class="stat-label">{{ label }}</div>
            <div v-if="change" class="stat-change" :class="changeType">
                {{ change }}
            </div>
        </div>
    </div>
</template>

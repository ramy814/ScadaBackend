<script setup>
import Icon from './Icon.vue';

defineProps({ icon: { type: String, default: 'inbox' }, title: { type: String, required: true }, body: { type: String, default: '' } });
</script>

<template>
    <div class="empty" role="status">
        <div class="empty-icon"><Icon :name="icon" :size="20" /></div>
        <h3>{{ title }}</h3>
        <p v-if="body">{{ body }}</p>
        <slot />
    </div>
</template>

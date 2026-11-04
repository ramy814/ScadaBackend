<script setup>
defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: '' },
    maxWidth: { type: String, default: '620px' },
});

defineEmits(['close']);
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="v-modal-overlay" @click.self="$emit('close')">
            <div class="v-modal" :style="{ maxWidth }">
                <div class="v-modal-header">
                    <h3 class="v-modal-title">{{ title }}</h3>
                    <button class="v-modal-close" type="button" @click="$emit('close')">✕</button>
                </div>
                <div class="v-modal-body">
                    <slot />
                </div>
                <div v-if="$slots.footer" class="v-modal-footer">
                    <slot name="footer" />
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { ref } from 'vue';
import { useI18n } from '../lib/i18n.js';

// Native <dialog>: focus trap, Esc and backdrop handled by the browser.
defineProps({ title: { type: String, required: true }, confirmLabel: { type: String, required: true }, tone: { type: String, default: 'primary' }, busy: Boolean });
const emit = defineEmits(['confirm']);
const dialog = ref(null);
const { t } = useI18n();
defineExpose({ open: () => dialog.value?.showModal(), close: () => dialog.value?.close() });
</script>

<template>
    <dialog ref="dialog" class="dialog">
        <form method="dialog" @submit.prevent="emit('confirm')">
            <div class="dialog-body">
                <h2 class="section-title">{{ title }}</h2>
                <slot />
            </div>
            <div class="dialog-foot">
                <button type="button" class="btn" @click="dialog.close()">{{ t('common.cancel') }}</button>
                <button type="submit" class="btn" :class="tone === 'danger' ? 'btn-danger' : 'btn-primary'" :disabled="busy">{{ confirmLabel }}</button>
            </div>
        </form>
    </dialog>
</template>

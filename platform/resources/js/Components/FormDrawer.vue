<script setup>
import Drawer from './Drawer.vue';
import { useI18n } from '../lib/i18n.js';

// Create/edit drawer with the standard save/cancel footer.
defineProps({ title: { type: String, required: true }, busy: Boolean, formId: { type: String, default: 'drawer-form' } });
const emit = defineEmits(['close', 'submit']);
const { t } = useI18n();
</script>

<template>
    <Drawer :title="title" @close="emit('close')">
        <form :id="formId" class="stack" @submit.prevent="emit('submit')"><slot /></form>
        <template #footer>
            <div class="row">
                <button type="submit" :form="formId" class="btn btn-primary" :disabled="busy">{{ t('common.save') }}</button>
                <button type="button" class="btn" @click="emit('close')">{{ t('common.cancel') }}</button>
                <span class="spacer" />
                <slot name="danger" />
            </div>
        </template>
    </Drawer>
</template>

<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import ConfirmDialog from './ConfirmDialog.vue';
import { useI18n } from '../lib/i18n.js';

// Audited soft delete: the reason is mandatory and the row stays in Oracle for review.
const props = defineProps({ title: { type: String, required: true }, description: { type: String, default: '' } });
const { t } = useI18n();
const dialog = ref(null);
const form = useForm({ reason: '' });
const url = ref('');
defineExpose({ open: (target) => { url.value = target; form.reset(); form.clearErrors(); dialog.value.open(); } });
const submit = () => form.delete(url.value, { preserveScroll: true, onSuccess: () => dialog.value.close() });
</script>

<template>
    <ConfirmDialog ref="dialog" :title="title" :confirm-label="t('common.delete')" tone="danger" :busy="form.processing" @confirm="submit">
        <p class="muted">{{ description || t('reports.audited_delete') }}</p>
        <div class="field">
            <label for="delete-reason">{{ t('reports.reason') }}</label>
            <textarea id="delete-reason" v-model="form.reason" class="textarea" required minlength="3" maxlength="1000" :aria-invalid="!!form.errors.reason" />
            <span v-if="form.errors.reason" class="error-text">{{ form.errors.reason }}</span>
        </div>
    </ConfirmDialog>
</template>

<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Icon from '../Icon.vue';
import { useI18n } from '../../lib/i18n.js';

const props = defineProps({ tripId: { type: Number, required: true }, base: { type: String, required: true }, canClarify: Boolean, hasResponse: Boolean });
const emit = defineEmits(['decided']);
const { t } = useI18n();
const form = useForm({ note: '' });
const pending = ref(null);

function submit(decision) {
    pending.value = decision;
    form.post(`${props.base}/${props.tripId}/${decision}`, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => { form.reset(); emit('decided', decision); },
        onFinish: () => { pending.value = null; },
    });
}
</script>

<template>
    <form class="stack" style="gap: var(--s-3)" @submit.prevent="submit('approve')">
        <div class="field">
            <label :for="`note-${tripId}`">{{ t('fleet.decision.note') }}</label>
            <textarea :id="`note-${tripId}`" v-model="form.note" class="textarea" rows="3" maxlength="1000" :aria-invalid="!!form.errors.note" :placeholder="t('fleet.decision.note_hint')" />
            <span v-if="form.errors.note" class="error-text">{{ form.errors.note }}</span>
            <span v-else-if="hasResponse" class="hint">{{ t('fleet.decision.keeps_question') }}</span>
        </div>
        <div class="row wrap">
            <button type="submit" class="btn btn-primary" :disabled="form.processing">
                <Icon :name="pending === 'approve' ? 'loader' : 'check'" :class="{ spin: pending === 'approve' }" />{{ t('fleet.decision.approve') }}
            </button>
            <button type="button" class="btn" :disabled="form.processing" @click="submit('reject')">
                <Icon :name="pending === 'reject' ? 'loader' : 'x'" :class="{ spin: pending === 'reject' }" />{{ t('fleet.decision.reject') }}
            </button>
            <button v-if="canClarify" type="button" class="btn btn-ghost" :disabled="form.processing" @click="submit('clarify')">
                <Icon name="message-square" />{{ t('fleet.decision.clarify') }}
            </button>
        </div>
        <p class="hint">{{ t('fleet.decision.final_hint') }}</p>
    </form>
</template>

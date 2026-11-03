<script setup>
import { onBeforeUnmount, onMounted } from 'vue';
import Icon from './Icon.vue';
import { useI18n } from '../lib/i18n.js';

defineProps({ title: { type: String, default: '' } });
const emit = defineEmits(['close']);
const { t } = useI18n();
const onKey = (event) => event.key === 'Escape' && emit('close');
onMounted(() => window.addEventListener('keydown', onKey));
onBeforeUnmount(() => window.removeEventListener('keydown', onKey));
</script>

<template>
    <Teleport to="body">
        <div class="drawer-scrim" @click="emit('close')" />
        <aside class="drawer" role="dialog" aria-modal="true" :aria-label="title">
            <header class="drawer-head">
                <slot name="title"><strong>{{ title }}</strong></slot>
                <span class="spacer" />
                <slot name="head-actions" />
                <button class="btn btn-ghost btn-icon" type="button" :aria-label="t('common.close')" @click="emit('close')"><Icon name="x" /></button>
            </header>
            <div class="drawer-body"><slot /></div>
            <footer v-if="$slots.footer" class="drawer-foot"><slot name="footer" /></footer>
        </aside>
    </Teleport>
</template>

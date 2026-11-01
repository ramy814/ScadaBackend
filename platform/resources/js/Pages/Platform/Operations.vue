<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import Icon from '../../Components/Icon.vue';
import PageHeader from '../../Components/PageHeader.vue';
import ConfirmDialog from '../../Components/ConfirmDialog.vue';
import { useI18n } from '../../lib/i18n.js';

const props = defineProps({ state: Object });
const { t, date } = useI18n();
const dialog = ref(null);
const form = useForm({ shutdown_enabled: !props.state.shutdown_enabled, reason: '', confirmation: false });
const submit = () => form.put('/platform/operations', { preserveScroll: true, onSuccess: () => { dialog.value?.close(); form.reset(); form.shutdown_enabled = !props.state.shutdown_enabled; } });
</script>

<template>
    <Head :title="t('nav.platform.operations')" />
    <PageHeader :title="t('operations.title')" :description="t('operations.subtitle')" />
    <div class="grid" style="grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr); align-items: start">
        <section class="panel">
            <div class="panel-body stack">
                <div class="row" style="gap: var(--s-3)">
                    <span class="app-mark" :style="state.shutdown_enabled ? 'background: var(--danger-bg); color: var(--danger)' : 'background: var(--success-bg); color: var(--success)'" style="width: 40px; height: 40px"><Icon name="power" :size="20" /></span>
                    <div>
                        <div class="section-title" style="font-size: var(--fs-lg)">{{ t(state.shutdown_enabled ? 'operations.state_suspended' : 'operations.state_running') }}</div>
                        <div class="muted" style="font-size: var(--fs-sm)">{{ state.changed_at ? t('operations.changed', { name: state.changed_by_name ?? '—', when: date(state.changed_at) }) : t('operations.never_changed') }}</div>
                    </div>
                </div>
                <div v-if="state.shutdown_enabled" class="notice danger"><Icon name="alert-triangle" /><span>{{ state.reason }}</span></div>
                <div v-if="!state.shutdown_enabled" class="field">
                    <label for="reason">{{ t('operations.reason') }}</label>
                    <textarea id="reason" v-model="form.reason" class="textarea" minlength="3" maxlength="1000" :aria-invalid="!!form.errors.reason" :placeholder="t('operations.reason_hint')" />
                    <span v-if="form.errors.reason" class="error-text">{{ form.errors.reason }}</span>
                </div>
                <div class="row">
                    <button v-if="state.shutdown_enabled" type="button" class="btn btn-primary" @click="dialog.open()"><Icon name="power" />{{ t('operations.reopen') }}</button>
                    <button v-else type="button" class="btn btn-danger" :disabled="form.reason.trim().length < 3" @click="dialog.open()"><Icon name="power" />{{ t('operations.suspend') }}</button>
                </div>
            </div>
        </section>
        <section class="panel panel-body stack" style="gap: var(--s-2)">
            <h2 class="section-title"><Icon name="info" />{{ t('operations.scope_title') }}</h2>
            <ul class="muted" style="margin: 0; padding-inline-start: var(--s-5); font-size: var(--fs-sm); line-height: 1.9">
                <li v-for="i in 4" :key="i">{{ t(`operations.scope.${i}`) }}</li>
            </ul>
        </section>
    </div>

    <ConfirmDialog ref="dialog" :title="t(state.shutdown_enabled ? 'operations.confirm_reopen' : 'operations.confirm_suspend')" :confirm-label="t(state.shutdown_enabled ? 'operations.reopen' : 'operations.suspend')" :tone="state.shutdown_enabled ? 'primary' : 'danger'" :busy="form.processing" @confirm="submit">
        <p class="muted">{{ t(state.shutdown_enabled ? 'operations.confirm_reopen_body' : 'operations.confirm_suspend_body') }}</p>
        <label v-if="!state.shutdown_enabled" class="check"><input v-model="form.confirmation" type="checkbox" required />{{ t('operations.acknowledge') }}</label>
    </ConfirmDialog>
</template>

<style scoped>
@media (max-width: 1100px) { .grid[style*='1.2fr'] { grid-template-columns: minmax(0, 1fr) !important; } }
</style>

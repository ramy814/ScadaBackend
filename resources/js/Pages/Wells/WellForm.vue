<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Icon from '../../Components/Icon.vue';
import PageHeader from '../../Components/PageHeader.vue';
import { useI18n } from '../../lib/i18n.js';
import { useCrumb } from '../../lib/shell.js';

// Field options are the exact allowlists of StoreWellRequest (ported from legacy).
const sections = [
    { key: 'general', fields: [['WELL_NUM', 'text'], ['NAME', 'text', true], ['QUARTER_OID', 'quarter'], ['DAMAGE_STATUS', ['سليمة', 'متضررة', 'أعيد تأهيلها', 'مدمرة']], ['OPERATIONAL_STATUS', ['يعمل', 'متوقف', 'تحت الصيانة']]] },
    { key: 'pump', fields: [['WELL_DEPTH', 'number'], ['PUMP_TYPE', ['submersible', 'vertical'], true], ['PUMP_POWER_HP', 'number'], ['CAPACITY', 'number', true], ['SALINITY', 'number']] },
    { key: 'power', fields: [['POWER_SOURCE', ['electricity', 'solar', 'generator'], true], ['LATITUDE', 'number', true], ['LONGITUDE', 'number', true]] },
    { key: 'tank', fields: [['TANK_SHAPE', ['اسطواني عمودي', 'اسطواني أفقي', 'مكعب', 'Daily Tank']], ['TANK_MATERIAL', ['HDPE', 'معدن', 'فيبرجلاس']], ['TANK_DIAMETER', 'number'], ['TANK_HEIGHT', 'number']] },
    { key: 'chlorine', fields: [['CHLORINATION_SYSTEM', ['يوجد', 'لا يوجد']], ['CHLORINATION_STATUS', ['يعمل', 'لا يعمل']], ['CHLORINE_TANK_CAPACITY', 'number'], ['CHLORINE_TANK_TYPE', ['اسطواني عمودي', 'مكعب']], ['CHLORINE_TANK_MATERIAL', ['HDPE أزرق', 'بلاستيك شفاف']], ['CHLORINE_PUMP_TYPE', 'text']] },
    { key: 'cooling', fields: [['COOLING_TANK_CAPACITY', ['220', '500']], ['COOLING_TANK_TYPE', ['HDPE أزرق', 'HDPE أسود']], ['COOLING_SYSTEM', ['اوتوماتيك', 'يدوي', 'غير متوفر', 'غاطس', 'اوتوماتيك يحتاج صيانة']], ['COOLING_LINE_TYPE', ['حديد سكب', 'HDPE']]] },
];
const translated = ['PUMP_TYPE', 'POWER_SOURCE'];

const props = defineProps({ well: Object, quarters: Array, generators: Array });
const { t } = useI18n();
const editing = computed(() => !!props.well);
useCrumb(editing.value ? props.well.NAME : t('wells.assets.add'));

const initial = Object.fromEntries(sections.flatMap((s) => s.fields.map(([key]) => [key, props.well?.[key] ?? ''])));
const form = useForm({
    ...initial,
    IS_ACTIVE: props.well ? [true, 1, '1'].includes(props.well.IS_ACTIVE) : true,
    generators: (props.well?.GENERATORS ?? []).map((g) => ({ GENERATOR_OID: g.GENERATOR_OID, FUEL_CONSUMPTION_LPH: g.FUEL_CONSUMPTION_LPH ?? '', CONSUMPTION_TOLERANCE_LPH: g.CONSUMPTION_TOLERANCE_LPH ?? '' })),
    media: [],
});
const generatorName = (oid) => { const g = props.generators.find((x) => Number(x.OID) === Number(oid)); return g ? `${g.GENERATOR_NUM ?? ''} · ${g.NAME ?? ''}` : oid; };
const pick = ref('');
function addGenerator() {
    if (pick.value && !form.generators.some((g) => Number(g.GENERATOR_OID) === Number(pick.value))) form.generators.push({ GENERATOR_OID: Number(pick.value), FUEL_CONSUMPTION_LPH: '', CONSUMPTION_TOLERANCE_LPH: '' });
    pick.value = '';
}
const addPhoto = () => form.media.push({ category: 'pump', label: '', file: null });

function submit() {
    form.transform((data) => ({ ...data, IS_ACTIVE: data.IS_ACTIVE ? 1 : 0, media: data.media.filter((m) => m.file) }))
        .post(editing.value ? `/wells/assets/${props.well.OID}` : '/wells/assets', { forceFormData: true, preserveScroll: true });
}
const removePhoto = (media) => router.delete(`/wells/assets/${props.well.OID}/media/${media.OID}`, { preserveScroll: true });
</script>

<template>
    <Head :title="editing ? well.NAME : t('wells.assets.add')" />
    <PageHeader :title="editing ? well.NAME : t('wells.assets.add')" :description="t('wells.form.subtitle')">
        <template #actions><Link href="/wells/assets" class="btn"><Icon name="arrow-left" class="flip" />{{ t('nav.wells.assets') }}</Link></template>
    </PageHeader>

    <form class="stack" style="max-width: 980px" @submit.prevent="submit">
        <section v-for="section in sections" :key="section.key" class="panel">
            <div class="panel-head"><h2 class="section-title">{{ t(`wells.form.sections.${section.key}`) }}</h2></div>
            <div class="panel-body grid grid-3">
                <div v-for="[key, type, required] in section.fields" :key="key" class="field">
                    <label :for="key">{{ t(`wells.form.fields.${key}`) }}<span v-if="required" style="color: var(--danger)"> *</span></label>
                    <select v-if="type === 'quarter'" :id="key" v-model="form[key]" class="select"><option value="">—</option><option v-for="q in quarters" :key="q.OID ?? q.oid" :value="q.OID ?? q.oid">{{ q.NAME ?? q.name }}</option></select>
                    <select v-else-if="Array.isArray(type)" :id="key" v-model="form[key]" class="select" :required="required">
                        <option value="">—</option>
                        <option v-for="option in type" :key="option" :value="option">{{ translated.includes(key) ? t(`wells.form.options.${option}`) : option }}</option>
                    </select>
                    <input v-else :id="key" v-model="form[key]" class="input" :class="{ num: type === 'number' }" :type="type === 'number' ? 'number' : 'text'" step="any" :required="required" :aria-invalid="!!form.errors[key]" />
                    <span v-if="form.errors[key]" class="error-text">{{ form.errors[key] }}</span>
                </div>
            </div>
            <div v-if="section.key === 'general'" class="panel-foot"><label class="check"><input v-model="form.IS_ACTIVE" type="checkbox" />{{ t('wells.form.active') }}</label></div>
        </section>

        <section class="panel">
            <div class="panel-head"><h2 class="section-title">{{ t('wells.form.sections.generators') }}</h2></div>
            <div class="panel-body stack">
                <div class="row wrap">
                    <select v-model="pick" class="select" style="max-width: 360px"><option value="">{{ t('wells.form.pick_generator') }}</option><option v-for="g in generators" :key="g.OID" :value="g.OID">{{ g.GENERATOR_NUM }} · {{ g.NAME }}</option></select>
                    <button type="button" class="btn" :disabled="!pick" @click="addGenerator"><Icon name="plus" />{{ t('wells.form.add_generator') }}</button>
                </div>
                <div v-for="(g, i) in form.generators" :key="g.GENERATOR_OID" class="grid grid-4" style="align-items: end">
                    <div class="field"><span class="label">{{ t('wells.form.generator') }}</span><span class="mono">{{ generatorName(g.GENERATOR_OID) }}</span></div>
                    <div class="field"><label>{{ t('wells.form.lph') }}</label><input v-model="g.FUEL_CONSUMPTION_LPH" type="number" step="any" min="0" class="input num" /></div>
                    <div class="field"><label>{{ t('wells.form.tolerance') }}</label><input v-model="g.CONSUMPTION_TOLERANCE_LPH" type="number" step="any" min="0" class="input num" /></div>
                    <button type="button" class="btn btn-ghost" @click="form.generators.splice(i, 1)"><Icon name="x" />{{ t('common.remove') }}</button>
                </div>
                <p class="hint">{{ t('wells.form.generator_hint') }}</p>
            </div>
        </section>

        <section class="panel">
            <div class="panel-head"><h2 class="section-title">{{ t('wells.form.sections.photos') }}</h2><span class="spacer" /><button type="button" class="btn btn-sm" @click="addPhoto"><Icon name="plus" :size="14" />{{ t('wells.form.add_photo') }}</button></div>
            <div class="panel-body stack">
                <div v-if="well?.MEDIA?.length" class="row wrap">
                    <div v-for="m in well.MEDIA" :key="m.OID" class="tag" style="height: auto; padding: 4px 8px; gap: 6px">
                        <a :href="`/wells/media/${m.OID}`" target="_blank" rel="noopener"><Icon name="image" :size="12" /> {{ m.LABEL || t(`wells.form.categories.${m.CATEGORY}`) }}</a>
                        <button type="button" class="btn btn-ghost btn-sm" style="height: 20px" @click="removePhoto(m)"><Icon name="x" :size="12" /></button>
                    </div>
                </div>
                <div v-for="(m, i) in form.media" :key="i" class="grid grid-4" style="align-items: end">
                    <div class="field"><label>{{ t('wells.form.category') }}</label><select v-model="m.category" class="select"><option v-for="c in ['pump', 'fuel_tank', 'cooling', 'valves_parts', 'well_room']" :key="c" :value="c">{{ t(`wells.form.categories.${c}`) }}</option></select></div>
                    <div class="field"><label>{{ t('wells.form.label') }}</label><input v-model="m.label" class="input" maxlength="255" /></div>
                    <div class="field"><label>{{ t('wells.form.file') }}</label><input type="file" accept="image/jpeg,image/png,image/webp" class="input" style="padding-top: 6px" @change="m.file = $event.target.files[0]" /></div>
                    <button type="button" class="btn btn-ghost" @click="form.media.splice(i, 1)"><Icon name="x" />{{ t('common.remove') }}</button>
                </div>
                <p class="hint">{{ t('wells.form.photo_hint') }}</p>
            </div>
        </section>

        <div class="row" style="position: sticky; bottom: 0; padding: var(--s-3) 0; background: var(--bg)">
            <button type="submit" class="btn btn-primary" :disabled="form.processing">{{ t('common.save') }}</button>
            <Link href="/wells/assets" class="btn">{{ t('common.cancel') }}</Link>
            <span v-if="Object.keys(form.errors).length" class="error-text">{{ t('wells.form.has_errors') }}</span>
        </div>
    </form>
</template>

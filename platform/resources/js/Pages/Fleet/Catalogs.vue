<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Icon from '../../Components/Icon.vue';
import PageHeader from '../../Components/PageHeader.vue';
import EmptyState from '../../Components/EmptyState.vue';
import Drawer from '../../Components/Drawer.vue';
import { useI18n } from '../../lib/i18n.js';

const props = defineProps({ type: String, items: Array });
const { t, num } = useI18n();
const editing = ref(null);
const form = useForm({ name: '', category: 'both', sort_order: 0, active: true });

function edit(item = null) {
    editing.value = item ?? {};
    form.clearErrors();
    form.defaults({ name: item?.name ?? '', category: item?.category ?? 'both', sort_order: item?.sort_order ?? 0, active: item ? !!item.active : true });
    form.reset();
}

function save() {
    const id = editing.value?.id ? `/${editing.value.id}` : '';
    form.transform((data) => (props.type === 'locations' ? data : { name: data.name, sort_order: data.sort_order, active: data.active }))
        .post(`/fleet/catalogs/${props.type}${id}`, { preserveScroll: true, onSuccess: () => { editing.value = null; } });
}
</script>

<template>
    <Head :title="t('nav.fleet.catalogs')" />
    <PageHeader :title="t('fleet.catalogs.title')" :description="t('fleet.catalogs.subtitle')">
        <template #actions><button type="button" class="btn btn-primary" @click="edit()"><Icon name="plus" />{{ t(`fleet.catalogs.add_${type}`) }}</button></template>
    </PageHeader>
    <section class="panel">
        <nav class="tabs" style="padding: 0 var(--s-3)">
            <Link v-for="tab in ['locations', 'mission-types']" :key="tab" :href="`/fleet/catalogs?type=${tab}`" :aria-current="type === tab">{{ t(`fleet.catalogs.${tab}`) }}</Link>
        </nav>
        <table v-if="items.length" class="table stackable">
            <thead><tr><th>{{ t('common.name') }}</th><th v-if="type === 'locations'">{{ t('fleet.catalogs.category') }}</th><th class="num">{{ t('fleet.catalogs.order') }}</th><th>{{ t('common.state') }}</th><th /></tr></thead>
            <tbody>
                <tr v-for="item in items" :key="item.id">
                    <td class="cell-main">{{ item.name }}</td>
                    <td v-if="type === 'locations'">{{ t(`fleet.catalogs.categories.${item.category}`) }}</td>
                    <td class="num">{{ num(item.sort_order) }}</td>
                    <td><span class="badge" :class="item.active ? 'success' : ''">{{ t(item.active ? 'common.active' : 'common.inactive') }}</span></td>
                    <td class="num"><button type="button" class="btn btn-ghost btn-sm" @click="edit(item)"><Icon name="pencil" :size="14" />{{ t('common.edit') }}</button></td>
                </tr>
            </tbody>
        </table>
        <EmptyState v-else icon="list" :title="t('fleet.catalogs.empty')" />
    </section>
    <p class="hint" style="margin-top: var(--s-2)">{{ t('fleet.catalogs.used_by_app') }}</p>

    <Drawer v-if="editing" :title="t(editing.id ? 'common.edit' : `fleet.catalogs.add_${type}`)" @close="editing = null">
        <form id="catalog-form" class="stack" @submit.prevent="save">
            <div class="field"><label for="c-name">{{ t('common.name') }}</label><input id="c-name" v-model="form.name" class="input" required maxlength="255" :aria-invalid="!!form.errors.name" /><span v-if="form.errors.name" class="error-text">{{ form.errors.name }}</span></div>
            <div v-if="type === 'locations'" class="field"><label for="c-cat">{{ t('fleet.catalogs.category') }}</label>
                <select id="c-cat" v-model="form.category" class="select"><option v-for="c in ['origin', 'destination', 'both']" :key="c" :value="c">{{ t(`fleet.catalogs.categories.${c}`) }}</option></select></div>
            <div class="field"><label for="c-order">{{ t('fleet.catalogs.order') }}</label><input id="c-order" v-model.number="form.sort_order" type="number" min="0" class="input num" /></div>
            <label class="check"><input v-model="form.active" type="checkbox" />{{ t('fleet.catalogs.active_hint') }}</label>
        </form>
        <template #footer>
            <div class="row"><button type="submit" form="catalog-form" class="btn btn-primary" :disabled="form.processing">{{ t('common.save') }}</button><button type="button" class="btn" @click="editing = null">{{ t('common.cancel') }}</button></div>
        </template>
    </Drawer>
</template>

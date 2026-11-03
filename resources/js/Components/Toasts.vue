<script setup>
import { ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Icon from './Icon.vue';

const page = usePage();
const items = ref([]);
let id = 0;

function push(text, kind) {
    const key = ++id;
    items.value.push({ key, text, kind });
    setTimeout(() => { items.value = items.value.filter((item) => item.key !== key); }, 5000);
}

watch(() => page.props.flash, (flash) => {
    if (flash?.success) push(flash.success, 'success');
    if (flash?.error) push(flash.error, 'error');
}, { immediate: true, deep: true });

watch(() => page.props.errors, (errors) => {
    const first = errors && Object.values(errors)[0];
    if (first) push(first, 'error');
});
</script>

<template>
    <div class="toasts" aria-live="polite">
        <div v-for="item in items" :key="item.key" class="toast" :class="item.kind">
            <Icon :name="item.kind === 'error' ? 'circle-x' : 'circle-check'" />
            <span>{{ item.text }}</span>
        </div>
    </div>
</template>

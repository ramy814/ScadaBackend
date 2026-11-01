<script setup>
import { ref, watch } from 'vue';
import Icon from './Icon.vue';
import { debounce, visit } from '../lib/query.js';

const props = defineProps({ modelValue: { type: String, default: '' }, param: { type: String, default: 'search' }, placeholder: { type: String, default: '' } });
const term = ref(props.modelValue ?? '');
const apply = debounce((value) => visit({ [props.param]: value }), 350);
watch(term, apply);
</script>

<template>
    <label class="search-box">
        <Icon name="search" :size="15" />
        <span class="sr-only">{{ placeholder }}</span>
        <input v-model="term" class="input" type="search" :placeholder="placeholder" />
    </label>
</template>

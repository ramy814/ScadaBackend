<script setup>
import * as icons from '../lib/icons.js';

const props = defineProps({ name: { type: String, required: true }, size: { type: Number, default: 16 } });
</script>

<template>
    <component :is="icons[props.name] ?? icons.info" :size="size" :stroke-width="1.75" aria-hidden="true" />
</template>

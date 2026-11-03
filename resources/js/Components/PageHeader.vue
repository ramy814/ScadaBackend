<script setup>
defineProps({ title: { type: String, required: true }, description: { type: String, default: '' } });
</script>

<template>
    <header class="page-head">
        <div>
            <h1>{{ title }}</h1>
            <p v-if="description">{{ description }}</p>
        </div>
        <div v-if="$slots.actions" class="actions"><slot name="actions" /></div>
    </header>
</template>

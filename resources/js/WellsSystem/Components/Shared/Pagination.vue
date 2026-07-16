<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    links: {
        type: Array,
        required: true,
    },
});
</script>

<template>
    <div v-if="links.length > 3" class="pagination">
        <template v-for="(link, p) in links" :key="p">
            <div v-if="link.url === null" 
                class="page-item disabled" 
                v-html="link.label">
            </div>
            <Link v-else
                :class="['page-item', { 'active': link.active }]"
                :href="link.url"
                v-html="link.label" />
        </template>
    </div>
</template>

<style scoped>
.pagination {
    display: flex;
    gap: 8px;
    justify-content: center;
    padding: 20px;
    flex-wrap: wrap;
}

.page-item {
    padding: 8px 12px;
    border: 1px solid var(--color-border);
    border-radius: 6px;
    background: var(--color-bg-card);
    color: var(--color-text-primary);
    text-decoration: none;
}

.page-item.active {
    background: var(--color-accent);
    border-color: var(--color-accent);
    color: var(--color-text-white);
}

.page-item.disabled {
    background: var(--color-bg-tertiary);
    color: var(--color-text-muted);
    opacity: 0.7;
}
</style>

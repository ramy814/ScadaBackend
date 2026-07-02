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
    <div v-if="links.length > 3" class="pagination" style="display: flex; gap: 8px; justify-content: center; padding: 20px; flex-wrap: wrap;">
        <template v-for="(link, p) in links" :key="p">
            <div v-if="link.url === null" 
                class="page-item disabled" 
                style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; color: #999; background: #f9f9f9;"
                v-html="link.label">
            </div>
            <Link v-else
                :class="['page-item', { 'active': link.active }]"
                style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; text-decoration: none; color: #333;"
                :style="link.active ? 'background-color: var(--color-primary); color: white; border-color: var(--color-primary);' : 'background: white;'"
                :href="link.url"
                v-html="link.label" />
        </template>
    </div>
</template>

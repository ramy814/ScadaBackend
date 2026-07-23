<script setup>
defineProps({
    media: { type: Array, default: () => [] },
    emptyText: { type: String, default: '-' },
})

function mediaUrl(item) {
    return item?.URL || item?.url || ''
}
</script>

<template>
    <div v-if="media.length" class="media-thumbnails">
        <a
            v-for="item in media"
            :key="item.OID || item.oid"
            :href="mediaUrl(item)"
            target="_blank"
            rel="noopener"
            class="media-thumbnail-link"
            title="فتح الصورة بالحجم الكامل"
        >
            <img :src="mediaUrl(item)" alt="صورة مرفقة" loading="lazy">
        </a>
    </div>
    <span v-else class="media-empty">{{ emptyText }}</span>
</template>

<style scoped>
.media-thumbnails {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}

.media-thumbnail-link {
    display: block;
    width: 48px;
    height: 48px;
    overflow: hidden;
    border-radius: 9px;
    border: 1px solid var(--color-border);
    background: var(--color-bg-tertiary);
    transition: border-color 0.15s ease, transform 0.15s ease;
}

.media-thumbnail-link:hover {
    border-color: var(--color-accent);
    transform: translateY(-1px);
}

.media-thumbnail-link img {
    width: 100%;
    height: 100%;
    display: block;
    object-fit: cover;
}

.media-empty {
    color: var(--color-text-muted);
}
</style>

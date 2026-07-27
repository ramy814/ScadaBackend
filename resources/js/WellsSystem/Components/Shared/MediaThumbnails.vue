<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
    media: { type: Array, default: () => [] },
    emptyText: { type: String, default: '-' },
    variant: { type: String, default: 'compact' },
})

const activeIndex = ref(null)
const activeMedia = computed(() => (
    activeIndex.value === null ? null : props.media[activeIndex.value]
))

function mediaUrl(item) {
    return item?.URL || item?.url || ''
}

function mediaName(item, index) {
    return item?.ORIGINAL_NAME || item?.original_name || `صورة ${index + 1}`
}

function openViewer(index) {
    activeIndex.value = index
}

function closeViewer() {
    activeIndex.value = null
}

function previousImage() {
    if (activeIndex.value === null) return
    activeIndex.value = (activeIndex.value - 1 + props.media.length) % props.media.length
}

function nextImage() {
    if (activeIndex.value === null) return
    activeIndex.value = (activeIndex.value + 1) % props.media.length
}
</script>

<template>
    <div
        v-if="media.length"
        :class="['media-thumbnails', { 'media-thumbnails--gallery': variant === 'gallery' }]"
    >
        <button
            v-for="(item, index) in media"
            :key="item.OID || item.oid"
            type="button"
            class="media-thumbnail-link"
            :title="`عرض ${mediaName(item, index)}`"
            :aria-label="`عرض ${mediaName(item, index)}`"
            @click="openViewer(index)"
        >
            <img :src="mediaUrl(item)" :alt="mediaName(item, index)" loading="lazy">
            <span v-if="variant === 'gallery'" class="media-thumbnail-zoom" aria-hidden="true">⌕</span>
        </button>
    </div>
    <span v-else class="media-empty">{{ emptyText }}</span>

    <Teleport to="body">
        <div
            v-if="activeMedia"
            class="media-viewer"
            role="dialog"
            aria-modal="true"
            aria-label="عارض الصور المرفقة"
            @click.self="closeViewer"
        >
            <div class="media-viewer-panel">
                <div class="media-viewer-header">
                    <div>
                        <strong>الصور المرفقة</strong>
                        <span>{{ activeIndex + 1 }} من {{ media.length }}</span>
                    </div>
                    <button type="button" class="media-viewer-close" aria-label="إغلاق" @click="closeViewer">×</button>
                </div>

                <div class="media-viewer-canvas">
                    <button
                        v-if="media.length > 1"
                        type="button"
                        class="media-viewer-nav media-viewer-nav--previous"
                        aria-label="الصورة السابقة"
                        @click="previousImage"
                    >‹</button>
                    <img :src="mediaUrl(activeMedia)" :alt="mediaName(activeMedia, activeIndex)">
                    <button
                        v-if="media.length > 1"
                        type="button"
                        class="media-viewer-nav media-viewer-nav--next"
                        aria-label="الصورة التالية"
                        @click="nextImage"
                    >›</button>
                </div>

                <div class="media-viewer-footer">
                    <span>{{ mediaName(activeMedia, activeIndex) }}</span>
                    <a :href="mediaUrl(activeMedia)" target="_blank" rel="noopener">فتح الصورة الأصلية ↗</a>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
.media-thumbnails {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}

.media-thumbnail-link {
    position: relative;
    display: block;
    width: 48px;
    height: 48px;
    padding: 0;
    overflow: hidden;
    cursor: pointer;
    border-radius: 9px;
    border: 1px solid var(--color-border);
    background: var(--color-bg-tertiary);
    transition: border-color 0.15s ease, transform 0.15s ease, box-shadow 0.15s ease;
}

.media-thumbnails--gallery {
    gap: 10px;
}

.media-thumbnails--gallery .media-thumbnail-link {
    width: 112px;
    height: 82px;
    border-radius: 12px;
}

.media-thumbnail-link:hover,
.media-thumbnail-link:focus-visible {
    border-color: var(--color-accent);
    transform: translateY(-1px);
    box-shadow: 0 8px 22px rgba(15, 23, 42, 0.16);
    outline: none;
}

.media-thumbnail-link img {
    width: 100%;
    height: 100%;
    display: block;
    object-fit: cover;
}

.media-thumbnail-zoom {
    position: absolute;
    left: 7px;
    bottom: 7px;
    display: grid;
    place-items: center;
    width: 25px;
    height: 25px;
    color: #fff;
    font-size: 17px;
    border-radius: 8px;
    background: rgba(15, 23, 42, 0.78);
    backdrop-filter: blur(5px);
}

.media-empty {
    color: var(--color-text-muted);
}

.media-viewer {
    position: fixed;
    inset: 0;
    z-index: 10000;
    display: grid;
    place-items: center;
    padding: 24px;
    background: rgba(2, 6, 23, 0.82);
    backdrop-filter: blur(8px);
}

.media-viewer-panel {
    width: min(980px, 100%);
    max-height: calc(100vh - 48px);
    overflow: hidden;
    border: 1px solid var(--color-border);
    border-radius: 18px;
    background: var(--color-bg-primary);
    box-shadow: 0 24px 70px rgba(0, 0, 0, 0.38);
}

.media-viewer-header,
.media-viewer-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 13px 16px;
    color: var(--color-text-primary);
}

.media-viewer-header > div {
    display: flex;
    align-items: center;
    gap: 10px;
}

.media-viewer-header span,
.media-viewer-footer span {
    color: var(--color-text-muted);
    font-size: 0.82rem;
}

.media-viewer-close {
    width: 36px;
    height: 36px;
    cursor: pointer;
    color: var(--color-text-primary);
    font-size: 1.55rem;
    line-height: 1;
    border: 1px solid var(--color-border);
    border-radius: 10px;
    background: var(--color-bg-tertiary);
}

.media-viewer-canvas {
    position: relative;
    display: grid;
    place-items: center;
    min-height: 300px;
    height: min(68vh, 720px);
    padding: 12px 62px;
    background: #05070b;
}

.media-viewer-canvas img {
    display: block;
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

.media-viewer-nav {
    position: absolute;
    top: 50%;
    z-index: 1;
    width: 42px;
    height: 52px;
    cursor: pointer;
    color: #fff;
    font-size: 2rem;
    line-height: 1;
    border: 1px solid rgba(255, 255, 255, 0.18);
    border-radius: 12px;
    background: rgba(15, 23, 42, 0.72);
    transform: translateY(-50%);
}

.media-viewer-nav--previous { right: 12px; }
.media-viewer-nav--next { left: 12px; }

.media-viewer-footer a {
    color: var(--color-accent);
    font-weight: 700;
    text-decoration: none;
}

@media (max-width: 640px) {
    .media-viewer { padding: 10px; }
    .media-viewer-panel { max-height: calc(100vh - 20px); }
    .media-viewer-canvas { height: 70vh; padding-inline: 48px; }
    .media-thumbnails--gallery .media-thumbnail-link { width: 92px; height: 70px; }
}
</style>

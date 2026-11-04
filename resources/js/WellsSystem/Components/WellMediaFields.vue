<script setup>
import { reactive } from 'vue'

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    media: { type: Array, default: () => [] },
    readonly: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'delete'])

const categories = [
    { key: 'pump', label: 'صور المضخة' },
    { key: 'fuel_tank', label: 'صور خزان الوقود' },
    { key: 'cooling', label: 'صور نظام التبريد' },
    { key: 'valves_parts', label: 'صور المحابس والقطع المتوفرة' },
    { key: 'well_room', label: 'صور غرفة البئر' },
]

const drafts = reactive(Object.fromEntries(categories.map(category => [category.key, {
    label: '',
    file: null,
    inputKey: 0,
    error: '',
}])))

function existingMedia(category) {
    return props.media.filter(item => item.CATEGORY === category)
}

function pendingMedia(category) {
    return props.modelValue
        .map((item, index) => ({ ...item, index }))
        .filter(item => item.category === category)
}

function selectFile(category, event) {
    drafts[category].file = event.target.files?.[0] || null
    drafts[category].error = ''
}

function addMedia(category) {
    const draft = drafts[category]

    if (!draft.file) {
        draft.error = 'اختر صورة أولاً'
        return
    }

    emit('update:modelValue', [...props.modelValue, {
        category,
        label: draft.label.trim(),
        file: draft.file,
    }])

    draft.label = ''
    draft.file = null
    draft.error = ''
    draft.inputKey += 1
}

function removePending(index) {
    emit('update:modelValue', props.modelValue.filter((_, itemIndex) => itemIndex !== index))
}
</script>

<template>
    <div class="well-media-sections">
        <section v-for="category in categories" :key="category.key" class="well-media-section">
            <h4 class="well-media-title">{{ category.label }}</h4>

            <div v-if="!readonly" class="well-media-upload">
                <input
                    v-model="drafts[category.key].label"
                    type="text"
                    class="form-input"
                    placeholder="اسم أو وصف الصورة"
                >
                <input
                    :key="drafts[category.key].inputKey"
                    type="file"
                    class="form-input"
                    accept="image/jpeg,image/png,image/webp"
                    @change="selectFile(category.key, $event)"
                >
                <button type="button" class="btn btn-secondary" @click="addMedia(category.key)">إضافة الصورة</button>
                <span v-if="drafts[category.key].error" class="form-error">{{ drafts[category.key].error }}</span>
            </div>

            <div v-if="existingMedia(category.key).length || pendingMedia(category.key).length" class="well-media-grid">
                <figure v-for="item in existingMedia(category.key)" :key="item.OID" class="well-media-card">
                    <img :src="item.URL" :alt="item.LABEL || item.ORIGINAL_NAME" loading="lazy">
                    <figcaption>{{ item.LABEL || item.ORIGINAL_NAME }}</figcaption>
                    <button
                        v-if="!readonly"
                        type="button"
                        class="btn btn-sm btn-danger"
                        @click="emit('delete', item.OID)"
                    >حذف</button>
                </figure>

                <div v-for="item in pendingMedia(category.key)" :key="`${category.key}-${item.index}`" class="well-media-card well-media-pending">
                    <strong>{{ item.label || item.file.name }}</strong>
                    <small>{{ item.file.name }}</small>
                    <button type="button" class="btn btn-sm btn-danger" @click="removePending(item.index)">إزالة</button>
                </div>
            </div>
            <p v-else class="form-hint">لا توجد صور مضافة</p>
        </section>
    </div>
</template>

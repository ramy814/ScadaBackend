<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue';

const props = defineProps({
    modelValue: { type: [String, Number, null], default: null },
    options: { type: Array, default: () => [] },
    placeholder: { type: String, default: 'اختر...' },
    valueKey: { type: String, default: 'value' },
    labelKey: { type: String, default: 'label' },
    disabled: { type: Boolean, default: false },
    searchable: { type: Boolean, default: true },
});

const emit = defineEmits(['update:modelValue']);

const isOpen = ref(false);
const searchQuery = ref('');
const dropdownRef = ref(null);
const searchInputRef = ref(null);

const selectedLabel = computed(() => {
    const option = props.options.find(o => {
        const val = typeof o === 'object' ? o[props.valueKey] : o;
        return val == props.modelValue;
    });
    if (!option) return props.placeholder;
    return typeof option === 'object' ? option[props.labelKey] : option;
});

const filteredOptions = computed(() => {
    if (!props.searchable || !searchQuery.value) return props.options;
    const query = searchQuery.value.toLowerCase().trim();
    return props.options.filter(o => {
        const text = typeof o === 'object' ? o[props.labelKey] : String(o);
        return String(text).toLowerCase().includes(query);
    });
});

const toggle = () => {
    if (props.disabled) return;
    isOpen.value = !isOpen.value;
    if (isOpen.value) {
        searchQuery.value = '';
        nextTick(() => {
            if (searchInputRef.value) {
                searchInputRef.value.focus();
            }
        });
    }
};

const select = (option) => {
    const val = typeof option === 'object' ? option[props.valueKey] : option;
    emit('update:modelValue', val);
    isOpen.value = false;
};

const handleClickOutside = (e) => {
    if (dropdownRef.value && !dropdownRef.value.contains(e.target)) {
        isOpen.value = false;
    }
};

onMounted(() => document.addEventListener('click', handleClickOutside));
onBeforeUnmount(() => document.removeEventListener('click', handleClickOutside));
</script>

<template>
    <div class="dropdown-select-container" ref="dropdownRef" style="position: relative; width: 100%;">
        <button
            type="button"
            class="form-select"
            :disabled="disabled"
            @click="toggle"
            style="text-align: right; cursor: pointer; display: flex; align-items: center; justify-content: space-between; width: 100%;"
        >
            <span>{{ selectedLabel }}</span>
        </button>

        <div v-if="isOpen" class="dropdown-menu" style="position: absolute; top: 100%; right: 0; left: 0; z-index: 9999; width: 100%; max-height: 280px; background: var(--color-bg-secondary); border: 1px solid var(--glass-border); border-radius: var(--border-radius-sm); margin-top: 4px; display: flex; flex-direction: column; overflow: hidden; box-shadow: var(--shadow-lg);">
            <div v-if="searchable" style="padding: 8px; border-bottom: 1px solid var(--glass-border); background: var(--color-bg-primary);">
                <input
                    ref="searchInputRef"
                    v-model="searchQuery"
                    type="text"
                    placeholder="ابحث..."
                    style="width: 100%; padding: 8px 12px; border: 1px solid var(--glass-border); border-radius: var(--border-radius-sm); background: var(--color-bg-input); color: var(--color-text-primary); font-size: 14px; outline: none; direction: rtl;"
                    @click.stop
                />
            </div>
            <div style="overflow-y: auto; flex: 1; max-height: 200px;">
                <button
                    v-for="option in filteredOptions"
                    :key="typeof option === 'object' ? option[valueKey] : option"
                    type="button"
                    class="dropdown-item"
                    style="width: 100%; text-align: right; padding: 10px 16px; border: none; background: transparent; color: var(--color-text-primary); cursor: pointer; transition: background 0.2s; font-size: 14px;"
                    @click="select(option)"
                >
                    {{ typeof option === 'object' ? option[labelKey] : option }}
                </button>
                <div v-if="filteredOptions.length === 0" style="padding: 12px; text-align: center; color: var(--color-text-muted); font-size: 14px;">
                    لا توجد نتائج مطابقة
                </div>
            </div>
        </div>
    </div>
</template>
